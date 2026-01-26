<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Processing;

class RemoteClient implements \SourcePot\Datapool\Interfaces\Processor,\SourcePot\Datapool\Interfaces\HomeApp{

    private const ENTRY_EXPIRATION_SEC=172800;
    private const TIMESTAMP_AGE_WARNING_THRESHOLD=30;
    private const MODE_STYLE_ARR=[
        'idle'=>'padding:0 0.25rem;background-color:#0f1;',
        'video'=>'padding:0 0.25rem;background-color:#fc0;',
        'capture'=>'padding:0 0.25rem;background-color:#fc0;',
        'sms'=>'padding:0 0.25rem;background-color:#fcc;',
        'alarm'=>'padding:0 0.25rem;background-color:#f00;color:#fff;'
    ];

    private const CONTENT_STRUCTURE_PARAMS=[
        'Client EntryId'=>['method'=>'element','tag'=>'p','element-content'=>'','excontainer'=>TRUE],
        'Client'=>['method'=>'select','value'=>'','options'=>[],'excontainer'=>TRUE],
        'Plot to show'=>['method'=>'select','value'=>'','options'=>[],'excontainer'=>TRUE],
        '2nd Plot to show'=>['method'=>'select','value'=>'','options'=>[],'excontainer'=>TRUE],
    ];

    private const ONEDIMSEPARATOR='||';

    private $oc;
    
    private $entryTable='';
    private $entryTemplate=[
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
    }
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    /**
     * This method links the processor to the canvas element
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method 
     * @param string $action Selects the requested process to be run  
     * @return string|bool Return the html-string or TRUE callingElement does not exist
     */
     public function dataProcessor(array $callingElementSelector=[],string $action='info'){
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->runClientProcessor($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runClientProcessor($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getClientWidget($callingElement),
                'settings'=>$this->getClientSettings($callingElement),
                'info'=>$this->getClientInfo($callingElement),
            };
        }
    }

    private function getClientWidget($callingElement)
    {
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client widget','generic',$callingElement,['method'=>'getClientWidgetHtml','classWithNamespace'=>__CLASS__],[]);
        return $html;
    }

