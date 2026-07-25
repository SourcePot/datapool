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

class XLStools{

    private $oc;
    private $spreadsheetTimestamp=FALSE;
    private $mapIndex2letter=[];
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $this->spreadsheetTimestamp=time();
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
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

    public function isSpreadsheet(array|string $selector):string|FALSE
    {
        $spreadsheetFile=(is_array($selector))?$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector):$selector;
        if (!is_file($spreadsheetFile)){
            return FALSE;
        }
        try {
            $fileType=\PhpOffice\PhpSpreadsheet\IOFactory::identify($spreadsheetFile,NULL,TRUE);
            return $fileType;
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            return FALSE;        
        }
    }

    private function getSpreadsheetReaderWorksheets(array|string $selector,string|int $loadSelectedWorksheet=0):array
    {
        $arr=['class'=>__CLASS__,'function'=>__FUNCTION__,'Worksheets'=>[]];
        $spreadsheetFile=(is_array($selector))?$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector):$selector;
        $arr['fileName']=array_pop(preg_split('/[\/\\\]/',$spreadsheetFile));
        if (!is_file($spreadsheetFile)){
            $this->oc['logger']->log('error','"{class} &rarr; {function}" failed to load open "{fileName}"',$arr);         
            return $arr;
        }
        try {
            $arr['fileType']=\PhpOffice\PhpSpreadsheet\IOFactory::identify($spreadsheetFile,NULL,TRUE);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            $arr['msg']=$e->getMessage();
            $this->oc['logger']->log('error','"{class} &rarr; {function}" failed to detect spreadsheet file type of "{fileName}": "{msg}"',$arr);
            return $arr;        
        }
        try{
            $reader= \PhpOffice\PhpSpreadsheet\IOFactory::createReader($arr['fileType']);
            $arr['Worksheets']=$reader->listWorksheetInfo($spreadsheetFile);
        } catch(\Exception $e){
            $arr['msg']=$e->getMessage();
            $this->oc['logger']->log('error','"{class} &rarr; {function}" failed to aquire spreadsheet information from "{fileName}": "{msg}"',$arr);     
            return $arr;    
        }
        if (empty($loadSelectedWorksheet)){
            return $arr;
        } else {
            $arr['selectedWorksheet']=$loadSelectedWorksheet;
            $reader->setLoadSheetsOnly($loadSelectedWorksheet);
        }
        try{
            $reader->setReadEmptyCells(FALSE);
            $reader->setReadDataOnly(TRUE);
            $arr['spreadsheet']=$reader->load($spreadsheetFile);
            $arr['worksheet']=$arr['spreadsheet']->getActiveSheet();
        } catch(\Exception $e){
            $arr['msg']=$e->getMessage();
            $this->oc['logger']->log('error','"{class} &rarr; {function}" failed to load worksheet "{selectedWorksheet}" from "{fileName}": "{msg}"',$arr);         
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

    private function matrix2shreadsheetTmpFile(array $matrix,array $entry, string $fileType='csv'):string
    {
        $fileType=strtolower($fileType);
        $csvSetting=$this->oc['SourcePot\Datapool\Tools\CSVtools']->getSetting(TRUE);
        $currentUser=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $author=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($currentUser,4);
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator($author)
            ->setLastModifiedBy($author)
            ->setTitle($entry['Name']??'')
            ->setSubject('')
            ->setDescription('')
            ->setKeywords('')
            ->setCategory('Data export');
        $dataWorkSheet=$spreadsheet->setActiveSheetIndex(0);
        $rowIndex=0;
        foreach($matrix as $row){
            $rowIndex++;
            $columnIndex=0;
            foreach($row as $header=>$value){
                if ($rowIndex===1){
                    $dataWorkSheet->setCellValue($this->mapIndex2letter[$columnIndex].$rowIndex,$header);
                    $statistics['header'][$columnIndex]=$header;
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
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet,ucfirst($fileType));
        $writer->setDelimiter($csvSetting['separator']);
        $writer->setEnclosure($csvSetting['enclosure']);
        $writer->setLineEnding(strtr($csvSetting['lineSeparator'],['CRLF'=>"\r\n",'LF'=>"\n",'CR'=>"\r",'PHP_EOL'=>PHP_EOL]));
        $writer->save($file);
        return $file;
    }

    public function entry2spreadsheet(array $entry=[],string $fileType='csv'):array|bool
    {
        if (empty($entry) && isset($_SESSION['spreadSheetVarSpace'])){
            // write csvVarSpace -> spreadsheet
            $statistics=['csv entries'=>0,'row count'=>0,'header'=>''];
            foreach($_SESSION['spreadSheetVarSpace'] as $EntryId=>$csvDefArr){
                unset($_SESSION['spreadSheetVarSpace'][$EntryId]);
                $entry=$csvDefArr['entry'];
                $file=$this->matrix2shreadsheetTmpFile($csvDefArr['rows'],$entry,$fileType);
                // add entry
                $entry['fileContent']=file_get_contents($file);
                if (empty($entry['Params']['File']['Name'])){
                    $entry['fileName']=$entry['Name'].'.'.$fileType;
                } else {
                    $entry['fileName']=$entry['Params']['File']['Name'];
                }
                $entry['Content']=$statistics;
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

public function matrix2spreadsheetDownload(array $matrix,string $fileType='csv'):string
    {
        $file=$this->matrix2shreadsheetTmpFile($matrix,['Name'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($matrix)],$fileType);
        // command processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['download'])){
            $file2download=key($formData['cmd']['download']);
            if (is_file($file2download)){
                header('Content-Type: application/csv');
                header('Content-Disposition: attachment; filename="'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime().'_matrix.csv');
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