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

class CanvasProcessing implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    public function __construct($oc){
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
    
    public function getEntryTemplate(){
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
    public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->runCanvasProcessing($callingElement,FALSE),
                'test'=>$this->runCanvasProcessing($callingElement,TRUE),
                'widget'=>$this->getCanvasProcessingWidget($callingElement),
                'settings'=>$this->getCanvasProcessingSettings($callingElement),
                'info'=>$this->getCanvasProcessingInfo($callingElement),
            };
        }
    }

    private function getCanvasProcessingWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Canvas processing','generic',$callingElement,array('method'=>'getCanvasProcessingWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
    private function getCanvasProcessingInfo($callingElement){
        $matrix=array();
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'?'));
        return $html;
    }

    public function getCanvasProcessingWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runCanvasProcessing($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runCanvasProcessing($arr['selector'],TRUE);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'CanvasProcessing widget'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getCanvasProcessingSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('CanvasProcessing entries settings','generic',$callingElement,array('method'=>'getCanvasProcessingSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getCanvasProcessingSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->canvasProcessingRules($arr['selector']);
        //$selectorMatrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
        //$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for CanvasProcessing'));
        return $arr;
    }
    
    private function canvasProcessingRules($callingElement){
        $contentStructure=array('Process'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                               );
        if (!isset($callingElement['Content']['Selector']['Source'])){return '';}
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Processing steps (attached data processing will be triggered)';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
    
    public function runCanvasProcessingOnClass($class,$isTestRun=FALSE){
        $result=array();
        $canvasElementsSelector=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'Group'=>'Canvas elements','Folder'=>$class);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($canvasElementsSelector,TRUE,'Read','EntryId',TRUE) as $canvasElement){
            // continue if Processor is not CanvasProcessing class
            if (empty($canvasElement['Content']['Widgets']['Processor'])){continue;}
            if (mb_strpos($canvasElement['Content']['Widgets']['Processor'],'SourcePot\Datapool\Processing\CanvasProcessing')===FALSE){continue;}
            // canvas processing class found -> run processor
            $result=$this->runCanvasProcessing($canvasElement,$isTestRun);
            break;
        }
        return $result;
    }
    
    public function runCanvasProcessing($callingElement,$isTestRun=TRUE){
        // get Canvas Elements to process
        $settingsKey=__CLASS__.'|'.$callingElement['Folder'];
        $canvasElements2process=$this->oc['SourcePot\Datapool\AdminApps\Settings']->getSetting('Job processing','Var space',[],$settingsKey,TRUE);
        if (empty($canvasElements2process)){
            $base=['canvasprocessingrules'=>[]];
            $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
            $canvasElements2process=$this->oc['SourcePot\Datapool\AdminApps\Settings']->setSetting('Job processing','Var space',$base['canvasprocessingrules'],$settingsKey,TRUE);
        }
        if (empty($canvasElements2process)){
            return ['Results'=>['Step count'=>array('Value'=>count($canvasElements2process)),'Error'=>array('Value'=>'No canvas processing rules found...'),]];
        }
        // process canvas element
        $canvasElement2process=array_shift($canvasElements2process);
        $canvasElements2process[$canvasElement2process['EntryId']]=NULL;
        if (!empty($canvasElement2process)){
            $step=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($canvasElement2process['EntryId']);
            $canvasElement=array('Source'=>'dataexplorer','EntryId'=>$canvasElement2process['Content']['Process']);            
            $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElement,TRUE);
            $processor=$canvasElement['Content']['Widgets']['Processor'];
            if (isset($this->oc[$processor])){
                $result=$this->oc[$processor]->dataProcessor($canvasElement,$isTestRun?'test':'run');
            } else {
                $this->oc['logger']->log('notice','Method "{method}", canvas element "{canvasElement}" has no processor. Check if you need this processing step.',array('method'=>__FUNCTION__,'canvasElement'=>$canvasElement['Content']['Style']['Text']));
                $result=array('Statistics'=>array());
            }
            $result['Statistics'][$isTestRun?'Tested':'Processed']=array('Value'=>'Step '.$step.': '.$canvasElement['Content']['Style']['Text']);
            $result['Statistics']['Timestamp']=array('Value'=>time());
            $result['Statistics']['Date']=array('Value'=>date('Y-m-d H:i:s'));
            $base['Statistics']=$result['Statistics'];
        }
        $this->oc['SourcePot\Datapool\AdminApps\Settings']->setSetting('Job processing','Var space',$canvasElements2process,$settingsKey,TRUE);
        return $result??[];
    }

}
?>