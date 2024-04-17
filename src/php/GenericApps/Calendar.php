<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\GenericApps;

const DB_TIMEZONE='UTC';

class Calendar implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array('Group'=>array('index'=>FALSE,'value'=>'Events','type'=>'VARCHAR(255)','Description'=>'This is the Group category'),
                                 'Folder'=>array('index'=>FALSE,'value'=>'event','type'=>'VARCHAR(255)','Description'=>'This is the Group category'),
                                 'Start'=>array('index'=>FALSE,'value'=>'{{TODAY}}','type'=>'DATETIME','Description'=>'Is the start of an event, event, etc.'),
                                 'End'=>array('index'=>FALSE,'value'=>'{{TOMORROW}}','type'=>'DATETIME','Description'=>'Is the end of an event, event, etc.')
                                 );

    private $slopeOptions=array('&#9472;&#9472;&#9488;__','__&#9484;&#9472;&#9472;');
    private $setting=array();
    private $toReplace=array();

    private $pageState=array();
    private $pageStateTemplate=array();

    public $definition=array('Type'=>array('@tag'=>'p','@default'=>'calendar event','@Read'=>'NO_R'),
                             'Map'=>array('@function'=>'getMapHtml','@class'=>'SourcePot\Datapool\Tools\GeoTools','@default'=>''),
                             'Content'=>array('Event'=>array('Description'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                             'Type'=>array('@function'=>'select','@options'=>array('meeting'=>'Meeting','travel'=>'Travel','event'=>'Event','toTo'=>'To do'),'@default'=>'meeting','@excontainer'=>TRUE),
                                                             'Start'=>array('@tag'=>'input','@type'=>'datetime-local','@default'=>'{{NOW}})','@excontainer'=>TRUE),
                                                             'Start timezone'=>array('@function'=>'select','@default'=>'{{TIMEZONE-SERVER}}','@excontainer'=>TRUE),
                                                             'End'=>array('@tag'=>'input','@type'=>'datetime-local','@default'=>'{{TOMORROW}})','@excontainer'=>TRUE),
                                                             'End timezone'=>array('@fuction'=>'select','@default'=>'{{TIMEZONE-SERVER}}','@excontainer'=>TRUE),
                                                             'Recurrence'=>array('@function'=>'select','@options'=>array('+0 day'=>'Same day','+1 day"'=>'Daily','+1 week"'=>'Weeklyy','+1 month"'=>'Monthly','+1 year"'=>'Yearly'),'@default'=>'+0 day','@excontainer'=>TRUE),
                                                             'Recurrence times'=>array('@tag'=>'input','@type'=>'number','@min'=>0,'@max'=>100,'@default'=>0,'@excontainer'=>TRUE),
                                                             'Recurrence id'=>array('@tag'=>'p'),
                                                             'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'),
                                                            ),
                                                'Location/Destination'=>array('Company'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Department'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Street'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'House number'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Town'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Zip'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Country'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save','@isApp'=>'&#127758;'),
                                                                 ),
                                            ),
                             'Misc'=>array('@function'=>'entryControls','@isApp'=>'&#128736;','@hideHeader'=>TRUE,'@hideKeys'=>TRUE,'@hideCaption'=>FALSE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'),
                             'Read'=>array('@function'=>'integerEditor','@default'=>'ALL_MEMBER_R','@key'=>'Read','@isApp'=>'R','@hideHeader'=>TRUE,'@hideKeys'=>TRUE,'@hideCaption'=>TRUE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'),
                             'Write'=>array('@function'=>'integerEditor','@default'=>'ALL_CONTENTADMIN_R','@key'=>'Write','@isApp'=>'W','@hideHeader'=>TRUE,'@hideKeys'=>TRUE,'@hideCaption'=>TRUE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'),
                             );

    private $options=array('Type'=>array('event'=>'Event','trip'=>'Trip','meeting'=>'Meeting','todo'=>'To do','done'=>'To do done','training_0'=>'Training scheduled','training_1'=>'Training prepared','training_2'=>'Training canceled','training_3'=>'Training no-show'),
                           'Days to show'=>array(10=>'Show 10 days',20=>'Show 20 days',45=>'Show 45 days',90=>'Show 90 days',180=>'Show 180 days',370=>'Show 370 days'),
                           'Day width'=>array(200=>'Small day width',400=>'Middle day width',800=>'Big day width',1600=>'Biggest day width'),
                           'Recurrence'=>array('+0 day'=>'Single event','+1 day'=>'Daily','+1 week'=>'Weekly','+1 month'=>'Monthly','+1 year'=>'Yearly'),
                           'Timezone'=>array('Europe/Berlin'=>'+1 Europe/Berlin','Europe/London'=>'0 Europe/London','Atlantic/Azores'=>'-1 Atlantic/Azores','Atlantic/South_Georgia'=>'-2 Atlantic/South_Georgia',
                                             'America/Sao_Paulo'=>'-3 America/Sao_Paulo','America/Halifax'=>'-4 America/Halifax','America/New_York'=>'-5 America/New York','America/Mexico_City'=>'-6 America/Mexico City',
                                             'America/Denver'=>'-7 America/Denver','America/Vancouver'=>'-8 America/Vancouver','America/Anchorage'=>'-9 America/Anchorage','Pacific/Honolulu'=>'-10 Pacific/Honolulu',
                                             'Pacific/Midway'=>'-11 Pacific/Midway','Pacific/Kiritimati'=>'-12 Pacific/Kiritimati','Pacific/Fiji'=>'+12 Pacific/Fiji','Asia/Magadan'=>'+11 Asia/Magadan',
                                             'Pacific/Guam'=>'+10 Pacific/Guam','Asia/Tokyo'=>'+9 Asia/Tokyo','Asia/Shanghai'=>'+8 Asia/Shanghai','Asia/Novosibirsk'=>'+7 Asia/Novosibirsk','Asia/Omsk'=>'+6 Asia/Omsk',
                                             'Asia/Yekaterinburg'=>'+5 Asia/Yekaterinburg','Europe/Samara'=>'+4 Europe/Samara','Europe/Moscow'=>'+3 Europe/Moscow','Africa/Cairo'=>'+2 Africa/Cairo','UTC'=>'UTC'),
                            );

    private $months=array('january'=>'01','february'=>'02','march'=>'03','april'=>'04','may'=>'05','june'=>'06','july'=>'07','august'=>'08','september'=>'09','october'=>'10','november'=>'11','december'=>'12',
                          'januar'=>'01','februar'=>'02','märz'=>'03','april'=>'04','mai'=>'05','juni'=>'06','juli'=>'07','august'=>'08','september'=>'09','oktober'=>'10','november'=>'11','dezember'=>'12',
                          'jan'=>'01','feb'=>'02','mar'=>'03','apr'=>'04','may'=>'05','jun'=>'06','jul'=>'07','aug'=>'08','sep'=>'09','oct'=>'10','nov'=>'11','dec'=>'12',
                          );

    private $revMonths=array('US'=>array('01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'),
                             'UK'=>array('01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'),
                             'DE'=>array('01'=>'Januar','02'=>'Februar','03'=>'März','04'=>'April','05'=>'Mai','06'=>'Juni','07'=>'Juli','08'=>'August','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Dezember'),
                             );
    
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }

    public function init(array $oc)
    {
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
        $this->definition['Content']['Event']['Start timezone']['@options']=$this->options['Timezone'];
        $this->definition['Content']['Event']['End timezone']['@options']=$this->options['Timezone'];
        $this->definition['Content']['Event']['Recurrence']['@options']=$this->options['Recurrence'];
        $oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
        // get settings
        $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        $currentUser=$oc['SourcePot\Datapool\Foundation\User']->getCurrentUser();
        $settingKey=(strcmp($currentUser['Owner'],'ANONYM')===0)?'ANONYM':$currentUser['EntryId'];
        $this->setting=array('Days to show'=>45,'Day width'=>400,'Timezone'=>$pageTimeZone);
        $this->setting=$oc['SourcePot\Datapool\AdminApps\Settings']->getSetting(__CLASS__,$settingKey,$this->setting,'Calendar',TRUE);
        // get page state
        $this->pageStateTemplate=array('Source'=>$this->entryTable,'Type'=>$this->definition['Type']['@default'],'EntryId'=>'{{EntryId}}','calendarDate'=>'{{YESTERDAY}}','addDate'=>'','refreshInterval'=>300);
        $this->pageState=$oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__,$this->pageStateTemplate);
        //
    }

    public function job($vars){
        // add bank holidays
        $eventClasses=array('\SourcePot\BankHolidays\es','\SourcePot\BankHolidays\de','\SourcePot\BankHolidays\uk');
        if (!isset($vars['bankholidays'])){$vars['bankholidays']['lastRun']=0;}
        if (!isset($vars['signalCleanup'])){$vars['signalCleanup']['lastRun']=0;}
        if (time()-$vars['bankholidays']['lastRun']>2600000){
            $entry=array('Source'=>$this->entryTable,'Group'=>'Bank holidays','Read'=>'ALL_R','Write'=>'ADMIN_R');
            $events=array();
            foreach($eventClasses as $eventClass){
                if (class_exists($eventClass)){
                    $eventsObj=new $eventClass();
                    $events+=$eventsObj->getBankHolidays();
                    $this->oc['logger']->log('info','Event class "{eventClass}" loaded',array('eventClass'=>$eventClass));
                } else {
                    $this->oc['logger']->log('info','Event class "{eventClass}" missing/not installed',array('eventClass'=>$eventClass));
                    continue;
                }
            }
            $context=array('eventCount'=>0,'countries'=>'');
            foreach($events as $country=>$eventArr){
                foreach($eventArr as $entryId=>$event){
                    $entry['EntryId']=$entryId;
                    $entry['Folder']=$country;
                    $entry['Content']=$event;
                    $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($entry);
                    $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
                    $context['eventCount']++;
                }
                $context['countries'].=(empty($context['countries']))?$country:'| '.$country;
            }
            $this->oc['logger']->log('info','Bank holidays: for countries "{countries}" were "{eventCount}" events added',$context);
            $vars['bankholidays']['lastRun']=time();
            return $vars;
        } else if (time()-$vars['signalCleanup']['lastRun']>725361){
            // delete signals without a linked calendar entry
            $this->oc['SourcePot\Datapool\Foundation\Signals']->removeSignalsWithoutSource(__CLASS__,__FUNCTION__);
            $vars['signalCleanup']['lastRun']=time();
            return $vars;
        } else if (isset($vars['Period start'])){
            // get relevant timespan
            $vars['Period end']=time();
            $dbTimezone=new \DateTimeZone(DB_TIMEZONE);
            $startDateTime=new \DateTime('@'.$vars['Period start']);
            $startDateTime->setTimezone($dbTimezone);
            $startWindow=$startDateTime->format('Y-m-d H:i:s');
            $endDateTime=new \DateTime('@'.$vars['Period end']);
            $endDateTime->setTimezone($dbTimezone);
            $endWindow=$endDateTime->format('Y-m-d H:i:s');
            // scan calendar entries
            $events=array();
            $selector=array('Source'=>$this->entryTable,'Group!'=>'Serial%','End>'=>$startWindow);
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','Name',TRUE,FALSE,FALSE) as $event){
                $events[$event['Name']]=intval($event['Start']<$endWindow);
            }
            // get serial events for the time window between the last run and now
            $selector=array('Source'=>$this->entryTable,'Group'=>'Serial events');
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','Name',TRUE,FALSE,FALSE) as $event){
                $events[$event['Name']]=0;
                $timestamp=$vars['Period start'];
                while($timestamp<$vars['Period end']){
                    if ($this->serialEventIsActive($event,$timestamp)){
                        $isActive=TRUE;
                        $events[$event['Name']]=1;
                        break;
                    }
                    $timestamp+=1800;  
                }
            }
            // update signals
            foreach($events as $name=>$value){
                $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,$name,$value,'bool'); 
            }
        }
        $vars['Period start']=time();
        return $vars;
    }

    public function getEntryTable(){
        return $this->entryTable;
    }
    
    public function getEntryTemplate(){
        return $this->entryTemplate;
    }
    
    public function getAvailableTimezones(){
        return $this->options['Timezone'];
    }

    private function stdReplacements($str=''){
        if (is_array($str)){return $str;}
        if (isset($this->oc['SourcePot\Datapool\Foundation\Database'])){
            $this->toReplace=$this->oc['SourcePot\Datapool\Foundation\Database']->enrichToReplace($this->toReplace);
        }
        foreach($this->toReplace as $needle=>$replacement){$str=str_replace($needle,$replacement,$str);}
        return $str;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Apps','Emoji'=>'&#9992;','Label'=>'Calendar','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
        } else {
            $html='';
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Calendar by '.__FUNCTION__,'generic',$this->pageState,array('method'=>'getCalendar','classWithNamespace'=>__CLASS__),array('style'=>array()));
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Serial events by '.__FUNCTION__,'generic',$this->pageState,array('method'=>'getSerialEventsFrom','classWithNamespace'=>__CLASS__),array('style'=>array()));
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    public function unifyEntry($entry){
        $entry['Source']=$this->entryTable;    
        $entry['Folder']=$_SESSION['currentUser']['EntryId'];
        if (empty($entry['Group'])){$entry['Group']='Events';}
        if (strcmp($entry['Group'],'Events')===0 || strcmp($entry['Group'],'Bank holidays')===0){
            // Standard events
            if (empty($entry['Content']['Event']) && !empty($entry['addDate'])){
                $entry['Content']['Event']['Start']=$entry['addDate'].'T00:00';
                $entry['Content']['Event']['Start timezone']=$this->setting['Timezone'];
                $entry['Content']['Event']['End']=$entry['addDate'].'T23:59';
                $entry['Content']['Event']['End timezone']=$this->setting['Timezone'];
                $entry['Content']['Event']['Description']='';
                $entry['Content']['Event']['Type']='event';
            }
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addEntryDefaults($entry);        
            if (!empty($entry['Content']['Event']['Start']) && !empty($entry['Content']['Event']['Start timezone']) && 
                !empty($entry['Content']['Event']['End']) && !empty($entry['Content']['Event']['End timezone'])){
                $entry['Name']=mb_substr($entry['Content']['Event']['Description'],0,200);
                $entry['Start']=$this->getTimezoneDate($entry['Content']['Event']['Start'],$entry['Content']['Event']['Start timezone'],DB_TIMEZONE);
                $entry['End']=$this->getTimezoneDate($entry['Content']['Event']['End'],$entry['Content']['Event']['End timezone'],DB_TIMEZONE);
                $entry['Type']=mb_strtolower($this->entryTable.' '.$entry['Content']['Event']['Type']);
                if (!empty($entry['entryIsUpdated'])){$entry=$this->updateCalendarEventEntry($entry,TRUE);}
            }
            $entry=$this->oc['SourcePot\Datapool\Foundation\Definitions']->definition2entry($this->definition,$entry);
        } else {
            // Serial events
            if (isset($entry['Content']['Name'])){$entry['Name']=$entry['Content']['Name'];}
            if (isset($entry['Content']['Type'])){$entry['Type']=mb_strtolower($this->entryTable.' '.$entry['Content']['Type']);}
            if (isset($entry['Content']['Visibility'])){$entry['Read']=$entry['Content']['Visibility'];}
        }
        return $entry;
    }
    
    public function getCalendar($arr){
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
    
    public function getSerialEventsFrom($arr=array()){
        $monthOptions=array(''=>'');
        $weekOptions=array(''=>'');
        $monthdayOptions=array(''=>'');
        $weekdayOptions=array(''=>'','1'=>'Monday','2'=>'Tuesday','3'=>'Wednesday','4'=>'Thursday','5'=>'Friday','6'=>'Saturday','7'=>'Sunday');
        $hourOptions=array(''=>'');
        for($index=0;$index<60;$index++){
            $shortIndex=strval($index);
            $fullIndex=(strlen($shortIndex)<2)?'0'.$shortIndex:$shortIndex;
            if ($index>0){
                if ($index<13){$monthOptions[$fullIndex]=$fullIndex;}
                if ($index<54){$weekOptions[$fullIndex]=$fullIndex;}
                if ($index<32){$monthdayOptions[$fullIndex]=$fullIndex;}
            }
            if ($index<25){$hourOptions[$fullIndex]=$fullIndex;}
        }
        $contentStructure=array('Name'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'Serial event','excontainer'=>TRUE),
                                'Type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>current($this->options['Type']),'options'=>$this->options['Type']),
                                'Month'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$monthOptions),
                                'Day'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$monthdayOptions),
                                'Week number'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$weekOptions),
                                'Week day'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$weekdayOptions),
                                'Hour'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$hourOptions),
                                'Timezone'=>array('method'=>'select','excontainer'=>TRUE,'value'=>DB_TIMEZONE,'options'=>$this->options['Timezone']),
                                'Visibility'=>array('method'=>'select','excontainer'=>TRUE,'value'=>32768,'options'=>$this->oc['SourcePot\Datapool\Foundation\User']->getUserRols(TRUE)),
                                );
        $arr['selector']=array('Source'=>$this->entryTable,'Group'=>'Serial events','Folder'=>$_SESSION['currentUser']['EntryId']);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Serial events definition';
        $arr['style']=array('background-color'=>'#e2dbff');
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $arr;
    }
    
    private function getCalendarEntry($arr=array()){
        $template=array('html'=>'','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $arr=array_merge($template,$arr);
        $event=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($this->pageState);
        if (empty($event)){$event=$this->pageState;}
        $event=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($event);
        if ($this->pageState['EntryId']=='{{EntryId}}' && empty($this->pageState['addDate'])){
            $arr['html'].=$this->getEventsOverview($arr);
        } else if(mb_strpos(strval($event['EntryId']),'___')!==FALSE){
            if (isset($event['Content']['File content'])){unset($event['Content']['File content']);}
            $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($event['Content']);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Serial event','hideKeys'=>TRUE,'hideHeader'=>TRUE));
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryControls(array('selector'=>$event));
        } else {
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($event);
        }
        return $arr;        
    }
    
    private function getCalendarSettings($arr=array()){
        $template=array('html'=>'','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $btnTemplate=array('style'=>array('font-size'=>'20px'),'tag'=>'button','keep-element-content'=>'TRUE','excontainer'=>FALSE);
        $arr=array_merge($template,$arr);
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['Home'])){
            $this->pageState['Group']='Events';
            $this->pageState['EntryId']='{{EntryId}}';
            $this->pageState['calendarDate']='{{YESTERDAY}}';
            $this->pageState['addDate']='';
            $this->pageState=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState(__CLASS__,$this->pageState);
        } else if (!empty($formData['cmd'])){
            $newPageState=array_merge($this->pageStateTemplate,$this->pageState,$formData['val']['pageState']);
            $this->pageState=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState(__CLASS__,$newPageState);
            $this->setting=$this->oc['SourcePot\Datapool\AdminApps\Settings']->setSetting(__CLASS__,$_SESSION['currentUser']['EntryId'],$formData['val']['setting'],'Calendar',FALSE);
        }
        $calendarDate=new \DateTime('@'.$this->calendarStartTimestamp());
        $calendarDate->setTimezone(new \DateTimeZone($this->setting['Timezone']));
        $calendarDateArr=$arr;
        $calendarDateArr['tag']='input';
        $calendarDateArr['type']='date';
        $calendarDateArr['title']='Press enter to select';
        $calendarDateArr['value']=$calendarDate->format('Y-m-d');
        $calendarDateArr['key']=array('pageState','calendarDate');
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($calendarDateArr);
        $btnArr=array_replace_recursive($btnTemplate,$arr);
        $btnArr['key']=array('Set');
        $btnArr['title']='Set';
        $btnArr['element-content']='&#10022;';
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        $btnArr['key']=array('Home');
        $btnArr['title']='Home';
        $btnArr['element-content']='&#9750;';
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        $timezoneArr=$arr;
        $timezoneArr['selected']=$this->setting['Timezone'];
        $timezoneArr['options']=$this->options['Timezone'];
        $timezoneArr['key']=array('setting','Timezone');
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($timezoneArr);
        $daysToShowArr=$arr;
        $daysToShowArr['selected']=$this->setting['Days to show'];
        $daysToShowArr['options']=$this->options['Days to show'];
        $daysToShowArr['key']=array('setting','Days to show');
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($daysToShowArr);
        $dayWidthArr=$arr;
        $dayWidthArr['selected']=$this->setting['Day width'];
        $dayWidthArr['options']=$this->options['Day width'];
        $dayWidthArr['key']=array('setting','Day width');
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($dayWidthArr);
        return $arr;
    }
    
    private function getEventsOverview(){
        $matrices=array();
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
                $matrices[$event['State']][$EntryId]=array('Event'=>$event['Name'],'Starts&nbsp;in'=>$this->getTimeDiff($event['Start'],'now',DB_TIMEZONE,DB_TIMEZONE));
            } else {
                $matrices[$event['State']][$EntryId]=array('Event'=>$event['Name'],'Ends&nbsp;in'=>$this->getTimeDiff($event['End'],'now',DB_TIMEZONE,DB_TIMEZONE));
            }
        }
        $html='';
        foreach($matrices as $caption=>$matrix){
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>$caption,'hideKeys'=>TRUE));
        }
        return $html;
    }
    
    private function getCalendarSheet($arr=array()){
        $template=array('html'=>'','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $arr=array_merge($template,$arr);
        $style=array('left'=>$this->date2pos());
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>'','keep-element-content'=>TRUE,'class'=>'calendar-timeline','style'=>$style));
            
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
            $dayStyle=array('left'=>$lastDayPos,'width'=>$newDayPos-$lastDayPos-1);
            if ($date==$this->pageState['addDate']){
                $dayStyle['background-color']='#f008';
            }
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>'','keep-element-content'=>TRUE,'class'=>'calendar-day','style'=>$dayStyle));
            $dayStyle=array('left'=>$lastDayPos,'width'=>$newDayPos-$lastDayPos-1);
            if (strcmp($weekDay,'Sun')===0 || strcmp($weekDay,'Sat')===0){$dayStyle['background-color']='#af6';}
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>$dayContent,'keep-element-content'=>TRUE,'key'=>array('Add',$date),'title'=>'Click here to open a new event','callingClass'=>__CLASS__,'callingFunction'=>'addEvents','class'=>'calendar-day','style'=>$dayStyle));
            $arr['html'].=$this->timeLineHtml($date);
            $lastDayPos=$newDayPos;
        }
        $arr=$this->addEvents($arr);
        $wrapperStyle=array('width'=>$this->getCalendarWidth(),'height'=>$arr['calendarSheetHeight']);
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'class'=>'calendar-sheet','style'=>$wrapperStyle));
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'class'=>'calendar-sheet-wrapper'));
        return $arr;
    }
    
    private function eventsFormProcessing(){
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
    
    private function addEvents($arr){
        $timestamp=$this->calendarStartTimestamp();
        $events=$this->getEvents($timestamp);
        $arr['calendarSheetHeight']=120;
        foreach($events as $EntryId=>$event){
            if (empty($event['Content']['Event']['Start']) || empty($event['Content']['Event']['End'])){continue;}
            if (is_array($event['Content']['Event']['Start'])){implode(' ',$event['Content']['Event']['Start']);}
            if (is_array($event['Content']['Event']['End'])){implode(' ',$event['Content']['Event']['End']);}
            $style=array();
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
            $title=$event['Name']."\n";
            $title.=str_replace('T',' ',$event['Content']['Event']['Start']).' ('.$event['Content']['Event']['Start timezone'].")\n";
            $title.=str_replace('T',' ',$event['Content']['Event']['End']).' ('.$event['Content']['Event']['End timezone'].')';
            $btnArr=array('tag'=>'button','element-content'=>$event['Name'],'title'=>$title,'key'=>array('EntryId',$EntryId),'entry-id'=>$EntryId,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>$class,'style'=>$style);
            
            if ($event['State']=='Serial event'){
                $btnArr['key'][1]=$event['SerailEntryId'];
                $btnArr['style']['background-color']='#e2dbff';
            }
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        }
        return $arr;
    }
        
    private function timeLineHtml($date){
        $html='';
        $lastPos=0;
        for($h=0;$h<24;$h++){
            if ($h<10){$dateTime=$date.' 0'.$h;} else {$dateTime=$date.' '.$h;}
            $dateTime.=':00:00';
            $newPos=$this->date2pos($dateTime);
            if ($newPos-$lastPos<30){continue;}
            $content=strval($h);
            $contentPos=round($newPos-(strlen($content)*10)/2);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$content,'class'=>'calendar-hour','style'=>array('left'=>$contentPos)));
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>'','class'=>'calendar-hour','style'=>array('left'=>$newPos)));
            $lastPos=$newPos;
        }
        return $html;
    }
    
    private function calendarStartTimestamp(){
        if (empty($this->pageState['calendarDate'])){return 0;}
        $calendarTimezone=new \DateTimeZone($this->setting['Timezone']);
        $calendarDate=$this->stdReplacements($this->pageState['calendarDate']);
        $calendarDateTime=new \DateTime($calendarDate,$calendarTimezone);
        $date=$calendarDateTime->format('Y-m-d 00:00:00');
        $calendarDateTime=new \DateTime($date,$calendarTimezone);
        return $calendarDateTime->getTimestamp();
    }
    
    private function date2pos($date='now',$timezone=FALSE){
        if (empty($timezone)){
            $timezone=new \DateTimeZone($this->setting['Timezone']);
        } else {
            $timezone=new \DateTimeZone($timezone);    
        }
        $dateTime=new \DateTime($this->stdReplacements($date),$timezone);
        return floor(($dateTime->getTimestamp()-$this->calendarStartTimestamp())*$this->setting['Day width']/86400);
    }

    private function pos2date($pos){
        $timestamp=$this->calendarStartTimestamp()+$pos*86400/$this->setting['Day width'];
        $dateTime=new \DateTime('@'.$timestamp);
        $dateTime->setTimezone(new \DateTimeZone($this->setting['Timezone']));
        return $dateTime->format('Y-m-d H:i:s');
    }
    
    private function getCalendarWidth(){
        $calendarStartTimestamp=$this->calendarStartTimestamp();
        $calendarDateTime=new \DateTime('@'.$calendarStartTimestamp);
        $calendarDateTime->add(\DateInterval::createFromDateString($this->setting['Days to show'].' days'));
        $calendarDateTime->setTimezone(new \DateTimeZone($this->setting['Timezone']));
        return ceil(($calendarDateTime->getTimestamp()-$calendarStartTimestamp)*$this->setting['Day width']/86400);
    }
    
    public function getTimezoneDate($date,$sourceTimezone,$targetTimezone){
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

    private function getEvents($timestamp,$isSystemCall=FALSE){
        $calendarDateTime=new \DateTime('@'.$timestamp);
        $serverTimezone=new \DateTimeZone(DB_TIMEZONE);
        $calendarDateTime->setTimezone($serverTimezone);
        $viewStart=$calendarDateTime->format('Y-m-d H:i:s');
        $calendarDateTime->add(\DateInterval::createFromDateString(($this->setting['Days to show']??'10').' days'));
        $viewEnd=$calendarDateTime->format('Y-m-d H:i:s');
        $events=array();
        $oldEvents=array();
        $selectors=array();
        $selectors['Ongoing event']=array('Source'=>$this->entryTable,'Group_1'=>'Events','Group_2'=>'Bank holidays','Start<'=>$viewStart,'End>'=>$viewEnd);
        $selectors['Finnishing event']=array('Source'=>$this->entryTable,'Group_1'=>'Events','Group_2'=>'Bank holidays','End>='=>$viewStart,'End<='=>$viewEnd);
        $selectors['Upcomming event']=array('Source'=>$this->entryTable,'Group_1'=>'Events','Group_2'=>'Bank holidays','Start>='=>$viewStart,'Start<='=>$viewEnd);
        $selectors['Serial event']=array('Source'=>$this->entryTable,'Group'=>'Serial events');
        foreach($selectors as $state=>$selector){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$isSystemCall,'Read','Start') as $entry){
                if (strcmp($state,'Serial event')===0){
                    $entries=$this->serialEntryToEntries($entry,$timestamp);
                } else {
                    $entries=array($entry);
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
                    $events[$key]['x0']=$this->date2pos($entry['Start'],DB_TIMEZONE);
                    $events[$key]['x1']=$this->date2pos($entry['End'],DB_TIMEZONE);
                    // add y-index
                    $events[$key]=$this->addYindex($events[$key],$oldEvents);
                    $oldEvents[$key]=$events[$key];
                }
            }
        }
        return $events;
    }
    
    private function serialEntryToEntries($entry,$timestamp){
        $formatTestArr=array('Month'=>'m','Week number'=>'W','Week day'=>'N','Day'=>'d','Hour'=>'H');
        $entries=array();
        $entryIdSuffix=0;
        $maxTimestamp=$timestamp+(intval($this->setting['Days to show']??'10')-1)*86400+90000;
        // scan calendar range
        while($timestamp<$maxTimestamp){
            $entryId=$entry['EntryId'].'|'.$entryIdSuffix;
            $dateTimeMatch=$this->serialEventIsActive($entry,$timestamp);
            if ($dateTimeMatch){
                if (!isset($entries[$entryId])){
                    $entries[$entryId]=$entry;
                    $entries[$entryId]['EntryId']=$entryId;
                    $entries[$entryId]['SerailEntryId']=$entry['EntryId'];
                    $entries[$entryId]['Start']=$this->getTimezoneDate('@'.$timestamp,'UTC',DB_TIMEZONE);
                    $entries[$entryId]['Content']['Event']['Start']=$entries[$entryId]['Start'];
                    $entries[$entryId]['Content']['Event']['Start timezone']=DB_TIMEZONE;
                }
            }
            $timestamp+=1800;  
            if (!$dateTimeMatch || $timestamp>=$maxTimestamp){
                if (isset($entries[$entryId])){
                    $entries[$entryId]['End']=$this->getTimezoneDate('@'.$lastTimestamp,'UTC',DB_TIMEZONE);
                    $entries[$entryId]['Content']['Event']['End']=$entries[$entryId]['End'];
                    $entries[$entryId]['Content']['Event']['End timezone']=DB_TIMEZONE;
                    $entryIdSuffix++;
                }
            }
            $lastTimestamp=$timestamp;
        }
        return $entries;
    }
    
    private function serialEventIsActive($entry,$timestamp){
        $formatTestArr=array('Month'=>'m','Week number'=>'W','Week day'=>'N','Day'=>'d','Hour'=>'H');
        $dateTime=new \DateTime('@'.$timestamp);
        $eventTimezone=new \DateTimeZone($entry['Content']['Timezone']);
        $dateTime->setTimezone($eventTimezone);
        // check for match
        $dateTimeMatch=TRUE;
        foreach($formatTestArr as $contentKey=>$formatCharacter){
            if (strlen($entry['Content'][$contentKey])!==0){        
                if (strcmp($dateTime->format($formatCharacter),$entry['Content'][$contentKey])!==0){
                    $dateTimeMatch=FALSE;
                }
            }
        }
        return $dateTimeMatch;
    }

    private function addYindex($newEvent,$oldEvents){
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

    private function eventsOverlap($eventA,$eventB){
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
        $template=array('years'=>'y','months'=>'m','days'=>'d','hours'=>'h','minutes'=>'i');
        //$template=array('years'=>'y','months'=>'m','days'=>'d','hours'=>'h','minutes'=>'i','seconds'=>'s');
        foreach($template as $label=>$index){
            $value=$interval->format('%'.$index);
            if (intval($value)===1){$label=rtrim($label,'s');}
            if (intval($value)===0 && $nonZeroDetected===FALSE){continue;} else {$nonZeroDetected=TRUE;}
            $str.=$value.'&nbsp;'.$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng($label).',&nbsp;';
        }
        return trim($str,',&nbsp;');
    }

    private function updateCalendarEventEntry($entry){
        $entry=$this->oc['SourcePot\Datapool\Tools\GeoTools']->address2location($entry);
        if (empty($entry['Content']['Event']['Recurrence id'])){$entry['Content']['Event']['Recurrence id']=$entry['EntryId'];}
        // delete all related entries
        $toDeleteSelector=array('Source'=>$entry['Source'],'Content'=>'%'.$entry['Content']['Event']['Recurrence id'].'%');
        $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($toDeleteSelector,TRUE);
        // create recurring entries
        $startSourceTimezone=new \DateTimeZone($entry['Content']['Event']['Start timezone']);
        $endSourceTimezone=new \DateTimeZone($entry['Content']['Event']['End timezone']);
        $startDateTime=new \DateTime($entry['Content']['Event']['Start'],$startSourceTimezone);
        $endDateTime=new \DateTime($entry['Content']['Event']['End'],$endSourceTimezone);
        $intervallRecurrence=\DateInterval::createFromDateString(trim($entry['Content']['Event']['Recurrence'],'+'));
        $loopEntry=$entry;
        for($loop=0;$loop<=$entry['Content']['Event']['Recurrence times'];$loop++){
            if ($loop===0){
                $loopEntry['EntryId']=$entry['EntryId'];
            } else {
                $loopEntry['EntryId']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getEntryId();
            }
            $loopEntry['Content']['Event']['Start']=$startDateTime->format('Y-m-d H:i:s');
            $loopEntry['Content']['Event']['End']=$endDateTime->format('Y-m-d H:i:s');
            $loopEntry['Start']=$this->getTimezoneDate($loopEntry['Content']['Event']['Start'],$loopEntry['Content']['Event']['Start timezone'],DB_TIMEZONE);
            $loopEntry['End']=$this->getTimezoneDate($loopEntry['Content']['Event']['End'],$loopEntry['Content']['Event']['End timezone'],DB_TIMEZONE);
            $loopEntry['Content']['Event']['Recurrence index']=$loop;
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($loopEntry);
            $startDateTime->add($intervallRecurrence);
            $endDateTime->add($intervallRecurrence);
        }
        return $entry;
    }
    
    public function timestamp2date($string):array
    {
        $timestamp=intval($string);
        $string=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.strval($timestamp));
        return $this->str2date($string,'UTC');
    }
    
    public function str2date($string,$timezone=NULL,bool $isExcelDate=FALSE):array
    {
        $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        $timezone??=$pageTimeZone;
        $dummyDate=\SourcePot\Datapool\Root::NULL_DATE;
        $dummyDateArr=array('year'=>mb_substr($dummyDate,0,4),'month'=>mb_substr($dummyDate,5,2),'day'=>mb_substr($dummyDate,8,2),'time'=>mb_substr($dummyDate,11,8));
        $dateArr=$context=$this->guessDateComps($string,$isExcelDate);
        if (isset($dateArr['timezoneIn'])){$timezone=$dateArr['timezoneIn'];}
        $context['class']=__CLASS__;
        $context['method']=__FUNCTION__;
        if (!$dateArr['isValid']){
            $dateArr=array_merge($dateArr,$dummyDateArr);
            $this->oc['logger']->log('warning','Method "{method}" failed to parse date from "{string}" with "{error}"',$context);         
        }
        // year corrections
        $year=intval($dateArr['year']);
        if ($year<10){
            $dateArr['year']='200'.$year;
        } else if ($year<60){
            $dateArr['year']='20'.$year;
        } else if ($year<100){
            $dateArr['year']='19'.$year;
        } else if ($year<1000){
            $dateArr['year']='0'.$year;
        }
        $dateArr['month']=str_pad($dateArr['month'],2,'0',STR_PAD_LEFT);
        $dateArr['day']=str_pad($dateArr['day'],2,'0',STR_PAD_LEFT);
        $dateArr['System']=$dateArr['year'].'-'.$dateArr['month'].'-'.$dateArr['day'].' '.$dateArr['time'];
        $timezoneObj=new \DateTimeZone($timezone);
        $systemTimezoneObj=new \DateTimeZone($pageTimeZone);
        try{
            $datetimeObj=new \DateTime($dateArr['System'],$timezoneObj);
        } catch (\Exception $e){
            $dateArr['isValid']=FALSE;
            $context['error']=$e->getMessage();
            $this->oc['logger']->log('warning','Method "{method}" failed to parse date from "{string}" with "{error}"',$context);         
            $dateArr=array_merge($dateArr,$dummyDateArr);
            $dateArr['System']=$dateArr['year'].'-'.$dateArr['month'].'-'.$dateArr['day'];
            $datetimeObj=new \DateTime($dateArr['System'],$timezoneObj);
        }
        $datetimeObj->setTimezone($systemTimezoneObj);
        $dateArr['System short']=$datetimeObj->format('Y-m-d');
        $dateArr['System']=$datetimeObj->format('Y-m-d H:i:s');
        $dateArr['YYYYMMDD']=$dateArr['year'].$dateArr['month'].$dateArr['day'];
        $dateArr['Timezone']=$pageTimeZone;
        $dateArr['Timestamp']=$datetimeObj->getTimestamp();
        $dateArr['US']=$datetimeObj->format('m/d/Y');
        $dateArr['UK']=$datetimeObj->format('d/m/Y');
        $dateArr['DE']=$datetimeObj->format('d.m.Y');
        $dateArr['day']=$datetimeObj->format('d');
        $dateArr['month']=$datetimeObj->format('m');
        $dateArr['year']=$datetimeObj->format('Y');
        $dateArr['US long']=$this->revMonths['US'][$dateArr['month']].' '.intval($dateArr['day']).', '.$dateArr['year'];
        $dateArr['UK long']=intval($dateArr['day']).' '.$this->revMonths['UK'][$dateArr['month']].' '.$dateArr['year'];
        $dateArr['DE long']=intval($dateArr['day']).'. '.$this->revMonths['DE'][$dateArr['month']].' '.$dateArr['year'];
        return $dateArr;
    }
    
    private function guessDateComps($string,bool $isExcelDate=FALSE):array
    {
        $arr=array('System'=>'','System short'=>'','isValid'=>TRUE,'string'=>$string,'time'=>'12:00:00');
        $string=strval($string);
        $string=trim(mb_strtolower($string));
        if (empty($string)){
            $arr['isValid']=FALSE;
            $arr['error']='Date string is empty';
            return $arr;            
        }
        // recover time string
        preg_match_all('/\d{2}\:\d{2}\:\d{2}/',$string,$matches);
        if (isset($matches[0][0])){
            $string=trim(str_replace($matches[0][0],'',$string));
            $arr['time']=$matches[0][0];
        }
        if ($isExcelDate){$string=preg_replace('/\D+/','',$string);}
        preg_match_all('/\d{4}[0-1][0-9][0-3][0-9]/',$string,$matches);
        if (isset($matches[0][0]) && !$isExcelDate){
            // format YYYYMMDD -> YYYY-MM-DD
            $string=$matches[0][0][0].$matches[0][0][1].$matches[0][0][2].$matches[0][0][3].'-'.$matches[0][0][4].$matches[0][0][5].'-'.$matches[0][0][6].$matches[0][0][7];
        } else if (ctype_digit($string) && (intval($string)-25569>0)){
            // EXCEL format
            $unixTimestamp=86400*(intval($string)-25569);
            $arr['timezoneIn']='UTC';
            $utcTimezoneObj=new \DateTimeZone($arr['timezoneIn']);
            $utcObj=new \DateTime('@'.strval($unixTimestamp),$utcTimezoneObj);
            $string=$utcObj->format('Y-m-d');
        } else if ($isExcelDate){
            $arr['isValid']=FALSE;
            $arr['error']='Date string is not valid Excel format';
            return $arr;            
        }
        // get month from month name
        foreach($this->months as $needle=>$month){
            if (mb_strpos($string,$needle)===FALSE){continue;}
            $arr['month']=$month;
        }
        // detect comma as year separator
        $commaPos=mb_strpos($string,',');
        if (!empty($commaPos)){
            $dateComps=explode(',',$string);
            $arr['year']=array_pop($dateComps);
            $arr['year']=trim($arr['year']);
            $string=$dateComps[0];
        }
        if (!empty($arr['year']) && !empty($arr['month'])){
            $arr['day']=preg_replace('/\D+/','',$string);
            return $arr;
        } else if (!empty($arr['month'])){
            $dateComps=preg_split('/\D+/',$string);
            $arr['year']=array_pop($dateComps);
            $arr['day']=array_shift($dateComps);
            return $arr;
        }
        $hyphenPos=mb_strpos($string,'-');
        $hyphenRPos=strrpos($string,'-');
        $dotPos=mb_strpos($string,'.');
        $dotRPos=strrpos($string,'.');
        if (!empty($hyphenPos) && !empty($hyphenRPos)){
            // YYYY-MM-DD
            $arr['year']=mb_substr($string,0,$hyphenPos);
            $arr['month']=mb_substr($string,$hyphenPos+1,$hyphenRPos-$hyphenPos-1);
            $arr['day']=mb_substr($string,$hyphenRPos+1);
            return $arr;
        } else if (!empty($dotPos) && !empty($dotRPos)){
            // DD.MM.YYYY
            $arr['day']=mb_substr($string,0,$dotPos);
            $arr['month']=mb_substr($string,$dotPos+1,$dotRPos-$dotPos-1);
            $arr['year']=mb_substr($string,$dotRPos+1);
            return $arr;
        } else {
            // unknown
            $dateComps=preg_split('/\D+/',$string);
            if (count($dateComps)!==3){
                $arr['isValid']=FALSE;
                $arr['error']='Failed to parse date string';
                return $arr;
            }
            $dateComps[0]=intval($dateComps[0]);
            $dateComps[1]=intval($dateComps[1]);
        }
        $arr['year']=$dateComps[2];
        if ($dateComps[0]>12){
            // default is US notation DD/MM/YYYY
            $arr['day']=strval($dateComps[0]);
            $arr['month']=strval($dateComps[1]);
        } else if ($dateComps[1]>12){
            // default is US notation MM/DD/YYYY
            $arr['day']=strval($dateComps[1]);
            $arr['month']=strval($dateComps[0]);
        } else {
            // default is US notation MM/DD/YYYY
            $arr['notice']='Parse date guessed US date format (default)';
            $arr['day']=strval($dateComps[1]);
            $arr['month']=strval($dateComps[0]);
        }
        return $arr;
    }

}
?>