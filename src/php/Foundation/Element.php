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

class Element{
    
    private $oc;
    
    const TRANSLATE=[
        'p'=>'element-content|title',
        'div'=>'element-content|title',
        'caption'=>'element-content',
        'label'=>'element-content',
        'span'=>'element-content|title',
        'submit'=>'value|title',
        'text'=>'placeholder|title',
        'button'=>'element-content|title',
        'th'=>'element-content',
        'td'=>'element-content',
        'h1'=>'element-content',
        'h2'=>'element-content',
        'h3'=>'element-content',
        'option'=>'element-content',
        ];
    
    const DEF=[
        // Generic
        ''=>['accesskey'=>FALSE,'autocapitalize'=>FALSE,'autofocus'=>FALSE,'class'=>'std','contenteditable'=>FALSE,'data-*'=>FALSE,
            'dir'=>FALSE,'draggable'=>FALSE,'enterkeyhint'=>FALSE,'hidden'=>FALSE,'id'=>FALSE,'inert'=>FALSE,'inputmode'=>FALSE,'is'=>FALSE,
            'itemid'=>FALSE,'itemprop'=>FALSE,'itemref'=>FALSE,'itemscope'=>FALSE,'itemtype'=>FALSE,'lang'=>FALSE,'nonce'=>FALSE,'part'=>FALSE,
            'popover'=>FALSE,'role'=>FALSE,'slot'=>FALSE,'spellcheck'=>FALSE,'style'=>FALSE,'tabindex'=>FALSE,'title'=>FALSE,
            'virtualkeyboardpolicy'=>FALSE,
            'stroke'=>FALSE,'stroke-dasharray'=>FALSE,'stroke-width'=>FALSE,'stroke-linecap'=>FALSE,'fill'=>FALSE,'fill-opacity'=>FALSE,
            'font'=>FALSE,'clip-path'=>FALSE,'viewBox'=>FALSE,'version'=>FALSE,'xmlns'=>FALSE,'integrity'=>FALSE,'nonce'=>FALSE,
            'data-value'=>FALSE,'data-timestamp'=>FALSE,
            ],
        
        // Table
        'table'=>[],
        'caption'=>[],
        'tbody'=>[],
        'tr'=>[],
        'td'=>['cell'=>FALSE],
        'th'=>[],
        // Forms
        'button'=>['name'=>TRUE],
        'datalist'=>['name'=>TRUE],
        'fieldset'=>['name'=>TRUE],
        'form'=>['action'=>FALSE,'accept-charset'=>FALSE,'autocomplete'=>FALSE,'enctype'=>'multipart/form-data',''=>FALSE,'method'=>'post','name'=>FALSE,
                    'novalidate'=>FALSE,'rel'=>FALSE,'target'=>FALSE],
        'input'=>['type'=>TRUE,'value'=>FALSE,'accept'=>FALSE,'name'=>TRUE,'disabled'=>FALSE,'multiple'=>FALSE,'checked'=>FALSE,'min'=>FALSE,'max'=>FALSE,'minlength'=>FALSE,'maxlength'=>FALSE,'placeholder'=>FALSE],
        'label'=>['for'=>TRUE],
        'legend'=>['name'=>TRUE],
        'optgroup'=>['name'=>TRUE],
        'option'=>['value'=>TRUE,'selected'=>FALSE],
        'output'=>['name'=>TRUE],
        'progress'=>['value'=>FALSE,'min'=>FALSE,'max'=>FALSE,],
        'meter'=>['min'=>TRUE,'max'=>TRUE,'low'=>FALSE,'high'=>FALSE,'optimum'=>FALSE,'value'=>FALSE],
        'select'=>['name'=>TRUE],
        'textarea'=>['name'=>TRUE,'placeholder'=>FALSE,'rows'=>FALSE,'cols'=>FALSE],
        
        'a'=>['href'=>FALSE,'target'=>FALSE],
        // Structural elements
        'main'=>[],
        'html'=>[],
        'details'=>['open'=>FALSE],
        'summary'=>[],
        'div'=>[],
        'li'=>[],
        'ol'=>[],
        'ul'=>[],
        'h1'=>[],
        'h2'=>[],
        'h3'=>[],
        'h4'=>[],
        'p'=>[],
        'article'=>[],
        'span'=>[],
        // Media
        'audio'=>['src'=>TRUE,'autoplay'=>FALSE,'controls'=>FALSE,'crossorigin'=>FALSE,'loop'=>FALSE,'muted'=>FALSE,'preload'=>FALSE,'height'=>FALSE,'width'=>FALSE],
        'canvas'=>['height'=>FALSE,'width'=>FALSE],
        'object'=>['data'=>TRUE,'type'=>TRUE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE,'autoplay'=>FALSE],
        'embed'=>['src'=>TRUE,'height'=>FALSE,'width'=>FALSE,'type'=>FALSE],
        'iframe'=>['src'=>TRUE,'height'=>FALSE,'width'=>FALSE],
        'img'=>['src'=>TRUE,'alt'=>FALSE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE,'orgheight'=>FALSE,'orgwidth'=>FALSE,'loading'=>FALSE],
        'link'=>['rel'=>FALSE,'href'=>TRUE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE],
        'picture'=>['src'=>TRUE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE],
        'script'=>['src'=>FALSE,'type'=>FALSE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE],
        'svg'=>['src'=>FALSE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE],
        'video'=>['src'=>FALSE,'autoplay'=>FALSE,'controls'=>FALSE,'crossorigin'=>FALSE,'loop'=>FALSE,'muted'=>FALSE,'preload'=>FALSE,'height'=>FALSE,'width'=>FALSE],
        'source'=>['src'=>TRUE,'type'=>FALSE,'srcset'=>FALSE,'sizes'=>FALSE,'media'=>FALSE,'height'=>FALSE,'width'=>FALSE],
        // SVG
        'path'=>['d'=>TRUE],
        'circle'=>['r'=>3,'cx'=>TRUE,'cy'=>TRUE],
        'text'=>['x'=>TRUE,'y'=>TRUE],
        'line'=>['x1'=>TRUE,'x2'=>TRUE,'y1'=>TRUE,'y2'=>TRUE],
        'rect'=>['x'=>TRUE,'y'=>TRUE,'width'=>TRUE,'height'=>TRUE],
        'tspan'=>['x'=>FALSE,'y'=>FALSE,'dx'=>FALSE,'dy'=>FALSE],
        'clipPath'=>[],
        'use'=>[],
        'defs'=>[],
        ];
    
