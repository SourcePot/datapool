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

class StatisticEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;

    private $entryTable='';
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );

    private $statisticOptions=array(''=>'','EP'=>'European Patents','Costs'=>'Costs');
    private $skipConditions=array('always'=>'always',
                                 'stripos'=>'is substring of',
                                 'stripos!'=>'is not substring of',
                                 'strcmp'=>'is equal to',
                                 'lt'=>'<',
                                 'le'=>'<=',
                                 'eq'=>'=',
                                 'ne'=>'<>',
                                 'gt'=>'>',
                                 'ge'=>'>=',
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

    /**
     * This method is the interface of this data processing class
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method 
     * @param string $action Selects the requested process to be run  
     *
     * @return bool TRUE the requested action exists or FALSE if not
     */
    public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        switch($action){
            case 'run':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                return $this->runStatisticEntries($callingElement,$testRunOnly=FALSE);
                }
                break;
            case 'test':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runStatisticEntries($callingElement,$testRunOnly=TRUE);
                }
                break;
            case 'widget':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getStatisticEntriesHtml($callingElement);
                }
                break;
            case 'settings':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getStatisticEntriesSettingsHtml($callingElement);
                }
                break;
            case 'info':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getStatisticEntriesInfo($callingElement);
                }
                break;
        }
        return FALSE;
    }

    private function getStatisticEntriesInfo($callingElement){
        $matrix=array();
        $matrix['Statistic control']=array('Msg'=>'PLease select the type of statistic you want to run and the desired output format.
                                                   If you choose "CSV", entries will be created with attached csv-files containing the data stored in the Content-column.<br/>
                                                   There will be one entry for each Name-column content i.e., the EntryId will be created from the Name.'
                                          );
        $matrix['Mapping']=array('Msg'=>'Use "Mapping" to map the content of specific columns/fields from the Source to the target. If you have multiple mapping rules to the same target, the content will be append with the separator "|".');
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Info','class'=>'max-content'));
        return $html;
    }

    private function getStatisticEntriesHtml($callingElement){
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Statistics','generic',$callingElement,array('method'=>'getStatisticEntriesWidget','classWithNamespace'=>__CLASS__),array());
        return $html;
    }
    
    public function getStatisticEntriesWidget($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runStatisticEntries($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runStatisticEntries($arr['selector'],TRUE);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Statistics widget'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getStatisticEntriesSettingsHtml($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Statistics settings','generic',$callingElement,array('method'=>'getStatisticEntriesSettings','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }

    public function getStatisticEntriesSettings($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->statisticTypeSelector($arr['selector']);
        // get statistic type setting
        $settings=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$arr['selector']);
        if (isset($settings['statistictypeselector'])){
            $statistictypeselector=current($settings['statistictypeselector']);
            $type=$statistictypeselector['Content']['Type'];
        } else {
            $type='';
        }
        // get all settings widgets
        $widgets=array('statisticSourceElements','statisticMappingRules','statisticRules');
        foreach($widgets as $widget){
            $paramsMethod=$widget.$type;
            if (method_exists(__CLASS__,$paramsMethod)){
                $arr['html'].=$this->$paramsMethod($arr['selector']);
            } else {
                $arr['html'].='Method "'.$paramsMethod.'()" for stytistic type "'.$type.'" does not exist';    
            }
        }
        return $arr;
    }
    
    private function statisticSourceElements($callingElement){
        $matrix=array('Error'=>array('Msg'=>'Please select a statistic type'));
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>array(),'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Error'));    
    }

    private function statisticTypeSelector($callingElement){
        $contentStructure=array('Type'=>array('method'=>'select','value'=>'','options'=>$this->statisticOptions,'keep-element-content'=>TRUE,'excontainer'=>FALSE),
                                'Output'=>array('method'=>'select','value'=>'entries','options'=>array('entries'=>'Entries','csv'=>'CSV'),'keep-element-content'=>TRUE,'excontainer'=>FALSE),
                                );
        // get selector
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (!empty($formData['changed'])){
            $elementId=key($formData['val']);
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        }
        // get HTML
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Statistic control';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }
    
// EP stytistic methods
    
    private function statisticSourceElementsEP($callingElement){
        $settings=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement);
        if (isset($settings['statisticsourceelementsep'])){
            $selector=current($settings['statisticsourceelementsep']);
            $selector=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$selector['Content']['Source']);
            $sourceElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,TRUE);
        } else {
            $sourceElement=array();
        }
        //
        $contentStructure=array('Source'=>array('method'=>'canvasElementSelect','excontainer'=>FALSE),
                                'Fallnummer column'=>array('method'=>'keySelect','value'=>'Date','standardColumsOnly'=>FALSE,'excontainer'=>FALSE),
                                );
        $contentStructure['Fallnummer column']+=$sourceElement['Content']['Selector'];
        // get selector
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (!empty($formData['changed'])){
            $elementId=key($formData['val']);
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        }
        // get HTML
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='EP statistic source';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>array(),'hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }
    
    private function statisticRulesEP($callingElement){
        $settings=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement);
        if (isset($settings['statisticsourceelementsep'])){
            $selector=current($settings['statisticsourceelementsep']);
            $selector=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$selector['Content']['Source']);
            $sourceElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,TRUE);
        } else {
            $sourceElement=array();
        }
        //
        $contentStructure=array('Data type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE),
                                'Skip if'=>array('method'=>'keySelect','value'=>'Date','standardColumsOnly'=>FALSE,'excontainer'=>TRUE),
                                'Condition'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'ne','options'=>$this->skipConditions),
                                'Value'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                );
        $contentStructure['Skip if']+=$sourceElement['Content']['Selector'];
        if (empty($callingElement['Content']['Selector']['Source'])){return $html;}
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Skip if any condition is met';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function statisticMappingRulesEP($callingElement){
        $settings=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement);
        if (isset($settings['statisticsourceelementsep'])){
            $selector=current($settings['statisticsourceelementsep']);
            $selector=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$selector['Content']['Source']);
            $sourceElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,TRUE);
        } else {
            $sourceElement=array();
        }
        //
        $contentStructure=array('Source column'=>array('method'=>'keySelect','value'=>'Date','standardColumsOnly'=>FALSE,'excontainer'=>TRUE),
                                'Target column'=>array('method'=>'keySelect','value'=>'Date','standardColumsOnly'=>TRUE,'excontainer'=>TRUE),
                                'Sub key'=>array('method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'Keep empty if n/a','excontainer'=>TRUE),
                                );
        $contentStructure['Source column']+=$sourceElement['Content']['Selector'];
        $contentStructure['Target column']+=$callingElement['Content']['Selector'];
        if (empty($callingElement['Content']['Selector']['Source'])){return $html;}
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Mapping';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function runStatisticEP($callingElement,$result,$testRun=FALSE)
    {
        // get settings
        $settings=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement);
        if (!isset($settings['statisticsourceelementsep'])){
            $result['Errors']['Statistics source element']=array('value'=>'Missing');
            return $result;
        }
        $sourceSettings=current($settings['statisticsourceelementsep']);
        $sourceSettings=$sourceSettings['Content'];
        // get rules
        $skipRules=array();
        if (!isset($settings['statisticrulesep'])){
            $result['Errors']['Statistic rules']=array('value'=>'Missing');
            return $result;
        }
        foreach($settings['statisticrulesep'] as $ruleIndex=>$rule){
            $skipRules[$ruleIndex]=$rule['Content'];
            $skipRules[$ruleIndex]['Value']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($rule['Content']['Value'],$rule['Content']['Data type']);
        }
        ksort($skipRules);
        // get Source element selector
        $selector=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$sourceSettings['Source']);
        $sourceElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,TRUE);
        if (!isset($sourceElement['Content']['Selector'])){
            $result['Errors']['Statistics source element selector']=array('value'=>'Is not set');
            return $result;
        }
        $columnsTemplate=array();
        $statisticEntries=array();
        $result['Entry statistic']=array('Skipped'=>array('value'=>0),
                                         'Skipped not EP file'=>array('value'=>0),
                                         'Skipped due to rule source missing'=>array('value'=>0),
                                         'Skipped due to rule'=>array('value'=>0),
                                         'Skipped non EP validation file'=>array('value'=>0),
                                         'Entries added'=>array('value'=>0),
                                         'CSV rows added'=>array('value'=>0),
                                         'Match'=>array('value'=>0),
                                         );
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($sourceElement['Content']['Selector'],TRUE,'Read','EntryId',TRUE) as $entry){
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
            // is required data present?
            if (!isset($entry[$sourceSettings['Fallnummer column']])){
                $result['Entry statistic']['Skipped']['value']++;
                continue;
            }
            // is European application?
            if (strpos($entry[$sourceSettings['Fallnummer column']],'EP')===FALSE && strpos($entry[$sourceSettings['Fallnummer column']],'WE')===FALSE){
                $result['Entry statistic']['Skipped not EP file']['value']++;
                continue;
            }
            // filter rules
            foreach($skipRules as $ruleIndex=>$rule){
                if (!isset($entry[$rule['Skip if']])){
                    $result['Entry statistic']['Skipped due to rule source missing']['value']++;
                    continue 2;
                }
                $skipIf=$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($entry[$rule['Skip if']],$rule['Data type']);
                if (isset($skipIf['Timestamp'])){
                    $skipIf=$skipIf['Timestamp'];
                    $value=$rule['Value']['Timestamp'];
                } else {
                    $value=$rule['Value'];
                }
                if ($this->check($skipIf,$rule['Condition'],$value)){
                    $result['Entry statistic']['Skipped due to rule']['value']++;
                    continue 2;
                }
            }
            $applicationKey=preg_replace('/[^0-9]+/','|',$entry[$sourceSettings['Fallnummer column']]);
            $countryKey=trim(substr($entry[$sourceSettings['Fallnummer column']],12,2));
            if (empty($countryKey)){
                $result['Entry statistic']['Skipped non EP validation file']['value']++;
                continue;
            }
            // loop through mapping rules
            if (!isset($statisticEntries[$applicationKey])){
                $statisticEntries[$applicationKey]=$callingElement['Content']['Selector'];
                $statisticEntries[$applicationKey]['Type']=$statisticEntries[$applicationKey]['Source'];
                foreach($settings['statisticmappingrulesep'] as $ruleMappingIndex=>$mappingRule){
                    $mappingRule=$mappingRule['Content'];
                    $mappingValue=(isset($entry[$mappingRule['Source column']]))?$mappingValue=$entry[$mappingRule['Source column']]:'';
                    if (empty($mappingRule['Sub key']) || ($mappingRule['Target column']!='Content' && $mappingRule['Target column']!='Params')){
                        if (empty($statisticEntries[$applicationKey][$mappingRule['Target column']])){
                            $statisticEntries[$applicationKey][$mappingRule['Target column']]=$mappingValue;
                        } else {
                            $statisticEntries[$applicationKey][$mappingRule['Target column']].='|'.$mappingValue;
                        }
                    } else {
                        if (empty($statisticEntries[$applicationKey][$mappingRule['Target column']][$mappingRule['Sub key']])){
                            $statisticEntries[$applicationKey][$mappingRule['Target column']][$mappingRule['Sub key']]=$mappingValue;
                        } else {
                            $statisticEntries[$applicationKey][$mappingRule['Target column']][$mappingRule['Sub key']].='|'.$mappingValue;
                        }
                    }
                }
            }
            // add validations
            $regionKey=trim(substr($entry[$sourceSettings['Fallnummer column']],10,2));
            $columnsTemplate[$regionKey]=0;
            $columnsTemplate[$countryKey]=0;
            $statisticEntries[$applicationKey]['Content']['All']=1;
            $statisticEntries[$applicationKey]['Content']['Fallnummer']=$entry[$sourceSettings['Fallnummer column']];
            $statisticEntries[$applicationKey]['Content'][$regionKey]=1;
            $statisticEntries[$applicationKey]['Content'][$countryKey]=1;
        }
        $statistictype=current($settings['statistictypeselector']);
        ksort($columnsTemplate);
        foreach($statisticEntries as $applicationKey=>$entry){
            $entry['Name']=(empty($entry['Name']))?date('Y-m-d').' EP statistic':$entry['Name'];
            $entry['Content']=array_merge($columnsTemplate,$entry['Content']);
            if ($statistictype['Content']['Output']=='csv'){
                $entry['EntryId']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($entry['Name']);
                if (!$testRun){
                    $this->oc['SourcePot\Datapool\Tools\CSVtools']->entry2csv($entry);
                    $result['Entry statistic']['CSV rows added']['value']++;
                }
            } else {
                $entry['EntryId']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($entry);
                if (!$testRun){
                    $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
                    $result['Entry statistic']['Entries added']['value']++;
                }
            }
        }
        $this->oc['SourcePot\Datapool\Tools\CSVtools']->entry2csv();
        return $result;
    }

