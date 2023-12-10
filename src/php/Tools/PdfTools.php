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
        $this->S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
        // get complete page settings
        $selector=array('Class'=>'SourcePot\Datapool\Foundation\Backbone','EntryId'=>'init');
        $this->pageSettings=$oc['SourcePot\Datapool\Foundation\Filespace']->entryById($selector,TRUE);
        
        //var_dump($this->attachments2arrSmalot('D:\XRechnung.pdf'));
        //var_dump($this->text2arrSpatie('d:\XRechnung.pdf'));
        //var_dump($this->text2arrSmalot('d:\XRechnung.pdf'));
        
        //$this->getPdfTextParserOptions();
    }
    
    public function getPdfTextParserOptions(){
        $parserKey='text2arr';
        $parser=array('default'=>'text2arrSmalot','options'=>array());
        foreach(get_class_methods(__CLASS__) as $method){
            if (strpos($method,$parserKey)===FALSE){continue;}
            $parserName=str_replace('text2arr','',$method);
            $parser['options'][$method]=$parserName;
        }
        return $parser;
    }
   
    public function text2arrSpatie($file=FALSE,$arr=array()){
        // get parser setting, add them if missing
        if (!isset($this->pageSettings['Content'][__FUNCTION__])){
            $this->pageSettings['Content'][__FUNCTION__]=array('path to Xpdf pdftotext executable'=>'');
            $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($this->pageSettings,TRUE);
        }
        // parse file if valid
        if (!is_file($file)){
            // invalid pdf-file
            $arr['error'][]='Method '.__FUNCTION__.' failed: Invalid file';
        } else if (!class_exists('\Spatie\PdfToText\Pdf')){
            // parser class is missing
            $arr['error'][]='\Spatie\PdfToText\Pdf is missing';
        } else if (empty($this->pageSettings['Content'][__FUNCTION__]['path to Xpdf pdftotext executable'])){
            // path to external parser executable is missing
            $arr['error'][]='Path to Xpdf pdftotext executable is missing'; 
        } else if (!is_file($this->pageSettings['Content'][__FUNCTION__]['path to Xpdf pdftotext executable'])){
            // path to external parser executable is not a file
            $arr['error'][]='No valid file at '.$this->pageSettings['Content'][__FUNCTION__]['path to Xpdf pdftotext executable']; 
        } else {
            try{
                $parser=new \Spatie\PdfToText\Pdf($this->pageSettings['Content'][__FUNCTION__]['path to Xpdf pdftotext executable']);
                $text=$parser->setOptions(['-enc UTF-8'])->setPdf($file)->text();
                $arr['Content']['File content']=$this->textCleanup($text);
            } catch (\Exception $e){
                $arr['error'][]=$e->getMessage();
            }
        }
        return $arr;
    }

    public function text2arrSmalot($file=FALSE,$arr=array()){
        // get parser setting, add them if missing
        if (!isset($this->pageSettings['Content'][__FUNCTION__])){
            $this->pageSettings['Content'][__FUNCTION__]=array();
            $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($this->pageSettings,TRUE);
        }
        // parse file if valid
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
            $arr['Content']['File content']=(isset($arr['Content']['File content']))?$arr['Content']['File content']:'';
            $embeddedFileContent='';
            $pdfParser= new \Smalot\PdfParser\Parser();
            $pdfParsed=$pdfParser->parseFile($file);
            $filespecs=$pdfParsed->getObjectsByType('Filespec');
            $embeddedFiles=$pdfParsed->getObjectsByType('EmbeddedFile');
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
                    $embeddedFileContent.="\n\n~~START~".$specsStr."~~\n";
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
                    $embeddedFileContent.='~~END~'.$specsStr."~~";
                }
                $arr['Content']['File content'].=$embeddedFileContent;
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