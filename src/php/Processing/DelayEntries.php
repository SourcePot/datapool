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

class DelayEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private const CONTENT_STRUCTURE_PARAMS=[
        'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>[0=>'No, move entries',1=>'Yes, copy entries']],
        'Forward to canvas element'=>['method'=>'canvasElementSelect','addColumns'=>[''=>'...'],'excontainer'=>TRUE],
        'Reset all trigger when condition is met'=>['method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>['No','Yes']],
    ];
        
    private const CONTENT_STRUCTURE_RULES=[
        'Operation'=>['method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'or','options'=>['||'=>'OR','&&'=>'AND','xor'=>'XOR',]],
        'Trigger'=>['method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>[]],
    ];

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
    public function dataProcessor(array $callingElementSelector=[],string $action='info'){
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->runDelayEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runDelayEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getDelayEntriesWidget($callingElement),
                'settings'=>$this->getDelayEntriesSettings($callingElement),
                'info'=>$this->getDelayEntriesInfo($callingElement),
            };
        }
    }

    private function getDelayEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Delaying','generic',$callingElement,['method'=>'getDelayEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }
    
     private function getDelayEntriesInfo($callingElement){
        $matrix=[];
        $matrix['Description']=['<p style="width:30em;">Entries will be forwarded to the selected next cnavas element when the trigger is active.</p>'];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?']);
        return $html;
    }
    
    public function getDelayEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runDelayEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runDelayEntries($arr['selector'],1);
        } else if (isset($formData['cmd']['trigger'])){
            $result=$this->runDelayEntries($arr['selector'],2);
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
        $matrix['Commands']['Trigger']=['tag'=>'button','element-content'=>'Manual trigger','key'=>['trigger'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Delaying widget']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            if ($caption==='Delaying statistics'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getDelayEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Delaying entries settings','generic',$callingElement,['method'=>'getDelayEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getDelayEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->delayingParams($arr['selector']);
        $arr['html'].=$this->delayingRules($arr['selector']);
        return $arr;
    }
    
    private function delayingParams($callingElement)
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Move entries when conditions are met.';
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }

    private function delayingRules($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_RULES;
        $contentStructure['Trigger']['options']=$this->oc['SourcePot\Datapool\Foundation\Signals']->getTriggerOptions();
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Delay ends if all rules combined are TRUE.';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
        
    public function runDelayEntries($callingElement,$testRun=1){
        $base=['delayingparams'=>[],'delayingrules'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // check condition and loop through source entries for processing
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=$this->checkCondition($base,$callingElement,['Statistics'=>['Moved entries'=>['Value'=>0]]],$testRun);
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix($result['Statistics']??[]);
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }
    
    private function checkCondition($base,$callingElement,$result,$testRun){
        $triggerOptions=$this->oc['SourcePot\Datapool\Foundation\Signals']->getTriggerOptions();
        $params=current($base['delayingparams']);
        $rules=$base['delayingrules'];
        foreach($rules as $ruleEntryId=>$rule){
            $rowKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleEntryId);
            $triggerId=$rule['Content']['Trigger']??'_NOT_YET_SET_';
            $triggerName=$triggerOptions[$rule['Content']['Trigger']]??FALSE;
            if ($triggerName===FALSE){
                continue;
            }
            // processing
            if (isset($triggerActive)){
                $prevTriggerActive=$triggerActive;
                $prevTriggerActiveBoolStyling=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($prevTriggerActive);
                $operation=self::CONTENT_STRUCTURE_RULES['Operation']['options'][$rule['Content']['Operation']];
            } else {
                $prevTriggerActiveBoolStyling='';
                $operation='';
                $prevTriggerActive=$this->oc['SourcePot\Datapool\Foundation\Signals']->isActiveTrigger($triggerId,TRUE);
            }
            $triggerActive=$this->oc['SourcePot\Datapool\Foundation\Signals']->isActiveTrigger($triggerId,TRUE);
            $triggerActiveBoolStyling=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($triggerActive,['element-content'=>$triggerName]);
            $conditionsMet=$this->oc['SourcePot\Datapool\Foundation\Computations']->isTrue($prevTriggerActive,$triggerActive,$rule['Content']['Operation']);
            $conditionsMetBoolStyling=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($conditionsMet);
            // create result
            $result['Delaying statistics'][$rowKey]=['Prev. trigger'=>$prevTriggerActiveBoolStyling,'Operation'=>$operation,'Trigger'=>$triggerActiveBoolStyling,'Conditions met'=>$conditionsMetBoolStyling];
            $trigger2reset[]=$triggerId;
        }
        // reset trigger
        if (($params['Content']['Reset all trigger when condition is met']??FALSE) && ($conditionsMet??FALSE) && empty($testRun)){
            foreach($trigger2reset as $triggerId){
                $this->oc['SourcePot\Datapool\Foundation\Signals']->resetTrigger($triggerId,TRUE);
            }
        }
        // move entries if condition is met
        $conditionsMet=($testRun===2)?TRUE:($conditionsMet??FALSE);
        $testRun=($testRun===2)?0:$testRun;
        if ($conditionsMet){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $entry){
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$base['entryTemplates'][$params['Content']['Forward to canvas element']],TRUE,$testRun,$params['Content']['Keep source entries']);
                $result['Statistics']['Moved entries']['Value']++;
            }
        }
        return $result;
    }

}
?>