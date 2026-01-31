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

class CleanupProcessor implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    
    private const INFO_MATRIX=[
        'Caption'=>['Comment'=>'CleanupProcessor class'],
        'Description'=>['Comment'=>'Thie processor empties canvas elements based on rules on forwards it\'s entries to the next canvas element.'],
    ];

    private const CONTENT_STRUCTURE_PARAMS=[
        // add content structure of parameters here...
        'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['No','Yes']],
        'Target'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],    
    ];
        
    private const CONTENT_STRUCTURE_RULES=[
        // add content structure of rules here...
        'Canvas element to empty'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],    
    ];
    
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
     * This method is links the processor with the canvaselement
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method 
     * @param string $action Selects the requested process to be run  
     * @return string|bool Return the html-string or TRUE callingElement does not exist
     */
     public function dataProcessor(array $callingElementSelector=[],string $action='info')
     {
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->run($callingElement,$testRunOnly=FALSE),
                'test'=>$this->run($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getWidget($callingElement),
                'settings'=>$this->getSettings($callingElement),
                'info'=>$this->getInfo($callingElement),
            };
        }
    }

    private function getWidget($callingElement)
    {
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Default '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'getWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }

    private function getInfo($callingElement):string
    {
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>self::INFO_MATRIX,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Help']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?','open'=>FALSE]);
        return $html;
    }

    public function getWidgetHtml($arr):array
    {
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->run($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->run($arr['selector'],TRUE);
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
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Default Processor']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            $appArr['open']=$caption==='Statistics';
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getSettings($callingElement):string
    {
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Processor parameters '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'processorParams','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Processor rules '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'processorRules','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }

    public function processorParams($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->processorParamsHtml($callingElement);
        return $arr;
    }
    
    public function processorRules($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->processorRulesHtml($callingElement);
        return $arr;
    }
    
    private function processorParamsHtml($callingElement)
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Clean-up processor parameter';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }
    
    private function processorRulesHtml(array $callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Clean-up processor rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    /******************************************************************************************************************************************
    *   Loop through entries and process entries based on processor parameters, rules
    *   The processor runs on all entries selected be the Canvas element (callingElement)
    */

    private function run(array $callingElement,bool $testRun=FALSE):array
    {
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,[]);
        // initialize statistic and result array
        $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->initProcessorResult(__CLASS__,$testRun,current($base['processorparamshtml'])['Content']['Keep source entries']??FALSE);
        $result['Clean-up']=[];
        // loop through entries
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->updateProcessorResult($result,$sourceEntry);
            if ($result['cntr']['timeLimitReached']){
                break;
            } else if (!$result['cntr']['isSkipRow']){
                $result=$this->processEntry($base,$sourceEntry,$result,$testRun);
            }
        }
        return $this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeProcessorResult($result);
    }

    private function processEntry(array $base,array $sourceEntry,array $result,bool $testRun):array
    {
        // recover basic context data
        $callingElementContent=$base['callingElement']; // Canvas element content
        $processorParams=current($base['processorparamshtml'])['Content']; // Processor params are linked to the key with the name = lower case method name there rules are defined
        $processorRules=$base['processorruleshtml']; // Processor rules are linked to the key with the name = lower case method name there rules are defined
        // recover the entry selector defined by the selected Canves element Selector
        $targetSelectorEntryId=$processorParams['Target'];
        $targetSelector=$base['entryTemplates'][$targetSelectorEntryId]??[];
        // process rules
        foreach($processorRules as $ruleEntryId=>$rule){
            $ruleKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleEntryId);
            $canvasElementIdForCleanUp=$rule['Content']['Canvas element to empty'];
            $canvasElementToCleanup=current($this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements(__CLASS__,$canvasElementIdForCleanUp));
            $selectorToCleanup=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->entryId2selector($canvasElementIdForCleanUp);
            if (!$testRun){
                $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selectorToCleanup,TRUE);
            }
            $result['Clean-up'][$ruleKey]=['Operation'=>$testRun?'Clean-up test':'Emptied','Canvas element'=>$canvasElementToCleanup['Content']['Style']['Text']];
        }
        // move entry
        $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetSelector,TRUE,$testRun,!empty($processorParams['Keep source entries']));
        $result['Statistics']['Entries moved (success)']['Value']++;
        return $result;
    }
}
?>