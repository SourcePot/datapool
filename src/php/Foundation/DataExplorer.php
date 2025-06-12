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

class DataExplorer{

    private const ROW_COUNT_LIMIT=FALSE;
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                                 'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                                 ];
    
    public $definition=['Content'=>['Style'=>['Text'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                                                        'Style class'=>['@function'=>'select','@options'=>['canvas-std'=>'Standard','canvas-red'=>'Error','canvas-green'=>'Data interface','canvas-dark'=>'Other canvas','canvas-text'=>'Text','canvas-symbol'=>'Symbol','canvas-processor'=>'Processor'],'@default'=>'canvas-std'],
                                                        'top'=>['@tag'=>'input','@type'=>'Text','@default'=>'0px'],
                                                        'left'=>['@tag'=>'input','@type'=>'Text','@default'=>'0px'],
                                                        ],
                                            'Selector'=>['Source'=>['@function'=>'select','@options'=>[]],
                                                        'Group'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                                                        'Folder'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                                                        'Name'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                                                        'EntryId'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                                                        'Type'=>['@tag'=>'input','@type'=>'Text','@default'=>''],
                                                        ],
                                             'Widgets'=>[
                                                        'Processor'=>['@function'=>'processorSelector','@class'=>__CLASS__],
                                                        'File upload'=>['@function'=>'select','@options'=>['No','Yes'],'@default'=>0],
                                                        'File upload extract archive'=>['@function'=>'select','@options'=>['No','Yes'],'@default'=>0],
                                                        'File upload extract email parts'=>['@function'=>'select','@options'=>['No','Yes'],'@default'=>0],
                                                        'pdf-file parser'=>['@function'=>'select','@options'=>[],'@default'=>0],
                                                        'Delete selected entries'=>['@function'=>'select','@options'=>['No','Yes'],'@default'=>1],
                                                        ],
                                              ],
                            ];
    
    private $tags=['run'=>['tag'=>'button','element-content'=>'&#10006;','keep-element-content'=>TRUE,'style'=>['font-size'=>'24px','color'=>'#fff;','background-color'=>'#0a0'],'showEditMode'=>TRUE,'type'=>'Control','Read'=>'ALL_CONTENTADMIN_R','title'=>'Close canvas editor'],
                        'edit'=>['tag'=>'button','element-content'=>'&#9998;','keep-element-content'=>TRUE,'style'=>['font-size'=>'24px','color'=>'#fff','background-color'=>'#a00'],'showEditMode'=>FALSE,'type'=>'Control','Read'=>'ALL_CONTENTADMIN_R','title'=>'Edit canvas'],
                        '&#9881;'=>['tag'=>'button','element-content'=>'&#9881;','keep-element-content'=>TRUE,'class'=>'canvas-processor','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R','title'=>'Step processing'],
                        'Select'=>['tag'=>'button','element-content'=>'Select','keep-element-content'=>TRUE,'class'=>'canvas-std','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R','title'=>'Database view'],
                        'Text'=>['tag'=>'div','element-content'=>'Text','keep-element-content'=>TRUE,'class'=>'canvas-text','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R','title'=>'Text box'],
                        ];
    
    private $graphicElemnts=['Connectors'=>['&xlarr;','&xrarr;','&xharr;','&larr;','&uarr;','&rarr;','&darr;','&harr;','&varr;','&nwarr;','&nearr;','&searr;','&swarr;','&larrhk;','&rarrhk;','&#8634;','&#8635;','&duarr;','&#10140;','&#8672;','&#8673;','&#8674;','&#8675;'],
                                  'Symbols'=>['&VerticalSeparator;','&#8285;','&#8286;','','','&sung;','&hearts;','&diams;','&clubs;','&sharp;','&#9850;','&#9873;','&#9888;','&#9885;','&#9986;','&#9992;','&#9993;','&#9998;','&#10004;','&#x2718;','&#10010;','&#10065;','&#10070;'],
                                  'Math'=>['&empty;','&nabla;','&nexist;','&ni;','&isin;','&notin;','&sum;','&prod;','&coprod;','&compfn;','&radic;','&prop;','&infin;','&angrt;','&angmsd;','&cap;','&int;','&asymp;','&Lt;','&Gt;','&Ll;','&Gg;','&equiv;'],
                                  ];