    const SECIAL_ATTR=[
        'function'=>FALSE,'method'=>FALSE,'target'=>FALSE,'trigger-id'=>FALSE,'container-id'=>FALSE,'excontainer'=>FALSE,'container'=>FALSE,'cell'=>FALSE,
        'row'=>FALSE,'source'=>FALSE,'entry-id'=>FALSE,'index'=>FALSE,'js-status'=>FALSE,
        ];
                               
    const COPY2SESSION=[
        'element-content'=>FALSE,'value'=>FALSE,'tag'=>TRUE,'type'=>'','key'=>FALSE,'id'=>FALSE,'name'=>FALSE,'selector'=>FALSE,'app'=>FALSE,
        'formProcessingClass'=>FALSE,'formProcessingFunction'=>FALSE,'formProcessingArg'=>FALSE,
        'callingClass'=>FALSE,'callingFunction'=>FALSE,'filter'=>FILTER_DEFAULT,'Read'=>FALSE,'Write'=>FALSE,'Owner'=>FALSE,
        ];

    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function element(array $arr):string
    {
        // translation, use type attribute, e.g. submit, text,... if it is present, instead of tag
        $translationTestKey=(isset($arr['type']))?'type':'tag';
        if (isset(self::TRANSLATE[$arr[$translationTestKey]])){
            $toTranslateKeys=explode('|',self::TRANSLATE[$arr[$translationTestKey]]);
            foreach($toTranslateKeys as $toTranslateKey){
                if (isset($arr[$toTranslateKey])){
                   $arr[$toTranslateKey]=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng($arr[$toTranslateKey]);
                }
            }
        }
        // create tag-arr from $arr
        if (empty($arr['tag'])){
            $arr['tag']='p';
            $arr['element-content']='ERROR "tag"-attribute missing';
            $arr['style']['background-color']='#f00';
        } else if ($arr['tag']==='script' || $arr['tag']==='object' || $arr['tag']==='embed' || $arr['tag']==='link' || $arr['tag']==='img' || $arr['tag']==='video' || $arr['tag']==='iframe'){
            $arr['nonce']='{{nonce}}';
        }
        if (isset(self::DEF[$arr['tag']])){
            if (isset($arr['element-content'])){$arr['element-content']=strval($arr['element-content']);}
            $def=array_merge(self::DEF[''],self::DEF[$arr['tag']],self::SECIAL_ATTR);
            $nameRequired=(!empty($def['name']));
            $elementArr=['tag'=>$arr['tag'],'attr'=>[],'sessionArr'=>['type'=>'']];
            foreach($def as $attrName=>$attrCntr){
                if (isset($arr[$attrName])){
                    $elementArr['sessionArr'][$attrName]=$arr[$attrName];
                    $elementArr['attr'][$attrName]=$this->attr2string($arr,$attrName,$arr[$attrName]);
                } else if ($attrCntr===FALSE){
                    // do nothing
                } else if ($attrCntr===TRUE){
                    if (strcmp($attrName,'name')!==0){
                        throw new \ErrorException('Function '.__FUNCTION__.': tag "'.$arr['tag'].'" required attribute "'.$attrName.'" missing.',0,E_ERROR,__FILE__,__LINE__);
                    }
                } else {
                    $elementArr['sessionArr'][$attrName]=$attrCntr;
                    $elementArr['attr'][$attrName]=$this->attr2string($arr,$attrName,$attrCntr);
                }
            }
        } else {
            throw new \ErrorException('Function '.__FUNCTION__.': tag-key "'.$arr['tag'].'" definition missing.',0,E_ERROR,__FILE__,__LINE__);    
        }
        // html-elements which require the name attribute will require the key attribute too
        if ($nameRequired){
            $arr['selector']=$arr['selector']??[];
            if (isset($arr['key'])){
                $arr=$this->addNameIdAttr($arr);
                $elementArr['attr']['id']=$this->attr2string($arr,'id',$arr['id']);
                $elementArr['attr']['name']=$this->attr2string($arr,'name',$arr['name']);
            } else {
                throw new \ErrorException('Function '.__FUNCTION__.': tag "'.$elementArr['tag'].'" required attribute "key" missing.',0,E_ERROR,__FILE__,__LINE__);
            }
            // copy special keys to session
            $sessionArr=$this->def2arr($arr,self::COPY2SESSION);
            $sessionArr=$this->addElement2session($sessionArr);
        }
        // compile html
        if (empty($arr['hasCover'])){
            $html=$this->elementArr2html($arr,$elementArr);
        } else {
            $html=$this->elementArr2htmlAddCover($arr,$elementArr);
        }
        return $html;
    }

