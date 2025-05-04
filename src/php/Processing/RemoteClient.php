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

use Google\Protobuf\StringValue;

class RemoteClient implements \SourcePot\Datapool\Interfaces\Processor{

    private const ENTRY_EXPIRATION_SEC=3600;
    private const ONEDIMSEPARATOR='||';
    
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
        $this->returnTimeSignals();
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

    private function getClientSettings($callingElement):string
    {
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client settings','generic',$callingElement,['method'=>'getClientSettingsHtml','classWithNamespace'=>__CLASS__],['style'=>['width'=>'auto']]);
        }
        $base=['clientparams'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        $params=current($base['clientparams']);
        // get client settings form
        if (!empty($params['Content']['Client'])){
            $selector=['Source'=>$this->entryTable,'EntryId'=>$params['Content']['Client'].'_setting','refreshInterval'=>30,'disableAutoRefresh'=>TRUE];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client specific settings','generic',$selector,['method'=>'getClientSettingsContainter','classWithNamespace'=>__CLASS__],['style'=>['width'=>'auto']]);
        }
        // get image shuffle
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement);
        $callingElement['callingElement']['Selector']['refreshInterval']=5;
        $callingElement['callingElement']['Selector']['disableAutoRefresh']=FALSE;
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Preview E','generic',$callingElement['callingElement']['Selector'],['method'=>'getPreviewContainer','classWithNamespace'=>__CLASS__],['style'=>['width'=>'auto','padding'=>'3rem 0.5rem']]);
        // get client status form
        if (!empty($params['Content']['Client'])){
            $selector=['Source'=>$this->entryTable,'EntryId'=>$params['Content']['Client'].'_%','refreshInterval'=>30,'disableAutoRefresh'=>FALSE];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client Status','generic',$selector,['method'=>'getClientStatusContainter','classWithNamespace'=>__CLASS__],['style'=>['width'=>'auto']]);
            $html.=$this->getClientPlot($callingElement);
        }
        return $html;
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
        $definition=['Source'=>$this->entryTable];
        $definition['EntryId']=$baseEntryId.'_definition';
        foreach($clientRequest as $flatKey=>$value){
            if (strpos($flatKey,'@')!==FALSE || strpos($flatKey,self::ONEDIMSEPARATOR)===FALSE){
                $definition[$flatKey]=$value;
            }
        }
        $definition['Expires']=\SourcePot\Datapool\Root::NULL_DATE;
        $definition=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($definition,self::ONEDIMSEPARATOR);
        $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($definition,TRUE);
        // create the entry from clientRequest
        $dataTypes=[];
        $valueIndicator=self::ONEDIMSEPARATOR.'@value';
        $dataTypeIndicator=self::ONEDIMSEPARATOR.'@dataType';
        $flatEntry=['Source'=>$this->entryTable,'EntryId'=>$baseEntryId.'_lastentry'];
        foreach($clientRequest as $flatKey=>$value){
            if (strpos($flatKey,'@')===FALSE){
                $flatEntry[$flatKey]=$value;
            } else if (($pos=strpos($flatKey,$dataTypeIndicator))!==FALSE){
                $newKey=substr($flatKey,0,$pos);
                $dataTypes[$newKey]=$value;
                $newKey=$this->getDataTypeFlatKey($newKey);
                $flatEntry[$newKey]=$value;
            } else if (($pos=strpos($flatKey,$valueIndicator))!==FALSE){
                $newKey=substr($flatKey,0,$pos);
                $flatEntry[$newKey]=$value;
            }
        }
        // save entry
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
        $this->distributeClientEntries($entry);
        // create signal
        $signalClient=$this->getClientName($flatEntry);
        foreach($flatEntry as $flatKey=>$signalValue){
            if (strpos($flatKey,'Content')!==0){continue;}
            if (stripos($flatKey,'timestamp')!==FALSE){continue;}
            $dataTypeKey=$this->getDataTypeFlatKey($flatKey);
            $dataType=(isset($flatEntry[$dataTypeKey]))?$flatEntry[$dataTypeKey]:'int';
            $signalName=explode(self::ONEDIMSEPARATOR,$flatKey);
            array_shift($signalName);
            $signalName=implode('→',$signalName);
            $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,$signalClient,$signalName,$signalValue,$dataType);
        } 
        // create the initial setting
        $setting=$entry;
        $setting['Expires']=\SourcePot\Datapool\Root::NULL_DATE;
        $setting['EntryId']=$baseEntryId.'_setting';
        $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($setting,TRUE);
        // prepare settings to be sent back to client
        $setting=$this->adjustByDataType($setting,FALSE);
        unset($setting['Content']['Status']);
        unset($setting['Params']);
        return $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat(['Content'=>$setting['Content']],self::ONEDIMSEPARATOR);
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

    public function getClientPlot(array $callingElement):string|array
    {
        $clientOptions=$this->getClientOptions();
        if (!empty($callingElement['clientparams']) && empty($callingElement['function'])){
            // draw plot pane request
            $params=current($callingElement['clientparams']);
            $selector=[];
            $selector['signalFunction']=$clientOptions[$params['Content']['Client']];
            $selector['callingClass']=__CLASS__;
            $selector['callingFunction']=__FUNCTION__;
            $selector['id']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE);
            $selector['height']=80;
            $_SESSION['plots'][$selector['id']]=$selector;
            $elArr=['tag'=>'div','class'=>'plot','keep-element-content'=>TRUE,'element-content'=>'Plot "'.$selector['id'].'" placeholder','id'=>$selector['id']];
            $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $elArr=['tag'=>'a','class'=>'plot','keep-element-content'=>TRUE,'element-content'=>'SVG','id'=>'svg-'.$selector['id'],'style'=>['clear'=>'both']];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $elArr=['tag'=>'div','class'=>'plot-wrapper','style','keep-element-content'=>TRUE,'element-content'=>$html];
            $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            return $html;
        } else {
            // return plot data request
            $plotData=['use'=>'clientPlot','meta'=>$callingElement,'data'=>[]];
            $timezone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
            $signalSelector=$this->oc['SourcePot\Datapool\Foundation\Signals']->getSignalSelector(__CLASS__,$callingElement['signalFunction'].'%',FALSE);
            $signalSelector['Name']='Status%';
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($signalSelector,TRUE,'Read','Date') as $entry){
                $plotData['meta']['title']=$callingElement['signalFunction'];
                $nameComps=explode('→',$entry['Name']);
                $subkey=array_pop($nameComps);
                foreach($entry['Content']['signal'] as $index=>$signal){
                    $plotData['data'][$index]['DateTime']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.$signal['timeStamp'],'',$timezone);
                    $plotData['data'][$index]['History [sec]']=time()-$signal['timeStamp'];
                    if ($signal['dataType']==='int'){
                        $plotData['data'][$index][$subkey]=round(floatval($signal['value']));
                    } else if ($signal['dataType']==='float'){
                        $plotData['data'][$index][$subkey]=floatval($signal['value']);
                    } else if ($signal['dataType']==='bool'){
                        $plotData['data'][$index][$subkey]=intval($signal['value']);
                    } else {
                        $plotData['data'][$index][$subkey]=$signal['value'];
                    }
                }
            }
            return $plotData;
        }
        return $callingElement;
    }

    private function distributeClientEntries(array $entry)
    {
        $expiresTimestamp=time()+self::ENTRY_EXPIRATION_SEC;
        $remoteClientComps=explode('\\',__CLASS__);
        // loop through all canvas elements with RemoteClient processor -> move entry to RemoteClient processor Selector
        $canvasElementsSelector=['Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'Group'=>'Canvas elements','Content'=>'%'.implode('%',$remoteClientComps).'%'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($canvasElementsSelector,TRUE) as $canvasElement){
            $target=$canvasElement['Content']['Selector'];
            $target['Name']=(isset($entry['Params']['File']['Name']))?$entry['Params']['File']['Name']:time();
            $target['Owner']='SYSTEM';
            $target['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.strval(time()+self::ENTRY_EXPIRATION_SEC));
            $this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$target,TRUE,FALSE,TRUE,FALSE);
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

    private function getDataTypeFlatKey(string $flatContentKey):string{
        $flatContentKey=str_replace('→',self::ONEDIMSEPARATOR,$flatContentKey);
        $flatContentKey=str_replace(' → ',self::ONEDIMSEPARATOR,$flatContentKey);
        if (strpos($flatContentKey,'Content')===FALSE){
            return 'Params'.self::ONEDIMSEPARATOR.'dataTypes'.self::ONEDIMSEPARATOR.$flatContentKey;
        } else {
            return str_replace('Content','Params'.self::ONEDIMSEPARATOR.'dataTypes',$flatContentKey);
        }
    }

    private function adjustByDataType(array $entry,bool $returnFlat=FALSE):array
    {
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry,self::ONEDIMSEPARATOR);
        foreach($flatEntry as $flatKey=>$flatValue){
            if (strpos($flatKey,'Content')!==0){continue;}
            $dataTypeKey=$this->getDataTypeFlatKey($flatKey);
            $dataType=(isset($flatEntry[$dataTypeKey]))?$flatEntry[$dataTypeKey]:'int';
            if ($dataType=='bool'){
                $flatEntry[$flatKey]=boolval($flatValue);
            } else if ($dataType=='float'){
                $flatEntry[$flatKey]=floatval($flatValue);
            } else if ($dataType=='int'){
                $flatEntry[$flatKey]=intval($flatValue);
            } else if ($dataType=='string'){
                $flatEntry[$flatKey]=strval($flatValue);
            }
        }
        if ($returnFlat){
            return $flatEntry;
        } else {
            return $this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntry,self::ONEDIMSEPARATOR);
        }
    }

    private function returnTimeSignals()
    {
        $returnTimes=[];
        // check all signals
        $signalSelector=$this->oc['SourcePot\Datapool\Foundation\Signals']->getSignalSelector(__CLASS__,'');
        $signalSelector['Folder']='%RemoteClient%';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($signalSelector,TRUE,'Read','Date') as $signal){
            if (!isset($signal['Content']['signal'][0]['timeStamp'])){continue;}
            if (strpos($signal['Name'],'returnTime')!==FALSE){continue;}
            if (!isset($returnTimes['Folder'])){
                $returnTimes[$signal['Folder']]=$signal['Content']['signal'][0]['timeStamp'];
            } else if ($returnTimes['Folder']<$returnTimes['Folder']=$signal['Content']['signal'][0]['timeStamp']){
                $returnTimes[$signal['Folder']]=$signal['Content']['signal'][0]['timeStamp'];
            }
        }
        // create return time signal
        foreach($returnTimes as $folder=>$time){
            $clientString=array_pop(explode('::',$folder));
            $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,$clientString,'returnTime [sec]',time()-$time,'int');
        }
    }
}
?>