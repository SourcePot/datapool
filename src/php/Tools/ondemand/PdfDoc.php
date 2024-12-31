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

class PdfDoc extends \FPDF{
    
    private $header=array();
    private $footer=array();
    
    public function setHeader(array $header):void
    {
        $this->header=$header;
    }
    
    public function setFooter(array $footer):void
    {
        $this->footer=$footer;
    }
    
    // Page header
    function Header()
    {
        foreach($this->header as $ruleId=>$rule){
            $this->SetFont($rule['font'],$rule['fontStyle'],$rule['fontSize']);
            $this->SetY($rule['y-pos [mm]']);
            $rule['text']=iconv('UTF-8','windows-1252',$rule['text']);
            $rule['text']=str_replace('[[pageNumber]]',strval($this->PageNo()),$rule['text']);
            $rule['text']=preg_replace('/{{[^{}]+}}/','',$rule['text']);
            $this->Cell(0,$rule['height [mm]'],$rule['text'],0,0,$rule['alignment']);
        }
    }

    // Page footer
    function Footer()
    {
        foreach($this->footer as $ruleId=>$rule){
            $this->SetFont($rule['font'],$rule['fontStyle'],$rule['fontSize']);
            $this->SetY($rule['y-pos [mm]']);
            $rule['text']=iconv('UTF-8','windows-1252',$rule['text']);
            $rule['text']=str_replace('[[pageNumber]]',strval($this->PageNo()),$rule['text']);
            $rule['text']=preg_replace('/{{[^{}]+}}/','',$rule['text']);
            $this->Cell(0,$rule['height [mm]'],$rule['text'],0,0,$rule['alignment']);
        }
    }
    
}
?>