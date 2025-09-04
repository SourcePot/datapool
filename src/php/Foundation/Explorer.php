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

class Explorer{
    
    private $oc;
    private $isVisible=[];
    
    private const SELECTOR_KEY_DATA=[
        'Source'=>[
            'addTitle'=>'Add new Group',
            'editTitle'=>'Edit selected Source',
            ],
        'Group'=>[
            'addTitle'=>'Add new Folder',
            'editTitle'=>'Edit selected Group',
            ],
        'Folder'=>[
            'addTitle'=>'Add new Entry/Entries',
            'editTitle'=>'Edit selected Folder',
            ],
        'EntryId'=>[
            'addTitle'=>'Update Entry',
            'editTitle'=>'Edit selected Entry',
            ],
        ];
                                 
    private const SELECTOR_TEMPLATE=[
        'Source'=>FALSE,
        'Group'=>FALSE,
        'Folder'=>FALSE,
        'EntryId'=>FALSE
        ];
    private const IS_VISIBLE_TEMPLATE=[
        'Source'=>FALSE,
        'Group'=>TRUE,
        'Folder'=>TRUE,
        'EntryId'=>TRUE,
        'addEntry'=>TRUE,
        'editEntry'=>TRUE,
        'miscToolsEntry'=>TRUE,
        'settingsEntry'=>TRUE,
        'sendEmail'=>TRUE,
        'accessInfo'=>TRUE,
        'setRightsEntry'=>TRUE,
        'comments'=>TRUE,
        ];
    private const SETTINGS_TEMPLATE=[
        'Source'=>[
            'orderBy'=>'Source',
            'isAsc'=>FALSE,
            'limit'=>FALSE,
            'offset'=>FALSE
            ],
        'Group'=>[
            'orderBy'=>'Group',
            'isAsc'=>FALSE,
            'limit'=>FALSE,
            'offset'=>FALSE
            ],
        'Folder'=>[
            'orderBy'=>'Folder',
            'isAsc'=>FALSE,
            'limit'=>FALSE,
            'offset'=>FALSE
            ],
        'EntryId'=>[
            'orderBy'=>'Name',
            'isAsc'=>FALSE,
            'limit'=>FALSE,
            'offset'=>FALSE
            ]
        ];

    private $addEntryByFileUpload=TRUE;

    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function unifyEntry(array $entry):array
    {
        // This function makes class specific corrections before the entry is inserted or updated.
        return $entry;
    }

