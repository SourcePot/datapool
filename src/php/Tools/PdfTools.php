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

    private $pageSettings=[];
    
    private $formats=['a4'=>['width'=>210,'height'=>297],
                    'a3'=>['width'=>297,'height'=>420],
                    'a5'=>['width'=>148,'height'=>210],
                    'a6'=>['width'=>105,'height'=>148],
                    ];
    
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
        // get complete page settings
        $selector=['Class'=>'SourcePot\Datapool\Foundation\Backbone','EntryId'=>'init'];
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
        $parser=['@function'=>'select','@default'=>'','@options'=>[''=>'None'],'@title'=>'Use "Smalot" as standard parser'];
        foreach(get_class_methods(__CLASS__) as $method){
            if (mb_strpos($method,$parserKey)===FALSE){continue;}
            $parserName=str_replace('text2arr','',$method);
            $parser['@options'][$method]=$parserName;
        }
        return $parser;
    }

    public function text2arrSpatie($file='',array $entry=[]):array
    {
        $entry['error']=(isset($entry['error']))?$entry['error']:[];
        // get parser setting, add them if missing
        if (!isset($this->pageSettings['Content']['Spatie path to Xpdf pdftotext executable'])){
            $this->pageSettings['Content']['Spatie path to Xpdf pdftotext executable']='';
            $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($this->pageSettings,TRUE);
        }
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'file'=>$file,'executable'=>$this->pageSettings['Content']['Spatie path to Xpdf pdftotext executable']];
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
                $entry['Content']['File content']=$this->textCleanup($text);
                $entry['Params']['Content']['parser']=__FUNCTION__;
                $this->oc['logger']->log('info','"{file}" parsed by "{function}" ',$context);
            } catch (\Exception $e){
                $this->oc['logger']->log('notice','Parser {function} failed with: '.$e->getMessage(),$context);
            }
        }
        return $entry;
    }

    public function text2arrSmalot($file='',array $entry=[]):array
    {
        $entry['error']=(isset($entry['error']))?$entry['error']:[];
        // get parser setting, add them if missing
        if (!isset($this->pageSettings['Content']['Smalot'])){
            $this->pageSettings['Content']['Smalot']='';
            $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($this->pageSettings,TRUE);
        }
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'file'=>$file,'Smalot'=>$this->pageSettings['Content']['Smalot']];
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
                $entry['Content']['File content']=$this->textCleanup($pdf->getText());
                $entry['Params']['File']['PDF properties']=$pdf->getDetails();
                $entry['Params']['Content']['parser']=__FUNCTION__;
                $this->oc['logger']->log('info','"{file}" parsed by "{function}" ',$context);    
            } catch (\Exception $e){
                $entry['error'][]=$e->getMessage();
            }
        } else {
            $entry['error'][]='Parser '.$context['function'].' failed with missing or invalid file';
            $this->oc['logger']->log('notice','Parser {function} failed with: file {file} is missing or invalid',$context);
        }
        return $entry;
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
    
    public function attachments2arrSmalot($file,array $entry=[]):array
    {
        $pathinfo=pathinfo($file);
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'file'=>$pathinfo['basename'],'fileName'=>$pathinfo['filename'],'fileExtension'=>$pathinfo['extension'],'attachments'=>0,'embedded'=>0];
        $pdfParser= new \Smalot\PdfParser\Parser();
        $pdfContent=$pdfContent = file_get_contents($file);
        try {
            $context['attachmentsFailed']=[];
            $pdfParsed = $pdfParser->parseContent($pdfContent);
            $filespecs = $pdfParsed->getObjectsByType('Filespec');
            foreach ($filespecs as $filespec){
                $fileDetails=$filespec->getDetails();
                if($filespec->getHeader()->has('EF') && $filespec->getHeader()->get('EF')->has('F')) {
                    $context['embeddedFileName']=$fileDetails['F'];
                    $embeddedFileContent=$filespec->getHeader()->get('EF')->get('F')->getContent();
                    if (!empty($embeddedFileContent)){
                        if (stripos($embeddedFileContent,'rsm:CrossIndustryInvoice')!==FALSE){
                            // XRechnung
                            $entry=$this->oc['SourcePot\Datapool\Tools\ZUGFeRD']->xmlString2entry($embeddedFileContent,$entry);
                            $this->oc['logger']->log('info','Method "{class} &rarr; {function}()" found embedded XRechnung-file "{embeddedFileName}" in "{file}". No additional entry was created, instead the file content will be added to the entry Content-key "zugferd".',$context);
                        } else {
                            // misc embedded file
                            $newEntry=$entry;
                            $newEntry['fileName']=preg_replace('/[^a-zäüößA-ZÄÜÖ0-9\.]+/','_',$context['embeddedFileName']);
                            $newEntry['fileContent']=$embeddedFileContent;
                            $newEntry['Name']=$pathinfo['basename'].' ['.$newEntry['fileName'].']';
                            $newEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($newEntry,['Source','Group','Folder','Name'],'0','',FALSE);
                            $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileContent2entry($newEntry);
                        }
                    } else {
                        $this->oc['logger']->log('notice','Method "{class} &rarr; {function}()" found empty embedded file "{embeddedFileName}" in "{file}". No additional entry was created.',$context);
                    }
                }
            }
        } catch (\Exception $e) {
            $context['error']=$e->getMessage();
            $this->oc['logger']->log('notice','Method "{class} &rarr; {function}()" failed to parse "{file}" with: "{error}"',$context);
        }
        return $entry;
    }

}
?>