    public function addNameIdAttr(array $arr):array
    {
        if (!empty($arr['id']) && empty($arr['name'])){
            $arr['name']=$arr['id'];
        } else if (empty($arr['id']) && !empty($arr['name'])){
            $arr['id']=$arr['name'];
        } else {
            $hashArr=[];
            $hashArr[]=['Source'=>$arr['selector']['Source']??'','Group'=>$arr['selector']['Group']??'','Folder'=>$arr['selector']['Folder']??'','Name'=>$arr['selector']['Name']??'','EntryId'=>$arr['selector']['EntryId']??''];
            $hashArr[]=$arr['key'];
            $hashArr[]=$arr['callingClass'];
            $hashArr[]=$arr['callingFunction'];
            $arr['name']=$arr['id']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($hashArr,TRUE);
        }
        return $arr;
    }
    
    private function attr2string(array $arr,string $attrName,$attrValue):string
    {
        if (strcmp($attrName,'name')===0 && !empty($arr['multiple'])){$attrValue.='[]';}
        if (is_array($attrValue)){
            $newAttrValue='';
            foreach($attrValue as $key=>$value){
                $key=strval($key);
                if (mb_strpos($key,'height')!==FALSE || mb_strpos($key,'width')!==FALSE || mb_strpos($key,'size')!==FALSE || mb_strpos($key,'top')!==FALSE || mb_strpos($key,'left')!==FALSE || mb_strpos($key,'bottom')!==FALSE || mb_strpos($key,'right')!==FALSE){
                    if (is_numeric($value)){
                        $value=strval($value).'px';
                    } else {
                        $value=strval($value);
                    }
                }
                $newAttrValue.=$key.':'.$value.';';
            }
            $attrValue=$newAttrValue;
        }
        if ($attrValue===TRUE){
            $string=$this->escapeAttrName($attrName);
        } else  if ($attrValue===FALSE){
            $string='';
        } else {
            $string=$this->escapeAttrName($attrName).'="'.$this->escapeAttrValue($attrValue).'"';
        }
        return $string;
    }

