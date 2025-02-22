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
        if (!isset($arr['html'])){$arr['html']='';}
        if (!empty($arr['maxDim'])){
            $arr['settings']['style']['max-width']=$arr['maxDim'];
            $arr['settings']['style']['max-height']=$arr['maxDim'];
        }
        if (!empty($arr['wrapperSettings']['style'])){
            $styleArr=(is_array($arr['wrapperSettings']['style']))?$arr['wrapperSettings']['style']:$this->oc['SourcePot\Datapool\Tools\MiscTools']->style2arr($arr['wrapperSettings']['style']);
            if (isset($styleArr['max-width'])){$arr['settings']['style']['max-width']=intval($styleArr['max-width'])-10;}
            if (isset($styleArr['max-height'])){$arr['settings']['style']['max-height']=intval($styleArr['max-height'])-40;}
        }
        $isSmallPreview=(!empty($arr['settings']['style']['max-width']) || !empty($arr['settings']['style']['width']));
        if (empty($arr['selector']['Source']) || empty($arr['selector']['EntryId'])){return $arr;}
        $arr=$this->addTmpFile($arr);
        if (!isset($arr['selector']['Params']['TmpFile']['MIME-Type'])){
            $arr['html']='';
        } else if (mb_strpos($arr['selector']['Params']['TmpFile']['MIME-Type'],'image')===0){
            $imageHtml=$this->getImage($arr);
            // add wrapper div
            $wrapperStyleTemplate=array('overflow'=>'hidden','cursor'=>'pointer');
            $arr['wrapper']['style']=(isset($arr['wrapper']['style']))?$arr['wrapper']['style']:array();
            $imageArr=array('tag'=>'div','element-content'=>$imageHtml,'keep-element-content'=>TRUE,'title'=>$arr['selector']['Name'],'class'=>'preview','source'=>$arr['selector']['Source'],'entry-id'=>$arr['selector']['EntryId']);
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
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'caption'=>$arr['selector']['Name'],'keep-element-content'=>TRUE,'style'=>array('clear'=>'both'),'class'=>'matrix'));
        } else if ($this->oc['SourcePot\Datapool\Tools\CSVtools']->isCSV($arr['selector']) && !$isSmallPreview){
            $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Container']->container('CSV editor','generic',$arr['selector'],array('method'=>'csvEditor','classWithNamespace'=>'SourcePot\Datapool\Tools\CSVtools'),array());
        } else if (!empty($arr['selector']['Params']['File']['Spreadsheet'])){
            $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($arr['selector']['Params']['File']['Spreadsheet'],\SourcePot\Datapool\Root::ONEDIMSEPARATOR,$isSmallPreview);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'caption'=>$arr['selector']['Name'],'keep-element-content'=>TRUE,'style'=>array('clear'=>'both'),'class'=>'matrix'));
        } else if (mb_strpos($arr['selector']['Params']['TmpFile']['MIME-Type'],'application/zip')===0){
            $matrix=array();
            $zip=new \ZipArchive;
            if ($zip->open($arr['selector']['Params']['TmpFile']['Source'])===TRUE){
                $zipFileCount=$zip->numFiles;
                for( $i=0;$i<$zip->numFiles;$i++){ 
                    $stat=$zip->statIndex($i);
                    if (!$isSmallPreview || $i<3){
                        $matrix[]=array('File'=>basename($stat['name']));
                    } else if ($i>=3){
                        $matrix['']=array('File'=>'...');
                        break;
                    }
                }
                $zip->close();
                $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','style'=>array('clear'=>'both'),'element-content'=>'Zip-archive: '.$zipFileCount.' files'));
            }
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'caption'=>$arr['selector']['Name'],'class'=>'','keep-element-content'=>TRUE,'style'=>array('clear'=>'both')));
        } else if (mb_strpos($arr['selector']['Params']['TmpFile']['MIME-Type'],'text/')===0 && $arr['selector']['Params']['TmpFile']['Extension']==='md'){
            $arr=$this->getMarkdown($arr);
        } else {
            $arr=$this->getObj($arr);
        }
        return $arr;
    }

    public function getIcon(array $arr):array|string
    {
        $arr=$this->addTmpFile($arr);
        if (!isset($arr['html'])){$arr['html']='';}
        if (empty($arr['selector']['Name'])){$text='?';} else {$text=$arr['selector']['Name'];}
        if (!isset($arr['selector']['Params']['TmpFile']['MIME-Type'])){$arr['selector']['Params']['TmpFile']['MIME-Type']='text';}
        if (mb_strpos($arr['selector']['Params']['TmpFile']['MIME-Type'],'image')===0){
            $arr['returnImgFileOnly']=TRUE;
            $arr['maxDim']=100;
            $iconSrc=$this->getImage($arr);
        } else {
            $iconArr=array('tag'=>'p','element-content'=>$text,'class'=>'icon');
            $iconHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($iconArr);
        }
        $imageArr=array('tag'=>'div','element-content'=>'<br/>','keep-element-content'=>TRUE,'class'=>'icon');
        $imageArr['title']=$text;
        if (isset($iconHtml)){$imageArr['element-content']=$iconHtml;}
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
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>__FUNCTION__.' called arr["selector"] being empty.'));
        } else if (!isset($arr['selector']['Content'])){
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>__FUNCTION__.' called, but arr["selector*]["Content"] is missing.'));    
        } else if (empty($arr['setting'])){
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>__FUNCTION__.' called with arr["setting"] being empty.'));    
        }
        if (!isset($arr['html'])){$arr['html']='';}
        // compile entry and add presentation keys
        if (!empty($arr['setting']['Show userAbstract'])){$arr['selector'][':::userAbstract']=TRUE;}
        if (!empty($arr['setting']['Show getPreview'])){$arr['selector'][':::getPreview']=TRUE;}
        $arrElements=array('arr'=>$arr,'elements'=>array());
        $S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($arr['selector']);
        foreach($flatEntry as $flatEntryKey=>$flatEntryValue){
            $flatEntryKey=str_replace($S,'|',$flatEntryKey);
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
            $arrElements['arr']['setting']['Key tags'][$key]=array('presentation-index'=>$presentationIndex);
            
            $class=$arrElements['arr']['selector']['Source'];
            if (mb_strpos($key,':::')===0){
                $arrElements['arr']['setting']['Key tags'][$key]['class']=$class;
            } else {
                $style=array('float'=>'left','clear'=>'both','font-size'=>'1em','padding'=>'0.5em','display'=>'initial');
                if (strcmp($key,'Name')!==0 && strcmp($key,'Message')!==0 && strcmp($key,'Date')!==0){$style['display']='none';}
                $arrElements['arr']['setting']['Key tags'][$key]['element']=array('tag'=>'p','style'=>$style,'class'=>$class,'keep-element-content'=>'');
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
                    $widgetArr['wrapResult']=array('tag'=>'div','style'=>array('float'=>'left','clear'=>'both','width'=>'100%','color'=>'#000','background-color'=>'#fff8'));
                    $widgetArr['selector']=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$arrElements['arr']['selector']['Owner']);
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
        if (!isset($arr['settings']['style'])){$arr['settings']['style']=array();}
        $arr['settings']['style']=array_merge(array('overflow'=>'hidden'),$arr['settings']['style']);
        $selector=array('Source'=>$arr['selector']['Source'],'EntryId'=>$arr['selector']['EntryId'],'Write'=>$arr['selector']['Write'],'Write'=>$arr['selector']['Read']);
        // process form
        $markDownId=md5(__FUNCTION__.$arr['selector']['Source'].$arr['selector']['EntryId']);
        $arr['callingFunction']=$markDownId;
        $btnArr=$arr;
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,$markDownId);
        if (isset($formData['val']['content'])){
            $source=key($formData['val']['content']);
            $entryId=key($formData['val']['content'][$source]);
            $mdFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file(array('Source'=>$source,'EntryId'=>$entryId));
            $md=$formData['val']['content'][$source][$entryId];
            file_put_contents($mdFile,$md);
        } else {
            $md=file_get_contents($arr['selector']['Params']['TmpFile']['Source']);
        }
        if ($this->oc['SourcePot\Datapool\Tools\NetworkTools']->getEditMode($arr['selector'])){
            $contentArr=array('tag'=>'textarea','element-content'=>$md,'keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>$markDownId);
            $contentArr['key']=array('content',$arr['selector']['Source'],$arr['selector']['EntryId']);
            $contentArr['style']=array('text-align'=>'left','width'=>'98%','height'=>'50vh');
            $contentArr['class']='code';
            $contentHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($contentArr);
            $btnArr['cmd']='show';
        } else {
            $contentHtml=\Michelf\Markdown::defaultTransform($md);
            $btnArr['cmd']='edit';
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$contentHtml,'keep-element-content'=>TRUE,'style'=>$arr['settings']['style']));
        if (empty($arr['settings']['style']['height']) && empty($arr['settings']['style']['max-height'])){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
        }
        return $arr;
    }
    
    private function getObj(array $arr):array
    {
        if (!isset($arr['html'])){$arr['html']='';}
        if (!isset($arr['settings']['style'])){$arr['settings']['style']=array();}
        $arr['settings']['style']=array_merge(array('float'=>'left','margin'=>'10px 0 0 5px','height'=>'70vh','width'=>'95vw','border'=>'1px dotted #444'),$arr['settings']['style']);
        if (is_file($arr['selector']['Params']['TmpFile']['Source'])){
            $pdfArr=$arr;
            $pdfArr['tag']='object';
            $pdfArr['data']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($arr['selector']['Params']['TmpFile']['Source']);
            $pdfArr['type']=$arr['selector']['Params']['File']['MIME-Type'];
            $pdfArr['style']=$pdfArr['settings']['style'];
            $pdfArr['element-content']='<a href="'.$pdfArr['data'].'" style="float:left;clear:both;padding:2rem;" target="_blank">File <b>'.$arr['selector']['Params']['File']['Name'].'</b> can\'t be presented, click here to download...</a>';
            $pdfArr['keep-element-content']=TRUE;
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pdfArr);
        } else {
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>'Sorry, file '.$arr['Params']['TmpFile']['Name'].' could not be copied into the presentation folder.'));
        }
        $arr['wrapperSettings']=array('style'=>'width:95%;');
        return $arr;
    }    
    
    private function getImage(array $arr):string|array
    {
        if (isset($arr['settings']['style']['max-width'])){
            $arr['maxDim']=$arr['settings']['style']['max-width'];
        }
        if (isset($arr['settings']['style']['max-height'])){
            $arr['maxDim']=$arr['settings']['style']['max-height'];
        }
        // return svg file without processing 
        if (stripos($arr['selector']['Params']['TmpFile']['MIME-Type'],'/svg')!==FALSE){
            return $this->oc['SourcePot\Datapool\Root']->file_get_contents_utf8($arr['selector']['Params']['TmpFile']['Source']);
        }
        // target file saved in the tmp directory
        $arr['targetFile']=$arr['selector']['Params']['TmpFile']['Source'];
        $arr['src']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($arr['targetFile']);
        // resampling will destroy exif data, rotation and flipping required
        $tmpArr=@getimagesize($arr['selector']['Params']['TmpFile']['Source']);
        if (empty($tmpArr)){
            // is not an image
            $arr['tag']='p';
            $arr['element-content']=$arr['selector']['Params']['TmpFile']['Name'].' seems to be not an image file...';
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
        } else {
            // is an image
            (empty($arr['selector']['Params']['TmpFile']['Style class']))?$arr['styleClass']='rotate0':$arr['styleClass']=$arr['selector']['Params']['TmpFile']['Style class'];
            $transformArr=$this->styleClass2Params($arr);
            $absSinRot=abs(sin(deg2rad($transformArr['deg'])));
            $imgArr=array('sourceFile'=>$arr['selector']['Params']['TmpFile']['Source'],
                          'targetFile'=>$arr['targetFile'],
                          'fileType'=>$tmpArr['mime'],
                          'absSinRot'=>$absSinRot,
                          'scaler'=>1,
                          'orgWidth'=>$tmpArr[0],
                          'orgHeight'=>$tmpArr[1],
                          'orgRot'=>$transformArr['deg'],
                          'orgFlipped'=>$transformArr['flip'],
                          'newSize'=>(isset($arr['newSize']))?$arr['newSize']:FALSE,
                          'newWidth'=>(isset($arr['newWidth']))?$arr['newWidth']:FALSE,
                          'newHeight'=>(isset($arr['newHeight']))?$arr['newHeight']:FALSE,
                          'maxDim'=>(isset($arr['maxDim']))?$arr['maxDim']:FALSE,
                          'minDim'=>(isset($arr['minDim']))?$arr['minDim']:FALSE,
                          'quality'=>90,
                          );
            //-- create image from file
            $orgImage=FALSE;
            try{
                if (mb_strpos($imgArr['fileType'],'/png')!==FALSE){
                    $orgImage=imagecreatefrompng($imgArr['sourceFile']);
                } else if (mb_strpos($imgArr['fileType'],'/gif')!==FALSE){
                    $orgImage=imagecreatefromgif($imgArr['sourceFile']);
                } else if (mb_strpos($imgArr['fileType'],'/bmp')!==FALSE){
                    $orgImage=imagecreatefromwbmp($imgArr['sourceFile']);
                } else if (mb_strpos($imgArr['fileType'],'/webp')!==FALSE){
                    $orgImage=imagecreatefromwebp($imgArr['sourceFile']);
                } else if (mb_strpos($imgArr['fileType'],'/jpg')!==FALSE){
                    $orgImage=imagecreatefromjpeg($imgArr['sourceFile']);
                } else if (mb_strpos($imgArr['fileType'],'/jpeg')!==FALSE){
                    $orgImage=imagecreatefromjpeg($imgArr['sourceFile']);
                } else {
                    $string=$this->oc['SourcePot\Datapool\Root']->file_get_contents_utf8($arr['selector']['Params']['TmpFile']['Source']);
                    if ($this->isBase64Encoded($string)){$string=base64_decode($string);}
                    $orgImage=@imagecreatefromstring($string);
                    if ($orgImage===FALSE){return 'Failed to create image';}
                }
            } catch(\Exception $e) {
                $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" caught exception {message}.',array('class'=>__CLASS__,'function'=>__FUNCTION__,'message'=>$e->getMessage()));
                return $arr;
            }
            //-- scale image
            if ($imgArr['absSinRot']>0.5){
                $imgArr['_newWidth']=$imgArr['orgHeight'];
                $imgArr['_newHeight']=$imgArr['orgWidth'];
            } else {
                $imgArr['_newWidth']=$imgArr['orgWidth'];
                $imgArr['_newHeight']=$imgArr['orgHeight'];    
            }
            if ($imgArr['_newWidth']>$imgArr['_newHeight']){
                $imgArr['_maxDim']=$imgArr['_newWidth'];
                $imgArr['_minDim']=$imgArr['_newHeight'];
            } else {
                $imgArr['_maxDim']=$imgArr['_newHeight'];
                $imgArr['_minDim']=$imgArr['_newWidth'];
            }
            if (!empty($imgArr['minDim'])){
                $imgArr['scaler']=intval($imgArr['minDim'])/$imgArr['_minDim'];
            } else if (!empty($imgArr['maxDim'])){
                $imgArr['scaler']=intval($imgArr['maxDim'])/$imgArr['_maxDim'];
            } else if (!empty($imgArr['newSize'])){
                $imgArr['scaler']=sqrt($imgArr['newSize']/($imgArr['_newWidth']*$imgArr['_newHeight']));
            } else {
                if ($imgArr['newWidth']===FALSE){$scalerWidth=1;} else {$scalerWidth=$imgArr['newWidth']/$imgArr['_newWidth'];}
                if ($imgArr['newHeight']===FALSE){$scalerHeight=1;} else {$scalerHeight=$imgArr['newHeight']/$imgArr['_newHeight'];}
                if ($scalerHeight>$scalerWidth){$imgArr['scaler']=$scalerWidth;} else {$imgArr['scaler']=$scalerHeight;}
            }
            if ($imgArr['scaler']===1){
                $newImage=$orgImage;
            } else {
                $imgArr['newWidth']=intval(round($imgArr['scaler']*$imgArr['orgWidth']));
                $imgArr['newHeight']=intval(round($imgArr['scaler']*$imgArr['orgHeight']));
                $newImage=imagecreatetruecolor($imgArr['newWidth'],$imgArr['newHeight']);
                imagecopyresampled($newImage,$orgImage,0,0,0,0,$imgArr['newWidth'],$imgArr['newHeight'],$imgArr['orgWidth'],$imgArr['orgHeight']);
            }
            imagealphablending($newImage,TRUE); 
            // rotate and image
            if ($imgArr['orgRot']!==0){$newImage=imagerotate($newImage,-$imgArr['orgRot'],0);}
            if ($imgArr['orgFlipped']!==FALSE){imageflip($newImage,$imgArr['orgFlipped']);}
            // save to target
            $html='';
            $imageTagArr=$arr;
            $imageTagArr['tag']='img';
            $imageTagArr['title']=$arr['selector']['Params']['TmpFile']['Name'];
            $imageTagArr['id']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($imgArr);
            if (isset($arr['containerId'])){$imageTagArr['container-id']=$arr['containerId'];}
            if (empty($arr['encodeBase64'])){
                imagejpeg($newImage,$arr['targetFile'],$imgArr['quality']);
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
        }
        return $html;
    }
        
    private function styleClass2Params(array $arr):array
    {
        $transformArr=array('deg'=>0,'flip'=>FALSE);
        $styleComps=explode(' ',$arr['styleClass']);
        $transformArr['deg']=intval(preg_replace('/[^0-9]/','',$styleComps[0]));
        if (empty($styleComps[1])){return $transformArr;}
        if (strcmp($styleComps[1],'flippedX')){
            $transformArr['flip']=IMG_FLIP_HORIZONTAL;
        } else if (strcmp($styleComps[1],'flippedY')){
            $transformArr['flip']=IMG_FLIP_VERTICAL;
        } else if (strcmp($styleComps[1],'flippedXY')){
            $transformArr['flip']=IMG_FLIP_BOTH;
        } 
        return $transformArr;
    }

    private function isBase64Encoded($data):bool
    {
        if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%',$data)){
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function scaleImageToCover(array $arr,string $imgFile,array $boxStyleArr):array
    {
        $imgPropArr=$this->getImgPropArr($imgFile);
        $arr=$this->resetScaleImage($arr);
        if ($imgPropArr['width']<$boxStyleArr['width'] && $imgPropArr['height']>$boxStyleArr['height']){
            $variant='A';
            $arr['minDim']=$boxStyleArr['width'];
        } else if ($imgPropArr['width']>$boxStyleArr['width'] && $imgPropArr['height']<$boxStyleArr['height']){
            $variant='B';
            $arr['minDim']=$boxStyleArr['height'];
        } else {
            $variant='C,D';    // not tested
            // scale image width to match box width
            $newImgHeight=$imgPropArr['height']*$boxStyleArr['width']/$imgPropArr['width'];
            if ($newImgHeight>$boxStyleArr['height']){
                // height > box
                $arr['newWidth']=$boxStyleArr['width'];
                $arr['newHeight']=$newImgHeight;
            } else {
                // height < box
                $newImgWidth=$imgPropArr['width']*$boxStyleArr['height']/$imgPropArr['height'];
                $arr['newHeight']=$boxStyleArr['height'];
                $arr['newWidth']=$newImgWidth;
            }
        }
        return $arr;
    }    

    public function scaleImageToContain(array $arr,string $imgFile,array $boxStyleArr):array
    {
        $imgPropArr=$this->getImgPropArr($imgFile);
        $arr=$this->resetScaleImage($arr);
        if ($imgPropArr['width']<$boxStyleArr['width'] && $imgPropArr['height']>$boxStyleArr['height']){
            $variant='C';    // OK
            //$arr['settings']['style']['max-height']='100%';
            $arr['newHeight']=$boxStyleArr['height'];
        } else if ($imgPropArr['width']>$boxStyleArr['width'] && $imgPropArr['height']<$boxStyleArr['height']){
            $variant='D';    // OK
            //$arr['settings']['style']['max-width']='100%';
            $arr['newWidth']=$boxStyleArr['width'];
        } else {
            // scale image width to match box width
            $newImgHeight=$imgPropArr['height']*$boxStyleArr['width']/$imgPropArr['width'];
            $newImgWidth=$imgPropArr['width']*$boxStyleArr['height']/$imgPropArr['height'];
            if ($newImgHeight>$boxStyleArr['height']){
                // height > box
                $arr['newWidth']=$newImgWidth;
                $arr['newHeight']=$boxStyleArr['height'];
            } else {
                // height > box
                $arr['newWidth']=$boxStyleArr['width'];
                $arr['newHeight']=$newImgHeight;
            }
        }
        return $arr;
    }
    
    private function resetScaleImage(array $arr):array
    {
        if (isset($arr['width'])){unset($arr['width']);}
        if (isset($arr['height'])){unset($arr['height']);}
        if (isset($arr['newWidth'])){unset($arr['newWidth']);}
        if (isset($arr['newHeight'])){unset($arr['newHeight']);}
        if (isset($arr['newWidth'])){unset($arr['newWidth']);}
        if (isset($arr['newHeight'])){unset($arr['newHeight']);}
        if (isset($arr['minDim'])){unset($arr['minDim']);}
        if (isset($arr['maxDim'])){unset($arr['maxDim']);}
        if (isset($arr['settings']['style']['max-width'])){unset($arr['settings']['style']['max-width']);}
        if (isset($arr['settings']['style']['max-height'])){unset($arr['settings']['style']['max-height']);}
        return $arr;
    }
    
    private function getImgPropArr(string $imgFile):array
    {
        $exif=$this->addExif2entry(array(),$imgFile);
        $exif=current($exif);
        $imgPropArr=getimagesize($imgFile);
        if (isset($exif['Orientation'])){
            if (intval($exif['Orientation'])>4){
                $imgPropArr['width']=$imgPropArr[1];
                $imgPropArr['height']=$imgPropArr[0];
            } else {
                $imgPropArr['width']=$imgPropArr[0];
                $imgPropArr['height']=$imgPropArr[1];
            }
        } else if (empty($imgPropArr)){
            $imgPropArr=array('width'=>1,'height'=>1);
        } else {
            $imgPropArr['width']=$imgPropArr[0];
            $imgPropArr['height']=$imgPropArr[1];
        }
        return $imgPropArr;
    }
    
    public function addExif2entry(array $entry,string $file):array
    {
        $entry['exif']=array();   
        if (!is_file($file)){
            // no attched file
        } else if (!function_exists('exif_read_data')){
            $this->oc['logger']->log('warning','Exif Function "exif_read_data" missing',array());   
        } else {
            $exif=@exif_read_data($file,'IFD0');
            $entry['exif']=(empty($exif))?array():$exif;
        }
        return $entry;
    }
    
    private function addTmpFile(array $arr):array
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
}
?>