    private $processorOptions=[];
    
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
        foreach($this->graphicElemnts as $category=>$htmlEntities){
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
    
    public function job($vars){
        // create signals from canvas elements
        $selector=['Source'=>$this->entryTable,'Group'=>'Canvas elements'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $canvasElement){
            if (empty($canvasElement['Content']['Selector']['Source'])){continue;}
            $value=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($canvasElement['Content']['Selector'],TRUE);
            $signal=$this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal($canvasElement['Folder'],'DataExplorer',$canvasElement['Content']['Style']['Text'],$value);
        }
        // cleanup
        if (isset($signal['Date'])){
            $toDeleteSelector=$this->oc['SourcePot\Datapool\Foundation\Signals']->getSignalSelector('%','DataExplorer');
            $toDeleteSelector['Date<']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime($signal['Date'],'-P1D');
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($toDeleteSelector,TRUE);
        }
        return $vars;
    }
    
    private function completeDefintion():void
    {
        // add Source selector
        $sourceOptions=[''=>'&larrhk;'];
        $dbInfo=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate(FALSE);
        foreach($dbInfo as $Source=>$entryTemplate){$sourceOptions[$Source]=$Source;}
        $this->definition['Content']['Selector']['Source']['@options']=$sourceOptions;
        $this->definition['Content']['Widgets']['pdf-file parser']=$this->oc['SourcePot\Datapool\Tools\PdfTools']->getPdfTextParserOptions();
        // add save button
        $this->definition['save']=['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'];
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
    }

    /**
    * Class specific entry normalization.
    *
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
                $entry['Content']['Selector']['Source']=$this->oc[$entry['Folder']]->getEntryTable();
                $entry['Content']['Widgets']['Processor']='SourcePot\Datapool\Processing\CanvasProcessing';
            }
            if (mb_strpos($entry['element-content'],'&#128337;')!==FALSE){
                $entry['Content']['Selector']['Source']=$this->oc[$entry['Folder']]->getEntryTable();
                $entry['Content']['Widgets']['Processor']='SourcePot\Datapool\Processing\CanvasTrigger';
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
    *
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
    *
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
        }
        if (isset($formData['cmd']['select'])){
            $canvasElement=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'selectedCanvasElement',$canvasElement);
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
    *
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
            if (!isset($matrix[$tag['type']]['Btn'])){$matrix[$tag['type']]['Btn']='';}
            $matrix[$tag['type']]['Btn'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btn);
        }
        $htmlArr=['cntr'=>'','processor'=>''];
        $tableArr=['matrix'=>$matrix,'keep-element-content'=>TRUE,'hideHeader'=>TRUE,'caption'=>'Canvas'];
        if ($isEditMode){$tableArr['style']=['min-width'=>'550px'];}
        $htmlArr['cntr']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table($tableArr);
        $selectedCanvasElement=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'selectedCanvasElement');
        $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selectedCanvasElement);
        if ($isEditMode){
            if ($canvasElement){
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
    *
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
        return $html;
    }
    
    /**
    * Creates canvas element html.
    *
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
                $style['box-shadow']='3px 3px 5px 1px #f009';
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
            $element['source']=$canvasElement['Source'];
            $element['entry-id']=$canvasElement['EntryId'];
            $style['cursor']='pointer';
        } else {
            if (empty($canvasElement['Content']['Selector']['Source'])){
                if ($canvasElement['Content']['Style']['Style class']!=='canvas-text' && $canvasElement['Content']['Style']['Style class']!=='canvas-symbol'){
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
        }
        // canvas element
        if ($rowCount!==FALSE && strcmp($canvasElement['Content']['Style']['Text'],'&#9881;')!==0 && strcmp($canvasElement['Content']['Style']['Text'],'&#128337;')!==0){
            $elmentInfo=['tag'=>'p','class'=>'canvas-info','element-content'=>'('.$rowCount.')'];
            $text.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elmentInfo);
        }
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
    *
    * @param string $callingClass    The calling method's class-name
    * @param string $callingFunction The calling method's name
    * @param array  $callingElement  Is the canvas element from which the template is derived
    * @param array  $settings        Initial settings
    *
    * @return array  Canvas element settings
    */
    public function callingElement2settings(string $callingClass,string $callingFunction,array $callingElement,array $settings=[]):array
    {
        $settings['Script start timestamp']=hrtime(TRUE);
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $settings['callingElement']=$callingElement['Content']??[];
        $settings['entryTemplates']['__BLACKHOLE__']=['Source'=>'blackhole','__BLACKHOLE__'=>'__BLACKHOLE__'];
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
    *
    * @param string    $callingClass       The calling method's class-name
    * @param string    $callingFunction    The calling method's name
    * @param array     $callingElement     Is the canvas element from which the template is derived
    *
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
        $arr=['callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'selector'=>$entry];
        return $arr;
    }
    
    /**
    * Canvas element selector from calling class.
    *
    * @param string    $callingClass       The calling method's class-name
    * @return array    Selector
    */
    public function canvasSelector(string $callingClass):array
    {
        $selector=['Source'=>$this->entryTable,'Group'=>'Canvas elements','Folder'=>$callingClass];
        return $selector;
    }

    /**
    * Canvas elements from calling class.
    *
    * @param string    $callingClass    The calling method's class-name
    * @param string    $EntryId         If empty all relevant canvas elements will be returned, else the selected canvas element 
    * @return array    Canvas elements
    */
    public function getCanvasElements(string $callingClass, string $EntryId=''):array
    {
        // This method is called by HTMLbuilder to provide a canvas elements selector.
        // It returns the canvas elements in order by their position.
        $elements=[];
        $selector=$this->canvasSelector($callingClass);
        if (empty($EntryId)){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector) as $entry){
                if (empty($entry['Content']['Style']['Text'])){continue;}
                if (strcmp($entry['Content']['Style']['Text'],'&#9881;')===0 || strcmp($entry['Content']['Style']['Text'],'&#128337;')===0){continue;}
                $elements[$entry['Content']['Style']['Text']]=$entry;
            }
            ksort($elements);
        } else {
            $selector['EntryId']=$EntryId;
            if ($entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector)){
                $elements[$EntryId]=$entry; 
            }
        }
        return $elements;
    }
    
    /**
    * Selector contained within a canvas element which is selected by EntryId.
    *
    * @param string    $entryId Is the EntryId
    * @return array    Selector or an empty array
    */
    public function entryId2selector(string $entryId):array
    {
        $selector=['Source'=>$this->entryTable,'EntryId'=>$entryId];
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,TRUE);
        if (isset($entry['Content']['Selector'])){
            $selector=[];
            foreach($entry['Content']['Selector'] as $key=>$value){
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
    *
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
    *
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
    *
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
        foreach($this->oc['SourcePot\Datapool\Root']->getRegisteredMethods('dataProcessor') as $classWithNamespace=>$ret){
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
                foreach($selectors as $index=>$selector){$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector);}
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

}
?>