// generic methods
    private function runStatistic($callingElement,$result,$testRun=FALSE)
    {
        $result['Errors']['Statistics type']=array('value'=>'Empty');
        return $result;
    }

    private function runStatisticEntries($callingElement,$testRun=FALSE){
        $props=array('Script start timestamp'=>hrtime(TRUE));
        $result=array();
        //
        $settings=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement);
        if (isset($settings['statistictypeselector'])){
            $statistictypeselector=current($settings['statistictypeselector']);
            $method='runStatistic'.$statistictypeselector['Content']['Type'];
            $result=$this->$method($callingElement,$result,$testRun);
        } else {
            $result['Errors']['Statistics type']=array('value'=>'Setting missing');
        }
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$props['Script start timestamp'])/1000000));
        return $result;
    }

    private function check($valueA,$condition,$valueB)
    {
        switch($condition){
            case 'stripos':
                if (stripos($valueA,$valueB)!==FALSE){return TRUE;}
                break;
            case 'stripos!':
                if (stripos($valueA,$valueB)===FALSE){return TRUE;}
                break;
            case 'strcmp':
                if (strcmp($valueA,$valueB)===0){return TRUE;}
                break;
            case 'eq':
                if ($valueA==$valueB){return TRUE;}
                break;
            case 'le':
                if ($valueA<=$valueB){return TRUE;}
                break;
            case 'lt':
                if ($valueA<$valueB){return TRUE;}
                break;
            case 'ge':
                if ($valueA>=$valueB){return TRUE;}
                break;
            case 'gt':
                if ($valueA>$valueB){return TRUE;}
                break;
            case 'ne':
                if ($valueA!=$valueB){ return TRUE;}
                break;
        }
        return FALSE;
    }

}
?>