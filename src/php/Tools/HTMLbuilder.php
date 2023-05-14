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
	
	private $elementAttrWhitelist=array('tag'=>TRUE,'input'=>TRUE,'type'=>TRUE,'class'=>TRUE,'style'=>TRUE,'id'=>TRUE,'name'=>TRUE,'title'=>TRUE,'function'=>TRUE,
										'method'=>TRUE,'enctype'=>TRUE,'xmlns'=>TRUE,'lang'=>TRUE,'href'=>TRUE,'src'=>TRUE,'value'=>TRUE,'width'=>TRUE,'height'=>TRUE,
										'rows'=>TRUE,'cols'=>TRUE,'target'=>TRUE,'allowfullscreen'=>TRUE,
										'min'=>TRUE,'max'=>TRUE,'for'=>TRUE,'multiple'=>TRUE,'disabled'=>TRUE,'selected'=>TRUE,'checked'=>TRUE,'controls'=>TRUE,'trigger-id'=>TRUE,
										'container-id'=>TRUE,'excontainer'=>TRUE,'container'=>TRUE,'cell'=>TRUE,'row'=>TRUE,'source'=>TRUE,'entry-id'=>TRUE,'source'=>TRUE,'index'=>TRUE,
										'js-status'=>TRUE,'default-min-width'=>TRUE,'default-min-height'=>TRUE,'default-max-width'=>TRUE,'default-max-height'=>TRUE,
										);
    private $needsNameAttr=array('input'=>TRUE,'select'=>TRUE,'textarea'=>TRUE,'button'=>TRUE,'fieldset'=>TRUE,'legend'=>TRUE,'output'=>TRUE,'optgroup'=>TRUE);
	
	
	private $btns=array('test'=>array('key'=>array('test'),'title'=>'Test run','hasCover'=>FALSE,'element-content'=>'Test','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE,'excontainer'=>FALSE),
					    'run'=>array('key'=>array('run'),'title'=>'Run','hasCover'=>FALSE,'element-content'=>'Run','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE,'excontainer'=>TRUE),
					    'add'=>array('key'=>array('add'),'title'=>'Add this entry','hasCover'=>FALSE,'element-content'=>'+','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'requiresFile'=>FALSE),
					    'save'=>array('key'=>array('save'),'title'=>'Save this entry','hasCover'=>FALSE,'element-content'=>'&check;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>FALSE),
					    'download'=>array('key'=>array('download'),'title'=>'Download attached file','hasCover'=>FALSE,'element-content'=>'&#8892;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','requiresFile'=>TRUE,'excontainer'=>TRUE),
					    'download all'=>array('key'=>array('download all'),'title'=>'Download all attached file','hasCover'=>FALSE,'element-content'=>'&#8892;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','requiresFile'=>FALSE,'excontainer'=>TRUE),
					    'select'=>array('key'=>array('select'),'title'=>'Select entry','hasCover'=>FALSE,'element-content'=>'&#10022;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Read','excontainer'=>TRUE),
					    'delete'=>array('key'=>array('delete'),'title'=>'Delete entry','hasCover'=>TRUE,'element-content'=>'&coprod;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','style'=>array('float'=>'left')),
					    'remove'=>array('key'=>array('remove'),'title'=>'Remove file','hasCover'=>TRUE,'element-content'=>'&coprod;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write','requiresFile'=>TRUE,'style'=>array('float'=>'left')),
					    'delete all'=>array('key'=>array('delete all'),'title'=>'Delete all selected entries','hasCover'=>TRUE,'element-content'=>'Delete all selected','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'style'=>array('float'=>'left')),
					    'delete all entries'=>array('key'=>array('delete all entries'),'title'=>'Delete all selected entries excluding attched files','hasCover'=>TRUE,'element-content'=>'Delete all selected','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>FALSE,'style'=>'float:left;'),
					    'moveUp'=>array('key'=>array('moveUp'),'title'=>'Moves the entry up','hasCover'=>FALSE,'element-content'=>'&#9660;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write'),
					    'moveDown'=>array('key'=>array('moveDown'),'title'=>'Moves the entry down','hasCover'=>FALSE,'element-content'=>'&#9650;','keep-element-content'=>TRUE,'tag'=>'button','requiredRight'=>'Write'),
					    );

	public function __construct($oc){
		$this->oc=$oc;
	}
	
	public function init($oc){
		$this->oc=$oc;
	}
	
	public function getBtns($arr){
		if (isset($this->btns[$arr['cmd']])){
			$arr=array_merge($arr,$this->btns[$arr['cmd']]);
		}
		return $arr;
	}
	
	public function traceHtml($msg='This has happend:'){
		$trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,5);	
		$html='<p>'.$msg.'</p><ol>';
		$html.='<li>1. '.$trace[1]['class'].'::'.$trace[1]['function'].'() '.$trace[0]['line'].'</li>';
		$html.='<li>2. '.$trace[2]['class'].'::'.$trace[2]['function'].'() '.$trace[1]['line'].'</li>';
		$html.='<li>3. '.$trace[3]['class'].'::'.$trace[3]['function'].'() '.$trace[2]['line'].'</li></ul>';
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
		// This function provides an HTML-table created based on the arr argument.
		// The following keys are specific to this funtion:
		// 'matrix' ... is the table matrix with the format: arr[matrix][row-index]=array(column-index => column-value, ..., last column-index => last column-value)
		// 'caption' ... is the table caption
		// 'skipEmptyRows' ... if TRUE rows with empty cells only will be skipped
		$html='';
		$hasRows=TRUE;
		$toReplace=array();
		if (!isset($arr['matrix'])){
			$msg='<p>'.__CLASS__.'&rarr;'.__FUNCTION__.' called with arr[matrix] missing.</p>';	
			$html=$this->traceHtml($msg);
		} else if (!is_array($arr['matrix'])){
			$msg='<p>'.__CLASS__.'&rarr;'.__FUNCTION__.' called with type arr[matrix] being '.gettype($arr['matrix']).'. should be an array.</p>';
			$html=$this->traceHtml($msg);
		} else if (!empty($arr['matrix'])){
			$hasRows=FALSE;
			if (empty($arr['style'])){
				$html.='<table {{class}}>'.PHP_EOL;
			} else {
				$arr['style']=is_array($arr['style'])?$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2style($arr['style']):$arr['style'];
				$html.='<table {{class}} style="'.$arr['style'].'">'.PHP_EOL;	
			}
			if (!empty($arr['caption'])){$html.='<caption {{class}}>'.PHP_EOL.$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng($arr['caption']).'</caption>'.PHP_EOL;}
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
					$toReplace['{{thead}}'].='<th {{class}}>'.$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng('Key').'</th>';
					$newRow.='<td {{class}}>'.$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng($row).'</td>';						
				}
				$allCellsEmpty=TRUE;
				$cellIndex=1;
				foreach($rowArr as $column=>$cell){
					if (is_array($column)){$column=$this->oc['SourcePot\Datapool\Foundation\Element']->element($column);} else {$column=htmlEntities(strval($column),ENT_QUOTES);}
					if (is_array($cell)){
						$cell=$this->oc['SourcePot\Datapool\Foundation\Element']->element($cell);
					} else {
						if (empty($arr['keep-element-content'])){$cell=htmlspecialchars(strval($cell),ENT_QUOTES,'UTF-8');}
					}
					if (!empty($cell)){$allCellsEmpty=FALSE;}
					$toReplace['{{thead}}'].='<th {{class}}>'.ucfirst($column).'</th>';
					$newRow.='<td {{class}} cell="'.$cellIndex.'-'.$rowIndex.'">'.$cell.'</td>';
					$cellIndex++;					
				}
				$newRow.='</tr>'.PHP_EOL;
				$toReplace['{{thead}}'].='</tr>'.PHP_EOL;
				if (!$allCellsEmpty || empty($arr['skipEmptyRows'])){
					$hasRows=TRUE;
					$toReplace['{{tbody}}'].=$newRow;
				}
				$rowIndex++;
			}
			if (empty($hasRows)){$html='';}
			if (empty($arr['class'])){$toReplace['{{class}}']='';} else {$toReplace['{{class}}']=$arr['class'];}
			foreach($toReplace as $needle=>$value){$html=str_replace($needle,$value,$html);}
		}
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
		$html='';
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->getDbInfo() as $table=>$tableDef){$arr['options'][$table]=ucfirst($table);}
		$html.=$this->select($arr);
		return $html;
	}
	
	public function keySelect($arr){
		$html='';
		if (empty($arr['Source'])){return $html;}
		$fileContentKeys=array();
		$keys=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($arr['Source']);
		if (empty($arr['standardColumsOnly'])){
			foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($arr,TRUE) as $tmpEntry){
				if ($tmpEntry['isSkipRow']){continue;}
				if (isset($tmpEntry['Params']['File']['MIME-Type'])){
					if (strpos($tmpEntry['Params']['File']['MIME-Type'],'text/')===0){
						$fileContentKeys=$this->oc['SourcePot\Datapool\Tools\CSVtools']->csvIterator($tmpEntry)->current();
					}
				}
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
		foreach($fileContentKeys as $key=>$value){
			$key='File content'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator().$key;
			$arr['options'][$key]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($key);
		}
		foreach($keys as $key=>$value){
			$arr['options'][$key]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($key);
		}
		$html.=$this->select($arr);
		return $html;
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
		$html=$this->select($arr);
		return $html;
	}
	
	public function preview($arr){
		return $this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
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
			$arr['value']=(isset($arr['value']))?$arr['value']:((isset($arr['selector']['EntryId']))?$arr['selector']['EntryId']:$arr['id']);
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
			} else {
				$btnFailed='Button defintion missing';
			}
			if (empty($btnFailed)){
				$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
			}
		} else {
			// button command processing
			$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
			$selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($formData['selector']);
			//if (!empty($formData['cmd'])){$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($formData);}
			if (isset($formData['cmd']['download']) || isset($formData['cmd']['download all'])){
				$this->oc['SourcePot\Datapool\Foundation\Filespace']->entry2fileDownload($selector);
			} else if (isset($formData['cmd']['delete']) || isset($formData['cmd']['delete all'])){
				$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector);
				$classWithNamespace=$this->oc['SourcePot\Datapool\Root']->source2class($selector['Source']);
				$selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->selectorAfterDeletion($selector);
				$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($classWithNamespace,$selector);	
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
				$classWithNamespace=$this->oc['SourcePot\Datapool\Root']->source2class($selector['Source']);
				$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($classWithNamespace,$selector);
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
		if (!isset($_SESSION[__CLASS__]['settings'][$callingFunction]['Category'])){$_SESSION[__CLASS__]['settings'][$callingFunction]['Category']=key($options);}
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
		// Typical use is for keys 'Read', 'Write' or 'Priviledges'.
		//
		if (empty($arr['selector']['Source'])){return 'Method '.__FUNCTION__.' called but Source missing.';}
		$template=array('key'=>'Read','integerDef'=>$this->oc['SourcePot\Datapool\Foundation\User']->getUserRols(),'bitCount'=>16);
		$arr=array_replace_recursive($template,$arr);
		$entry=$arr['selector'];
		if (!$this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE,FALSE,$ignoreOwner=TRUE)){
			//return $this->element(array('tag'=>'p','element-content'=>'access denied'));
		}
		$integer=$entry[$arr['key']];
		$callingClass=__CLASS__;
		$callingFunction=__FUNCTION__.$arr['key'];
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($callingClass,$callingFunction);
		if ($saveRequest=isset($formData['cmd'][$arr['key']]['save'])){
			//$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($formData,$arr['key']);
		}
		$updatedInteger=0;
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
			$matrix[$bitIndex]['Label']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'label','for'=>$id,'element-content'=>strval($label)));
			$matrix[$bitIndex]['Status']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'checkbox','checked'=>$checked,'id'=>$id,'key'=>array($arr['key'],$bitIndex),'callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'title'=>'Bit '.$bitIndex));
		}
		$updateBtn=array('tag'=>'button','key'=>array($arr['key'],'save'),'value'=>'save','element-content'=>'💾','callingClass'=>$callingClass,'callingFunction'=>$callingFunction);
		$matrix['Cmd']['Label']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($updateBtn);
		$matrix['Cmd']['Status']='';
		if ($saveRequest){
			$this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
			$entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($entry);
			$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntries($entry,array($arr['key']=>$updatedInteger),FALSE,'Write');
			$statistics=$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
			$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>$arr['key'].'-key processed: '.$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics),'priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
		$hideHeader=(isset($arr['hideHeader']))?$arr['hideHeader']:TRUE;
		$hideKeys=(isset($arr['hideKeys']))?$arr['hideKeys']:TRUE;
		$html=$this->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'"'.$arr['key'].'" right','hideKeys'=>$hideKeys,'hideHeader'=>$hideHeader));
		// present as App if requested
		if (!empty($arr['isApp'])){
			$app=array('html'=>$html,'icon'=>$arr['key'][0],'title'=>'Setting "'.$arr['key'].'" access right');
			$html=$this->app($app);
			
		}
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
	public function entryControls($arr){
		if (!isset($arr['selector'])){return 'Selector missing';}
		$arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
		if (empty($arr['selector'])){return 'Entry does not exsist (yet).';}
		if (!isset($arr['callingClass'])){$arr['callingClass']=__CLASS__;}
		if (!isset($arr['callingFunction'])){$arr['callingFunction']=__FUNCTION____;}
		$matrix=array();
		$matrix['Preview']['Button']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'file','key'=>array('Upload'),'style'=>array('clear'=>'left'),'excontainer'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
		$matrix['Preview']['Button'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Upload','key'=>array('Upload'),'style'=>array('clear'=>'right'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'excontainer'=>TRUE));
		$mediaArr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview(array('selector'=>$arr['selector'],'style'=>array('max-height'=>600,'max-height'=>600)));
		$matrix['Preview']['Button'].=$mediaArr['html'];
		$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($arr);
		foreach(array('download','remove','delete') as $cmd){
			$ucfirstCmd=ucfirst($cmd);
			if (!empty($arr['hide'.$ucfirstCmd])){continue;}
			$arr['excontainer']=TRUE;
			$arr['cmd']=$cmd;
			$matrix[$ucfirstCmd]['Button']=$this->btn($arr);
		}
		$hideHeader=(isset($arr['hideHeader']))?$arr['hideHeader']:TRUE;
		$hideKeys=(isset($arr['hideKeys']))?$arr['hideKeys']:FALSE;
		$html=$this->table(array('matrix'=>$matrix,'hideHeader'=>$hideHeader,'hideKeys'=>$hideKeys,'caption'=>'Entry control elements','keep-element-content'=>TRUE,'style'=>array('clear'=>'none')));
		// present as App if requested
		if (!empty($arr['isApp'])){
			$app=array('html'=>$html,'icon'=>'&#128736;','title'=>'Add and remove files, delete the whole entry');
			$html=$this->app($app);
			
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
								$userName=$this->oc['SourcePot\Datapool\Foundation\User']->userAbtract($matrix[$matrixIndex][$label],3);
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
		if (empty($arr['contentStructure']) || empty($arr['selector']['Source']) || empty($arr['callingClass']) || empty($arr['callingFunction'])){
			throw new \ErrorException('Method '.__FUNCTION__.', required arr key(s) missing.',0,E_ERROR,__FILE__,__LINE__);	
		}
		$matrix=array();
		$matrix['New']=$this->entry2row($arr,TRUE,FALSE,FALSE);
		$selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($arr['selector'],array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'Name'=>FALSE,'Type'=>FALSE));
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','EntryId',TRUE) as $entry){
			$listIndicatorPos=strpos($entry['EntryId'],'__');
			$orderedListComps=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($entry['EntryId']);
			if (count($orderedListComps)!==2){continue;}
			$arr['selector']=$entry;
			$matrix[$orderedListComps[0]]=$this->entry2row($arr,FALSE,FALSE,FALSE);
		}
		$matrix['New']=$this->entry2row($arr,FALSE,FALSE,TRUE);
		$html=$this->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
		return $html;
	}

	public function entry2row($arr,$commandProcessingOnly=FALSE,$singleRowOnly=FALSE,$isNewRow=FALSE){
		if ($commandProcessingOnly || $singleRowOnly){
			$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
			if (isset($formData['cmd']['add']) || isset($formData['cmd']['save'])){
				$entry=$arr['selector'];
				$entry['EntryId']=$formData['cmd'][key($formData['cmd'])];
				$entry['Content']=$formData['val'][$entry['EntryId']]['Content'];
				$file=FALSE;
				if (isset($formData['files'][$entry['EntryId']])){
					$flatFile=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($formData['files'][$entry['EntryId']]);
					$file=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatArrLeaves($flatFile);
					if ($file['error']!=0){$file=FALSE;}
				}
				if ($file){
					$arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entries($file,$entry);
				} else {
					$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($entry);
					$arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
				}
			} else if (isset($formData['cmd']['delete'])){
				$selector=array('Source'=>$arr['selector']['Source'],'EntryId'=>$formData['cmd']['delete']);
				$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector);
			} else if (isset($formData['cmd']['moveUp'])){
				$selector=array('Source'=>$arr['selector']['Source'],'EntryId'=>$formData['cmd']['moveUp']);
				$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntry($selector,TRUE);
			} else if (isset($formData['cmd']['moveDown'])){
				$selector=array('Source'=>$arr['selector']['Source'],'EntryId'=>$formData['cmd']['moveDown']);
				$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntry($selector,FALSE);
			}
			if ($commandProcessingOnly){return array();}
		}
		$row=array();
		if ($isNewRow){
			$arr['selector']['Content']=array();
			$arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],$relevantKeys=array('Source','Group','Folder','Name','Type'),'0','',TRUE);
			$newIndex=(isset($arr['selector']['rowCount']))?$arr['selector']['rowCount']+1:1;
			$arr['selector']['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Database']->addOrderedListIndexToEntryId($arr['selector']['EntryId'],$newIndex);
			$this->oc['SourcePot\Datapool\Foundation\Database']->orderedEntryListCleanup($arr['selector']);
			
		}
		foreach($arr['contentStructure'] as $contentKey=>$elementArr){
			if (!isset($elementArr['htmlBuilderMethod'])){array('error'=>'arr["contentStructure" key "htmlBuilderMethod" missing in '.__FUNCTION__);}
			$htmlBuilderMethod=$elementArr['htmlBuilderMethod'];
			if (!method_exists(__NAMESPACE__,$htmlBuilderMethod)){array('error'=>'arr["contentStructure" requests method '.$htmlBuilderMethod.' which does not exist in '.__FUNCTION__);}
			if (isset($arr['selector']['Content'][$contentKey])){
				if (isset($elementArr['element-content'])){
					$elementArr['element-content']=$arr['selector']['Content'][$contentKey];
					if (!empty($elementArr['value'])){
						$elementArr['value']=$elementArr['value'];
					}
				} else {
					$elementArr['value']=$arr['selector']['Content'][$contentKey];
				}
			}
			$elementArr['callingClass']=$arr['callingClass'];
			$elementArr['callingFunction']=$arr['callingFunction'];
			$elementArr['key']=array($arr['selector']['EntryId'],'Content',$contentKey);
			if (isset($arr['canvasCallingClass'])){$elementArr['canvasCallingClass']=$arr['canvasCallingClass'];}
			$row[$contentKey]=$this->$htmlBuilderMethod($elementArr);
			if (isset($elementArr['type']) && isset($elementArr['value'])){
				if (strcmp($elementArr['type'],'hidden')===0){
					$row[$contentKey].=$elementArr['value'];
				}
			}
		}
		if (empty($arr['noBtns'])){
			$row['Buttons']='';
			$arr['value']=$arr['selector']['EntryId'];
			if (empty($isNewRow)){
				$btnArr=array_replace_recursive($arr,$this->btns['save']);
				$row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);	
				$btnArr=array_replace_recursive($arr,$this->btns['delete']);
				$row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);	
				if (empty($arr['selector']['isLast'])){
					$btnArr=array_replace_recursive($arr,$this->btns['moveUp']);
					$row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);	
				}
				if (empty($arr['selector']['isFirst'])){
					$btnArr=array_replace_recursive($arr,$this->btns['moveDown']);
					$row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);	
				}
			} else {
				$btnArr=array_replace_recursive($arr,$this->btns['add']);
				$row['Buttons'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
			}
			$row['Buttons']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','keep-element-content'=>TRUE,'element-content'=>$row['Buttons'],'style'=>'min-width:150px;'));
		}
		return $row;
	}
	
	public function getIframe($html,$arr=array()){
		if (!is_string($html)){return $html;}
		if (strlen($html)==strlen(strip_tags($html))){return $html;}
		$tmpDir=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
		$htmlFile=$tmpDir.md5($html).'.html';
		$bytes=file_put_contents($htmlFile,$html);
		$arr['tag']='iframe';
		$arr['allowfullscreen']=TRUE;
		$arr['element-content']='Html content';
		$arr['src']=str_replace($GLOBALS['dirs']['tmp'],$GLOBALS['relDirs']['tmp'],$htmlFile);
		return $this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
	}
	
	
}
?>