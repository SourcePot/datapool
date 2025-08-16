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
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        ];
        
    private $paramsTemplate=['Mode'=>'entries'];

    private const MAX_TEST_TIME=5000000000;   // in nanoseconds
    private const MAX_PROC_TIME=100000000000;   // in nanoseconds
    
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
                'run'=>$this->runMapEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runMapEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getMapEntriesWidget($callingElement),
                'settings'=>$this->getMapEntriesSettings($callingElement),
                'info'=>$this->getMapEntriesInfo($callingElement),
            };
        }
    }

    private function getMapEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Mapping','generic',$callingElement,['method'=>'getMapEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }

    private function getMapEntriesInfo($callingElement){
        $matrix=[];
        $matrix['']['value']='If you select "Create csv" or "Create zip", remeber to set the target entry Name-column.<br/>The file name will be the target entry Name. Mapping will result in as many files as there are different Names.';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?']);
        return $html;
    }
       
    public function getMapEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runMapEntries($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runMapEntries($arr['selector'],TRUE);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Mapping']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            if ($caption==='Mapping statistics'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getMapEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Mapping entries settings','generic',$callingElement,['method'=>'getMapEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
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
        $contentStructure=[
            'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>[0=>'No, move entries',1=>'Yes, copy entries']],
            'Target'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
            'Mode'=>['method'=>'select','value'=>$this->paramsTemplate['Mode'],'excontainer'=>TRUE,'options'=>['entries'=>'Entries','csv'=>'Create csv','zip'=>'Create zip']],
            'Attached file'=>['method'=>'select','value'=>0,'excontainer'=>TRUE,'options'=>['Keep','Remove from target']],
            'Order by'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Date','standardColumsOnly'=>TRUE],
            'Order'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>[0=>'Descending',1=>'Ascending']],
            'No match placeholder'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'','placeholder'=>'e.g. {missing}','excontainer'=>TRUE),
            ];
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
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        if (empty($arr['selector']['Content'])){$row['trStyle']=['background-color'=>'#a00'];}
        $matrix=['Parameter'=>$row];
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }
    
    private function mappingRules($callingElement){
        $contentStructure=[
            'Target value or...'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            '...value selected by'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>['Linked file'=>'Linked file']],
            'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
            'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE],
            'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            'Combine'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS,'title'=>"Controls the resulting value, fIf the target already exsists."],
            ];
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
        $base=['mappingparams'=>[],'mappingrules'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=['Mapping statistics'=>['Entries'=>['value'=>0],
                                        'Spreadsheet entries'=>['value'=>0],
                                        'Files added to zip'=>['value'=>0],
                                        'Skip rows'=>['value'=>0],
                                        'Output format'=>['value'=>'Entries'],
                                        'Comment'=>['value'=>''],
                                        ]
                    ];
        // loop through entries
        $params=current($base['mappingparams']);
        $base['Attachment name']=date('Y-m-d His').' '.implode('-',$base['entryTemplates'][$params['Content']['Target']]);
        $base['zipRequested']=strcmp($params['Content']['Mode'],'zip')===0;
        $base['csvRequested']=strcmp($params['Content']['Mode'],'csv')===0 || strcmp($params['Content']['Mode'],'zip')===0;
        if ($base['zipRequested']){
            $zipName=date('Y-m-d His').' '.__FUNCTION__.'.zip';
            $zipFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir().$zipName;
            $zip= new \ZipArchive;
            $zip->open($zipFile,\ZipArchive::CREATE);
        }
        $timeLimit=$testRun?self::MAX_TEST_TIME:(($base['csvRequested'] || $params['Content']['Keep source entries']>0)?0:self::MAX_PROC_TIME);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE,'Read',$params['Content']['Order by'],boolval($params['Content']['Order'])) as $sourceEntry){
            $expiredTime=hrtime(TRUE)-$base['Script start timestamp'];
            if ($expiredTime>$timeLimit && $timeLimit>0){
                $result['Mapping statistics']['Comment']['value']='Incomplete run due to reaching the maximum processing time';
                break;
            }
            if ($sourceEntry['isSkipRow']){
                $result['Mapping statistics']['Skip rows']['value']++;
                continue;
            }
            if ($base['zipRequested']){
                // add attached file to temporary zip-archive        
                $attachment=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
                if (is_file($attachment)){
                    $sourceEntry['Linked file']=preg_replace('/[^0-9a-zA-ZöüäÖÜÄß\-]/','_',$sourceEntry['Name']).'.'.$sourceEntry['Params']['File']['Extension'];
                    $result['Mapping statistics']['Files added to zip']['value']+=intval($zip->addFile($attachment,$sourceEntry['Linked file']));
                } else {
                    $sourceEntry['Linked file']='';
                }
            }
            // map entry
            if (empty($sourceEntry['Params']['File']['SpreadsheetIteratorClass'])){
                $result=$this->mapEntry($base,$sourceEntry,$result,$testRun);
            } else {
                $iteratorClass=$sourceEntry['Params']['File']['SpreadsheetIteratorClass'];
                $iteratorMethod=$sourceEntry['Params']['File']['SpreadsheetIteratorMethod'];
                foreach($this->oc[$iteratorClass]->$iteratorMethod($sourceEntry,$sourceEntry['Params']['File']['Extension']) as $rowIndex=>$rowArr){
                    $result['Mapping statistics']['Spreadsheet entries']['value']++;
                    $sourceEntry['Params']['File']['Spreadsheet']=$rowArr;
                    $result=$this->mapEntry($base,$sourceEntry,$result,$testRun);
                }
            }

        } // end of entry loop
        if ($base['csvRequested'] || $base['zipRequested']){
            // write csv file
            $result['Mapping statistics']['Output format']['value']='CSV';
            $csvEntry=$this->oc['SourcePot\Datapool\Tools\CSVtools']->entry2csv();
        }            
        if ($base['zipRequested'] && isset($csvEntry['EntryId'])){
            // add csv file to zip archive
            if (isset($result['targetEntry']['Source'])){
                $csvFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file(['Source'=>$csvEntry['Source'],'EntryId'=>$csvEntry['EntryId']]);
                $result['Mapping statistics']['Files added to zip']['value']+=intval($zip->addFile($csvFile,$csvEntry['Params']['File']['Name']));
            }
            // create zip entry
            $archiveFileCount=$zip->count();
            $zip->close();    
            if (isset($result['targetEntry']['Source'])){
                $zipEntry=['Source'=>$result['targetEntry']['Source'],'Name'=>$result['targetEntry']['Name'].' (complete)'];
                $zipEntry=array_replace_recursive($result['targetEntry'],$zipEntry);
                $zipEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($zipEntry,['Name'],'0','',FALSE);
                $zipEntry['File upload extract archive']=FALSE;
                $zipEntry['Content']=['Zip archive file count'=>$archiveFileCount];
                $zipEntry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entry($zipFile,$zipEntry,FALSE,TRUE);
                $result['Mapping statistics']['Output format']['value']='Zip + csv';
            }
        }
        //delete source entries
        $params=current($base['mappingparams']);
        if (($base['csvRequested'] || $base['zipRequested']) && empty($params['Content']['Keep source entries'])){
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($callingElement['Content']['Selector']);
        }
        // multiple hits statistics
        foreach($this->oc['SourcePot\Datapool\Tools\MiscTools']->getMultipleHitsStatistic() as $hitsArr){
            if ($hitsArr['Hits']<2){continue;}
            $result['Hits >1 with same EntryId'][$hitsArr['Name']]=['Hits'=>$hitsArr['Hits'],'Comment'=>$hitsArr['Comment']];    
        }
        // remove targetEntry from results
        if (isset($result['targetEntry'])){
            unset($result['targetEntry']);
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }
    
    private function mapEntry($base,$sourceEntry,$result,$testRun){
        $params=current($base['mappingparams']);
        $targetEntry=[];
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        foreach($base['mappingrules'] as $ruleIndex=>$rule){
            if (strcmp($rule['Content']['...value selected by'],'useValue')===0){
                $targetValue=$rule['Content']['Target value or...'];
            } else {
                if (isset($flatSourceEntry[$rule['Content']['...value selected by']])){
                    $targetValue=$flatSourceEntry[$rule['Content']['...value selected by']];
                } else {
                    $targetValue=$params['Content']['No match placeholder']??'';
                }
            }
            $targetValue=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($targetValue,$rule['Content']['Target data type']);
            $this->oc['SourcePot\Datapool\Foundation\Computations']->add2combineCache($rule['Content']['Combine'],$rule['Content']['Target column'],$rule['Content']['Target key'],$targetValue);
        }
        $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Computations']->combineAll($targetEntry);
        // wrapping up
        foreach($targetEntry as $key=>$value){
            $targetEntry=$this->valueSizeLimitCompliance($key,$targetEntry);
        }
        $targetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($targetEntry);
        $result['Mapping statistics']['Entries']['value']++;
        $sourceEntry['Content']=NULL;
        if ($base['csvRequested'] || $base['zipRequested']){
            // add entry to csv
            $sourceEntry['Params']=NULL;
            $targetEntry=array_replace_recursive($sourceEntry,$targetEntry,$base['entryTemplates'][$params['Content']['Target']]);
            $targetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($targetEntry,['Name'],'0','',FALSE);
            if (!$testRun){
                $this->oc['SourcePot\Datapool\Tools\CSVtools']->entry2csv($targetEntry);
            }
        } else {
            // update and move entry to target
            $sourceEntry=array_replace_recursive($sourceEntry,$targetEntry);
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Content']['Target']],TRUE,$testRun,$params['Content']['Keep source entries']);
            if (!empty($params['Content']['Attached file']) && empty($testRun)){
                $this->oc['SourcePot\Datapool\Foundation\Database']->removeFileFromEntry($targetEntry);
            }
        }
        $this->oc['SourcePot\Datapool\Tools\MiscTools']->add2hitStatistics($targetEntry,'');
        if (!isset($result['Sample result']) || mt_rand(0,100)>90){
            $result['Sample result']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
        }
        $result['targetEntry']=$targetEntry;
        return $result;
    }
    
    private function valueSizeLimitCompliance($key,array $entry):array
    {
        if (isset($GLOBALS['dbInfo']['user'][$key]['type'])){
            $sizeLimitBytes=intval(trim($GLOBALS['dbInfo']['user'][$key]['type'],'varcharVARCHAR()'));
            if ($sizeLimitBytes>0){
                $currentSize=mb_strlen(strval($entry[$key]),'8bit');
                if ($currentSize>$sizeLimitBytes){
                    $entry[$key]=mb_strcut(strval($entry[$key]),0,$sizeLimitBytes);
                    $this->oc['logger']->log('notice','Mapping to "entry[{key}]" value size of "{currentSize} Bytes" exeeded the limit of "{sizeLimitBytes}". The value was truncated to the size limit.',['key'=>$key,'sizeLimitBytes'=>$sizeLimitBytes,'currentSize'=>$currentSize]);
                }
            }
        }
        return $entry;
    }

}
?>