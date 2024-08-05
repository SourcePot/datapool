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
                'run'=>$this->runForwardEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runForwardEntries($callingElement,$testRunOnly=TRUE),
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
            $result=$this->runForwardEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runForwardEntries($arr['selector'],1);
        } else if (isset($formData['cmd']['receive'])){
            $result=$this->runForwardEntries($arr['selector'],2);
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
        $arr['html'].=$this->forwardingRules($arr['selector']);
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
            $callingElement['Content']['Selector']=$this->oc[$arr['selector']['Content']['Inbox source']]->receiverSelector($callingElement['EntryId']);
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

    private function forwardingRules($callingElement){
        $contentStructure=array('...'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'&&','options'=>array('&&'=>'AND','||'=>'OR'),'keep-element-content'=>TRUE),
                                'Value source'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','standardColumsOnly'=>FALSE,'addSourceValueColumn'=>TRUE),
                                '| '=>array('method'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE),
                                'Value data type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE),
                                'OR'=>array('method'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE),
                                'Regular expression'=>array('method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'e.g. \d+','excontainer'=>TRUE),
                                ' |'=>array('method'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE),
                                'compare'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'strpos','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getConditions(),'keep-element-content'=>TRUE),
                                'with'=>array('method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'invoice','excontainer'=>TRUE),
                                'Forward on success'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                );
        $contentStructure['Value source']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Forwarding rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
   
    public function runForwardEntries($callingElement,$testRun=1){
        $base=array('forwardingparams'=>array(),'forwardingrules'=>array());
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        $base['canvasElements']=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements($callingElement['Folder']);
        // get targets template
        $base['targets']=array();
        foreach($base['forwardingrules'] as $ruleId=>$rule){
            foreach($base['canvasElements'] as $targetName=>$target){
                if ($target['EntryId']==$rule['Content']['Forward on success']){
                    $base['targets'][$targetName]=$target['EntryId'];
                }
            }
        }
        $result=array('Processing statistics'=>array('Entries'=>array('value'=>0),
                                                     'Itmes already processed and skipped'=>array('value'=>0),
                                                     'Itmes forwarded'=>array('value'=>0),
                                                    ),
                      'Forwarded'=>array(),
                     );
        // receive entries
        if ($testRun==2 || $testRun==0){
            $inboxParams=current($base['inboxparams'])['Content'];
            if (isset($this->oc[$inboxParams['Inbox source']])){
                $inboxResult=$this->oc[$inboxParams['Inbox source']]->receive($callingElement['EntryId']);
            } else {
                $this->oc['logger']->log('warning','Function {class}::{function} failed. Inbox "{inboxSource}" was not initiated.',array('class'=>__CLASS__,'class'=>__CLASS__,'inboxSource'=>$inboxParams['Inbox source']));         
                $result['Inbox statistics']['error']['value']='Inbox source not set';
            }
            foreach($inboxResult as $key=>$value){
                //if (empty($value)){continue;}
                $result['Processing statistics'][$key]['value']=$value;
            }
        }
        // process entry forwarding
        if ($testRun<2){
            // loop through source entries and parse these entries
            $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
            // loop through entries
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
                $result['Processing statistics']['Entries']['value']++;
                $result=$this->forwardEntry($base,$sourceEntry,$result,$testRun);
            }
        }
        
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function forwardEntry($base,$sourceEntry,$result,$testRun){
        $params=current($base['forwardingparams']);
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        $debugArr=array('base'=>$base,'testRun'=>$testRun);
        $forwardTo=array();
        $targets=array();
        foreach($base['forwardingrules'] as $ruleId=>$rule){
            if (isset($flatSourceEntry[$rule['Content']['Value source']])){$valueA=$flatSourceEntry[$rule['Content']['Value source']];} else {$valueA='';}
            $valueA=$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($valueA,$rule['Content']['Value data type']);
            $conditionMet=$this->oc['SourcePot\Datapool\Tools\MiscTools']->isTrue($valueA,$rule['Content']['with'],$rule['Content']['compare']);
            if (isset($forwardTo[$rule['Content']['Forward on success']])){
                if ($rule['Content']['...']==='&&'){
                    $forwardTo[$rule['Content']['Forward on success']]=$forwardTo[$rule['Content']['Forward on success']] && $conditionMet;
                } else if ($rule['Content']['...']==='||'){
                    $forwardTo[$rule['Content']['Forward on success']]=$forwardTo[$rule['Content']['Forward on success']] || $conditionMet;
                } else {
                    $rule['Content']['ruleIndex']=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleId);
                    $this->oc['logger']->log('notice','Rule "{ruleIndex}" is invalid, key "... = {...}" is undefined',$rule['Content']);
                }
            } else {
                $forwardTo[$rule['Content']['Forward on success']]=$conditionMet;
            }
        }
        $targets=$base['targets'];
        foreach($forwardTo as $targetEntryId=>$conditionMet){
            $targetName=array_search($targetEntryId,$base['targets']);
            $targets[$targetName]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($conditionMet);
            if (count($result['Forwarded'])<10){
                $result['Forwarded'][$sourceEntry['Name']]=$targets;
            } else {
                $key=key($targets);
                $result['Forwarded']['...'][$key]='...';
            }
            if ($conditionMet){
                $processingLogText='Conditions met, forwarded entry to "'.$targetName.'"';
                if ($this->itemAlreadyProcessed($sourceEntry,$processingLogText)){
                    $result['Processing statistics']['Itmes already processed and skipped']['value']++;
                } else {
                    $sourceEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($sourceEntry,'Processing log',array('forwarded'=>$processingLogText),FALSE);
                    $this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$targetEntryId],TRUE,$testRun,TRUE,TRUE);
                    $result['Processing statistics']['Itmes forwarded']['value']++;
                }
            }
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
}
?>