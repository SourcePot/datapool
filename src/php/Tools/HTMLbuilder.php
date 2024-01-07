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
                        'delete'=>array('key'=>array('delete'),'title'=>'Delete entry','hasCover'=>TRUE,'element-content'=>'&coprod;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>array(),'excontainer'=>FALSE),
                        'remove'=>array('key'=>array('remove'),'title'=>'Remove file','hasCover'=>TRUE,'element-content'=>'&xcup;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>TRUE,'style'=>array(),'excontainer'=>FALSE),
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
        
    public function __construct($oc){
        $this->oc=$oc;
    }
    
    public function init($oc){
        $this->oc=$oc;    
    }
    
    public function getBtns($arr){
        if (isset($this->btns[$arr['cmd']])){
            $arr=array_merge($this->btns[$arr['cmd']],$arr);
        }
        return $arr;
    }
    
    public function traceHtml($msg='This has happend:'){
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,5);    
        $html='<p>'.$msg.'</p><ol>';
        for($index=1;$index<4;$index++){
            if (!isset($trace[$index])){break;}
            $html.='<li>'.$trace[$index]['class'].'::'.$trace[$index]['function'].'() '.$trace[$index-1]['line'].'</li>';
        }
        return $html;
    }
    
    public function template2string($template='Hello [p:{{key}}]...',$arr=array('key'=>'world'),$element=array()){
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
    
    private function arr2id($arr){
        $toHash=array($arr['callingClass'],$arr['callingFunction'],$arr['key']);
        return $this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($toHash);
    }
    
    public function element($arr){
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
    }

    public function table($arr,$returnArr=FALSE){
        $html='';
        if (!empty($arr['matrix'])){
            $indexArr=array('x'=>0,'y'=>0);
            $tableArr=array('tag'=>'table','keep-element-content'=>TRUE,'element-content'=>'');
            if (isset($arr['id'])){$tableArr['id']=$arr['id'];}
            if (isset($arr['style'])){$tableArr['style']=$arr['style'];}
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
                if (isset($rowArr['setRowStyle'])){
                    $rowStyle=$rowArr['setRowStyle'];
                    unset($rowArr['setRowStyle']);
                } else {
                    $rowStyle=array();
                }
                if (!empty($arr['skipEmptyRows']) && empty($rowArr)){continue;}
                if (empty($arr['hideKeys'])){$rowArr=array('key'=>$rowLabel)+$rowArr;}
                $trArr=array('tag'=>'tr','keep-element-content'=>TRUE,'element-content'=>'','style'=>$rowStyle);
                $trHeaderArr=array('tag'=>'tr','keep-element-content'=>TRUE,'element-content'=>'');
                foreach($rowArr as $colLabel=>$cell){
                    $indexArr['x']++;
                    $thArr=array('tag'=>'th','element-content'=>ucfirst(strval($colLabel)),'keep-element-content'=>!empty($arr['keep-element-content']));
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
    
    public function select($arr,$returnArr=FALSE){
        // This function returns the HTML-select-element with options based on $arr.
        // Required keys are 'options', 'key', 'callingClass' and 'callingFunction'.
        // Key 'label', 'selected', 'triggerId' are optional.
        // If 'hasSelectBtn' is set, a button will be added which will be clicked if an item is selected.
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
                if (strcmp(strval($name),strval($selected))===0){$optionArr['selected']=TRUE;}
                $optionArr['value']=$name;
                $optionArr['element-content']=$label;
                $optionArr['dontTranslateValue']=TRUE;
                $toReplace['{{options}}'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($optionArr);                
            }
            foreach($toReplace as $needle=>$value){$html=str_replace($needle,$value,$html);}
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
    
    public function tableSelect($arr){
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->getDbInfo() as $table=>$tableDef){$arr['options'][$table]=ucfirst($table);}
        return $this->select($arr);
    }
    
    public function keySelect($arr,$appendOptions=array()){
        if (empty($arr['Source'])){return '';}
        $fileContentKeys=array();
        $keys=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($arr['Source']);
        if (empty($arr['standardColumsOnly'])){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($arr,TRUE) as $tmpEntry){
                if ($tmpEntry['isSkipRow']){continue;}
                $keys=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($tmpEntry);
                break;
            }
        }
        $arr['keep-element-content']=TRUE;
        if (!empty($arr['addSourceValueColumn'])){
            $arr['options']=array('useValue'=>'&xrArr;');
        } else {
            $arr['options']=array();
        }
        if (!empty($arr['addColumns'])){
            $arr['options']+=$arr['addColumns'];
        }
        foreach($keys as $key=>$value){
            $arr['options'][$key]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($key);
        }
        $arr['options']+=$appendOptions;
        return $this->select($arr);
    }
    
    public function canvasElementSelect($arr){
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
    
    public function preview($arr){
        return $this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
    }
    
    public function copy2clipboard($text){
        $html='';
        $id=md5($text.mt_rand(1000,9999));
        $element=array('tag'=>'button','element-content'=>'&#10064;&#10064;','keep-element-content'=>TRUE,'id'=>'clipboard-'.$id,'key'=>array('copy',$id),'excontainer'=>TRUE,'title'=>'copy to clipboard','style'=>array('font-weight'=>'bold'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        $element=array('tag'=>'div','element-content'=>$text,'id'=>$id,'style'=>array('padding'=>'0'));
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        $element=array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        return $html;
    }
    
    public function btn($arr=array()){
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
                if (!empty($arr['requiresFile']) && strpos(strval($arr['selector']['EntryId']),'-guideEntry')===FALSE){
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
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entries($fileArr,$entry);
            } else if (isset($formData['cmd']['delete']) || isset($formData['cmd']['delete all'])){
                $count=$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector);
                if ($count){
                    $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->selectorAfterDeletion($selector);
                    $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateBySelector($selector);
                    $this->oc['SourcePot\Datapool\Foundation\Logger']->log('notice','Entries deleted: "{count}"',array('count'=>$count));         
                } else {
                    $this->oc['SourcePot\Datapool\Foundation\Logger']->log('notice','Nothing deleted. Either an empty selection or missing write access');         
                }
            } else if (isset($formData['cmd']['remove'])){
                $entry=$formData['selector'];
                if (!empty($entry['EntryId'])){
                    $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
                    if (is_file($file)){unlink($file);}
                    if (isset($entry['Params']['File'])){unset($entry['Params']['File']);}
                    $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
                }
            } else if (isset($formData['cmd']['delete all entries'])){
                $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntriesOnly($selector);
            } else if (isset($formData['cmd']['select'])){
                $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateBySelector($selector);
            } else if (isset($formData['cmd']['edit'])){
                $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setEditMode($selector,TRUE);
            } else if (isset($formData['cmd']['show'])){
                $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setEditMode($selector,FALSE);
            } else if (isset($formData['cmd']['export'])){
                $selectors=array($selector);
                $pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
                $fileName=date('Y-m-d H_i_s').' '.$pageSettings['pageTitle'].' '.current($selectors)['Source'].' dump.zip';
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->downloadExportedEntries($selectors,FALSE,10000000000,$fileName);
            }
        }
        return $html;
    }
        
    public function app($arr){
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
    
    public function emojis($arr=array()){
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
    
    public function integerEditor($arr){
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
            $context=array('key'=>$arr['key'],'statistics'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics));
            $this->oc['SourcePot\Datapool\Foundation\Logger']->log('notice','{key}-key processed: {statistics}',$context);    
        }
        $hideHeader=(isset($arr['hideHeader']))?$arr['hideHeader']:TRUE;
        $hideKeys=(isset($arr['hideKeys']))?$arr['hideKeys']:TRUE;
        $html.='</fieldset>';
        return $html;
    }
    
    public function setAccessByte($arr){
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
    public function entryControls($arr,$isDebugging=FALSE){
        if (!isset($arr['selector'])){return 'Selector missing';}
        $debugArr=array('arr_in'=>$arr);
        $arr['html']='';
        if (!isset($arr['selector']['Content'])){
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
        }
        if (empty($arr['selector'])){return 'Entry does not exsist (yet).';}
        $template=array('callingClass'=>__CLASS__,
                        'callingFunction'=>__FUNCTION__,
                        'hideHeader'=>TRUE,
                        'hideKeys'=>TRUE,
                        'previewStyle'=>array('max-height'=>100,'max-width'=>200),
                        );
        $arr=array_replace_recursive($template,$arr);
        $matrix=array('Preview'=>array('Value'=>''),'Btns'=>array('Value'=>''));
        if (empty($arr['hidePreview'])){
            $previewArr=$arr;
            $previewArr['settings']['style']=$arr['previewStyle'];
            $previewArr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($previewArr);
            $matrix['Preview']['Value'].=$previewArr['html'];
        }
        foreach(array('select','remove','delete','download','upload') as $cmd){
            $ucfirstCmd=ucfirst($cmd);
            if (!empty($arr['settings']['hide'.$ucfirstCmd])){continue;}
            $arr['excontainer']=TRUE;
            $arr['cmd']=$cmd;
            $matrix['Btns']['Value'].=$this->btn($arr);
            $debugArr['btn'][]=$arr;
        }
        $matrix['Btns']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$matrix['Btns']['Value'],'keep-element-content'=>TRUE,'style'=>array('width'=>'max-content')));
        $html=$this->table(array('matrix'=>$matrix,'hideHeader'=>$arr['hideHeader'],'hideKeys'=>$arr['hideKeys'],'caption'=>FALSE,'keep-element-content'=>TRUE,'style'=>array('clear'=>'none','margin'=>'0','min-width'=>'200px','box-shadow'=>'none','border'=>'1px dotted #444')));
        if ($isDebugging){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        return $html;
    }
    
    /**
    * This method returns an html-table containing an overview of the entry content-, processing- and attachment-logs.
    * @return string
    */
    public function entryLogs($arr){
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

    public function entryListEditor($arr){
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
        if (empty($arr['contentStructure']) || empty($arr['selector']['Source']) || empty($arr['callingClass']) || empty($arr['callingFunction'])){
            throw new \ErrorException('Method '.__FUNCTION__.', required arr key(s) missing.',0,E_ERROR,__FILE__,__LINE__);    
        }
        $isSystemCall=$this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin();
        $matrix=array('New'=>array());
        $arr['movedEntryId']=$this->entry2row($arr,TRUE,FALSE,FALSE,$isSystemCall);
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($arr['selector'],array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'Name'=>FALSE,'Type'=>FALSE));
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$isSystemCall,'Read','EntryId',TRUE) as $entry){
            $orderedListComps=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($entry['EntryId']);
            if (count($orderedListComps)!==2){continue;}
            $arr['selector']=$entry;
            $matrix[$orderedListComps[0]]=$this->entry2row($arr,FALSE,FALSE,FALSE,$isSystemCall);
        }
        $matrix['New']=$this->entry2row($arr,FALSE,FALSE,TRUE);
        $matrix['New']['setRowStyle']=array('background-color'=>'#ddf;');
        $tableArr=array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']);
        if (isset($tableArrStyle)){$tableArr['style']=$tableArrStyle;}
        $html=$this->table($tableArr);
        return $html;
    }

    public function entry2row($arr,$commandProcessingOnly=FALSE,$singleRowOnly=FALSE,$isNewRow=FALSE,$isSystemCall=FALSE){
        if (isset($arr['selector']['Class'])){
            $dataStorageClass='SourcePot\Datapool\Foundation\Filespace';
        } else {
            $dataStorageClass='SourcePot\Datapool\Foundation\Database';    
        }    
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
                    $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entries($file,$entry);
                } else {
                    $entry=$this->oc[$dataStorageClass]->unifyEntry($entry);
                    $arr['selector']=$this->oc[$dataStorageClass]->updateEntry($entry,$isSystemCall,FALSE,TRUE,'');
                }
            } else if (isset($formData['cmd']['delete'])){
                $selector=array('Source'=>$arr['selector']['Source'],'EntryId'=>key(current($formData['cmd'])));
                $this->oc[$dataStorageClass]->deleteEntries($selector);
            } else if (isset($formData['cmd']['moveUp'])){
                $selector=array('Source'=>$arr['selector']['Source'],'EntryId'=>key(current($formData['cmd'])));
                $movedEntryId=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntry($selector,TRUE,$isSystemCall);
            } else if (isset($formData['cmd']['moveDown'])){
                $selector=array('Source'=>$arr['selector']['Source'],'EntryId'=>key(current($formData['cmd'])));
                $movedEntryId=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntry($selector,FALSE,$isSystemCall);
            }
            if ($commandProcessingOnly){
                if (isset($movedEntryId)){
                    return $movedEntryId;
                } else {
                    return '';
                }
            }
        }
        $row=array();
        if ($isNewRow){
            $arr['selector']['Content']=array();
            $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],$relevantKeys=array('Source','Group','Folder','Name','Type'),'0','',TRUE);
            $newIndex=(isset($arr['selector']['rowCount']))?$arr['selector']['rowCount']+1:1;
            $arr['selector']['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Database']->addOrderedListIndexToEntryId($arr['selector']['EntryId'],$newIndex);
            $this->oc['SourcePot\Datapool\Foundation\Database']->orderedEntryListCleanup($arr['selector'],FALSE);
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
                if (!$isNewRow){$elementArr['excontainer']=TRUE;}
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
                    if (empty($arr['selector']['isLast'])){
                        $btnArr=array_replace_recursive($arr,$this->btns['moveUp']);
                        $btnArr['key'][]=$arr['selector']['EntryId'];
                        if (strcmp($arr['selector']['EntryId'],$arr['movedEntryId'])===0){$btnArr['style']=array('background-color'=>'#89fa');}
                        $row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);    
                    }
                    if (empty($arr['selector']['isFirst'])){
                        $btnArr=array_replace_recursive($arr,$this->btns['moveDown']);
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
    
    public function row2table($row,$caption='Row as table',$flip=FALSE){
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
    
    public function value2tabelCellContent($html,$arr=array()){
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
        $presentArr=$this->mapContainer2presentArr($presentArr);
        $selector=$this->getPresentationSelector($presentArr);
        if (!empty($presentArr['selector']['EntryId'])){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($presentArr['selector'],FALSE);
            if ($entry){
                $presentArr['selector']=$entry;
            }            
        }
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','EntryId') as $setting){
            $rowCount=$setting['rowCount'];
            $presentArr['style']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->style2arr($setting['Content']['Style']);
            $presentArr['class']=$setting['Content']['Style class'];
            $cntrArr=explode('|',$setting['Content']['Entry key']);
            if (count($cntrArr)===1){
                // Simple value or array presentation
                if (is_array($presentArr['selector'][$setting['Content']['Entry key']])){
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
        if (empty($rowCount)){
            $this->oc['SourcePot\Datapool\Foundation\Logger']->log('error','Entry presentation setting missing for "{selectorFolder}"',array('selectorFolder'=>$selector['Folder']));    
        }
        if (isset($presentArr['containerId'])){
            $presentArr['html']=$html;
            $presentArr['wrapperSettings']['hideReloadBtn']=TRUE;
            return $presentArr;
        } else {
            return $html;
        }
    }
    
    private function getPresentationSelector($presentArr){
        $selector=array('Source'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Presentation');
        $selector['Folder']=$presentArr['callingClass'].'::'.$presentArr['callingFunction'];
        $guideEntry=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getGuideEntry($selector);
        $selector['Name']='Setting';
        return $selector;
    }
    
    private function mapContainer2presentArr($presentArr){
        if (strcmp($presentArr['callingClass'],'SourcePot\\Datapool\\Foundation\\Container')===0){
            $presentArr['callingClass']=$this->oc['SourcePot\Datapool\Root']->source2class($presentArr['selector']['Source']);
            $presentArr['callingFunction']=$presentArr['settings']['method'];
        }
        return $presentArr;
    }
    
    public function getPresentationSettingHtml($arr,$isDebugging=FALSE){
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
    
    private function getStyleClassOptions($arr){
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
    * This method creates a chart consisting of plots from events, events can consist of more than on signal.
    * Every key of the event-array which is not a standard-key is a signal-key with the corresponding value..
    * @param array  $events     Contains the events to be displayed 
    * @param array  $styles     Is an arrey of styles of the different chart building parts
    * @return string
    */
    public function simpleEventChart($events=FALSE,$styles=array('chart'=>array(),'plot'=>array(),'bar'=>array(),'caption'=>array(),'xLable'=>array(),'yLable'=>array())){
        $stdKeys=array('timestamp'=>TRUE,'datetime'=>TRUE,'timezone'=>TRUE,'x'=>TRUE);
        $stylesTemplate=array();
        $stylesTemplate['chart']=array('position'=>'relative','margin-top'=>'50px');
        $stylesTemplate['plot']=array('position'=>'relative','width'=>360,'height'=>50,'margin-bottom'=>'2.5em','border'=>'1px solid #444');
        $stylesTemplate['bar']=array('position'=>'absolute','width'=>5);
        $stylesTemplate['xLable']=array('position'=>'absolute','font-size'=>'0.8em','width'=>'7em','height'=>'4em','text-align'=>'left');
        $stylesTemplate['yLable']=array('position'=>'absolute','font-size'=>'0.8em','width'=>'7em','height'=>'1.2em','text-align'=>'right');
        $stylesTemplate['caption']=array('position'=>'absolute','top'=>'-2em','width'=>'100%','text-align'=>'center');
        $stylesTemplate['plot']['margin-left']=$stylesTemplate['yLable']['width'];
        foreach($stylesTemplate as $key=>$styleTemplate){
            if (isset($styles[$key])){
                $styles[$key]=array_replace_recursive($styleTemplate,$styles[$key]);
            } else {
                $styles[$key]=$styleTemplate;
            }
        }
        // get sample events if events are not provided
        $xMax=intval(round(200*pi()));
        if ($events===FALSE){
            for($index=0;$index<100;$index++){
                $x=mt_rand(0,$xMax)/100;
                $events[]=array('timeStamp'=>mt_rand(1700246000,1700247000),'Signal A'=>mt_rand(-100,100));
                $events[]=array('timestamp'=>1700246000+(360*$x),'Signal B'=>1*sin($x));
            }
        }
        // get parameters
        $labelEvents=array();
        $normEvents=array();
        $params=array();
        foreach($events as $index=>$event){
            $normEvent=$this->normalizeEvent($event);
            // x-index
            if (isset($normEvent['x'])){
                // nothing to do
            } else if (isset($normEvent['timestamp'])){
                $normEvent['x']=$normEvent['timestamp'];
            } else {
                $normEvent['x']=$index;
            }
            // x-range
            if (!isset($params['xMin'])){$params['xMin']=$normEvent['x'];$labelEvents['x']['params']['xMin']=$normEvent;}
            if (!isset($params['xMax'])){$params['xMax']=$normEvent['x'];$labelEvents['x']['params']['xMax']=$normEvent;}
            if ($params['xMin']>$normEvent['x']){$params['xMin']=$normEvent['x'];$labelEvents['x']['params']['xMin']=$normEvent;}
            if ($params['xMax']<$normEvent['x']){$params['xMax']=$normEvent['x'];$labelEvents['x']['params']['xMax']=$normEvent;}
            $normEvents[$index]=$normEvent;
            // y-range
            foreach($normEvent as $signalKey=>$signal){
                if (isset($stdKeys[$signalKey])){continue;}
                if (!isset($params[$signalKey]['min'])){$params[$signalKey]['min']=$signal['value'];}
                if (!isset($params[$signalKey]['max'])){$params[$signalKey]['max']=$signal['value'];}
                if ($params[$signalKey]['min']>$signal['value']){$params[$signalKey]['min']=$signal['value'];}
                if ($params[$signalKey]['max']<$signal['value']){$params[$signalKey]['max']=$signal['value'];}
                $labelEvents['y'][$signalKey]['yMin']=$params[$signalKey]['min'];
                $labelEvents['y'][$signalKey]['yMax']=$params[$signalKey]['max'];
            }
        }
        if (empty($params)){return '';}
        if ($params['xMin']===$params['xMax']){$params['xMax']=$params['xMin']+1;}
        // create chart
        $html='';
        // scaler
        $htmlArr=array();
        $hasLabel=array();
        $lastSignalKey=FALSE;
        $xScale=intval($styles['plot']['width'])/($params['xMax']-$params['xMin']);
        foreach($normEvents as $index=>$event){
            foreach($event as $signalKey=>$signal){
                if (isset($stdKeys[$signalKey])){continue;}
                if (!isset($htmlArr[$signalKey])){$htmlArr[$signalKey]='';}
                $styles['bar']['background-color']='#f004';   
                // add bar -> html-div
                $yScale=FALSE;
                $yRange=$params[$signalKey]['max']-$params[$signalKey]['min'];
                if ($params[$signalKey]['max']===$params[$signalKey]['min']){
                    if ($params[$signalKey]['min']<0){
                        $params[$signalKey]['max']=0;
                    } else if ($params[$signalKey]['min']>0){
                        $params[$signalKey]['min']=0;
                    } else {
                        $yScale=1;
                    }
                }
                if ($yScale===FALSE){
                    $yScale=intval($styles['plot']['height'])/$params[$signalKey]['max']-$params[$signalKey]['min'];
                }
                $styles['bar']['border-top']='1px solid #000';
                $styles['bar']['left']=round($xScale*($event['x']-$params['xMin'])-0.5*$styles['bar']['width']);
                if ($params[$signalKey]['max']>0 && $params[$signalKey]['min']<0){
                    if ($signal['value']<0){
                        $styles['bar']['height']=ceil(-$yScale*$signal['value']);   
                        $styles['bar']['bottom']=round($yScale*($signal['value']-$params[$signalKey]['min']));
                        $styles['bar']['border-bottom']='1px solid #000';
                        $styles['bar']['background-color']='#80f4';   
                    } else {
                        $styles['bar']['height']=ceil($yScale*$signal['value']);   
                        $styles['bar']['bottom']=round(-$yScale*$params[$signalKey]['min']);
                    }
                } else {
                    $styles['bar']['height']=ceil($yScale*($signal['value']-$params[$signalKey]['min']));
                    $styles['bar']['bottom']=0;
                }
                $element=array('tag'=>'div','element-content'=>' ','keep-element-content'=>TRUE,'style'=>$styles['bar']);
                $element['title']=(isset($event['datetime']))?$event['datetime']:$event['x'];
                $element['title'].=' | '.$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($signal['signal']);
                $htmlArr[$signalKey].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                // add y-label and captions
                if (!isset($hasLabel[$signalKey])){
                    $hasLabel[$signalKey]=TRUE;
                    foreach($labelEvents['y'][$signalKey] as $labelIndex=>$labelValue){
                        $label=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($labelValue,0);
                        $styles['yLable']['bottom']=ceil($yScale*($labelValue-$params[$signalKey]['min']));
                        $styles['yLable']['left']='-'.strval($styles['yLable']['width']);
                        $styles['yLable']['height']='1em';
                        $element=array('tag'=>'p','element-content'=>$label,'keep-element-content'=>TRUE,'style'=>$styles['yLable']);
                        $htmlArr[$signalKey].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                    }
                    $element=array('tag'=>'h3','element-content'=>$signalKey,'keep-element-content'=>TRUE,'style'=>$styles['caption']);
                    $htmlArr[$signalKey].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);                                    
                }
                $lastSignalKey=$signalKey;
            }
        }
        // add x-label
        if ($lastSignalKey){
            foreach($labelEvents['x']['params'] as $labelIndex=>$labelEvent){
                $label=strval((isset($labelEvent['datetime']))?$labelEvent['datetime']:$labelEvent['x']);
                $styles['xLable']['bottom']='-'.strval($styles['xLable']['height']);
                $styles['xLable']['left']=round($xScale*($labelEvent['x']-$params['xMin'])-0.5*$styles['bar']['width']);
                $element=array('tag'=>'p','element-content'=>$label,'keep-element-content'=>TRUE,'style'=>$styles['xLable']);
                $htmlArr[$lastSignalKey].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
            }
        }
        // compile plots to chart
        foreach($htmlArr as $signalKey=>$barsHtml){
            $element=array('tag'=>'div','element-content'=>$barsHtml,'keep-element-content'=>TRUE,'style'=>$styles['plot']);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        }
        $element=array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>$styles['chart']);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        return $html;
    }
    
    private function normalizeEvent($event){
        $normEvent=array();
        $standardKeys=array('timeStamp'=>'timestamp','timestamp'=>'timestamp','dateTime'=>'datetime','datetime'=>'datetime','timeZone'=>'timezone','timezone'=>'timezone','X'=>'x','x'=>'x');
        foreach($standardKeys as $testKey=>$stdKey){
            if (isset($event[$testKey])){
                $normEvent[$stdKey]=$event[$testKey];
                unset($event[$testKey]);
            }
        }
        // normalize keys
        if (!isset($normEvent['timezone'])){
            $normEvent['timezone']='UTC';
        }
        $timezoneObj=new \DateTimeZone($normEvent['timezone']);
        // get datetime-object
        $datetimeObj=FALSE;
        if (isset($normEvent['datetime'])){
            $datetimeObj=new \DateTime($normEvent['datetime'],$timezoneObj);
        } else if (isset($normEvent['timestamp'])){
            $datetimeObj=new \DateTime('@'.$normEvent['timestamp'],$timezoneObj);
        }
        // add datetime and timestamp
        if ($datetimeObj){
            $normEvent['datetime']=$datetimeObj->format('Y-m-d H:i:s');
            $normEvent['timestamp']=$datetimeObj->getTimestamp();
        }
        // normalize value
        foreach($event as $signalKey=>$signal){
            if (is_bool($signal)){
                $normEvent[$signalKey]=array('value'=>intval($signal),'signal'=>$signal);
            } else {
                $normEvent[$signalKey]=array('value'=>floatval($signal),'signal'=>$signal);
            }
        }
        return $normEvent;
    }
    
}
?>