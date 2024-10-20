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

class RemoteClient implements \SourcePot\Datapool\Interfaces\Processor{

    private const ENTRY_EXPIRATION_SEC=3600;
    private const ONEDIMSEPARATOR='||';
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
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

    /**
     * This method is the interface of this data processing class
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method 
     * @param string $action Selects the requested process to be run  
     *
     * @return string|bool Return the html-string or TRUE callingElement does not exist
     */
     public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
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
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client widget','generic',$callingElement,array('method'=>'getClientWidgetHtml','classWithNamespace'=>__CLASS__),array());
        return $html;
    }

    private function getClientInfo($callingElement):string
    {
        $matrix=array();
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'?'));
        return $html;
    }

    public function getClientWidgetHtml($arr):array
    {
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runClientProcessor($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runClientProcessor($arr['selector'],TRUE);
        }
        // build html
        $btnArr=array('tag'=>'input','type'=>'submit','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $matrix=array();
        $btnArr['value']='Test';
        $btnArr['key']=array('test');
        $matrix['Commands']['Test']=$btnArr;
        $btnArr['value']='Run';
        $btnArr['key']=array('run');
        $matrix['Commands']['Run']=$btnArr;
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Client widget'));
        foreach($result as $caption=>$matrix){
            if (!is_array($matrix)){continue;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getClientSettings($callingElement):string
    {
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client settings','generic',$callingElement,array('method'=>'getClientSettingsHtml','classWithNamespace'=>__CLASS__),array('style'=>array('width'=>'auto')));
        }
        $base=array('clientparams'=>array());
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        $params=current($base['clientparams']);
        // get client settings form
        if (!empty($params['Content']['Client'])){
            $selector=array('Source'=>$this->entryTable,'EntryId'=>$params['Content']['Client'].'_setting');
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client SettingsB','generic',$selector,array('method'=>'getClientSettingsContainter','classWithNamespace'=>__CLASS__),array('style'=>array('width'=>'auto')));
        }
        // get image shuffle
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement);
        $callingElement['callingElement']['Selector']['refreshInterval']=5;
        //$callingElement['callingElement']['Selector']['disableAutoRefresh']=FALSE;
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Preview E','generic',$callingElement['callingElement']['Selector'],array('method'=>'getPreviewContainer','classWithNamespace'=>__CLASS__),array('style'=>array('width'=>'auto','padding'=>'3rem 0.5rem')));
        // get client status form
        if (!empty($params['Content']['Client'])){
            $selector=array('Source'=>$this->entryTable,'EntryId'=>$params['Content']['Client'].'_%','refreshInterval'=>30);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Client Status','generic',$selector,array('method'=>'getClientStatusContainter','classWithNamespace'=>__CLASS__),array('style'=>array('width'=>'auto')));
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

    private function clientParams($callingElement)
    {
        $contentStructure=array('Client'=>array('method'=>'select','value'=>'','options'=>$this->getClientOptions(),'excontainer'=>TRUE),
                                );
        // get selctor
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']['Content']=array();
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
        $arr['caption']='Calculation control';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['trStyle']=array('background-color'=>'#a00');}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }
    
    private function runClientProcessor(array $callingElement,bool $testRun=FALSE):array
    {
        $base=array('clientparams'=>array());
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Client statistics'=>array('Entries'=>array('value'=>0),
                                                    'Failure'=>array('value'=>0),
                                                    'Success'=>array('value'=>0),
                                                    )
                    );
        // loop through entries
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            if ($sourceEntry['isSkipRow']){
                $result['Client statistics']['Skip rows']['value']++;
                continue;
            }
            $result=$this->clientEntry($base,$sourceEntry,$result,$testRun);
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function clientEntry(array $base,array $sourceEntry,array $result,bool $testRun)
    {
        $debugArr=array();
        $params=current($base['calculationparams']);
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        return $result;
    }


    public function clientCall($clientRequest):array
    {
        // enrich client request
        $clientRequest['Read']=(empty($clientRequest['Read']))?$this->entryTemplate['Read']['value']:$clientRequest['Read'];
        $clientRequest['Write']=(empty($clientRequest['Write']))?$this->entryTemplate['Write']['value']:$clientRequest['Write'];
        $idArr=array('client_id'=>$clientRequest['client_id'],'Group'=>$clientRequest['Group'],'Folder'=>$clientRequest['Folder'],'Name'=>$clientRequest['Name']);
        $baseEntryId=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($idArr,TRUE);
        // create definition from clientRequest
        $definition=array('Source'=>$this->entryTable);
        $definition['EntryId']=$baseEntryId.'_definition';
        foreach($clientRequest as $flatKey=>$value){
            if (strpos($flatKey,'@')!==FALSE || strpos($flatKey,self::ONEDIMSEPARATOR)===FALSE){
                $definition[$flatKey]=$value;
            }
        }
        $definition=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($definition,self::ONEDIMSEPARATOR);
        $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($definition,TRUE);
        // create the entry from clientRequest
        $valueIndicator=self::ONEDIMSEPARATOR.'@value';
        $entry=array('Source'=>$this->entryTable);
        $entry['EntryId']=$baseEntryId.'_lastentry';
        foreach($clientRequest as $flatKey=>$value){
            if (strpos($flatKey,'@')===FALSE){
                $entry[$flatKey]=$value;
            } else if (($pos=strpos($flatKey,$valueIndicator))!==FALSE){
                $newKey=substr($flatKey,0,$pos);
                $entry[$newKey]=$value;
            }
        }
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($entry,self::ONEDIMSEPARATOR);
        $fileArr=current($_FILES);
        if ($fileArr && empty($fileArr['error'])){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($fileArr,$entry,FALSE,TRUE);
        } else {
            $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
            if (is_file($sourceFile)){unlink($sourceFile);}
            if (isset($entry['Params']['File'])){unset($entry['Params']['File']);}
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
        }
        $this->distributeClientEntries($entry);
        // create the initial setting
        $setting=$entry;
        $setting['Expires']=\SourcePot\Datapool\Root::NULL_DATE;
        $setting['EntryId']=$baseEntryId.'_setting';
        $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($setting,TRUE);
        return $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat(array('Content'=>$setting['Content']),self::ONEDIMSEPARATOR);
    }

    private function getClientOptions():array
    {
        $options=array();
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator(array('Source'=>$this->entryTable),TRUE) as $clientEntry){
            $entryIdComps=explode('_',$clientEntry['EntryId']);
            $options[$entryIdComps[0]]=$this->getSignalName($clientEntry);
        }
        return $options;
    }

    public function getClientSettingsContainter(array $arr)
    {
        $arr['html']='Entry missing...';
        $entryIdcomps=explode('_',$arr['selector']['EntryId']);
        $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(array('Source'=>$arr['selector']['Source'],'EntryId'=>$entryIdcomps[0].'_setting'));
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['Settings'])){
            $setting['Content']['Settings']=$formData['val']['Settings'];
            $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($setting,TRUE);
        }
        $defEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(array('Source'=>$arr['selector']['Source'],'EntryId'=>$entryIdcomps[0].'_definition'));
        if (empty($setting) || empty($defEntry)){return $arr;}
        unset($defEntry['Content']['Status']);
        $defEntry['Content']['Settings']['']=array('@tag'=>'button','@value'=>'Save');
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Definitions']->definition2html($defEntry,$setting['Content'],$arr['callingClass'],$arr['callingFunction'],$isDebugging=FALSE);
        return $arr;
    }

    public function getClientStatusContainter(array $arr):array
    {
        $arr['html']='Entry missing...';
        $entryIdcomps=explode('_',$arr['selector']['EntryId']);
        $lastEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(array('Source'=>$arr['selector']['Source'],'EntryId'=>$entryIdcomps[0].'_lastentry'));
        $defEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(array('Source'=>$arr['selector']['Source'],'EntryId'=>$entryIdcomps[0].'_definition'));
        if (empty($lastEntry) || empty($defEntry)){return $arr;}
        unset($defEntry['Content']['Settings']);
        if (isset($lastEntry['Content']['Status']['timestamp'])){
            $lastEntry['Content']['Status']['timestamp']=(time()-$lastEntry['Content']['Status']['timestamp']).' sec ('.$lastEntry['Content']['Status']['timestamp'].')';
        }
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Definitions']->definition2html($defEntry,$lastEntry['Content'],__CLASS__,__FUNCTION__,$isDebugging=FALSE);
        return $arr;
    }

    public function getPreviewContainer(array $arr):array
    {
        $paramNeedles=array('%image%','%video%','%application%',);
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
            $selector=array();
            $selector['signalFunction']=$clientOptions[$params['Content']['Client']];
            $selector['callingClass']=__CLASS__;
            $selector['callingFunction']=__FUNCTION__;
            $selector['id']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE);
            $selector['height']=200;
            $_SESSION['plots'][$selector['id']]=$selector;
            $elArr=array('tag'=>'div','class'=>'plot','keep-element-content'=>TRUE,'element-content'=>'Plot "'.$selector['id'].'" placeholder','id'=>$selector['id']);
            $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $elArr=array('tag'=>'a','class'=>'plot','keep-element-content'=>TRUE,'element-content'=>'SVG','id'=>'svg-'.$selector['id']);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $elArr=array('tag'=>'div','class'=>'plot-wrapper','style','keep-element-content'=>TRUE,'element-content'=>$html);
            $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            return $html;
        } else {
            // return plot data request
            $plotData=array('use'=>'clientPlot','meta'=>$callingElement,'data'=>array());
            $timezone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
            $signalSelector=$this->oc['SourcePot\Datapool\Foundation\Signals']->getSignalSelector('RemoteClient',$callingElement['signalFunction'],FALSE);
            $signalSelector['Name']='Status';
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($signalSelector,TRUE,'Read','Date') as $entry){
                $plotData['meta']['title']=$entry['Folder'].' → '.$entry['Name'];
                foreach($entry['Content']['signal'] as $index=>$signal){
                    $plotData['data'][$index]['DateTime']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.$signal['timeStamp'],'',$timezone);
                    $plotData['data'][$index]['History [sec]']=time()-$signal['timeStamp'];
                    foreach($signal['value'] as $subKey=>$subValue){
                        $numbersOnly=preg_replace('/[^0-9\.\,\-]/u','',$subValue);
                        if (strlen($numbersOnly)===strlen($subValue)){
                            // is number
                            $plotData['data'][$index][$subKey]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->str2float($subValue);
                        } else {
                            // is string
                            $plotData['data'][$index][$subKey]=$subValue;
                        }
                    }
                    $plotData['data'][$index]+=$signal['value'];
                }
            }
            return $plotData;
        }
        return $callingElement;
    }

    private function distributeClientEntries(array $entry)
    {
        // get all canvas elements with RemoteClient processor
        $clientTargetSelectors=array();
        $remoteClientComps=explode('\\',__CLASS__);
        $canvasElementsSelector=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'Group'=>'Canvas elements','Content'=>'%'.implode('%',$remoteClientComps).'%');
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($canvasElementsSelector,TRUE) as $canvasElement){
            $clientTargetSelectors[$canvasElement['EntryId']]=$canvasElement['Content']['Selector'];
        }
        // distribute entry
        $expiresTimestamp=time()+self::ENTRY_EXPIRATION_SEC;
        foreach($clientTargetSelectors as $entryId=>$selector){
            $targetEntry=$entry;
            $targetEntry['Owner']='SYSTEM';
            $targetEntry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.$expiresTimestamp);
            foreach($selector as $column=>$value){
                if (!empty($value)){$targetEntry[$column]=$value;}
            }
            $targetEntry['EntryId']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash(array($targetEntry['Source'],$targetEntry['Group'],$targetEntry['Folder'],$targetEntry['Name'],hrtime(TRUE)),TRUE);
            $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
            if (is_file($sourceFile)){
                $targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($targetEntry);
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($sourceFile,$targetFile);
            }
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($targetEntry,TRUE);
            $selectorStr=$this->getSignalName($targetEntry);
            foreach($targetEntry['Content'] as $contentKey=>$contentValue){
                $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal('RemoteClient',$selectorStr,$contentKey,$contentValue,'int'); 
            }
        }
    }

    private function getSignalName(array $selector):string
    {
        $string='';
        $string.=(empty($selector['Group']))?'':$selector['Group'];
        $string.=' | ';
        $string.=(empty($selector['Folder']))?'':$selector['Folder'];
        $string.=' | ';
        $string.=(empty($selector['Name']))?'':$selector['Name'];
        return $string;
    }
}
?>