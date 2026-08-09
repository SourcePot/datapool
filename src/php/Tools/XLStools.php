<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Tools;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class XLStools{

    private const SPREADSHEET_FORMAT_ERROR_THRESHOLD=20; // spreadsheet totalColumns/totalRows

    private $oc=[];
    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];
    
    private $spreadsheetSetting=[];
    private $spreadsheetTimestamp=FALSE;
    private $mapIndex2letter=[];

    private const SPREADSHEET_SETTINGS=[
        'import formats'=>[''=>'Xlsx, Xls, Xml, Ods, Slk, Gnumeric, Html, Csv','xml'=>'Ignore Xml','html'=>'Ignore Html','xml,html'=>'Ignore Xml & Html'],
        'output format'=>['Csv'=>'Csv','Xls'=>'Xls',"Xlsx"=>'Xlsx','Ods'=>'Ods','Pdf'=>'PDF'],
        'delimiter'=>[';'=>'Semicolon',','=>'Comma','TAB'=>'Tabulator','|'=>'Pipe'],
        'enclosure'=>['"'=>'"',"'"=>"'",''=>'None'],
        'escape'=>[''=>'None','"'=>'"','\\'=>'\\',],
        'lineSeparator'=>['CRLF'=>'Carriage return & line feed','LF'=>'Line feed','CR'=>'Carriage return','PHP_EOL'=>'PHP_EOL'],
    ];

    private const KEY_MAP=[
        'TAB'=>"\t",
        'LFCR'=>"\n\r",
        'CRLF'=>"\r\n",
        'LF'=>"\n",
        'CR'=>"\r",
        'PHP_EOL'=>PHP_EOL,
    ];

    private const MIME_MAP=[
        'csv'=>'text/csv',
        'xls'=>'application/vnd.ms-excel',
        'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ods'=>'application/vnd.oasis.opendocument.spreadsheet',
    ];
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $this->spreadsheetTimestamp=time();
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    public function init()
    {
        $this->loadSettings();
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
        for($indexA=65;$indexA<=90;$indexA++){
            $this->mapIndex2letter[]=chr($indexA);
        }
        for($indexA=65;$indexA<=90;$indexA++){
            for($indexB=65;$indexB<=90;$indexB++){
                $this->mapIndex2letter[]=chr($indexA).chr($indexB);
            }
        }
        $this->entry2spreadsheet();
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    private function loadSettings():void
    {
        $category=filter_input(INPUT_GET,'category',FILTER_SANITIZE_ENCODED);
        $app=(\SourcePot\Datapool\Foundation\Menu::CATEGORIES[$category?:'Data']??['Category'=>'Data'])['Category'];
        $callingClass=$_SESSION['page state']['selectedApp'][$app]['Class']??'GENERIC';
        $this->spreadsheetSetting=$this->getSpreadsheetSettings($callingClass);
    }

    public function spreadsheetSettingsSelector($callingClass=''):array
    {
        $elector=['Source'=>$this->getEntryTable(),'Group'=>'Settings','Folder'=>'Spreadsheet','Name'=>$callingClass?:'GENERIC'];
        $elector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($elector,['Group','Folder','Name'],0);
        return $elector;
    }

    public function getSpreadsheetSettingsEntry($callingClass=''):array
    {
        $selector=$this->spreadsheetSettingsSelector($callingClass);
        $settingsEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($selector,TRUE);    
        return $settingsEntry?:$selector;
    }

    public function getSpreadsheetSettings($callingClass='',bool $rawSetting=FALSE):array
    {
        $settingsEntry=$this->getSpreadsheetSettingsEntry($callingClass);    
        foreach(self::SPREADSHEET_SETTINGS as $key=>$options){
            reset($options);
            $setting[$key]=$settingsEntry['Content'][$key]??key($options);
            if (!$rawSetting){
                $setting[$key]=strtr($setting[$key],self::KEY_MAP);
            }
        }
        return $setting??[];
    }
    
    public function settingsWidget(array $arr):array
    {
        $entryName=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($arr['selector'])['Name']??'GENERIC';
        $callingClass=$arr['selector']['callingClass']?:$arr['selector']['Name']?:$entryName;
        $settingsEntry=$this->getSpreadsheetSettingsEntry($callingClass);
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (!empty($formData['val'][$callingClass])){
            $settingsEntry['Content']=$formData['val'][$callingClass];
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($settingsEntry);
        }
        $matrix=[];
        $setting=$this->getSpreadsheetSettings($callingClass,TRUE);
        foreach(self::SPREADSHEET_SETTINGS as $key=>$options){
            $selectArr=['key'=>[$callingClass,$key],'options'=>$options,'value'=>$setting[$key],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
            $matrix[ucfirst($key)]=['value'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr)];
        }
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Settings '.$callingClass]);    
        return $arr;
    }

    public function isSpreadsheet(array|string $selector):string|bool
    {
        $spreadsheetFile=(is_array($selector))?$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector):$selector;
        if (!is_file($spreadsheetFile)){
            return FALSE;
        }
        try {
            $fileType=IOFactory::identify($spreadsheetFile,NULL,TRUE);
            $fileTypeComps=explode('\\',$fileType);
            $fileTypeStr=array_pop($fileTypeComps);
            if (stripos($this->spreadsheetSetting['import formats'],$fileTypeStr)!==FALSE){
                return FALSE;
            } else {
                return $fileType;   // e.g. "PhpOffice\\PhpSpreadsheet\\Reader\\Csv"
            }
        } catch (\Exception $e) {
            return FALSE;        
        }
    }

    private function getSpreadsheetReaderWorksheets(array|string $selector,string|int $loadSelectedWorksheet=0):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        $arr=['Worksheets'=>[]];
        $spreadsheetFile=(is_array($selector))?$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector):$selector;
        $context+=pathinfo($spreadsheetFile);
        if (!is_file($spreadsheetFile)){
            $this->oc['logger']->log('notice','"{class}&rarr;{function}()" failed to load open "{filename}"',$arr);         
            return $arr;
        }
        try {
            $arr['readerClass']=IOFactory::identify($spreadsheetFile,NULL,TRUE);
        } catch (\Exception $e) {
            $context['msg']=$e->getMessage();
            $this->oc['logger']->log('notice','"{class}&rarr;{function}()" failed to detect spreadsheet file type of "{basename}": "{msg}"',$context);
            return $arr;        
        }
        try{
            $reader=IOFactory::createReader($arr['readerClass']);
            if (stripos($arr['readerClass'],'csv')!==FALSE){
                $reader->setTestAutoDetect(FALSE);
                $reader->setInputEncoding(Csv::GUESS_ENCODING);
                $reader->setFallbackEncoding('UTF-8');
                $reader->setDelimiter($this->spreadsheetSetting['delimiter']);
                $reader->setEnclosure($this->spreadsheetSetting['enclosure']);
                $reader->setEscapeCharacter($this->spreadsheetSetting['escape']);
            }
            $arr['Worksheets']=$reader->listWorksheetInfo($spreadsheetFile);
        } catch(\Exception $e){
            $context['msg']=$e->getMessage();
            $this->oc['logger']->log('notice','"{class}&rarr;{function}()" failed to aquire spreadsheet information from "{basename}": "{msg}"',$context);     
            return $arr;    
        }
        if (empty($loadSelectedWorksheet)){
            return $arr;
        } else {
            $arr['selectedWorksheet']=$context['selectedWorksheet']=$loadSelectedWorksheet;
            $reader->setLoadSheetsOnly($loadSelectedWorksheet);
        }
        try{
            $reader->setReadEmptyCells(FALSE);
            $reader->setReadDataOnly(TRUE);
            $arr['spreadsheet']=$reader->load($spreadsheetFile);
            $arr['worksheet']=$arr['spreadsheet']->getActiveSheet();
            $this->oc['logger']->log('info','"{class}&rarr;{function}()" got worksheet "{selectedWorksheet}" from "{basename}"',$context);         
        } catch(\Exception $e){
            $context['msg']=$e->getMessage();
            $this->oc['logger']->log('notice','"{class}&rarr;{function}()" failed to load worksheet "{selectedWorksheet}" from "{basename}": "{msg}"',$context);         
        }
        return $arr;
    }

    public function addSpreadsheetInfo(array|string $selector,array $entry=[],):array
    {
        $info=$this->getSpreadsheetReaderWorksheets($selector,0);
        if (empty($info['Worksheets'])){
            return $entry;
        }
        $entry['Params']['File']['SpreadsheetIteratorClass']=__CLASS__;
        $entry['Params']['File']['SpreadsheetIteratorMethod']='iterator';
        foreach($info['Worksheets'] as $worksheetInfo){
            $sample=[];
            $entry['Params']['File']['SpreadsheetWorksheets'][$worksheetInfo['worksheetName']]=$worksheetInfo;
            $entry['Params']['File']['SpreadsheetWorksheets'][$worksheetInfo['worksheetName']]['formatError']=($worksheetInfo['totalColumns']/($worksheetInfo['totalRows']?:0.000000001)>self::SPREADSHEET_FORMAT_ERROR_THRESHOLD);
            foreach($this->iterator($selector,$worksheetInfo['worksheetName'],10) as $cells){
                $sample=array_merge($sample,$cells);
            }
            if (empty($entry['Params']['File']['Spreadsheet'])){
                $entry['Params']['File']['Spreadsheet']=$sample;    
            }
            $entry['Params']['File']['SpreadsheetByWorksheet'][$worksheetInfo['worksheetName']]=$sample;
        }
        return $entry;
    }
    
    public function iterator(array|string $selector,string|int $worksheetName=0,int $rows2load=-1):\Generator
    {
        $spreadsheetArr=$this->getSpreadsheetReaderWorksheets($selector,$worksheetName);
        if (empty($worksheetName)){
            $worksheetName=$spreadsheetArr['Worksheets'][0]['worksheetName'];
            $spreadsheetArr=$this->getSpreadsheetReaderWorksheets($selector,$worksheetName);
        }
        if (empty($spreadsheetArr['worksheet'])){
            return FALSE;
        }
        $row=$spreadsheetArr['worksheet']->getRowIterator();
        $maxColumnIndex=$lastMaxColumnIndex=0;
        $keys=[];
        while($row->valid() && $rows2load!==0){
            $cellIterator=$row->current()->getCellIterator();
            $cells=$styles=[];
            $lastMaxColumnIndex=$maxColumnIndex;
            foreach($cellIterator as $columnIndex=>$cell){
                $cells[$columnIndex]=$cell->getValue();
                $styles[$columnIndex]=$cell->getStyle();
                if ($cell->getValue()!==NULL && $columnIndex>$maxColumnIndex){
                    $maxColumnIndex=$columnIndex;
                }
            }
            $row->next();
            if ($rows2load>0){$rows2load--;}
            // process row
            if ($maxColumnIndex>$lastMaxColumnIndex){
                // is new header
                $keys=$cells;
            } else {
                // is data row
                $returnRow=[];
                foreach($keys as $columnIndex=>$key){
                    $key=$key??'Column_'.$columnIndex;
                    $returnRow[$key]=$cells[$columnIndex]??NULL;
                }
                yield $returnRow;
            }
        }
        return TRUE;
    }

    private function matrix2spreadsheetTmpFile(array $matrix,array $entry,string $fileType):string
    {
        $fileType=strtolower($fileType);
        $currentUser=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $author=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($currentUser,4);
        $pageTitle=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator($author)
            ->setLastModifiedBy($author)
            ->setTitle($entry['Name']??'')
            ->setSubject('Spreadsheet generated by '.$pageTitle)
            ->setDescription($entry['Message']??'')
            ->setKeywords('Datapool')
            ->setCategory('Data export');
        $dataWorkSheet=$spreadsheet->setActiveSheetIndex(0);
        $rowIndex=0;
        foreach($matrix as $row){
            $rowIndex++;
            $columnIndex=0;
            foreach($row as $header=>$value){
                if ($rowIndex===1){
                    $dataWorkSheet->setCellValue($this->mapIndex2letter[$columnIndex].$rowIndex,$header);
                }
                $columnIndex++;
            }
            if ($rowIndex===1){$rowIndex++;}
            $columnIndex=0;
            foreach($row as $value){
                $dataWorkSheet->setCellValue($this->mapIndex2letter[$columnIndex].$rowIndex,$value);
                $columnIndex++;
            }
        }
        // save to file
        $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getPrivatTmpDir(__FUNCTION__).md5($entry['Name']).'.'.$fileType;
        $writer = IOFactory::createWriter($spreadsheet,ucfirst($fileType));
        if ($fileType==='csv'){
            $writer->setDelimiter($this->spreadsheetSetting['delimiter']);
            $writer->setEnclosure($this->spreadsheetSetting['enclosure']);
            $writer->setLineEnding($this->spreadsheetSetting['lineSeparator']);
        }
        $writer->save($file);
        return $file;
    }

    public function entry2spreadsheet(array $entry=[]):array|bool
    {
        if (empty($entry) && isset($_SESSION['spreadSheetVarSpace'])){
            // write csvVarSpace -> spreadsheet
            $statistics=['Spreadsheet entries generated'=>count($_SESSION['spreadSheetVarSpace']),'Spreadsheet rows'=>0];
            foreach($_SESSION['spreadSheetVarSpace'] as $EntryId=>$csvDefArr){
                unset($_SESSION['spreadSheetVarSpace'][$EntryId]);
                $entry=$csvDefArr['entry'];
                $statistics['Spreadsheet rows']=count($csvDefArr['rows']);
                $fileType=strtolower($this->spreadsheetSetting['output format']);
                $file=$this->matrix2spreadsheetTmpFile($csvDefArr['rows'],$entry,$fileType);
                // add entry
                $entry['fileContent']=file_get_contents($file);
                if (empty($entry['Params']['File']['Name'])){
                    $entry['fileName']=$entry['Name'].'.'.$fileType;
                } else {
                    $entry['fileName']=$entry['Params']['File']['Name'];
                }
                $entry['Content']=array_merge($entry['Content']??[],$statistics);
                $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->fileContent2entry($entry);
                $this->oc['logger']->log('info','Spreadsheet-entry created named "{Name}" containing {rowCount} rows.',['Name'=>$entry['Name'],'rowCount'=>count($csvDefArr['rows'])]);    
            }
            return $entry;
        } else if (isset($entry['Content'])){
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder'],$this->spreadsheetTimestamp,'',TRUE);
            $elementId=$entry['EntryId'];
            $flatContentArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry['Content']);
            if (!isset($_SESSION['spreadSheetVarSpace'][$elementId])){
                $_SESSION['spreadSheetVarSpace'][$elementId]=['rows'=>[],'entry'=>$entry,'first row'=>$flatContentArr];
            }
            $row=[];
            foreach($_SESSION['spreadSheetVarSpace'][$elementId]['first row'] as $column=>$firstRowValue){
                if (isset($flatContentArr[$column])){
                    $row[$column]=$flatContentArr[$column];
                } else {
                    $row[$column]='?';
                }
            }
            $_SESSION['spreadSheetVarSpace'][$elementId]['rows'][]=$row;
            return $entry;
        } else if (!isset($_SESSION['spreadSheetVarSpace'])){
            // nothing to do
            $_SESSION['spreadSheetVarSpace']=[];
        } else {
            $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
            $this->oc['logger']->log('notice','Method "{function}" called by "{trace}" without content',['function'=>__FUNCTION__,'trace'=>$trace[1]['function']]);    
        }
        return FALSE;
    }

    public function matrix2spreadsheetDownload(array $matrix):string
    {
        $spreadsheetSetting=$this->getSpreadsheetSettings('GENERIC');
        $fileType=strtolower($spreadsheetSetting['output format']);
        $file=$this->matrix2spreadsheetTmpFile($matrix,['Name'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($matrix)],$fileType);
        $pathParts=pathinfo($file);
        // command processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['download'])){
            $file2download=key($formData['cmd']['download']);
            if ($this->oc['SourcePot\Datapool\Foundation\Filespace']->isPrivatTmpDirFile($file2download)){
                header('Content-Type: '.self::MIME_MAP[$pathParts['extension']]);
                header('Content-Disposition: attachment; filename="'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime().'_matrix.'.$pathParts['extension'].'"');
                header('Content-Length: '.fileSize($file2download));
                readfile($file2download);
                exit;
            }
        }
        // create html
        $html='';
        $btnArr=['cmd'=>'download','key'=>['download',$file],'title'=>'Download table as csv-file','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $btnArr=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->getBtns($btnArr);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>['clear'=>'none']]);
        return $html;
    }

}
?>