    private function getClientInfo($callingElement):string
    {
        $matrix=['Important'=>['Message'=>'The selector key "Name" of the Canvas Element must not be set, it must be kept empty.<br/>Only the selector keys "Source", "Group" and "Folder" can be used.']];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'!']);
        return $html;
    }

    public function getClientWidgetHtml($arr):array
    {
        $arr['html']=$arr['html']??'';
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runClientProcessor($arr['selector'],FALSE);
            $appOpen=TRUE;
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runClientProcessor($arr['selector'],TRUE);
            $appOpen=TRUE;
        }
        // build html
        $btnArr=['tag'=>'input','type'=>'submit','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $matrix=[];
        $btnArr['value']='Test';
        $btnArr['key']=['test'];
        $matrix['Commands']['Test']=$btnArr;
        $btnArr['value']='Run';
        $btnArr['key']=['run'];
        $matrix['Commands']['Run']=$btnArr;
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Client widget']);
        foreach($result as $caption=>$matrix){
            if (!is_array($matrix)){continue;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption]);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$arr['html'],'icon'=>'Client','open'=>$appOpen??FALSE]);
        return $arr;
    }

    private function getClientSettings($callingElement,bool $isWidget=FALSE):string|array
    {
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin() && !$isWidget){
            $paramsHtml=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client settings','generic',$callingElement,['method'=>'getClientSettingsHtml','classWithNamespace'=>__CLASS__],['style'=>['width'=>'auto']]);
        }
        $base=['clientparams'=>[]];
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        $params=current($callingElement['clientparams']);
        if (!empty($params['Content']['Client']) && !empty($callingElement['callingElement']['Selector'])){
            $baseEntryId=$params['Content']['Client'];
            // get client settings form
            $selector=['Source'=>$this->entryTable,'EntryId'=>$baseEntryId.'_settings','disableAutoRefresh'=>TRUE];
            $htmlSettings=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client settings '.$baseEntryId,'generic',$selector,['method'=>'getClientSettingsContainer','classWithNamespace'=>__CLASS__],['style'=>['width'=>'auto','padding'=>0,'border'=>'none']]);
            // get client status form
            $selector=['Source'=>$this->entryTable,'EntryId'=>$baseEntryId.'_status','disableAutoRefresh'=>FALSE];
            $callingElement['lastEntrySelector']=$selector;
            $htmlStatus=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client status '.$baseEntryId,'generic',$selector,['method'=>'getClientStatusContainter','classWithNamespace'=>__CLASS__],['style'=>['width'=>'auto','padding'=>0,'border'=>'none']]);
            // get plot
            $htmlPlot=$this->getClientPlot($callingElement);
            // get image shuffle
            $selector=$callingElement['callingElement']['Selector'];
            $selector['orderBy']='Date';
            $selector['isAsc']=False;
            $htmlImageShuffle=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry shuffle '.$baseEntryId,'getImageShuffle',$selector,[],['style'=>['width'=>'auto']]);
        }
        $callingElement['html']=$paramsHtml??'';
        $callingElement['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>($htmlSettings??'').($htmlStatus??''),'keep-element-content'=>TRUE]);
        $callingElement['html'].=($htmlImageShuffle??'').($htmlPlot??'');
        if ($isWidget){
            return $callingElement;
        } else {
            return $callingElement['html'];
        }
    }
    
    public function getClientSettingsHtml(array $arr):array
    {
        // get html
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->clientParams($arr['selector']);
        return $arr;
    }

    private function clientParams($callingElement):string
    {
        $plotOptions=[];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,['clientparams'=>[]]);
        $params=current($base['clientparams']??[]);
        $signalSelector=$this->oc['SourcePot\Datapool\Foundation\Signals']->getSignalSelector(__CLASS__,$params['Content']['Client']??'__NOTHING_HERE__','%');
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($signalSelector) as $signal){
            if (!isset($signal['Content']['signal'])){continue;}
            $plotOptions[$signal['Name']]=$signal['Name'];
        }
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure['Client EntryId']['element-content']=$params['Content']['Client'];
        $contentStructure['Client']['options']=$this->getClientOptions();
        $contentStructure['Plot to show']['options']=$plotOptions;
        $contentStructure['2nd Plot to show']['options']=$this->oc['SourcePot\Datapool\Foundation\Signals']->getSignalOptions();
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Select client';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        $matrix=['Parameter'=>$row];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'Params']);
        return $html;
    }
    
    private function runClientProcessor(array $callingElement,bool $testRun=FALSE):array
    {
        $base=['clientparams'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=['Client statistics'=>['Entries'=>['value'=>0],'Failure'=>['value'=>0],'Success'=>['value'=>0],]];
        // loop through entries
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            if ($sourceEntry['isSkipRow']){
                $result['Client statistics']['Skip rows']['value']++;
                continue;
            }
            $result=$this->clientEntry($base,$sourceEntry,$result,$testRun);
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }
    
    private function clientEntry(array $base,array $sourceEntry,array $result,bool $testRun)
    {
        $debugArr=[];
        $params=current($base['clientparams']);
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        return $result;
    }

    public function clientCall($clientRequest):array
    {
        $idArr=[
            'client_id'=>$clientRequest['client_id'],
            'Group'=>$clientRequest['Status'.self::ONEDIMSEPARATOR.'Group'],
            'Folder'=>$clientRequest['Status'.self::ONEDIMSEPARATOR.'Folder'],
            'Name'=>$clientRequest['Status'.self::ONEDIMSEPARATOR.'Name']
        ];
        $baseEntryId=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($idArr,TRUE);
        // create templates from clientRequest
        $flatEntryTemplates=[
            'Settings'=>['Source'=>$this->entryTable,'Owner'=>'SYSTEM','EntryId'=>$baseEntryId,'Read'=>'ALL_DATA_SENTINEL_R','Write'=>'ADMIN_R','Expires'=>\SourcePot\Datapool\Root::NULL_DATE],
            'Status'=>['Source'=>$this->entryTable,'Owner'=>'SYSTEM','EntryId'=>$baseEntryId,'Read'=>'ALL_DATA_SENTINEL_R','Write'=>'ADMIN_R','Expires'=>\SourcePot\Datapool\Root::NULL_DATE],
        ];
        $flatEntries=[];
        foreach($clientRequest as $flatKey=>$value){
            if ($value==='__TODELETE__' || $value===''){continue;}
            $keyComps=explode(self::ONEDIMSEPARATOR,$flatKey);
            $entryType=array_shift($keyComps);
            $flatKey=implode(self::ONEDIMSEPARATOR,$keyComps);
            if (strpos($flatKey,'@excontainer')!==FALSE){
                $flatEntries[$entryType][$flatKey]=boolval(intval($value));
            } else if (strpos($flatKey,'@xMin')!==FALSE || strpos($flatKey,'@xMax')!==FALSE || strpos($flatKey,'@yMin')!==FALSE || strpos($flatKey,'@yMax')!==FALSE){
                $flatEntries[$entryType][$flatKey]=floatval($value);
            } else {
                $flatEntries[$entryType][$flatKey]=$value;
            }
        }
        foreach($flatEntryTemplates as $entryType=>$flatEntryTemplate){
            if (!isset($flatEntries[$entryType])){
                continue;
            }
            $flatEntryTemplate['EntryId']=$flatEntryTemplate['EntryId'].'_'.strtolower($entryType);
            $flatEntry=array_merge($flatEntryTemplate,$flatEntries[$entryType]);
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntry,self::ONEDIMSEPARATOR);
            if ($entryType==='Settings'){
                // create settings entry
                $settingsEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,TRUE);
                $clientSetting=[];
                foreach($settingsEntry['Content']['Settings'] as $key=>$propertyValueArr){
                    if (!isset($propertyValueArr['@value'])){continue;}
                    $clientSetting['Settings']['Content']['Settings'][$key]['@value']=$propertyValueArr['@value'];
                }
                $this->updateSignalsFromEntry($settingsEntry,$entryType);
            } else {
                // create status entry
                $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
                $fileArr=current($_FILES);
                if ($fileArr && empty($fileArr['error'])){
                    $success=move_uploaded_file($fileArr['tmp_name'],$sourceFile);
                    $entry=$this->oc['SourcePot\Datapool\Tools\ExifTools']->addExif2entry($entry,$sourceFile);
                } else if ($fileArr && !empty($fileArr['error'])){
                    unlink($sourceFile);
                    unset($entry['Params']['File']);
                }
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
                $this->updateSignalsFromEntry($entry,$entryType);
                $this->distributeClientEntries($entry);
            }
        }
        return $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($clientSetting??[],self::ONEDIMSEPARATOR);
    }

    private function distributeClientEntries(array $entry)
    {
        // baseEntryId -> clientParams containing that baseEntryId -> get linked CanvasElements
        $entryIdComps=explode('_',$entry['EntryId']);
        $RemoteClientParamsSelector=['Source'=>$this->getEntryTable(),'Content'=>'%'.$entryIdComps[0].'%'];
        $expiresTimestamp=time()+intval($flatEntry['lifetime']??self::ENTRY_EXPIRATION_SEC);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($RemoteClientParamsSelector,TRUE) as $paramsEntry){
            // Name of params entry is EntryId of CanvasElement | clientParams -> CanvasElement
            $canvasElementSelector=['Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$paramsEntry['Name']];
            $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElementSelector,TRUE);
            if (empty($canvasElement['Content']['Selector'])){continue;}
            // save entry to CanvasElement selector
            $target=$canvasElement['Content']['Selector'];
            $target['Params']['Client']['baseEntryId']=$entryIdComps[0];
            $target['Name']=$entry['Params']['File']['Name']?:time();
            $target['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
            $target['Owner']='SYSTEM';
            $target['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.strval($expiresTimestamp));
            $this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$target,TRUE,FALSE,TRUE,FALSE);
        }
    }

    private function updateSignalsFromEntry(array $entry, string $type)
    {
        $signalClient=$this->getCientId($entry);
        $dataType='float';
        foreach(current($entry['Content']) as $name=>$properties){
            $signalParams=['height'=>120];
            foreach($properties as $property=>$value){
                $property=trim($property,'@');
                if ($property==='dataType'){
                    $dataType=$value;
                } else if ($property==='value'){
                    $signalValue=$value;
                } else {
                    $signalParams[$property]=$value;
                }
            }
            if (intval($signalParams['isSignal']??0)<1){continue;}
            $signalName=$type.'→'.$name;
            $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,$signalClient,$signalName,$signalValue,$dataType,$signalParams);
        }
    }

    public function getClientPlot(array $callingElement):string
    {
        $params=current($callingElement['clientparams']??[]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Signals']->signal2plot(__CLASS__,$params['Content']['Client']??'__NOTHING_HERE__',$params['Content']['Plot to show']??'%');
        $html.=$this->oc['SourcePot\Datapool\Foundation\Signals']->selector2plot(['EntryId'=>$params['Content']['2nd Plot to show']??'__NOTHING_HERE__'],['caption'=>'']);
        return $html;
    }

    private function getClientOptions():array
    {
        $options=[];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator(['Source'=>$this->entryTable],TRUE) as $clientEntry){
            if ($clientEntry['Group']==='clientParams'){continue;}
            $entryIdComps=explode('_',$clientEntry['EntryId']);
            $options[$entryIdComps[0]]=$this->getClientName($clientEntry);
        }
        return $options;
    }

    public function getClientSettingsContainer(array $arr)
    {
        $arr['html']='Settings entry missing...';
        $settings=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($arr['selector']);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['val']['Settings'])){
            foreach($formData['val']['Settings'] as $key=>$value){
                $settings['Content']['Settings'][$key]['@value']=$value;
            }
            $settings=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($settings,TRUE);
            //$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file(['content'=>$settings['Content'],'formData'=>$formData]);
        }
        if (empty($settings)){return $arr;}
        $defEntry['Content']['']=['@tag'=>'button','@value'=>'Save'];
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Definitions']->definition2html($settings,[],$arr['callingClass'],$arr['callingFunction'],$isDebugging=FALSE);
        return $arr;
    }

    public function getClientStatusContainter(array $arr):array
    {
        $arr['html']='Status entry missing...';
        $status=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($arr['selector']);
        if (empty($status)){
            return $arr;
        }
        if (isset($status['Content']['Status']['mode']['@value'])){
            if (isset(self::MODE_STYLE_ARR[$status['Content']['Status']['mode']['@value']])){
                $status['Content']['Status']['mode']['@style']=self::MODE_STYLE_ARR[$status['Content']['Status']['mode']['@value']];
            }
        }
        if (isset($status['Content']['Status']['timestamp']['@value'])){
            $returnTime=round(time()-$status['Content']['Status']['timestamp']['@value']);
            $status['Content']['Status']['timestamp']['@value']=$returnTime.' sec';
            if ($returnTime>self::TIMESTAMP_AGE_WARNING_THRESHOLD){
                $status['Content']['Status']['timestamp']['@style']='padding:0 0.25rem;background-color:#fcc;';
            }
        }
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Definitions']->definition2html($status,$status['Content'],__CLASS__,__FUNCTION__,$isDebugging=FALSE);
        return $arr;
    }

    private function getCientId(array $entry):string|FALSE
    {
        $entryIdComps=explode('_',$entry['EntryId']);
        if (count($entryIdComps)===2){
            return $entryIdComps[0];
        } else {
            return FALSE;
        }
    }

    private function getClientName(array $selector):string
    {
        $string='';
        $string.=(empty($selector['Group']))?'':$selector['Group'];
        $string.=' | ';
        $string.=(empty($selector['Folder']))?'':$selector['Folder'];
        $string.=' | ';
        $string.=(empty($selector['Name']))?'':$selector['Name'];
        return $string;
    }

    /******************************************************************************************************************************************
    * HomeApp Interface Implementation
    * 
    */
    
    public function getHomeAppWidget(string $name):array
    {
        $appsHtml=[];
        // get all callingElements from client Params
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator(['Source'=>$this->getEntryTable(),'Group'=>'clientParams']) as $clientParams){
            $callingElementSelector=['Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$clientParams['Name']];
            $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector);
            $callingElement=$this->getClientSettings($callingElement,TRUE);
            if (!empty($callingElement['lastEntrySelector'])){
                $lastEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElement['lastEntrySelector']);
                if ($lastEntry){
                    $name=$this->getClientName($lastEntry);
                    // get select button
                    $clientParams=current($callingElement['clientparams']);
                    $canvasElement=['app'=>$clientParams['Folder'],'Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$clientParams['Name'],'Read'=>$lastEntry['Read']];
                    $callingElement['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn(['selector'=>$canvasElement,'cmd'=>'select']);
                    // wrapping-up
                    $appsHtml[$name]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$callingElement['html'],'icon'=>$name,'style'=>['clear'=>'both','padding'=>'1.5rem 0.5rem']]);
                }
            }
        }
        ksort($appsHtml);
        $element=['element-content'=>'','keep-element-content'=>TRUE];
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Remote client','keep-element-content'=>TRUE]);
        $element['element-content'].=implode(PHP_EOL,$appsHtml);
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget presents <b>Remote Clients</b>.';
        return $info;
    }

}
?>