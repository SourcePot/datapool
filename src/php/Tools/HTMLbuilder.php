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

class HTMLbuilder{
    
    private $oc;
    
    private $btns=array('test'=>array('key'=>array('test'),'title'=>'Test run','hasCover'=>FALSE,'element-content'=>'Test','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE,'excontainer'=>FALSE),
                        'edit'=>array('key'=>array('edit'),'title'=>'Edit','hasCover'=>FALSE,'element-content'=>'&#9998;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>FALSE,'style'=>array(),'excontainer'=>TRUE),
                        'show'=>array('key'=>array('show'),'title'=>'Show','hasCover'=>FALSE,'element-content'=>'&#10003;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>FALSE,'style'=>array(),'excontainer'=>TRUE),
                        'print'=>array('key'=>array('print'),'title'=>'Print','hasCover'=>FALSE,'element-content'=>'&#10064;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE,'style'=>array(),'excontainer'=>TRUE),
                        'run'=>array('key'=>array('run'),'title'=>'Run','hasCover'=>FALSE,'element-content'=>'Run','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE,'excontainer'=>TRUE),
                        'add'=>array('key'=>array('add'),'title'=>'Add this entry','hasCover'=>FALSE,'element-content'=>'+','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE),
                        'save'=>array('key'=>array('save'),'title'=>'Save this entry','hasCover'=>FALSE,'element-content'=>'&check;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>FALSE),
                        'upload'=>array('key'=>array('upload'),'title'=>'Upload file','hasCover'=>FALSE,'element-content'=>'Upload','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>FALSE,'excontainer'=>FALSE),
                        'download'=>array('key'=>array('download'),'title'=>'Download attached file','hasCover'=>FALSE,'element-content'=>'&#8892;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','requiresFile'=>TRUE,'excontainer'=>TRUE),
                        'download all'=>array('key'=>array('download all'),'title'=>'Download all attached file','hasCover'=>FALSE,'element-content'=>'&#8892;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','requiresFile'=>FALSE,'excontainer'=>TRUE),
                        'export'=>array('key'=>array('export'),'title'=>'Export all selected entries','hasCover'=>FALSE,'element-content'=>'&#9842;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','requiresFile'=>FALSE,'excontainer'=>TRUE),
                        'select'=>array('key'=>array('select'),'title'=>'Select entry','hasCover'=>FALSE,'element-content'=>'&#10022;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','excontainer'=>TRUE),
                        'approve'=>array('key'=>array('approve'),'title'=>'Approve entry','hasCover'=>FALSE,'element-content'=>'&check;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>array('font-size'=>'2rem','color'=>'green'),'excontainer'=>FALSE),
                        'decline'=>array('key'=>array('decline'),'title'=>'Decline entry','hasCover'=>FALSE,'element-content'=>'&#10006;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>array('font-size'=>'2rem','color'=>'red'),'excontainer'=>FALSE),
                        'delete'=>array('key'=>array('delete'),'title'=>'Delete entry','hasCover'=>TRUE,'element-content'=>'&coprod;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>array(),'excontainer'=>FALSE),
                        'remove'=>array('key'=>array('remove'),'title'=>'Remove attched file only','hasCover'=>TRUE,'element-content'=>'&xcup;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>TRUE,'style'=>array(),'excontainer'=>FALSE),
                        'delete all'=>array('key'=>array('delete all'),'title'=>'Delete all selected entries','hasCover'=>TRUE,'element-content'=>'Delete all selected','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'style'=>array(),'excontainer'=>FALSE),
                        'moveUp'=>array('key'=>array('moveUp'),'title'=>'Moves the entry up','hasCover'=>FALSE,'element-content'=>'&#9660;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>array('float'=>'right')),
                        'moveDown'=>array('key'=>array('moveDown'),'title'=>'Moves the entry down','hasCover'=>FALSE,'element-content'=>'&#9650;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>array()),
                        );

    private $appOptions=array('SourcePot\Datapool\Tools\GeoTools|getMapHtml'=>'getMapHtml()',
                       'SourcePot\Datapool\Foundation\Container|entryEditor|container'=>'entryEditor()',
                       'SourcePot\Datapool\Foundation\Container|comments'=>'comments()',
                       'SourcePot\Datapool\Tools\HTMLbuilder|entryControls'=>'entryControls()',
                       'SourcePot\Datapool\Tools\HTMLbuilder|deleteBtn'=>'deleteBtn()',
                       'SourcePot\Datapool\Tools\HTMLbuilder|downloadBtn'=>'downloadBtn()',
                       'SourcePot\Datapool\Tools\HTMLbuilder|selectBtn'=>'selectBtn()',
                       'SourcePot\Datapool\Tools\MediaTools|getPreview'=>'getPreview()',
                       'SourcePot\Datapool\Foundation\User|ownerAbstract'=>'ownerAbstract()',
                       );
        
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $_SESSION[__CLASS__]['keySelect']=array();
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }
    
    public function getBtns(array $arr):array
    {
        if (isset($this->btns[$arr['cmd']])){
            $arr=array_merge($this->btns[$arr['cmd']],$arr);
        }
        return $arr;
    }
    
    public function traceHtml(string $msg='This has happend:'):string
    {
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,5);    
        $html='<p>'.$msg.'</p><ol>';
        for($index=1;$index<4;$index++){
            if (!isset($trace[$index])){break;}
            $html.='<li>'.$trace[$index]['class'].'::'.$trace[$index]['function'].'() '.$trace[$index-1]['line'].'</li>';
        }
        return $html;
    }
    
    public function template2string($template='Hello [p:{{key}}]...',$arr=array('key'=>'world'),$element=array())
    {
        $flatArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($arr);
        foreach($flatArr as $flatArrKey=>$flatArrValue){
            $template=str_replace('{{'.$flatArrKey.'}}',(string)$flatArrValue,$template);
        }
        $template=preg_replace('/{{[^{}]+}}/','',$template);
        preg_match_all('/(\[\w+:)([^\]]+)(\])/',$template,$matches);
        if (isset($matches[0][0])){
            foreach($matches[0] as $matchIndex=>$match){
                $element['tag']=trim($matches[1][$matchIndex],'[:');
                $element['element-content']=$matches[2][$matchIndex];
                $replacement=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                $template=str_replace($match,$replacement,$template);
            }
        }
        return $template;
    }
    
    private function arr2id(array $arr):string
    {
        $toHash=array($arr['callingClass'],$arr['callingFunction'],$arr['key']);
        return $this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($toHash);
    }
    
    public function element(array $arr):string
    {
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
    }

    public function table(array $arr,bool $returnArr=FALSE):string|array
    {
        $html='';
        $styles=array('trStyle'=>array());
        if (!empty($arr['matrix'])){
            $indexArr=array('x'=>0,'y'=>0);
            $tableArr=array('tag'=>'table','keep-element-content'=>TRUE,'element-content'=>'');
            if (isset($arr['id'])){$tableArr['id']=$arr['id'];}
            if (isset($arr['style'])){$tableArr['style']=$arr['style'];}
            if (isset($arr['title'])){$tableArr['title']=$arr['title'];}
            $tbodyArr=array('tag'=>'tbody','keep-element-content'=>TRUE);
            if (isset($arr['class'])){
                $tableArr['class']=$arr['class'];
                $tbodyArr['class']=$arr['class'];
            }
            if (!empty($arr['caption'])){
                $captionArr=array('tag'=>'caption','keep-element-content'=>TRUE,'element-content'=>$arr['caption']);
                if (isset($arr['class'])){$captionArr['class']=$arr['class'];}
                $tableArr['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($captionArr);
            }
            foreach($arr['matrix'] as $rowLabel=>$rowArr){
                $indexArr['x']=0;
                $indexArr['y']++;
                $rowArr['trStyle']=(isset($rowArr['trStyle']))?$rowArr['trStyle']:$styles['trStyle'];
                if (!empty($arr['skipEmptyRows']) && empty($rowArr)){continue;}
                if (empty($arr['hideKeys'])){$rowArr=array(' Key '=>$rowLabel)+$rowArr;}
                $trArr=array('tag'=>'tr','keep-element-content'=>TRUE,'element-content'=>'','style'=>$rowArr['trStyle']);
                $trHeaderArr=array('tag'=>'tr','keep-element-content'=>TRUE,'element-content'=>'');
                foreach($rowArr as $colLabel=>$cell){
                    if (isset($styles[$colLabel])){continue;}
                    if (empty($arr['thKeepCase'])){
                        $colLabel=ucfirst(strval($colLabel));
                    } else {
                        $colLabel=strval($colLabel);
                    }
                    $indexArr['x']++;
                    $thArr=array('tag'=>'th','element-content'=>$colLabel,'keep-element-content'=>!empty($arr['keep-element-content']));
                    $tdArr=array('tag'=>'td','cell'=>$indexArr['x'].'-'.$indexArr['y'],'keep-element-content'=>!empty($arr['keep-element-content']));
                    if (isset($cell['tdStyle'])){
                        $tdArr['style']=$cell['tdStyle'];
                        unset($cell['tdStyle']);
                    }
                    if (isset($arr['class'])){
                        $trHeaderArr['class']=$trArr['class']=$thArr['class']=$tdArr['class']=$arr['class'];
                    }
                    if (is_array($cell)){
                        $tdArr['element-content']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($cell);
                    } else {
                        if (empty($arr['keep-element-content'])){$cell=htmlspecialchars(strval($cell),ENT_QUOTES,'UTF-8');}
                        $tdArr['element-content']=$cell;
                    }
                    $trHeaderArr['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($thArr);
                    $trArr['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($tdArr);
                } // loop through columns
                if (!isset($tbodyArr['element-content'])){
                    if (empty($arr['hideHeader'])){
                        $tbodyArr['element-content']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($trHeaderArr);
                    } else {
                        $tbodyArr['element-content']='';
                    }
                }
                $tbodyArr['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($trArr);
            } // loop through rows
            $tableArr['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($tbodyArr);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($tableArr);
        } // if !empty matrix  
        if ($returnArr){return array('html'=>$html);} else {return $html;}
    }
    
    public function select(array $arr,bool $returnArr=FALSE):string|array
    {
        // This function returns the HTML-select-element with options based on $arr.
        // Required keys are 'options', 'key', 'callingClass' and 'callingFunction'.
        // Key 'label', 'selected', 'triggerId' are optional.
        // If 'hasSelectBtn' is set, a button will be added which will be clicked if an item is selected.
        $optionsFilterLimit=20;
        if (!isset($arr['key'])){
            throw new \ErrorException('Function '.__FUNCTION__.': Missing key-key in argument arr',0,E_ERROR,__FILE__,__LINE__);
        }
        if (is_array($arr['key'])){$key=implode($this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator(),$arr['key']);} else {$key=$arr['key'];}
        $inputId=$this->arr2id($arr).'input';
        $triggerId=$this->arr2id($arr).'btn';    
        $html='';
        if (!empty($arr['options'])){
            // create label
            if (!empty($arr['label'])){
                $inputArr=$arr;
                $inputArr['tag']='label';
                $inputArr['for']=$inputId;
                $inputArr['element-content']=$arr['label'];
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($inputArr);
                unset($arr['label']);
            }
            // create select
            $selected='';
            if (isset($arr['selected'])){$selected=$arr['selected'];unset($arr['selected']);}
            if (isset($arr['value'])){$selected=$arr['value'];unset($arr['value']);}
            if (!isset($arr['options'][$selected]) && !empty($selected)){$arr['options'][$selected]=$selected;}
            $toReplace=array();
            $selectArr=$arr;
            if (!empty($arr['hasSelectBtn'])){$selectArr['trigger-id']=$triggerId;}
            $selectArr['tag']='select';
            $selectArr['id']=$inputId;
            $selectArr['value']=$selected;
            $selectArr['element-content']='{{options}}';
            $selectArr['keep-element-content']=TRUE;
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($selectArr);
            // create options
            if (isset($arr['style'])){unset($arr['style']);}
            $toReplace['{{options}}']='';
            foreach($arr['options'] as $name=>$label){
                $optionArr=$arr;
                $optionArr['tag']='option';
                if (strval($name)===strval($selected)){$optionArr['selected']=TRUE;}
                if (strval($name)==='useValue'){$optionArr['title']='Use value provided';}
                $optionArr['value']=$name;
                $optionArr['element-content']=$label;
                $optionArr['dontTranslateValue']=TRUE;
                $toReplace['{{options}}'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($optionArr);                
            }
            foreach($toReplace as $needle=>$value){
                $html=str_replace($needle,$value,$html);
            }
            if (count($arr['options'])>$optionsFilterLimit && !empty($selectArr['id'])){
                $filterArr=array('tag'=>'input','type'=>'text','placeholder'=>'filter','key'=>array('filter'),'class'=>'filter','id'=>'filter-'.$selectArr['id'],'excontainer'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($filterArr);
                $countArr=array('tag'=>'p','element-content'=>count($arr['options']),'class'=>'filter','id'=>'count-'.$selectArr['id']);
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($countArr);
            }
            if (isset($selectArr['trigger-id'])){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'*','key'=>array('select'),'value'=>$key,'id'=>$triggerId,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
            }
        }
        if ($returnArr){
            $arr['html']=$html;
            return $arr;
        } else {
            return $html;
        }
    }
    
    public function tableSelect(array $arr):string
    {
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->getDbInfo() as $table=>$tableDef){$arr['options'][$table]=ucfirst($table);}
        return $this->select($arr);
    }
    
    /**
    * The method returns an html-selector for entry keys. The entry is selected by parameter $arr. 
    * $arr options are:
    * 'value' or 'selected' - Is the value selecting the option
    * 'standardColumsOnly' - If TRUE, html-selector options will only contain standard columns 
    * 'addSourceValueColumn' - If TRUE, an option 'useValue' will is added 
    * 'addColumns' - (array), options to be added to the html-selector 
    * 'showSample' - If TRUE, entry sample values are added 
    *
    * @param array $arr Is the entry selector and options  
    * @return array HTML-selector
    */
    public function keySelect(array $arr,array $appendOptions=array()):string
    {
        if (empty($arr['Source'])){return '';}
        $arr['value']=(isset($arr['value']))?$arr['value']:'';
        $stdKeys=$keys=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($arr['Source']);
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($arr,array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE,'Type'=>FALSE,'Read'=>FALSE,'Write'=>FALSE,'app'=>''));
        $requestId=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE).(empty($arr['standardColumsOnly'])?'ALL':'STANDARD');
        if (isset($_SESSION[__CLASS__][__FUNCTION__][$requestId])){
            // get options from cache
            $keys=$_SESSION[__CLASS__][__FUNCTION__][$requestId];
        } else {
            // get available keys
            $rowCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,TRUE,'Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$removeGuideEntries=TRUE,$isDebugging=FALSE);
            for($i=0;$i<2;$i++){
                $offset=($rowCount>1)?mt_rand(0,$rowCount-1):0;
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read',FALSE,TRUE,1,$offset) as $tmpEntry){
                    $keys+=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($tmpEntry);
                }                
            }
            $_SESSION[__CLASS__][__FUNCTION__][$requestId]=$keys;
        }
        $arr['keep-element-content']=TRUE;
        $arr['options']=(empty($arr['addSourceValueColumn']))?array():array('useValue'=>'&#9998;');
        $arr['options']+=(empty($arr['addColumns']))?array():$arr['addColumns'];
        $sampleValue='';
        foreach($keys as $key=>$value){
            if (!empty($arr['standardColumsOnly']) && !isset($stdKeys[$key])){continue;}
            if ($key==$arr['value'] && !empty($arr['showSample'])){$sampleValue=(is_array($value))?'':strval($value);}
            $arr['options'][$key]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($key);
        }
        $arr['options']+=$appendOptions;
        $html=$this->select($arr);
        if (!empty($sampleValue)){
            if (strlen($sampleValue)>40){$sampleValue=substr($sampleValue,0,37).'...';}
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','class'=>'sample','element-content'=>$sampleValue));
        }
        return $html;
    }
    
    public function canvasElementSelect(array $arr):string
    {
        if (empty($arr['canvasCallingClass'])){
            throw new \ErrorException('Function '.__FUNCTION__.': Argument arr[canvasCallingClass] is missing but required.',0,E_ERROR,__FILE__,__LINE__);
        }
        $canvasElements=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements($arr['canvasCallingClass']);
        if (empty($arr['addColumns'])){$arr['options']=array();} else {$arr['options']=$arr['addColumns'];}
        foreach($canvasElements as $key=>$canvasEntry){
            if (empty($canvasEntry['Content']['Selector']['Source'])){continue;}
            $arr['options'][$canvasEntry['EntryId']]=$canvasEntry['Content']['Style']['Text'];
        }
        return $this->select($arr);
    }

    public function getClientInfo(array $arr):string
    {
        return $this->oc['SourcePot\Datapool\Foundation\ClientAccess']->getClientInfo($arr);
    }
    
    public function preview(array $arr):array
    {
        return $this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
    }
    
    public function copy2clipboard(string $text):string
    {
        $html='';
        $id=md5($text.mt_rand(1000,9999));
        $element=array('tag'=>'button','element-content'=>'&#9780;','keep-element-content'=>TRUE,'id'=>'clipboard-'.$id,'key'=>array('copy',$id),'excontainer'=>TRUE,'title'=>'Copy to clipboard','style'=>array('font-weight'=>'bold'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        $element=array('tag'=>'div','element-content'=>$text,'id'=>$id,'style'=>array('padding'=>'0'));
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        $element=array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        return $html;
    }
    
    /**
    * This method returns an html-button to trigger entry processing such as download, delete, etc.
    * The method is called with an empty $arr argument by SourcePot\Datapool\Root::run() to process the button commands before the web page is built.
    *
    * @param array $arr Is the control-array it must be empty to process the last user action OR
    *                   it must contain the entry selector (key="selector") and key="cmd" which selects the button template  
    * @return string The html-tag for the button 
    */
    public function btn(array $arr=array()):string
    {
        // This function returns standard buttons based on argument arr.
        // If arr is empty, buttons will be processed
        $html='';
        $defaultValues=array('selector'=>array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE,'Type'=>FALSE));
        $setValues=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        if (isset($arr['cmd'])){
            // compile button
            $arr['element-content']=(isset($arr['element-content']))?$arr['element-content']:ucfirst($arr['cmd']);
            $arr['id']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($arr,TRUE);
            $arr['key']=array($arr['cmd']);
            if (!empty($arr['selector']['Source'])){$arr['source']=$arr['selector']['Source'];}
            if (!empty($arr['selector']['EntryId'])){
                $arr['entry-id']=$arr['selector']['EntryId'];
                if (!isset($arr['value'])){$arr['value']=$arr['selector']['EntryId'];}
            } else if (!isset($arr['value'])){
                $arr['value']=$arr['id'];
            }
            $btnFailed=FALSE;
            if (isset($this->btns[$arr['cmd']])){
                $arr=array_replace_recursive($defaultValues,$arr,$setValues,$this->btns[$arr['cmd']]);
                if (!empty($arr['requiredRight'])){
                    $hasAccess=$this->oc['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],$arr['requiredRight']);
                    if (empty($hasAccess)){$btnFailed='Access denied';}
                }
                if (!empty($arr['requiresFile']) && mb_strpos(strval($arr['selector']['EntryId']),'-guideEntry')===FALSE){
                    $hasFile=is_file($this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr['selector']));
                    if (!$hasFile || empty($arr['selector']['Params']['File'])){$btnFailed='File error';}
                }
                $arr['element-content']=str_replace(' ','&nbsp;',$arr['element-content']);
            } else {
                $btnFailed='Button defintion missing';
            }
            if (empty($btnFailed)){
                if (isset($arr['selector']['Content'])){unset($arr['selector']['Content']);}
                if (isset($arr['selector']['Params'])){unset($arr['selector']['Params']);}
                if (strcmp($arr['cmd'],'upload')===0){
                    $arr['key'][]=$arr['selector']['EntryId'];
                    $fileArr=$arr;
                    if (isset($fileArr['element-content'])){unset($fileArr['element-content']);}
                    $fileArr['tag']='input';
                    $fileArr['type']='file';
                    $fileArr['excontainer']=TRUE;
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileArr);
                }
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
            }
        } else {
            $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
            // button command processing
            $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
            $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($formData['selector']);
            //if (!empty($formData['cmd'])){$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($formData);}
            if (isset($formData['cmd']['download']) || isset($formData['cmd']['download all'])){
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->entry2fileDownload($selector);
            } else if (isset($formData['cmd']['upload'])){
                $key=key($formData['cmd']['upload']);
                $fileArr=current($formData['files']['upload'][$key]);
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($fileArr,$entry);
            } else if (isset($formData['cmd']['delete']) || isset($formData['cmd']['delete all'])){
                $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector);
                $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->selectorAfterDeletion($selector);
                $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateBySelector($selector);
            } else if (isset($formData['cmd']['remove'])){
                $entry=$formData['selector'];
                $this->oc['SourcePot\Datapool\Foundation\Database']->removeFileFromEntry($entry);
            } else if (isset($formData['cmd']['select'])){
                $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateBySelector($selector);
            } else if (isset($formData['cmd']['edit'])){
                $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setEditMode($selector,TRUE);
            } else if (isset($formData['cmd']['show'])){
                $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setEditMode($selector,FALSE);
            } else if (isset($formData['cmd']['export'])){
                $pageTitle=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');
                $fileName=date('Y-m-d H_i_s').' '.$pageTitle.' '.$selector['Source'].' dump.zip';
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->downloadExportedEntries(array($selector),$fileName,FALSE,10000000000);
            } else if (isset($formData['cmd']['approve']) || isset($formData['cmd']['decline'])){
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
                $cmd=key($formData['cmd']);
                $entry['Params']['User'][$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()]=array('action'=>$cmd,'timestamp'=>time(),'app'=>$selector['app']);
                $entry['Params']['User'][$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()]['user']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(array(),1);
                $entry['Params']['User'][$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()]['user email']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(array(),7);
                $entry['Params']['User'][$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()]['user mobile']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(array(),9);
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
            }
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->formData2statisticlog($formData);
        }
        return $html;
    }

    /**
    * This method ios a wrapper method for method btn() which returns an entry delete html-button.
    *
    * @param array $arr It must contain the entry selector (key="selector")  
    * @return string The html-tag for the button 
    */
    public function deleteBtn(array $arr):string
    {
        $arr['cmd']='delete';
        $html=$this->btn($arr);
        return $html;
    }
    
    /**
    * This method is a wrapper method for method btn() which returns an entry download html-button.
    *
    * @param array $arr It must contain the entry selector (key="selector")  
    * @return string The html-tag for the button 
    */
    public function downloadBtn(array $arr):string
    {
        $arr['cmd']='download';
        $html=$this->btn($arr);
        return $html;
    }

    /**
    * This method is a wrapper method for method btn() which returns an entry download html-button.
    *
    * @param array $arr It must contain the entry selector (key="selector")  
    * @return string The html-tag for the button 
    */
    public function selectBtn(array $arr):string
    {
        $arr['cmd']='select';
        $html=$this->btn($arr);
        return $html;
    }

    public function app(array $arr):string
    {
        if (empty($arr['html'])){return '';}
        $arr['icon']=(isset($arr['icon']))?$arr['icon']:'?';
        $arr['style']=(isset($arr['style']))?$arr['style']:array();
        $arr['class']=(isset($arr['class']))?$arr['class']:'app';
        $arr['title']=(isset($arr['title']))?$arr['title']:'';
        $summaryArr=array('tag'=>'summary','element-content'=>$arr['icon'],'keep-element-content'=>TRUE,'title'=>$arr['title'],'class'=>$arr['class']);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($summaryArr);
        $detailsArr=array('tag'=>'details','element-content'=>$html.$arr['html'],'keep-element-content'=>TRUE,'class'=>$arr['class'],'style'=>$arr['style']);
        if (isset($arr['open'])){$detailsArr['open']=$arr['open'];}
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($detailsArr);
        return $html;
        
    }
    
    public function emojis(array $arr=array()):array
    {
        if (empty($arr['settings']['target'])){
            throw new \ErrorException('Method '.__FUNCTION__.' called without target setting.',0,E_ERROR,__FILE__,__LINE__);    
        }
        $arr['html']=(isset($arr['html']))?$arr['html']:'';        
        // get emoji options
        $options=array();
        foreach($this->oc['SourcePot\Datapool\Tools\MiscTools']->emojis as $category=>$categoryArr){
            foreach($categoryArr as $group=>$groupArr){
                $firstEmoji=$this->oc['SourcePot\Datapool\Tools\MiscTools']->code2utf(key($groupArr));
                $options[$category.'||'.$group]=$firstEmoji.' '.$group;
            }
        }
        //
        $callingFunction=$arr['settings']['target'];
        if (!isset($_SESSION[__CLASS__]['settings'][$callingFunction]['Category'])){
            $_SESSION[__CLASS__]['settings'][$callingFunction]['Category']=key($options);
        }
        $arr['formData']=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,$callingFunction);
        if (!empty($arr['formData']['val'])){
            $_SESSION[__CLASS__]['settings'][$callingFunction]=$arr['formData']['val'];
        }
        $currentKeys=explode('||',$_SESSION[__CLASS__]['settings'][$callingFunction]['Category']);
        $categorySelectArr=array('options'=>$options,'key'=>array('Category'),'selected'=>$_SESSION[__CLASS__]['settings'][$callingFunction]['Category'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction);
        $html=$this->select($categorySelectArr);
        if (count($currentKeys)>1){
            $tagArr=array('tag'=>'a','href'=>'#','class'=>'emoji','target'=>$arr['settings']['target']);
            foreach($this->oc['SourcePot\Datapool\Tools\MiscTools']->emojis[$currentKeys[0]][$currentKeys[1]] as $code=>$title){
                $tagArr['id']='utf8-'.$code;
                $tagArr['title']=$title;
                $tagArr['element-content']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->code2utf($code);
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($tagArr);
            }
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE));
        return $arr;
    }
    
    public function integerEditor(array $arr):string
    {
        // This function provides the HTML-script for an integer editor for the provided entry argument.
        // Typical use is for keys 'Read', 'Write' or 'Privileges'.
        //
        if (empty($arr['selector']['Source'])){return 'Method '.__FUNCTION__.' called but Source missing.';}
        $template=array('key'=>'Read','integerDef'=>$this->oc['SourcePot\Datapool\Foundation\User']->getUserRols(),'bitCount'=>16);
        $arr=array_replace_recursive($template,$arr);
        $entry=$arr['selector'];
        // only the Admin has access to the method if columns 'Privileges' is selected
        if (is_array($arr['key'])){$arr['key']=array_shift($arr['key']);}
        if (strcmp($arr['key'],'Privileges')===0 && !$this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE,FALSE,TRUE)){
            return '';
        }
        $integer=$entry[$arr['key']];
        $callingClass=__CLASS__;
        $callingFunction=__FUNCTION__.$arr['key'];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($callingClass,$callingFunction);
        $saveRequest=isset($formData['cmd'][$arr['key']]['save']);
        $updatedInteger=0;
        $html='<fieldset>';
        $html.='<legend>'.'"'.$arr['key'].'" right'.'</legend>';
        $matrix=array();
        if (is_string($integer)){$integer=intval($integer);}
        for($bitIndex=0;$bitIndex<$arr['bitCount'];$bitIndex++){
            $currentVal=pow(2,$bitIndex);
            if ($saveRequest){
                // get checkboxes from form
                if (empty($formData['val'][$arr['key']][$bitIndex])){
                    $checked=FALSE;
                } else {
                    $updatedInteger+=$currentVal;
                    $checked=TRUE;
                }
            } else {
                // get checkboxes from form
                if (($currentVal & $integer)==0){$checked=FALSE;} else {$checked=TRUE;}
            }
            if (isset($arr['integerDef'][$bitIndex]['Name'])){$label=$arr['integerDef'][$bitIndex]['Name'];} else {$label=$bitIndex;}
            $bitIndex=strval($bitIndex);
            $id=md5($callingClass.$callingFunction.$bitIndex);
            //$matrix[$bitIndex]['Label']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'label','for'=>$id,'element-content'=>strval($label)));
            //$matrix[$bitIndex]['Status']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'checkbox','checked'=>$checked,'id'=>$id,'key'=>array($arr['key'],$bitIndex),'callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'title'=>'Bit '.$bitIndex));
            $htmlBit=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'checkbox','checked'=>$checked,'id'=>$id,'key'=>array($arr['key'],$bitIndex),'callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'title'=>'Bit '.$bitIndex));
            $htmlBit.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'label','for'=>$id,'element-content'=>strval($label)));
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$htmlBit,'keep-element-content'=>TRUE,'class'=>'fieldset'));
        }
        $updateBtn=array('tag'=>'button','key'=>array($arr['key'],'save'),'element-content'=>'Save','style'=>array('margin'=>'0','width'=>'100%'),'callingClass'=>$callingClass,'callingFunction'=>$callingFunction);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($updateBtn);
        //$matrix['Cmd']['Label']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($updateBtn);
        //$matrix['Cmd']['Status']='';
        if ($saveRequest){
            $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($entry);
            $entry=$this->oc['SourcePot\Datapool\Root']->substituteWithPlaceholder($entry);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntries($entry,array($arr['key']=>$updatedInteger),FALSE,'Write');
            $statistics=$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
            $context=array('key'=>$arr['key'],'Source'=>$entry['Source'],'selector'=>'','statistics'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics));
            $context['what']=(empty($entry['EntryId']))?'entries':'entry';
            $context['selector'].=(empty($entry['Group']))?'':' | Group='.$entry['Group'];
            $context['selector'].=(empty($entry['Folder']))?'':' | Folder='.$entry['Folder'];
            $context['selector'].=(empty($entry['EntryId']))?'':' | EntryId='.$entry['EntryId'];
            $context['selector']=trim($context['selector'],'| ');
            $this->oc['logger']->log('info','{Source}-{what} selected by "{selector}" {key}-key processed: {statistics}',$context);    
        }
        $hideHeader=(isset($arr['hideHeader']))?$arr['hideHeader']:TRUE;
        $hideKeys=(isset($arr['hideKeys']))?$arr['hideKeys']:TRUE;
        $html.='</fieldset>';
        return $html;
    }
    
    public function setAccessByte(array $arr):string
    {
        // This method returns html with a number of checkboxes to set the bits of an access-byte.
        // $arr[key] ... Selects the respective access-byte, e.g. $arr['key']='Read', $arr['key']='Write' or $arr['key']='Privileges'.   
        if (!isset($arr['selector'])){return $arr;}
        if (empty($arr['key'])){$arr['key']='Read';}
        if (empty($arr['selector']['Source']) || empty($arr['selector']['EntryId']) || empty($arr['selector'][$arr['key']])){
            $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>'Required keys missing.'));
        } else {
            $html=$this->integerEditor($arr);
        }
        return $html;
    }
    
    /**
    * This method returns an html-table containing a file upload facility as well as the gerenic buttons 'remove' and 'delete'.
    * $arr['hideDownload']=TRUE hides the downlaod-button, $arr['hideRemove']=TRUE hides the remove-button and $arr['hideDelete']=TRUE hides the delete-button. 
    * @return string
    */
    public function entryControls(array $arr,bool $isDebugging=FALSE):string
    {
        $tableStyle=array('clear'=>'none','margin'=>'0','min-width'=>'200px');
        if (!isset($arr['selector'])){return 'Selector missing';}
        $debugArr=array('arr_in'=>$arr);
        $arr['html']='';
        if (!isset($arr['selector']['Content'])){
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
        }
        if (empty($arr['selector'])){
            return 'Entry does not exsist (yet).';
        }
        $template=array('callingClass'=>__CLASS__,
                        'callingFunction'=>__FUNCTION__,
                        'hideHeader'=>TRUE,
                        'hideKeys'=>TRUE,
                        'previewStyle'=>array('max-height'=>100,'max-width'=>200),
                        'settings'=>array('hideApprove'=>TRUE,'hideDecline'=>TRUE,'hideSelect'=>FALSE,'hideRemove'=>FALSE,'hideDelete'=>FALSE,'hideDownload'=>FALSE,'hideUpload'=>FALSE,'hideDelete'=>FALSE),
                        );
        $arr=array_replace_recursive($template,$arr);
        // create preview
        $matrix=array('Preview'=>array('Value'=>''),'Btns'=>array('Value'=>''));
        if (empty($arr['hidePreview'])){
            $previewArr=$arr;
            $previewArr['settings']['style']=$arr['previewStyle'];
            $previewArr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($previewArr);
            $matrix['Preview']['Value'].=$previewArr['html'];
        }
        // detected user action
        $tableTitle=$arr['selector']['Name'];
        $userAction='none';
        if (isset($arr['selector']['Params']['User'][$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()]['action'])){
            $userAction=$arr['selector']['Params']['User'][$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()]['action'];
            $tableTitle='Your decission for the entry: '.$userAction;
        }
        if ($userAction==='approve'){
            $tableStyle['border']='2px solid #0f0';
        } else if ($userAction==='decline'){
            $tableStyle['border']='2px solid #f00';
        }
        // create buttons
        foreach($arr['settings'] as $key=>$value){
            if (strpos($key,'hide')!==0){continue;}
            $cmd=strtolower(str_replace('hide','',$key));
            if ($value===FALSE && $userAction!==$cmd){
                $arr['excontainer']=TRUE;
                $arr['cmd']=$cmd;
                $matrix['Btns']['Value'].=$this->btn($arr);
                $debugArr['btn'][]=$arr;
            }
        }
        // finalize
        $matrix['Btns']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$matrix['Btns']['Value'],'keep-element-content'=>TRUE,'style'=>array('width'=>'max-content')));
        $html=$this->table(array('matrix'=>$matrix,'hideHeader'=>$arr['hideHeader'],'hideKeys'=>$arr['hideKeys'],'caption'=>FALSE,'keep-element-content'=>TRUE,'title'=>$tableTitle,'style'=>$tableStyle,'class'=>'matrix'));
        if ($isDebugging){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        return $html;
    }
    
    public function entry2row(array $arr,bool $commandProcessingOnly=FALSE,bool $singleRowOnly=FALSE,bool $isNewRow=FALSE,bool $isSystemCall=FALSE):array|string
    {
        $arr['returnRow']=TRUE;
        return $this->entryListEditor($arr,$isSystemCall);
    }

    public function entryListEditor(array $arr,bool $isSystemCall=TRUE):string|array
    {
        $errorMsg='Method "'.__CLASS__.' &rarr; '.__FUNCTION__.'()" called with arr-argument keys missing: ';
        if (!isset($arr['contentStructure']) || empty($arr['callingClass']) || empty($arr['callingFunction'])){
            $errorMsg.=' contentStructure, callingClass or callingFunction';
            $this->oc['logger']->log('error',$errorMsg,array());
            return (empty($arr['returnRow']))?$errorMsg:array('error'=>$errorMsg);
        }
        if ((empty($arr['selector']['Source']) && empty($arr['selector']['Class'])) || empty($arr['selector']['EntryId'])){
            $errorMsg.=' Source or Class or EntryId';
            $this->oc['logger']->log('error',$errorMsg,array());
            return (empty($arr['returnRow']))?$errorMsg:array('error'=>$errorMsg);
        }
        $this->oc['SourcePot\Datapool\Foundation\Legacy']->updateEntryListEditorEntries($arr); // <----------------- Update old EntryId
        // get base selector and storage object
        if (!empty($arr['selector']['Source'])){
            $storageObj='SourcePot\Datapool\Foundation\Database';
            $baseSelector=array('Source'=>$arr['selector']['Source']);
        } else {
            $storageObj='SourcePot\Datapool\Foundation\Filespace';
            $baseSelector=array('Class'=>$arr['selector']['Class']);
        }
        // initialization
        $arr['returnRow']=!empty($arr['returnRow']);
        $arr['caption']=(empty($arr['caption']))?'CAPTION MISSING':$arr['caption'];
        $arr['maxRowCount']=(empty($arr['maxRowCount']))?999:$arr['maxRowCount'];
        if ($arr['returnRow']){$arr['maxRowCount']=1;}
        $firstEntry=$arr['selector'];
        if ($arr['maxRowCount']>1){
            $firstEntry['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Database']->addOrderedListIndexToEntryId($arr['selector']['EntryId'],1);
        } else {
            $firstEntry['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListKeyFromEntryId($arr['selector']['EntryId']);
        }
        $this->oc[$storageObj]->entryByIdCreateIfMissing($firstEntry,TRUE);
        // command processing
        $movedEntryId='';
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (!empty($formData['cmd'])){
            $selector=$baseSelector;
            $selector['EntryId']=key(current($formData['cmd']));
        }
        if (isset($formData['cmd']['save'])){
            $entry=array_merge($arr['selector'],$selector,$formData['val'][$selector['EntryId']]);
            $this->oc[$storageObj]->updateEntry($entry,$isSystemCall);
        } else if (isset($formData['cmd']['delete'])){
            $this->oc['SourcePot\Datapool\Foundation\Database']->rebuildOrderedList($selector,array('removeEntryId'=>$selector['EntryId']));
        } else if (isset($formData['cmd']['add'])){
            $endIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($selector['EntryId']);
            $newEntry=$arr['selector'];
            $newEntry['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Database']->addOrderedListIndexToEntryId($selector['EntryId'],$endIndex+1);
            $this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($newEntry);
        } else if (isset($formData['cmd']['moveUp'])){
            $movedEntryId=$this->oc['SourcePot\Datapool\Foundation\Database']->rebuildOrderedList($selector,array('moveUpEntryId'=>$selector['EntryId']));
        } else if (isset($formData['cmd']['moveDown'])){
            $movedEntryId=$this->oc['SourcePot\Datapool\Foundation\Database']->rebuildOrderedList($selector,array('moveDownEntryId'=>$selector['EntryId']));
        }
        // html creation
        $csvMatrix=array();
        $matrix=array();
        $startIndex=$endIndex=1;
        $selector=$baseSelector;
        $selector['EntryId']='%'.$arr['selector']['EntryId'];
        foreach($this->oc[$storageObj]->entryIterator($selector,$isSystemCall,'Read','EntryId',TRUE) as $entry){
            $endIndex=$entry['rowCount'];
            $entryIdComps=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($entry['EntryId']);
            $currentIndex=intval($entryIdComps[0]);
            $rowIndex=$entryIdComps[0];
            if (empty($entry['Content'])){$matrix[$rowIndex]['trStyle']=array('background-color'=>'#f00');}
            foreach($arr['contentStructure'] as $contentKey=>$elementArr){
                $classWithNamespace=(empty($elementArr['classWithNamespace']))?__CLASS__:$elementArr['classWithNamespace'];
                $method=(empty($elementArr['method']))?'method-arg-missing':$elementArr['method'];
                if (method_exists($classWithNamespace,$method)){
                    // if classWithNamespace::method() exists
                    if (isset($entry['Content'][$contentKey])){
                        if (isset($elementArr['element-content'])){
                            $elementArr['element-content']=$entry['Content'][$contentKey];
                            if (!empty($elementArr['value'])){$elementArr['value']=$elementArr['value'];}
                        } else {
                            $elementArr['value']=$entry['Content'][$contentKey];
                        }
                    }
                    $elementArr['callingClass']=$arr['callingClass'];
                    $elementArr['callingFunction']=$arr['callingFunction'];
                    $elementArr['key']=array($entry['EntryId'],'Content',$contentKey);
                    if (isset($arr['canvasCallingClass'])){$elementArr['canvasCallingClass']=$arr['canvasCallingClass'];}
                    $matrix[$rowIndex][$contentKey]=$this->oc[$classWithNamespace]->$method($elementArr);
                    if (isset($elementArr['type']) && isset($elementArr['value'])){
                        if (strcmp($elementArr['type'],'hidden')===0){
                            $matrix[$rowIndex][$contentKey].=$elementArr['value'];
                        }
                    }
                    $csvMatrix[$entry['EntryId']][$contentKey]=$elementArr['value']??$elementArr['element-content']??'';
                } else {
                    // if classWithNamespace::method() does not exists
                    $matrix[$rowIndex][$contentKey]=$this->traceHtml('Not found: '.$classWithNamespace.'::'.$method.'(arr)');
                }
            } // end of loop through content structure
            $cmdBtns='';
            $moveBtns='';
            // add buttons
            $btnArr=array_replace_recursive($arr,$this->btns['save'],array('excontainer'=>FALSE));
            $btnArr['key'][]=$entry['EntryId'];
            $cmdBtns.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
            if ($entry['rowCount']>1){
                $btnArr=array_replace_recursive($arr,$this->btns['delete'],array('excontainer'=>FALSE));
                $btnArr['key'][]=$entry['EntryId'];
                $cmdBtns.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
            }
            if ($endIndex<$arr['maxRowCount'] && $currentIndex===$endIndex){
                $btnArr=array_replace_recursive($arr,$this->btns['add'],array('excontainer'=>FALSE));
                $btnArr['key'][]=$entry['EntryId'];
                $cmdBtns.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
            }
            if ($currentIndex>$startIndex){
                $btnArr=array_replace_recursive($arr,$this->btns['moveDown'],array('excontainer'=>FALSE));
                $btnArr['key'][]=$entry['EntryId'];
                if (strcmp($entry['EntryId'],$movedEntryId)===0){$btnArr['style']['background-color']='#89fa';}
                $moveBtns.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);    
            }
            if ($currentIndex<$endIndex){
                $btnArr=array_replace_recursive($arr,$this->btns['moveUp'],array('excontainer'=>FALSE));
                $btnArr['key'][]=$entry['EntryId'];
                if (strcmp($entry['EntryId'],$movedEntryId)===0){$btnArr['style']['background-color']='#89fa';}
                $moveBtns.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);    
            }
            $matrix[$rowIndex]['']=$cmdBtns;
            if ($entry['rowCount']>1){$matrix[$rowIndex]['Move']=$moveBtns;}
        } // end of loop through list entries
        $matrix[$rowIndex]['Move']=($matrix[$rowIndex]['Move']??'').$this->oc['SourcePot\Datapool\Tools\CSVtools']->matrix2csvDownload($csvMatrix);
        if ($arr['returnRow']){
            return (empty(current($matrix)))?array('value'=>''):current($matrix);
        } else {
            return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
        }
    }

    public function selector2string(array $selector=array(), bool $useEntryId=FALSE):string
    {
        $template=array('Source'=>'','Group'=>'','Folder'=>'','Name'=>'');
        if (empty($selector['Name']) && !empty($selector['EntryId'])){
            $selector=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
        }
        $result=array();
        if ($useEntryId){$keys['EntryId']='';}
        foreach($template as $key=>$default){
            if (!isset($selector[$key])){break;}
            if ($selector[$key]===FALSE){break;}
            $result[$key]=(isset($selector[$key]))?strval($selector[$key]):$default;
        }
        return implode(' &rarr; ',$result);
    }

    public function row2table(array $row,string $caption='Row as table',bool $flip=FALSE):string
    {
        if ($flip){
            $matrix=array();
            foreach($row as $key=>$value){
                $matrix[$key]=array('value'=>$value);
            }
        } else {
            $matrix=array($caption=>$row);
        }
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
    }
    
    public function value2tabelCellContent($html,array $arr=array())
    {
        if (!is_string($html) || empty($html)){
            return $html;
        } else if (strlen(strip_tags($html))==strlen($html)){
            $arr['tag']='p';
            $arr['class']='td-content-wrapper';
            $arr['keep-element-content']=TRUE;
            $arr['element-content']=$html;
        } else {
            $tmpDir=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
            $htmlFile=$tmpDir.md5($html).'.html';
            $bytes=file_put_contents($htmlFile,$html);
            $arr['tag']='embed';
            $arr['type']='text/html';
            $arr['allowfullscreen']=TRUE;
            $arr['element-content']=' ';
            $arr['src']=str_replace($GLOBALS['dirs']['tmp'],$GLOBALS['relDirs']['tmp'].'/',$htmlFile);
        }
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
    }
    
    public function loadEntry(array $arr):string
    {
        if (empty($arr['selector'])){return '';}
        if (empty($arr['excontainer'])){
            $settingsTemplate=array('method'=>'presentEntry','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder');
            $arr['settings']=array_merge($arr['settings'],$settingsTemplate);
            $wrapperSetting=array();
            $wrapperSetting['class']=(empty($arr['class']))?'std':$arr['class'];
            $wrapperSetting['style']=(empty($arr['style']))?'':$arr['style'];
            return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Present entry '.$arr['settings']['presentEntry'].' '.$arr['selector']['EntryId'],'generic',$arr['selector'],$arr['settings'],$wrapperSetting);
        } else {
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
            $arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
            return $arr['html'];
        }
    }        
    
    /**
    * This method returns html-string or adds the html content to the 'html'-key of the parameter $presentArr.
    * The html-string presents the selected entry based on the presentation settings.
    * @param array Contains the entry selector. 
    * @return array|string
    */
    public function presentEntry(array $presentArr):array|string
    {
        $html='';
        if (!empty($presentArr['selector']['EntryId'])){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($presentArr['selector'],FALSE);
            if ($entry){
                $presentArr['selector']=$entry;
            }
        }
        $presentArr=$this->mapContainer2presentArr($presentArr);
        $selector=$this->getPresentationSelector($presentArr);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','EntryId') as $setting){
            $presentArr['style']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->style2arr($setting['Content']['Style']??array());
            $presentArr['class']=$setting['Content']['Style class'];
            $cntrArr=explode('|',$setting['Content']['Entry key']);
            if (count($cntrArr)===1){
                // Simple value or array presentation
                $showKey=boolval(intval($setting['Content']['Show key']));
                $key=$setting['Content']['Entry key'];
                $presentationValue=$presentArr['selector'][$key]??'';
                if (is_array($presentationValue)){
                    $flatEntryPart=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($presentationValue);
                    foreach($flatEntryPart as $flatKey=>$flatValue){
                        // filter keys
                        if (empty($setting['Content']['Key filter'])){continue;}
                        if (strpos($flatKey,$setting['Content']['Key filter'])===FALSE){
                            unset($flatEntryPart[$flatKey]);
                        }
                    }
                    if (count($flatEntryPart)==1){
                        $key.=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator().key($flatEntryPart);
                        $presentationValue=current($flatEntryPart);
                    } else {
                        $presentationValue=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntryPart);
                    }
                }
                if (is_array($presentationValue)){
                    // present as table
                    $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($presentationValue);
                    $presentHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>(($showKey)?$key:''),'style'=>$presentArr['style'],'class'=>$presentArr['class']));
                } else {
                    // present as div
                    if ($showKey){
                        $key=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($key);
                        $presentationValue='<b>'.$key.': </b>'.$presentationValue;
                    }
                    $presentHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$presentationValue,'keep-element-content'=>TRUE,'style'=>$presentArr['style'],'class'=>$presentArr['class']));
                }
                $html.=$this->oc['SourcePot\Datapool\Tools\MiscTools']->wrapUTF8($presentHtml);
            } else {
                // App presentation
                $callingClass=array_shift($cntrArr);
                $callingFunction=array_shift($cntrArr);
                $wrapper=array_shift($cntrArr);
                if (empty($wrapper)){
                    $appArr=$this->oc[$callingClass]->$callingFunction($presentArr);
                } else if ($wrapper=='container'){
                    $appArr=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Container '.$setting['EntryId'],'generic',$presentArr['selector'],array('method'=>$callingFunction,'classWithNamespace'=>$callingClass),array());    
                }
                if (is_array($appArr)){$html.=$appArr['html'];} else {$html.=$appArr;}
            }
        }
        if (empty($setting['rowCount'])){
            $this->oc['logger']->log('error','Entry presentation setting missing for "{selectorFolder}"',array('selectorFolder'=>$selector['Folder']));    
        }
        if (isset($presentArr['containerId'])){
            $presentArr['html']=$html;
            $presentArr['wrapperSettings']['hideReloadBtn']=TRUE;
            return $presentArr;
        } else {
            return $html;
        }
    }
    
    private function getPresentationSelector(array $presentArr):array
    {
        if (!empty($presentArr['settings']['presentEntry'])){
            $presentArr['callingFunction'].='|'.$presentArr['settings']['presentEntry'];
        } else if (!empty($presentArr['selector']['function'])){
            $presentArr['callingFunction'].='|'.$presentArr['selector']['function'];
        }
        $selector=array('Source'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Presentation');
        $selector['Folder']=$presentArr['callingClass'].'::'.$presentArr['callingFunction'];
        $guideEntry=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getGuideEntry($selector);
        $selector['Name']='Setting';
        return $selector;
    }
    
    private function mapContainer2presentArr(array $presentArr):array
    {
        if (strcmp($presentArr['callingClass'],'SourcePot\\Datapool\\Foundation\\Container')===0){
            $presentArr['callingClass']=$this->oc['SourcePot\Datapool\Root']->source2class($presentArr['selector']['Source']);
            $presentArr['callingFunction']=$presentArr['settings']['method'];
        }
        return $presentArr;
    }
    
    public function getPresentationSettingHtml(array $arr):array
    {
        $callingClassFunction=explode('::',$arr['selector']['Folder']);
        $entryKeyOptions=$this->appOptions;
        if (isset($this->oc[$callingClassFunction[0]])){
            $entryTemplate=$this->oc[$callingClassFunction[0]]->getEntryTemplate();
            foreach($entryTemplate as $column=>$columnInfo){$entryKeyOptions[$column]=$column;}
        }
        $styleClassOptions=$this->getStyleClassOptions($arr);
        $contentStructure=array('Entry key'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'Name','options'=>$entryKeyOptions,'keep-element-content'=>TRUE),
                                'Key filter'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                'Style class'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'ep-std','options'=>$styleClassOptions,'keep-element-content'=>TRUE),
                                'Style'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                'Show key'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array('No','Yes')),
                                );
        $arr['contentStructure']=$contentStructure;
        $arr['caption']=$arr['selector']['Folder'];
        $arr['selector']['Name']='Setting';
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],array('Source','Group','Folder','Name'),'0','',FALSE);
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $arr;
    }
    
    private function getStyleClassOptions(array $arr):array
    {
        $entryPresentationCss=$GLOBALS['dirs']['media'].'/ep.css';
        $entryPresentationCss=file_get_contents($entryPresentationCss);
        preg_match_all('/(\.)([a-z0-9\-]+)([\{\,\:]+)/',$entryPresentationCss,$matches);
        $options=array();
        foreach($matches[2] as $class){
            $options[$class]=$class;
        }
        return $options;
    }

    public function plotDataProvider(array $arr):array
    {
        $plotData=array('class'=>__CLASS__,'function'=>__FUNCTION__);
        if (isset($_SESSION['plots'][$arr['id']])){
            $plotData=array_merge($plotData,$_SESSION['plots'][$arr['id']]);
            if (!empty($plotData['callingClass']) && !empty($plotData['callingFunction'])){
                if (is_object($this->oc[$plotData['callingClass']])){
                    $callingClass=$plotData['callingClass'];
                    $callingFunction=$plotData['callingFunction'];
                    $plotData=$this->oc[$callingClass]->$callingFunction($plotData);
                } else {
                    $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" called, but callingClass="{callingClass}" is not an object.',$plotData);
                }
            } else {
                $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" called with empty callingClass="{callingClass}" and/or callingClass="{callingFunction}".',$plotData);
            }
        } else {
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" called but $_SESSION vars not set.',$plotData);
        }
        return $plotData;
    }

}
?>