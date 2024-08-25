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

class ForwardEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    private $maxResultTableLength=50;

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
                'widget'=>$this->getForwardEntriesWidget($callingElement),
                'settings'=>$this->getForwardEntriesSettings($callingElement),
                'info'=>$this->getForwardEntriesInfo($callingElement),
            };
        }
    }

    private function getForwardEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Selecting','generic',$callingElement,array('method'=>'getForwardEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
     private function getForwardEntriesInfo($callingElement){
        $matrix=array();
        $matrix['Description']=array('<p style="width:40em;">This processor forwards entries to various targets on the basis of conditions. If there are several rules for a forwarding target, all rules must be fulfilled in order to forward the entry.<br/>Rules are linked by "AND" or "OR" (rule key "..."), the oparation is ignored for the first rule of each target.</p>');
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        return $html;
    }
    
    public function getForwardEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runForwardEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runForwardEntries($arr['selector'],1);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Forwarding'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getForwardEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Forwarding entries params','generic',$callingElement,array('method'=>'getForwardEntriesParamsHtml','classWithNamespace'=>__CLASS__),array());
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Forwarding entries settings','generic',$callingElement,array('method'=>'getForwardEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getForwardEntriesParamsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->forwardingParams($arr['selector']);
        return $arr;
    }

    public function getForwardEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->forwardingRules($arr['selector']);
        return $arr;
    }

    private function forwardingParams($callingElement){
        $contentStructure=array('Keep source entries'=>array('method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>array(0=>'No, move entries',1=>'Yes, copy entries')),
                                'Save'=>array('method'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
                                );
        // get selctor
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
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
        $arr['caption']='Forwarding parameters';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['trStyle']=array('background-color'=>'#a00');}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }
    
    private function forwardingRules($callingElement){
        $contentStructure=array('...'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'&','options'=>array('&&'=>'AND','||'=>'OR'),'keep-element-content'=>TRUE),
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
        $arr['caption']='Select rules';
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
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Forwarding statistics'=>array('Entries'=>array('value'=>0),
                                                    ),
                      'Forwarded'=>array(),
                    );
        // loop through entries
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            $result['Forwarding statistics']['Entries']['value']++;
            $result=$this->forwardEntry($base,$sourceEntry,$result,$testRun);
        }
        if (count($result['Forwarded'])>=$this->maxResultTableLength){
            $currentValues=current($result['Forwarded']);
            foreach($currentValues as $key=>$value){$currentValues[$key]='...';}
            $result['Forwarded']['...']=$currentValues;
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
            if (count($result['Forwarded'])<$this->maxResultTableLength){
                $result['Forwarded'][$sourceEntry['Name']]=$targets;
            }
            if ($conditionMet){
                $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$targetEntryId],TRUE,$testRun,$params['Content']['Keep source entries']);
            }
        }
        return $result;
    }

}
?>