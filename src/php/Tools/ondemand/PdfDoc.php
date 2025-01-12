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
    private $logo=FALSE;
    protected $B=0;
    protected $I=0;
    protected $U=0;
    protected $HREF='';
    
    public function WriteHTML($html)
    {
        // HTML parser
        $html=str_replace("\n",' ',$html);
        $a=preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
        foreach($a as $i=>$e)
        {
            if($i%2==0){
                // Text
                if($this->HREF){$this->PutLink($this->HREF,$e);} else {$this->Write(5,$e);}
            } else{
                // Tag
                if($e[0]=='/'){
                    $this->CloseTag(strtoupper(substr($e,1)));
                } else {
                    // Extract attributes
                    $a2=explode(' ',$e);
                    $tag=strtoupper(array_shift($a2));
                    $attr=array();
                    foreach($a2 as $v){
                        if(preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3)){$attr[strtoupper($a3[1])]=$a3[2];}
                    }
                    $this->OpenTag($tag,$attr);
                }
            }
        }
    }
    
    function OpenTag($tag,$attr)
    {
        // Opening tag
        if($tag=='B' || $tag=='I' || $tag=='U'){$this->SetStyle($tag,true);}
        if($tag=='A'){$this->HREF=$attr['HREF'];}
        if($tag=='BR'){$this->Ln(5);}
    }
    
    function CloseTag($tag)
    {
        // Closing tag
        if($tag=='B' || $tag=='I' || $tag=='U'){$this->SetStyle($tag,false);}
        if($tag=='A'){$this->HREF='';}
    }
    
    function SetStyle($tag, $enable)
    {
        // Modify style and select corresponding font
        $this->$tag+=($enable?1:-1);
        $style='';
        foreach(array('B', 'I', 'U') as $s){
            if($this->$s>0){$style.=$s;}
        }
        $this->SetFont('',$style);
    }
    
    function PutLink($URL, $txt)
    {
        // Put a hyperlink
        $this->SetTextColor(0,0,255);
        $this->SetStyle('U',true);
        $this->Write(5,$txt,$URL);
        $this->SetStyle('U',false);
        $this->SetTextColor(0);
    }

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
            if ($rule['text']==='[[logo]]'){
                if ($rule['[[logo]]']){
                    $this->Image($rule['[[logo]]'],$rule['x-pos [mm]'],$rule['y-pos [mm]'],$rule['width [mm]'],$rule['height [mm]']);
                }
            } else { 
                $this->SetFont($rule['font'],$rule['fontStyle'],$rule['fontSize']);
                $this->SetXY($rule['x-pos [mm]'],$rule['y-pos [mm]']);
                $rule['text']=iconv('UTF-8','windows-1252',$rule['text']);
                $rule['text']=str_replace('[[pageNumber]]',strval($this->PageNo()),$rule['text']);
                $rule['text']=preg_replace('/{{[^{}]+}}/','',$rule['text']);
                $this->Cell($rule['width [mm]'],$rule['height [mm]'],$rule['text'],0,0,$rule['alignment']);
            }
        }
    }

    // Page footer
    function Footer()
    {
        foreach($this->footer as $ruleId=>$rule){
            if ($rule['text']==='[[logo]]'){
                if ($rule['[[logo]]']){
                    $this->Image($rule['[[logo]]'],$rule['x-pos [mm]'],$rule['y-pos [mm]'],$rule['width [mm]'],$rule['height [mm]']);
                }
            } else { 
                $this->SetFont($rule['font'],$rule['fontStyle'],$rule['fontSize']);
                $this->SetXY($rule['x-pos [mm]'],$rule['y-pos [mm]']);
                $rule['text']=iconv('UTF-8','windows-1252',$rule['text']);
                $rule['text']=str_replace('[[pageNumber]]',strval($this->PageNo()),$rule['text']);
                $rule['text']=preg_replace('/{{[^{}]+}}/','',$rule['text']);
                $this->Cell($rule['width [mm]'],$rule['height [mm]'],$rule['text'],0,0,$rule['alignment']);
            }
        }
    }
    
}
?>