    private function def2arr(array $arrIn,array $def,array $arrOut=[]):array
    {
        foreach($def as $defKey=>$defCntr){
            if (isset($arrIn[$defKey])){
                $arrOut[$defKey]=$arrIn[$defKey];
            } else if ($defCntr===FALSE){
                // do nothing
            } else if ($defCntr===TRUE){
                throw new \ErrorException('Function '.__FUNCTION__.': def['.$defKey.']-argument (===TRUE) requires arrIn['.$defKey.']-argument to be set, but it is missing.',0,E_ERROR,__FILE__,__LINE__);
            } else {
                $arrOut[$defKey]=$defCntr;
            }
        }
        return $arrOut;
    }

    private function addElement2session(array $sessionArr):array
    {
        if (!empty($sessionArr['callingClass']) && !empty($sessionArr['callingFunction']) && !empty($sessionArr['name'])){
            $_SESSION['name2classFunction'][$sessionArr['name']]=['callingClass'=>$sessionArr['callingClass'],'callingFunction'=>$sessionArr['callingFunction']];
            $_SESSION[$sessionArr['callingClass']][$sessionArr['callingFunction']][$sessionArr['name']]=$sessionArr;
        } else {
            $emptyKey=(empty($sessionArr['callingClass'])?'callingClass':(empty($sessionArr['callingFunction'])?'callingFunction':'name'));
            throw new \ErrorException('Function '.__FUNCTION__.' called with empty key "'.$emptyKey.'"',0,E_ERROR,__FILE__,__LINE__);    
        }
        return $_SESSION[$sessionArr['callingClass']][$sessionArr['callingFunction']][$sessionArr['name']];
    }
    
    private function elementArr2html(array $arr,array $elementArr):string
    {
        if (isset($arr['element-content'])){
            $arr['element-content']=strval($arr['element-content']);
            if (empty($arr['keep-element-content'])){$arr['element-content']=htmlentities($arr['element-content']);}
            $html='<'.$elementArr['tag'].' '.implode(' ',$elementArr['attr']).'>'.$arr['element-content'].'</'.$elementArr['tag'].'>';
        } else {
            $html='<'.$elementArr['tag'].' '.implode(' ',$elementArr['attr']).'/>';
        }
        return $html;
    }

    private function elementArr2htmlAddCover(array $arr,array $elementArr):string
    {
        unset($arr['hasCover']);
        $elementArrStyle=$this->oc['SourcePot\Datapool\Tools\MiscTools']->attr2value($elementArr['attr']['style']??'');
        $elementArrStyle=$this->oc['SourcePot\Datapool\Tools\MiscTools']->style2arr($elementArrStyle);
        $coverPArr=array('tag'=>'p','class'=>'cover','id'=>'cover-'.$arr['id'],'style'=>[],'element-content'=>'Sure?');
        $coverDivArr=array('tag'=>'div','class'=>'cover-wrapper','id'=>'cover-wrapper','style'=>[],'keep-element-content'=>TRUE);
        // move selected $elementArr styles to the top div
        foreach($elementArrStyle as $styleKey=>$styleValue){
            if (stripos($styleKey,'float')===FALSE && stripos($styleKey,'clear')===FALSE && stripos($styleKey,'margin')===FALSE && stripos($styleKey,'padding')===FALSE && stripos($styleKey,'position')===FALSE && stripos($styleKey,'display')===FALSE){continue;}
            $coverDivArr['style'][$styleKey]=$styleValue;
            unset($elementArrStyle[$styleKey]);
        }
        $elementArr['attr']['style']='style="'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2style($elementArrStyle).'"';
        // create cover
        $coverDivArr['title']=(isset($elementArr['attr']['title']))?($this->oc['SourcePot\Datapool\Tools\MiscTools']->attr2value($elementArr['attr']['title'])):'Safety cover..';
        $coverDivArr['element-content']=$this->elementArr2html($arr,$elementArr).$this->element($coverPArr);
        return $this->element($coverDivArr);
    }