    public function getExplorer(string $callingClass, array $visibility=[], bool $addEntryByFileUpload=TRUE):string
    {
        $this->appProcessing($callingClass);
        $this->addEntryByFileUpload=$addEntryByFileUpload;
        // set selector visibility
        $this->isVisible=array_merge(self::IS_VISIBLE_TEMPLATE,$visibility);
        $this->isVisible['Source']=!empty(\SourcePot\Datapool\Root::ALLOW_SOURCE_SELECTION[$callingClass]);
        // compile html
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h1','element-content'=>'Explorer']);
        $selectorsHtml=$this->getSelectors($callingClass);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$selectorsHtml,'keep-element-content'=>TRUE]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE,'id'=>'explorer','style'=>[]]);
        return $html;
    }

    private function getSelectors(string $callingClass):string
    {
        $selectorPageState=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        $stateKeys=['selectedKey'=>key($selectorPageState),'nextKey'=>key($selectorPageState)];
        $lngNeedle='|'.$_SESSION['page state']['lngCode'].'|';
        $html='';
        $selector=[];
        foreach(self::SELECTOR_TEMPLATE as $column=>$initValue){
            $selectorHtml='';
            $options=[\SourcePot\Datapool\Root::GUIDEINDICATOR=>'&larrhk;'];
            if ($column==='EntryId'){
                $label='Name';
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read',self::SETTINGS_TEMPLATE[$column]['orderBy'],self::SETTINGS_TEMPLATE[$column]['isAsc']) as $row){
                    if ($row[$label]===\SourcePot\Datapool\Root::GUIDEINDICATOR){continue;}
                    if (!empty(\SourcePot\Datapool\Root::USE_LANGUAGE_IN_TYPE[$selector['Source']]) && strpos($row['Type'],$lngNeedle)===FALSE){continue;}
                    $options[$row[$column]]=$row[$label];
                }
            } else {
                $label=$column;
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->getDistinct($selector,$column,FALSE,'Read','Name',self::SETTINGS_TEMPLATE[$column]['isAsc']) as $row){
                    if ($row[$label]===\SourcePot\Datapool\Root::GUIDEINDICATOR){continue;}
                    $options[$row[$column]]=$row[$label];
                }
            }
            $selector[$column]=(isset($selectorPageState[$column]))?$selectorPageState[$column]:$initValue;
            $selectorHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['label'=>$label,'options'=>$options,'hasSelectBtn'=>TRUE,'key'=>['selector',$column],'value'=>$selector[$column],'keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'explorer']);
            $selectorHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','class'=>'explorer','element-content'=>$selectorHtml,'keep-element-content'=>TRUE]);
            if (!empty($this->isVisible[$column])){
                // Source-selector is added based on visibility setting
                $html.=$selectorHtml;
            }
            $stateKeys['nextKey']=$column;
            if ($selector[$column]===FALSE){break;} else {$stateKeys['selectedKey']=$column;}
        }
        $html.=$this->addApps($callingClass,$stateKeys);
        return $html;
    }

    private function addApps(string $callingClass,array $stateKeys):string
    {
        $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
        if (empty($entry)){
            $entry=$this->getGuideEntry($selector);
        }
        $html='';
        $arr=$this->addEntry($callingClass,$stateKeys,$selector,$entry);
        $appHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
        $arr=$this->editEntry($callingClass,$stateKeys,$selector,$entry);
        $appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
        $arr=$this->miscToolsEntry($callingClass,$stateKeys);
        $appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
        $arr=$this->settingsEntry($callingClass,$stateKeys);
        $appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isMember()){
            $arr=$this->sendEmail($callingClass,$stateKeys);
            $appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);            
            $arr=$this->accessInfo($callingClass,$stateKeys);
            $appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
            $arr=$this->setRightsEntry($callingClass,$stateKeys,'Read');
            $appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
            $arr=$this->setRightsEntry($callingClass,$stateKeys,'Write');
            $appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
            $commentsArr=$this->comments($callingClass,$stateKeys);
            $appHtml.=$commentsArr['html'];
        }
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$appHtml,'keep-element-content'=>TRUE,'class'=>'explorer','style'=>['clear'=>'both']]);
        return $html;
    }
    
    public function getGuideEntry(array $selector,array $template=[]):array|bool
    {
        if (empty($selector['Source'])){
            // selector is insufficient, return selector
            return $selector;
        } else if (!empty($selector['EntryId'])){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
            if (empty($entry)){
                // guide entry not found, maybe missing access rights, return selector
                return $selector;
            } else {
                // return selected guide entry
                return $entry;
            }
        }
        // create new guide entry, if it does not exist
        $currentUser=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $readR=intval($currentUser['Privileges']) | $this->oc['SourcePot\Datapool\Foundation\Access']->getAccessOptions()['ALL_CONTENTADMIN_R'];
        $writeR=$this->oc['SourcePot\Datapool\Foundation\Access']->getAccessOptions()['ALL_CONTENTADMIN_R'];
        $entry=['Name'=>\SourcePot\Datapool\Root::GUIDEINDICATOR,'Owner'=>$currentUser['EntryId'],'Read'=>$readR,'Write'=>$writeR];
        $unseledtedDetected=FALSE;
        foreach(self::SELECTOR_TEMPLATE as $column=>$initValue){
            if (empty($selector[$column])){$unseledtedDetected=TRUE;}
            $entry[$column]=($unseledtedDetected)?\SourcePot\Datapool\Root::GUIDEINDICATOR:$selector[$column];
        }
        $entry=array_merge($template,$entry);
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder'],'0',\SourcePot\Datapool\Root::GUIDEINDICATOR,FALSE);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Read');
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Write');
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,TRUE);
        return $entry;
    }
    
    private function updateGuideEntries(array $oldSelector,array $newSelector):array
    {
        $oldSelector['Type']=\SourcePot\Datapool\Root::GUIDEINDICATOR.'%';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($oldSelector,FALSE,'Write',FALSE,TRUE,FALSE,FALSE,[],FALSE) as $entry){
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($entry);
            $entry=array_merge($entry,self::SELECTOR_TEMPLATE);
            $guideEntry=$this->getGuideEntry($newSelector,$entry);
        }
        return $newSelector;
    }
    
    private function addGuideEntry2selector(array $selector,array $guideEntry):array
    {
        $guideTemplate=[];
        if (isset($guideEntry['Content']['settings'])){$guideTemplate=$guideEntry['Content']['settings'];}
        if (isset($guideEntry['Read'])){$guideTemplate['Read']=$guideEntry['Read'];}
        if (isset($guideEntry['Write'])){$guideTemplate['Write']=$guideEntry['Write'];}
        $selector=array_merge($guideTemplate,$selector);
        return $selector;
    }

    public function selector2setting(array $selector, string $key='')
    {
        $selectorSettings=[];
        if (isset($selector['File upload extract archive'])){
            $selectorSettings['File upload extract email parts']=$selector['File upload extract archive'];
        }
        if (isset($selector['File upload extract archive'])){
            $selectorSettings['File upload extract archive']=$selector['File upload extract archive'];
        }
        if (isset($selector['pdf-file parser'])){
            $selectorSettings['pdf-file parser']=$selector['pdf-file parser'];
        }
        //
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($selector,['Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE]);
        $guideEntry=$this->getGuideEntry($selector);
        $pdfParser=$this->oc['SourcePot\Datapool\Tools\PdfTools']->getPdfTextParserOptions();
        $initSettings=[
            'File upload extract email parts'=>1,
            'File upload extract archive'=>0,
            'pdf-file parser'=>$pdfParser['@default'],
            'widget'=>($selector['Source']=='documents')?'entryList':'entryByEntry'
            ];
        $setting=array_merge($initSettings,$guideEntry['Content']['settings']??[],$selectorSettings);
        if (empty($key)){
            return $setting;
        } else {
            return $setting[$key]??FALSE;
        }
    }
    
    public function appProcessing(string $callingClass):array
    {
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        // process selectors
        $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        //$guideEntry=$this->selector2guideEntry($selector);
        $guideEntry=$this->getGuideEntry($selector);
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'getSelectors');
        if (isset($formData['cmd']['select'])){
            $resetFromHere=FALSE;
            foreach($formData['val']['selector'] as $column=>$selected){
                if (strcmp($selected,\SourcePot\Datapool\Root::GUIDEINDICATOR)===0){$resetFromHere=TRUE;}
                $newSelector[$column]=$resetFromHere?FALSE:$selected;
                if (isset($selector[$column])){
                    if ($newSelector[$column]!=$selector[$column]){$resetFromHere=TRUE;}
                }
            }
            $newSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($newSelector,['Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE]);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($callingClass,$newSelector);
        }
        $selector=$this->addGuideEntry2selector($selector,$guideEntry);
        // add entry app
        $selector['Params']['uploadApp']=$callingClass;
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'addEntry');
        if (isset($formData['cmd']['add']) && !empty($formData['val'][$formData['cmd']['add']])){
            $selector=array_merge($selector,$formData['val']);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($callingClass,$selector);
            $guideEntry=$this->getGuideEntry($selector);
        } else if (isset($formData['cmd']['add entry'])){
            $entry=$selector;
            $entry['Name']=$formData['val']['add entry'];
            $selector=$this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($entry);
            $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($callingClass,$selector);
        } else if (isset($formData['cmd']['add files'])){
            if ($formData['hasValidFiles']){
                foreach($formData['files']['add files_'] as $fileIndex=>$fileArr){
                    if ($fileArr['error']){continue;}
                    $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($fileArr,$selector);
                }
            }        
        } else if (isset($formData['cmd']['update file'])){
            if ($formData['hasValidFiles']){
                foreach($formData['files']['update file_'] as $fileIndex=>$fileArr){
                    if ($fileArr['error']){continue;}
                    $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($fileArr,$selector);
                }
            }    
        }
        $this->oc['SourcePot\Datapool\Tools\MiscTools']->formData2statisticlog($formData);
        // editEntry
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'editEntry');
        if (isset($formData['cmd']['edit'])){
            $newSelector=array_merge($selector,$formData['val']);
            $newSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($newSelector);
            // update guide entries and all other entries
            $this->updateGuideEntries($selector,$newSelector);
            $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($selector);
            $newSelector=$this->oc['SourcePot\Datapool\Root']->substituteWithPlaceholder($newSelector);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntries($selector,$newSelector);
            // set new page state to new selector
            $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($callingClass,$newSelector);
        }
        $this->oc['SourcePot\Datapool\Tools\MiscTools']->formData2statisticlog($formData);
        return $selector;
    }
    
    private function addEntry(string $callingClass,array $stateKeys,array $selector,array $entry):array
    {
        if (empty($this->isVisible[__FUNCTION__])){return ['html'=>''];}
        $access=TRUE;
        $arr=['html'=>'','icon'=>'&#10010;','title'=>self::SELECTOR_KEY_DATA[$stateKeys['selectedKey']]['addTitle'],'class'=>'explorer'];
        if (strcmp($stateKeys['nextKey'],'Source')===0 || !$this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE)){
            return ['html'=>'','icon'=>'&#10010;','class'=>'explorer'];
        } else {
            $btnId=md5(__FUNCTION__);
            $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h3','element-content'=>'Add']);
            if (strcmp($stateKeys['selectedKey'],'Folder')===0){
                if ($this->addEntryByFileUpload){
                    $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->fileUpload(['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>['add files'],'element-content'=>'Add file(s)'],['formProcessingClass'=>__CLASS__,'formProcessingFunction'=>'appProcessing','formProcessingArg'=>$callingClass]);
                } else {
                    $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','key'=>['add entry'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>['clear'=>'left']]);
                    $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'Add entry','key'=>['add entry'],'value'=>$stateKeys['nextKey'],'id'=>$btnId,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>[]]);
                }
            } else if (strcmp($stateKeys['selectedKey'],'EntryId')===0 && $this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE)){
                if ($this->addEntryByFileUpload){
                    $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->fileUpload(['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>['update file'],'element-content'=>'Update entry file'],['formProcessingClass'=>__CLASS__,'formProcessingFunction'=>'appProcessing','formProcessingArg'=>$callingClass]);
                } else {
                    $arr['html']='';
                }
            } else {
                $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','placeholder'=>'e.g. Documents','key'=>[$stateKeys['nextKey']],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>['clear'=>'left']]);
                $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'Add '.$stateKeys['nextKey'],'key'=>['add'],'value'=>$stateKeys['nextKey'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>[]]);
            }
            if (empty($access)){$arr['html']='';}
        }
        return $arr;
    }

    private function editEntry(string $callingClass,array $stateKeys,array $selector,array $entry):array
    {
        if (empty($this->isVisible[__FUNCTION__])){return ['html'=>''];}
        if (strcmp($stateKeys['selectedKey'],'Source')===0 || !$this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE)){
            return ['html'=>'','icon'=>'&#9998;','class'=>'explorer'];
        }
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h3','element-content'=>'Edit']);
        if (strcmp($stateKeys['selectedKey'],'EntryId')===0){
            $selector=['Source'=>$selector['Source'],'EntryId'=>$selector['EntryId']];
            if (!empty($entry)){$html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry editor','entryEditor',$entry,['hideEntryControls'=>TRUE],[]);}
        } else {
            $fileElement=['tag'=>'input','type'=>'text','value'=>$selector[$stateKeys['selectedKey']],'key'=>[$stateKeys['selectedKey']],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>['clear'=>'left']];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileElement);
            $addBtn=['tag'=>'button','element-content'=>'Edit '.$stateKeys['selectedKey'],'key'=>['edit'],'value'=>$stateKeys['selectedKey'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>[]];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($addBtn);
        }
        $arr=['html'=>$html,'icon'=>'&#9998;','title'=>self::SELECTOR_KEY_DATA[$stateKeys['selectedKey']]['editTitle'],'class'=>'explorer'];
        return $arr;
    }
    
    private function miscToolsEntry(string $callingClass,array $stateKeys):array
    {
        if (empty($this->isVisible[__FUNCTION__])){return ['html'=>''];}
        $html=$btnHtml='';
        $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        $guideEntry=$this->getGuideEntry($selector);
        $btnArr=['selector'=>$guideEntry];
        foreach(array('download all','print','export','delete') as $cmd){
            $btnArr['cmd']=$cmd;
            $btnHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
        }
        if (!empty($btnHtml)){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h3','element-content'=>'Misc tools']);
            $wrapperElement=['tag'=>'div','element-content'=>$btnHtml,'keep-element-content'=>TRUE,'style'=>['clear'=>'both']];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($wrapperElement);
        }
        $arr=['html'=>$html,'icon'=>'...','title'=>'Misc tools, e.g. entry deletion and download','class'=>'explorer'];
        return $arr;
    }

    private function settingsEntry(string $callingClass,array $stateKeys):array
    {
        if (empty($this->isVisible[__FUNCTION__])){return ['html'=>''];}
        $html='';
        $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        $guideEntry=$this->getGuideEntry($selector);
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($guideEntry,'Write',FALSE)){
            $pdfParser=$this->oc['SourcePot\Datapool\Tools\PdfTools']->getPdfTextParserOptions();
            $options=['File upload extract email parts'=>['No','Yes'],'File upload extract archive'=>['No','Yes'],'pdf-file parser'=>$pdfParser['@options'],'widget'=>['entryList'=>'Entry list','entryByEntry'=>'Entry by entry']];
            $settings=$this->selector2setting($selector);
            // form processing
            $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
            if (isset($formData['cmd']['save'])){
                $guideEntry['Content']['settings']=$settings=$formData['val'];
                $guideEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($guideEntry);
            }
            // compile html: upload and presentation settings
            $matrix=[];
            $arr=['selector'=>$guideEntry,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
            foreach($settings as $key=>$setting){
                $arr['options']=$options[$key];
                $arr['value']=$setting;
                $arr['key']=[$key];
                $matrix[$key]=['value'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($arr)];
            }
            $matrix['']=['value'=>['tag'=>'button','key'=>['save'],'element-content'=>'Save','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]];
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Upload settings']);
        }
        $arr=['html'=>$html,'icon'=>'#','title'=>'Settings','class'=>'explorer'];
        return $arr;
    }
    
    private function sendEmail(string $callingClass,array $setKeys):array
    {
        if (empty($this->isVisible[__FUNCTION__])){return ['html'=>''];}
        $arr=['html'=>'','callingClass'=>$callingClass,'callingFunction'=>__FUNCTION__,'icon'=>'@','title'=>'Send entry as email','class'=>'explorer'];
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        if (!empty($arr['selector']['EntryId'])){
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Send entry','sendEntry',$arr['selector'],[],[]);
        }
        return $arr;
    } 
    
    private function comments(string $callingClass,array $setKeys):array
    {
        if (empty($this->isVisible[__FUNCTION__])){return ['html'=>''];}
        $arr=['html'=>''];
        if (strcmp($setKeys['selectedKey'],'EntryId')!==0){
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
            $arr=['selector'=>$this->getGuideEntry($selector),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'comment'];
            $arr=$this->oc['SourcePot\Datapool\Foundation\Container']->comments($arr);
        }
        return $arr;
    }
    
    private function setRightsEntry(string $callingClass,array $stateKeys,string $right):array
    {
        if (empty($this->isVisible[__FUNCTION__])){return ['html'=>''];}
        $icon=ucfirst($right);
        $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        if (strcmp($stateKeys['selectedKey'],'Source')===0){
            // Source level
            return ['html'=>'','icon'=>$icon[0],'class'=>'explorer'];
        }
        // check if there are any entries with write access
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($selector);
        $hasWritableEntries=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($selector,FALSE,'Write',FALSE);
        if (empty($hasWritableEntries)){
            // no entries with write access found
            return ['html'=>'','icon'=>$icon[0],'class'=>'explorer'];
        }
        // create html
        $entry=$this->getGuideEntry($selector);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->integerEditor(['selector'=>$entry,'key'=>$right]);
        $arr=['html'=>$html,'icon'=>$icon[0],'title'=>'Setting "'.$right.'" access right','class'=>'explorer'];
        return $arr;
    }

    private function accessInfo(string $callingClass,array $stateKeys):array
    {
        if (empty($this->isVisible[__FUNCTION__])){return ['html'=>''];}
        $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($selector);
        if (empty($selector['EntryId'])){return ['html'=>''];}
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($selector,TRUE);
        // create html
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $owner=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($entry['Owner'],1);
        $readAccess=$this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Read');
        $writeAccess=$this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write');
        $userRols=$this->oc['SourcePot\Datapool\Foundation\Access']->rightsHtml(['selector'=>$user],'Privileges');
        $readRols=$this->oc['SourcePot\Datapool\Foundation\Access']->rightsHtml(['selector'=>$entry],'Read');
        $writeRols=$this->oc['SourcePot\Datapool\Foundation\Access']->rightsHtml(['selector'=>$entry],'Write');
        $matrix=[];
        $matrix['Read access']=['Your rols'=>$userRols,'Entry access for'=>$readRols,'Owner'=>$owner,'Access granted'=>(empty($readAccess)?'FALSE':$readAccess)];
        $matrix['Write access']=['Your rols'=>$userRols,'Entry access for'=>$writeRols,'Owner'=>$owner,'Access granted'=>(empty($writeAccess)?'FALSE':$writeAccess)];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Access infos']);
        $arr=['html'=>$html,'icon'=>'i','title'=>'Info','class'=>'explorer'];
        return $arr;
    }

}
?>