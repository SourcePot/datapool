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

    private const SHOW_FILTER_OPTION_COUNT=20;
    private const MAX_SELECT_OPTION_COUNT=10000;
    private const MAX_PREV_WIDTH=300;
    private const MAX_PREV_HEIGHT=150;

    private const APPROVE_STYLE=['border'=>'2px solid #0f0'];
    private const DECLINE_STYLE=['border'=>'2px solid #f00'];

    private const SET_ACCESS_BYTE_INFO="Security relevant setting!\nNew Priviledges will become active at the next user login.";

    private const PRESENTATION_SETTINGS_INFO="<b>SECURITY ADVICE:</b>\nIf a \"Key filter\" leads to multiple entries with sub-keys \"tag\" and \"element-content\" set, an html-element will bey created from all sub-keys.\nThere is no filtering applied. Ensure the values are trustworthy to prevent XSS attacks.\n\n<b>Example:</b>\n\"Entry key\" = 'Params'\n\"Key filter\" = 'Item link'\n...will create a html button, if the respective feed item entry created by class Feeds contains a link. This is safe as long as the class Feeds is safe and trustworthy.";
    
    private const BUTTONS=[
        'test'=>['key'=>['test'],'title'=>'Test run','hasCover'=>FALSE,'element-content'=>'Test','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE,'excontainer'=>FALSE],
        'edit'=>['key'=>['edit'],'title'=>'Edit','hasCover'=>FALSE,'element-content'=>'&#9998;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>FALSE,'style'=>[],'excontainer'=>FALSE],
        'show'=>['key'=>['show'],'title'=>'Show','hasCover'=>FALSE,'element-content'=>'&#10003;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>FALSE,'style'=>[],'excontainer'=>FALSE],
        'print'=>['key'=>['print'],'title'=>'Print','hasCover'=>FALSE,'element-content'=>'&#10064;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE,'style'=>[],'excontainer'=>TRUE],
        'run'=>['key'=>['run'],'title'=>'Run','hasCover'=>FALSE,'element-content'=>'Run','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE,'excontainer'=>TRUE],
        'add'=>['key'=>['add'],'title'=>'Add this entry','hasCover'=>FALSE,'element-content'=>'+','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE],
        'save'=>['key'=>['save'],'title'=>'Save this entry','hasCover'=>FALSE,'element-content'=>'&check;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>FALSE],
        'upload'=>['key'=>['upload'],'title'=>'Upload file','hasCover'=>FALSE,'element-content'=>'Upload','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>FALSE,'excontainer'=>FALSE],
        'download'=>['key'=>['download'],'title'=>'Download attached file','hasCover'=>FALSE,'element-content'=>'&#8892;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','requiresFile'=>TRUE,'excontainer'=>TRUE],
        'download all'=>['key'=>['download all'],'title'=>'Download all attached file','hasCover'=>FALSE,'element-content'=>'&#8892;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','requiresFile'=>FALSE,'excontainer'=>TRUE],
        'export'=>['key'=>['export'],'title'=>'Export all selected entries','hasCover'=>FALSE,'element-content'=>'&#9842;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','requiresFile'=>FALSE,'excontainer'=>TRUE],
        'select'=>['key'=>['select'],'title'=>'Select entry','hasCover'=>FALSE,'element-content'=>'&#10022;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','excontainer'=>TRUE],
        'approve'=>['key'=>['approve'],'title'=>'Approve entry','hasCover'=>FALSE,'element-content'=>'&check;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>['font-size'=>'2rem','color'=>'green'],'excontainer'=>FALSE],
        'decline'=>['key'=>['decline'],'title'=>'Decline entry','hasCover'=>FALSE,'element-content'=>'&#10006;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>['font-size'=>'2rem','color'=>'red'],'excontainer'=>FALSE],
        'delete'=>['key'=>['delete'],'title'=>'Delete entry','hasCover'=>TRUE,'element-content'=>'&coprod;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>[],'excontainer'=>FALSE],
        'remove'=>['key'=>['remove'],'title'=>'Remove attched file only','hasCover'=>TRUE,'element-content'=>'&xcup;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>TRUE,'style'=>[],'excontainer'=>FALSE],
        'delete all'=>['key'=>['delete all'],'title'=>'Delete all selected entries','hasCover'=>TRUE,'element-content'=>'Delete all selected','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'style'=>[],'excontainer'=>FALSE],
        'moveUp'=>['key'=>['moveUp'],'title'=>'Moves the entry up','hasCover'=>FALSE,'element-content'=>'&#9660;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>['margin'=>0]],
        'moveDown'=>['key'=>['moveDown'],'title'=>'Moves the entry down','hasCover'=>FALSE,'element-content'=>'&#9650;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>['margin'=>0]],
        'archive'=>['key'=>['archive'],'title'=>'Sets the expiry date to latest possible','hasCover'=>FALSE,'element-content'=>'&infin;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>[],'excontainer'=>FALSE],
    ];

    private const APP_OPTIONS=[
        'SourcePot\Datapool\Tools\GeoTools|getMapHtml'=>'getMapHtml()',
        'SourcePot\Datapool\Foundation\Container|entryEditor|container'=>'entryEditor()',
        'SourcePot\Datapool\Foundation\Container|comments'=>'comments()',
        'SourcePot\Datapool\Tools\HTMLbuilder|entryControls'=>'entryControls()',
        'SourcePot\Datapool\Tools\HTMLbuilder|deleteBtn'=>'deleteBtn()',
        'SourcePot\Datapool\Tools\HTMLbuilder|downloadBtn'=>'downloadBtn()',
        'SourcePot\Datapool\Tools\HTMLbuilder|selectBtn'=>'selectBtn()',
        'SourcePot\Datapool\Tools\HTMLbuilder|archiveBtn'=>'archiveBtn()',
        'SourcePot\Datapool\Tools\MediaTools|getPreview'=>'getPreview()',
        'SourcePot\Datapool\Foundation\User|ownerAbstract'=>'ownerAbstract()',
    ];
        
    private $keyCache=[];
    private $cssVars=[];

    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
        $this->cssVars=$this->oc['SourcePot\Datapool\AdminApps\Settings']->getCssVars();
    }
    
    public function getBtns(array $arr):array
    {
        if (isset(self::BUTTONS[$arr['cmd']])){
            $arr=array_merge(self::BUTTONS[$arr['cmd']],$arr);
        }
        return $arr;
    }
    
    public function traceHtml(string $msg='This has happend:'):string
    {
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,5);
        $mgHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>$msg]);   
        $html='';
        for($index=1;$index<4;$index++){
            if (!isset($trace[$index])){break;}
            $html.='<li>'.$trace[$index]['class'].'::'.$trace[$index]['function'].'() '.$trace[$index-1]['line'].'</li>';
        }
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'ol','element-content'=>$html,'keep-element-content'=>TRUE]);
        $html=$mgHtml.$html;
        return $html;
    }
    
    private function arr2id(array $arr):string
    {
        $toHash=[$arr['callingClass'],$arr['callingFunction'],$arr['key']];
        return $this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($toHash);
    }
    
    public function element(array $arr):string
    {
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
    }

    public function table(array $arr,bool $returnArr=FALSE):string|array
    {
        $html='';
        $styles=['trStyle'=>[]];
        if (!empty($arr['matrix'])){
            $indexArr=['x'=>0,'y'=>0];
            $tableArr=['tag'=>'table','keep-element-content'=>TRUE,'element-content'=>''];
            if (isset($arr['id'])){$tableArr['id']=$arr['id'];}
            if (isset($arr['style'])){$tableArr['style']=$arr['style'];}
            if (isset($arr['title'])){$tableArr['title']=$arr['title'];}
            $tbodyArr=['tag'=>'tbody','keep-element-content'=>TRUE];
            if (isset($arr['class'])){
                $tableArr['class']=$arr['class'];
                $tbodyArr['class']=$arr['class'];
            }
            if (!empty($arr['caption'])){
                $captionArr=['tag'=>'caption','keep-element-content'=>TRUE,'element-content'=>$arr['caption']];
                if (isset($arr['class'])){$captionArr['class']=$arr['class'];}
                $tableArr['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($captionArr);
            }
            foreach($arr['matrix'] as $rowLabel=>$rowArr){
                $indexArr['x']=0;
                $indexArr['y']++;
                $rowArr['trStyle']=(isset($rowArr['trStyle']))?$rowArr['trStyle']:$styles['trStyle'];
                if (!empty($arr['skipEmptyRows']) && empty($rowArr)){continue;}
                if (empty($arr['hideKeys'])){$rowArr=[' Key '=>$rowLabel]+$rowArr;}
                $trArr=['tag'=>'tr','keep-element-content'=>TRUE,'element-content'=>'','style'=>$rowArr['trStyle']];
                $trHeaderArr=['tag'=>'tr','keep-element-content'=>TRUE,'element-content'=>''];
                foreach($rowArr as $colLabel=>$cell){
                    if (is_object($cell)){$cell='{object}';}
                    if (isset($styles[$colLabel])){continue;}
                    if (empty($arr['thKeepCase'])){
                        $colLabel=ucfirst(strval($colLabel));
                    } else {
                        $colLabel=strval($colLabel);
                    }
                    $indexArr['x']++;
                    $thArr=['tag'=>'th','element-content'=>$colLabel,'keep-element-content'=>!empty($arr['keep-element-content'])];
                    $tdArr=['tag'=>'td','cell'=>$indexArr['x'].'-'.$indexArr['y'],'keep-element-content'=>!empty($arr['keep-element-content'])];
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
        if ($returnArr){return ['html'=>$html];} else {return $html;}
    }
    
    public function select(array $arr,bool $returnArr=FALSE):string|array
    {
        if (!isset($arr['key'])){
            throw new \ErrorException('Function '.__FUNCTION__.': Missing key-key in argument arr',0,E_ERROR,__FILE__,__LINE__);
        }
        if (is_array($arr['key'])){
            $key=implode(\SourcePot\Datapool\Root::ONEDIMSEPARATOR,$arr['key']);} else {$key=$arr['key'];
        }
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
            if (isset($arr['selected'])){
                $selected=$arr['selected'];
                unset($arr['selected']);
            }
            if (isset($arr['value'])){
                $selected=$arr['value'];
                unset($arr['value']);
            }
            if (!isset($arr['options'][$selected]) && !empty($selected)){
                $arr['options'][$selected]=$selected;
            }
            $toReplace=[];
            $selectArr=$arr;
            if (!empty($arr['hasSelectBtn'])){$selectArr['trigger-id']=$triggerId;}
            $selectArr['tag']='select';
            $selectArr['id']=$inputId;
            $selectArr['value']=$selected;
            $selectArr['element-content']='{{options}}';
            $selectArr['keep-element-content']=TRUE;
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($selectArr);
            // create options
            $toReplace['{{options}}']='';
            $optionCount=0;
            foreach($arr['options'] as $name=>$label){
                $optionArr=$arr;
                $optionArr['tag']='option';
                if (strval($name)===strval($selected)){$optionArr['selected']=TRUE;}
                if (strval($name)==='useValue'){$optionArr['title']='Use value provided';}
                $optionArr['value']=$name;
                $optionArr['element-content']=$label;
                $optionArr['dontTranslateValue']=TRUE;
                $toReplace['{{options}}'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($optionArr);
                $optionCount++;
                if ($optionCount>=self::MAX_SELECT_OPTION_COUNT){
                    $this->oc['logger']->log('notice','Html selector reached option limit. Not all options are shown.',[]);
                    $noticeOption=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'option','value'=>'','element-content'=>'Cut off: option limit '.self::MAX_SELECT_OPTION_COUNT.' reached!','style'=>['border-bottom'=>'#a00','color'=>'#f00','font-weight'=>'bold'],'title'=>'LIMIT REACHED']);
                    $toReplace['{{options}}']=$noticeOption.$toReplace['{{options}}'];
                    break;
                }            
            }
            foreach($toReplace as $needle=>$value){
                $html=str_replace($needle,$value,$html);
            }
            if (count($arr['options'])>self::SHOW_FILTER_OPTION_COUNT && !empty($selectArr['id'])){
                $filterArr=['tag'=>'input','type'=>'text','placeholder'=>'filter','key'=>['filter'],'class'=>'filter','id'=>'filter-'.$selectArr['id'],'excontainer'=>TRUE,'style'=>['width'=>'2rem','min-width'=>'unset'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($filterArr);
                $countArr=['tag'=>'p','element-content'=>count($arr['options']),'class'=>'filter','id'=>'count-'.$selectArr['id']];
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($countArr);
            }
            if (isset($selectArr['trigger-id'])){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'*','key'=>['select'],'value'=>$key,'id'=>$triggerId,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']]);
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
    
    public function keySelect(array $arr,array $appendOptions=[]):string
    {
        if (empty($arr['Source'])){return '';}
        $arr['value']=(isset($arr['value']))?$arr['value']:'';
        $stdKeys=$keys=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($arr['Source']);
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($arr,['Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE,'Type'=>FALSE,'Read'=>FALSE,'Write'=>FALSE,'app'=>'']);
        $requestId=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE).(empty($arr['standardColumsOnly'])?'ALL':'STANDARD');
        if (isset($this->keyCache[__CLASS__][__FUNCTION__][$requestId])){
            // get options from cache
            $keys=$this->keyCache[__CLASS__][__FUNCTION__][$requestId];
        } else {
            // get available keys
            $rowCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,TRUE,'Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$removeGuideEntries=TRUE,$isDebugging=FALSE);
            for($i=0;$i<2;$i++){
                $offset=($rowCount>1)?mt_rand(0,$rowCount-1):0;
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read',FALSE,TRUE,1,$offset) as $tmpEntry){
                    $keys+=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($tmpEntry);
                }                
            }
            $this->keyCache[__CLASS__][__FUNCTION__][$requestId]=$keys;
        }
        $arr['keep-element-content']=TRUE;
        $arr['options']=(empty($arr['addSourceValueColumn']))?[]:['useValue'=>'&#9998;'];
        $arr['options']+=(empty($arr['addColumns']))?[]:$arr['addColumns'];
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
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','class'=>'sample','element-content'=>$sampleValue]);
        }
        return $html;
    }
    
    public function canvasElementSelect(array $arr):string
    {
        if (empty($arr['canvasCallingClass'])){
            throw new \ErrorException('Function '.__FUNCTION__.': Argument arr[canvasCallingClass] is missing but required.',0,E_ERROR,__FILE__,__LINE__);
        }
        $canvasElements=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements($arr['canvasCallingClass']);
        foreach($canvasElements as $key=>$canvasEntry){
            if (empty($canvasEntry['Content']['Selector']['Source'])){continue;}
            $arr['options'][$canvasEntry['EntryId']]=($canvasEntry['Content']['Style']['Text']==='&empty;')?'__BLACKHOLE__':$canvasEntry['Content']['Style']['Text'];
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
        $element=['tag'=>'button','element-content'=>'&#9780;','keep-element-content'=>TRUE,'id'=>'clipboard-'.$id,'key'=>['copy',$id],'excontainer'=>TRUE,'title'=>'Copy to clipboard','style'=>['font-weight'=>'bold'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        $element=['tag'=>'div','element-content'=>$text,'id'=>$id,'style'=>['padding'=>'0']];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        $element=['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE];
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        return $html;
    }
    
    public function btn(array $arr=[]):string
    {
        // This function returns standard buttons based on argument arr.
        // If arr is empty, buttons will be processed
        $html='';
        $defaultValues=['Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE,'Read'=>0,'Write'=>0,'Owner'=>'SYSTEM','app'=>''];
        if (isset($arr['cmd'])){
            $defaultValues['app']=$arr['app']??$arr['callingClass']??'';
            $arr['callingClass']=__CLASS__;
            $arr['callingFunction']=__FUNCTION__;
            // compile button
            $arr['element-content']=(isset($arr['element-content']))?$arr['element-content']:ucfirst($arr['cmd']);
            $arr['key']=[$arr['cmd']];
            $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($arr['selector'],$defaultValues);
            $arr['source']=$arr['selector']['Source'];
            $arr['entry-id']=$arr['selector']['EntryId'];
            $arr['value']=$arr['value']??$arr['selector']['EntryId'];
            $arr=$this->oc['SourcePot\Datapool\Foundation\Element']->addNameIdAttr($arr);
            // check for button failure
            $btnFailed=FALSE;
            if (isset(self::BUTTONS[$arr['cmd']])){
                $arr=array_replace_recursive($defaultValues,$arr,self::BUTTONS[$arr['cmd']]);
                if (!empty($arr['requiredRight'])){
                    $hasAccess=$this->oc['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],$arr['requiredRight']);
                    if (empty($hasAccess)){$btnFailed='Access denied';}
                }
                if (!empty($arr['requiresFile']) && mb_strpos(strval($arr['selector']['EntryId']),'-guideEntry')===FALSE){
                    $hasFile=is_file($this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr['selector']));
                    if (!$hasFile){$btnFailed='File error';}
                }
                $arr['element-content']=str_replace(' ','&nbsp;',$arr['element-content']);
            } else {
                $btnFailed='Button defintion missing';
            }
            // finalize button
            if (empty($btnFailed)){
                if (strcmp($arr['cmd'],'upload')===0){
                    $fileArr=$arr;
                    $arr['key'][]=$arr['selector']['EntryId'];
                    $arr=$this->oc['SourcePot\Datapool\Foundation\Element']->addNameIdAttr($arr);    
                    unset($fileArr['name']);
                    unset($fileArr['id']);
                    if (isset($fileArr['element-content'])){
                        unset($fileArr['element-content']);
                    }
                    $fileArr['trigger-id']=$arr['id'];
                    $fileArr['tag']='input';
                    $fileArr['type']='file';
                    $fileArr['excontainer']=TRUE;
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileArr);
                }
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
            } else {
                //$html.=$btnFailed;
            }
        } else {
            $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
            // button command processing
            $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
            $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($formData['selector']);
            if (isset($formData['cmd']['download']) || isset($formData['cmd']['download all'])){
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->entry2fileDownload($selector);
            } else if (isset($formData['cmd']['upload'])){
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
                $filesArr=current($formData['files']['upload']);
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($filesArr,$entry);
            } else if (isset($formData['cmd']['delete']) || isset($formData['cmd']['delete all'])){
                $minSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->minimalSelector($selector);
                $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($minSelector);
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
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->downloadExportedEntries([$selector],$fileName,FALSE,10000000000);
            } else if (isset($formData['cmd']['archive'])){
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
                $entry['Expires']=\SourcePot\Datapool\Root::NULL_DATE;
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
            } else if (isset($formData['cmd']['approve']) || isset($formData['cmd']['decline'])){
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
                $cmd=key($formData['cmd']);
                $userKey=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId().'_action';
                $entry['Params']['User'][$userKey]=['action'=>$cmd,'timestamp'=>time(),'Source'=>$selector['Source']];
                $entry['Params']['User'][$userKey]['user']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract([],1);
                $entry['Params']['User'][$userKey]['user email']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract([],7);
                $entry['Params']['User'][$userKey]['user mobile']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract([],9);
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
            }
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->formData2statisticlog($formData);
        }
        return $html;
    }

    public function deleteBtn(array $arr):string
    {
        $arr['cmd']='delete';
        $html=$this->btn($arr);
        return $html;
    }
    
    public function downloadBtn(array $arr):string
    {
        $arr['cmd']='download';
        $html=$this->btn($arr);
        return $html;
    }

    public function selectBtn(array $arr):string
    {
        $arr['cmd']='select';
        $html=$this->btn($arr);
        return $html;
    }

    public function archiveBtn(array $arr):string
    {
        $arr['cmd']='archive';
        $html=$this->btn($arr);
        return $html;
    }

    public function fileUpload(array $element, array $settings):string
    {
        if (empty($element['callingClass']) || empty($element['callingFunction'])){
            $missingKey=((empty($element['callingFunction']))?'callingFunction':'callingClass');
            throw new \ErrorException('Function "'.__FUNCTION__.' &rarr; '.__CLASS__.'()" called but key "'.$missingKey.'" empty or missing.',0,E_ERROR,__FILE__,__LINE__);   
        }
        // add settings stored in session
        $element['formProcessingArg']=$settings['formProcessingArg']??[];
        $element['formProcessingClass']=$settings['formProcessingClass']??$element['callingClass'];
        $element['formProcessingFunction']=$settings['formProcessingFunction']??$element['callingFunction'];
        // get wrapper style and specific keys
        if (!empty($element['style'])){
            $divStyle=$element['style'];
            unset($element['style']);
        }
        if (!empty($element['element-content'])){
            $elementContent=$element['element-content'];
            unset($element['element-content']);
        }
        $element['class']='file-upload';
        $element['key']=$element['key']??['upload'];
        // compile upload button
        $elementBtn=array_merge(['tag'=>'button','element-content'=>$elementContent??'Upload'],$element);
        $elementBtn['key']=$element['key'];
        $elementBtn=$this->oc['SourcePot\Datapool\Foundation\Element']->addNameIdAttr($elementBtn);
        // compile file input
        $lastKeyIndex=count($element['key'])-1;
        $element['key'][$lastKeyIndex]=$element['key'][$lastKeyIndex].'_';
        $elementFile=array_merge(['tag'=>'input','type'=>'file','multiple'=>TRUE],$element);
        $elementFile['key']=$element['key'];
        $elementFile=$this->oc['SourcePot\Datapool\Foundation\Element']->addNameIdAttr($elementFile);
        $elementFile['trigger-id']=$elementBtn['id'];
        // progross bar
        $elemntProgress=['tag'=>'progress','element-content'=>'Progress','value'=>1,'min'=>0,'max'=>100,'class'=>$element['class']];
        $elemntProgress['name']=$elemntProgress['id']=$elementFile['trigger-id'].'_progress';
        // info field
        $elemntInfo=['tag'=>'p','element-content'=>'','class'=>$element['class']];
        $elemntInfo['name']=$elemntInfo['id']=$elementFile['trigger-id'].'_info';
        // compile html
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elementFile);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elementBtn);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elemntProgress);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elemntInfo);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>$element['class'],'style'=>$divStyle??[]]);
        return $html;
    }

    public function app(array $arr):string
    {
        if (empty($arr['html'])){
            return '';
        }
        $arr['icon']=$arr['icon']??'?';
        $arr['style']=$arr['style']??[];
        $arr['class']=$arr['class']??'app';
        $arr['title']=$arr['title']??'';
        $summaryArr=['tag'=>'summary','element-content'=>$arr['icon'],'keep-element-content'=>TRUE,'title'=>$arr['title'],'class'=>$arr['class']];
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($summaryArr);
        $detailsArr=['tag'=>'details','element-content'=>$html.$arr['html'],'keep-element-content'=>TRUE,'class'=>$arr['class'],'style'=>$arr['style']];
        if (isset($arr['open'])){$detailsArr['open']=$arr['open'];}
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($detailsArr);
        return $html;   
    }
    
    public function emojis(array $arr=[]):array
    {
        if (empty($arr['settings']['target'])){
            throw new \ErrorException('Method '.__FUNCTION__.' called without target setting.',0,E_ERROR,__FILE__,__LINE__);    
        }
        $arr['html']=$arr['html']??'';        
        // get emoji options
        $options=[];
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
        $categorySelectArr=['options'=>$options,'key'=>['Category'],'selected'=>$_SESSION[__CLASS__]['settings'][$callingFunction]['Category'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction];
        $html=$this->select($categorySelectArr);
        if (count($currentKeys)>1){
            $tagArr=['tag'=>'a','href'=>'#','class'=>'emoji','target'=>$arr['settings']['target'],'excontainer'=>TRUE];
            foreach($this->oc['SourcePot\Datapool\Tools\MiscTools']->emojis[$currentKeys[0]][$currentKeys[1]] as $code=>$title){
                $tagArr['id']='utf8-'.$code;
                $tagArr['title']=$title;
                $tagArr['element-content']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->code2utf($code);
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($tagArr);
            }
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE]);
        return $arr;
    }
    
    public function integerEditor(array $arr):string
    {
        $template=['key'=>'Read','integerDef'=>$this->oc['SourcePot\Datapool\Foundation\User']->getUserRoles(),'bitCount'=>16];
        $arr=array_replace_recursive($template,$arr);
        $entry=$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']??[],FALSE);
        if (empty($entry)){return '';}
        // only the Admin has access to the method if columns 'Privileges' is selected
        if (is_array($arr['key'])){
            $arr['key']=array_shift($arr['key']);
        }
        if (strcmp($arr['key'],'Privileges')===0 && !$this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE,FALSE,TRUE)){
            return '';
        }
        if (!$this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE,FALSE,FALSE)){
            return '';
        }
        $integer=intval($entry[$arr['key']]);
        $callingClass=__CLASS__;
        $callingFunction=__FUNCTION__.$arr['key'];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($callingClass,$callingFunction);
        $saveRequest=isset($formData['cmd'][$arr['key']]['save']);
        $updatedInteger=0;
        $html='<fieldset>';
        $html.='<legend>'.'"'.$arr['key'].'" right'.'</legend>';
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
            $htmlBit=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'checkbox','checked'=>$checked,'id'=>$id,'key'=>[$arr['key'],$bitIndex],'callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'title'=>'Bit '.$bitIndex,'excontainer'=>TRUE]);
            $htmlBit.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'label','for'=>$id,'element-content'=>strval($label)]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$htmlBit,'keep-element-content'=>TRUE,'class'=>'fieldset']);
        }
        $updateBtn=['tag'=>'button','key'=>[$arr['key'],'save'],'element-content'=>'Save','style'=>['margin'=>'0','width'=>'100%'],'callingClass'=>$callingClass,'callingFunction'=>$callingFunction];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($updateBtn);
        if ($saveRequest){
            $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($entry);
            $entry=$this->oc['SourcePot\Datapool\Root']->substituteWithPlaceholder($entry);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntries($entry,[$arr['key']=>$updatedInteger],FALSE,'Write');
            $statistics=$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
            $context=['key'=>$arr['key'],'Source'=>$entry['Source'],'selector'=>'','statistics'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics)];
            $context['what']=(empty($entry['EntryId']))?'entries':'entry';
            $context['selector'].=(empty($entry['Group']))?'':' | Group='.$entry['Group'];
            $context['selector'].=(empty($entry['Folder']))?'':' | Folder='.$entry['Folder'];
            $context['selector'].=(empty($entry['EntryId']))?'':' | EntryId='.$entry['EntryId'];
            $context['selector']=trim($context['selector'],'| ');
            $this->oc['logger']->log('info','{Source}-{what} selected by "{selector}" {key}-key processed: {statistics}',$context);    
        }
        $html.='</fieldset>';
        return $html;
    }
    
    public function setAccessByte(array $arr):string
    {
        if (!isset($arr['selector'])){
            return 'Selector missing!';
        }
        $arr['key']=$arr['key']?:'Read';
        $html='';
        if (empty($arr['selector']['Source']) || empty($arr['selector']['EntryId']) || empty($arr['selector'][$arr['key']])){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>'Required keys missing.']);
        } else {
            $editorHtml=$this->integerEditor($arr);
            if ($arr['key']==='Privileges' && !empty($editorHtml)){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','style'=>['clear'=>'both','color'=>'#f00'],'keep-element-content'=>TRUE,'element-content'=>self::SET_ACCESS_BYTE_INFO]);
            }
            $html.=$editorHtml;
        }
        return $html;
    }

    public function entryControls(array $arr,bool $isDebugging=FALSE):string
    {
        $tableStyle=['clear'=>'none','margin'=>'0','min-width'=>'200px'];
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']??[],FALSE);
        if (empty($entry)){return '';}
        // check if a canvas element is selected and it's processor
        $selectedCanvasElement=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey('SourcePot\Datapool\Foundation\DataExplorer','selectedCanvasElement');
        $hasCheckEntriesProcessor=(($selectedCanvasElement['Content']['Widgets']['Processor']??'')==='SourcePot\\checkentries\\checkentries');
        // create tmeplate
        $template=[
            'callingClass'=>__CLASS__,
            'callingFunction'=>__FUNCTION__,
            'hideHeader'=>TRUE,
            'hideKeys'=>TRUE,
            'previewStyle'=>[
                'max-width'=>self::MAX_PREV_WIDTH,
                'max-height'=>self::MAX_PREV_HEIGHT
            ],
            'settings'=>[
                'hideApprove'=>($hasCheckEntriesProcessor?FALSE:TRUE),
                'hideDecline'=>($hasCheckEntriesProcessor?FALSE:TRUE),
                'hideSelect'=>FALSE,
                'hideRemove'=>FALSE,
                'hideDelete'=>FALSE,
                'hideDownload'=>FALSE,
                'hideUpload'=>FALSE,
                'hideDelete'=>FALSE,
                'hideArchive'=>TRUE,
            ],
        ];
        $arr=array_replace_recursive($template,$arr);
        // create preview
        $matrix=['Preview'=>['Value'=>''],'Btns'=>['Value'=>'']];
        if (empty($arr['hidePreview'])){
            $previewArr=$arr;
            $previewArr['settings']['style']=$arr['previewStyle'];
            $previewArr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($previewArr);
            $matrix['Preview']['Value'].=$previewArr['html'];
        }
        // detected user action
        $tableTitle=$entry['Name'];
        $userAction='none';
        $userKey=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId().'_action';
        if (isset($entry['Params']['User'][$userKey])){
            $userAction=$entry['Params']['User'][$userKey]['action'];
            $tableTitle='Your decission for the entry: '.$userAction;
        }
        if ($userAction==='approve'){
            $tableStyle=array_merge($tableStyle,self::APPROVE_STYLE);
        } else if ($userAction==='decline'){
            $tableStyle=array_merge($tableStyle,self::DECLINE_STYLE);
        }
        // create buttons that are not hidden
        foreach($arr['settings'] as $key=>$value){
            if (strpos($key,'hide')!==0){
                continue;
            }
            $cmd=strtolower(str_replace('hide','',$key));
            if ($cmd==='archive' && ($arr['selector']['Expires']??\SourcePot\Datapool\Root::NULL_DATE)!==\SourcePot\Datapool\Root::NULL_DATE){
                $value=FALSE;
            }
            if ($value===FALSE && $userAction!==$cmd){
                $arr['excontainer']=TRUE;
                $arr['cmd']=$cmd;
                $matrix['Btns']['Value'].=$this->btn($arr);
            }
        }
        // finalize
        $matrix['Btns']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$matrix['Btns']['Value'],'keep-element-content'=>TRUE,'style'=>['width'=>'max-content']]);
        $html=$this->table(['matrix'=>$matrix,'hideHeader'=>$arr['hideHeader'],'hideKeys'=>$arr['hideKeys'],'caption'=>FALSE,'keep-element-content'=>TRUE,'title'=>$tableTitle,'style'=>$tableStyle,'class'=>'matrix']);
        return $html;
    }
    
    public function entry2row(array $arr,bool $isSystemCall=FALSE):array|string
    {
        $arr['returnRow']=TRUE;
        return $this->entryListEditor($arr,$isSystemCall);
    }

    public function entryListEditor(array $arr,bool $isSystemCall=TRUE):string|array
    {
        $errorMsg='Method "'.__CLASS__.' &rarr; '.__FUNCTION__.'()" called with arr-argument keys missing: ';
        $arr['returnRow']=!empty($arr['returnRow']);
        if (!isset($arr['contentStructure']) || empty($arr['callingClass']) || empty($arr['callingFunction'])){
            $errorMsg.=' contentStructure, callingClass or callingFunction';
            $this->oc['logger']->log('error',$errorMsg,[]);
            return (!$arr['returnRow'])?$errorMsg:['error'=>$errorMsg];
        }
        if ((empty($arr['selector']['Source']) && empty($arr['selector']['Class'])) || empty($arr['selector']['EntryId'])){
            $errorMsg.=' Source or Class or EntryId';
            $this->oc['logger']->log('error',$errorMsg,[]);
            return (!$arr['returnRow'])?$errorMsg:['error'=>$errorMsg];
        }
        $this->oc['SourcePot\Datapool\Foundation\Legacy']->updateEntryListEditorEntries($arr); // <----------------- Update old EntryId
        // get base selector and storage object
        if (!empty($arr['selector']['Source'])){
            $storageObj='SourcePot\Datapool\Foundation\Database';
            $baseSelector=['Source'=>$arr['selector']['Source']];
        } else {
            $storageObj='SourcePot\Datapool\Foundation\Filespace';
            $baseSelector=['Class'=>$arr['selector']['Class']];
        }
        // initialization
        $arr['caption']=(empty($arr['caption']))?'CAPTION MISSING':$arr['caption'];
        if ($arr['returnRow']){
            $arr['maxRowCount']=1;
        } else {
            $arr['maxRowCount']=$arr['maxRowCount']?:999;
        }
        // legacy EntryId correction -> since 12/2025 entry2row() requires an ordered list EntryId
        $primaryKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListKeyFromEntryId($arr['selector']['EntryId']);
        $oldEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(['Source'=>$arr['selector']['Source'],'EntryId'=>$primaryKey]);
        if ($oldEntry){
            $arr['selector']=$oldEntry;
            $arr['selector']['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Database']->addOrderedListIndexToEntryId($arr['selector']['EntryId'],1);
            $newEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
            if ($this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries(['Source'=>$oldEntry['Source'],'EntryId'=>$oldEntry['EntryId']],TRUE)>0){
                $this->oc['logger']->log('notice','Method entry2row() legacy correction done. EntryId updated to ordered list EntryId "{EntryId}" in table "{Source}"',$arr['selector']);
            }
        }
        // get the first entry
        $firstEntry=$arr['selector'];
        $firstEntry['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Database']->addOrderedListIndexToEntryId($arr['selector']['EntryId'],1);
        $this->oc[$storageObj]->entryByIdCreateIfMissing($firstEntry,TRUE);
        // command processing
        $movedEntryId='';
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['reloadBtnArr'])){
            // form reload
        } else if (!empty($formData['cmd'])){
            $selector=$baseSelector;
            $selector['EntryId']=key(current($formData['cmd']));
        }
        if (isset($formData['cmd']['save'])){
            $entry=array_merge($arr['selector'],$selector,$formData['val'][$selector['EntryId']]);
            $this->oc[$storageObj]->updateEntry($entry,$isSystemCall);
        } else if (isset($formData['cmd']['delete'])){
            $this->oc['SourcePot\Datapool\Foundation\Database']->rebuildOrderedList($selector,['removeEntryId'=>$selector['EntryId']]);
        } else if (isset($formData['cmd']['add'])){
            $endIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($selector['EntryId']);
            $newEntry=$arr['selector'];
            $newEntry['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Database']->addOrderedListIndexToEntryId($selector['EntryId'],$endIndex+1);
            $this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($newEntry);
        } else if (isset($formData['cmd']['moveUp'])){
            $movedEntryId=$this->oc['SourcePot\Datapool\Foundation\Database']->rebuildOrderedList($selector,['moveUpEntryId'=>$selector['EntryId']]);
        } else if (isset($formData['cmd']['moveDown'])){
            $movedEntryId=$this->oc['SourcePot\Datapool\Foundation\Database']->rebuildOrderedList($selector,['moveDownEntryId'=>$selector['EntryId']]);
        }
        // html creation
        $noWriteAccess=FALSE;
        $matrix=$csvMatrix=$emptyRow=[];
        $startIndex=$endIndex=1;
        $selector=$baseSelector;
        $selector['EntryId']='%'.$arr['selector']['EntryId'];
        foreach($this->oc[$storageObj]->entryIterator($selector,$isSystemCall,'Read','EntryId',TRUE) as $entry){
            $noWriteAccess=empty($this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write'));
            $endIndex=$entry['rowCount'];
            $entryIdComps=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($entry['EntryId']);
            $currentIndex=intval($entryIdComps[0]);
            $rowIndex=$entryIdComps[0];
            foreach($arr['contentStructure'] as $contentKey=>$elementArr){
                $classWithNamespace=(empty($elementArr['classWithNamespace']))?__CLASS__:$elementArr['classWithNamespace'];
                $method=(empty($elementArr['method']))?'method-arg-missing':$elementArr['method'];
                $emptyRow[$contentKey]='';
                if (method_exists($classWithNamespace,$method)){
                    // if classWithNamespace::method() exists
                    if (isset($entry['Content'][$contentKey])){
                        if (isset($elementArr['element-content'])){
                            $elementArr['element-content']=$entry['Content'][$contentKey];
                            if (!empty($elementArr['value'])){
                                $elementArr['value']=$elementArr['value'];
                            }
                        } else {
                            $elementArr['value']=$entry['Content'][$contentKey];
                        }
                    }
                    $elementArr['callingClass']=$arr['callingClass'];
                    $elementArr['callingFunction']=$arr['callingFunction'];
                    $elementArr['key']=[$entry['EntryId'],'Content',$contentKey];
                    if (isset($arr['canvasCallingClass'])){
                        $elementArr['canvasCallingClass']=$arr['canvasCallingClass'];
                    }
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
            if (!isset($matrix[$rowIndex])){
                continue;
            }
            $matrix[$rowIndex]=array_merge($matrix[$rowIndex],[' '=>'','  '=>'','   '=>'','    '=>'',]);
            $emptyRow+=[' '=>'','  '=>'','   '=>'','    '=>'',];
            // add buttons
            $btnArr=array_merge($arr,self::BUTTONS['save'],['excontainer'=>FALSE,'tdStyle'=>['padding'=>0]]);
            $btnArr['key'][]=$entry['EntryId'];
            $matrix[$rowIndex][' ']=$btnArr;
            if ($entry['rowCount']>1){
                $btnArr=array_merge($arr,self::BUTTONS['delete'],['excontainer'=>FALSE,'tdStyle'=>['padding'=>0]]);
                $btnArr['key'][]=$entry['EntryId'];
                $matrix[$rowIndex]['  ']=$btnArr;
            }
            if ($endIndex<$arr['maxRowCount'] && $currentIndex===$endIndex){
                $btnArr=array_merge($arr,self::BUTTONS['add'],['excontainer'=>FALSE,'tdStyle'=>['padding'=>0]]);
                $btnArr['key'][]=$entry['EntryId'];
                $addBtn=$btnArr;
            }
            if ($currentIndex>$startIndex){
                $btnArr=array_merge($arr,self::BUTTONS['moveDown'],['excontainer'=>FALSE,'tdStyle'=>['padding'=>'0 0 0 5px']]);
                $btnArr['key'][]=$entry['EntryId'];
                if (strcmp($entry['EntryId'],$movedEntryId)===0){
                    $btnArr['class']='blue';
                }
                $matrix[$rowIndex]['   ']=$btnArr;    
            }
            if ($currentIndex<$endIndex && $arr['returnRow']>1){
                $btnArr=array_merge($arr,self::BUTTONS['moveUp'],['excontainer'=>FALSE,'tdStyle'=>['padding'=>0]]);
                $btnArr['key'][]=$entry['EntryId'];
                if (strcmp($entry['EntryId'],$movedEntryId)===0){
                    $btnArr['class']='blue';
                }
                $matrix[$rowIndex]['    ']=$btnArr;    
            }
            if ($entry['rowCount']<2){
                $matrix[$rowIndex]['   ']=$matrix[$rowIndex]['    ']='';
            }
            if (empty($entry['Content'])){
                $matrix[$rowIndex]['trStyle']['background-color']=$this->cssVars['--attentionColorTransparent']??'#faa';
            }
        } // end of loop through list entries
        $matrix['']=array_merge($emptyRow,[' '=>$addBtn,'    '=>$this->oc['SourcePot\Datapool\Tools\CSVtools']->matrix2csvDownload($csvMatrix)]);
        // write protection
        if ($noWriteAccess){$matrix=[];}
        // prepare return values
        if ($arr['returnRow']){
            return (empty(current($matrix)))?['value'=>'']:current($matrix);
        } else {
            return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
        }
    }

    public function selector2string(array $selector=[], bool $useEntryId=FALSE):string
    {
        $template=['Source'=>'','Group'=>'','Folder'=>'','Name'=>''];
        if (empty($selector['Name']) && !empty($selector['EntryId'])){
            $selector=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
        }
        $result=[];
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
            $matrix=[];
            foreach($row as $key=>$value){
                $matrix[$key]=['value'=>$value];
            }
        } else {
            $matrix=[$caption=>$row];
        }
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption]);
    }
    
    public function value2tableCellContent($html,array $arr=[])
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
            file_put_contents($htmlFile,$html);
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
            $settingsTemplate=['method'=>'presentEntry','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder'];
            $arr['settings']=array_merge($arr['settings'],$settingsTemplate);
            $wrapperSetting=['class'=>$arr['class']?:'','style'=>$arr['style']??[]];
            return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Present entry '.$arr['settings']['presentEntry'].' '.$arr['selector']['EntryId'],'generic',$arr['selector'],$arr['settings'],$wrapperSetting);
        } else {
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
            $arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
            return $arr['html'];
        }
    }        
    
    public function presentEntry(array $presentArr):array|string
    {
        $html='';
        if (!empty($presentArr['selector']['EntryId']) && empty($presentArr['selector']['Params'])){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($presentArr['selector'],FALSE);
            if ($entry){
                $presentArr['selector']=$entry;
            }
        }
        $presentArr=$this->mapContainer2presentArr($presentArr);
        $selector=$this->getPresentationSelector($presentArr);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','EntryId') as $setting){
            if (empty($setting['Content']['Entry key'])){
                $this->oc['logger']->log('error','Entry presentation setting is empty Folder="{Folder}", Name="{Name}"',$setting);
                continue;
            }
            $presentArr['style']=$presentArr['settings']['style']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->style2arr($setting['Content']['Style']??'');
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
                        if (strpos($flatKey,$setting['Content']['Key filter']??'')===FALSE && !empty($setting['Content']['Key filter'])){
                            // remove value-key if filter is set but filter constrain not met 
                            unset($flatEntryPart[$flatKey]);
                        }
                    }
                    if (count($flatEntryPart)==1){
                        $key.=\SourcePot\Datapool\Root::ONEDIMSEPARATOR.key($flatEntryPart);
                        $presentationValue=current($flatEntryPart);
                    } else {
                        $presentationValue=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntryPart);
                    }
                }
                if (is_array($presentationValue)){
                    $element=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatArrLeaves($flatEntryPart);
                    if (isset($element['tag']) && isset($element['element-content'])){
                        // present element
                        $presentationValue=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                        $presentHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$presentationValue,'keep-element-content'=>TRUE,'style'=>$presentArr['style'],'class'=>$presentArr['class']]);
                    } else {
                        // present as table
                        $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($presentationValue);
                        $presentHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>(($showKey)?$key:''),'style'=>$presentArr['style'],'class'=>$presentArr['class']]);
                    }
                } else {
                    // present as div
                    $presentationValue=strip_tags((string)$presentationValue);  // prevent XSS atacks
                    if ($key==='Date'){
                        $pageTimeZone=\SourcePot\Datapool\Root::getUserTimezone();
                        $date=new \DateTime($presentationValue,new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
                        $date->setTimezone(new \DateTimeZone($pageTimeZone));
                        $presentationValue=$date->format('Y-m-d H:i:s').' ('.$pageTimeZone.')';
                    }
                    if ($showKey){
                        $key=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($key);
                        $presentationValue='<b>'.$key.': </b>'.$presentationValue;
                    }
                    $presentHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$presentationValue,'keep-element-content'=>TRUE,'style'=>$presentArr['style'],'class'=>$presentArr['class']]);
                }
                $html.=$presentHtml;
            } else {
                // App presentation
                $callingClass=array_shift($cntrArr);
                $callingFunction=array_shift($cntrArr);
                $wrapper=array_shift($cntrArr);
                if (empty($wrapper)){
                    $appArr=$this->oc[$callingClass]->$callingFunction($presentArr);
                } else if ($wrapper=='container'){
                    $appArr=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Present entry '.$setting['EntryId'],'generic',$presentArr['selector'],['method'=>$callingFunction,'classWithNamespace'=>$callingClass,'presentEntrySelector'=>$selector],[]);    
                }
                if (is_array($appArr)){$html.=$appArr['html'];} else {$html.=$appArr;}
            }
        }
        if (empty($setting['rowCount'])){
            $this->oc['logger']->log('error','Entry presentation setting missing for "{selectorFolder}"',['selectorFolder'=>$selector['Folder']]);
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
        $selector=['Source'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Presentation'];
        $selector['Folder']=$presentArr['callingClass'].'::'.$presentArr['callingFunction'];
        $guideEntry=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getGuideEntry($selector);
        $selector['Name']='Setting';
        return $selector;
    }
    
    private function mapContainer2presentArr(array $presentArr):array
    {
        if (strcmp($presentArr['callingClass']??'','SourcePot\\Datapool\\Foundation\\Container')===0){
            $presentArr['callingClass']=$this->oc['SourcePot\Datapool\Root']->source2class($presentArr['selector']['Source']);
            $presentArr['callingFunction']=$presentArr['settings']['method']??$presentArr['callingFunction'];
        }
        return $presentArr;
    }
    
    public function getPresentationSettingHtml(array $arr):array
    {
        if (empty($arr['selector']['Folder'])){
            return ['html'=>'Please select the presentation setting, provide the Folder...'];
        }
        $callingClassFunction=explode('::',$arr['selector']['Folder']);
        $entryKeyOptions=self::APP_OPTIONS;
        if (isset($this->oc[$callingClassFunction[0]])){
            $entryTemplate=$this->oc[$callingClassFunction[0]]->getEntryTemplate();
            foreach($entryTemplate as $column=>$columnInfo){$entryKeyOptions[$column]=$column;}
        }
        $styleClassOptions=$this->getStyleClassOptions($arr);
        $contentStructure=[
            'Entry key'=>['method'=>'select','excontainer'=>TRUE,'value'=>'Name','options'=>$entryKeyOptions,'keep-element-content'=>TRUE],
            'Key filter'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            'Style class'=>['method'=>'select','excontainer'=>TRUE,'value'=>'ep-std','options'=>$styleClassOptions,'keep-element-content'=>TRUE],
            'Style'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            'Show key'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['No','Yes']],
        ];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']=$arr['selector']['Folder'];
        $arr['selector']['Name']='Setting';
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],['Source','Group','Folder','Name'],'0','',FALSE);
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>self::PRESENTATION_SETTINGS_INFO,'keep-element-content'=>TRUE,'style'=>['font-size'=>'1.25rem','margin'=>'5px']]);
        return $arr;
    }
    
    private function getStyleClassOptions(array $arr):array
    {
        $entryPresentationCss=$GLOBALS['dirs']['media'].'/ep.css';
        $entryPresentationCss=file_get_contents($entryPresentationCss);
        preg_match_all('/(\.)([a-z0-9\-]+)([\{\,\:]+)/',$entryPresentationCss,$matches);
        $options=[];
        foreach($matches[2] as $class){
            $options[$class]=$class;
        }
        return $options;
    }
}
?>