    /**
     * This method returns the processing results from  $_POST and $_FILES. It returns an array containing old values, new values, files and commmands.
     *
     */
    public function formProcessing(string $callingClass,string $callingFunction):array
    {
        $result=['cmd'=>[],'val'=>[],'changed'=>[],'files'=>[],'hasValidFiles'=>FALSE,'selector'=>[],'callingClass'=>$callingClass,'callingFunction'=>$callingFunction];
        if (isset($_SESSION[$callingClass][$callingFunction])){
            foreach($_SESSION[$callingClass][$callingFunction] as $name=>$arr){
                // Process _POST array
                if (isset($_POST[$name]) && isset($arr['tag'])){
                    $keys=$arr['key'];
                    if (isset($arr['value'])){
                        $oldValue=strval($arr['value']);
                    } else if (isset($arr['element-content'])){
                        $oldValue=strval($arr['element-content']);
                    } else {
                        $oldValue='';
                    }
                    if ($arr['type']==='submit' || $arr['tag']==='button'){
                        $newValue=$oldValue;
                        array_unshift($keys,'cmd');
                        // copy COPY2SESSION
                        $result=$this->def2arr($arr,self::COPY2SESSION,$result);
                        //$result['selector']=(isset($arr['selector']))?$arr['selector']:$result['selector'];
                    } else {
                        $filter=(empty($arr['filter']))?(\FILTER_DEFAULT):intval($arr['filter']);
                        $newValue=filter_input(INPUT_POST,$name,$filter);
                        array_unshift($keys,'val');
                    }
                    if (strval($newValue)!==$oldValue){
                        $changedKeys=$arr['key'];
                        array_unshift($changedKeys,'changed');
                        $changedValueArr=$this->arrKeys2arr($changedKeys,$oldValue);
                        $result=array_replace_recursive($result,$changedValueArr);
                    }
                    $newValueArr=$this->arrKeys2arr($keys,$newValue);
                    $result=array_replace_recursive($result,$newValueArr);
                }
                // Process _FILES array
                if (isset($_FILES[$name])){
                    // process $_FILES
                    foreach($_FILES[$name] as $fileKey=>$fileArr){
                        if (!is_array($fileArr)){$fileArr=array($fileArr);}
                        foreach($fileArr as $fileIndex=>$fileValue){
                            $keysA=$arr['key'];
                            array_unshift($keysA,'files');
                            $keysA[]=$fileIndex;
                            $keysB=$keysA;
                            //
                            $keysA[]=$fileKey;
                            $fileValueArr=$this->arrKeys2arr($keysA,$fileValue);
                            $result=array_replace_recursive($result,$fileValueArr);
                            //
                            if (strcmp($fileKey,'error')===0){
                                $result['hasValidFiles']=(empty($fileValue))?(intval($result['hasValidFiles'])+1):$result['hasValidFiles'];
                                $keysB[]='msg';
                                $msgArr=$this->arrKeys2arr($keysB,$this->fileErrorCode2str($fileValue));
                                $result=array_replace_recursive($result,$msgArr);
                            }
                        }  // loop through files
                    } // loop through file keys
                } // has files
            } // loop through session var
        } // relevant session var exists
        return $result;
    }
    
    private function arrKeys2arr($keys,$value){
        $arr=$value;
        while(count($keys)>0){
            $subKey=array_pop($keys);
            $arr=[$subKey=>$arr];
        }
        return $arr;
    }

    public function fileErrorCode2str($code):string
    {
        $codeArr=[
            0=>'There is no error, the file uploaded with success',
            1=>'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2=>'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3=>'The uploaded file was only partially uploaded',
            4=>'No file was uploaded',
            6=>'Missing a temporary folder',
            7=>'Failed to write file to disk.',
            8=>'A PHP extension stopped the file upload.',
            ];
        $code=intval($code);
        if (isset($codeArr[$code])){return $codeArr[$code];} else {return '';}
    }
    
    private function escapeAttrName(string $attrName):string
    {
        $attrName=preg_replace('/[^a-zA-Z0-9\-]/','',$attrName);
        return $attrName;
    }
    
    private function escapeAttrValue($attrValue):string
    {
        $pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
        $attrValue=htmlspecialchars(strval($attrValue),ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML401,$pageSettings['charset'],TRUE);
        return $attrValue;
    }

}
?>