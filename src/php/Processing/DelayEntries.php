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
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }
    
    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
    }

    public function getEntryTable():string{return $this->entryTable;}
    
    public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
        // This method is the interface of this data processing class
        // The Argument $action selects the method to be invoked and
        // argument $callingElementSelector$ provides the entry which triggerd the action.
        // $callingElementSelector ... array('Source'=>'...', 'EntryId'=>'...', ...)
        // If the requested action does not exist the method returns FALSE and 
        // TRUE, a value or an array otherwise.
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        switch($action){
            case 'run':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runDelayEntries($callingElement,$testRunOnly=0);
                }
                break;
            case 'test':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runDelayEntries($callingElement,$testRunOnly=1);
                }
                break;
            case 'widget':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getDelayEntriesWidget($callingElement);
                }
                break;
            case 'settings':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getDelayEntriesSettings($callingElement);
                }
                break;
            case 'info':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getDelayEntriesInfo($callingElement);
                }
                break;
        }
        return FALSE;
    }

    private function getDelayEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Delaying','generic',$callingElement,array('method'=>'getDelayEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
    public function getDelayEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runDelayEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runDelayEntries($arr['selector'],1);
        } else if (isset($formData['cmd']['trigger'])){
            $result=$this->runDelayEntries($arr['selector'],2);
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
        $matrix['Commands']['Trigger']=array('tag'=>'button','element-content'=>'Manual trigger','key'=>array('trigger'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Delaying widget'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getDelayEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Delaying entries settings','generic',$callingElement,array('method'=>'getDelayEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getDelayEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->delayingParams($arr['selector']);
        $arr['html'].=$this->delayingRules($arr['selector']);
        //$selectorMatrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
        //$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for Delaying'));
        return $arr;
    }
    
    private function delayingParams($callingElement){
        $return=array('html'=>'','Parameter'=>array(),'result'=>array());
        if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
        $contentStructure=array('Forward to canvas element'=>array('method'=>'canvasElementSelect','addColumns'=>array(''=>'...'),'excontainer'=>TRUE),
                                'Reset all trigger when condition is met'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>array('No','Yes')),
                                'Save'=>array('method'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
                            );
        // get selctorB
        $arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);;
        $arr['selector']['Content']=array('Column to delay'=>'Name');
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
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }

    private function delayingRules($callingElement){
        $triggerOptions=$this->oc['SourcePot\Datapool\Foundation\Signals']->getTriggerOptions();
        $contentStructure=array('Trigger'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>$triggerOptions),
                                'Reset trigger'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>array('No','Yes')),
                                'Combine with next row'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'or','options'=>array('or'=>'OR','and'=>'AND','xor'=>'XOR',)),
                                );
        $arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,FALSE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Delay ends if all rules combined are TRUE.';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
        
    public function runDelayEntries($callingElement,$testRun=1){
        $base=array('Script start timestamp'=>hrtime(TRUE));
        $entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId']);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            $key=explode('|',$entry['Type']);
            $key=array_pop($key);
            $base[$key][$entry['EntryId']]=$entry;
            // entry template
            foreach($entry['Content'] as $contentKey=>$content){
                if (is_array($content)){continue;}
                if (strpos($content,'EID')!==0 || strpos($content,'eid')===FALSE){continue;}
                $template=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->entryId2selector($content);
                if ($template){$base['entryTemplates'][$content]=$template;}
            }
        }
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Delaying statistics'=>array('Condition'=>array('value'=>''),
                                                 'Condition met'=>array('value'=>0),
                                                 'Reset trigger'=>array('value'=>0),
                                                 'Moved entries'=>array('value'=>0),
                                                 'Sent by email'=>array('value'=>''),
                                                 )
                     );
        $result=$this->checkCondition($base,$callingElement,$result,$testRun);
        //
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function checkCondition($base,$callingElement,$result,$testRun,$isDebugging=FALSE){
        $params=current($base['delayingparams']);
        $debugArr=array('params'=>$params,'rules'=>$base['delayingrules'],'selector'=>$callingElement,'testRun'=>$testRun);
        $isFirstRule=TRUE;
        $lastOparation='or';
        $trigger2reset=array();
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
            $debugArr['Steps'][]=array('lastValue'=>$lastValue,'lastOparation'=>$lastOparation,'triggerValue'=>$triggerValue,'result'=>$result['Delaying statistics']['Condition met']['value']);
            // add 'Contition' result
            if (!$isFirstRule){
                $result['Delaying statistics']['Condition']['value'].='<b>'.$lastOparation.'</b>';
            }
            $triggerName=$triggerOptions[$rule['Content']['Trigger']];
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
            $entry=$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog2entry($entry,'Processing log',array('action'=>'Enties moved'),FALSE);
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

    public function callingElement2arr($callingClass,$callingFunction,$callingElement){
        if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
        $type=$this->oc['SourcePot\Datapool\Root']->class2source(__CLASS__);
        $type.='|'.$callingFunction;
        $entry=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Type'),0);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ALL_CONTENTADMIN_R');
        $entry['Content']=array();
        $arr=array('callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'selector'=>$entry);
        return $arr;
    }

}
?>