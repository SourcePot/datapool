<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Calendar;

class Calendar implements \SourcePot\Datapool\Interfaces\Job,\SourcePot\Datapool\Interfaces\App,\SourcePot\Datapool\Interfaces\HomeApp{
    
    private const APP_ACCESS='ALL_MEMBER_R';
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=[
        'Group'=>['value'=>'Events','type'=>'VARCHAR(255)','Description'=>'This is the Group category'],
        'Folder'=>['value'=>'event','type'=>'VARCHAR(255)','Description'=>'This is the Group category'],
        'Start'=>['value'=>'{{nowDateUTC}}','type'=>'DATETIME','Description'=>'Is the start of an event, event, etc.'],
        'End'=>['value'=>'{{TOMORROW}}','type'=>'DATETIME','Description'=>'Is the end of an event, event, etc.']
        ];

    private $setting=[];
    private $toReplace=[];

    private $pageState=[];
    private $pageStateTemplate=[];

    private const DEFINITION=[
        'Type'=>['@tag'=>'p','@Read'=>'NO_R'],
        'Map'=>['@function'=>'getMapHtml','@class'=>'SourcePot\Datapool\Tools\GeoTools','@default'=>''],
        'Content'=>[
            'Event'=>[
                'Description'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                'Type'=>['@function'=>'select','@options'=>['meeting'=>'Meeting','travel'=>'Travel','event'=>'Event','toTo'=>'To do'],'@default'=>'meeting','@excontainer'=>TRUE],
                'Start'=>['@tag'=>'input','@type'=>'datetime-local','@default'=>'{{nowDateUTC}})','@excontainer'=>TRUE],
                'Start timezone'=>['@function'=>'select','@default'=>'{{TIMEZONE-SERVER}}','@options'=>self::OPTIONS['Timezone'],'@excontainer'=>TRUE],
                'End'=>['@tag'=>'input','@type'=>'datetime-local','@default'=>'{{TOMORROW}})','@excontainer'=>TRUE],
                'End timezone'=>['@function'=>'select','@default'=>'{{TIMEZONE-SERVER}}','@options'=>self::OPTIONS['Timezone'],'@excontainer'=>TRUE],
                'Save'=>['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'],
                ],
            'Location/Destination'=>[
                'Company'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                'Department'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                'Street'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                'House number'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                'Town'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                'Zip'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                'Country'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                'Save'=>['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save','@isApp'=>'&#127758;'],
                ],
            ],
        'Misc'=>['@function'=>'entryControls','@isApp'=>'&#128736;','@hideHeader'=>TRUE,'@hideKeys'=>TRUE,'@hideCaption'=>FALSE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'],
        'Read'=>['@function'=>'integerEditor','@default'=>'ALL_MEMBER_R','@key'=>'Read','@isApp'=>'R','@hideHeader'=>TRUE,'@hideKeys'=>TRUE,'@hideCaption'=>TRUE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'],
        'Write'=>['@function'=>'integerEditor','@default'=>'ALL_CONTENTADMIN_R','@key'=>'Write','@isApp'=>'W','@hideHeader'=>TRUE,'@hideKeys'=>TRUE,'@hideCaption'=>TRUE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'],
        ];

    private const OPTIONS=[
        'Type'=>['event'=>'Event','trip'=>'Trip','meeting'=>'Meeting','todo'=>'To do','done'=>'To do done','training_0'=>'Training scheduled','training_1'=>'Training prepared','training_2'=>'Training canceled','training_3'=>'Training no-show'],
        'Days to show'=>[10=>'Show 10 days',20=>'Show 20 days',45=>'Show 45 days',90=>'Show 90 days',180=>'Show 180 days',370=>'Show 370 days'],
        'Day width'=>[200=>'Small day width',400=>'Middle day width',800=>'Big day width',1600=>'Biggest day width'],
        'Timezone'=>[
            'Europe/Berlin'=>'+1 Europe/Berlin','Europe/London'=>'0 Europe/London','Atlantic/Azores'=>'-1 Atlantic/Azores','Atlantic/South_Georgia'=>'-2 Atlantic/South_Georgia',
            'America/Sao_Paulo'=>'-3 America/Sao_Paulo','America/Halifax'=>'-4 America/Halifax','America/New_York'=>'-5 America/New York','America/Mexico_City'=>'-6 America/Mexico City',
            'America/Denver'=>'-7 America/Denver','America/Vancouver'=>'-8 America/Vancouver','America/Anchorage'=>'-9 America/Anchorage','Pacific/Honolulu'=>'-10 Pacific/Honolulu',
            'Pacific/Midway'=>'-11 Pacific/Midway','Pacific/Kiritimati'=>'-12 Pacific/Kiritimati','Pacific/Fiji'=>'+12 Pacific/Fiji','Asia/Magadan'=>'+11 Asia/Magadan',
            'Pacific/Guam'=>'+10 Pacific/Guam','Asia/Tokyo'=>'+9 Asia/Tokyo','Asia/Shanghai'=>'+8 Asia/Shanghai','Asia/Novosibirsk'=>'+7 Asia/Novosibirsk','Asia/Omsk'=>'+6 Asia/Omsk',
            'Asia/Yekaterinburg'=>'+5 Asia/Yekaterinburg','Europe/Samara'=>'+4 Europe/Samara','Europe/Moscow'=>'+3 Europe/Moscow','Africa/Cairo'=>'+2 Africa/Cairo','UTC'=>'UTC'
            ],
        ];

    public function __construct($oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,self::DEFINITION);
        // get settings
        $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        $settingKey=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId();
        $setting=['Days to show'=>45,'Day width'=>400,'Timezone'=>$pageTimeZone];
        $this->setting=$this->oc['SourcePot\Datapool\AdminApps\Settings']->getSetting(__CLASS__,$settingKey,$setting,'Calendar',FALSE);
        $this->setting=(empty($this->setting['Timezone']))?$setting:$this->setting;
        // get page state
        $this->pageStateTemplate=['Source'=>$this->entryTable,'EntryId'=>'{{EntryId}}','calendarDate'=>date('Y-m-d H:i:s'),'addDate'=>'','refreshInterval'=>0];
        $this->pageState=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__,$this->pageStateTemplate);
        $this->pageState=$this->oc['SourcePot\Datapool\Root']->substituteWithPlaceholder($this->pageState);
    }

    /**
    * Housekeeping method periodically executed by job.php (this script should be called once per minute through a CRON-job)
    * @param    string $vars Initial persistent data space
    * @return   array  Array Updateed persistent data space
    */
    public function job(array $vars):array
    {
        // add bank holidays
        if (!isset($vars['Bank holidays'])){$vars['Bank holidays']['lastRun']=0;}
        if (!isset($vars['Signal cleanup'])){$vars['Signal cleanup']['lastRun']=0;}
        $action='';
        if (time()-$vars['Bank holidays']['lastRun']>26000){
            // load bank holidays
            $action='Bank holidays';
            $setting=$this->oc['SourcePot\Datapool\AdminApps\Settings']->getSetting(__CLASS__,'getJobSettings',[],'Job selected countries and regions',TRUE);
            $countriesRegions=[];
            $vars['Bank holidays']['Year']=intval(date('Y'))+mt_rand(-1,2);
            foreach($setting as $countryCode=>$regions){
                foreach($regions as $region=>$active){
                    if (empty($active)){continue;}
                    $countriesRegions[]=$region.' ('.strtoupper($countryCode).')';
                    $holidayObj=new \SourcePot\BankHolidays\holidays($vars['Bank holidays']['Year'],$countryCode);
                    foreach($holidayObj->datapoolHolidays($region,['Owner'=>'SYSTEM'],\SourcePot\Datapool\Root::DB_TIMEZONE) as $event){
                        $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($event,TRUE);
                    }
                }
            }
            $vars['Bank holidays']=array_merge($vars['Bank holidays'],$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic());
            $vars['Bank holidays']['Countries & regions']=implode(', ',$countriesRegions);
            $vars['Bank holidays']['lastRun']=time();
            return $vars;
        } else if (time()-$vars['Signal cleanup']['lastRun']>725361){
            // delete signals without a linked calendar entry
            $action='Signal cleanup';
            $this->oc['SourcePot\Datapool\Foundation\Signals']->removeSignalsWithoutSource(__CLASS__,__FUNCTION__);
            $vars['Signal cleanup']['lastRun']=time();
            $vars['Signal cleanup']=array_merge($vars['Signal cleanup'],$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic());
            return $vars;
        } else {
            // get relevant timespan
            $action='Signals';
            $vars['Signals']['Period start']=$vars['Signals']['Period start']??time();
            $vars['Signals']['Period end']=time();
            $startDateTime=new \DateTime();
            $startDateTime->setTimestamp($vars['Signals']['Period start']); 
            $startDateTime->setTimezone(new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
            $startWindow=$startDateTime->format('Y-m-d H:i:s');
            $endDateTime=new \DateTime();
            $endDateTime->setTimestamp($vars['Signals']['Period end']); 
            $endDateTime->setTimezone(new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
            $endWindow=$endDateTime->format('Y-m-d H:i:s');
            // scan calendar entries
            $events=[];
            $selector=['Source'=>$this->entryTable,'Group!'=>'Serial%','End>'=>$startWindow];
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','Name',TRUE,FALSE,FALSE) as $event){
                $vars['Signals']['Relevant calendar entries found']=$event['rowCount'];
                $events[$event['Name']]=intval($event['Start']<$endWindow);
            }
            // get serial events for the time window between the last run and now
            $selector=['Source'=>$this->entryTable,'Group'=>'Serial events'];
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','Name',TRUE,FALSE,FALSE) as $serialEvent){
                $vars['Signals']['Relevant serial events found']=$serialEvent['rowCount'];
                $serialEntries=$this->serialEntryToEntries($serialEvent,$vars['Signals']['Period start'],$vars['Signals']['Period end']);
                $events[$serialEvent['Name']]=intval(!empty($serialEntries));
            }
            // update signals
            foreach($events as $name=>$value){
                $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,$name,$value,'bool'); 
            }
            $vars['Signals']['Window start ('.\SourcePot\Datapool\Root::DB_TIMEZONE.')']=$startWindow;
            $vars['Signals']['Window end ('.\SourcePot\Datapool\Root::DB_TIMEZONE.')']=$endWindow;
            $vars['Signals']['Period start']=time();
        }
        $vars[$action]=array_merge($vars[$action]??[],$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic());
        $vars['Last action']=['Done'=>$action,'Date & time'=>time()];
        return $vars;
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
    
    public function getAvailableTimezones()
    {
        return self::OPTIONS['Timezone'];
    }

    private function stdReplacements($str='')
    {
        if (is_array($str)){return $str;}
        if (isset($this->oc['SourcePot\Datapool\Foundation\Database'])){
            $this->toReplace=$this->oc['SourcePot\Datapool\Foundation\Database']->enrichToReplace($this->toReplace);
        }
        foreach($this->toReplace as $needle=>$replacement){$str=str_replace($needle,$replacement,$str);}
        return $str;
    }

    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return ['Category'=>'Calendar','Emoji'=>'&#9992;','Label'=>'Calendar','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            $html='';
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Calendar by '.__FUNCTION__,'generic',$this->pageState,['method'=>'getCalendar','classWithNamespace'=>__CLASS__],['style'=>[]]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Serial events by '.__FUNCTION__,'generic',$this->pageState,['method'=>'getSerialEventsFrom','classWithNamespace'=>__CLASS__],['style'=>[]]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Job settings '.__FUNCTION__,'generic',$this->pageState,['method'=>'getJobSettings','classWithNamespace'=>__CLASS__],['style'=>[]]);
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    public function unifyEntry($entry)
    {
        $entry['Source']=$this->entryTable;    
        $entry['Folder']=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId();
        if (empty($entry['Group'])){$entry['Group']='Events';}
        if ((strcmp($entry['Group'],'Events')===0 || strcmp($entry['Group'],'Bank holidays')===0) && isset($entry['addDate'])){
            // Standard events
            $entry['Content']['Event']['Start']=(isset($entry['Content']['Event']['Start']))?$entry['Content']['Event']['Start']:$entry['addDate'].'T00:00';
            $entry['Content']['Event']['Start timezone']=(isset($entry['Content']['Event']['Start timezone']))?$entry['Content']['Event']['Start timezone']:$this->setting['Timezone'];
            $entry['Content']['Event']['End']=(isset($entry['Content']['Event']['End']))?$entry['Content']['Event']['End']:$entry['addDate'].'T23:59';
            $entry['Content']['Event']['End timezone']=(isset($entry['Content']['Event']['End timezone']))?$entry['Content']['Event']['End timezone']:$this->setting['Timezone'];
            $entry['Name']=(isset($entry['Name']))?$entry['Name']:'';
            $entry['Content']['Event']['Description']=(isset($entry['Content']['Event']['Description']))?$entry['Content']['Event']['Description']:'';
            $entry['Name']=(empty($entry['Content']['Event']['Description']))?$entry['Name']:mb_substr($entry['Content']['Event']['Description'],0,200);
            $entry['Start']=(empty($entry['Content']['Event']['Start']))?$entry['Start']:$this->getTimezoneDate($entry['Content']['Event']['Start'],$entry['Content']['Event']['Start timezone'],\SourcePot\Datapool\Root::DB_TIMEZONE);
            $entry['End']=(empty($entry['Content']['Event']['End']))?$entry['End']:$this->getTimezoneDate($entry['Content']['Event']['End'],$entry['Content']['Event']['End timezone'],\SourcePot\Datapool\Root::DB_TIMEZONE);
        } else {
            // Serial events
            if (isset($entry['Content']['Name'])){$entry['Name']=$entry['Content']['Name'];}
            if (isset($entry['Content']['Visibility'])){$entry['Read']=$entry['Content']['Visibility'];}
        }
        $entry=$this->oc['SourcePot\Datapool\Tools\GeoTools']->address2location($entry);
        return $entry;
    }
    
    public function getCalendar($arr)
    {
        if (!isset($arr['html'])){$arr['html']='';}
        $this->eventsFormProcessing();
        $settingsArr=$this->getCalendarSettings($arr);
        $entryArr=$this->getCalendarEntry($arr);
        $calendarArr=$this->getCalendarSheet($arr);
        $arr['html'].=$settingsArr['html'];
        $arr['html'].=$calendarArr['html'];
        $arr['html'].=$entryArr['html'];
        return $arr;
    }
    
    public function getSerialEventsFrom($arr=[]):array
    {
        $monthOptions=[''=>''];
        $weekOptions=[''=>''];
        $monthdayOptions=[''=>''];
        $weekdayOptions=[''=>'','1'=>'Monday','2'=>'Tuesday','3'=>'Wednesday','4'=>'Thursday','5'=>'Friday','6'=>'Saturday','7'=>'Sunday'];
        $hourOptions=[''=>''];
        $minOptions=[''=>''];
        $weekdayOptions=[''=>'','1'=>'Monday','2'=>'Tuesday','3'=>'Wednesday','4'=>'Thursday','5'=>'Friday','6'=>'Saturday','7'=>'Sunday'];
        $durationOptions=[10=>'10 min',15=>'15 min',60=>'1 hour',120=>'2 hours',360=>'6 hours',480=>'8 hours',720=>'12 hours',1440=>'24 hours'];
        for($index=0;$index<60;$index++){
            $shortIndex=strval($index);
            $fullIndex=(strlen($shortIndex)<2)?'0'.$shortIndex:$shortIndex;
            if ($index>0){
                if ($index<13){$monthOptions[$fullIndex]=$fullIndex;}
                if ($index<54){$weekOptions[$fullIndex]=$fullIndex;}
                if ($index<32){$monthdayOptions[$fullIndex]=$fullIndex;}
            }
            if ($index<25){$hourOptions[$fullIndex]=$fullIndex;}
            if ($index<60){$minOptions[$fullIndex]=$fullIndex;}
        }
        $contentStructure=[
            'Name'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'Serial event','excontainer'=>TRUE],
            'Type'=>['method'=>'select','excontainer'=>TRUE,'value'=>current(self::OPTIONS['Type']),'options'=>self::OPTIONS['Type']],
            'Month'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$monthOptions],
            'Day'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$monthdayOptions],
            'Week day'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$weekdayOptions],
            'Hour'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$hourOptions],
            'Minute'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$minOptions],
            'Duration'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$durationOptions],
            'Timezone'=>['method'=>'select','excontainer'=>TRUE,'value'=>\SourcePot\Datapool\Root::DB_TIMEZONE,'options'=>self::OPTIONS['Timezone']],
            'Visibility'=>['method'=>'select','excontainer'=>TRUE,'value'=>32768,'options'=>$this->oc['SourcePot\Datapool\Foundation\User']->getUserRoles(TRUE)],
            ];
        $currentUserId=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId();
        $arr['selector']=['Source'=>$this->entryTable,'Group'=>'Serial events','Folder'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),'EntryId'=>$currentUserId,'owner'=>$currentUserId];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Serial events definition';
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $arr;
    }
    
    private function getCalendarEntry($arr=[]):array
    {
        $template=['html'=>'','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $arr=array_merge($template,$arr);
        $event=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($this->pageState);
        if (empty($event)){
            $emptyEvent=TRUE;
            $event=$this->pageState;
        }
        if (!empty($emptyEvent) && empty($this->pageState['addDate'])){
            $arr['html'].=$this->getEventsOverview($arr);
        } else if(mb_strpos(strval($event['EntryId']),'___')!==FALSE){
            // serial event selected
            if (isset($event['Content']['File content'])){unset($event['Content']['File content']);}
            $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($event['Content']);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'caption'=>'Serial event','hideKeys'=>TRUE,'hideHeader'=>TRUE]);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryControls(['selector'=>$event]);
        } else {
            $event=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($event);
            $event['owner']=(empty($event['owner']))?$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId():$event['owner'];
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($event);
        }
        return $arr;        
    }
    
    private function getCalendarSettings($arr=[]):array
    {
        $arr['html']=$arr['html']??'';
        $arr['keep-element-content']=TRUE;
        $arr['callingClass']=__CLASS__;
        $arr['callingFunction']=__FUNCTION__;
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['Home']) || isset($formData['cmd']['Set'])){
            $this->pageState['Group']='Events';
            $this->pageState['EntryId']='{{EntryId}}';
            if (isset($formData['cmd']['Set'])){
                $this->pageState['calendarDate']=$formData['val']['pageState']['calendarDate'];
            } else {
                $this->pageState['calendarDate']='{{YESTERDAY}}';
            }
            $this->pageState['addDate']='';
            $this->pageState=$this->oc['SourcePot\Datapool\Root']->substituteWithPlaceholder($this->pageState);
            $this->pageState=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState(__CLASS__,$this->pageState);
        } else if (!empty($formData['val']['pageState'])){
            $this->resetEventCache();
            $this->pageState=array_merge($this->pageStateTemplate,$this->pageState,$formData['val']['pageState']);
            $this->pageState=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState(__CLASS__,$this->pageState);
            $this->setting=$this->oc['SourcePot\Datapool\AdminApps\Settings']->setSetting(__CLASS__,$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),$formData['val']['setting'],'Calendar',FALSE);
        }
        $tagArr=$btnArr=$arr;
        $tagArr['style']=['padding'=>'7px 3px'];
        $btnArr['style']=['font-size'=>'20px','padding'=>'2px'];
        $btnArr['tag']='button';
        $btnArr['excontainer']=FALSE;
        $btnArr['key']=['Home'];
        $btnArr['title']='Home';
        $btnArr['element-content']='&#9750;';
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        $calendarDateArr=$tagArr;
        $calendarDateArr['tag']='input';
        $calendarDateArr['type']='date';
        $calendarDateArr['title']='Press enter to select';
        $calendarDateArr['value']=$this->timeStamp2pageDateTime($this->calendarStartTimestamp(),'Y-m-d');
        $calendarDateArr['key']=['pageState','calendarDate'];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($calendarDateArr);
        $btnArr['key']=['Set'];
        $btnArr['title']=$btnArr['key'][0];
        $btnArr['element-content']='&#10022;';
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'calendar-settings-wrapper']);
        //
        $timezoneArr=$tagArr;
        $timezoneArr['selected']=$this->setting['Timezone'];
        $timezoneArr['options']=self::OPTIONS['Timezone'];
        $timezoneArr['key']=['setting','Timezone'];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($timezoneArr);
        $daysToShowArr=$tagArr;
        $daysToShowArr['selected']=$this->setting['Days to show'];
        $daysToShowArr['options']=self::OPTIONS['Days to show'];
        $daysToShowArr['key']=['setting','Days to show'];
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($daysToShowArr);
        $dayWidthArr=$tagArr;
        $dayWidthArr['selected']=$this->setting['Day width'];
        $dayWidthArr['options']=self::OPTIONS['Day width'];
        $dayWidthArr['key']=['setting','Day width'];
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($dayWidthArr);
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'calendar-settings-wrapper']);
        return $arr;
    }
    
    public function getJobSettings($arr=[]):array
    {
        $arr['html']=$arr['html']??'';
        if (!$this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            return $arr;
        }
        // get setting
        $setting=$this->oc['SourcePot\Datapool\AdminApps\Settings']->getSetting(__CLASS__,'getJobSettings',[],'Job selected countries and regions',TRUE);
        // update bank holiday setting from form
        $activeCountry=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],__FUNCTION__);
        if (!empty($formData['val'])){
            foreach(\SourcePot\BankHolidays\holidays::getAvailableCountries() as $countryCode=>$countryName){
                $activeCountry[$countryCode]=!empty($formData['changed'][$countryCode]);
                foreach(\SourcePot\BankHolidays\holidays::getAvailableRegions($countryCode) as $region){
                    $setting[$countryCode][$region]=!empty($formData['val'][$countryCode][$region]);
                }
            }        
            $this->oc['SourcePot\Datapool\AdminApps\Settings']->setSetting(__CLASS__,'getJobSettings',$setting,'Job selected countries and regions',TRUE);
        }
        // compile bank holiday settings html
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h1','element-content'=>'Relevant bank holidays']);
        foreach(\SourcePot\BankHolidays\holidays::getAvailableCountries() as $countryCode=>$countryName){
            $appHtml='';
            foreach(\SourcePot\BankHolidays\holidays::getAvailableRegions($countryCode) as $region){
                $id=md5($countryCode.'|'.$region);
                $setting[$countryCode][$region]=$setting[$countryCode][$region]??FALSE;
                $htmlRegion=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'checkbox','checked'=>$setting[$countryCode][$region],'id'=>$id,'key'=>[$countryCode,$region],'callingClass'=>$arr['callingClass'],'callingFunction'=>__FUNCTION__,'title'=>$region.' of '.$countryName,'excontainer'=>FALSE]);
                $htmlRegion.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'label','for'=>$id,'element-content'=>$region]);
                $appHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$htmlRegion,'keep-element-content'=>TRUE,'class'=>'fieldset']);
            }
            $app=['icon'=>$countryName,'title'=>$countryName,'html'=>$appHtml,'open'=>!empty($activeCountry[$countryCode])];
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($app);
        }
        // compile job var space overview
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h1','element-content'=>'Calendar job var-space']);
        $jobVarSpace=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(['Source'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Job processing','Folder'=>'Var space','Name'=>__CLASS__]);
        $jobVarSpace['Content']=$jobVarSpace['Content']??[];
        $valueMatrix=[];
        foreach($jobVarSpace['Content'] as $key=>$value){
            if (is_array($value)){
                $matrix=[];
                foreach($value as $subKey=>$subValue){
                    if (in_array($subKey,['Period start','Period end','lastRun','Date & time'])){$subValue=$this->timeStamp2pageDateTime($subValue);}
                    $matrix[$subKey]=['value'=>$subValue];
                }
                $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>$key,'hideKeys'=>FALSE,'hideHeader'=>TRUE]);
            } else {
                if (in_array($key,['Period start','Period end','lastRun','Date & time'])){$value=$this->timeStamp2pageDateTime($value);}
                $valueMatrix[$key]=['value'=>$value];
            }
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$valueMatrix,'keep-element-content'=>TRUE,'caption'=>'Values','hideKeys'=>FALSE,'hideHeader'=>TRUE]);
        return $arr;
    }

    private function getEventsOverview()
    {
        $matrices=[];
        $events=$this->getEvents(time());
        foreach($events as $EntryId=>$event){
            if (isset($matrices[$event['State']])){
                if (count($matrices[$event['State']])>5){
                    $valueArr=current($matrices[$event['State']]);
                    $valueArr=array_flip($valueArr);
                    $valueArr=array_fill_keys(array_keys($valueArr),'...');
                    $matrices[$event['State']]['...']=$valueArr;
                    continue;
                }
            }
            if (mb_strpos($event['State'],'Upcomming')!==FALSE){
                $matrices[$event['State']][$EntryId]=['Event'=>$event['Name'],'Starts&nbsp;in'=>$this->getTimeDiff($event['Start'],'now',\SourcePot\Datapool\Root::DB_TIMEZONE,\SourcePot\Datapool\Root::DB_TIMEZONE)];
            } else {
                $matrices[$event['State']][$EntryId]=['Event'=>$event['Name'],'Ends&nbsp;in'=>$this->getTimeDiff($event['End'],'now',\SourcePot\Datapool\Root::DB_TIMEZONE,\SourcePot\Datapool\Root::DB_TIMEZONE)];
            }
        }
        $html='';
        foreach($matrices as $caption=>$matrix){
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>$caption,'hideKeys'=>TRUE]);
        }
        return $html;
    }
    
    public function getCalendarSheet($arr=[])
    {
        $template=['html'=>'','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $arr=array_merge($template,$arr);
        $style=['left'=>$this->date2pos()];
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>'','keep-element-content'=>TRUE,'class'=>'calendar-timeline','style'=>$style]);
            
        $lastDayPos=0;
        $dayInterval=\DateInterval::createFromDateString('1 days');
        $calendarDateTime=new \DateTime('@'.$this->calendarStartTimestamp());
        $calendarDateTime->setTimezone(new \DateTimeZone($this->setting['Timezone']));
        for($day=0;$day<$this->setting['Days to show'];$day++){
            //var_dump($calendarDateTime->format('Y-m-d H:i:s'));
            $weekDay=$calendarDateTime->format('D');
            $date=$calendarDateTime->format('Y-m-d');
            $dayContent=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng('Week').' '.intval($calendarDateTime->format('W')).', '.$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng($weekDay).'<br/>';
            $dayContent.=$date;
            $calendarDateTime->add($dayInterval);
            $newDayPos=$this->date2pos($calendarDateTime->format('Y-m-d H:i:s'));
            $dayStyle=['left'=>$lastDayPos,'width'=>$newDayPos-$lastDayPos-1];
            if ($date==$this->pageState['addDate']??''){$dayStyle['background-color']='#f008';}
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>'','keep-element-content'=>TRUE,'class'=>'calendar-day','style'=>$dayStyle]);
            $dayStyle=['left'=>$lastDayPos,'width'=>$newDayPos-$lastDayPos-1];
            if (strcmp($weekDay,'Sun')===0 || strcmp($weekDay,'Sat')===0){$dayStyle['background-color']='#af6';}
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>$dayContent,'keep-element-content'=>TRUE,'key'=>['Add',$date],'title'=>'Click here to open a new event','callingClass'=>__CLASS__,'callingFunction'=>'addEvents','class'=>'calendar-day','style'=>$dayStyle]);
            $arr['html'].=$this->timeLineHtml($date);
            $lastDayPos=$newDayPos;
        }
        $arr=$this->addEvents($arr);
        $wrapperStyle=['width'=>$this->getCalendarWidth(),'height'=>$arr['calendarSheetHeight']];
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'class'=>'calendar-sheet','style'=>$wrapperStyle]);
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'class'=>'calendar-sheet-wrapper']);
        return $arr;
    }
    
    private function eventsFormProcessing()
    {
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'addEvents');
        if (isset($formData['cmd']['EntryId'])){
            // select entry
            $selector=$this->pageState;
            $selector['EntryId']=key($formData['cmd']['EntryId']);
            $this->pageState['EntryId']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'EntryId',$selector['EntryId']);
            if (mb_strpos($selector['EntryId'],'___')===FALSE){
                $event=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
                $this->pageState['calendarDate']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'calendarDate',$event['Start']);
                $this->pageState['addDate']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'addDate','');
            }
        } else if (isset($formData['cmd']['Add'])){
            // add new entry
            $this->pageState['EntryId']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'EntryId','{{EntryId}}');
            $this->pageState['calendarDate']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'calendarDate',key($formData['cmd']['Add']));
            $this->pageState['addDate']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'addDate',key($formData['cmd']['Add']));
        }
        return $formData;
    }
    
    private function addEvents($arr)
    {
        $timestamp=$this->calendarStartTimestamp();
        $events=$this->getEvents($timestamp);
        $arr['calendarSheetHeight']=120;
        foreach($events as $EntryId=>$event){
            if (empty($event['Content']['Event']['Start']) || empty($event['Content']['Event']['End'])){continue;}
            if (is_array($event['Content']['Event']['Start'])){implode(' ',$event['Content']['Event']['Start']);}
            if (is_array($event['Content']['Event']['End'])){implode(' ',$event['Content']['Event']['End']);}
            $style=[];
            $style['top']=100+$event['y']*40;
            if ($style['top']+50>$arr['calendarSheetHeight']){$arr['calendarSheetHeight']=$style['top']+50;}
            $style['left']=$event['x0'];
            $style['width']=$event['x1']-$event['x0']-2;
            if ($style['width']<10){$style['width']=10;}
            $class='calendar-event';
            if (!empty($this->pageState['EntryId'])){
                if (mb_strpos($EntryId,$this->pageState['EntryId'])!==FALSE){
                    $class='calendar-event-selected';
                }
            }
            if ($event['Group']=="Bank holidays"){$style['background-color']='#71d71f';} else {$style['background-color']='#c0b3ee';}
            $title=$event['Name']."\n";
            $title.=str_replace('T',' ',$event['Content']['Event']['Start']).' ('.$event['Content']['Event']['Start timezone'].")\n";
            $title.=str_replace('T',' ',$event['Content']['Event']['End']).' ('.$event['Content']['Event']['End timezone'].')';
            $btnArr=['tag'=>'button','element-content'=>$event['Name'],'title'=>$title,'key'=>['EntryId',$EntryId],'entry-id'=>$EntryId,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>$class,'style'=>$style];
            if ($event['State']=='Serial event'){
                $btnArr['key'][1]=$event['SerailEntryId'];
            }
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        }
        return $arr;
    }
        
    private function timeLineHtml($date)
    {
        $html='';
        $lastPos=0;
        for($h=0;$h<24;$h++){
            if ($h<10){$dateTime=$date.' 0'.$h;} else {$dateTime=$date.' '.$h;}
            $dateTime.=':00:00';
            $newPos=$this->date2pos($dateTime);
            if ($newPos-$lastPos<30){continue;}
            $content=strval($h);
            $contentPos=round($newPos-(strlen($content)*10)/2);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>$content,'class'=>'calendar-hour','style'=>['left'=>$contentPos]]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>'','class'=>'calendar-hour','style'=>['left'=>$newPos]]);
            $lastPos=$newPos;
        }
        return $html;
    }
    
    private function calendarStartTimestamp()
    {
        $calendarTimezone=new \DateTimeZone($this->setting['Timezone']);
        $dbTimezone=new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE);
        $selectedEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($this->pageState);
        if (!empty($selectedEntry['Start'])){
            $calendarDateTime=new \DateTime($selectedEntry['Start'],$dbTimezone);    
        } else if (!empty($this->pageState['calendarDate'])){
            $calendarDate=$this->stdReplacements($this->pageState['calendarDate']);
            $calendarDateTime=new \DateTime($calendarDate,$calendarTimezone);
        } else {
            return strtotime(date('Y-m-d 00:00:00'));
        }
        $date=$calendarDateTime->format('Y-m-d 00:00:00');
        $calendarDateTime=new \DateTime($date,$calendarTimezone);
        return $calendarDateTime->getTimestamp();
    }
    
    private function date2pos($date='now',$timezone='')
    {
        if (empty($timezone)){
            $timezone=new \DateTimeZone($this->setting['Timezone']);
        } else {
            $timezone=new \DateTimeZone($timezone);    
        }
        $dateTime=new \DateTime($this->stdReplacements($date),$timezone);
        return floor(($dateTime->getTimestamp()-$this->calendarStartTimestamp())*$this->setting['Day width']/86400);
    }

    private function pos2date($pos)
    {
        $timestamp=$this->calendarStartTimestamp()+$pos*86400/$this->setting['Day width'];
        return $this->timeStamp2pageDateTime($timestamp);
    }
    
    private function getCalendarWidth()
    {
        $calendarStartTimestamp=$this->calendarStartTimestamp();
        $calendarDateTime=new \DateTime('@'.$calendarStartTimestamp);
        $calendarDateTime->add(\DateInterval::createFromDateString($this->setting['Days to show'].' days'));
        $calendarDateTime->setTimezone(new \DateTimeZone($this->setting['Timezone']));
        return ceil(($calendarDateTime->getTimestamp()-$calendarStartTimestamp)*$this->setting['Day width']/86400);
    }
    
    public function getTimezoneDate($date,$sourceTimezone,$targetTimezone)
    {
        $sourceTimezone=new \DateTimeZone($sourceTimezone);
        $targetTimezone=new \DateTimeZone($targetTimezone);
        if (gettype($date)==='object'){
            $dateTime=$date;
        } else if ($date[0]=='@'){
            $dateTime=new \DateTime($date);
        } else {
            $dateTime=new \DateTime($date,$sourceTimezone);
        }
        $dateTime->setTimezone($targetTimezone);
        return $dateTime->format('Y-m-d H:i:s');
    }

    private function getEvents($timestamp,$isSystemCall=FALSE)
    {
        $events=$this->getEventCache($timestamp);
        if ($events){
            return $events;
        } else {
            $events=[];
        }
        // get events
        $calendarDateTime=new \DateTime();
        $calendarDateTime->setTimestamp($timestamp); 
        $serverTimezone=new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE);
        $calendarDateTime->setTimezone($serverTimezone);
        $viewStart=$calendarDateTime->format('Y-m-d H:i:s');
        $calendarDateTime->add(\DateInterval::createFromDateString(($this->setting['Days to show']??'10').' days'));
        $viewEnd=$calendarDateTime->format('Y-m-d H:i:s');
        $oldEvents=[];
        $selectors=[];
        $selectors['Ongoing event']=['Source'=>$this->entryTable,'Group_1'=>'Events','Group_2'=>'Bank holidays','Start<'=>$viewStart,'End>'=>$viewEnd];
        $selectors['Finnishing event']=['Source'=>$this->entryTable,'Group_1'=>'Events','Group_2'=>'Bank holidays','End>='=>$viewStart,'End<='=>$viewEnd];
        $selectors['Upcomming event']=['Source'=>$this->entryTable,'Group_1'=>'Events','Group_2'=>'Bank holidays','Start>='=>$viewStart,'Start<='=>$viewEnd];
        $selectors['Serial event']=['Source'=>$this->entryTable,'Group'=>'Serial events'];
        foreach($selectors as $state=>$selector){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$isSystemCall,'Read','Start') as $entry){
                if (strcmp($state,'Serial event')===0){
                    $entries=$this->serialEntryToEntries($entry,$timestamp);
                } else {
                    $entries=[$entry];
                }
                foreach($entries as $entry){
                    // skip invalid entries
                    if (empty($entry['Content']['Event']['Start']) || empty($entry['Content']['Event']['End'])){
                        continue;
                    }
                    $key=$entry['EntryId'];
                    $eventStartTimestamp=strtotime($entry['Start']);
                    if (strcmp($state,'Finnishing event')===0){
                        if ($eventStartTimestamp>time()){$state='Upcomming event';}
                    }
                    $events[$key]=$entry;
                    $events[$key]['State']=$state;
                    $events[$key]['x0']=$this->date2pos($entry['Start'],\SourcePot\Datapool\Root::DB_TIMEZONE);
                    $events[$key]['x1']=$this->date2pos($entry['End'],\SourcePot\Datapool\Root::DB_TIMEZONE);
                    // add y-index
                    $events[$key]=$this->addYindex($events[$key],$oldEvents);
                    $oldEvents[$key]=$events[$key];
                }
            }
        }
        // cache events
        $this->setEventCache($timestamp,$events);
        return $events;
    }
    
    private function serialEntryToEntries($entry,int $timestamp,int $maxTimestamp=0)
    {
        $entries=[];
        $entryIdSuffix=0;
        $maxTimestamp=(empty($maxTimestamp))?($timestamp+(intval($this->setting['Days to show']??'10')-1)*86400+90000):$maxTimestamp;
        // scan calendar range
        $durationMinutes=intval($entry['Content']['Duration']??60);
        $durationSeconds=$durationMinutes*60;
        $timestamp-=$durationSeconds;
        while($timestamp<$maxTimestamp){
            $entryId=$entry['EntryId'].'|'.$entryIdSuffix;
            $dateTimeMatch=$this->serialEventIsActive($entry,$timestamp);
            if ($dateTimeMatch){
                $endTimestamp=$timestamp+$durationSeconds;
                $entries[$entryId]=$entry;
                $entries[$entryId]['EntryId']=$entryId;
                $entries[$entryId]['SerailEntryId']=$entry['EntryId'];
                $entries[$entryId]['Start']=$this->getTimezoneDate('@'.$timestamp,'UTC',\SourcePot\Datapool\Root::DB_TIMEZONE);
                $entries[$entryId]['Content']['Event']['Start']=$entries[$entryId]['Start'];
                $entries[$entryId]['Content']['Event']['Start timezone']=\SourcePot\Datapool\Root::DB_TIMEZONE;
                $entries[$entryId]['End']=$this->getTimezoneDate('@'.$endTimestamp,'UTC',\SourcePot\Datapool\Root::DB_TIMEZONE);
                $entries[$entryId]['Content']['Event']['End']=$entries[$entryId]['End'];
                $entries[$entryId]['Content']['Event']['End timezone']=\SourcePot\Datapool\Root::DB_TIMEZONE;
                $timestamp=$endTimestamp;
                $entryIdSuffix++;
            } else {
                $timestamp+=60;
            }
        }
        return $entries;
    }
    
    private function serialEventIsActive($entry,$timestamp)
    {
        if (empty($entry['Content'])){return FALSE;}
        $formatTestArr=['Month'=>'m','Week day'=>'N','Day'=>'d','Hour'=>'H','Minute'=>'i'];
        $dateTime=new \DateTime('@'.$timestamp);
        $eventTimezone=new \DateTimeZone($entry['Content']['Timezone']);
        $dateTime->setTimezone($eventTimezone);
        // check for match
        $dateTimeMatch=TRUE;
        foreach($formatTestArr as $contentKey=>$formatCharacter){
            if (strlen($entry['Content'][$contentKey])!==0){        
                if (strcmp($dateTime->format($formatCharacter),$entry['Content'][$contentKey])!==0){
                    $dateTimeMatch=FALSE;
                    break;
                }
            }
        }
        return $dateTimeMatch;
    }

    private function addYindex($newEvent,$oldEvents)
    {
        $newEvent['y']=0;
        do{
            $nextTry=FALSE;
            foreach($oldEvents as $EntryId=>$oldEvent){
                if ($newEvent['y']===$oldEvent['y']){
                    if ($this->eventsOverlap($newEvent,$oldEvent)){
                        $newEvent['y']++;
                        $nextTry=TRUE;
                        break;
                    }
                }
            }
        } while($nextTry);
        return $newEvent;
    }

    private function eventsOverlap($eventA,$eventB)
    {
        return (($eventA['x0']>$eventB['x0'] && $eventA['x0']<=$eventB['x1']) || ($eventA['x1']>$eventB['x0'] && $eventA['x1']<=$eventB['x1']) || ($eventA['x0']<$eventB['x0'] && $eventA['x1']>=$eventB['x1']));
    }

    public function getTimeDiff($dateA,$dateB,$timezoneA=FALSE,$timezoneB=FALSE){
        $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        if (empty($timezoneA)){$timezoneA=$pageTimeZone;}
        if (empty($timezoneB)){$timezoneB=$pageTimeZone;}
        $timezoneA=new \DateTimezone($timezoneA);
        $timezoneB=new \DateTimezone($timezoneB);
        $dateA=new \DateTime($dateA,$timezoneA);
        $dateB=new \DateTime($dateB,$timezoneB);
        $interval=$dateA->diff($dateB);
        $str='';
        $nonZeroDetected=FALSE;
        $template=['years'=>'y','months'=>'m','days'=>'d','hours'=>'h','minutes'=>'i'];
        //$template=['years'=>'y','months'=>'m','days'=>'d','hours'=>'h','minutes'=>'i','seconds'=>'s'];
        foreach($template as $label=>$index){
            $value=$interval->format('%'.$index);
            if (intval($value)===1){$label=rtrim($label,'s');}
            if (intval($value)===0 && $nonZeroDetected===FALSE){continue;} else {$nonZeroDetected=TRUE;}
            $str.=$value.'&nbsp;'.$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng($label).',&nbsp;';
        }
        return trim($str,',&nbsp;');
    }

    public function sec2str(int $seconds):string
    {
        $template=['day'=>86400,'hour'=>3600,'min'=>60,'sec'=>1];
        $result='';
        foreach($template as $key=>$duration){
            $value=intval(floor($seconds/$duration));
            if ($value>1){$key.='s';}
            $seconds-=$value*$duration;
            if ($value===0){continue;}
            $result.=$value.$key.', ';
        }
        return trim($result,', ');
    }

    public function str2date(string $str):array
    {
        $dateTimeParserObj=new \SourcePot\Asset\DateTimeParser();
        $dateTimeParserObj->setFromString($str,new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
        return $dateTimeParserObj->getArray();
    }
    
    public function timestamp2date($timestamp):array
    {
        $dateTimeParserObj=new \SourcePot\Asset\DateTimeParser();
        $dateTimeParserObj->setInitDateTime('2999-12-31 12:00:00');
        $dateTimeParserObj->setFromTimestamp($timestamp,new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
        return $dateTimeParserObj->getArray();
    }
    
    public function excel2date($excel):array
    {
        $dateTimeParserObj=new \SourcePot\Asset\DateTimeParser();
        $dateTimeParserObj->setInitDateTime('2999-12-31 12:00:00');
        $dateTimeParserObj->setFromExcelTimestamp($excel,new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
        return $dateTimeParserObj->getArray();
    }
    
    public function str2dateString($string,string $key='System'):string
    {
        $dateArr=$this->str2date($string);
        $string=($dateArr['isValid'] && isset($dateArr[$key]))?$dateArr[$key]:'';
        return $string;
    }

    public function timeStamp2pageDateTime($timestamp,string $format='Y-m-d H:i:s'):string
    {
        $timestamp=intval($timestamp);
        $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        $dateTimeObj=new \DateTime('@'.$timestamp);
        $dateTimeObj->setTimezone(new \DateTimeZone($pageTimeZone));
        return $dateTimeObj->format($format);
    }

    private function setEventCache(int $timestamp, array $events):void
    {
        $cacheTimeStamp=intval($timestamp/30)*30;
        $_SESSION[__CLASS__]['events-cache']=[$cacheTimeStamp=>$events];
    }

    private function getEventCache(int $timestamp):array|FALSE
    {
        $cacheTimeStamp=intval($timestamp/30)*30;
        return $_SESSION[__CLASS__]['events-cache'][$cacheTimeStamp]??FALSE;
    }

    private function resetEventCache():void
    {
        $_SESSION[__CLASS__]['events-cache']=[];
    }

    /******************************************************************************************************************************************
    * HomeApp Interface Implementation
    * 
    */
    
    public function getHomeAppWidget(string $name):array
    {
        // reset page setting
        $this->pageState['Group']='Events';
        $this->pageState['EntryId']='{{EntryId}}';
        $this->pageState['calendarDate']='{{YESTERDAY}}';
        $this->pageState['addDate']='';
        $this->pageState=$this->oc['SourcePot\Datapool\Root']->substituteWithPlaceholder($this->pageState);
        $this->pageState=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState(__CLASS__,$this->pageState);
        // get container
        $elector=['Source'=>$this->entryTable,'refreshInterval'=>60];
        $element=['element-content'=>'','style'=>[]];
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Calendar preview','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Calendar sheet '.__FUNCTION__,'generic',$elector,['method'=>'getCalendarSheet','classWithNamespace'=>__CLASS__],['style'=>['border'=>'none']]);
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget presents todays <b>calendar sheet</b>.';
        return $info;
    }
    
}
?>