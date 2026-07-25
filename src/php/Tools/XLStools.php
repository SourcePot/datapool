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
    
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
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


}
?>