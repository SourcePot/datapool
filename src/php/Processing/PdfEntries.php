<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Processing;

require_once($GLOBALS['dirs']['src'].'fpdf/fpdf.php');
require_once($GLOBALS['dirs']['php'].'Tools/ondemand/PdfDoc.php');

class PdfEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    private $sampleTargetFile='';
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );

    private $paper=array('a4'=>'A4',
                         'a3'=>'A3',
                         'a5'=>'A5',
                         'a6'=>'A6',
                         );
    
    private $contentTypes=array('content'=>'Page content',
                                'header'=>'Page header',
                                'footer'=>'Page footer',
                                );
    
    private $alignments=array('L'=>'start',
                             'C'=>'center',
                             'R'=>'end',
                             'J'=>'justify'
                            );
    
    private $fontStyles=array(''=>'normal',
                              'B'=>'bold',
                              'I'=>'italic',
                              'U'=>'underline'
                              );
    
    private $fonts=array('Arial'=>'Arial, Helvetica, sans-serif',
                         'Courier'=>'Courier New',
                         'Times'=>'"Times New Roman", Times, serif',
                         'Symbol'=>'Symbol, sans-serif;',
                         'ZapfDingbats'=>"Wingdings, 'Zapf Dingbats', sans-serif",
                         );

    private $orientation=array('P'=>'Portrait','L'=>'Landscape');
    
    private $pageSettings=[];
    
    public function __construct($oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
        $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
        // get sample target file
        $this->sampleTargetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir().'PdfEntries.pdf';
    }
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate(){
        return $this->entryTemplate;
    }

   /**
     * This method is the interface of this data processing class
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method 
     * @param string $action Selects the requested process to be run  
     *
     * @return string|bool Return the html-string or TRUE callingElement does not exist
     */
    public function dataProcessor(array $callingElementSelector=[],string $action='info'){
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->runPdfEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runPdfEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getPdfEntriesWidget($callingElement),
                'settings'=>$this->getPdfEntriesSettings($callingElement),
                'info'=>$this->getPdfEntriesInfo($callingElement),
            };
        }
    }

    private function getPdfEntriesWidget(array $callingElement):string
    {
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('PDF creation','generic',$callingElement,array('method'=>'getPdfEntriesWidgetHtml','classWithNamespace'=>__CLASS__),[]);
        return $html;
    }

    private function getPdfEntriesInfo(array $callingElement):string
    {
        $matrix=array('Please note:'=>array('Placeholder'=>'If the canvas element contains an entry with an attached jpeg- or png-image, this image will be available as logo.<br/>The placeholder [[logo]] should only be used on its own, not within a continuous text.'));
        $matrix['Logo']=array('Placeholder'=>'[[logo]]');
        $matrix['Page number']=array('Placeholder'=>'[[pageNumber]]');
        $matrix['Page count']=array('Placeholder'=>'[[pageCount]]');
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'?'));
        return $html;
    }

    private function getLogo(array $callingElement):string|bool
    {
        $logoFile=FALSE;
        $selector=$callingElement['Content']['Selector'];
        $selector['Params']='%image\\/%';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $entry){
            if (empty($entry['Params']['File'])){continue;}
            $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
            if (strpos($entry['Params']['File']['MIME-Type'],'image')!==0 || !is_file($file)){continue;}
            if (strpos($entry['Params']['File']['MIME-Type'],'jpeg')!==FALSE || strpos($entry['Params']['File']['MIME-Type'],'png')!==FALSE){
                $logoFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getPrivatTmpDir().$entry['EntryId'].'.'.$entry['Params']['File']['Extension'];
                if (is_file($logoFile)){
                    // logo file already present
                    break;
                } else {
                    // loge file needs to be copied to the private tmp-dir
                    if ($this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($file,$logoFile)){
                        $this->oc['logger']->log('info','Function {class} &rarr; {function}() copied logo-file "{name}" to the private temp dir.',array('class'=>__CLASS__,'function'=>__FUNCTION__,'name'=>$entry['Params']['File']['Name']));         
                        break;
                    } else {
                        $this->oc['logger']->log('warning','Function {class} &rarr; {function}() failed  to copy logo-file to the private temp dir.',array('class'=>__CLASS__,'function'=>__FUNCTION__));         
                    }
                }
            }
        }
        return $logoFile;
    }
       
    public function getPdfEntriesWidgetHtml(array $arr):array
    {
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runPdfEntries($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runPdfEntries($arr['selector'],TRUE);
        }
        // build html
        $btnArr=array('tag'=>'input','type'=>'submit','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $matrix=[];
        $btnArr['value']='Test';
        $btnArr['key']=array('test');
        $matrix['Commands']['Test']=$btnArr;
        $btnArr['value']='Run';
        $btnArr['key']=array('run');
        $matrix['Commands']['Run']=$btnArr;
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Mapping widget'));
        foreach($result as $caption=>$matrix){
            $appArr=array('html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption)));
            $appArr['icon']=$caption;
            //if ($caption==='Copying results'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr=$this->pdfPreview($arr);
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }


    private function getPdfEntriesSettings(array $callingElement):string
    {
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('PDF entries settings','generic',$callingElement,array('method'=>'getPdfEntriesSettingsHtml','classWithNamespace'=>__CLASS__),[]);
        }
        return $html;
    }
    
    public function getPdfEntriesSettingsHtml(array $arr):array
    {
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->pdfParams($arr['selector']);
        $arr['html'].=$this->pdfPlaceholder($arr['selector']);
        $arr['html'].=$this->pdfRules($arr['selector']);
        return $arr;
    }

    private function pdfParams(array $callingElement):string
    {
        $contentStructure=array('Target'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                'Paper'=>array('method'=>'select','value'=>key($this->paper),'excontainer'=>TRUE,'options'=>$this->paper),
                                'Orientation'=>array('method'=>'select','excontainer'=>TRUE,'value'=>key($this->orientation),'options'=>$this->orientation),
                                'Top margin [mm]'=>array('method'=>'element','tag'=>'input','type'=>'number','value'=>20,'excontainer'=>TRUE),
                                'Bottom margin [mm]'=>array('method'=>'element','tag'=>'input','type'=>'number','value'=>20,'excontainer'=>TRUE),
                                );
        // get selctor
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        $elementId=key($formData['val']);
        if (isset($formData['cmd'][$elementId])){
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        }
        // get HTML
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='PDF control';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['trStyle']=array('background-color'=>'#a00');}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }
    
    private function pdfPlaceholder(array $callingElement):string
    {
        $contentStructure=array('source'=>array('method'=>'keySelect','value'=>'Name','addSourceValueColumn'=>TRUE,'excontainer'=>TRUE),
                                'placeholder'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'{{Name}}','excontainer'=>TRUE),
                                );
        $contentStructure['source']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,FALSE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Placeholder';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function pdfRules(array $callingElement):string
    {
        $contentStructure=array('type'=>array('method'=>'select','value'=>'','options'=>$this->contentTypes,'excontainer'=>TRUE),
                                'text'=>array('method'=>'element','tag'=>'textarea','element-content'=>'','keep-element-content'=>TRUE,'excontainer'=>TRUE),
                                'x-pos [mm]'=>array('method'=>'element','tag'=>'input','type'=>'number','value'=>30,'style'=>array('width'=>'50px'),'title'=>'Negative values set distance in mm from the right edge, from the left edge otherwise','excontainer'=>TRUE),
                                'y-pos [mm]'=>array('method'=>'element','tag'=>'input','type'=>'number','value'=>30,'style'=>array('width'=>'50px'),'title'=>'Negative values set distance in mm from the bottom edge, from the top edge otherwise','excontainer'=>TRUE),
                                'width [mm]'=>array('method'=>'element','tag'=>'input','type'=>'number','value'=>30,'style'=>array('width'=>'50px'),'excontainer'=>TRUE),
                                'height [mm]'=>array('method'=>'element','tag'=>'input','type'=>'number','value'=>10,'style'=>array('width'=>'50px'),'excontainer'=>TRUE),
                                'font'=>array('method'=>'select','value'=>'','options'=>$this->fonts,'excontainer'=>TRUE),
                                'fontSize'=>array('method'=>'element','tag'=>'input','type'=>'number','value'=>12,'style'=>array('width'=>'50px'),'excontainer'=>TRUE),
                                'fontStyle'=>array('method'=>'select','value'=>'','options'=>$this->fontStyles,'excontainer'=>TRUE),
                                'alignment'=>array('method'=>'select','value'=>'J','options'=>$this->alignments,'excontainer'=>TRUE),
                                );
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,FALSE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Content rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
    
    public function pdfPreview(array $arr):array
    {
        $arr['html']=$arr['html']??'';
        $arr['selector']['Params']['TmpFile']['Source']=$this->sampleTargetFile;
        $arr['selector']['Params']['TmpFile']['MIME-Type']='application/pdf';
        $arr['selector']['Params']['File']['Nme']=$arr['selector']['Name'];
        if (is_file($arr['selector']['Params']['TmpFile']['Source'])){
            $arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
        } else {
            $arr['html'].='<p>Preview document missing.<br/>Preview document will be created ob "Test" or "Run" with at least one valid entry.</p>';
        }
        return $arr;    
    }
    
    private function runPdfEntries(array $callingElement,bool $testRun=FALSE):array
    {
        
        $settings=array('pdfparams'=>[],'pdfplaceholder'=>[],'pdfrules'=>[]);
        $settings=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$settings);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Pdf statistics'=>array('Entries'=>array('value'=>0),
                                              'Skip rows'=>array('value'=>0),
                                              )
                    );

        if (is_file($this->sampleTargetFile)){unlink($this->sampleTargetFile);}
        // loop through entries
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            if (strpos($sourceEntry['Params']['File']['MIME-Type'],'image')===0){continue;}
            //if (time()-$settings['Script start timestamp']>30){break;}
            if ($sourceEntry['isSkipRow']){
                $result['Pdf statistics']['Skip rows']['value']++;
                continue;
            }
            $result=$this->pdfEntry($settings,$sourceEntry,$result,$testRun,$callingElement);
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$settings['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function pdfEntry(array $settings,array $sourceEntry,array $result,bool $testRun,$callingElement):array
    {
        $params=current($settings['pdfparams']);
        // get target entry and file
        $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$settings['entryTemplates'][$params['Content']['Target']],TRUE,$testRun);
        if ($testRun){
            $targetFile=$this->sampleTargetFile;
        } else {
            $targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($targetEntry);
        }
        // process rules
        $pageContent=array('header'=>[],'content'=>[],'footer'=>[]);
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        foreach($settings['pdfrules'] as $ruleId=>$rule){
            if ($settings['pdfrules'][$ruleId]['Content']['text']==='[[logo]]'){
                $settings['pdfrules'][$ruleId]['Content']['[[logo]]']=$this->getLogo($callingElement);
            } else {
                foreach($settings['pdfplaceholder'] as $placeholderId=>$placeholder){
                    $placeholderKey=$placeholder['Content']['source'];
                    $placeholderNeedle=$placeholder['Content']['placeholder'];
                    if (!isset($flatSourceEntry[$placeholderKey])){continue;}
                    $settings['pdfrules'][$ruleId]['Content']['text']=str_replace($placeholderNeedle,strval($flatSourceEntry[$placeholderKey]),$settings['pdfrules'][$ruleId]['Content']['text']);
                }
            }
            $pageContent[$settings['pdfrules'][$ruleId]['Content']['type']][$ruleId]=$settings['pdfrules'][$ruleId]['Content'];
        }
        // create pdf
        $pdf= new \SourcePot\Datapool\Tools\PdfDoc($params['Content']['Orientation'],'mm',$params['Content']['Paper']);
        $pdf->SetAuthor($this->pageSettings['pageTitle'],TRUE);
        $pdf->SetTopMargin($params['Content']['Top margin [mm]']);
        $pdf->SetAutoPageBreak(TRUE,$params['Content']['Bottom margin [mm]']);
        $pdf->setHeader($pageContent['header']);
        $pdf->setFooter($pageContent['footer']);
        $pdf->AliasNbPages('[[pageCount]]');
        $pdf->AddPage();
        foreach($pageContent['content'] as $ruleId=>$rule){
            if ($rule['text']==='[[logo]]'){
                if ($rule['[[logo]]']){$pdf->Image($rule['[[logo]]'],$rule['x-pos [mm]'],$rule['y-pos [mm]'],$rule['width [mm]'],$rule['height [mm]']);}
            } else {
                $pdf->SetFont($rule['font'],$rule['fontStyle'],$rule['fontSize']);
                $pdf->SetXY($rule['x-pos [mm]'],$rule['y-pos [mm]']);
                $rule['text']=iconv('UTF-8','windows-1252',$rule['text']);
                $rule['text']=preg_replace('/{{[^{}]+}}/','',$rule['text']);
                $pdf->MultiCell($rule['width [mm]'],$rule['height [mm]'],$rule['text'],0,$rule['alignment']);
            }
        }
        if (is_file($targetFile)){
            unlink($targetFile);
        }
        $pdf->Output('F',$targetFile);
        // pdf file data
        $targetEntry['Params']['File']=[];
        $targetEntry['Params']['File']['Uploaded']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
        $targetEntry['Params']['File']['Size']=filesize($targetFile);
        $targetEntry['Params']['File']['Name']=preg_replace('/[^0-9a-zA-Z]/','_',$targetEntry['Name']);
        $targetEntry['Params']['File']['Extension']='pdf';
        $targetEntry['Params']['File']['MIME-Type']='application/pdf';
        $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($targetEntry,TRUE);
        return $result;
    }
    
}
?>