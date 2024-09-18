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

class CopyEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
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
                'run'=>$this->runCopyEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runCopyEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getCopyEntriesWidget($callingElement),
                'settings'=>$this->getCopyEntriesSettings($callingElement),
                'info'=>$this->getCopyEntriesInfo($callingElement),
            };
        }
    }

    private function getCopyEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Copying','generic',$callingElement,array('method'=>'getCopyEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
     private function getCopyEntriesInfo($callingElement){
        $matrix=array();
        $matrix['Description']=array('<p>This processor copies entries to various targets.</p>');
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'?'));
        return $html;
    }
    
    public function getCopyEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runCopyEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runCopyEntries($arr['selector'],1);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Copying'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getCopyEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Copying settings','generic',$callingElement,array('method'=>'getCopyEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getCopyEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->copyingRules($arr['selector']);
        return $arr;
    }

    private function copyingRules($callingElement){
        $triggerOptions=$this->oc['SourcePot\Datapool\Foundation\Signals']->getTriggerOptions();
        $contentStructure=array('Target'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                );
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Select rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
        
    public function runCopyEntries($callingElement,$testRun=1){
        $base=array('copyingrules'=>array());
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        $base['canvasElements']=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements($callingElement['Folder']);
        // get targets
        $base['targets']=array();
        foreach($base['copyingrules'] as $ruleId=>$rule){
            foreach($base['canvasElements'] as $targetName=>$target){
                if ($target['EntryId']==$rule['Content']['Target']){
                    $base['targets'][$target['EntryId']]=$target;
                }
            }
        }
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Copying results'=>array('Entries'=>array('value'=>0)));
        // loop through entries
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            $result['Copying results']['Entries']['value']++;
            $result=$this->copyEntry($base,$sourceEntry,$result,$testRun);
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function copyEntry($base,$sourceEntry,$result,$testRun){
        foreach($base['copyingrules'] as $ruleId=>$rule){
            $targetEntryId=$rule['Content']['Target'];
            $target=$base['targets'][$targetEntryId];
            $targetName=$target['Content']['Style']['Text'];
            if (isset($result['Copying results'][$targetName]['value'])){
                $result['Copying results'][$targetName]['value']++;
            } else {
                {$result['Copying results'][$targetName]['value']=1;}
            }
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$rule['Content']['Target']],TRUE,$testRun,TRUE);
        }
        if (!$testRun){
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries(array('Source'=>$sourceEntry['Source'],'EntryId'=>$sourceEntry['EntryId']),TRUE);
        }
        return $result;
    }

}
?>