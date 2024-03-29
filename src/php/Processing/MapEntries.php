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

class MapEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;

    private $entryTable='';
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );

    private $dataTypes=array('string'=>'String','stringNoWhitespaces'=>'String without whitespaces','splitString'=>'Split string','int'=>'Integer','float'=>'Float','bool'=>'Boolean','money'=>'Money','date'=>'Date','codepfad'=>'Codepfad','unycom'=>'UNYCOM file number');
    private $skipCondition=array('always'=>'always',
                                 'stripos'=>'is substring of target value',
                                 'stripos!'=>'is not substring  of target value',
                                 'strcmp'=>'is equal to target value',
                                 'lt'=>'is < than target value',
                                 'le'=>'is <= than target value',
                                 'eq'=>'is = to target value',
                                 'ne'=>'is <> to target value',
                                 'gt'=>'is > than target value',
                                 'ge'=>'is >= than target value',
                                 );
        
    private $paramsTemplate=array('Mode'=>'entries','Array→string glue'=>'|');
    
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
                return $this->runMapEntries($callingElement,$testRunOnly=FALSE);
                }
                break;
            case 'test':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runMapEntries($callingElement,$testRunOnly=TRUE);
                }
                break;
            case 'widget':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getMapEntriesWidget($callingElement);
                }
                break;
            case 'settings':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getMapEntriesSettings($callingElement);
                }
                break;
            case 'info':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getMapEntriesInfo($callingElement);
                }
                break;
        }
        return FALSE;
    }

    private function getMapEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Mapping','generic',$callingElement,array('method'=>'getMapEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }

    private function getMapEntriesInfo($callingElement){
        $matrix=array();
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        return $html;
    }
       
    public function getMapEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runMapEntries($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runMapEntries($arr['selector'],TRUE);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Mapping widget'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }


    private function getMapEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Mapping entries settings','generic',$callingElement,array('method'=>'getMapEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getMapEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->mappingParams($arr['selector']);
        $arr['html'].=$this->mappingRules($arr['selector']);
        //$selectorMatrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
        //$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for mapping'));
        return $arr;
    }

    private function mappingParams($callingElement){
        $contentStructure=array('Target'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                'Mode'=>array('method'=>'select','value'=>$this->paramsTemplate['Mode'],'excontainer'=>TRUE,'options'=>array('entries'=>'Entries (EntryId will be created from Name)','csv'=>'Create csv','zip'=>'Create zip')),
                                'Array→string glue'=>array('method'=>'select','excontainer'=>TRUE,'value'=>$this->paramsTemplate['Array→string glue'],'options'=>array('|'=>'|',' '=>'Space',''=>'None','_'=>'Underscore')),
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
        $arr['caption']='Mapping control: Select mapping target and type';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }
    
    private function mappingRules($callingElement){
        $contentStructure=array('Target value or...'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                '...value selected by'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>array('Linked file'=>'Linked file')),
                                'Target data type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->dataTypes),
                                'Target column'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
                                'Target key'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                'Use rule if Compare value'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'always','options'=>$this->skipCondition),
                                'Compare value'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                );
        $contentStructure['...value selected by']+=$callingElement['Content']['Selector'];
        $contentStructure['Target column']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Mapping rules: Map selected entry values or constants (Source value) to target entry values';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function runMapEntries($callingElement,$testRun=FALSE){
        $base=array('mappingparams'=>array(),'mappingrules'=>array());
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Mapping statistics'=>array('Entries'=>array('value'=>0),
                                                  'Spreadsheet entries'=>array('value'=>0),
                                                  'Files added to zip'=>array('value'=>0),
                                                  'Skip rows'=>array('value'=>0),
                                                  'Output format'=>array('value'=>'Entries'),
                                                 )
                    );
        // loop through entries
        $params=current($base['mappingparams']);
        $base['Attachment name']=date('Y-m-d His').' '.implode('-',$base['entryTemplates'][$params['Content']['Target']]);
        $base['zipRequested']=(!$testRun && strcmp($params['Content']['Mode'],'zip')===0);
        $base['csvRequested']=(!$testRun && (strcmp($params['Content']['Mode'],'csv')===0 || strcmp($params['Content']['Mode'],'zip')===0));
        if ($base['zipRequested']){
            $zipName=date('Y-m-d His').' '.__FUNCTION__.'.zip';
            $zipFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir().$zipName;
            $zip= new \ZipArchive;
            $zip->open($zipFile,\ZipArchive::CREATE);
        }
        $deleteEntries=array('Source'=>$callingElement['Content']['Selector']['Source'],'EntryIds'=>array());
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            //if (time()-$base['Script start timestamp']>30){break;}
            if ($sourceEntry['isSkipRow']){
                $result['Mapping statistics']['Skip rows']['value']++;
                continue;
            }
            if ($base['zipRequested']){
                // open temporary zip-archive        
                $attachment=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
                if (is_file($attachment)){
                    $result['Mapping statistics']['Files added to zip']['value']++;
                    $sourceEntry['Linked file']=preg_replace('/[^0-9a-zA-ZöüäÖÜÄß\-]/','_',$sourceEntry['Name']).'.'.$sourceEntry['Params']['File']['Extension'];
                    $zip->addFile($attachment,$sourceEntry['Linked file']);
                } else {
                    $sourceEntry['Linked file']='';
                }
                $deleteEntries['EntryIds'][]="'".$sourceEntry['EntryId']."'";
            }
            // map entry
            if (!empty($sourceEntry['Params']['File']['SpreadsheetIteratorClass'])){
                $iteratorClass=$sourceEntry['Params']['File']['SpreadsheetIteratorClass'];
                $iteratorMethod=$sourceEntry['Params']['File']['SpreadsheetIteratorMethod'];
                foreach($this->oc[$iteratorClass]->$iteratorMethod($sourceEntry,$sourceEntry['Params']['File']['Extension']) as $rowIndex=>$rowArr){
                    $result['Mapping statistics']['Spreadsheet entries']['value']++;
                    $sourceEntry['Params']['File']['Spreadsheet']=$rowArr;
                    $result=$this->mapEntry($base,$sourceEntry,$result,$testRun);
                }
            } else {
                $result=$this->mapEntry($base,$sourceEntry,$result,$testRun);
            }
        }
        if ($base['csvRequested'] || $base['zipRequested']){
            $result['Mapping statistics']['Output format']['value']='CSV';
            $this->oc['SourcePot\Datapool\Tools\CSVtools']->entry2csv();
        }            
        if ($base['zipRequested']){
            if (isset($result['Target']['Source'])){
                // add csv file to zip archive
                $csvFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file(array('Source'=>$result['Target']['Source']['value'],'EntryId'=>$result['Target']['EntryId']['value']));
                $zipFileName=preg_replace('/[^a-zA-Z0-9\-]/','_',$result['Target']['Name']['value']);
                $zip->addFile($csvFile,$zipFileName.'.csv');
            }
            $zip->close();    
            if (isset($result['Target']['Source'])){
                // create zip entry
                $zipEntry=array('Source'=>$result['Target']['Source']['value'],'Name'=>$result['Target']['Name']['value'].' (complete)');
                $zipEntry=array_replace_recursive($sourceEntry,$base['entryTemplates'][$params['Content']['Target']],$zipEntry);
                $zipEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($zipEntry,array('Name'),'0','',FALSE);
                // add file info
                $pathArr=pathinfo($zipFile);
                $zipEntry['Params']['File']['Source']=$zipFile;
                $zipEntry['Params']['File']['Size']=filesize($zipEntry['Params']['File']['Source']);
                $zipEntry['Params']['File']['Name']=$pathArr['basename'];
                $zipEntry['Params']['File']['Extension']=$pathArr['extension'];
                $zipEntry['Params']['File']['MIME-Type']=mime_content_type($zipFile);
                $zipEntry['Params']['File']['Date (created)']=filectime($zipEntry['Params']['File']['Source']);
                $zipEntry['Type']=$zipEntry['Source'].' '.str_replace('/',' ',$zipEntry['Params']['File']['MIME-Type']);
                // save entry and file
                $zipEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($zipEntry,TRUE,FALSE,TRUE,$zipEntry['Params']['File']['Source']);
                $entryFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($zipEntry);
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($zipFile,$entryFile);
                $result['Mapping statistics']['Output format']['value']='Zip + csv';
            }
            $this->deleteEntriesById($deleteEntries['Source'],$deleteEntries['EntryIds']);
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function mapEntry($base,$sourceEntry,$result,$testRun){
        $params=current($base['mappingparams']);
        $targetEntry=array();
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        foreach($base['mappingrules'] as $ruleIndex=>$rule){
            if (strcmp($rule['Content']['...value selected by'],'useValue')===0){
                $targetValue=$rule['Content']['Target value or...'];
            } else {
                if (isset($flatSourceEntry[$rule['Content']['...value selected by']])){
                    $targetValue=$flatSourceEntry[$rule['Content']['...value selected by']];
                } else {
                    $targetValue='{{missing}}';
                }
            }
            $targetEntry=$this->addValue2flatEntry($targetEntry,$rule['Content']['Target column'],$rule['Content']['Target key'],$targetValue,$rule['Content']['Target data type'],$rule['Content']);
        }
        // wrapping up
        foreach($targetEntry as $key=>$value){
            if (strpos($key,'Content')===0 || strpos($key,'Params')===0){continue;}
            if (!is_array($value)){continue;}
            foreach($value as $subKey=>$subValue){
                $value[$subKey]=$this->getStdValueFromValueArr($subValue);
            }
            // set order of array values
            ksort($value);
            $targetEntry[$key]=implode($params['Content']['Array→string glue'],$value);
        }
        $result['Mapping statistics']['Entries']['value']++;
        $sourceEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($sourceEntry,'Processing log',array('mode'=>$params['Content']['Mode']),FALSE);
        if ($base['csvRequested'] || $base['zipRequested']){
            unset($sourceEntry['Content']);
            unset($sourceEntry['Params']);
            $targetEntry=array_replace_recursive($sourceEntry,$targetEntry,$base['entryTemplates'][$params['Content']['Target']]);
            $targetEntry['Name']=$base['Attachment name'];
            $targetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($targetEntry,array('Name'),'0','',FALSE);
            if (!$testRun){
                $this->oc['SourcePot\Datapool\Tools\CSVtools']->entry2csv($targetEntry);
            }
        } else {
            $sourceEntry=array_replace_recursive($sourceEntry,$targetEntry);
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Content']['Target']],TRUE,$testRun);
        }
        $result['Target']['Source']['value']=$targetEntry['Source'];
        $result['Target']['EntryId']['value']=$targetEntry['EntryId'];
        $result['Target']['Name']['value']=$targetEntry['Name'];
        if (!isset($result['Sample result']) || mt_rand(0,100)>90){
            $result['Sample result']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
        }
        return $result;
    }
    
    private function deleteEntriesById($Source,$EntryIds){
        if (empty($EntryIds)){
            return $this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
        }
        foreach($EntryIds as $EntryId){
            $entrySelector=array('Source'=>$Source,'EntryId'=>trim($EntryId,"'"));
            $fileToDelete=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entrySelector);
            if (is_file($fileToDelete)){
                $this->oc['SourcePot\Datapool\Foundation\Database']->addStatistic('removed',1);
                unlink($fileToDelete);
            }
        }
        $sql="DELETE FROM ".$Source." WHERE `EntryId` IN(".implode(',',$EntryIds).");";
        $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql);
        $this->oc['SourcePot\Datapool\Foundation\Database']->addStatistic('deleted',$stmt->rowCount());
        return $this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
    }
    
    private function getStdValueFromValueArr($value,$useKeyIfPresent='NOBODY-SHOULD-USE-THIS-KEY-IN-THE-VALUEARR'){
        if (isset($value[$useKeyIfPresent])){
            $value=$value[$useKeyIfPresent];
        } else if (is_array($value)){
            reset($value);
            $value=current($value);
        }
        return $value;
    }

    private function addValue2flatEntry($entry,$baseKey,$key,$value,$dataType,$rule){
        if (!isset($entry[$baseKey])){$entry[$baseKey]=array();}
        if (!is_array($entry[$baseKey]) && empty($key)){$entry[$baseKey]=array();}
        $newValue=array($key=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($value,$dataType));
        if ($this->useRule($rule,$dataType,$newValue[$key])){
            if (is_array($entry[$baseKey])){
                $entry[$baseKey]=array_replace_recursive($entry[$baseKey],$newValue);
            } else {
                $entry[$baseKey]=$newValue;
            }
        }
        return $entry;
    }

    private function useRule($rule,$dataType,$targetValue){
        // unify datatype
        if (strcmp(($rule['Use rule if Compare value']),'always')===0){return TRUE;}
        if (strlen($rule['Use rule if Compare value'])>2){
            // compare strings
            $compareValue=$rule['Compare value'];
            $targetValue=$this->getStdValueFromValueArr($targetValue);
            $targetValue=strval($targetValue);
        } else {
            // compare numbers
            $compareValue=floatval($rule['Compare value']);
            if (strcmp($dataType,'date')===0){
                $targetValue=$this->getStdValueFromValueArr($targetValue,'System');
                $targetValue=strtotime($targetValue);
                $compareValue=strtotime($rule['Compare value']);
            } else if (strcmp($dataType,'int')===0){
                $targetValue=$this->getStdValueFromValueArr($targetValue);
                $compareValue=round($compareValue);
            } else if (strcmp($dataType,'unycom')===0){
                $targetValue=$this->getStdValueFromValueArr($targetValue,'Number');
                $compareValue=round($compareValue);
            } else {
                $targetValue=$this->getStdValueFromValueArr($targetValue);
            }
        }
        $return=FALSE;
        switch($rule['Use rule if Compare value']){
            case 'stripos':
                if (stripos($targetValue,$compareValue)!==FALSE){$return=TRUE;}
                break;
            case 'stripos!':
                if (stripos($targetValue,$compareValue)===FALSE){$return=TRUE;}
                break;
            case 'strcmp':
                if (strcmp($targetValue,$compareValue)===0){$return=TRUE;}
                break;
            case 'eq':
                if ($targetValue==$compareValue){$return=TRUE;}
                break;
            case 'le':
                if ($targetValue>=$compareValue){$return=TRUE;}
                break;
            case 'lt':
                if ($targetValue>$compareValue){$return=TRUE;}
                break;
            case 'ge':
                if ($targetValue<=$compareValue){$return=TRUE;}
                break;
            case 'gt':
                if ($targetValue<$compareValue){$return=TRUE;}
                break;
            case 'ne':
                if ($targetValue!=$compareValue){$return=TRUE;}
                break;
        }
        return $return;
    }

}
?>