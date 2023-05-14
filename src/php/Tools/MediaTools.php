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
    
	public function __construct($oc){
		$this->oc=$oc;
	}

	public function init($oc){
		$this->oc=$oc;
	}

	public function getPreview($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		if (isset($arr['maxDim'])){
			$arr['style']['max-width']=$arr['maxDim'];
			$arr['style']['max-height']=$arr['maxDim'];
		}
		$isSmallPreview=(!empty($arr['style']['max-width']) || !empty($arr['style']['width']));
		$file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr['selector']);
		if (is_file($file)){
			if (!isset($arr['selector']['Params']['File']['MIME-Type'])){
				// attached file has unknown file type
				$arr['html']='🗒';
			} else if (strpos($arr['selector']['Params']['File']['MIME-Type'],'audio')===0){
				$arr=$this->getAudio($arr);
			} else if (strpos($arr['selector']['Params']['File']['MIME-Type'],'video')===0){
				$arr=$this->getVideo($arr);
			} else if (strpos($arr['selector']['Params']['File']['MIME-Type'],'image')===0){
				$imageHtml=$this->getImage($arr);
				$id=md5('wrapper'.$arr['selector']['Source'].$arr['selector']['EntryId']);
				$imageArr=array('tag'=>'div','element-content'=>$imageHtml,'keep-element-content'=>TRUE,'class'=>'preview','id'=>$id,'source'=>$arr['selector']['Source'],'entry-id'=>$arr['selector']['EntryId']);
				$imageArr['style']=array('cursor'=>'pointer','padding'=>'3px');
				$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($imageArr);
			} else if (strpos($arr['selector']['Params']['File']['MIME-Type'],'application/json')===0){
				if ($isSmallPreview){
					$arr['html']='&plusb;';
				} else {
					$json=$this->oc['SourcePot\Datapool\Foundation\Filespace']->file_get_contents_utf8($file);
					$json=json_decode($json,TRUE,512,JSON_INVALID_UTF8_IGNORE);
					$matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($json);
					$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'caption'=>$arr['selector']['Name'],'keep-element-content'=>TRUE));
				}
			} else if (strpos($arr['selector']['Params']['File']['MIME-Type'],'application/pdf')===0){
				$arr=$this->getPdf($arr);
			} else if (strpos($arr['selector']['Params']['File']['MIME-Type'],'text/html')===0){
				$arr=$this->getHtml($arr);
			} else if ($this->oc['SourcePot\Datapool\Tools\CSVtools']->isCSV($arr['selector'])){
				$arr['html']='&plusb;';
			} else if (strpos($arr['selector']['Params']['File']['MIME-Type'],'text/')===0){
				$text=$this->oc['SourcePot\Datapool\Foundation\Filespace']->file_get_contents_utf8($file);
				$arr=$this->addPreviewTextStyle($arr);
				$arr['tag']='p';
				$arr['element-content']=substr($text,0,200);
				$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
			} else {
				// attached file with undefined mime-type
				$arr['html'].='&#9782;';
			}
		} else {
			// No file attached
		}
		if (empty($arr['returnHtmlOnly'])){return $arr;} else {return $arr['html'];}
	}

	private function addPreviewTextStyle($arr){
		$arr['style']['float']='left';
		$arr['style']['clear']='both';
		$arr['style']['font-size']='0.8em';
		$arr['style']['font-style']='italic;';
		$arr['style']['max-width']=100;
		return $arr;
	}	
	
	public function getIcon($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		if (!isset($arr['newHeight'])){$arr['newHeight']=40;}
		if (!isset($arr['newWidth'])){$arr['newWidth']=40;}
		if (!isset($arr['selector']['Params']['File']['MIME-Type'])){$arr['selector']['Params']['File']['MIME-Type']='text';}
		if (strpos($arr['selector']['Params']['File']['MIME-Type'],'image')===0){
			$arr['returnImgFileOnly']=TRUE;
			$iconSrc=$this->getImage($arr);
		} else {
			if (empty($arr['selector']['Name'])){$text='?';} else {$text=$arr['selector']['Name'];}
			$iconArr=array('tag'=>'p','element-content'=>$text,'style'=>array());
			$iconHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($iconArr);
		}
		$imageArr=array('tag'=>'div','element-content'=>'<br/>','keep-element-content'=>TRUE,'class'=>'icon','style'=>array('width'=>$arr['newWidth'],'height'=>$arr['newHeight']));
		if (isset($iconHtml)){$imageArr['element-content']=$iconHtml;}
		if (isset($iconSrc)){
			$imageArr['style']['background-image']='url('.$iconSrc.')';
		}
		$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($imageArr);
		if (empty($arr['returnHtmlOnly'])){return $arr;} else {return $arr['html'];}
	}
	
	public function presentEntry($arr){
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
		if (empty($arr['returnHtmlOnly'])){return $arr;} else {return $arr['html'];}
	}
	
	private function addElementFromKeySettingValue($arrElements,$key,$value){
		// comments are excluded due to varying keys
		if (strpos($key,'Content|Comments')===0){return $arrElements;}
		if (strpos($arrElements['arr']['selector']['Source'],'settings')===0){return $arrElements;}
		// if setting key does not exist yet add the standard key-value
		if (!isset($arrElements['arr']['setting']['Key tags'][$key])){
			$arrElements['arr']['settingNeedsUpdate']=TRUE;
			
			$presentationIndex=1;
			while(isset($arrElements['elements'][$presentationIndex])){$presentationIndex+=10;}
			$arrElements['arr']['setting']['Key tags'][$key]=array('presentation-index'=>$presentationIndex);
			
			$class=$arrElements['arr']['selector']['Source'];
			if (strpos($key,':::')===0){
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
			if (strpos($key,':::')===0){
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
	
	public function loadImage($arr){
		$image=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr);
		// get source file
		$imageFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($image);
		if (is_file($imageFile)){
			// get target file
			$absFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
			$absFile.=$image['EntryId'].'_org.'.$image['Params']['File']['Extension'];
			//$absFile.=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getEntryId().'.'.$image['Params']['File']['Extension'];
			$relFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($absFile);
			copy($imageFile,$absFile);
			$arr['src']=$relFile;
			$arr['title']='';
			if (!empty($image['Params']['reverseGeoLoc']['display_name'])){$arr['title']=$image['Params']['reverseGeoLoc']['display_name'];}
			if (!empty($image['Params']['DateTime']['GPS'])){
				$pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
				$arr['title'].=' '.$image['Params']['DateTime']['GPS'].' ('.$pageSettings['pageTimeZone'].')';
			}
			if (!empty($image['Params']['GPS']['GPSAltitude'])){$arr['title'].=' | Altitude: '.$image['Params']['GPS']['GPSAltitude'];}
			$arr['title']=htmlentities($arr['title']);
			if (isset($image['Params']['File']['Style class'])){$arr['class']=$image['Params']['File']['Style class'];} else {$arr['class']='';}
			return $arr;
		} else {
			return FALSE;
		}
	}
	
	private function getVideo($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$video=$arr['selector'];
		$videoFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($video);
		if (is_file($videoFile)){
			// copy video to temp folder
			$absFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
			$absFile.=$video['EntryId'].'.'.$video['Params']['File']['Extension'];
			copy($videoFile,$absFile);
			$videoArr=$arr;
			$videoArr['tag']='video';
			$videoArr['type']=$video['Params']['File']['MIME-Type'];
			$videoArr['src']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($absFile);
			$videoArr['element-content']=$arr['selector']['Name'];
			$videoArr['style']['margin']='0.5em';
			$videoArr['controls']=TRUE;
			$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($videoArr);
		}
		return $arr;
	}
	
	private function getAudio($arr,$maxDim=FALSE){
		if (!isset($arr['html'])){$arr['html']='';}
		$audio=$arr['selector'];
		$audioFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($audio);
		if (is_file($audioFile)){
			// copy audio to temp folder
			$absFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
			$absFile.=$audio['EntryId'].'.'.$audio['Params']['File']['Extension'];
			copy($audioFile,$absFile);
			$audioArr=$arr;
			$audioArr['tag']='audio';
			$audioArr['type']=$audio['Params']['File']['MIME-Type'];
			$audioArr['src']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($absFile);
			$audioArr['element-content']=$arr['selector']['Name'];
			$audioArr['style']['margin']='0.5em';
			$audioArr['controls']=TRUE;
			$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($audioArr);
		}
		return $arr;
	}

	private function getPdf($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$style=array('margin'=>'10px 0 0 5px','width'=>'98%','height'=>'500px','border'=>'1px solid #444');
		$sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr['selector']);
		$tmpDir=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
		$pdfFile=$tmpDir.$arr['selector']['Params']['File']['Name'];
		$this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($sourceFile,$pdfFile);
		if (is_file($pdfFile)){
			$pdfArr=$arr;
			$pdfArr['tag']='embed';
			$pdfArr['src']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($pdfFile);
			$pdfArr['type']='application/pdf';
			$pdfArr['style']=(isset($pdfArr['style']))?array_merge($style,$pdfArr['style']):$style;
			$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pdfArr);
		} else {
			$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>'Sorry, file '.$arr['Params']['File']['Name'].' could not be copied into the presentation folder.'));
		}
		$arr['wrapperSettings']=array('style'=>'width:95%;');
		return $arr;
	}	
	
	private function getHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$style=array('margin'=>'10px 0 0 5px','width'=>'98%','height'=>'500px','border'=>'1px solid #444');
		$sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr['selector']);
		$tmpDir=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
		$htmlFile=$tmpDir.$arr['selector']['Params']['File']['Name'];
		$this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($sourceFile,$htmlFile);
		if (is_file($htmlFile)){
			$htmlArr=$arr;
			$htmlArr['tag']='iframe';
			$htmlArr['src']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($htmlFile);
			$htmlArr['type']='application/pdf';
			$htmlArr['element-content']='Html content';
			$htmlArr['style']=(isset($pdfArr['style']))?array_merge($style,$pdfArr['style']):$style;
			$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($htmlArr);
		} else {
			$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>'Sorry, file '.$arr['Params']['File']['Name'].' could not be copied into the presentation folder.'));
		}
		$arr['wrapperSettings']=array('style'=>'width:95%;');
		return $arr;
	}	
	
	private function getImage($arr){
		$html='';
		$sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr['selector']);
		// return svg file without processing 
		if (stripos($arr['selector']['Params']['File']['MIME-Type'],'/svg')!==FALSE){
			return $this->oc['SourcePot\Datapool\Foundation\Filespace']->file_get_contents_utf8($sourceFile);
		}
		//target file saved in the tmp directory
		$arr['targetFile']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
		$arr['targetFile'].=$arr['selector']['EntryId'].'.'.$arr['selector']['Params']['File']['Extension'];
		$arr['src']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($arr['targetFile']);
		//-- resampling will destroy exif data, rotation and flipping required
		$tmpArr=@getimagesize($sourceFile);
		if (empty($tmpArr)){
			// is not an image
			$arr['tag']='p';
			$arr['element-content']=$arr['selector']['Params']['File']['Name'].' seems to be not an image file...';
			return $this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
		} else {
			// is an image
			(empty($arr['selector']['Params']['File']['Style class']))?$arr['styleClass']='rotate0':$arr['styleClass']=$arr['selector']['Params']['File']['Style class'];
			$transformArr=$this->styleClass2Params($arr);
			$absSinRot=abs(sin(deg2rad($transformArr['deg'])));
			$imgArr=array('sourceFile'=>$sourceFile,
						  'targetFile'=>$arr['targetFile'],
						  'fileType'=>$tmpArr['mime'],
						  'absSinRot'=>$absSinRot,
						  'scaler'=>1,
						  'orgWidth'=>$tmpArr[0],
						  'orgHeight'=>$tmpArr[1],
						  'orgRot'=>$transformArr['deg'],
						  'orgFlipped'=>$transformArr['flip'],
						  'newSize'=>(isset($arr['newSize']))?$arr['newSize']:FALSE,
						  'newWidth'=>(isset($arr['newWidth']))?$arr['newWidth']:300,
						  'newHeight'=>(isset($arr['newHeight']))?$arr['newHeight']:300,
						  'maxDim'=>(isset($arr['maxDim']))?$arr['maxDim']:FALSE,
						  'minDim'=>(isset($arr['minDim']))?$arr['minDim']:FALSE,
						  'quality'=>90,
						  );
			//-- create image from file
			$orgImage=FALSE;
			try{
				if (strpos($imgArr['fileType'],'/png')!==FALSE){
					$orgImage=imagecreatefrompng($imgArr['sourceFile']);
				} else if (strpos($imgArr['fileType'],'/gif')!==FALSE){
					$orgImage=imagecreatefromgif($imgArr['sourceFile']);
				} else if (strpos($imgArr['fileType'],'/bmp')!==FALSE){
					$orgImage=imagecreatefromwbmp($imgArr['sourceFile']);
				} else if (strpos($imgArr['fileType'],'/webp')!==FALSE){
					$orgImage=imagecreatefromwebp($imgArr['sourceFile']);
				} else if (strpos($imgArr['fileType'],'/jpg')!==FALSE){
					$orgImage=imagecreatefromjpeg($imgArr['sourceFile']);
				} else if (strpos($imgArr['fileType'],'/jpeg')!==FALSE){
					$orgImage=imagecreatefromjpeg($imgArr['sourceFile']);
				} else {
					$string=$this->oc['SourcePot\Datapool\Foundation\Filespace']->file_get_contents_utf8($imgArr['sourceFile']);
					if ($this->isBase64Encoded($string)){$string=base64_decode($string);}
					$orgImage=imagecreatefromstring($string);	
				}
			} catch(Exception $e) {
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
			//-- rotate and image
			if ($imgArr['orgRot']!==0){$newImage=imagerotate($newImage,-$imgArr['orgRot'],0);}
			if ($imgArr['orgFlipped']!==FALSE){imageflip($newImage,$imgArr['orgFlipped']);}
			//-- save to target
			$imageTagArr=$arr;
			$imageTagArr['tag']='img';
			$imageTagArr['title']=$arr['selector']['Params']['File']['Name'];
			if (empty($arr['encodeBase64'])){
				imagejpeg($newImage,$arr['targetFile'],$imgArr['quality']);
				chmod($arr['targetFile'],0774);
				imagedestroy($orgImage);
				@imagedestroy($newImage);
				if (empty($arr['returnImgFileOnly'])){
					$imageTagArr['src']=$arr['src'];
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
		
	private function styleClass2Params($arr){
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

	private function isBase64Encoded($data){
		if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%',$data)){
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
}
?>