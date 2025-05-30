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
    
    private $entryTable='';
    private $entryTemplate=[];
                                 
    private $selectorTemplate=['Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'EntryId'=>FALSE];
    private $isVisibleTemplate=['Source'=>FALSE,'Group'=>TRUE,'Folder'=>TRUE,'EntryId'=>TRUE];
    private $settingsTemplate=['Source'=>['orderBy'=>'Source','isAsc'=>TRUE,'limit'=>FALSE,'offset'=>FALSE],
                                'Group'=>['orderBy'=>'Group','isAsc'=>TRUE,'limit'=>FALSE,'offset'=>FALSE],
                                'Folder'=>['orderBy'=>'Folder','isAsc'=>TRUE,'limit'=>FALSE,'offset'=>FALSE],
                                'EntryId'=>['orderBy'=>'Name','isAsc'=>TRUE,'limit'=>FALSE,'offset'=>FALSE]
                                ];
                                    
    private $addEntryByFileUpload=TRUE;

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
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    public function unifyEntry(array $entry):array
    {
        // This function makes class specific corrections before the entry is inserted or updated.
        return $entry;
    }

    public function getExplorer(string $callingClass, array $visibility=[], bool $addEntryByFileUpload=TRUE):string
    {
        $selector=$this->appProcessing($callingClass);
        $this->addEntryByFileUpload=$addEntryByFileUpload;
        // set selector visibility
        $this->isVisibleTemplate=array_merge($this->isVisibleTemplate,$visibility);
        $this->isVisibleTemplate['Source']=!empty(\SourcePot\Datapool\Root::ALLOW_SOURCE_SELECTION[$callingClass]);
        // compile html
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h1','element-content'=>'Explorer'));
        $selectorsHtml=$this->getSelectors($callingClass);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$selectorsHtml,'keep-element-content'=>TRUE));
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE,'id'=>'explorer','style'=>[]));
        return $html;
    }

    private function getSelectors(string $callingClass):string
    {
        $selectorPageState=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        $stateKeys=['selectedKey'=>key($selectorPageState),'nextKey'=>key($selectorPageState)];
        $lngNeedle='|'.$_SESSION['page state']['lngCode'].'|';
        $html='';
        $selector=[];
        foreach($this->selectorTemplate as $column=>$initValue){
            $selectorHtml='';
            $options=[\SourcePot\Datapool\Root::GUIDEINDICATOR=>'&larrhk;'];
            if ($column==='EntryId'){
                $label='Name';
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read',$this->settingsTemplate[$column]['orderBy'],$this->settingsTemplate[$column]['isAsc']) as $row){
                    if ($row[$label]===\SourcePot\Datapool\Root::GUIDEINDICATOR){continue;}
                    if (!empty(\SourcePot\Datapool\Root::USE_LANGUAGE_IN_TYPE[$selector['Source']]) && strpos($row['Type'],$lngNeedle)===FALSE){continue;}
                    $options[$row[$column]]=$row[$label];
                }
            } else {
                $label=$column;
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->getDistinct($selector,$column,FALSE,'Read','Name',$this->settingsTemplate[$column]['isAsc']) as $row){
                    if ($row[$label]===\SourcePot\Datapool\Root::GUIDEINDICATOR){continue;}
                    $options[$row[$column]]=$row[$label];
                }
            }
            $selector[$column]=(isset($selectorPageState[$column]))?$selectorPageState[$column]:$initValue;
            $selectorHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['label'=>$label,'options'=>$options,'hasSelectBtn'=>TRUE,'key'=>['selector',$column],'value'=>$selector[$column],'keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'explorer']);
            $selectorHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','class'=>'explorer','element-content'=>$selectorHtml,'keep-element-content'=>TRUE]);
            if (!empty($this->isVisibleTemplate[$column])){
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
            $arr=$this->setRightsEntry($callingClass,$stateKeys,'Read');
            $appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
            $arr=$this->setRightsEntry($callingClass,$stateKeys,'Write');
            $appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
            $commentsArr=$this->comments($callingClass,$stateKeys);
            $appHtml.=$commentsArr['html'];
        }
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$appHtml,'keep-element-content'=>TRUE,'style'=>['float'=>'left','clear'=>'both','padding'=>'5px','margin'=>'0.5em']]);
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
        $unseledtedDetected=FALSE;
        $write=(empty($selector['Group']))?'ALL_MEMBER_R':'ALL_CONTENTADMIN_R'; // if no Group is set, all memebers can add a Group else only the owner and content admin can write a Group, Folder, Name 
        $entry=['Name'=>\SourcePot\Datapool\Root::GUIDEINDICATOR,'Owner'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),'Read'=>'ALL_MEMBER_R','Write'=>$write];
        foreach($this->selectorTemplate as $column=>$initValue){
            if (empty($selector[$column])){$unseledtedDetected=TRUE;}
            $entry[$column]=($unseledtedDetected)?\SourcePot\Datapool\Root::GUIDEINDICATOR:$selector[$column];
        }
        $entry=array_merge($template,$entry);
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder'),'0',\SourcePot\Datapool\Root::GUIDEINDICATOR,FALSE);
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
            $entry=array_merge($entry,$this->selectorTemplate);
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
        if (isset($selector['File upload extract archive'])){$selectorSettings['File upload extract email parts']=$selector['File upload extract archive'];}
        if (isset($selector['File upload extract archive'])){$selectorSettings['File upload extract archive']=$selector['File upload extract archive'];}
        if (isset($selector['pdf-file parser'])){$selectorSettings['pdf-file parser']=$selector['pdf-file parser'];}
        //
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($selector,array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE));
        $guideEntry=$this->getGuideEntry($selector);
        $pdfParser=$this->oc['SourcePot\Datapool\Tools\PdfTools']->getPdfTextParserOptions();
        $initSettings=['File upload extract email parts'=>1,
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
            $newSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($newSelector,array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE));
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
        $access=TRUE;
        $arr=['html'=>'','icon'=>'&#10010;','title'=>'Add new "'.$stateKeys['selectedKey'].'"','class'=>'explorer'];
        if (strcmp($stateKeys['nextKey'],'Source')===0 || !$this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE)){
            return array('html'=>'','icon'=>'&#10010;','class'=>'explorer');
        } else {
            $btnId=md5(__FUNCTION__);
            $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h3','element-content'=>'Add'));
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
        if (strcmp($stateKeys['selectedKey'],'Source')===0 || !$this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE)){
            return array('html'=>'','icon'=>'&#9998;','class'=>'explorer');
        }
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h3','element-content'=>'Edit'));
        if (strcmp($stateKeys['selectedKey'],'EntryId')===0){
            $selector=['Source'=>$selector['Source'],'EntryId'=>$selector['EntryId']];
            if (!empty($entry)){$html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry editor','entryEditor',$entry,[],[]);}
        } else {
            $fileElement=['tag'=>'input','type'=>'text','value'=>$selector[$stateKeys['selectedKey']],'key'=>[$stateKeys['selectedKey']],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>['clear'=>'left']];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileElement);
            $addBtn=['tag'=>'button','element-content'=>'Edit '.$stateKeys['selectedKey'],'key'=>['edit'],'value'=>$stateKeys['selectedKey'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>[]];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($addBtn);
        }
        $arr=['html'=>$html,'icon'=>'&#9998;','title'=>'Edit selected "'.$stateKeys['selectedKey'].'"','class'=>'explorer'];
        return $arr;
    }
    
    private function miscToolsEntry(string $callingClass,array $stateKeys):array
    {
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
        $arr=['html'=>'','callingClass'=>$callingClass,'callingFunction'=>__FUNCTION__,'icon'=>'@','title'=>'Send entry as email','class'=>'explorer'];
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        if (!empty($arr['selector']['EntryId'])){
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Send entry','sendEntry',$arr['selector'],[],[]);
        }
        return $arr;
    } 
    
    private function comments(string $callingClass,array $setKeys):array
    {
        $arr=['html'=>''];
        if (strcmp($setKeys['selectedKey'],'EntryId')!==0){
            $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h3','element-content'=>'Misc tools'));
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
            $arr=['selector'=>$this->getGuideEntry($selector),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'comment'];
            $arr=$this->oc['SourcePot\Datapool\Foundation\Container']->comments($arr);
        }
        return $arr;
    }
    
    private function setRightsEntry(string $callingClass,array $stateKeys,string $right):array
    {
        $icon=ucfirst($right);
        $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
        if (strcmp($stateKeys['selectedKey'],'Source')===0){
            // Source level
            return array('html'=>'','icon'=>$icon[0],'class'=>'explorer');
        }
        // check if there are any entries with write access
        $hasWritableEntries=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($selector,FALSE,'Write',FALSE);
        if (empty($hasWritableEntries)){
            // no entries with write access found
            return ['html'=>'','icon'=>$icon[0],'class'=>'explorer'];
        }
        // create html
        $entry=$this->getGuideEntry($selector);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->integerEditor(array('selector'=>$entry,'key'=>$right));
        $arr=['html'=>$html,'icon'=>$icon[0],'title'=>'Setting "'.$right.'" access right','class'=>'explorer'];
        return $arr;
    }
    
    public function selector2linkInfo(string $app, array $selector):array
    {
        $classInfo=$this->oc[$app]->run(TRUE);
        $linkInfo=array_merge($classInfo,$selector);
        $linkInfo['linkid']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($linkInfo,TRUE);
        $_SESSION['page state']['linkids'][$linkInfo['linkid']]=$linkInfo;
        $linkInfo['href']='index.php?'.http_build_query(array('category'=>$linkInfo['Category'],'linkid'=>$linkInfo['linkid']));
        $linkInfo['tag']='a';
        $linkInfo['keep-element-content']=TRUE;
        return $linkInfo;
    }
    
}
?>