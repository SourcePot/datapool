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

    private const ENTRY_EXPIRATION_SEC=600;
    private const ONEDIMSEPARATOR='||';
    private const INDICATORS=[self::ONEDIMSEPARATOR.'@value'=>'value',
                            self::ONEDIMSEPARATOR.'@dataType'=>'dataTypes',
                            self::ONEDIMSEPARATOR.'@isSignal'=>'signals',
                            self::ONEDIMSEPARATOR.'@min'=>'min',
                            self::ONEDIMSEPARATOR.'@max'=>'max',
                            self::ONEDIMSEPARATOR.'@color'=>'color',
                        ];

    private $oc;
    
    private $entryTable='';
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
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
     * This method is the interface of this data processing class
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method 
     * @param string $action Selects the requested process to be run  
     *
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
        $matrix=[];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?']);
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
        if (!empty($params['Content']['Client'])){
            $baseEntryId=$params['Content']['Client'];
            // get client settings form
            $selector=['Source'=>$this->entryTable,'EntryId'=>$baseEntryId.'_setting','disableAutoRefresh'=>FALSE];
            $htmlSettings=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client specific settings '.$baseEntryId,'generic',$selector,['method'=>'getClientSettingsContainter','classWithNamespace'=>__CLASS__],['style'=>['width'=>'auto','border'=>'none']]);
            // get client status form
            $selector=['Source'=>$this->entryTable,'EntryId'=>$baseEntryId.'_lastentry','disableAutoRefresh'=>FALSE];
            $callingElement['lastEntrySelector']=$selector;
            $htmlStatus=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client Status '.$baseEntryId,'generic',$selector,['method'=>'getClientStatusContainter','classWithNamespace'=>__CLASS__],['style'=>['width'=>'auto','border'=>'none']]);
            // get plot
            $htmlPlot=$this->getClientPlot($callingElement);
            // get image shuffle
            $selector=$callingElement['callingElement']['Selector'];
            $selector['refreshInterval']=5;
            $selector['disableAutoRefresh']=FALSE;
            $selector['orderBy']='Date';
            $selector['isAsc']=False;
            $htmlImageShuffle=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Preview '.$baseEntryId,'generic',$selector,['method'=>'getPreviewContainer','classWithNamespace'=>__CLASS__],['style'=>['width'=>'auto','padding'=>'5px','border'=>'none']]);
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
        $contentStructure=['Client'=>['method'=>'select','value'=>'','options'=>$this->getClientOptions(),'excontainer'=>TRUE],];
        // get selctor
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']['Content']=[];
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        $elementId=key($formData['val']);
        if (isset($formData['cmd'][$elementId])){
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        }
        // get HTML
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Select client';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        if (empty($arr['selector']['Content'])){$row['trStyle']=['background-color'=>'#a00'];}
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
        // enrich client request
        $clientRequest['Read']=(empty($clientRequest['Read']))?$this->entryTemplate['Read']['value']:$clientRequest['Read'];
        $clientRequest['Write']=(empty($clientRequest['Write']))?$this->entryTemplate['Write']['value']:$clientRequest['Write'];
        $idArr=['client_id'=>$clientRequest['client_id'],'Group'=>$clientRequest['Group'],'Folder'=>$clientRequest['Folder'],'Name'=>$clientRequest['Name']];
        $baseEntryId=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($idArr,TRUE);
        // create definition from clientRequest it contains Status and Settings definition
        $flatDefinition=['Source'=>$this->entryTable,'Owner'=>'SYSTEM','EntryId'=>$baseEntryId.'_definition','Expires'=>\SourcePot\Datapool\Root::NULL_DATE];
        $flatEntry=['Source'=>$this->entryTable,'EntryId'=>$baseEntryId.'_lastentry','Owner'=>'SYSTEM'];
        foreach($clientRequest as $flatKey=>$value){
            $keyComps=explode(self::ONEDIMSEPARATOR,$flatKey);
            $testKey=self::ONEDIMSEPARATOR.array_pop($keyComps);
            $newKey=implode(self::ONEDIMSEPARATOR,$keyComps);
            if (strpos($flatKey,'@')===FALSE){
                // not a definition
                $flatEntry[$flatKey]=$value;
            } else if (isset(self::INDICATORS[$testKey])){
                // value, data type or signal
                if (self::INDICATORS[$testKey]!=='value'){
                    $newKey=$this->getDataPropertyFlatKey($newKey,self::INDICATORS[$testKey]);
                }
                $flatEntry[$newKey]=$value;
            }
            // defintion
            if (strpos($flatKey,'@')!==FALSE || strpos($flatKey,self::ONEDIMSEPARATOR)===FALSE){
                $flatDefinition[$flatKey]=$value;
            }
        }
        // save definition
        $definition=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatDefinition,self::ONEDIMSEPARATOR);
        $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($definition,TRUE);
        // save the entry from clientRequest
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntry,self::ONEDIMSEPARATOR);
        $entry=$this->adjustByDataType($entry,FALSE);
        $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
        $fileArr=current($_FILES);
        if ($fileArr && empty($fileArr['error'])){
            $success=move_uploaded_file($fileArr['tmp_name'],$sourceFile);
            $entry=$this->oc['SourcePot\Datapool\Tools\ExifTools']->addExif2entry($entry,$sourceFile);
        } else {
            if (is_file($sourceFile)){unlink($sourceFile);}
            if (isset($entry['Params']['File'])){unset($entry['Params']['File']);}
        }
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
        $this->updateSignalsFromEntry($entry);
        $this->distributeClientEntries($entry);
        // create the initial setting and get current setting
        $setting=$entry;
        $setting['Expires']=\SourcePot\Datapool\Root::NULL_DATE;
        $setting['EntryId']=$baseEntryId.'_setting';
        $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($setting,TRUE);
        // prepare settings to be sent back to client
        $setting=$this->adjustByDataType($setting,FALSE);
        if (isset($setting['Content']['Status'])){unset($setting['Content']['Status']);}
        if (isset($setting['Params'])){unset($setting['Params']);}
        return $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat(['Content'=>$setting['Content']],self::ONEDIMSEPARATOR);
    }

    private function updateSignalsFromEntry(array $entry)
    {
        $signalClient=$this->getCientId($entry);
        $flatContent=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat(($entry['Content']??[]),self::ONEDIMSEPARATOR);
        $flatSignals=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat(($entry['Params']['signals']??[]),self::ONEDIMSEPARATOR);
        $flatDataTypes=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat(($entry['Params']['dataTypes']??[]),self::ONEDIMSEPARATOR);
        $flatMin=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat(($entry['Params']['min']??[]),self::ONEDIMSEPARATOR);
        $flatMax=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat(($entry['Params']['max']??[]),self::ONEDIMSEPARATOR);
        $flatColor=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat(($entry['Params']['color']??[]),self::ONEDIMSEPARATOR);
        foreach($flatSignals as $subKey=>$isSignal){
            if (intval($isSignal)<1 || !isset($flatContent[$subKey])){continue;}
            $dataType=$flatDataTypes[$subKey]??'float';
            $signalValue=$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($flatContent[$subKey],$dataType);
            $signalName=str_replace(self::ONEDIMSEPARATOR,'→',$subKey);
            $params=['dataType'=>$dataType,'height'=>120];
            if (isset($flatMin[$subKey])){$params['min']=$flatMin[$subKey];}
            if (isset($flatMax[$subKey])){$params['max']=$flatMax[$subKey];}
            if (isset($flatColor[$subKey])){$params['color']=$flatColor[$subKey];}
            $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,$signalClient,$signalName,$signalValue,$dataType,$params);
        }
    }

    public function getClientPlot(array $callingElement):string
    {
        $params=current($callingElement['clientparams']??[]);
        return $this->oc['SourcePot\Datapool\Foundation\Signals']->signal2plot(__CLASS__,$params['Content']['Client']??'__NOTHING_HERE__','%');
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

    public function getClientSettingsContainter(array $arr)
    {
        $arr['html']='Entry missing...';
        $entryIdcomps=explode('_',$arr['selector']['EntryId']);
        $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(['Source'=>$arr['selector']['Source'],'EntryId'=>$entryIdcomps[0].'_setting']);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['val']['Settings'])){
            $setting['Content']['Settings']=$formData['val']['Settings'];
            $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($setting,TRUE);
        }
        // get defintion entry, removed Status definition and retun Settings form
        $defEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(['Source'=>$arr['selector']['Source'],'EntryId'=>$entryIdcomps[0].'_definition']);
        if (empty($setting) || empty($defEntry)){return $arr;}
        unset($defEntry['Content']['Status']);
        $defEntry['Content']['Settings']['']=['@tag'=>'button','@value'=>'Save'];
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Definitions']->definition2html($defEntry,$setting['Content'],$arr['callingClass'],$arr['callingFunction'],$isDebugging=FALSE);
        return $arr;
    }

    public function getClientStatusContainter(array $arr):array
    {
        $arr['html']='Entry missing...';
        $entryIdcomps=explode('_',$arr['selector']['EntryId']);
        $lastEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(['Source'=>$arr['selector']['Source'],'EntryId'=>$entryIdcomps[0].'_lastentry']);
        $defEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(['Source'=>$arr['selector']['Source'],'EntryId'=>$entryIdcomps[0].'_definition']);
        if (empty($lastEntry) || empty($defEntry)){return $arr;}
        unset($defEntry['Content']['Settings']);
        if (isset($lastEntry['Content']['Status']['timestamp'])){
            $returnTime=round(time()-$lastEntry['Content']['Status']['timestamp']);
            $lastEntry['Content']['Status']['timestamp']=$returnTime.' sec';
        }
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Definitions']->definition2html($defEntry,$lastEntry['Content'],__CLASS__,__FUNCTION__,$isDebugging=FALSE);
        return $arr;
    }

    public function getPreviewContainer(array $arr):array
    {
        $paramNeedles=['%image%','%video%','%application%',];
        // generic settings
        $previewArr=$arr;
        $previewArr['maxDim']='360px';
        // get newst content
        foreach($paramNeedles as $paramNeedle){
            $previewArr['selector']['Params']=$paramNeedle;
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($previewArr['selector'],FALSE,'Read','Date',FALSE,1,FALSE) as $entry){
                $previewArr['selector']=$entry;
                $arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($previewArr);
            }
        }
        return $arr;
    }

    private function distributeClientEntries(array $entry)
    {
        $expiresTimestamp=time()+intval($flatEntry['lifetime']??self::ENTRY_EXPIRATION_SEC);
        $remoteClientComps=explode('\\',__CLASS__);
        // loop through all canvas elements with RemoteClient processor -> move entry to RemoteClient processor Selector
        $canvasElementsSelector=['Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'Group'=>'Canvas elements','Content'=>'%'.implode('%',$remoteClientComps).'%'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($canvasElementsSelector,TRUE) as $canvasElement){
            $target=$canvasElement['Content']['Selector'];
            $target['Name']=(isset($entry['Params']['File']['Name']))?$entry['Params']['File']['Name']:time();
            $target['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
            $target['Owner']='SYSTEM';
            $target['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.strval($expiresTimestamp));
            $this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$target,TRUE,FALSE,TRUE,FALSE);
        }
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

    private function getDataPropertyFlatKey(string $flatContentKey,string $property='dataTypes'):string{
        $flatContentKey=str_replace('→',self::ONEDIMSEPARATOR,$flatContentKey);
        $flatContentKey=str_replace(' → ',self::ONEDIMSEPARATOR,$flatContentKey);
        if (strpos($flatContentKey,'Content')===FALSE){
            return 'Params'.self::ONEDIMSEPARATOR.$property.self::ONEDIMSEPARATOR.$flatContentKey;
        } else {
            return str_replace('Content','Params'.self::ONEDIMSEPARATOR.$property,$flatContentKey);
        }
    }

    private function adjustByDataType(array $entry,bool $returnFlat=FALSE):array
    {
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry,self::ONEDIMSEPARATOR);
        foreach($flatEntry as $flatKey=>$flatValue){
            if (strpos($flatKey,'Content')!==0){continue;}
            $dataTypeKey=$this->getDataPropertyFlatKey($flatKey,'dataTypes');
            $dataType=(isset($flatEntry[$dataTypeKey]))?$flatEntry[$dataTypeKey]:'int';
            $flatEntry[$flatKey]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($flatValue,$dataType);
        }
        if ($returnFlat){
            return $flatEntry;
        } else {
            return $this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntry,self::ONEDIMSEPARATOR);
        }
    }

    public function getHomeAppWidget(string $name):array
    {
        $appsHtml=[];
        // get all callingElements from client Params
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator(['Source'=>$this->getEntryTable(),'Group'=>'clientParams']) as $clientParams){
            $callingElementSelector=['Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$clientParams['Name']];
            $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector);
            $callingElement=$this->getClientSettings($callingElement,TRUE);
            if ($callingElement['lastEntrySelector']){
                $lastEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElement['lastEntrySelector']);
                if ($lastEntry){
                    $name=$this->getClientName($lastEntry);
                    $appsHtml[$name]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$callingElement['html'],'icon'=>$name,'style'=>['padding'=>'1.5rem 0.5rem']]);
                }
            }
        }
        ksort($appsHtml);
        $element=['element-content'=>implode(PHP_EOL,$appsHtml),'keep-element-content'=>TRUE];
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget presents <b>Remote Clients</b>.';
        return $info;
    }

}
?>