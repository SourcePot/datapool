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
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];
    
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
    public function dataProcessor(array $callingElementSelector=[],string $action='info'):array|string|bool
    {
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

    private function getCanvasProcessingWidget($callingElement):string
    {
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Canvas processing','generic',$callingElement,['method'=>'getCanvasProcessingWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }
    
    private function getCanvasProcessingInfo($callingElement):string{
        $matrix=[];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?']);
        return $html;
    }

    public function getCanvasProcessingWidgetHtml($arr):array
    {
        $arr['html']=$arr['html']??'';
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runCanvasProcessing($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runCanvasProcessing($arr['selector'],TRUE);
        } else if (isset($formData['cmd']['reset'])){
            $result=$this->runCanvasProcessing($arr['selector'],2);
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
        $btnArr['value']='Reset';
        $btnArr['key']=['reset'];
        $matrix['Commands']['Reset']=$btnArr;
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'CanvasProcessing widget']);
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption]);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getCanvasProcessingSettings($callingElement):string
    {
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('CanvasProcessing entries settings','generic',$callingElement,['method'=>'getCanvasProcessingSettingsHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html??'';
    }
    
    public function getCanvasProcessingSettingsHtml($arr):array
    {
        $arr['html']=$arr['html']??'';
        $arr['html'].=$this->canvasProcessingRules($arr['selector']);
        return $arr;
    }
    
    private function canvasProcessingRules($callingElement):string
    {
        $contentStructure=['Process'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],];
        if (!isset($callingElement['Content']['Selector']['Source'])){return '';}
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Canvas elements to process...';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
    
    public function runCanvasProcessingOnClass($class,$isTestRun=FALSE):array
    {
        $canvasElements=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements($class,'',FALSE);
        foreach($canvasElements as $canvasElement){
            if (($canvasElement['Content']['Widgets']['Processor']??'')!==__CLASS__){
                continue;
            }
            $result=$this->runCanvasProcessing($canvasElement,$isTestRun);
            break;
        }
        return $result??['Notice'=>'No canvas processing element found for '.$class];
    }
    
    public function runCanvasProcessing($callingElement,$isTestRun=TRUE):array
    {
        $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->initProcessorResult(__CLASS__,$isTestRun);
        // get rules, i.e. canvas elements to process
        $rules2process=[];
        $stateSelector=['Source'=>$this->getEntryTable(),'Group'=>'canvasProcessingState','Folder'=>'Rules to process','Name'=>$callingElement['EntryId']];
        $stateSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($stateSelector,['Source','Group','Folder','Name'],'0','',FALSE);
        $settings=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($stateSelector,TRUE);
        if ($isTestRun===2){
            $settings['Content']=NULL;
            $settings=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($settings,TRUE);
            $result['Statistics']['Info']['Value']='Canvas element stack emptied';
            return $result;
        }
        if (empty($settings['Content'])){
            $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,['canvasprocessingrules'=>[]]);
            foreach($base['canvasprocessingrules'] as $ruleEntryId=>$rule){
                $rules2process[$ruleEntryId]=['Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$rule['Content']['Process']];
            }
            ksort($rules2process);
            $settings=array_merge($stateSelector,['Content'=>$rules2process,'Read'=>'ALL_DATA_R','Write'=>'ALL_CONTENTADMIN_R','Owner'=>'SYSTEM']);
            $settings=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($settings,TRUE);
        } else {
            $rules2process=$settings['Content'];
        }
        // process canvas element
        $infoMsg=[];
        foreach($rules2process as $ruleId=>$canvasElement2process){
            $step=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId(strval($ruleId));
            $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElement2process,TRUE);
            $processor=$canvasElement['Content']['Widgets']['Processor'];
            if (isset($this->oc[$processor])){
                $result=$this->oc[$processor]->dataProcessor($canvasElement,$isTestRun?'test':'run');
                if ($result['cntr']['incompleteRun']??FALSE){
                    $infoMsg[]='Incomplete run (this step will be processed again)...';
                } else {
                    $infoMsg[]='Completed...';
                    $rules2process[$ruleId]=NULL;
                }
            } else {
                $infoMsg[]='Processor missing...';
                $rules2process[$ruleId]=NULL;
            }
            $infoMsg[]='<b/>step '.$step.'</b>, canvas element <b>'.$canvasElement['Content']['Style']['Text'].'</b>';
            $result['Statistics']['-----------------------------------']['Value']='--------------------------------------------';
            $result['Statistics']['Canvas Processing']['Value']=implode('<br/>',$infoMsg);
            break;
        }
        $settings['Content']=$rules2process;
        $settings=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($settings,TRUE);
        return $result;
    }
}
?>