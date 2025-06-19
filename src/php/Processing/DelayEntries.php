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
    
    private function delayingParams($callingElement){
        $return=['html'=>'','Parameter'=>[],'result'=>[]];
        if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
        $contentStructure=[
            'Forward to canvas element'=>['method'=>'canvasElementSelect','addColumns'=>[''=>'...'],'excontainer'=>TRUE],
            'Reset all trigger when condition is met'=>['method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>['No','Yes']],
            ];
        // get selctorB
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']['Content']=['Column to delay'=>'Name'];
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
        $arr['caption']='Move entries when conditions are met.';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
        $matrix=['Parameter'=>$row];
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }

    private function delayingRules($callingElement){
        $triggerOptions=$this->oc['SourcePot\Datapool\Foundation\Signals']->getTriggerOptions();
        $contentStructure=[
            'Trigger'=>['method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>$triggerOptions],
            'Reset trigger'=>['method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>['No','Yes']],
            'Combine with next row'=>['method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'or','options'=>['or'=>'OR','and'=>'AND','xor'=>'XOR',]],
            ];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Delay ends if all rules combined are TRUE.';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
        
    public function runDelayEntries($callingElement,$testRun=1){
        $base=['delayingparams'=>[],'delayingrules'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=[
            'Delaying statistics'=>[
                'Condition'=>['value'=>''],
                'Condition met'=>['value'=>0],
                'Reset trigger'=>['value'=>0],
                'Moved entries'=>['value'=>0],
                ]
            ];
        $result=$this->checkCondition($base,$callingElement,$result,$testRun);
        //
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }
    
    private function checkCondition($base,$callingElement,$result,$testRun,$isDebugging=FALSE){
        $params=current($base['delayingparams']);
        $debugArr=['params'=>$params,'rules'=>$base['delayingrules'],'selector'=>$callingElement,'testRun'=>$testRun];
        $isFirstRule=TRUE;
        $lastOparation='or';
        $trigger2reset=[];
        $triggerOptions=$this->oc['SourcePot\Datapool\Foundation\Signals']->getTriggerOptions();
        foreach($base['delayingrules'] as $ruleId=>$rule){
            // calculate new 'Condition met'
            $triggerValue=$this->oc['SourcePot\Datapool\Foundation\Signals']->isActiveTrigger($rule['Content']['Trigger'],TRUE);
            if ($triggerValue && !empty($rule['Content']['Reset trigger'])){
                $trigger2reset[$rule['Content']['Trigger']]=TRUE;
            } else {
                $trigger2reset[$rule['Content']['Trigger']]=FALSE;
            }
            $spanOpen=$triggerValue?'<span class="status-on">':'<span class="status-off">';
            $invSpanOpen=$triggerValue?'<span class="status-off">':'<span class="status-on">';
            $lastValue=$result['Delaying statistics']['Condition met']['value'];
            $result['Delaying statistics']['Condition met']['value']=match($lastOparation){
                'or'=>$lastValue | $triggerValue,
                'and'=>$lastValue & $triggerValue,
                'xor'=>$lastValue ^ $triggerValue,
            };
            $debugArr['Steps'][]=['lastValue'=>$lastValue,'lastOparation'=>$lastOparation,'triggerValue'=>$triggerValue,'result'=>$result['Delaying statistics']['Condition met']['value']];
            // add 'Contition' result
            if (!$isFirstRule){
                $result['Delaying statistics']['Condition']['value'].='<b>'.$lastOparation.'</b>';
            }
            $triggerName=(isset($triggerOptions[$rule['Content']['Trigger']]))?$triggerOptions[$rule['Content']['Trigger']]:'?';
            $result['Delaying statistics']['Condition']['value'].=' '.$spanOpen.$triggerName.'</span>';
            $result['Delaying statistics']['Condition']['value'].=' ';
            $lastOparation=$rule['Content']['Combine with next row'];
            $isFirstRule=FALSE;
        }
        // move or send entries
        if ($testRun===2){
            $testRun=FALSE;
            $manualTrigger=TRUE;
        } else {
            $manualTrigger=FALSE;
        }
        // move entries and send emails
        if ($result['Delaying statistics']['Condition met']['value'] || $manualTrigger==2){
            $result=$this->moveEntries($base,$callingElement,$result,$testRun);
            $result=$this->sentEmail($base,$callingElement,$result,$testRun);
        }
        // reset trigger
        foreach($trigger2reset as $triggerEntryId=>$toReset){
            $toReset=(!empty($params['Content']['Reset all trigger when condition is met']) && $result['Delaying statistics']['Condition met']['value'])?TRUE:$toReset;
            if ($toReset){
                if (empty($testRun)){
                    $this->oc['SourcePot\Datapool\Foundation\Signals']->resetTrigger($triggerEntryId,TRUE);
                }
                $result['Delaying statistics']['Reset trigger']['value']++;
            }
        }
        // finalize documentation
        $result['Delaying statistics']['Condition met']['value']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($result['Delaying statistics']['Condition met']['value']);
        if ($isDebugging){
            $debugArr['trigger2reset']=$trigger2reset;
            $debugArr['result']=$result;
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        return $result;
    }
    
    private function moveEntries($base,$callingElement,$result,$testRun){
        $params=current($base['delayingparams']);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $entry){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$base['entryTemplates'][$params['Content']['Forward to canvas element']],TRUE,$testRun);
            $result['Delaying statistics']['Moved entries']['value']++;
        }
        return $result;
    }
    
    private function sentEmail($base,$callingElement,$result,$testRun){
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $entry){
            
        }
        return $result;
    }

}
?>