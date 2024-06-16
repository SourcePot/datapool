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
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
        
    private $paramsTemplate=array('Mode'=>'entries','Array→string glue'=>'|');
    
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
                'run'=>$this->runMapEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runMapEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getMapEntriesWidget($callingElement),
                'settings'=>$this->getMapEntriesSettings($callingElement),
                'info'=>$this->getMapEntriesInfo($callingElement),
            };
        }
    }

    private function getMapEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Mapping','generic',$callingElement,array('method'=>'getMapEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }

    private function getMapEntriesInfo($callingElement){
        $matrix=array();
        $matrix['']['value']='If you select "Create csv" or "Create zip", remeber to set the target entry Name-column.<br/>The file name will be the target entry Name. Mapping will result in as many files as there are different Names.';
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Mapping'));
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
        return $arr;
    }

    private function mappingParams($callingElement){
        $contentStructure=array('Keep source entries'=>array('method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>array(0=>'No, move entries',1=>'Yes, copy entries')),
                                'Target'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                'Mode'=>array('method'=>'select','value'=>$this->paramsTemplate['Mode'],'excontainer'=>TRUE,'options'=>array('entries'=>'Entries','csv'=>'Create csv','zip'=>'Create zip')),
                                'Array→string glue'=>array('method'=>'select','excontainer'=>TRUE,'value'=>$this->paramsTemplate['Array→string glue'],'options'=>array('|'=>'|',' '=>'Space',''=>'None','_'=>'Underscore')),
                                'Order by'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Date','standardColumsOnly'=>TRUE),
                                'Order'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array(0=>'Descending',1=>'Ascending')),
                                'Save'=>array('method'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
                                );
        $contentStructure['Order by']+=$callingElement['Content']['Selector'];
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
        if (empty($arr['selector']['Content'])){$row['trStyle']=array('background-color'=>'#a00');}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }
    
    private function mappingRules($callingElement){
        $contentStructure=array('Target value or...'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                '...value selected by'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>array('Linked file'=>'Linked file')),
                                'Target data type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE),
                                'Target column'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
                                'Target key'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
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
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE,'Read',$params['Content']['Order by'],boolval($params['Content']['Order'])) as $sourceEntry){
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
                $zipEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->addType2entry($zipEntry);
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
            if (mb_strpos($key,'Content')===0 || mb_strpos($key,'Params')===0){continue;}
            if (!is_array($value)){continue;}
            foreach($value as $subKey=>$subValue){
                $value[$subKey]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->valueArr2value($subValue);
            }
            // set order of array values
            ksort($value);
            $targetEntry[$key]=implode($params['Content']['Array→string glue'],$value);
        }
        $result['Mapping statistics']['Entries']['value']++;
        //
        $sourceEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($sourceEntry,'Processing log',array('mode'=>$params['Content']['Mode']),FALSE);
        $sourceEntry['Content']=array();
        if ($base['csvRequested'] || $base['zipRequested']){
            unset($sourceEntry['Params']);
            $targetEntry=array_replace_recursive($sourceEntry,$targetEntry,$base['entryTemplates'][$params['Content']['Target']]);
            $targetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($targetEntry,array('Name'),'0','',FALSE);
            if (!$testRun){
                $this->oc['SourcePot\Datapool\Tools\CSVtools']->entry2csv($targetEntry);
            }
        } else {
            if (isset($sourceEntry['Params']['File'])){unset($sourceEntry['Params']['File']);}
            $sourceEntry=array_replace_recursive($sourceEntry,$targetEntry);
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Content']['Target']],TRUE,$testRun,$params['Content']['Keep source entries']);
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
    
    private function addValue2flatEntry($entry,$baseKey,$key,$value,$dataType,$rule)
    {
        if (!isset($entry[$baseKey])){$entry[$baseKey]=array();}
        if (!is_array($entry[$baseKey]) && empty($key)){$entry[$baseKey]=array();}
        $newValue=array($key=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($value,$dataType));
        if (is_array($entry[$baseKey])){
            $entry[$baseKey]=array_replace_recursive($entry[$baseKey],$newValue);
        } else {
            $entry[$baseKey]=$newValue;
        }
        return $entry;
    }

}
?>