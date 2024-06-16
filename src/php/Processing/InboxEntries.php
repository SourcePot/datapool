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

class InboxEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    private $inboxClass='';
    private $base=array();

    private $conditions=array('stripos'=>'contains',
                             'stripos!'=>'does not contain',
                            );

    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }
    
    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }

    public function getEntryTable():string{return $this->entryTable;}

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
                'run'=>$this->runInboxEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runInboxEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getInboxEntriesWidget($callingElement),
                'settings'=>$this->getInboxEntriesSettings($callingElement),
                'info'=>$this->getInboxEntriesInfo($callingElement),
            };
        }
    }

    private function getInboxEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Inbox','generic',$callingElement,array('method'=>'getInboxEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
     private function getInboxEntriesInfo($callingElement){
        $matrix=array();
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        return $html;
    }
    
    public function getInboxEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runInboxEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runInboxEntries($arr['selector'],1);
        } else if (isset($formData['cmd']['receive'])){
            $result=$this->runInboxEntries($arr['selector'],2);
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
        $btnArr['value']='Receive';
        $btnArr['key']=array('receive');
        $matrix['Commands']['Receive']=$btnArr;
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Inbox widget'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getInboxEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Inbox entries settings','generic',$callingElement,array('method'=>'getInboxEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getInboxEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->inboxParams($arr['selector']);
        $arr['callingClass']=$arr['selector']['Folder'];
        if (isset($this->oc[$this->inboxClass])){
            $arr['html'].=$this->oc[$this->inboxClass]->receiverPluginHtml($arr);
        }
        $arr['html'].=$this->inboxConditionRules($arr['selector']);
        $arr['html'].=$this->inboxForwardingRules($arr['selector']);
        return $arr;
    }
    
    private function inboxParams($callingElement){
        $return=array('html'=>'','Parameter'=>array(),'result'=>array());
        if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
        $options=$this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Receiver');
        $contentStructure=array('Inbox source'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>0,'options'=>$options),
                                'Save'=>array('method'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
                            );
        // get selctorB
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']['Content']=array('Column to delay'=>'Name');
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        $elementId=key($formData['val']);
        if (isset($formData['cmd'][$elementId])){
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
            // synchronize with data source
            $callingElement['Content']['Selector']=$this->oc[$arr['selector']['Content']['Inbox source']]->receiverSelector($callingElement['Folder']);
            $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($callingElement,TRUE);
        }
        // load inbox class
        if (isset($arr['selector']['Content']['Inbox source'])){$this->inboxClass=$arr['selector']['Content']['Inbox source'];}
        // get HTML
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Forward entries from inbox';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['trStyle']=array('background-color'=>'#a00');}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }

    private function inboxConditionRules($callingElement){
        $contentStructure=array('Column'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
                                'Condition'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'stripos','options'=>$this->conditions),
                                'Value A'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                ' '=>array('method'=>'element','tag'=>'p','keep-element-content'=>TRUE,'element-content'=>'OR'),
                                'Value B'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                );
        $contentStructure['Column']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Define conditions';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
        
    private function inboxForwardingRules($callingElement){
        $base=$this->getBaseArr($callingElement);
        $conditionRuleOptions=array(''=>'-');
        if (!empty($base['inboxconditionrules'])){
            foreach($base['inboxconditionrules'] as $ruleId=>$rule){
                $ruleIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleId,TRUE);
                $conditionRuleOptions[$ruleId]='Rule '.$ruleIndex;
            }
        }
        $contentStructure=array('Condition A'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>$conditionRuleOptions),
                                ' '=>array('method'=>'element','tag'=>'p','keep-element-content'=>TRUE,'element-content'=>'AND'),
                                'Condition B'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>$conditionRuleOptions),
                                '  '=>array('method'=>'element','tag'=>'p','keep-element-content'=>TRUE,'element-content'=>'AND'),
                                'Condition C'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'','options'=>$conditionRuleOptions),
                                'Forward to'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                );
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Forwarding conditions';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    public function runInboxEntries($callingElement,$testRun=1){
        $base=$this->getBaseArr($callingElement);
        $inboxParams=current($base['inboxparams']);
        $inboxParams=$inboxParams['Content'];
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Inbox statistics'=>array('Items processed'=>array('value'=>0),
                                                'Itmes forwarded'=>array('value'=>0),
                                                'Itmes already processed and skipped'=>array('value'=>0),
                                               ),
                      'Processing results'=>array(),
                     );
        if ($testRun==2){
            if (isset($this->oc[$inboxParams['Inbox source']])){
                $inboxResult=$this->oc[$inboxParams['Inbox source']]->receive($callingElement['Folder']);    
            } else {
                $this->oc['logger']->log('warning','Function {class}::{function} failed. Inbox "{inboxSource}" was not initiated.',array('class'=>__CLASS__,'class'=>__CLASS__,'inboxSource'=>$inboxParams['Inbox source']));         
                $result['Inbox statistics']['error']['value']='Inbox source not set';
            }
            foreach($inboxResult as $key=>$value){
                if (empty($value)){continue;}
                $result['Inbox statistics'][$key]['value']=$value;
            }
        } else {
            // loop through source entries and parse these entries
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE,'Read','Date',FALSE) as $entry){
                $result=$this->processEntry($entry,$base,$callingElement,$result,$testRun);
                $result['Inbox statistics']['Items processed']['value']++;
            }
            if ($testRun && !empty($meta)){
                $result[$inboxParams['Inbox source']]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($meta);
            }
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function processEntry($entry,$base,$callingElement,$result,$testRun,$isDebugging=FALSE){
        $userId=empty($_SESSION['currentUser']['EntryId'])?'ANONYM':$_SESSION['currentUser']['EntryId'];
        $params=current($base['inboxparams']);
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
        // process condition rules
        if (empty($base['inboxconditionrules'])){
            $result['Statistics']['Error']['value']='Condition rules missing';
            return $result;
        }
        $entryResultArr=array('Name'=>$entry['Name']);
        foreach($base['inboxconditionrules'] as $ruleId=>$rule){
            $ruleIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleId,TRUE);
            $conditionMet[$ruleId]=FALSE;
            foreach($flatEntry as $flatKey=>$value){
                if (mb_strpos($flatKey,$rule['Content']['Column'])!==0){continue;}
                $needlePosA=(empty($rule['Content']['Value A']))?FALSE:mb_stripos($value,$rule['Content']['Value A']);
                $needlePosB=(empty($rule['Content']['Value B']))?FALSE:mb_stripos($value,$rule['Content']['Value B']);
                $conditionMet[$ruleId]=match($rule['Content']['Condition']){
                    'stripos'=>$needlePosA!==FALSE || $needlePosB!==FALSE,
                    'stripos!'=>$needlePosA===FALSE && $needlePosB===FALSE,
                };
                if ($conditionMet[$ruleId]){break;}
            }
            $entryResultArr['Condition rule '.$ruleIndex]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($conditionMet[$ruleId]);    
        }
        // process forwarding rules
        if (empty($base['inboxforwardingrules'])){
            $result['Statistics']['Error']['value']='Forwarding rules missing';
            return $result;
        }
        foreach($base['inboxforwardingrules'] as $ruleId=>$rule){
            $ruleIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleId,TRUE);
            $forward[$ruleId]=TRUE;
            foreach($rule['Content'] as $key=>$condRuleId){
                if (mb_strpos($key,'Condition')===FALSE || empty($condRuleId)){continue;}
                if (empty($conditionMet[$condRuleId])){
                    $forward[$ruleId]=FALSE;
                    break;
                }
            }
            $entryResultArr['Forward rule '.$ruleIndex]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($forward[$ruleId]);    
            if ($forward[$ruleId]){
                $forward[$ruleId]=$rule['Content']['Forward to'];
                $forwardToEntry=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$rule['Content']['Forward to']);
                $forwardToEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($forwardToEntry,TRUE);
                $entryResultArr['Forwarded to']=$forwardToEntry['Content']['Style']['Text'];    
            } else {
                $entryResultArr['Forwarded to']='-';    
            }
        }
        // forward relevant entries
        $inboxEntry=$entry;
        foreach($forward as $ruleId=>$forwardTo){
            $ruleIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleId,TRUE);
            if (is_bool($forwardTo)){
                $statisticsKey='Forwarding rule '.$ruleIndex.' failed';
                $targetEntry=$entry;
            } else if (isset($base['entryTemplates'][$forwardTo])){
                // conditions to forward entry  met
                $statisticsKey='Forwarding rule '.$ruleIndex.' success';
                $targetSelector=$base['entryTemplates'][$forwardTo];
                $processingLogText='Rule "'.$ruleId.'" condition met, forwarded entry to "'.implode(' &rarr; ',$targetSelector).'"';
                $inboxEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entry,'Processing log',array('forwarded'=>$processingLogText),FALSE);
                if ($this->itemAlreadyProcessed($entry,$processingLogText)){
                    $result['Inbox statistics']['Itmes already processed and skipped']['value']++;
                } else {
                    $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($inboxEntry,$targetSelector,TRUE,$testRun,TRUE,TRUE);
                    $result['Inbox statistics']['Itmes forwarded']['value']++;
                }
            } else {
                // conditions to forward entry NOT met
                $statisticsKey='Forwarding rule '.$ruleIndex.' error';
                $targetEntry=$entry;
                $this->oc['logger']->log('notice','"{class}" forwarding rule "{ruleId}" error',array('class'=>__CLASS__,'ruleId'=>$ruleId));
            }
            // update statistic
            if (isset($result['Inbox statistics'][$statisticsKey]['value'])){
                $result['Inbox statistics'][$statisticsKey]['value']++;
            } else {
                $result['Inbox statistics'][$statisticsKey]['value']=1;
            }
        }
        $index=count($result['Processing results'])+1;
        $result['Processing results'][$index]=$entryResultArr;
        if ($isDebugging){
            $debugArr['result']=$result;
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        return $result;
    }
    
    private function itemAlreadyProcessed($item,$processingLogText){
        if (!isset($item['Params']['Processing log'])){return FALSE;}
        foreach($item['Params']['Processing log'] as $log){
            if (!isset($log['forwarded'])){continue;}
            if (strcmp($log['forwarded'],$processingLogText)===0){
                return TRUE;
            }
        }
        return FALSE;
    }
    
    private function getBaseArr($callingElement){
        $base=array('Script start timestamp'=>hrtime(TRUE));
        $entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId']);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            $key=strtolower($entry['Group']);
            $base[$key][$entry['EntryId']]=$entry;
            // entry template
            foreach($entry['Content'] as $contentKey=>$content){
                if (is_array($content)){continue;}
                if (mb_strpos($content,'EID')!==0 || mb_strpos($content,'eid')===FALSE){continue;}
                $template=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->entryId2selector($content);
                if ($template){$base['entryTemplates'][$content]=$template;}
            }
        }
        return $base;
    }

}
?>