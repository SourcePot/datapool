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

class MediaTools{

    private $oc;
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        // add calendar placeholder
        $this->oc['SourcePot\Datapool\Root']->addPlaceholder('{{nowDateUTC}}',$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now'));
        $this->oc['SourcePot\Datapool\Root']->addPlaceholder('{{YESTERDAY}}',$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('yesterday'));
        $this->oc['SourcePot\Datapool\Root']->addPlaceholder('{{TOMORROW}}',$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('tomorrow'));
        $this->oc['SourcePot\Datapool\Root']->addPlaceholder('{{TIMEZONE}}',\SourcePot\Datapool\Root::DB_TIMEZONE);
        $this->oc['SourcePot\Datapool\Root']->addPlaceholder('{{TIMEZONE-SERVER}}',date_default_timezone_get());
    }

    public function getPreview(array $arr):array
    {
        $arr['html']=$arr['html']??'';
        if (empty($arr['selector']['Source']) || empty($arr['selector']['EntryId'])){
            return $arr;
        }
        $arr=$this->addTmpFile($arr);
        // style property adjustments
        $arr['maxDim']=$arr['maxDim']??$arr['settings']['maxDim']??NULL;
        $arr['minDim']=$arr['minDim']??$arr['settings']['minDim']??NULL;
        if (!empty($arr['maxDim'])){
            $arr['settings']['style']['max-width']=$arr['settings']['style']['max-height']=$arr['maxDim'];
        }
        if (!empty($arr['wrapperSettings']['style'])){
            $styleArr=(is_array($arr['wrapperSettings']['style']))?$arr['wrapperSettings']['style']:$this->oc['SourcePot\Datapool\Tools\MiscTools']->style2arr($arr['wrapperSettings']['style']);
            if (isset($styleArr['max-width'])){
                $arr['settings']['style']['max-width']=intval($styleArr['max-width'])-10;
            }
            if (isset($styleArr['max-height'])){
                $arr['settings']['style']['max-height']=intval($styleArr['max-height'])-40;
            }
        }
        $isSmallPreview=(!empty($arr['settings']['style']['max-width']) || !empty($arr['settings']['style']['width']));
        // create preview based on 'MIME-Type'
        if (!isset($arr['selector']['Params']['TmpFile']['MIME-Type'])){
            $arr['html']='';
        } else if (mb_strpos($arr['selector']['Params']['TmpFile']['MIME-Type'],'image')===0){
            $imageHtml=$this->getImage($arr);
            // add wrapper div
            $wrapperStyleTemplate=['overflow'=>'hidden','cursor'=>'pointer'];
            $arr['wrapper']['style']=(isset($arr['wrapper']['style']))?$arr['wrapper']['style']:[];
            $imageArr=['tag'=>'div','element-content'=>$imageHtml,'keep-element-content'=>TRUE,'title'=>$arr['selector']['Name'],'class'=>'preview','source'=>$arr['selector']['Source'],'entry-id'=>$arr['selector']['EntryId']];
            $imageArr['id']='img-'.md5($arr['selector']['EntryId']);
            $imageArr['source']=$arr['selector']['Source'];
            $imageArr['entry-id']=$arr['selector']['EntryId'];
            if (isset($arr['containerId'])){$imageArr['id'].='-'.$arr['containerId'];}
            $imageArr['style']=array_merge($wrapperStyleTemplate,$arr['wrapper']['style']);
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($imageArr);
        } else if (mb_strpos($arr['selector']['Params']['TmpFile']['MIME-Type'],'application/json')===0){
            $json=$this->oc['SourcePot\Datapool\Root']->file_get_contents_utf8($arr['selector']['Params']['TmpFile']['Source']);
            $json=json_decode($json,TRUE,512,JSON_INVALID_UTF8_IGNORE);
            $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($json,\SourcePot\Datapool\Root::ONEDIMSEPARATOR,$isSmallPreview);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'caption'=>$arr['selector']['Name'],'keep-element-content'=>TRUE,'style'=>['clear'=>'both'],'class'=>'matrix']);
        } else if ($this->oc['SourcePot\Datapool\Tools\CSVtools']->isCSV($arr['selector']) && !$isSmallPreview){
            $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Container']->container('CSV editor','generic',$arr['selector'],['method'=>'csvEditor','classWithNamespace'=>'SourcePot\Datapool\Tools\CSVtools'],[]);
        } else if (!empty($arr['selector']['Params']['File']['Spreadsheet'])){
            $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($arr['selector']['Params']['File']['Spreadsheet'],\SourcePot\Datapool\Root::ONEDIMSEPARATOR,$isSmallPreview);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'caption'=>$arr['selector']['Name'],'keep-element-content'=>TRUE,'style'=>['clear'=>'both'],'class'=>'matrix']);
        } else if (mb_strpos($arr['selector']['Params']['TmpFile']['MIME-Type'],'application/zip')===0){
            $matrix=[];
            $zip=new \ZipArchive;
            if ($zip->open($arr['selector']['Params']['TmpFile']['Source'])===TRUE){
                $zipFileCount=$zip->numFiles;
                for( $i=0;$i<$zip->numFiles;$i++){ 
                    $stat=$zip->statIndex($i);
                    if (!$isSmallPreview || $i<3){
                        $matrix[]=['File'=>basename($stat['name'])];
                    } else if ($i>=3){
                        $matrix['']=['File'=>'...'];
                        break;
                    }
                }
                $zip->close();
                $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','style'=>['clear'=>'both'],'element-content'=>'Zip-archive: '.$zipFileCount.' files']);
            }
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'caption'=>$arr['selector']['Name'],'class'=>'','keep-element-content'=>TRUE,'style'=>['clear'=>'both']]);
        } else if (mb_strpos($arr['selector']['Params']['TmpFile']['MIME-Type'],'text/')===0 && $arr['selector']['Params']['TmpFile']['Extension']==='md'){
            $arr=$this->getMarkdown($arr);
        } else {
            $arr=$this->getObj($arr);
        }
        return $arr;
    }

    public function getIcon(array $arr):array|string
    {
        $arr['html']=$arr['html']??'';
        $arr['maxDim']=$arr['maxDim']??50;
        $arr['selector']['Params']['TmpFile']['MIME-Type']=$arr['selector']['Params']['TmpFile']['MIME-Type']??'text';
        $fontSize=round($arr['maxDim']*0.4);
        $arr=$this->addTmpFile($arr);
        // get text
        if (empty($arr['selector']['Name'])){
            $text='?';
        } else {
            $text=preg_replace('/[^A-Z]/','',$arr['selector']['Name']);
            $text=substr($text,0,2);
            $text=(empty($text))?'?':$text;
            $text=strtr($text,['WC'=>'CW','wc'=>'cw']);
        }
        // add content: text, image
        if (mb_strpos($arr['selector']['Params']['TmpFile']['MIME-Type'],'image')===0){
            $arr['returnImgFileOnly']=TRUE;
            $iconSrc=$this->getImage($arr);
        } else {
            $iconArr=['tag'=>'p','element-content'=>$text,'class'=>'icon','style'=>['font-size'=>$fontSize,'width'=>$arr['maxDim'],'line-height'=>$arr['maxDim']]];
            $iconHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($iconArr);
        }
        // add wrapper div
        $style=['width'=>$arr['maxDim'],'height'=>$arr['maxDim']];
        $imageArr=['tag'=>'div','element-content'=>'<br/>','keep-element-content'=>TRUE,'class'=>'icon','style'=>$style];
        $imageArr['title']=$text;
        if (isset($iconHtml)){
            $imageArr['element-content']=$iconHtml;
        }
        if (isset($iconSrc)){
            $imageArr['style']['background-image']='url('.$iconSrc.')';
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($imageArr);
        if (empty($arr['returnHtmlOnly'])){
            return $arr;
        } else {
            return $arr['html'];
        }
    }
    
    public function presentEntry(array $arr):array|string{
        if (empty($arr['selector'])){
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>__FUNCTION__.' called arr["selector"] being empty.']);
        } else if (!isset($arr['selector']['Content'])){
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>__FUNCTION__.' called, but arr["selector*]["Content"] is missing.']);    
        } else if (empty($arr['setting'])){
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>__FUNCTION__.' called with arr["setting"] being empty.']);
        }
        if (!isset($arr['html'])){$arr['html']='';}
        // compile entry and add presentation keys
        if (!empty($arr['setting']['Show userAbstract'])){$arr['selector'][':::userAbstract']=TRUE;}
        if (!empty($arr['setting']['Show getPreview'])){$arr['selector'][':::getPreview']=TRUE;}
        $arrElements=['arr'=>$arr,'elements'=>[]];
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($arr['selector']);
        foreach($flatEntry as $flatEntryKey=>$flatEntryValue){
            $flatEntryKey=str_replace(\SourcePot\Datapool\Root::ONEDIMSEPARATOR,'|',$flatEntryKey);
            $arrElements=$this->addElementFromKeySettingValue($arrElements,$flatEntryKey,$flatEntryValue);
        }
        // sort html elements and prsent them in order
        $arr=$arrElements['arr'];
        ksort($arrElements['elements']);
        foreach($arrElements['elements'] as $presentationIndex=>$element){
            if (!empty($element['element'])){
                $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element['element']);
            }
            if (isset($element['html'])){
                $arr['html'].=$element['html'];    
            }
        }
        if (!empty($arr['setting']['Show entry editor'])){$arr=$this->oc['SourcePot\Datapool\Foundation\Container']->entryEditor($arr);}
        if (empty($arr['returnHtmlOnly'])){
            return $arr;
        } else {
            return $arr['html'];
        }
    }
    
    private function addElementFromKeySettingValue(array $arrElements,$key,$value):array
    {
        // comments are excluded due to varying keys
        if (mb_strpos($key,'Content|Comments')===0){return $arrElements;}
        if (mb_strpos($arrElements['arr']['selector']['Source'],'settings')===0){return $arrElements;}
        // if setting key does not exist yet add the standard key-value
        if (!isset($arrElements['arr']['setting']['Key tags'][$key])){
            $arrElements['arr']['settingNeedsUpdate']=TRUE;
            
            $presentationIndex=1;
            while(isset($arrElements['elements'][$presentationIndex])){$presentationIndex+=10;}
            $arrElements['arr']['setting']['Key tags'][$key]=['presentation-index'=>$presentationIndex];
            
            $class=$arrElements['arr']['selector']['Source'];
            if (mb_strpos($key,':::')===0){
                $arrElements['arr']['setting']['Key tags'][$key]['class']=$class;
            } else {
                $style=['float'=>'left','clear'=>'both','font-size'=>'1em','padding'=>'0.5em','display'=>'initial'];
                if (strcmp($key,'Name')!==0 && strcmp($key,'Message')!==0 && strcmp($key,'Date')!==0){$style['display']='none';}
                $arrElements['arr']['setting']['Key tags'][$key]['element']=['tag'=>'p','style'=>$style,'class'=>$class,'keep-element-content'=>''];
            }
        }
        // create elements or widgets for presentation on the web page
        $presentationIndex=$arrElements['arr']['setting']['Key tags'][$key]['presentation-index'];
        $arrElements['elements'][$presentationIndex]=$arrElements['arr']['setting']['Key tags'][$key];
        $arrElements['elements'][$presentationIndex]['entryKey']=$key;
        if (isset($arrElements['arr']['setting']['Key tags'][$key])){
            if (mb_strpos($key,':::')===0){
                $widgetArr=$arrElements['arr'];
                if (strcmp($key,':::userAbstract')===0){
                    $widgetArr['wrapResult']=['tag'=>'div','style'=>['float'=>'left','clear'=>'both','width'=>'100%','color'=>'#000','background-color'=>'#fff8']];
                    $widgetArr['selector']=['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$arrElements['arr']['selector']['Owner']];
                    $arrElements['elements'][$presentationIndex]['html']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbtract($widgetArr,2);
                } else if (strcmp($key,':::getPreview')===0){
                    $widgetArr['returnHtmlOnly']=TRUE;
                    $widgetArr=array_merge($widgetArr,$arrElements['arr']['setting']['Key tags'][$key]);
                    $arrElements['elements'][$presentationIndex]['html']=$this->getPreview($widgetArr);
                }
            } else {
                $arrElements['elements'][$presentationIndex]['element']['element-content']=strval($value);
            }
        } else {
            $arrElements['elements'][$presentationIndex]['html']='<p>Unkown key "'.$key.'" used in "'.__FUNCTION__.'"</p>';
        }
        return $arrElements;
    }
    
    public function loadImage(array $arr):array|bool
    {
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
        $arr=$this->addTmpFile($arr);
        // get source file
        if (is_file($arr['selector']['Params']['TmpFile']['Source'])){
            // get target file
            $arr['src']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($arr['selector']['Params']['TmpFile']['Source']);
            $arr['title']='';
            if (!empty($arr['selector']['Params']['Address']['display_name'])){$arr['title']=$arr['selector']['Params']['Address']['display_name'];}
            if (!empty($arr['selector']['Params']['Geo']['alt'])){$arr['title'].=' | Altitude: '.$arr['selector']['Params']['Geo']['alt'];}
            $arr['title']=htmlentities($arr['title']);
            if (isset($arr['selector']['Params']['TmpFile']['Style class'])){
                $arr['class']=$arr['selector']['Params']['TmpFile']['Style class'];
            } else {
                $arr['class']='';
            }
            return $arr;
        } else {
            return FALSE;
        }
    }
    
    private function getMarkdown(array $arr):array
    {
        $arr['settings']['style']=$arr['settings']['style']??[];
        $arr['settings']['style']=array_merge(['overflow'=>'hidden'],$arr['settings']['style']);
        // process form
        $markDownId=md5(__FUNCTION__.$arr['selector']['Source'].$arr['selector']['EntryId']);
        $arr['callingFunction']=$markDownId;
        $btnArr=$arr;
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,$markDownId);
        if (isset($formData['val']['content'])){
            $source=key($formData['val']['content']);
            $entryId=key($formData['val']['content'][$source]);
            $mdFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file(['Source'=>$source,'EntryId'=>$entryId]);
            $md=$formData['val']['content'][$source][$entryId];
            file_put_contents($mdFile,$md);
        } else {
            $md=file_get_contents($arr['selector']['Params']['TmpFile']['Source']);
        }
        if ($this->oc['SourcePot\Datapool\Tools\NetworkTools']->getEditMode($arr['selector'])){
            $contentArr=['tag'=>'textarea','element-content'=>$md,'keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>$markDownId];
            $contentArr['key']=['content',$arr['selector']['Source'],$arr['selector']['EntryId']];
            $contentArr['style']=['text-align'=>'left','width'=>'98%','height'=>'50vh'];
            $contentArr['class']='code';
            $contentHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($contentArr);
            $btnArr['cmd']='show';
        } else {
            $contentHtml=\Michelf\Markdown::defaultTransform($md);
            $btnArr['cmd']='edit';
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$contentHtml,'keep-element-content'=>TRUE,'style'=>$arr['settings']['style']]);
        if (empty($arr['settings']['style']['height']) && empty($arr['settings']['style']['max-height'])){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
        }
        return $arr;
    }
    
    private function getObj(array $arr):array
    {
        $arr['html']=$arr['html']??'';
        $arr['settings']['style']=$arr['settings']['style']??[];
        $arr['settings']['style']['float']=$arr['settings']['style']['float']??'left';
        $arr['settings']['style']['margin']=$arr['settings']['style']['margin']??'10px 0 0 5px';
        $arr['settings']['style']['max-height']=$arr['settings']['style']['maxDim']??$arr['settings']['style']['max-height']??NULL;
        $arr['settings']['style']['max-width']=$arr['settings']['style']['maxDim']??$arr['settings']['style']['max-width']??NULL;
        if (is_file($arr['selector']['Params']['TmpFile']['Source'])){
            $objArr=$arr;
            $objArr['tag']='object';
            $objArr['data']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($arr['selector']['Params']['TmpFile']['Source']);
            $objArr['type']=$arr['selector']['Params']['File']['MIME-Type']??$arr['selector']['Params']['TmpFile']['MIME-Type'];
            $objArr['style']=$objArr['settings']['style'];
            $objArr['element-content']='<a href="'.$objArr['data'].'" style="float:left;clear:both;padding:2rem;" target="_blank">File <b>'.($arr['selector']['Params']['File']['Name']??'').'</b> can\'t be presented, click here to download...</a>';
            $objArr['keep-element-content']=TRUE;
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($objArr);
        } else {
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>'Sorry, file '.($arr['Params']['TmpFile']['Name']??'').' could not be copied into the presentation folder.']);
        }
        $arr['wrapperSettings']=['style'=>'width:95%;'];
        return $arr;
    }    
    
    private function getImage(array $arr):string|array
    {
        $entry=$arr['selector']??$arr;
        // return svg file without processing 
        if (stripos($entry['Params']['TmpFile']['MIME-Type'],'/svg')!==FALSE){
            return $this->oc['SourcePot\Datapool\Root']->file_get_contents_utf8($entry['Params']['TmpFile']['Source']);
        }
        // get image properties
        $tmpArr=@getimagesize($entry['Params']['TmpFile']['Source']);
        if (empty($tmpArr)){
            // is not an image
            $arr['tag']='p';
            $arr['element-content']=$entry['Params']['TmpFile']['Name'].' seems to be not an image file...';
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
        }
        // target file to be saved in the tmp directory
        $arr['targetFile']=$entry['Params']['TmpFile']['Source'];
        $arr['src']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($arr['targetFile']);
        // is an image
        $arr['styleClass']=(empty($entry['Params']['TmpFile']['Style class']))?'rotate0':$entry['Params']['TmpFile']['Style class'];
        $arr=$this->stylestyleClass2style($arr,$entry['Params']['TmpFile']['Source']);
        $imgPropArr=[
            'sourceFile'=>$entry['Params']['TmpFile']['Source'],
            'targetFile'=>$arr['targetFile'],
            'fileType'=>$tmpArr['mime'],
            'absSinRot'=>abs(sin(deg2rad($arr['settings']['style']['deg'])))??0,
            'scaler'=>1,
            'orgWidth'=>$tmpArr[0],
            'orgHeight'=>$tmpArr[1],
            'orgRot'=>$arr['settings']['style']['deg'],
            'orgFlipped'=>$arr['settings']['style']['flip']??FALSE,
            'quality'=>90,
            'maxDim'=>$arr['maxDim']??$arr['settings']['style']['maxDim']??$arr['style']['maxDim']??NULL,
            'minDim'=>$arr['minDim']??$arr['settings']['style']['minDim']??$arr['style']['minDim']??0,
            'max-width'=>$arr['settings']['style']['max-width']??$arr['style']['max-width']??NULL,
            'max-height'=>$arr['settings']['style']['max-height']??$arr['style']['max-height']??NULL,
            ];
        // create image from file
        $orgImage=FALSE;
        try{
            if (mb_strpos($imgPropArr['fileType'],'/png')!==FALSE){
                $orgImage=imagecreatefrompng($imgPropArr['sourceFile']);
            } else if (mb_strpos($imgPropArr['fileType'],'/gif')!==FALSE){
                $orgImage=imagecreatefromgif($imgPropArr['sourceFile']);
            } else if (mb_strpos($imgPropArr['fileType'],'/bmp')!==FALSE){
                $orgImage=imagecreatefromwbmp($imgPropArr['sourceFile']);
            } else if (mb_strpos($imgPropArr['fileType'],'/webp')!==FALSE){
                $orgImage=imagecreatefromwebp($imgPropArr['sourceFile']);
            } else if (mb_strpos($imgPropArr['fileType'],'/jpg')!==FALSE){
                $orgImage=imagecreatefromjpeg($imgPropArr['sourceFile']);
            } else if (mb_strpos($imgPropArr['fileType'],'/jpeg')!==FALSE){
                $orgImage=imagecreatefromjpeg($imgPropArr['sourceFile']);
            } else {
                $string=$this->oc['SourcePot\Datapool\Root']->file_get_contents_utf8($entry['Params']['TmpFile']['Source']);
                if ($this->isBase64Encoded($string)){$string=base64_decode($string);}
                $orgImage=@imagecreatefromstring($string);
                if ($orgImage===FALSE){return 'Failed to create image';}
            }
        } catch(\Exception $e) {
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" caught exception {message}.',['class'=>__CLASS__,'function'=>__FUNCTION__,'message'=>$e->getMessage()]);
            return $arr;
        }
        // get orgininal width, height after rotation
        if ($imgPropArr['absSinRot']>0.5){
            $tmp=$imgPropArr['max-width'];
            $imgPropArr['max-width']=$imgPropArr['max-width'];
            $imgPropArr['max-width']=$tmp;
            //
            $tmp=$imgPropArr['min-width'];
            $imgPropArr['min-width']=$imgPropArr['min-width'];
            $imgPropArr['min-width']=$tmp;
        }
        // get minimum scaler if scaling-up if minDim is set
        if ($imgPropArr['orgWidth']<$imgPropArr['minDim']){
            $scaler=$imgPropArr['minDim']/$imgPropArr['orgWidth'];
            $imgPropArr['scaler']=($imgPropArr['scaler']<$scaler)?$scaler:$imgPropArr['scaler'];
        }
        if ($imgPropArr['orgHeight']<$imgPropArr['minDim']){
            $scaler=$imgPropArr['minDim']/$imgPropArr['orgHeight'];
            $imgPropArr['scaler']=($imgPropArr['scaler']<$scaler)?$scaler:$imgPropArr['scaler'];
        }
        // get maximum scaler from max-width, max-height
        if ($imgPropArr['orgWidth']>$imgPropArr['max-width'] && $imgPropArr['max-width']!==NULL){
            $scaler=$imgPropArr['max-width']/$imgPropArr['orgWidth'];
            $imgPropArr['scaler']=($imgPropArr['scaler']>$scaler)?$scaler:$imgPropArr['scaler'];
        }
        if ($imgPropArr['orgHeight']>$imgPropArr['max-height'] && $imgPropArr['max-height']!==NULL){
            $scaler=$imgPropArr['max-height']/$imgPropArr['orgHeight'];
            $imgPropArr['scaler']=($imgPropArr['scaler']>$scaler)?$scaler:$imgPropArr['scaler'];
        }
        // reduce scale if height of width larger than maxDim
        if ($imgPropArr['orgWidth']>$imgPropArr['maxDim'] && $imgPropArr['maxDim']!==NULL){
            $scaler=$imgPropArr['maxDim']/$imgPropArr['orgWidth'];
            $imgPropArr['scaler']=($imgPropArr['scaler']>$scaler)?$scaler:$imgPropArr['scaler'];
        }
        if ($imgPropArr['orgHeight']>$imgPropArr['maxDim'] && $imgPropArr['maxDim']!==NULL){
            $scaler=$imgPropArr['maxDim']/$imgPropArr['orgHeight'];
            $imgPropArr['scaler']=($imgPropArr['scaler']>$scaler)?$scaler:$imgPropArr['scaler'];
        }
        // scale image
        if ($imgPropArr['scaler']===1){
            $newImage=$orgImage;
        } else {
            $imgPropArr['newWidth']=intval($imgPropArr['scaler']*$imgPropArr['orgWidth']);
            $imgPropArr['newHeight']=intval($imgPropArr['scaler']*$imgPropArr['orgHeight']);
            $newImage=imagecreatetruecolor($imgPropArr['newWidth'],$imgPropArr['newHeight']);
            imagecopyresampled($newImage,$orgImage,0,0,0,0,$imgPropArr['newWidth'],$imgPropArr['newHeight'],$imgPropArr['orgWidth'],$imgPropArr['orgHeight']);
        }
        imagealphablending($newImage,TRUE); 
        // rotate image
        if ($imgPropArr['orgRot']!==0){
            $newImage=imagerotate($newImage,$imgPropArr['orgRot'],0);
        }
        // flip image
        if (!empty($imgPropArr['orgFlipped'])){
            imageflip($newImage,$imgPropArr['orgFlipped']);
        }
        // save to target
        $html='';
        $imageTagArr=$arr['tag']??[];
        $imageTagArr['tag']='img';
        $imageTagArr['title']=$entry['Params']['TmpFile']['Name'];
        $imageTagArr['id']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($imgPropArr);
        if (isset($arr['containerId'])){$imageTagArr['container-id']=$arr['containerId'];}
        if (empty($arr['encodeBase64'])){
            imagejpeg($newImage,$arr['targetFile'],$imgPropArr['quality']);
            chmod($arr['targetFile'],0774);
            imagedestroy($orgImage);
            @imagedestroy($newImage);
            if (empty($arr['returnImgFileOnly'])){
                $imageTagArr['src']=$arr['src'];
                $dims=getimagesize($arr['targetFile']);
                $imageTagArr['orgwidth']=$dims[0];
                $imageTagArr['orgheight']=$dims[1];
                $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($imageTagArr);
                if (!empty($arr['Date'])){$html.='<p class="imgOverlay">'.$arr['Date'].'</p>';}
            } else {
                return $arr['src'];
            }
        } else {
            ob_start();
            imagejpeg($newImage);
            $imagedata=ob_get_contents();
            ob_end_clean();
            $imageTagArr['src']='data:image/png;base64,'.base64_encode($imagedata);
            $imageTagArr['keep-element-content']=TRUE;
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($imageTagArr);
        }
        return $html;
    }
        
    private function stylestyleClass2style(array $arr, string $file=''):array
    {
        $arr['settings']['style']['deg']=intval(preg_replace('/[^0-9]/','',$arr['styleClass']));
        // get exif
        $exif=$this->addExif2entry([],$file)['exif']??[];
        $exif['Rotation']=$exif['Rotation']??$exif['Orientation']??1;
        // rotation
        if ($exif['Rotation']===1 || $exif['Rotation']===2){
            $arr['settings']['style']['deg']=0;
        } else if ($exif['Rotation']===8 || $exif['Rotation']===7){
            $arr['settings']['style']['deg']=90;
        } else if ($exif['Rotation']===3 || $exif['Rotation']===4){
            $arr['settings']['style']['deg']=180;
        } else if ($exif['Rotation']===6 || $exif['Rotation']===5){
            $arr['settings']['style']['deg']=270;
        }
        // mirrored
        if ($exif['Rotation']===2 || $exif['Rotation']===4){
            $arr['styleClass']='flippedX';
        } else if ($exif['Rotation']===7 || $exif['Rotation']===5){
            $arr['styleClass']='flippedY';
        }
        // get if flipped
        if (strpos($arr['styleClass'],'flippedX')!==FALSE){
            $arr['settings']['style']['flip']=IMG_FLIP_HORIZONTAL;
        } else if (strpos($arr['styleClass'],'flippedY')!==FALSE){
            $arr['settings']['style']['flip']=IMG_FLIP_VERTICAL;
        } else if (strpos($arr['styleClass'],'flippedXY')!==FALSE){
            $arr['settings']['style']['flip']=IMG_FLIP_BOTH;
        } 
        return $arr;
    }

    private function isBase64Encoded($data):bool
    {
        if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%',$data)){
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function addGPano2entry(array $entry,string $file):array
    {
        $endNeedle='</x:xmpmeta>';
        $fileContent=file_get_contents($file);
        if (str_contains($fileContent,'GPano=')){
            // extract GPano section
            $startPos=strpos($fileContent,'<x:xmpmeta');
            $endPos=strpos($fileContent,$endNeedle);
            $length=($endPos+strlen($endNeedle))-$startPos;
            $xml=substr($fileContent,$startPos,$length);
            // parse xml
            $GPano=[];
            $GPanoComps=explode('GPano:',$xml);
            array_shift($GPanoComps);
            foreach($GPanoComps as $GPanoStr){
                preg_match('/([^=]+)="([^"]+)"/',$GPanoStr,$matches);
                if (!isset($matches[0])){continue;}
                $GPano[$matches[1]]=$matches[2];
            }
            $entry['Params']['GPano']=$GPano;
        }
        return $entry;
    }
    
    public function addTmpFile(array $arr):array
    {
        $tmpDir=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
        $tmpFileName=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($arr['selector'],TRUE);
        $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr['selector']);
        if (is_file($sourceFile) && !empty($arr['selector']['Params']['File']['Extension'])){
            $tmpFile=$tmpDir.$tmpFileName.'.'.$arr['selector']['Params']['File']['Extension'];
            $this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($sourceFile,$tmpFile);
            $arr['selector']['Params']['TmpFile']=$arr['selector']['Params']['File'];
        } else if(isset($arr['selector']['Content']['Html'])){
            $tmpFile=$tmpDir.$tmpFileName.'.html';
            file_put_contents($tmpFile,$arr['selector']['Content']['Html']);
            $arr['selector']['Params']['TmpFile']['Extension']='html';
            $arr['selector']['Params']['TmpFile']['MIME-Type']='application/xhtml+xml';
        } else if(isset($arr['selector']['Content']['Plain'])){
            $tmpFile=$tmpDir.$tmpFileName.'.txt';
            file_put_contents($tmpFile,$arr['selector']['Content']['Plain']);
            $arr['selector']['Params']['File']['Extension']='txt';
            $arr['selector']['Params']['File']['MIME-Type']='text/plain';
        } else {
            return $arr;
        }
        $arr['selector']['Params']['TmpFile']['Source']=$tmpFile;
        return $arr;
    }
    
    public function addExif2entry(array $entry,string $file):array
    {
        $entry['exif']=[];   
        if (!is_file($file)){
            // no attched file
        } else if (!function_exists('exif_read_data')){
            $this->oc['logger']->log('warning','Exif Function "exif_read_data" missing',[]);   
        } else {
            $exif=@exif_read_data($file,'IFD0');
            $entry['exif']=(empty($exif))?[]:$exif;
        }
        return $entry;
    }

}
?>