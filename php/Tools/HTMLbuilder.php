<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Tools;

class HTMLbuilder{
	
	private $arr;
	
	private $elementAttrWhitelist=array('tag'=>TRUE,'input'=>TRUE,'type'=>TRUE,'class'=>TRUE,'style'=>TRUE,'id'=>TRUE,'name'=>TRUE,'title'=>TRUE,'function'=>TRUE,
										'method'=>TRUE,'enctype'=>TRUE,'xmlns'=>TRUE,'lang'=>TRUE,'href'=>TRUE,'src'=>TRUE,'value'=>TRUE,'width'=>TRUE,'height'=>TRUE,
										'rows'=>TRUE,'cols'=>TRUE,'target'=>TRUE,
										'min'=>TRUE,'max'=>TRUE,'for'=>TRUE,'multiple'=>TRUE,'disabled'=>TRUE,'selected'=>TRUE,'checked'=>TRUE,'controls'=>TRUE,'trigger-id'=>TRUE,
										'container-id'=>TRUE,'excontainer'=>TRUE,'container'=>TRUE,'cell'=>TRUE,'row'=>TRUE,'source'=>TRUE,'entry-id'=>TRUE,'source'=>TRUE,'index'=>TRUE,
										'js-status'=>TRUE,'default-min-width'=>TRUE,'default-min-height'=>TRUE,'default-max-width'=>TRUE,'default-max-height'=>TRUE,
										);
    private $needsNameAttr=array('input'=>TRUE,'select'=>TRUE,'textarea'=>TRUE,'button'=>TRUE,'fieldset'=>TRUE,'legend'=>TRUE,'output'=>TRUE,'optgroup'=>TRUE);
	
	public function __construct($arr){
		$this->arr=$arr;
	}
	
	public function init($arr){
		$this->arr=$arr;
		return $this->arr;
	}
	
