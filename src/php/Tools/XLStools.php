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
    
    public function isXLS(array $selector):bool
    {
        $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector);
        if (!is_file($file)){return FALSE;}
        if (mb_strpos(mime_content_type($file),'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')===FALSE){
            return FALSE;
        } else {
            return TRUE;
        }
    }
    
    public function iterator(array|string $selector,$reader='xlsx'):\Generator
    {
        // get file from selector
        if (is_array($selector)){
            $xlsFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector);
        } else {
            $xlsFile=$selector;
        }
        if (is_file($xlsFile)){
            // scan file
            $reader= \PhpOffice\PhpSpreadsheet\IOFactory::createReader(ucfirst($reader));
            $reader->setReadDataOnly(TRUE);
            try{
                $spreadsheet=$reader->load($xlsFile);
            } catch(\Exception $e){
                $this->oc['logger']->log('error','"{function}" failed to load "{file}"',['function'=>__FUNCTION__,'file'=>$xlsFile,'msg'=>$e->getMessage()]);         
                yield [];
                return FALSE;
            }
            $worksheet=$spreadsheet->getActiveSheet();
            $xls=$worksheet->getRowIterator();
            $keys=[];
            while($xls->valid()){
                $result=[];
                $cellIterator=$xls->current()->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);
                foreach($cellIterator as $columnIndex=>$cell){
                    $cellValue=$cell->getValue();
                    $cellValue??='';
                    if (isset($keys[$columnIndex])){
                        $result[$keys[$columnIndex]]=$cellValue;
                    } else {
                        $keys[$columnIndex]=$cellValue;
                    }
                }
                if ($xls->key()>1){
                    yield $result;
                }
                $xls->next();
            }
        } else {
            // file missing
            yield ['ERROR'=>'"'.__FUNCTION__.'" called but file "'.$xlsFile.'" not found.'];
            return FALSE;
        }
        return TRUE;
    }

}
?>