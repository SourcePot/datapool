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
	
	private $def=array(''=>array('accesskey'=>FALSE,'autocapitalize'=>FALSE,'autofocus'=>FALSE,'class'=>'std','contenteditable'=>FALSE,'data-*'=>FALSE,
								 'dir'=>FALSE,'draggable'=>FALSE,'enterkeyhint'=>FALSE,'hidden'=>FALSE,'id'=>FALSE,'inert'=>FALSE,'inputmode'=>FALSE,'is'=>FALSE,
								 'itemid'=>FALSE,'itemprop'=>FALSE,'itemref'=>FALSE,'itemscope'=>FALSE,'itemtype'=>FALSE,'lang'=>FALSE,'nonce'=>FALSE,'part'=>FALSE,
								 'popover'=>FALSE,'role'=>FALSE,'slot'=>FALSE,'spellcheck'=>FALSE,'style'=>FALSE,'tabindex'=>FALSE,'title'=>FALSE,'translate'=>FALSE,
								 'virtualkeyboardpolicy'=>FALSE),
					   // Forms
					   'button'=>array('name'=>TRUE),
					   'datalist'=>array('name'=>TRUE),
					   'fieldset'=>array('name'=>TRUE),
					   'form'=>array('action'=>FALSE,'accept-charset'=>FALSE,'autocomplete'=>FALSE,'enctype'=>'multipart/form-data',''=>FALSE,'method'=>'post','name'=>FALSE,
									 'novalidate'=>FALSE,'rel'=>FALSE,'target'=>FALSE),
					   'input'=>array('type'=>TRUE,'value'=>FALSE,'accept'=>FALSE,'name'=>TRUE,'disabled'=>FALSE,'multiple'=>FALSE,'checked'=>FALSE),
					   'label'=>array('for'=>TRUE),
					   'legend'=>array('name'=>TRUE),
					   'meter'=>array('name'=>TRUE),
					   'optgroup'=>array('name'=>TRUE),
					   'option'=>array('value'=>TRUE,'selected'=>FALSE),
					   'output'=>array('name'=>TRUE),
					   'progress'=>array('name'=>TRUE),
					   'select'=>array('name'=>TRUE),
					   'textarea'=>array('name'=>TRUE),
					   
					   'a'=>array('href'=>FALSE),
					   
					   'main'=>array(),
					   
					   'details'=>array(),
					   'summary'=>array(),
					   'div'=>array(),
					   'li'=>array(),
					   'ol'=>array(),
					   'ul'=>array(),
					   'h1'=>array(),
					   'h2'=>array(),
					   'h3'=>array(),
					   'h4'=>array(),
					   'p'=>array(),
					   'article'=>array(),
					   
					   'audio'=>array('src'=>TRUE,'autoplay'=>FALSE,'controls'=>FALSE,'crossorigin'=>FALSE,'loop'=>FALSE,'muted'=>FALSE,'preload'=>FALSE,'height'=>FALSE,'width'=>FALSE),
					   'canvas'=>array('height'=>FALSE,'width'=>FALSE),
					   'embed'=>array('src'=>TRUE,'height'=>FALSE,'width'=>FALSE),
					   'iframe'=>array('src'=>TRUE,'height'=>FALSE,'width'=>FALSE),
					   'img'=>array('src'=>TRUE,'alt'=>FALSE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE),
					   'link'=>array('src'=>TRUE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE),
					   'object'=>array('src'=>TRUE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE),
					   'picture'=>array('src'=>TRUE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE),
					   'script'=>array('src'=>TRUE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE),
					   'svg'=>array('src'=>TRUE,'crossorigin'=>FALSE,'height'=>FALSE,'width'=>FALSE),
					   'video'=>array('src'=>TRUE,'autoplay'=>FALSE,'controls'=>FALSE,'crossorigin'=>FALSE,'loop'=>FALSE,'muted'=>FALSE,'preload'=>FALSE,'height'=>FALSE,'width'=>FALSE),
					   'source'=>array('src'=>TRUE,'type'=>FALSE,'srcset'=>FALSE,'sizes'=>FALSE,'media'=>FALSE,'height'=>FALSE,'width'=>FALSE),
					  
					   );
	
	private $specialAttr=array('function'=>FALSE,'method'=>FALSE,'target'=>FALSE,'trigger-id'=>FALSE,'container-id'=>FALSE,'excontainer'=>FALSE,'container'=>FALSE,'cell'=>FALSE,
							   'row'=>FALSE,'source'=>FALSE,'entry-id'=>FALSE,'index'=>FALSE,'js-status'=>FALSE,
							   );
							   
	private $copyKeys2Session=array('element-content'=>FALSE,'value'=>FALSE,'tag'=>TRUE,'key'=>FALSE,'id'=>FALSE,'name'=>FALSE,
									'callingClass'=>FALSE,'callingFunction'=>FALSE,'filter'=>FILTER_DEFAULT,'Read'=>FALSE,'Write'=>FALSE,
									);
	
	private $copyKeys2selector=array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE,'Type'=>FALSE,'Date'=>FALSE,'Expires'=>FALSE,
									 'Read'=>FALSE,'Write'=>FALSE,'Privileges'=>FALSE,'LoginId'=>FALSE
									);

	public function __construct($oc){
		$this->oc=$oc;
	}
	
	public function init($oc){
		$this->oc=$oc;
	}

	public function testing($arr){
		$this->formProcessing(__CLASS__,__FUNCTION__);
		$html=$this->element(array('tag'=>'input','type'=>'file','key'=>array('Content','Files'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('margin'=>'50px 10px'),'multiple'=>TRUE));
		$html.=$this->element(array('tag'=>'input','type'=>'text','key'=>array('Content','Text A'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('margin'=>'50px 10px')));
		$html.=$this->element(array('tag'=>'input','type'=>'text','key'=>array('Content','Text B'),'value'=>'Hallo','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('margin'=>'50px 10px')));
		$element=array('tag'=>'textarea','element-content'=>'Textarea...','key'=>array('Content','Long text'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('margin'=>'50px 10px'));
		$html.=$this->element($element);
		
		$options=array('\C\a\r\s\t\e\n'=>'Carsten','\K\l\a\u\s'=>'Klaus');
		$html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$options,'selected'=>'\K\l\a\u\s','key'=>array('Content','Name'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		
		
		
		$element=array('tag'=>'button','element-content'=>'S<i>av</i>e','keep-element-content'=>TRUE,'key'=>array('Content','Button','1234'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('margin'=>'50px 10px'));
		$element['hasCover']=TRUE;
		$element['Source']='user';
		$element['EntryId']='84632jhgj4h234j2';
		$html.=$this->element($element);
		$html=$this->element(array('tag'=>'form','element-content'=>$html,'keep-element-content'=>TRUE));
		
		$arr['toReplace']['{{body}}']=$html;
		
		return $arr;
	}
	
	public function element($arr):string{
		if (empty($arr['tag'])){
			//$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($arr);
			throw new \ErrorException('Function '.__FUNCTION__.': Missing or empty arr[tag]-argument.',0,E_ERROR,__FILE__,__LINE__);
		} else if (isset($this->def[$arr['tag']])){
			$def=array_merge($this->def[''],$this->def[$arr['tag']],$this->specialAttr);
			$nameRequired=(!empty($def['name']));
			$elementArr=array('tag'=>$arr['tag'],'attr'=>array(),'sessionArr'=>array('type'=>''));
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
			if (isset($arr['key'])){
				$arr['id']=(empty($arr['id']))?md5($arr['tag'].'|'.implode('|',$arr['key'])):$arr['id'];
				$arr['name']=(empty($arr['name']))?$arr['id']:$arr['name'];
				$elementArr['attr']['id']=$this->attr2string($arr,'id',$arr['id']);
				$elementArr['attr']['name']=$this->attr2string($arr,'name',$arr['name']);
			} else {
				throw new \ErrorException('Function '.__FUNCTION__.': tag "'.$elementArr['tag'].'" required attribute "key" missing.',0,E_ERROR,__FILE__,__LINE__);
			}
			$elementArr['sessionArr']=$this->def2arr($arr,$this->copyKeys2Session,$elementArr['sessionArr']);
			$arr['selector']=(isset($arr['selector']))?$arr['selector']:array();
			$elementArr['sessionArr']['selector']=$this->def2arr($arr['selector'],$this->copyKeys2selector);
			$elementArr=$this->addElement2session($arr,$elementArr);
		}
		$html=$this->elementArr2html($arr,$elementArr);
		if (!empty($arr['hasCover'])){$html=$this->addCover($arr,$html);}
		return $html;
	}
	
	private function attr2string($arr,$attrName,$attrValue){
		if (strcmp($attrName,'name')===0 && !empty($arr['multiple'])){$attrValue.='[]';}
		if (is_array($attrValue)){
			$newAttrValue='';
			foreach($attrValue as $key=>$value){
				if (strpos($key,'height')!==FALSE || strpos($key,'width')!==FALSE || strpos($key,'size')!==FALSE || strpos($key,'top')!==FALSE || strpos($key,'left')!==FALSE || strpos($key,'bottom')!==FALSE || strpos($key,'right')!==FALSE){
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

	private function def2arr($arrIn,$def,$arrOut=array()){
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

	private function addElement2session($arr,$elementArr){
		if (isset($elementArr['sessionArr']['name'])){
			if (isset($arr['callingClass']) && isset($arr['callingFunction'])){
				$_SESSION[$arr['callingClass']][$arr['callingFunction']][$elementArr['sessionArr']['name']]=$elementArr['sessionArr'];
			} else {
				throw new \ErrorException('Function '.__FUNCTION__.': tag "'.$elementArr['tag'].'" required attributes "callingClass" or "callingFunction" missing.',0,E_ERROR,__FILE__,__LINE__);	
			}
		}
		return $elementArr;
	}
	
	private function elementArr2html($arr,$elementArr){
		if (isset($arr['element-content'])){
			$arr['element-content']=strval($arr['element-content']);
			if (empty($arr['keep-element-content'])){$arr['element-content']=htmlentities($arr['element-content']);}
			$html='<'.$elementArr['tag'].' '.implode(' ',$elementArr['attr']).'>'.$arr['element-content'].'</'.$elementArr['tag'].'>';
		} else {
			$html='<'.$elementArr['tag'].' '.implode(' ',$elementArr['attr']).'/>';
		}
		return $html;
	}

	private function addCover($arr,$html){
		$arr['title']=(isset($arr['title']))?$arr['title']:'Safety cover..';
		$arr['style']=(isset($arr['style']))?$arr['style']:array();
		$coverArrP=array('tag'=>'p','title'=>'Safety cover..','class'=>'cover','id'=>'cover-'.hrtime(TRUE),'element-content'=>'Sure?');
		$html.=$this->element($coverArrP);
		$coverArrDiv=array('tag'=>'div','title'=>$arr['title'],'class'=>'cover-wrapper','id'=>'cover-wrapper','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>$arr['style']);
		$html=$this->element($coverArrDiv);
		
		/*
		$title=;
		$html.='<p id="cover-'.hrtime(TRUE).'" class="cover" title="'.$title.'">Sure?</p>';
		if (empty($arr['style'])){
			$html='<div class="cover-wrapper" title="'.$title.'">'.$html.'</div>';
		} else {
			$html='<div class="cover-wrapper" style="'.$arr['style'].'" title="'.$title.'">'.$html.'</div>';
		}
		*/
		return $html;
	}

	public function formProcessing($callingClass,$callingFunction):array{
		// This method returns the result from processing of $_POST and $_FILES.
		// It returns an array with the old values, the new values, files und commmands.
		$result=array('cmd'=>array(),'val'=>array(),'changed'=>array(),'files'=>array(),'hasValidFiles'=>FALSE,'selector'=>array(),'callingClass'=>$callingClass,'callingFunction'=>$callingFunction);
		$S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		if (isset($_SESSION[$callingClass][$callingFunction])){
			foreach($_SESSION[$callingClass][$callingFunction] as $name=>$arr){
				if (isset($_POST[$name]) && isset($arr['tag'])){
					// process $_POST
					$keys=$arr['key'];
					if (isset($arr['value'])){
						$oldValue=strval($arr['value']);
					} else if (isset($arr['element-content'])){
						$oldValue=strval($arr['element-content']);
					} else {
						$oldValue='';
					}
					if (strcmp(strval($arr['type']),'submit')===0 || strcmp(strval($arr['tag']),'button')===0){
						$newValue=$oldValue;
						array_unshift($keys,'cmd');
						$result['selector']=(isset($arr['selector']))?$arr['selector']:$result['selector'];
					} else {
						$newValue=filter_input(INPUT_POST,$name,$arr['filter']);
						array_unshift($keys,'val');
					}
					if (strcmp($newValue,$oldValue)!==0){
						$changedKeys=$arr['key'];
						array_unshift($changedKeys,'changed');
						$changedValueArr=$this->arrKeys2arr($changedKeys,$oldValue);
						$result=array_replace_recursive($result,$changedValueArr);
					}
					$newValueArr=$this->arrKeys2arr($keys,$newValue);
					$result=array_replace_recursive($result,$newValueArr);
				}
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
						}
					}
				}
			}
		}
		//$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($result);
		return $result;
	}

	private function arrKeys2arr($keys,$value){
		$arr=$value;
		while(count($keys)>0){
			$subKey=array_pop($keys);
			$arr=array($subKey=>$arr);
		}
		return $arr;
	}

	private function fileErrorCode2str($code){
		$codeArr=array(0=>'There is no error, the file uploaded with success',
					   1=>'The uploaded file exceeds the upload_max_filesize directive in php.ini',
					   2=>'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
					   3=>'The uploaded file was only partially uploaded',
					   4=>'No file was uploaded',
					   6=>'Missing a temporary folder',
					   7=>'Failed to write file to disk.',
					   8=>'A PHP extension stopped the file upload.',
					   );
		$code=intval($code);
		if (isset($codeArr[$code])){return $codeArr[$code];} else {return '';}
	}
	
	private function escapeAttrName($attrName):string{
		$attrName=preg_replace('/[^a-zA-Z\-]/','',$attrName);
		return $attrName;
	}
	
	private function escapeAttrValue($attrValue):string{
		$pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
		$attrValue=htmlspecialchars(strval($attrValue),ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML401,$pageSettings['charset'],TRUE);
		return $attrValue;
	}

}
?>