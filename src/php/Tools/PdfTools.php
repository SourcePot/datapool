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

    private $oc;

    private $pageSettings=array();
    private $S='';
    
    private $formats=array('a4'=>array('width'=>210,'height'=>297),
                           'a3'=>array('width'=>297,'height'=>420),
                           'a5'=>array('width'=>148,'height'=>210),
                           'a6'=>array('width'=>105,'height'=>148),
                           );
    
    public function __construct(array $oc)
    {    
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
        // get complete page settings
        $selector=array('Class'=>'SourcePot\Datapool\Foundation\Backbone','EntryId'=>'init');
        $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryById($selector,TRUE);
    }
    
    public function getFormat(string $paper='a4'):array
    {
        $paper=mb_strtolower($paper);
        if (isset($this->formats[$paper])){
            return $this->formats[$paper];
        } else {
            return $this->formats['a4'];
        }
    }
    
    public function getPdfTextParserOptions():array
    {
        $parserKey='text2arr';
        $parser=array('default'=>'text2arrSmalot','options'=>array());
        foreach(get_class_methods(__CLASS__) as $method){
            if (mb_strpos($method,$parserKey)===FALSE){continue;}
            $parserName=str_replace('text2arr','',$method);
            $parser['options'][$method]=$parserName;
        }
        return $parser;
    }
   
    public function text2arrSpatie($file='',array $arr=array()):array
    {
        $arr['error']=TRUE;
        // get parser setting, add them if missing
        if (!isset($this->pageSettings['Content']['Spatie path to Xpdf pdftotext executable'])){
            $this->pageSettings['Content']['Spatie path to Xpdf pdftotext executable']='';
            $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($this->pageSettings,TRUE);
        }
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__,'file'=>$file,'executable'=>$this->pageSettings['Content']['Spatie path to Xpdf pdftotext executable']);
        // parse file if valid
        if (!is_file($file)){
            // invalid pdf-file
            $this->oc['logger']->log('notice','Parser {function} failed with: file {file} is missing or invalid',$context);    
        } else if (!class_exists('\Spatie\PdfToText\Pdf')){
            // parser class is missing
            $this->oc['logger']->log('error','Parser {function} failed with: class "\Spatie\PdfToText\Pdf" is missing',$context);    
        } else if (empty($this->pageSettings['Content']['Spatie path to Xpdf pdftotext executable'])){
            // path to external parser executable is missing
            $this->oc['logger']->log('warning','Parser {function} failed with: Path to Xpdf pdftotext executable is missing',$context);    
        } else if (!is_file($this->pageSettings['Content']['Spatie path to Xpdf pdftotext executable'])){
            // path to external parser executable is not a file
            $this->oc['logger']->log('error','Parser {function} failed with: {executable} is no valid file',$context);    
        } else {
            try{
                $parser=new \Spatie\PdfToText\Pdf($this->pageSettings['Content']['Spatie path to Xpdf pdftotext executable']);
                $text=$parser->setOptions(['-enc UTF-8'])->setPdf($file)->text();
                $arr['Content']['File content']=$this->textCleanup($text);
                $arr['Params']['Content']['parser']=__FUNCTION__;
                $arr['error']=FALSE;
                $this->oc['logger']->log('info','"{file}" parsed by "{function}" ',$context);    
            } catch (\Exception $e){
                $this->oc['logger']->log('notice','Parser {function} failed with: '.$e->getMessage(),$context);    
            }
        }
        return $arr;
    }

    public function text2arrSmalot($file='',array $arr=array()):array
    {
        $arr['error']=TRUE;
        // get parser setting, add them if missing
        if (!isset($this->pageSettings['Content']['Smalot'])){
            $this->pageSettings['Content']['Smalot']='';
            $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($this->pageSettings,TRUE);
        }
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__,'file'=>$file,'Smalot'=>$this->pageSettings['Content']['Smalot']);
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
                $arr['Params']['Content']['parser']=__FUNCTION__;
                $this->oc['logger']->log('info','"{file}" parsed by "{function}" ',$context);    
                $arr['error']=FALSE;
            } catch (\Exception $e){
                $arr['error'][]=$e->getMessage();
            }
        } else {
            $this->oc['logger']->log('notice','Parser {function} failed with: file {file} is missing or invalid',$context);
        }
        return $arr;
    }
    
    private function textCleanup(string $text):string
    {
        $encodings=['UTF-8','ISO-8859-1','windows-1252'];
        //$encodings=['ISO-8859-1','windows-1252','UTF-8'];
        $encoding=mb_detect_encoding($text,$encodings);
        $text=mb_convert_encoding($text,'UTF-8',$encoding);
        $text=preg_replace('/[\t ]+/',' ',$text);
        $text=preg_replace('/(\n )+|(\r )+/',"\n",$text);
        $text=preg_replace('/[\n\r]+/',"\n",$text);   
        return $text;
    }
    
    public function attachments2arrSmalot($file,array $arr=array()):array
    {
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
                        if (mb_strpos($key,'Type')!==FALSE || mb_strpos($key,'Desc')!==FALSE || mb_strpos($key,'Subtype')!==FALSE){
                            $lastSepPos=strrpos($key,$this->S);
                            if ($lastSepPos){$key=mb_substr($key,$lastSepPos+strlen($this->S));}
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
                                $embeddedFileContent.=mb_substr($contentKey,$lastSepPos).': '.trim(strval($contentVvalue)).";\n";
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