	public function element($arr,$returnArr=FALSE){
		// This function creates an HTML-element based on the arr argument.
		// Special arr-keys are 
		// 'callingClass' ... is the class-name from which the element was called
		// 'callingFunction' ... is the function-name from which the element was called
		// 'key' ... is the id for a HTML-element carring information transmitted through a HTML-form
		// 'keep-element-content' ... if TRUE the element-content will not be HTML encoded
		// 'element-content' ... contains the HTML-element content, e.g. ABC in <p><ABC/p>
		// Do not set the element-content-key if the element does not use a closing tag, e.g. <br />  
		// 'tag' ... is the HTML-element tag value, e.g. body in <body>...</body>
		// all other keys are attribute-keys of the element.
		if (!isset($arr['html'])){$arr['html']='';}
		if (empty($arr['tag'])){
			$trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,4);
			$arr['html'].='<br/>Problem: Method "'.__FUNCTION__.'" was called with empty arr["tag"] by...';
			$arr['html'].='<br/>1. '.$trace[0]['class'].'::'.$trace[0]['function'].'() '.__LINE__;
			$arr['html'].='<br/>2. '.$trace[1]['class'].'::'.$trace[1]['function'].'() '.$trace[0]['line'];
			$arr['html'].='<br/>3. '.$trace[2]['class'].'::'.$trace[2]['function'].'() '.$trace[1]['line'].'<br/>';
		} else {
			if (!isset($arr['callingClass'])){$arr['callingClass']=__CLASS__;}
			if (!isset($arr['callingFunction'])){$arr['callingFunction']=__FUNCTION__;}
			if (isset($arr['style'])){
				if (is_array($arr['style'])){$arr['style']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2style($arr['style']);}
				if (!empty($arr['hasCover'])){
					$arr['coverStyle']=$arr['style'];
					unset($arr['style']);
				}
			}
			$arr=$this->elementTranslations($arr);
			// get and unfiy key
			if (isset($arr['key'])){
				if (is_array($arr['key'])){
					$arr['key']=implode($this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator(),$arr['key']);
				}
			}
			// add name-key if required
			if (empty($arr['name'])){
				if (isset($this->needsNameAttr[$arr['tag']])){
					// get name attribute if required by tag
					if (empty($arr['key'])){
						throw new \ErrorException('Function '.__FUNCTION__.': Missing or empty key-key in argument arr for an HTML-tag which needs the name-attr',0,E_ERROR,__FILE__,__LINE__);
					}
					if (!empty($arr['id'])){
						$arr['name']=$arr['id'];
					} else {
						$arr['name']=$this->arr2id($arr);
						//$arr['name']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getHash($callingClass.$callingFunction.$arr['key']);
					}
				}
			}
			// add to session form data if name is provided
			if (!empty($arr['name'])){
				$value=FALSE;
				if (isset($arr['value'])){$value=$arr['value'];} else if (isset($arr['element-content'])){$value=$arr['element-content'];}
				//$sessionArr=array('key'=>$key,'value'=>$value,'tag'=>$arr['tag']);
				$sessionArr=$arr;
				if (!isset($sessionArr['type'])){$sessionArr['type']='';}
				$_SESSION[$arr['callingClass']][$arr['callingFunction']][$arr['name']]=$sessionArr;
				if (empty($arr['id'])){$arr['id']=$arr['name'];}
				if (!empty($arr['multiple'])){$arr['name']=$arr['name'].'[]';}
			}
			// create HTML-element structure
			$toReplace=array();
			if (isset($arr['element-content'])){
				if (empty($arr['keep-element-content'])){
					$arr['element-content']=htmlspecialchars($arr['element-content'],ENT_QUOTES,'UTF-8');
				}
				if (strcmp($arr['tag'],'p')===0){$arr['element-content']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->wrapUTF8($arr['element-content']);}
				$toReplace['{{element-content}}']=$arr['element-content'];
				$arr['html']='<{{tag}} {{attr}}>{{element-content}}</{{tag}}>';
			} else {
				$arr['html']='<{{tag}} {{attr}}/>';	
			}
			// create HTML-element
			$state='';
			$toReplace['{{attr}}']='';
			foreach($arr as $attrkey=>$attrValue){
				if (!isset($this->elementAttrWhitelist[$attrkey])){continue;}
				if (is_array($attrValue)){
					continue;
				} else if (is_bool($attrValue)){
					if ($attrValue===TRUE){$state=$attrkey;}
				} else {
					$attrValue=strval($attrValue);
					$attrValue=htmlEntities($attrValue,ENT_QUOTES);
					if (strcmp($attrkey,'tag')===0){
						$toReplace['{{tag}}']=$attrValue;
					} else {
						$toReplace['{{attr}}'].=$attrkey.'="'.$attrValue.'" ';
					}
				}
			}
			$toReplace['{{attr}}'].=$state;
			// replace HTML-element struvture elements and return element
			foreach($toReplace as $needle=>$value){$arr['html']=str_replace($needle,strval($value),$arr['html']);}
		}
		if (!empty($arr['hasCover'])){$arr=$this->addCover($arr);}
		if ($returnArr){
			return $arr;
		} else {
			return $arr['html'];
		}
	}
	
	private function addCover($arr){
		$title='Safety cover..';
		$arr['html'].='<p id="cover-'.hrtime(TRUE).'" class="cover" title="'.$title.'">Sure?</p>';
		if (empty($arr['coverStyle'])){
			$arr['html']='<div class="cover-wrapper" title="'.$title.'">'.$arr['html'].'</div>';
		} else {
			$arr['html']='<div class="cover-wrapper" style="'.$arr['coverStyle'].'" title="'.$title.'">'.$arr['html'].'</div>';
		}
		return $arr;
	}

	public function template2string($template='Hello [p:{{key}}]...',$arr=array('key'=>'world'),$element=array()){
		$flatArr=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($arr);
		foreach($flatArr as $flatArrKey=>$flatArrValue){
			$template=str_replace('{{'.$flatArrKey.'}}',(string)$flatArrValue,$template);
		}
		$template=preg_replace('/{{[^{}]+}}/','',$template);
		preg_match_all('/(\[\w+:)([^\]]+)(\])/',$template,$matches);
		if (isset($matches[0][0])){
			foreach($matches[0] as $matchIndex=>$match){
				$element['tag']=trim($matches[1][$matchIndex],'[:');
				$element['element-content']=$matches[2][$matchIndex];
				$replacement=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
				$template=str_replace($match,$replacement,$template);
			}
		}
		return $template;
	}
	
	private function arr2id($arr){
		$toHash=array($arr['callingClass'],$arr['callingFunction'],$arr['key']);
		return $this->arr['SourcePot\Datapool\Tools\MiscTools']->getHash($toHash);
	}
	
	private function elementTranslations($arr){
		if (!empty($arr['keep-element-content'])){return $arr;}
		if (empty($arr['dontTranslateContent']) && !empty($arr['element-content'])){
			$arr['element-content']=$this->arr['SourcePot\Datapool\Foundation\Dictionary']->lng($arr['element-content']);
		}
		if (empty($arr['dontTranslateTitle']) && !empty($arr['title'])){
			$arr['title']=$this->arr['SourcePot\Datapool\Foundation\Dictionary']->lng($arr['title']);
		}
		if (empty($arr['dontTranslateValue']) && !empty($arr['value']) && !empty($arr['type'])){
			if (strcmp($arr['tag'],'input')===0 && strcmp($arr['type'],'submit')===0){$arr['value']=$this->arr['SourcePot\Datapool\Foundation\Dictionary']->lng($arr['value']);}
		}
		return $arr;
	}
	
	public function table($arr,$returnArr=FALSE){
		// This function provides an HTML-table created based on the arr argument.
		// The following keys are specific to this funtion:
		// 'matrix' ... is the table matrix with the format: arr[matrix][row-index]=array(column-index => column-value, ..., last column-index => last column-value)
		// 'caption' ... is the table caption
		// 'skipEmptyRows' ... if TRUE rows with empty cells only will be skipped
		$hasNoRows=TRUE;
		$html='';
		$toReplace=array();
		if (!empty($arr['matrix'])){
			if (empty($arr['style'])){
				$html.='<table {{class}}>'.PHP_EOL;
			} else {
				$arr['style']=is_array($arr['style'])?$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2style($arr['style']):$arr['style'];
				$html.='<table {{class}} style="'.$arr['style'].'">'.PHP_EOL;	
			}
			if (!empty($arr['caption'])){$html.='<caption {{class}}>'.PHP_EOL.$this->arr['SourcePot\Datapool\Foundation\Dictionary']->lng($arr['caption']).'</caption>'.PHP_EOL;}
			if (empty($arr['hideHeader'])){$html.='<thead {{class}}>'.PHP_EOL.'{{thead}}</thead>'.PHP_EOL;}
			$html.='<tbody {{class}}>'.PHP_EOL.'{{tbody}}</tbody>'.PHP_EOL;
			$html.='</table>'.PHP_EOL;
			$rowIndex=1;
			$toReplace['{{tbody}}']='';
			foreach($arr['matrix'] as $row=>$rowArr){
				$setRowStyle='';
				if (isset($rowArr['setRowStyle'])){$setRowStyle=$rowArr['setRowStyle'];unset($rowArr['setRowStyle']);}
				$toReplace['{{thead}}']='<tr {{class}}>';
				$newRow='<tr {{class}} row="'.$rowIndex.'" style="'.$setRowStyle.'">';
				if (empty($arr['hideKeys'])){
					$toReplace['{{thead}}'].='<th {{class}}>'.$this->arr['SourcePot\Datapool\Foundation\Dictionary']->lng('Key').'</th>';
					$newRow.='<td {{class}}>'.$this->arr['SourcePot\Datapool\Foundation\Dictionary']->lng($row).'</td>';						
				}
				$allCellsEmpty=TRUE;
				$cellIndex=1;
				foreach($rowArr as $column=>$cell){
					if (is_array($column)){$column=$this->element($column);} else {$column=htmlEntities(strval($column),ENT_QUOTES);}
					if (is_array($cell)){
						$cell=$this->element($cell);
					} else {
						if (empty($arr['keep-element-content'])){$cell=htmlspecialchars(strval($cell),ENT_QUOTES,'UTF-8');}
					}
					if (!empty($cell)){$allCellsEmpty=FALSE;}
					$toReplace['{{thead}}'].='<th {{class}}>'.$column.'</th>';
					$newRow.='<td {{class}} cell="'.$cellIndex.'-'.$rowIndex.'">'.$cell.'</td>';
					$cellIndex++;					
				}
				$newRow.='</tr>'.PHP_EOL;
				$toReplace['{{thead}}'].='</tr>'.PHP_EOL;
				if (!$allCellsEmpty || empty($arr['skipEmptyRows'])){
					$hasNoRows=FALSE;
					$toReplace['{{tbody}}'].=$newRow;
				}
				$rowIndex++;
			}	
		}
		if ($hasNoRows){$html='';}
		if (empty($arr['class'])){$toReplace['{{class}}']='';} else {$toReplace['{{class}}']=$arr['class'];}
		foreach($toReplace as $needle=>$value){$html=str_replace($needle,$value,$html);}
		if ($returnArr){
			$arr['html']=$html;
			return $arr;
		} else {
			return $html;
		}
	}
	
	public function select($arr,$returnArr=FALSE){
		// This function returns the HTML-select-element with options based on $arr.
		// Required keys are 'options', 'key', 'callingClass' and 'callingFunction'.
		// Key 'label', 'selected', 'triggerId' are optional.
		// If 'hasSelectBtn' is set, a button will be added which will be clicked if an item is selected.
		if (!isset($arr['key'])){
			throw new \ErrorException('Function '.__FUNCTION__.': Missing key-key in argument arr',0,E_ERROR,__FILE__,__LINE__);
		}
		if (is_array($arr['key'])){$key=implode($this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator(),$arr['key']);} else {$key=$arr['key'];}
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
				$html.=$this->element($inputArr);
				unset($arr['label']);
			}
			// create select
			$selected='';
			if (isset($arr['selected'])){$selected=$arr['selected'];unset($arr['selected']);}
			if (isset($arr['value'])){$selected=$arr['value'];unset($arr['value']);}
			$toReplace=array();
			$selectArr=$arr;
			if (!empty($arr['hasSelectBtn'])){$selectArr['trigger-id']=$triggerId;}
			$selectArr['tag']='select';
			$selectArr['id']=$inputId;
			$selectArr['value']=$selected;
			$selectArr['element-content']='{{options}}';
			$selectArr['keep-element-content']=TRUE;
			$html.=$this->element($selectArr);
			// create options
			if (isset($arr['style'])){unset($arr['style']);}
			$toReplace['{{options}}']='';
			foreach($arr['options'] as $name=>$label){
				$optionArr=$arr;
				$optionArr['tag']='option';
				if (strcmp(strval($name),strval($selected))===0){$optionArr['selected']=TRUE;}
				$optionArr['value']=$name;
				$optionArr['element-content']=$label;
				$optionArr['keep-element-content']=TRUE;
				$toReplace['{{options}}'].=$this->element($optionArr);				
			}
			foreach($toReplace as $needle=>$value){$html=str_replace($needle,$value,$html);}
			if (isset($selectArr['trigger-id'])){
				$html.=$this->element(array('tag'=>'button','element-content'=>'*','key'=>array('select'),'value'=>$key,'id'=>$triggerId,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
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
		$html='';
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->getDbInfo() as $table=>$tableDef){$arr['options'][$table]=ucfirst($table);}
		$html.=$this->select($arr);
		return $html;
	}
	
	public function keySelect($arr){
		$html='';
		if (empty($arr['Source'])){return $html;}
		$fileContentKeys=array();
		$keys=$this->arr['SourcePot\Datapool\Foundation\Database']->entryTemplate($arr);
		if (empty($arr['standardColumsOnly'])){
			foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($arr,TRUE) as $tmpEntry){
				if ($tmpEntry['isSkipRow']){continue;}
				if (isset($tmpEntry['Params']['File']['MIME-Type'])){
					if (strpos($tmpEntry['Params']['File']['MIME-Type'],'text/')===0){
						$fileContentKeys=$this->arr['SourcePot\Datapool\Tools\CSVtools']->csvIterator($tmpEntry)->current();
					}
				}
				$keys=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($tmpEntry);
				break;
			}
		}
		$arr['keep-element-content']=TRUE;
		if (!empty($arr['addSourceValueColumn'])){
			$arr['options']=array('useValue'=>'&xrArr;');
		} else {
			$arr['options']=array();
		}
		foreach($fileContentKeys as $key=>$value){
			$key='File content'.$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator().$key;
			$arr['options'][$key]=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($key);
		}
		foreach($keys as $key=>$value){
			$arr['options'][$key]=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($key);
		}
		$html.=$this->select($arr);
		return $html;
	}
	
	public function canvasElementSelect($arr){
		if (empty($arr['canvasCallingClass'])){
			throw new \ErrorException('Function '.__FUNCTION__.': Argument arr[canvasCallingClass] is missing but required.',0,E_ERROR,__FILE__,__LINE__);
		}
		$canvasElements=$this->arr['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements($arr['canvasCallingClass']);
		$arr['options']=array();
		foreach($canvasElements as $key=>$canvasEntry){
			if (empty($canvasEntry['Content']['Selector']['Source'])){continue;}
			$arr['options'][$canvasEntry['EntryId']]=$canvasEntry['Content']['Style']['text'];
		}
		$html=$this->select($arr);
		return $html;
	}
	
	public function preview($arr){
		return $this->arr['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
	}
	
	public function btn($arr){
		// This function returns standard buttons based on argument arr.
		// If arr is empty, buttons will be processed
		$btnDefs=array('test'=>array('title'=>'Test run','hasCover'=>FALSE,'element-content'=>'Test','requiredRight'=>FALSE,'requiresFile'=>FALSE,'excontainer'=>FALSE),
					   'run'=>array('title'=>'Run','hasCover'=>FALSE,'element-content'=>'Run','requiredRight'=>FALSE,'requiresFile'=>FALSE,'excontainer'=>TRUE),
					   'add'=>array('title'=>'Add this entry','hasCover'=>FALSE,'element-content'=>'+','requiredRight'=>FALSE,'requiresFile'=>FALSE),
					   'save'=>array('title'=>'Save this entry','hasCover'=>FALSE,'element-content'=>'&veeeq;','requiredRight'=>'Write','requiresFile'=>FALSE),
					   'download'=>array('title'=>'Download attached file','hasCover'=>FALSE,'element-content'=>'&#8892;','requiredRight'=>'Read','requiresFile'=>TRUE,'excontainer'=>TRUE),
					   'select'=>array('title'=>'Select entry','hasCover'=>FALSE,'element-content'=>'&#10022;','requiredRight'=>'Read','excontainer'=>TRUE),
					   'delete'=>array('title'=>'Delete entry','hasCover'=>TRUE,'element-content'=>'&xcup;','requiredRight'=>'Write','style'=>array('float'=>'left')),
					   'remove'=>array('title'=>'Remove file','hasCover'=>TRUE,'element-content'=>'&xcup;','requiredRight'=>'Write','requiresFile'=>TRUE,'style'=>array('float'=>'left')),
					   'delete all'=>array('title'=>'Delete all selected entries','hasCover'=>TRUE,'element-content'=>'Delete all selected','requiredRight'=>FALSE,'style'=>array('float'=>'left')),
					   'delete all entries'=>array('title'=>'Delete all selected entries excluding attched files','hasCover'=>TRUE,'element-content'=>'Delete all selected','requiredRight'=>FALSE,'style'=>'float:left;'),
					   'moveUp'=>array('title'=>'Moves the entry up','hasCover'=>FALSE,'element-content'=>'&#8681;','requiredRight'=>'Write'),
					   'moveDown'=>array('title'=>'Moves the entry down','hasCover'=>FALSE,'element-content'=>'&#8679;','requiredRight'=>'Write'),
					   );
		$html='';
		$stdKeys=array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE,'Type'=>FALSE,'cmd'=>'select');
		if (isset($arr['cmd'])){
			// compile button
			if (isset($arr['selector']['Source'])){$arr['Source']=$arr['selector']['Source'];}
			if (isset($arr['selector']['EntryId'])){$arr['EntryId']=$arr['selector']['EntryId'];}
			$arr=array_replace_recursive($stdKeys,$arr);
			if (!isset($arr['element-content'])){$arr['element-content']=ucfirst($arr['cmd']);}
			$arr['tag']='button';
			if (empty($arr['callingClass'])){$arr['callingClass']=__CLASS__;}
			if (empty($arr['callingFunction'])){$arr['callingFunction']=__FUNCTION__;}
			$arr['id']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getHash($arr,TRUE);
			if (!isset($arr['value'])){
				if (isset($arr['EntryId'])){$arr['value']=$arr['EntryId'];} else {$arr['value']=$arr['id'];};
			}
			$arr['key']=$arr['cmd'];
			$arr['keep-element-content']=TRUE;
			if (isset($btnDefs[$arr['cmd']])){
				// found button definition
				$arr=array_replace_recursive($arr,$btnDefs[$arr['cmd']]);
				if (!empty($arr['requiredRight'])){
					$hasAccess=$this->arr['SourcePot\Datapool\Foundation\Access']->access($arr,$arr['requiredRight']);
					if (empty($hasAccess)){$arr=FALSE;}
				}
				if (!empty($arr['requiresFile']) && strpos($arr['EntryId'],'-guideEntry')===FALSE){
					$hasFile=is_file($this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr));
					if (!$hasFile){$arr=FALSE;}
				}
			} else {
				// missing button definition
				if (!isset($arr['element-content'])){
					$arr['element-content']=ucfirst(isset($arr['value'])?$text=$arr['value']:$arr['key']);
				}
			}
			if (!empty($arr)){$html.=$this->element($arr);}
		} else {
			// button command processing
			$formData=$this->formProcessing(__CLASS__,__FUNCTION__);
			$selector=$this->formData2selector($formData);
			//if (!empty($formData['cmd'])){$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($formData);}
			if (isset($formData['cmd']['download'])){
				$this->arr['SourcePot\Datapool\Foundation\Filespace']->entry2fileDownload($selector);
			} else if (isset($formData['cmd']['delete']) || isset($formData['cmd']['delete all'])){
				//$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($selector);
				$this->arr['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector);
			} else if (isset($formData['cmd']['remove'])){
				$entry=$formData['element'];
				if (!empty($entry['EntryId'])){
					$file=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
					if (is_file($file)){unlink($file);}
					if (isset($entry['Params']['File'])){unset($entry['Params']['File']);}
					$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
				}
			} else if (isset($formData['cmd']['delete all entries'])){
				$this->arr['SourcePot\Datapool\Foundation\Database']->deleteEntriesOnly($selector);
			} else if (isset($formData['cmd']['moveUp'])){
				$this->arr['SourcePot\Datapool\Foundation\Database']->moveEntry($selector,TRUE);
			} else if (isset($formData['cmd']['moveDown'])){
				$this->arr['SourcePot\Datapool\Foundation\Database']->moveEntry($selector,FALSE);
			} else if (isset($formData['cmd']['select'])){
				if (isset($this->arr['view classes'][$selector['Source']])){
					$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageState($this->arr['view classes'][$selector['Source']],$selector);
				}
			}
			return $arr;
		}
		return $html;
	}
	
	public function app($arr){
		$arrTemplate=array('class'=>'app','icon'=>'?','default-max-width'=>'fit-content','default-max-height'=>'fit-content','js-status'=>'minimized','default-min-width'=>'28px','default-min-height'=>'26px','keep-element-content'=>TRUE);
		$arr=array_merge($arrTemplate,$arr);
		$arr['style']['width']=$arr['default-min-width'];
		$arr['style']['height']=$arr['default-min-height'];
		if (empty($arr['html'])){return '';}
		$id=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getHash($arr['html'],TRUE);
		$iconArr=$arr;
		unset($iconArr['default-min-width']);
		unset($iconArr['default-min-height']);
		unset($iconArr['default-max-width']);
		unset($iconArr['default-max-height']);
		unset($iconArr['style']);
		$iconArr['tag']='a';
		$iconArr['element-content']=$arr['icon'];
		$iconArr['id']='app-icon-'.$id;
		$icon=$this->element($iconArr);
		$divArr=$arr;
		$divArr['tag']='div';
		$divArr['element-content']=$arr['html'].$icon;
		$divArr['id']='app-content-'.$id;
		$html=$this->element($divArr);
		return $html;
	}
	
	public function emojis($arr=array()){
		if (!isset($arr['html'])){$arr['html']='';}
		if (empty($arr['settings']['target'])){
			$arr['html']='Error: method '.__FUNCTION__.' called without target setting.';
			return $arr;
		}
		$callingFunction=$arr['settings']['target'];
		if (!isset($_SESSION[__CLASS__]['settings'][$callingFunction])){$_SESSION[__CLASS__]['settings'][$callingFunction]=array('Category'=>'');}
		$arr['formData']=$this->formProcessing(__CLASS__,$callingFunction);
		if (!empty($arr['formData']['val'])){
			$_SESSION[__CLASS__]['settings'][$callingFunction]=$arr['formData']['val'];
		}
		$currentKeys=explode('||',$_SESSION[__CLASS__]['settings'][$callingFunction]['Category']);
		$options=array();
		foreach($this->arr['SourcePot\Datapool\Tools\MiscTools']->emojis as $category=>$categoryArr){
			foreach($categoryArr as $group=>$groupArr){
				$firstEmoji=$this->arr['SourcePot\Datapool\Tools\MiscTools']->code2utf(key($groupArr));
				$options[$category.'||'.$group]=$firstEmoji.' '.$group;
			}
		}
		$categorySelectArr=array('options'=>$options,'key'=>array('Category'),'selected'=>$_SESSION[__CLASS__]['settings'][$callingFunction]['Category'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction);
		$arr['html'].=$this->select($categorySelectArr);
		if (count($currentKeys)>1){
			$tagArr=array('tag'=>'a','href'=>'#','class'=>'emoji','target'=>$arr['settings']['target']);
			foreach($this->arr['SourcePot\Datapool\Tools\MiscTools']->emojis[$currentKeys[0]][$currentKeys[1]] as $code=>$title){
				$tagArr['id']='utf8-'.$code;
				$tagArr['title']=$title;
				$tagArr['element-content']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->code2utf($code);
				$arr['html'].=$this->element($tagArr);
			}
		}
		$arr['html']=$this->element(array('tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE));
		return $arr;
	}
	
	public function integerEditor($selector,$key,$integerDef=array(),$bitCount=16){
		// This function provides the HTML-script for an integer editor for the provided entry argument.
		// Typical use is for keys 'Read', 'Write' or 'Priviledges'.
		//
		$integer=$selector[$key];
		$callingClass=__CLASS__;
		$callingFunction=__FUNCTION__.$key;
		$formData=$this->formProcessing($callingClass,$callingFunction);
		$saveRequest=isset($formData['cmd'][$key]['save']);
		$updatedInteger=0;
		$matrix=array();
		if (is_string($integer)){$integer=intval($integer);}
		for($bitIndex=0;$bitIndex<$bitCount;$bitIndex++){
			$currentVal=pow(2,$bitIndex);
			if ($saveRequest){
				// get checkboxes from form
				if (empty($formData['val'][$key][$bitIndex])){
					$checked=FALSE;
				} else {
					$updatedInteger+=$currentVal;
					$checked=TRUE;
				}
			} else {
				// get checkboxes from form
				if (($currentVal & $integer)==0){$checked=FALSE;} else {$checked=TRUE;}
			}
			if (isset($integerDef[$bitIndex]['Name'])){$label=$integerDef[$bitIndex]['Name'];} else {$label=$bitIndex;}
			$bitIndex=strval($bitIndex);
			$id=md5($callingClass.$callingFunction.$bitIndex);
			$cellHtml=$this->element(array('tag'=>'label','for'=>$id,'element-content'=>strval($label)));
			$cellHtml.=$this->element(array('tag'=>'input','type'=>'checkbox','checked'=>$checked,'id'=>$id,'key'=>array($key,$bitIndex),'callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'title'=>'Bit '.$bitIndex));
			$matrix[$bitIndex]['Current value']=$cellHtml;
		}
		$updateBtn=array('tag'=>'button','key'=>array($key,'save'),'value'=>'save','element-content'=>'💾','callingClass'=>$callingClass,'callingFunction'=>$callingFunction);
		$matrix['Cmd']['Current value']=$this->element($updateBtn);
		if ($saveRequest){
			$selector=$this->arr['SourcePot\Datapool\Foundation\Explorer']->guideEntry2selector($selector);
			$this->arr['SourcePot\Datapool\Foundation\Database']->resetStatistic();
			$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntries($selector,array($key=>$updatedInteger),FALSE,FALSE);
			$statistics=$this->arr['SourcePot\Datapool\Foundation\Database']->getStatistic();
			if ($statistics['updated']>0){
				$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Key "'.$key.'" updated for "'.$statistics['updated'].'" entries.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			}
		}
		$html=$this->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>FALSE,'hideKeys'=>TRUE,'hideHeader'=>TRUE));
		return $html;
	}
	
	public function setAccessByte($arr){
		// This method returns html with a number of checkboxes to set the bits of an access-byte.
		// $arr[key] ... Selects the respective access-byte, e.g. $arr['key']='Read', $arr['key']='Write' or $arr['key']='Privileges'.   
		if (isset($arr['selector'])){$entry=$arr['selector'];} else {return $arr;}
		if (empty($arr['key'])){$arr['key']='Read';}
		if (empty($entry['Source']) || empty($entry['EntryId']) || empty($entry[$arr['key']])){
			$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>'Required keys missing.'));
		} else if ($this->arr['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE,FALSE,$ignoreOwner=TRUE)){
			$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->integerEditor($entry,$arr['key'],$this->arr['SourcePot\Datapool\Foundation\User']->getUserRols());
		} else {
			$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>'access denied'));
		}
		return $html;
	}

	public function entryControls($arr){
		// This method returns html with a file upload facility and buttons to 
		// 'download' an attached file, 'remove' the attached file and 'delete' the entry.
		// $arr['hideDownload']=TRUE hides the downlaod-button, $arr['hideRemove']=TRUE hides the remove-button and $arr['hideDelete']=TRUE hides the delete-button. 
		if (!isset($arr['selector'])){return 'Selector missing';}
		$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
		if (empty($entry)){return 'Entry does not exsist (yet).';}
		if (!isset($arr['callingClass'])){$arr['callingClass']=__CLASS__;}
		if (!isset($arr['callingFunction'])){$arr['callingFunction']=__FUNCTION____;}
		$html='';
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'input','type'=>'file','key'=>array('Upload'),'style'=>array('clear'=>'left'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'button','element-content'=>'Upload','key'=>array('Upload'),'style'=>array('clear'=>'right'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'excontainer'=>TRUE));
		$mediaArr=$this->arr['SourcePot\Datapool\Tools\MediaTools']->getPreview(array('selector'=>$arr['selector'],'style'=>array('width'=>'100%','max-height'=>100,'max-height'=>100)));
		$html.=$mediaArr['html'];
		$btnArr=$arr['selector'];
		$matrix=array();
		foreach(array('download','remove','delete') as $cmd){
			$ucfirstCmd=ucfirst($cmd);
			if (!empty($arr['hide'.$ucfirstCmd])){continue;}
			$btnArr['cmd']=$cmd;
			$tag=$this->btn($btnArr);
			if (!empty($tag)){$matrix[$ucfirstCmd]['Button']=$tag;}
		
		}
		$html.=$this->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'keep-element-content'=>TRUE,'style'=>array('clear'=>'both')));
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE));
		return $html;
	}

	public function entryListEditor($arr){
		// This method returns a html-table from entries selected by the arr-argument.
		// Each entry is a row in the table and the data stored under the key Content of every entry can be updated and entries can be added or deleted.
		// $arr must contain the key 'contentStructure' which defines the html-elements used in order to show and edit the entry content.
		// Important keys are:
		// 'contentStructure' ... array([Content key]=>array('htmlBuilderMethod'=>[HTMLbuilder method to be used],'class'=>[Style class],....))
		// 'callingClass','callingFunction' ... are used for the form processing
		// 'caption' ... sets the table caption
		if (empty($arr['caption'])){$arr['caption']='Please provide a caption';}
		if (empty($arr['Name'])){$arr['Name']=$arr['caption'];}
		if (empty($arr['callingClass'])){$arr['callingClass']=__CLASS__;}
		if (empty($arr['callingFunction'])){$arr['callingFunction']=__CLASS__;}
		if (empty($arr['contentStructure']) || empty($arr['Source'])){return array('error'=>'Required arr key(s) missing in '.__FUNCTION__);}
		$arr['rowCount']=0;
		$matrix=array();
		$matrix['New']=$this->entry2row($arr,TRUE,FALSE);
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($arr,FALSE,'Read','EntryId',TRUE) as $entry){
			$arr['rowCount']=$entry['rowCount'];
			$entryArr=$entry;
			if (isset($arr['canvasCallingClass'])){$entryArr['canvasCallingClass']=$arr['canvasCallingClass'];}
			$entryArr['callingClass']=$arr['callingClass'];
			$entryArr['callingFunction']=$arr['callingFunction'];
			$entryArr['contentStructure']=$arr['contentStructure'];
			$entryArr['Name']=$arr['Name'];
			$matrix[$entry['EntryId']]=$this->entry2row($entryArr,FALSE,FALSE);
		}
		$matrix['New']=$this->entry2row($arr,FALSE,FALSE);
		$html=$this->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
		return $html;
	}

	public function entry2row($arr,$commandProcessingOnly=FALSE,$singleRowOnly=FALSE){
		if ($commandProcessingOnly || $singleRowOnly){
			$formData=$this->formProcessing($arr['callingClass'],$arr['callingFunction']);
			if (isset($formData['cmd']['add']) || isset($formData['cmd']['save'])){
				$entry=$arr;
				$entry['EntryId']=current($formData['cmd']);
				$entry['Content']=$formData['val'][$entry['EntryId']]['Content'];
				$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ALL_CONTENTADMIN_R');
				$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
			} else if (isset($formData['cmd']['delete'])){
				$selector=array('Source'=>$arr['Source'],'EntryId'=>$formData['cmd']['delete']);
				$this->arr['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector);
			} else if (isset($formData['cmd']['moveUp'])){
				$selector=array('Source'=>$arr['Source'],'EntryId'=>$formData['cmd']['moveUp']);
				$this->arr['SourcePot\Datapool\Foundation\Database']->moveEntry($selector,TRUE);
			} else if (isset($formData['cmd']['moveDown'])){
				$selector=array('Source'=>$arr['Source'],'EntryId'=>$formData['cmd']['moveDown']);
				$this->arr['SourcePot\Datapool\Foundation\Database']->moveEntry($selector,FALSE);
			}
			if ($commandProcessingOnly){return array();}
		}
		$row=array();
		if (empty($arr['EntryId'])){
			$newEntry=TRUE;
			$arr=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr,array('Source','Group','Folder','Name','Type'),0);
			if ($singleRowOnly){
				// nothing to do here yet
			} else if (isset($arr['rowCount'])){
				$arr['EntryId']=$this->arr['SourcePot\Datapool\Foundation\Database']->addOrderedListIndexToEntryId($arr['EntryId'],$arr['rowCount']+1);
				$this->arr['SourcePot\Datapool\Foundation\Database']->orderedEntryListCleanup($arr);
			}
		}
		foreach($arr['contentStructure'] as $contentKey=>$elementArr){
			if (!isset($elementArr['htmlBuilderMethod'])){array('error'=>'arr["contentStructure" key "htmlBuilderMethod" missing in '.__FUNCTION__);}
			$htmlBuilderMethod=$elementArr['htmlBuilderMethod'];
			if (!method_exists(__NAMESPACE__,$htmlBuilderMethod)){array('error'=>'arr["contentStructure" requests method '.$htmlBuilderMethod.' which does not exist in '.__FUNCTION__);}
			if (isset($arr['Content'][$contentKey])){$elementArr['value']=$arr['Content'][$contentKey];}
			$elementArr['callingClass']=$arr['callingClass'];
			$elementArr['callingFunction']=$arr['callingFunction'];
			$elementArr['key']=array($arr['EntryId'],'Content',$contentKey);
			if (isset($arr['canvasCallingClass'])){$elementArr['canvasCallingClass']=$arr['canvasCallingClass'];}
			$row[$contentKey]=$this->$htmlBuilderMethod($elementArr);
		}
		if (empty($arr['noBtns'])){
			if (empty($newEntry)){
				$arr['cmd']='save';
				$row['Buttons']=$this->btn($arr);	
				$arr['cmd']='delete';
				$row['Buttons'].=$this->btn($arr);
				if (empty($arr['isLast'])){
					$arr['cmd']='moveUp';
					$row['Buttons'].=$this->btn($arr);
				}
				if (empty($arr['isFirst'])){
					$arr['cmd']='moveDown';
					$row['Buttons'].=$this->btn($arr);
				}
			} else {
				$arr['cmd']='add';
				$row['Buttons']=$this->btn($arr);
			}
			$row['Buttons']=$this->element(array('tag'=>'div','keep-element-content'=>TRUE,'element-content'=>$row['Buttons'],'style'=>'min-width:150px;'));
		}
		return $row;
	}
	
	public function formProcessing($callingClass,$callingFunction,$resetAfterProcessing=FALSE){
		// This method returns the result from processing of $_POST and $_FILES.
		// It returns an array with the old values, the new values, files und commmands.
		$result['cmd']=array();
		$result['val']=array();
		$result['valFlat']=array();
		$result['changed']=array();
		$result['files']=array();
		$S=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		if (isset($_SESSION[$callingClass][$callingFunction])){
			foreach($_SESSION[$callingClass][$callingFunction] as $name=>$arr){
				if (isset($_POST[$name])){
					// process $_POST
					if (empty($arr['filter'])){$filter=FILTER_DEFAULT;} else {$filter=$arr['filter'];}
					$newValue=filter_input(INPUT_POST,$name,FILTER_DEFAULT);
					if (strcmp(strval($arr['type']),'submit')===0){
						$result['cmd'][$arr['key']]=$newValue;
					} else if (strcmp(strval($arr['tag']),'button')===0){
						$result['cmd'][$arr['key']]=$newValue;
					} else {
						$result['val'][$arr['key']]=$newValue;
						if (!isset($arr['value'])){$arr['value']='';}
						if (strcmp(strval($newValue),strval($arr['value']))!==0){
							$result['changed'][$arr['key']]=$arr['value'];
							$_SESSION[$callingClass][$callingFunction][$name]['value']=$newValue;
						}
					}
					$result['element']=$_SESSION[$callingClass][$callingFunction][$name];
				}
				if (isset($_FILES[$name])){
					// process $_FILES
					$result['file errors']=array();
					foreach($_FILES[$name] as $fileKey=>$fileArr){
						if (!is_array($fileArr)){$fileArr=array($fileArr);}
						foreach($fileArr as $fileIndex=>$fileValue){
							if (strcmp($fileKey,'error')===0){
								if (intval($fileValue)>0){
									$result['file errors'][$fileIndex]=$this->fileErrorCode2str($fileValue);
								}
							}
							$result['files'][$arr['key'].$S.$fileIndex.$S.$fileKey]=$fileValue;
							$result['files'][$arr['key'].$S.$fileIndex.$S.'element name']=$name;
						}
					}
					$result['files']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flat2arr($result['files']);
					foreach($result['file errors'] as $fileIndex=>$error){unset($result['files'][$arr['key']][$fileIndex]);}
				}
			}
			if ($resetAfterProcessing){$_SESSION[$callingClass][$callingFunction]=array();}
		}
		$result['valFlat']=$result['val'];
		$result['cmd']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flat2arr($result['cmd']);
		$result['val']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flat2arr($result['val']);
		$result['changed']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flat2arr($result['changed']);
		//$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($result);
		return $result;
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
	
	public function formData2selector($formData){
		$selector=array();
		if (empty($formData['element'])){return $selector;}
		if (strpos($formData['element']['EntryId'],'-guideEntry')!==FALSE && strpos($formData['element']['Type'],'__')===0){
			$selectorTemplate=array('Source'=>TRUE,'Group'=>TRUE,'Folder'=>TRUE);
		} else if (!empty($formData['element']['EntryId'])){
			$selectorTemplate=array('Source'=>TRUE,'EntryId'=>TRUE);
		} else {
			$selectorTemplate=$this->arr['SourcePot\Datapool\Foundation\Database']->entryTemplate($formData['element']);
			$selectorTemplate['Source']=array();
		}
		foreach($selectorTemplate as $column=>$defArr){
			if (!empty($formData['element'][$column])){
				if (is_array($formData['element'][$column])){continue;}
				$selector[$column]=$formData['element'][$column];
			}
		}
		return $selector;
	}

}
?>