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

class PdfTools{
    
    private $pageSettings=array();
    private $patieOK=FALSE;
    private $S='';
    
    public function __construct(){
        
    }
   
    public function init($oc){
        $this->oc=$oc;
        $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
        $this->S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
        
        //var_dump($this->attachments2arrSmalot('D:\XRechnung.pdf'));
        //var_dump($this->text2arrSpatie('d:\XRechnung.pdf'));
        //var_dump($this->text2arrSmalot('d:\XRechnung.pdf'));
    }
   
    public function text2arrSpatie($file,$arr=array()){
        if (!class_exists('\Spatie\PdfToText\Pdf')){
           $arr['error'][]='\Spatie\PdfToText\Pdf is missing'; 
        } else if (empty($this->pageSettings['path to Xpdf pdftotext executable'])){
           $arr['error'][]='Path to Xpdf pdftotext executable is missing'; 
        } else if (!is_file($this->pageSettings['path to Xpdf pdftotext executable'])){
           $arr['error'][]='No valid file at '.$this->pageSettings['path to Xpdf pdftotext executable']; 
        } else {
            try{
                $parser=new \Spatie\PdfToText\Pdf($this->pageSettings['path to Xpdf pdftotext executable']);
                $text=$parser->setOptions(['-enc UTF-8'])->setPdf($file)->text();
                $arr['Content']['File content']=$this->textCleanup($text);
            } catch (\Exception $e){
                $arr['error'][]=$e->getMessage();
            }
        }
        return $arr;
    }

    public function text2arrSmalot($file,$arr=array()){
        if (is_file($file)){
            // parser configuration
            $config=new \Smalot\PdfParser\Config();
            $config->setHorizontalOffset('');
            $config->setRetainImageContent(FALSE);
            // check for encryption etc.
            $parser=new \Smalot\PdfParser\Parser([],$config);
            try{
                // parse content
                $pdf=$parser->parseFile($file);
                $arr['Content']['File content']=$this->textCleanup($pdf->getText());
                $arr['Params']['File']['PDF properties']=$pdf->getDetails();
                // clean-up
            } catch (\Exception $e){
                $arr['error'][]=$e->getMessage();
            }
        } else {
            $arr['error'][]='Method '.__FUNCTION__.' failed: Invalid file';
        }
        return $arr;
    }
    
    private function textCleanup($text){
        $text=preg_replace('/[\t ]+/',' ',$text);
        $text=preg_replace('/(\n )+|(\r )+/',"\n",$text);
        $text=preg_replace('/[\n\r]+/',"\n",$text);                
        return $text;
    }
    
    public function attachments2arrSmalot($file,$arr=array()){
        if (is_file($file)){
            $embeddedFileContent='';
            $pdfParser = new \Smalot\PdfParser\Parser();
            $pdfParsed = $pdfParser->parseFile($file);
            $filespecs=$pdfParsed->getObjectsByType('Filespec');
            $embeddedFiles = $pdfParsed->getObjectsByType('EmbeddedFile');
            try{
                // get file specs
                $index=0;
                $specs=array();
                foreach($filespecs as $filespec){
                    $index++;
                    $specArr=$filespec->getDetails();
                    $specFlat=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($specArr);
                    foreach($specFlat as $key=>$value){
                        if (strpos($key,'Type')!==FALSE || strpos($key,'Desc')!==FALSE || strpos($key,'Subtype')!==FALSE){
                            $lastSepPos=strrpos($key,$this->S);
                            if ($lastSepPos){$key=substr($key,$lastSepPos+strlen($this->S));}
                            $specs[$index][$key]=$value;
                        }
                    }
                }
                // get file content
                $index=0;
                foreach ($embeddedFiles as $embeddedFile){
                    $index++;
                    $specsStr=implode(' ',$specs[$index]);
                    $embeddedFileContent.='~~START~'.$specsStr."~~\n";
                    if (!isset($specs[$index]['Subtype'])){$specs[$index]['Subtype']='unknown type';}
                    $content=trim($embeddedFile->getContent());
                    $content=stripslashes($content);
                    if (stripos($specs[$index]['Subtype'],'xml')!==FALSE){
                        try{
                            $content=$this->oc['SourcePot\Datapool\Tools\MiscTools']->xml2arr($content);
                            $content=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($content);
                            foreach($content as $contentKey=>$contentVvalue){
                                $lastSepPos=strrpos($contentKey,$this->S);
                                if ($lastSepPos===FALSE){$lastSepPos=0;} else {$lastSepPos+=strlen($this->S);}
                                $embeddedFileContent.=substr($contentKey,$lastSepPos).': '.trim(strval($contentVvalue)).";\n";
                            }
                        } catch (\Exception $e){
                            $arr['error'][]=$e->getMessage();
                            continue;
                        }
                    } else {
                       $embeddedFileContent.='Content-type not yet implemented';
                    }
                    $embeddedFileContent.='~~END~'.$specsStr."~~\n";
                }
                $arr['Content']['File content embedded']=$embeddedFileContent;
            } catch (\Exception $e){
                $arr['error'][]=$e->getMessage();
            }
        } else {
            $arr['error'][]='Method '.__FUNCTION__.' failed: Invalid file';
        }
        return $arr;
    }


}
?>