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
                        'delete all entries'=>array('key'=>array('delete all entries'),'title'=>'Delete all selected entries excluding attched files','hasCover'=>TRUE,'element-content'=>'Delete all selected','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'style'=>array(),'excontainer'=>FALSE),
                        'moveUp'=>array('key'=>array('moveUp'),'title'=>'Moves the entry up','hasCover'=>FALSE,'element-content'=>'&#9660;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write'),
                        'moveDown'=>array('key'=>array('moveDown'),'title'=>'Moves the entry down','hasCover'=>FALSE,'element-content'=>'&#9650;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write'),
                        );

    private $appOptions=array('SourcePot\Datapool\Tools\GeoTools|getMapHtml'=>'getMapHtml()',
                       'SourcePot\Datapool\Foundation\Container|entryEditor|container'=>'entryEditor()',
                       'SourcePot\Datapool\Foundation\Container|comments'=>'comments()',
                       'SourcePot\Datapool\Tools\HTMLbuilder|entryLogs'=>'entryLogs()',
                       'SourcePot\Datapool\Foundation\Container|tools'=>'tools()',
                       'SourcePot\Datapool\Tools\MediaTools|getPreview'=>'getPreview()',
                       'SourcePot\Datapool\Foundation\User|ownerAbstract'=>'ownerAbstract()',
                       'SourcePot\Datapool\Foundation\Explorer|getQuicklinksHtml'=>'getQuicklinksHtml()',
                       );
        
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $_SESSION[__CLASS__]['keySelect']=array();
    }
    
    public function init(array $oc)
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
        $keys=array();
        $stdKeys=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($arr['Source']);
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($arr,array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE,'Type'=>FALSE,'Read'=>FALSE,'Write'=>FALSE,'app'=>''));
        $requestId=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE).(empty($arr['standardColumsOnly'])?'ALL':'STD');
        if (isset($_SESSION[__CLASS__][__FUNCTION__][$requestId])){
            // get options from cache
            $keys=$_SESSION[__CLASS__][__FUNCTION__][$requestId];
        } else {
            // get available keys
            $foundEntries=FALSE;
            $keyTestArr=array(array('EntryId',TRUE),array('EntryId',FALSE),array('Date',TRUE),array('Date',TRUE),array('Group',TRUE),array('Group',FALSE),array('Folder',TRUE),array('Folder',TRUE),array('Name',TRUE),array('Name',FALSE));
            foreach($keyTestArr as $keyTest){
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read',$keyTest[0],$keyTest[1],2) as $tmpEntry){
                    $foundEntries=TRUE;
                    $keys+=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($tmpEntry);
                }                
            }
            if ($foundEntries){
                $_SESSION[__CLASS__][__FUNCTION__][$requestId]=$keys;
            } else {
                $keys=$stdKeys;
            }
        }
        $arr['keep-element-content']=TRUE;
        if (empty($arr['addSourceValueColumn'])){
            $arr['options']=array();
        } else {
            $arr['options']=array('useValue'=>'&#9998;');
        }
        if (!empty($arr['addColumns'])){
            $arr['options']+=$arr['addColumns'];
        }
        $sampleValue='';
        foreach($keys as $key=>$value){
            if (!empty($arr['standardColumsOnly']) && !isset($stdKeys[$key])){continue;}
            if ($key==$arr['value'] && !empty($arr['showSample'])){$sampleValue=(is_array($value))?'':$value;}
            $arr['options'][$key]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($key);
        }
        $arr['options']+=$appendOptions;
        $html=$this->select($arr);
        if (!empty($sampleValue)){
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
            } else if (isset($formData['cmd']['delete all entries'])){
                $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntriesOnly($selector);
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
                $entry['Params']['User'][$_SESSION['currentUser']['EntryId']]=array('action'=>$cmd,'timestamp'=>time(),'app'=>$selector['app']);
                $entry['Params']['User'][$_SESSION['currentUser']['EntryId']]['user']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($_SESSION['currentUser'],1);
                $entry['Params']['User'][$_SESSION['currentUser']['EntryId']]['user email']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($_SESSION['currentUser'],7);
                $entry['Params']['User'][$_SESSION['currentUser']['EntryId']]['user mobile']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($_SESSION['currentUser'],9);
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
            }
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->formData2statisticlog($formData);
        }
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
        $tableStyle=array('clear'=>'none','margin'=>'0','min-width'=>'200px','box-shadow'=>'none','border'=>'1px dotted #444');
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
                        'settings'=>array('hideApprove'=>TRUE,'hideDecline'=>TRUE,'hideSelect'=>FALSE,'hideRemove'=>FALSE,'hideDelete'=>FALSE,'hideDownload'=>FALSE,'hideUpload'=>TRUE,'hideDelete'=>FALSE),
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
        if (isset($arr['selector']['Params']['User'][$_SESSION['currentUser']['EntryId']]['action'])){
            $userAction=$arr['selector']['Params']['User'][$_SESSION['currentUser']['EntryId']]['action'];
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
        $html=$this->table(array('matrix'=>$matrix,'hideHeader'=>$arr['hideHeader'],'hideKeys'=>$arr['hideKeys'],'caption'=>FALSE,'keep-element-content'=>TRUE,'title'=>$tableTitle,'style'=>$tableStyle));
        if ($isDebugging){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        return $html;
    }
    
    /**
    * This method returns an html-table containing an overview of the entry content-, processing- and attachment-logs.
    * @return string
    */
    public function entryLogs(array $arr):string
    {
        if (!isset($arr['selector'])){return $this->traceHtml('Problem: Method "'.__FUNCTION__.'" arr[selector] missing.');}
        if (!isset($arr['selector']['Params'])){return $this->traceHtml('Problem: Method "'.__FUNCTION__.'" arr[selector][Params] missing.');}
        $matrix=array();
        $subMatrices=array();
        $standardKeys=array('timestamp'=>FALSE,'time'=>TRUE,'timezone'=>FALSE,'method_0'=>TRUE,'method_1'=>TRUE,'method_2'=>TRUE,'userId'=>TRUE);
        $relevantKeys=array('Attachment log','Content log','Processing log');
        foreach($relevantKeys as $logKey){
            if (!isset($arr['selector']['Params'][$logKey])){continue;}
            foreach($arr['selector']['Params'][$logKey] as $logIndex=>$logArr){
                if (!isset($logArr['timestamp'])){continue;}
                $matrixIndex=$logArr['timestamp'].$logKey;
                while(isset($matrix[$matrixIndex])){$matrixIndex.='.';}
                $matrix[$matrixIndex]['Type']=$logKey;
                foreach($standardKeys as $property=>$isVisible){
                    if ($isVisible){
                        $label=explode('_',ucfirst($property));
                        if (count($label)>1){
                            $caption=array_shift($label);
                            $label=array_pop($label);
                            $subMatrices[$caption][$label]=(empty($logArr[$property]))?'':$logArr[$property];
                        } else {
                            $label=array_pop($label);
                            $matrix[$matrixIndex][$label]=(empty($logArr[$property]))?'':$logArr[$property];
                            if (strcmp($label,'UserId')===0){
                                $userName=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($matrix[$matrixIndex][$label],3);
                                if (!empty($userName)){$matrix[$matrixIndex][$label]=$userName;}
                            }
                        }
                    }
                    unset($arr['selector']['Params'][$logKey][$logIndex][$property]);
                }
                $subMatrices['Message']=$arr['selector']['Params'][$logKey][$logIndex];
                foreach($subMatrices as $caption=>$subMatrix){
                    $matrix[$matrixIndex][$caption]='';
                    foreach($subMatrix as $property=>$propValue){
                        if (is_array($propValue)){$propValue=implode('|',$propValue);}
                        $matrix[$matrixIndex][$caption].=$property.': '.$propValue.'<br/>';
                    }
                }
            }
        }
        krsort($matrix);
        $html=$this->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>TRUE,'caption'=>'Entry logs','keep-element-content'=>TRUE,'style'=>array('clear'=>'none')));
        $html.=$this->oc['SourcePot\Datapool\Tools\CSVtools']->matrix2csvDownload($matrix);
        return $html;
    }

    public function entryListEditor(array $arr):string
    {
        // This method returns a html-table from entries selected by the arr-argument.
        // Each entry is a row in the table and the data stored under the key Content of every entry can be updated and entries can be added or deleted.
        // $arr must contain the key 'contentStructure' which defines the html-elements used in order to show and edit the entry content.
        // Important keys are:
        // 'contentStructure' ... array([Content key]=>array('method'=>[HTMLbuilder method to be used],'class'=>[Style class],....))
        // 'callingClass','callingFunction' ... are used for the form processing
        // 'caption' ... sets the table caption
        if (isset($arr['style'])){$tableArrStyle=$arr['style'];unset($arr['style']);}
        if (empty($arr['caption'])){$arr['caption']='Please provide a caption';}
        if (empty($arr['Name'])){$arr['Name']=$arr['caption'];}
        if (!isset($arr['contentStructure']) || empty($arr['selector']['Source']) || empty($arr['callingClass']) || empty($arr['callingFunction'])){
            throw new \ErrorException('Method '.__FUNCTION__.', required arr key(s) missing.',0,E_ERROR,__FILE__,__LINE__);    
        }
        $isSystemCall=$this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin();
        $matrix=array('New'=>array());
        $arr['movedEntryId']=$this->entry2row($arr,TRUE,FALSE,FALSE,$isSystemCall);
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($arr['selector'],array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'Name'=>FALSE));
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$isSystemCall,'Read','EntryId',TRUE) as $entry){
            $orderedListComps=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($entry['EntryId']);
            if (count($orderedListComps)!==2){continue;}
            $arr['selector']=$entry;
            $matrix[$orderedListComps[0]]=$this->entry2row($arr,FALSE,FALSE,FALSE,$isSystemCall);
        }
        $matrix['New']=$this->entry2row($arr,FALSE,FALSE,TRUE);
        $matrix['New']['trStyle']=array('background-color'=>'#999');
        $tableArr=array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']);
        if (isset($tableArrStyle)){$tableArr['style']=$tableArrStyle;}
        $html=$this->table($tableArr);
        return $html;
    }

    public function entry2row(array $arr,bool $commandProcessingOnly=FALSE,bool $singleRowOnly=FALSE,bool $isNewRow=FALSE,bool $isSystemCall=FALSE):array|string
    {
        $olInfoArr=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListInfo($arr['selector']);
        if ($commandProcessingOnly || $singleRowOnly){
            $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
            if (isset($formData['cmd']['add']) || isset($formData['cmd']['save'])){
                $entry=$arr['selector'];
                $entry['EntryId']=key(current($formData['cmd']));
                if (isset($entry['Content'])){
                    $entry['Content']=array_replace_recursive($entry['Content'],$formData['val'][$entry['EntryId']]['Content']);
                } else {
                    $entry['Content']=$formData['val'][$entry['EntryId']]['Content'];
                }
                $file=FALSE;
                if (isset($formData['files'][$entry['EntryId']])){
                    $flatFile=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($formData['files'][$entry['EntryId']]);
                    $file=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatArrLeaves($flatFile);
                    if ($file['error']!=0){$file=FALSE;}
                }
                if ($file){
                    $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($file,$entry);
                } else {
                    $entry=$this->oc[$olInfoArr['storageClass']]->unifyEntry($entry);
                    $arr['selector']=$this->oc[$olInfoArr['storageClass']]->updateEntry($entry,$isSystemCall,FALSE,TRUE,'');
                }
            } else if (isset($formData['cmd']['delete'])){
                $selector=array('Source'=>$arr['selector']['Source'],'EntryId'=>key(current($formData['cmd'])));
                $this->oc['SourcePot\Datapool\Foundation\Database']->deleteOrderedListEntry($selector);
            } else if (isset($formData['cmd']['moveUp'])){
                $selector=array('Source'=>$arr['selector']['Source'],'EntryId'=>key(current($formData['cmd'])));
                $movedEntryId=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntry($selector,TRUE,$isSystemCall);
            } else if (isset($formData['cmd']['moveDown'])){
                $selector=array('Source'=>$arr['selector']['Source'],'EntryId'=>key(current($formData['cmd'])));
                $movedEntryId=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntry($selector,FALSE,$isSystemCall);
            }
            if ($commandProcessingOnly){
                if (isset($movedEntryId)){return $movedEntryId;} else {return '';}
            }
        }
        $row=array();
        if ($isNewRow){
            $arr['selector']['EntryId']=$olInfoArr['newEntryId'];
        }
        foreach($arr['contentStructure'] as $contentKey=>$elementArr){
            $classWithNamespace=(empty($elementArr['classWithNamespace']))?__CLASS__:$elementArr['classWithNamespace'];
            $method=(empty($elementArr['method']))?'method-arg-missing':$elementArr['method'];
            if (method_exists($classWithNamespace,$method)){
                // if classWithNamespace::method() exists
                if (isset($arr['selector']['Content'][$contentKey])){
                    if (isset($elementArr['element-content'])){
                        $elementArr['element-content']=$arr['selector']['Content'][$contentKey];
                        if (!empty($elementArr['value'])){$elementArr['value']=$elementArr['value'];}
                    } else {
                        $elementArr['value']=$arr['selector']['Content'][$contentKey];
                    }
                }
                if (!$isNewRow && !isset($elementArr['excontainer'])){
                    $elementArr['excontainer']=TRUE;
                }
                $elementArr['callingClass']=$arr['callingClass'];
                $elementArr['callingFunction']=$arr['callingFunction'];
                $elementArr['key']=array($arr['selector']['EntryId'],'Content',$contentKey);
                if (isset($arr['canvasCallingClass'])){$elementArr['canvasCallingClass']=$arr['canvasCallingClass'];}
                $row[$contentKey]=$this->oc[$classWithNamespace]->$method($elementArr);
                if (isset($elementArr['type']) && isset($elementArr['value'])){
                    if (strcmp($elementArr['type'],'hidden')===0){
                        $row[$contentKey].=$elementArr['value'];
                    }
                }                
            } else {
                // if classWithNamespace::method() does not exists
                $row[$contentKey]=$this->traceHtml('Not found: '.$classWithNamespace.'::'.$method.'(arr)');
            }
        }
        if (empty($arr['noBtns'])){
            $row['Buttons']='';
            if (empty($isNewRow)){
                $btnArr=array_replace_recursive($arr,$this->btns['save']);
                $btnArr['key'][]=$arr['selector']['EntryId'];
                $row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
                if (!$singleRowOnly){
                    $btnArr=array_replace_recursive($arr,$this->btns['delete']);
                    $btnArr['key'][]=$arr['selector']['EntryId'];
                    $row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);  
                    if ($olInfoArr['hasMoveDownBtn']){
                        $btnArr=array_replace_recursive($arr,$this->btns['moveDown']);
                        $btnArr['key'][]=$arr['selector']['EntryId'];
                        if (strcmp($arr['selector']['EntryId'],$arr['movedEntryId'])===0){$btnArr['style']=array('background-color'=>'#89fa');}
                        $row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);    
                    }
                    if ($olInfoArr['hasMoveUpBtn']){
                        $btnArr=array_replace_recursive($arr,$this->btns['moveUp']);
                        $btnArr['key'][]=$arr['selector']['EntryId'];
                        if (strcmp($arr['selector']['EntryId'],$arr['movedEntryId'])===0){$btnArr['style']=array('background-color'=>'#89fa');}
                        $row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);    
                    }
                }
            } else {
                $btnArr=array_replace_recursive($arr,$this->btns['add']);
                $btnArr['key'][]=$arr['selector']['EntryId'];
                $row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
            }
            $row['Buttons']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','keep-element-content'=>TRUE,'element-content'=>$row['Buttons'],'style'=>'min-width:150px;'));
        }
        return $row;
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
            return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Present entry '.$arr['settings']['presentEntry'].' '.$arr['selector']['EntryId'],'generic',$arr['selector'],$arr['settings'],array('style'=>array('padding'=>'0','margin'=>'0','border'=>'none')));
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
            $presentArr['style']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->style2arr($setting['Content']['Style']);
            $presentArr['class']=$setting['Content']['Style class'];
            $cntrArr=explode('|',$setting['Content']['Entry key']);
            if (count($cntrArr)===1){
                // Simple value or array presentation
                if (!isset($presentArr['selector'][$setting['Content']['Entry key']])){
                    // Entzry key missing
                    $matrix=array();
                } else if (is_array($presentArr['selector'][$setting['Content']['Entry key']])){
                    // Simple array presentation
                    $resultArr=array();
                    $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($presentArr['selector'][$setting['Content']['Entry key']]);
                    foreach($flatEntry as $flatKey=>$flatValue){
                        if (!empty($setting['Content']['Key filter'])){
                            if (stripos($flatKey,$setting['Content']['Key filter'])===FALSE){continue;}
                        }
                        $resultArr[$flatKey]=$flatValue;
                    }
                    if (count($resultArr)===1){
                        $flatKey=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label(key($resultArr));
                        $matrix=array($flatKey=>array('value'=>current($resultArr)));
                    } else {
                        $entrSubArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($resultArr);
                        $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($entrSubArr);
                    }
                } else {
                    // Simple value presentation
                    $matrix=array($setting['Content']['Entry key']=>array('value'=>$presentArr['selector'][$setting['Content']['Entry key']]));
                }
                $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>empty($setting['Content']['Show key']),'keep-element-content'=>TRUE,'caption'=>'','style'=>$presentArr['style'],'class'=>$presentArr['class']));
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
        $html=$this->oc['SourcePot\Datapool\Tools\MiscTools']->wrapUTF8($html);
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
    
    public function getPresentationSettingHtml(array $arr,bool $isDebugging=FALSE):array
    {
        $debugArr=array('arr'=>$arr);
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
        $arr['selector']['Name']='Setting';
        $arr['caption']=$arr['selector']['Folder'];
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        if ($isDebugging){
            $debugArr['selector']=$arr['selector'];
            $debugArr['contentStructure']=$arr['contentStructure'];
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
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

    /**
    * This plot method returns html-string representing a plot placeholder. The placeholder will be replaced by javerscript.
    * @param array prop Contains plot properties such as 'caption', 'style', 'plotProp', 'axisX'etc...
    * @param array traces is a list of arguments each defining a trace. Use method getTraceTemplate to build a trace
    * @return string
    */
    public function xyTraces2plot(array $prop=array(),...$traces):string
    {
        $plot=$prop;
        if (!empty($prop['traces'])){
            unset($plot['traces']);
            $traces=$prop['traces'];
        }
        foreach($traces as $traceIndex=>$trace){
            $data=array();
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($trace['selector'],$trace['isSystemCall'],'Read',$trace['orderBy'],$trace['isAsc'],$trace['limit'],$trace['offset']) as $entry){
                $trace=$this->addEntry2trace($trace,$entry);
            }
            $plot['traces'][$trace['Name']]=$trace;
            if (isset($trace['stroke'])){$plot['traces'][$trace['Name']]['prop']['stroke']=$trace['stroke'];}
        }
        return $this->plot($plot);
    }
    
    /**
    * This method returns a trace-array template to be used in conjuntion whith plot methods.
    * @return array
    */
    public function getTraceTemplate():array
    {
        $trace=array('Name'=>'Logs',
                     'selector'=>array('Source'=>'signals','Group'=>'signal','Folder'=>'%Logger%'),
                     'isSystemCall'=>FALSE,'orderBy'=>FALSE,'isAsc'=>TRUE,'limit'=>FALSE,'offset'=>0,
                     'x'=>array('key'=>'Content|[]|signal|[]|*|[]|timeStamp','Type'=>'timestamp','Name'=>'Date'),
                     'y'=>array('key'=>'Content|[]|signal|[]|*|[]|value','Type'=>'int','Name'=>'Count'),
                     'data'=>array(),
                     //'type'=>'rectY',
                     //'type'=>'lineY',
                     'type'=>'lineY',
                     );
        return $trace;
    }

    /**
    * This method adds data of an entry to the trace.
    * @param array traces Is the trace. Use method getTraceTemplate to build a trace
    * @param array entry Is the entry to be parsed for new data for the trace
    * @return array Is the updated trace array
    */
    private function addEntry2trace(array $trace,array $entry):array
    {
        $S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
        $compareFlatKeyCompsXY=array('x'=>explode($S,$trace['x']['key']),'y'=>explode($S,$trace['y']['key']));
        $dataXY=array();
        foreach($flatEntry as $flatArrKey=>$flatArrValue){
            $flatKeyComps=explode($S,$flatArrKey);
            $index=array();
            foreach($compareFlatKeyCompsXY as $dim=>$compareFlatKeyComps){
                if (count($flatKeyComps)!==count($compareFlatKeyComps)){continue;}
                $isValid=TRUE;
                foreach($compareFlatKeyComps as $keyIndex=>$compareFlatKeyComp){
                    if (strcmp($compareFlatKeyComp,'*')===0){
                        $index[$dim]=$flatKeyComps[$keyIndex];
                    } else if (strcmp($compareFlatKeyComp,$flatKeyComps[$keyIndex])!==0){
                        $isValid=FALSE;
                        break;
                    }
                }
                if ($isValid){
                    if (!isset($trace[$dim]['Name'])){
                        $trace[$dim]['Name']=$compareFlatKeyComp;
                    }
                    $flatArrValue=match($trace[$dim]['Type']){
                        'int'=>intval($flatArrValue),
                        'float'=>floatval($flatArrValue),
                        'bool'=>boolval($flatArrValue),
                        'string'=>strval($flatArrValue),
                        'date'=>strval($flatArrValue),
                        'timestamp'=>date('Y-m-d H:i:s',$flatArrValue),
                        NULL=>$flatArrValue,
                    };
                    $indexKey=$entry['EntryId'];
                    if (isset($index[$dim])){$indexKey.='_'.$index[$dim];}
                    $dataXY[$indexKey][$trace[$dim]['Name']]=$flatArrValue;
                }
            }
        }
        $trace['datatype']=array('x'=>$trace['x']['Type'],'y'=>$trace['y']['Type']);
        $trace['traceProp']=array('x'=>$trace['x']['Name'],'y'=>$trace['y']['Name']);
        $trace['data']+=$dataXY;
        return $trace;
    }
    
    /**
    * This method returns the plot html placeholder.
    * @param array plot Is the plot definition array compiled by a plot method. It contains alse the traces to be displayed.
    * @return string Is the html plot placeholder
    */
    private function plot(array $plot):string
    {
        // create plot definition
        $plotTemplate=array('caption'=>'Sample chart',
                            'plotProp'=>array('grid'=>TRUE,'color'=>array('legend'=>TRUE),'marginBottom'=>50,'x'=>array('padding'=>0.2)),
                            'axisX'=>array('tickRotate'=>0),
                            'gridX'=>array(),
                            'axisY'=>array(),
                            'gridY'=>array(),
                            'ruleY'=>array(0),
                            'ruleX'=>array(),
                            );
        $plot=array_replace_recursive($plotTemplate,$plot);
        $plot['id']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash(hrtime(TRUE),TRUE);
        $_SESSION[__CLASS__]['plot'][$plot['id']]=$plot;
        //$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($_SESSION[__CLASS__]['plot']);
        // draw plot container
        $elArr=array('tag'=>'div','class'=>'plot','keep-element-content'=>TRUE,'element-content'=>'Plot '.$plot['caption'],'id'=>$plot['id']);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
        $elArr=array('tag'=>'a','class'=>'plot','keep-element-content'=>TRUE,'element-content'=>'SVG','id'=>'svg-'.$plot['id']);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
        $elArr=array('tag'=>'div','class'=>'plot-wrapper','keep-element-content'=>TRUE,'element-content'=>$html);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
        return $html;
    }
    
    /**
    * This method returns the plot data. The method is called by javascript.
    * @param array arr Contains the relevant plot-id, the id of the html plot placeholder.
    * @return array plot data
    */
    public function getPlotData(array $arr):array
    {
        return $_SESSION[__CLASS__]['plot'][$arr['id']];
    }

}
?>