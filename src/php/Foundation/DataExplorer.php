<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class DataExplorer implements \SourcePot\Datapool\Interfaces\Job{

    public const MAX_TEST_TIME=3000000000;   // in nanoseconds
    public const MAX_PROC_TIME=60000000000;   // in nanoseconds

    private const ROW_COUNT_LIMIT=FALSE;

    private const SIGNAL_EXPIRY_THRESHOLD='-P10D';  // Negative DateTimeIntervall string, e.g. '-P1D' for 1 day
    
    private const STYLE_CLASSES=[
        'canvas-std'=>'Standard',
        'canvas-red'=>'Error',
        'canvas-green'=>'Data interface',
        'canvas-dark'=>'Other canvas',
        'canvas-text'=>'Text',
        'canvas-text-bold'=>'Text (bold)',
        'canvas-text-la'=>'Text (left-aligned)',
        'canvas-text-ra'=>'Text (right-aligned)',
        'canvas-symbol'=>'Symbol',
        'canvas-processor'=>'Processor',
    ];
    private const DYNAMIC_STYLE_TEMPLATE=[
        'color'=>'rgb({{VALUE}},0,0)',
    ];

    private const SELECTED_CANVAS_ELEMENT_STYLE=[
        'box-shadow'=>'3px 3px 5px 5px var(--attentionColor)',
    ];
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];
    
    public $definition=[
        'Content'=>[
            'Style'=>[
                'Text'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                'Style class'=>['@function'=>'select','@options'=>self::STYLE_CLASSES,'@default'=>'canvas-std'],
                'top'=>['@tag'=>'input','@type'=>'Text','@default'=>'0px'],
                'left'=>['@tag'=>'input','@type'=>'Text','@default'=>'0px'],
                ],
            'Dynamic style'=>[
                'Signal'=>['@function'=>'select','@options'=>[],'@value'=>''],
                'Max signal value age [sec]'=>['@tag'=>'input','@type'=>'Text','@default'=>60,'@title'=>'If last signal value is older, the value will be set to minimum. The value must be larger than a transmission delay.'],
                'Property'=>['@function'=>'select','@options'=>['color'=>'color','background-color'=>'background-color',],'@default'=>'color'],
                'Profile'=>['@function'=>'select','@options'=>['linear'=>'Linear','steps'=>'Steps','threshold'=>'Threshold',],'@default'=>'threshold'],
                'Profile value'=>['@tag'=>'input','@type'=>'Text','@default'=>'','@title'=>'Is the threshold, if profile is Threshold. Is the amount of steps, if profile is Steps'],
                'Min'=>['@tag'=>'input','@type'=>'Text','@default'=>'','@title'=>'Leave empty, if it schould be set beased on the provoded value range'],
                'Max'=>['@tag'=>'input','@type'=>'Text','@default'=>'','@title'=>'Leave empty, if it schould be set beased on the provoded value range'],
                ],
            'Selector'=>[
                'Source'=>['@function'=>'select','@options'=>[]],
                'Group'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                'Folder'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                'Name'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                'EntryId'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                'Type'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                ],
            'Widgets'=>[
                'Processor'=>['@function'=>'processorSelector','@value'=>'SourcePot\Datapool\Processing\DefaultProcessor','@class'=>__CLASS__],
                'Enable signal'=>['@function'=>'select','@options'=>['No','Yes'],'@default'=>0],
                'File upload'=>['@function'=>'select','@options'=>['No','Yes'],'@default'=>0],
                'File upload extract archive'=>['@function'=>'select','@options'=>['No','Yes'],'@default'=>0],
                'File upload extract email parts'=>['@function'=>'select','@options'=>['No','Yes'],'@default'=>0],
                'pdf-file parser'=>['@function'=>'select','@options'=>[],'@default'=>0],
                'Delete selected entries'=>['@function'=>'select','@options'=>['No','Yes'],'@default'=>1],
            ],
        ],
    ];
    
    private $tags=[
        'run'=>['tag'=>'button','element-content'=>'&#10006;','keep-element-content'=>TRUE,'style'=>['font-size'=>'24px','color'=>'#fff;','background-color'=>'#0a0'],'showEditMode'=>TRUE,'type'=>'Control','Read'=>'ALL_CONTENTADMIN_R','title'=>'Close canvas editor'],
        'edit'=>['tag'=>'button','element-content'=>'&#9998;','keep-element-content'=>TRUE,'style'=>['font-size'=>'24px','color'=>'#fff','background-color'=>'#a00'],'showEditMode'=>FALSE,'type'=>'Control','Read'=>'ALL_CONTENTADMIN_R','title'=>'Edit canvas'],
        '&#9881;'=>['tag'=>'button','element-content'=>'&#9881;','keep-element-content'=>TRUE,'class'=>'canvas-processor','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R','title'=>'Step processing','Content'=>['Selector'=>['Source'=>'logger']]],
        'Select'=>['tag'=>'button','element-content'=>'Select','keep-element-content'=>TRUE,'class'=>'canvas-std','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R','title'=>'Database view'],
        'ABCD'=>['tag'=>'button','element-content'=>'ABCD','keep-element-content'=>TRUE,'class'=>'canvas-text','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R','title'=>'Database view'],
        '__BLACKHOLE__'=>['tag'=>'div','element-content'=>'&empty;','keep-element-content'=>TRUE,'class'=>'canvas-std','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R','title'=>'Black hole'],
    ];
    
    private const GRAPHIC_ELEMENTS=[
        'Connectors'=>['&xlarr;','&xrarr;','&xharr;','&larr;','&uarr;','&rarr;','&darr;','&harr;','&varr;','&nwarr;','&nearr;','&searr;','&swarr;','&larrhk;','&rarrhk;','&#8634;','&#8635;','&duarr;','&#10140;','&#8672;','&#8673;','&#8674;','&#8675;'],
        'Symbols'=>['&VerticalSeparator;','&#8285;','&#8286;','-','&#9783;','&sung;','&hearts;','&diams;','&clubs;','&sharp;','&#9850;','&#9873;','&#9888;','&#9885;','&#9986;','&#9992;','&#9993;','&#9998;','&#10004;','&#x2718;','&#10010;','&#10065;','&#10070;'],
        'Math'=>['&empty;','&nabla;','&nexist;','&ni;','&isin;','&notin;','&sum;','&prod;','&coprod;','&compfn;','&radic;','&prop;','&infin;','&angrt;','&angmsd;','&cap;','&int;','&asymp;','&Lt;','&Gt;','&Ll;','&Gg;','&equiv;'],
    ];

    public function __construct(array $oc)
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
        foreach(self::GRAPHIC_ELEMENTS as $category=>$htmlEntities){
            foreach($htmlEntities as $htmlEntity){
                $this->tags[$htmlEntity]=['tag'=>'div','element-content'=>$htmlEntity,'keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>$category,'Read'=>'ALL_CONTENTADMIN_R'];
            }
        }
        $this->completeDefintion();
    }
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
    
    /**
    * Housekeeping method periodically executed by job.php (this script should be called once per minute through a CRON-job)
    * @param    string $vars Initial persistent data space
    * @return   array  Array Updateed persistent data space
    */
    public function job(array $vars=[]):array
    {
        // loop through all canvas elements, create signals if signal creation is enabled for the respective canvas element
        $signalsUpdated=[];
        $selector=['Source'=>$this->entryTable,'Group'=>'Canvas elements'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $canvasElement){
            if (empty($canvasElement['Content']['Selector']['Source']) || empty($canvasElement['Content']['Widgets']['Enable signal'])){continue;}
            $newPropValue=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($canvasElement['Content']['Selector'],TRUE);
            $signal=$this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal($canvasElement['Folder'],'DataExplorer',$canvasElement['Content']['Style']['Text'],$newPropValue);
            $signalsUpdated[$canvasElement['EntryId']]=$canvasElement['Folder'].' → '.$canvasElement['Content']['Style']['Text'];
        }
        $vars['Signals updated']=implode('<br/>',$signalsUpdated);
        // cleanup - delete all signals which were not updated for the timespan set in SIGNAL_EXPIRY_THRESHOLD
        $toDeleteSelector=$this->oc['SourcePot\Datapool\Foundation\Signals']->getSignalSelector('%','DataExplorer');
        $toDeleteSelector['Date<']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now',self::SIGNAL_EXPIRY_THRESHOLD);
        $vars['Signal selector for deletion']=$toDeleteSelector;
        $vars['Signals deleted']=$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($toDeleteSelector,TRUE)['deleted'];
        return $vars;
    }
    
    private function completeDefintion():void
    {
        // add Source selector
        $sourceOptions=[''=>''];
        $dbInfo=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate(FALSE);
        foreach($dbInfo as $Source=>$entryTemplate){
            $sourceOptions[$Source]=$Source;
        }
        $this->definition['Content']['Selector']['Source']['@options']=$sourceOptions;
        $this->definition['Content']['Widgets']['pdf-file parser']=$this->oc['SourcePot\Datapool\Tools\PdfTools']->getPdfTextParserOptions();
        // add signal options
        $this->definition['Content']['Dynamic style']['Signal']['@options']=[''=>'-']+$this->oc['SourcePot\Datapool\Foundation\Signals']->getSignalOptions();
        // add save button
        $this->definition['save']=['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'];
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
    }

    /**
    * Class specific entry normalization.
    * @param    array  $entry  Entry
    * @return   array  Normalized entry
    */
    public function unifyEntry(array $entry):array
    {
        $entry['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
        // new entry -> create structure
        if (!empty($entry['element-content'])){
            $entry['Name']=$entry['element-content'];
            $entry['Content']['Style']['Text']=$entry['element-content'];
            if (mb_strpos($entry['element-content'],'&#9881;')!==FALSE){
                $entry['Content']['Widgets']['Processor']='SourcePot\Datapool\Processing\CanvasProcessing';
            } else if (mb_strpos($entry['element-content'],'&empty;')!==FALSE){
                $entry['Content']['Selector']=['__BLACKHOLE__'=>TRUE,'Source'=>$this->entryTable,'Group'=>'__BLACKHOLE__'];
                $entry['Content']['Widgets']['Processor']='';
            }
        }
        // adjust style class and unify Name
        if (!empty($entry['class'])){
            $entry['Content']['Style']['Style class']=$entry['class'];
        }
        if (!empty($entry['Content']['Style']['Text'])){
            $entry['Name']=$entry['Content']['Style']['Text'];
        }
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_MEMBER_R','ALL_CONTENTADMIN_R');
        $entry=$this->oc['SourcePot\Datapool\Foundation\Definitions']->definition2entry($this->definition,$entry);
        return $entry;
    }

    /**
    * Creates array containing html-content for the canvas explorer as well as content, including widgets etc.
    * @param    string $callingClass  Class calling this method
    * @return   array  Array containing the html
    */
    public function getDataExplorer(string $callingClass):array
    {
        $return=['isEditMode'=>$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'isEditMode'),'contentHtml'=>''];
        // add canvas element
        $return['canvasElement']=$this->canvasFormProcessing($callingClass);
        // create explorer html
        $cntrHtmlArr=$this->getCntrHtml($callingClass);
        $canvasHtml=$this->getCanvas($callingClass);
        $articleArr=['tag'=>'article','class'=>'explorer','element-content'=>$canvasHtml.$cntrHtmlArr['cntr'],'keep-element-content'=>TRUE,'style'=>[]];
        $return['explorerHtml']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($articleArr);
        $return['explorerHtml'].=$cntrHtmlArr['processor'];
        // create content html
        $isEditMode=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'isEditMode',FALSE);
        if (!empty($return['canvasElement']['Content']['Widgets']["Processor"]) && !$isEditMode){
            $canvasElement=$return['canvasElement'];
            $processor=$canvasElement['Content']['Widgets']["Processor"];
            if (isset($this->oc[$processor])){
                $return['contentHtml'].=$this->oc[$processor]->dataProcessor($canvasElement,'settings');
            }
        }
        return $return;
    }
    
    /**
    * Canvas element form processing.
    * @param    string $callingClass  Class calling this method
    * @return   array  Canvas element
    */
    private function canvasFormProcessing(string $callingClass):array|bool
    {
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'getCanvas',TRUE);
        if (!empty($formData['cmd'])){
            $cmd=key($formData['cmd']);
            $elementSelector=['Source'=>key($formData['cmd'][$cmd])];
            $elementSelector['EntryId']=key($formData['cmd'][$cmd][$elementSelector['Source']]);
            $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($elementSelector);
            $this->addUserAction($canvasElement,$formData);
        }
        if (isset($formData['cmd']['select'])){
            $canvasElement=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'selectedCanvasElement',$canvasElement);
            $this->addUserAction($canvasElement,$formData);
        } else if (isset($formData['cmd']['delete'])){
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($canvasElement);
            $canvasElement=[];
        } else if (isset($formData['cmd']['view'])){
            $canvasElement=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'selectedCanvasElement',$canvasElement);
            $selector=$canvasElement['Content']['Selector'];
            if ($classWithNamespace=$this->oc['SourcePot\Datapool\Root']->source2class($selector['Source'])){
                $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($classWithNamespace,$selector);
            }
        } else {
            $canvasElement=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'selectedCanvasElement');    
        }
        return $canvasElement;
    }
    
    /**
    * Creates html control panel with two parts: 'cntr' and 'processor'.
    * @param    string $callingClass  Class calling this method
    * @return   array  Array containing the html
    */
    private function getCntrHtml(string $callingClass):array
    {
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__,TRUE);
        if (isset($formData['cmd']['run'])){
            $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'isEditMode',FALSE);
        } else if (isset($formData['cmd']['edit'])){
            $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'isEditMode',TRUE);
        } else if (!empty($formData['cmd'])){
            // insert new canvas element; canvas element structure will be finalized by unifyEntry-method 
            $entry=['Source'=>$this->entryTable,'Group'=>'Canvas elements','Folder'=>$callingClass];
            $entry=array_merge($this->tags[key($formData['cmd'])],$entry);
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($entry);
        }
        // build control html
        $isEditMode=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'isEditMode',FALSE);
        $isEditMode=$this->oc['SourcePot\Datapool\Foundation\Access']->accessSpecificValue('ALL_CONTENTADMIN_R',$isEditMode,FALSE);
        if (!$this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){$isEditMode=FALSE;}
        $matrix=[];
        foreach($this->tags as $key=>$tag){
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->accessSpecificValue('ALL_CONTENTADMIN_R',FALSE,TRUE)){continue;}
            if ($tag['showEditMode']!==$isEditMode){continue;}
            $btn=$tag;
            $btnTemplate=['tag'=>'button','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>[$key],'style'=>['position'=>'relative','padding'=>'0','margin'=>'0.1em','font-size'=>'18px']];
            $btn=array_replace_recursive($btn,$btnTemplate);
            $btn['class']='canvas-cntr-btn';
            if (!isset($matrix[$tag['type']]['Btn'])){
                $matrix[$tag['type']]['Btn']='';
            }
            $matrix[$tag['type']]['Btn'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btn);
        }
        $htmlArr=['cntr'=>'','processor'=>''];
        $tableArr=['matrix'=>$matrix,'keep-element-content'=>TRUE,'hideHeader'=>TRUE,'caption'=>'Canvas'];
        if ($isEditMode){$tableArr['style']=['min-width'=>'550px'];}
        $htmlArr['cntr']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table($tableArr);
        $selectedCanvasElement=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'selectedCanvasElement');
        $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selectedCanvasElement);
        if ($isEditMode){
            if ($canvasElement && $canvasElement['Content']['Style']['Text']!=='&empty;'){
                $htmlArr['cntr'].=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($canvasElement);
            }
        } else {
            $htmlArr['cntr'].=$this->getFileUpload($canvasElement);
            $htmlArr['cntr'].=$this->getDeleteBtn($canvasElement);
            if (!empty($canvasElement['Content']['Widgets']["Processor"])){
                $processorClass=$canvasElement['Content']['Widgets']["Processor"];
                if (isset($this->oc[$processorClass])){
                    $htmlArr['processor'].=$this->oc[$processorClass]->dataProcessor($canvasElement,'widget');
                    $htmlArr['processor'].=$this->oc[$processorClass]->dataProcessor($canvasElement,'info');
                } else {
                    $this->oc['logger']->log('error','Processor class {processor} missing',['processor'=>$processorClass]);
                }
            }
            $htmlArr['cntr'].=$this->exportImportHtml($callingClass);
        }
        return $htmlArr;
    }
    
    /**
    * Creates canvas html inluding canvas elements.
    * @param    string $callingClass  Class calling this method
    * @return   string Html replesenting the complete canvas with canvas elements
    */
    private function getCanvas(string $callingClass):string
    {
        // create html
        $selectedCanvasElement=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'selectedCanvasElement');
        $html='';
        $selector=['Source'=>$this->entryTable,'Group'=>'Canvas elements','Folder'=>$callingClass];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector) as $entry){
            $html.=$this->canvasElement2html(__CLASS__,__FUNCTION__,$entry,$selectedCanvasElement);
        }
        $html='<div id="canvas">'.$html.'</div>';
        $html='<div id="canvas-wrapper" style="width:100%;overflow-x:auto;overflow-y:hidden;">'.$html.'</div>';
        return $html;
    }
    
    /**
    * Creates canvas element html.
    * @param    string  $callingClass  Class calling this method
    * @param    string  $callingFunction  Method calling this method
    * @param    array   $canvasElement  Canvas element array
    * @param    bool    $selectedCanvasElement  TRUE if canvas element is selected
    * @return   string  Html replesenting the canvas element
    */
    private function canvasElement2html($callingClass,$callingFunction,$canvasElement,$selectedCanvasElement=FALSE){
        $rowCount=FALSE;
        $element=['tag'=>'div'];
        // get canvas element style
        $style=['left'=>$canvasElement['Content']['Style']['left'],'top'=>$canvasElement['Content']['Style']['top']];
        if (!empty($selectedCanvasElement['EntryId'])){
            if (strcmp($selectedCanvasElement['EntryId'],$canvasElement['EntryId'])===0){
                $style=array_merge($style,self::SELECTED_CANVAS_ELEMENT_STYLE);
            }
        }
        $text=$canvasElement['Content']['Style']['Text'];
        $isEditMode=!empty($this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'isEditMode',FALSE));
        $isEditMode=$this->oc['SourcePot\Datapool\Foundation\Access']->accessSpecificValue('ALL_CONTENTADMIN_R',$isEditMode,FALSE);
        if ($isEditMode){
            $btnArr=['tag'=>'button','value'=>'edit','Source'=>$canvasElement['Source'],'EntryId'=>$canvasElement['EntryId'],'keep-element-content'=>TRUE,'class'=>'canvas-element-btn'];
            $btnArr['callingClass']=$callingClass;
            $btnArr['callingFunction']=$callingFunction;
            // canvas element select button
            $btnArr['style']=['top'=>'-5px'];
            $btnArr['key']=['select',$canvasElement['Source'],$canvasElement['EntryId']];
            $btnArr['title']='Select';
            $btnArr['id']=md5('select'.$canvasElement['EntryId'].__FUNCTION__);
            $btnArr['element-content']='&#10022;';
            $text.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
            // canvas element delete button
            $btnArr['style']=['bottom'=>'-5px'];
            $btnArr['title']='Delete';
            $btnArr['key']=['delete',$canvasElement['Source'],$canvasElement['EntryId']];
            $btnArr['id']=md5('delete'.$canvasElement['EntryId'].__FUNCTION__);
            $btnArr['element-content']='🗑';
            $text.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
            //
            $style['cursor']='pointer';
        } else {
            if (empty($canvasElement['Content']['Selector']['Source'])){
                if (strpos($canvasElement['Content']['Style']['Style class'],'canvas-text')===FALSE && $canvasElement['Content']['Style']['Style class']!=='canvas-symbol'){
                    $rowCount='SOURCE NOT SET!';
                }
            } else {
                // canvas element view button
                $element=$canvasElement;
                $element['key']=['view',$canvasElement['Source'],$canvasElement['EntryId']];
                $element['id']=md5('view'.$canvasElement['EntryId'].__FUNCTION__);
                $element['tag']='button';
                $style['z-index']='5';
                $style['box-sizing']='content-box';
                $rowCountSelector=$canvasElement['Content']['Selector'];
                $rowCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($rowCountSelector,TRUE,'Read',FALSE,TRUE,self::ROW_COUNT_LIMIT,FALSE,TRUE);
                if ($rowCount===self::ROW_COUNT_LIMIT){
                    $rowCount='>'.$rowCount;
                }
            }
            if (!empty($canvasElement['Content']['Dynamic style']['Signal'])){
                $element['dynamic-style-id']=$canvasElement['EntryId'];
            }
        }
        // canvas element
        if ($rowCount!==FALSE && strcmp($canvasElement['Content']['Style']['Text'],'&#9881;')!==0 && strcmp($canvasElement['Content']['Style']['Text'],'&empty;')!==0){
            $elmentInfo=['tag'=>'p','class'=>'canvas-info','element-content'=>'('.$rowCount.')'];
            $text.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elmentInfo);
        }
        $element['source']=$canvasElement['Source'];
        $element['entry-id']=$canvasElement['EntryId'];
        $element['class']=$canvasElement['Content']['Style']['Style class'];
        $element['element-content']=$text;
        $element['keep-element-content']=TRUE;
        $element['callingClass']=$callingClass;
        $element['callingFunction']=$callingFunction;
        $element['style']=$style;
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        return $html;
    }

    /**
    * Returns all settings for a canvas element.
    * @param string $callingClass    The calling method's class-name
    * @param string $callingFunction The calling method's name
    * @param array  $callingElement  Is the canvas element from which the template is derived
    * @param array  $settings        Initial settings
    * @return array  Canvas element settings
    */
    public function callingElement2settings(string $callingClass,string $callingFunction,array $callingElement,array $settings=[]):array
    {
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $settings['callingElement']=$callingElement['Content']??[];
        $settings['entryTemplates']['__BLACKHOLE__']=['Source'=>'__BLACKHOLE__'];
        $entriesSelector=['Source'=>$this->oc[$callingClass]->getEntryTable(),'Name'=>$callingElement['EntryId']??''];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            $key=strtolower($entry['Group']);
            $settings[$key][$entry['EntryId']]=$entry;
            // entry template
            foreach($entry['Content'] as $contentKey=>$content){
                if (is_array($content)){continue;}
                if (mb_strpos($content,'EID')!==0 || mb_strpos($content,'eid')===FALSE){continue;}
                $template=$this->entryId2selector($content);
                if ($template){$settings['entryTemplates'][$content]=$template;}
            }
        }
        return $settings;
    }
    
    /**
    * Creates an arr-template (for rules, parameters etc) for the callingElement.
    * The template is used by HTMLbuilder's entry2row(arr-template) method.
    * @param string    $callingClass       The calling method's class-name
    * @param string    $callingFunction    The calling method's name
    * @param array     $callingElement     Is the canvas element from which the template is derived
    * @return array    arr-template
    */
    public function callingElement2arr(string $callingClass,string $callingFunction,array $callingElement):array
    {
        if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){
            return [];
        }
        $entry=['Source'=>$this->oc[$callingClass]->getEntryTable(),'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId']];
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Group','Folder','Name'],0);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ALL_CONTENTADMIN_R');
        $entry['Content']=[];
        $arr=['callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'selector'=>$entry,'canvasCallingClass'=>$callingElement['Folder']];
        return $arr;
    }
    
    /**
    * Canvas element selector from calling class.
    * @param string    $callingClass       The calling method's class-name
    * @return array    Selector
    */
    public function canvasSelector(string $callingClass):array
    {
        $selector=['Source'=>$this->entryTable,'Group'=>'Canvas elements','Folder'=>$callingClass];
        return $selector;
    }

    /**
    * Canvas elements of calling class. This method is called by HTMLbuilder to provide a canvas elements selector.
    * @param string    $callingClass    The calling method's class-name
    * @param string    $EntryId         If empty all relevant canvas elements will be returned, else the selected canvas element 
    * @return array    Canvas elements
    */
    public function getCanvasElements(string $callingClass, string $EntryId='',bool $skipCanvasProcessingElements=TRUE):array
    {
        $elements=[];
        if (empty($EntryId)){
            $selector=$this->canvasSelector($callingClass);
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $entry){
                if ($skipCanvasProcessingElements && $callingClass!=='SourcePot\Datapool\Processing\CanvasProcessing' && $entry['Content']['Style']['Text']==='&#9881;'){
                    continue;
                }
                $elements[$entry['Content']['Style']['Text']]=$entry;
            }
            ksort($elements);
        } else {
            if ($entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(['Source'=>$this->entryTable,'EntryId'=>$EntryId])){
                $elements[$EntryId]=$entry; 
            }
        }
        return $elements;
    }
    
    /**
    * Selector contained within a canvas element which is selected by EntryId.
    * @param string    $entryId Is the EntryId
    * @return array    Selector or an empty array
    */
    public function entryId2selector(string $entryId):array
    {
        $canvasElement=current($this->getCanvasElements('',$entryId));
        if (isset($canvasElement['Content']['Selector'])){
            foreach($canvasElement['Content']['Selector'] as $key=>$value){
                if (empty($value)){continue;}
                $selector[$key]=$value;
            }
            krsort($selector);
            return $selector;
        } else {
            return [];
        }
    }

    public function processorSelector($arr):string
    {
        $arr['options']=$this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Processor');
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($arr);
        return $html;
    }

    public function finalizeContentStructure(array $contentStructure,array $callingElement):array
    {
        $keysRequireSelector=['keySelect'=>TRUE];
        foreach($contentStructure as $key=>$tagArr){
            if ($keysRequireSelector[$tagArr['method']??'']??FALSE){
                $contentStructure[$key]+=$callingElement['Content']['Selector'];
            }
        }
        return $contentStructure;
    }

    /**
    * Update canvas element properties with $arr values, i.e. style including position.
    * This method is called via js-request.
    * @param    array   $canvasElement Array containing vales to be updated
    * @return   $canvasElement Updated canvas element
    */
    public function setCanvasElementStyle(array $canvasElement):array
    {
        if (!empty($canvasElement['Source']) && !empty($canvasElement['EntryId']) && !empty($canvasElement['Content']['Style'])){
            $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($canvasElement);
        }
        return $canvasElement;
    }
    
    /**
    * File upload form linked to the canvas element.
    * @param    array   $canvasElement  Canvas elememnt
    * @return   string  Html-form
    */
    public function getFileUpload(array $canvasElement):string
    {
        if (empty($canvasElement['Content']['Widgets']['File upload'])){return '';}
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__,TRUE);
        if (isset($formData['cmd']['upload'])){
            foreach($formData['files']['upload_'] as $fileArr){
                if (empty($fileArr["tmp_name"])){continue;}
                $entry=$canvasElement['Content']['Selector'];
                $entry['File upload extract archive']=!empty($canvasElement['Content']['Widgets']['File upload extract archive']);
                $entry['File upload extract email parts']=!empty($canvasElement['Content']['Widgets']['File upload extract email parts']);
                $entry['pdf-file parser']=(isset($canvasElement['Content']['Widgets']['pdf-file parser']))?$canvasElement['Content']['Widgets']['pdf-file parser']:'';
                $entry['EntryId']=hash_file('md5',$fileArr["tmp_name"]);
                if (empty($entry['Folder'])){$entry['Folder']='Upload';}
                if (empty($entry['Name'])){$entry['Name']=$fileArr["name"];}
                $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_MEMBER_R','ALL_MEMBER_R');
                $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($fileArr,$entry);
            }
        }
        // create html
        $uploadElement=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->fileUpload(['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>['upload'],'element-content'=>'Add file(s)'],['formProcessingArg'=>$canvasElement]);
        $matrix=[];
        $matrix['upload']=['value'=>$uploadElement];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'File upload']);
        return $html;
    }
    
    /**
    * Delete button linked to the canvas element.
    * @param    array   $canvasElement  Canvas elememnt
    * @return   string  Delete button html
    */
    private function getDeleteBtn(array $canvasElement):string
    {
        if (empty($canvasElement['Content']['Widgets']['Delete selected entries'])){return '';}
        $deleteBtn=['selector'=>$canvasElement['Content']['Selector']];
        $deleteBtn['cmd']='delete all';
        $deleteBtn=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($deleteBtn);
        $matrix=[];
        $matrix['cmd']=['value'=>$deleteBtn];
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Delete entries']);
    }
    
    /**
    * Export/import form linked to the App.
    * @param    string  $callingClass  Is the class calling this method
    * @return   string  Import/export html form
    */
    private function exportImportHtml(string $callingClass):string
    {
        if (!$this->oc['SourcePot\Datapool\Foundation\Access']->accessSpecificValue('ALL_CONTENTADMIN_R')){
            return '';
        }
        $selectors=[];
        foreach($GLOBALS['dbInfo'] as $table=>$infoArr){
            $selectors[$table]=['Source'=>$table,'Folder'=>$callingClass];
        }
        $selectors=['dataexplorer'=>['Source'=>'dataexplorer','Folder'=>$callingClass]];
        foreach($this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Processor') as $classWithNamespace){
            $source=$this->oc['SourcePot\Datapool\Root']->class2source($classWithNamespace);
            $selectors[$source]=['Source'=>$source,'Folder'=>$callingClass];
        }
        $callingClassName=mb_substr($callingClass,strrpos($callingClass,'\\')+1);
        $className=mb_substr(__CLASS__,strrpos(__CLASS__,'\\')+1);
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__,TRUE);
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        if (isset($formData['cmd']['Download backup'])){
            $exportFileName=date('Y-m-d').' '.$className.' '.$callingClassName.' dump.zip';
            $this->oc['SourcePot\Datapool\Foundation\Filespace']->downloadExportedEntries($selectors,$exportFileName);
        } else if (isset($formData['cmd']['Import'])){
            $tmpFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir().'tmp.zip';
            $success=move_uploaded_file($formData["files"]["import files"][0]['tmp_name'],$tmpFile);
            if ($success){
                foreach($selectors as $selector){
                    $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector);
                }
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->importEntries($tmpFile,$formData["files"]["import files"][0]['name']);
            } else {
                $this->oc['logger']->log('notice','Import of "{name}" failed',$formData["files"]["import files"][0]);    
            }
        }
        $btnArr=['tag'=>'button','keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>['float'=>'left','clear'=>'both','margin'=>'0.5em 5px;']];
        $html='';
        $btnArr['element-content']='Download backup';
        $btnArr['key']=[$btnArr['element-content']];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        $element=['tag'=>'input','type'=>'file','multiple'=>TRUE,'key'=>['import files'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>'float:left;clear:left;margin:0.5em 5px;'];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        $btnArr['element-content']='Import';
        $btnArr['key']=[$btnArr['element-content']];
        $btnArr['hasCover']=TRUE;
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'&#9850;']);
        return $html;
    }

    private function addUserAction($canvasElement,$formData)
    {
        $selectedStatusEntry=['Source'=>$this->getEntryTable(),'Group'=>'User action','Folder'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),'Name'=>$canvasElement['EntryId'],'Content'=>['function'=>__FUNCTION__,'action'=>key($formData['cmd'])]];
        $selectedStatusEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($selectedStatusEntry,['Group','Folder'],0);
        $selectedStatusEntry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','PT5M');
        $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($selectedStatusEntry);
    }

    public function getUserActions($data):array
    {
        $userActions=[];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator(['Source'=>$this->getEntryTable(),'Group'=>'User action'],TRUE) as $userAction){
            if ($userAction['Folder']===$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()){continue;}
            $userActions[$userAction['Folder']]=[
                'action'=>$userAction['Content']['action'],
                'canvas-element'=>$userAction['Name'],
                'color'=>($userAction['Content']['action']==='view')?'var(--green)':'var(--attentionColor)',
                'User'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($userAction['Folder'],0),
            ];
        }
        return $userActions;
    }

    public function getDynamicStyle($data)
    {
        // get canvas-element with attached signal
        $canvasElementSelector=['Source'=>$this->entryTable,'EntryId'=>$data['dynamic-style-id']];
        $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElementSelector,TRUE);
        if (!isset($canvasElement['Content']['Dynamic style'])){return [];}
        // get signal
        $signalSelector=['Source'=>$this->oc['SourcePot\Datapool\Foundation\Signals']->getEntryTable(),'EntryId'=>$canvasElement['Content']['Dynamic style']['Signal']];
        $signal=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($signalSelector,TRUE);
        if (empty($signal['Content']['signal'])){return [];}
        // signal -> style
        $signalProps=['min'=>NULL,'max'=>NULL,'last'=>NULL,'latestTimestamp'=>NULL];
        foreach($signal['Content']['signal'] as $valueArr){
            $value=floatval($valueArr['value']);
            $timeStamp=intval($valueArr['timeStamp']);
            if ($signalProps['last']===NULL){
                $signalProps['latestTimestamp']=$timeStamp;
                $signalProps['last']=$signalProps['min']=$signalProps['max']=$value;
            }
            if ($signalProps['latestTimestamp']<$timeStamp){
                $signalProps['latestTimestamp']=$timeStamp;
                $signalProps['last']=$value;
            }
            if ($signalProps['min']>$valueArr['value']){
                $signalProps['min']=$value;
            }
            if ($signalProps['max']<$valueArr['value']){
                $signalProps['max']=$value;
            }
        }
        $signalProps['min']=($canvasElement['Content']['Dynamic style']['Min'])?:$signalProps['min'];
        $signalProps['max']=($canvasElement['Content']['Dynamic style']['Max'])?:$signalProps['max'];
        $maxAge=intval($canvasElement['Content']['Dynamic style']['Max signal value age [sec]']?:PHP_INT_MAX);
        if ($maxAge<(time()-$signalProps['latestTimestamp'])){
            $signalProps['last']=$signalProps['min'];
            $signalProps['latestTimestamp']=time();
        }
        // calculate style property based on value and profile
        $styleProp=['property'=>$canvasElement['Content']['Dynamic style']['Property']];
        $range=(($signalProps['max']??0)-($signalProps['min']??0))?:1;
        $valueMinusMin=($signalProps['last']??0)-($signalProps['min']??0);
        $profile=$canvasElement['Content']['Dynamic style']['Profile']?:'linear';
        if ($profile=='linear'){
            $newPropValue=$valueMinusMin/$range;
        } else if ($profile=='steps'){
            $profileValue=floatval($canvasElement['Content']['Dynamic style']['Profile value']?:100);
            $newPropValue=round($canvasElement['Content']['Dynamic style']['Profile value']*$valueMinusMin/$range)/$profileValue;
        } else if ($profile=='threshold'){
            $profileValue=floatval($canvasElement['Content']['Dynamic style']['Profile value']?:0);
            $newPropValue=($valueMinusMin>$profileValue)?1:0;
        }
        if (strpos($canvasElement['Content']['Dynamic style']['Property'],'color')!==FALSE){
            $styleProp['value']=str_replace('{{VALUE}}',strval($newPropValue*255),self::DYNAMIC_STYLE_TEMPLATE['color']);
        }

        $styleProp['debugging']=['profile'=>$profile,'range'=>$range,'valueMinusMin'=>$valueMinusMin,];
        $styleProp['signalProps']=$signalProps;
        
        return $styleProp;
    }

    public function initProcessorResult(string $callingClass, bool|int $isTestRun=FALSE, $keepSourceEntries=0):array
    {
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $maxProcTime=($isTestRun===TRUE)?\SourcePot\Datapool\Foundation\DataExplorer::MAX_TEST_TIME:\SourcePot\Datapool\Foundation\DataExplorer::MAX_PROC_TIME;
        $maxProcTimeDisabled=(intval($keepSourceEntries)===1 && $isTestRun!==TRUE);
        $runType=($isTestRun===FALSE)?'NORMAL RUN':(($isTestRun===TRUE)?'TEST RUN':'PROCESSOR SPECIFIC RUN');
        $result=[
            'cntr'=>[
                'scriptStartTimestamp'=>hrtime(TRUE),
                'maxExecutionTime'=>$maxProcTimeDisabled?0:$maxProcTime,
                'timeLimitReached'=>FALSE,
                'incompleteRun'=>FALSE,
                'isSkipRow'=>FALSE,
            ],
            'Statistics'=>[
                'Processing time [sec]'=>['Value'=>0],
                'Processor'=>['Value'=>$callingClass],
                'Type'=>['Value'=>$runType],
                'Info'=>['Value'=>$maxProcTimeDisabled?['Execution time limit disabled (check "Keep source entries" setting)']:[]],
                'Entries touched'=>['Value'=>0],
                'Entries moved (success)'=>['Value'=>0],
                'Entries moved (failure)'=>['Value'=>0],
                'Entries skipped (skip rows)'=>['Value'=>0],
            ],
        ];
        return $result;
    }

    public function updateProcessorResult(array $result, array $entry):array
    {
        $result['Statistics']['Entries touched']['Value']++;
        $result['cntr']['timeLimitReached']=($result['cntr']['maxExecutionTime']>0 && (hrtime(TRUE)-$result['cntr']['scriptStartTimestamp'])>$result['cntr']['maxExecutionTime']);
        $result['cntr']['isSkipRow']=boolval($entry['isSkipRow']);
        if ($result['cntr']['timeLimitReached']){
            if (($entry['rowCount']-$result['Statistics']['Entries touched']['Value'])>0){
                $result['Statistics']['Info']['Value'][]='Imcomplete run ('.round($result['Statistics']['Entries touched']['Value']/$entry['rowCount']*100,2).'% processed)';
                $result['cntr']['incompleteRun']=TRUE;
            }
            $result['Statistics']['Info']['Value'][]='Time limit reached';
        }
        if ($result['cntr']['isSkipRow']){
            $result['Statistics']['Entries skipped (skip rows)']['Value']++;
        }
        return $result;
    }

    public function finalizeProcessorResult(array $result):array
    {
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix($result['Statistics']);
        $processingTimeSec=(hrtime(TRUE)-$result['cntr']['scriptStartTimestamp'])/1e+9;
        $result['Statistics']['Processing time [sec]']['Value']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($processingTimeSec,3);
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $entriesPerSec=$result['Statistics']['Entries touched']['Value']/$result['Statistics']['Processing time [sec]']['Value'];
        $result['Statistics']['Info']['Value'][]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($entriesPerSec,1).' entries per sec';
        $result['Statistics']['Info']['Value']=implode('<br/>',$result['Statistics']['Info']['Value']);
        return $result;
    }
    
}
?>