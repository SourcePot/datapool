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

namespace Datapool\Foundation;

class DataExplorer{
	
	private $arr;

	private $entryTable;
	private $entryTemplate=array();
	
	public $definition=array('Content'=>array('Style'=>array('text'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
															 'font-size'=>array('@tag'=>'input','@type'=>'text','@default'=>'1em'),
															 'color'=>array('@tag'=>'input','@type'=>'text','@default'=>'#fff'),
															 'background-color'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
															 'border'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
															 'line-height'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
															 'top'=>array('@tag'=>'input','@type'=>'text','@default'=>'0px'),
															 'left'=>array('@tag'=>'input','@type'=>'text','@default'=>'0px'),
															 ),
											  'Selector'=>array('Source'=>array('@function'=>'select','@options'=>array()),
																'Group'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
																'Folder'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
																'Name'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
																'ElementId'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
																'Type'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
																),
											  'Widgets'=>array('Processor'=>array('@function'=>'select','@options'=>array(),'@default'=>0),
															   'File upload'=>array('@function'=>'select','@options'=>array('No','Yes'),'@default'=>0),
															   'Delete selected entries'=>array('@function'=>'select','@options'=>array('No','Yes'),'@default'=>1),
																),
											  ),
							 'Read'=>array('@tag'=>'p','@default'=>'ALL_R'),
							 'Write'=>array('@tag'=>'p','@default'=>'ADMIN_R'),
							);

	private $cntrBtns=array('&#9874;'=>array('key'=>array('edit mode'),'value'=>'edit','editMode'=>FALSE,'style'=>'color:#fff;background-color:#d00;clear:right;'),
							'&#10006;'=>array('key'=>array('run mode'),'value'=>'run','editMode'=>TRUE,'style'=>'color:#fff;background-color:#d00;clear:right;'),
							'...'=>array('key'=>array('...'),'value'=>'new','editMode'=>TRUE,'title'=>'Text box','style'=>'clear:left;'),
							'&larr;'=>array('key'=>array('&larr;'),'value'=>'new','editMode'=>TRUE),
							'&uarr;'=>array('key'=>array('&uarr;'),'value'=>'new','editMode'=>TRUE),
							'&rarr;'=>array('key'=>array('&rarr;'),'value'=>'new','editMode'=>TRUE),
							'&darr;'=>array('key'=>array('&darr;'),'value'=>'new','editMode'=>TRUE),
							'&harr;'=>array('key'=>array('&harr;'),'value'=>'new','editMode'=>TRUE,'style'=>'clear:right;'),
							'&varr;'=>array('key'=>array('&varr;'),'value'=>'new','editMode'=>TRUE,'style'=>'clear:left;'),
							'&nwarr;'=>array('key'=>array('&nwarr;'),'value'=>'new','editMode'=>TRUE),
							'&nearr;'=>array('key'=>array('&nearr;'),'value'=>'new','editMode'=>TRUE),
							'&searr;'=>array('key'=>array('&searr;'),'value'=>'new','editMode'=>TRUE),
							'&swarr;'=>array('key'=>array('&swarr;'),'value'=>'new','editMode'=>TRUE),
							'|'=>array('key'=>array('|'),'value'=>'new','editMode'=>TRUE,'style'=>'clear:right;'),
							'&#9881;'=>array('key'=>array('&#9881;'),'value'=>'new','cronJobCntr'=>TRUE,'title'=>'CRON job','editMode'=>TRUE,'style'=>'clear:left;'),
							);
    
	private $processorOptions=array();
	
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		$this->completeDefintion();
		return $this->arr;
	}
	
	public function getEntryTable(){return $this->entryTable;}

	public function getEntryTemplate(){return $this->entryTemplate;}
	
	private function completeDefintion(){
		// add Source selector
		$sourceOptions=array(''=>'&larrhk;');
		$dbInfo=$this->arr['Datapool\Foundation\Database']->getDbInfo();
		foreach($dbInfo as $Source=>$defArr){$sourceOptions[$Source]=$Source;}
		$functionOptions=array(''=>'&larrhk;');
		$this->definition['Content']['Selector']['Source']['@options']=$sourceOptions;
		// add data processors
		$this->processorOptions=array(''=>'&larrhk;');
		foreach($this->arr['registered methods']['dataProcessor'] as $classWithNamespace=>$defArr){
			$label=$this->arr['Datapool\Foundation\Database']->class2source($classWithNamespace,TRUE,TRUE);
			$this->processorOptions[$classWithNamespace]=$label;
		}
		$this->definition['Content']['Widgets']['Processor']['@options']=$this->processorOptions;
		// add save button
		$this->definition['save']=array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save');
		$this->arr['Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
	}

	public function unifyEntry($entry){
		$entry=$this->arr['Datapool\Foundation\Definitions']->definition2entry($this->definition,$entry);
		return $entry;
	}

	public function getDataExplorer($callingClass){
		$result=array('Content'=>array('Selector'=>array(),'Widgets'=>array()));
		// get explorer html
		$canvasElement=$this->canvasFormProcessing($callingClass);
		if (!empty($canvasElement)){$return=$canvasElement['Content'];}
		$cntrHtml=$this->getCntrHtml($callingClass);
		$canvasHtml=$this->getCanvas($callingClass);
		$articleArr=array('tag'=>'article','class'=>'explorer','element-content'=>$canvasHtml.$cntrHtml,'keep-element-content'=>TRUE,'style'=>'width:96%;');
		$return['explorerHtml']=$this->arr['Datapool\Tools\HTMLbuilder']->element($articleArr);
		// get content html
		$return['contentHtml']='';
		$return['selector']=$this->arr['Datapool\Tools\NetworkTools']->getSelectorFromPageState($callingClass);
		if (!empty($canvasElement['Content']['Widgets']["Processor"])){
			$return['contentHtml'].=$this->arr[$canvasElement['Content']['Widgets']["Processor"]]->dataProcessor('settings',$canvasElement);
		}
     	return $return;
	}
	
	private function canvasFormProcessing($callingClass){
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,'getCanvas');
		if (isset($formData['cmd']['select'])){
			$this->arr['Datapool\Tools\NetworkTools']->setClassState(__CLASS__,'selectedCanvasElement',$formData['element']);
		} else if (isset($formData['cmd']['delete'])){
			$this->arr['Datapool\Foundation\Database']->deleteEntries($formData['element']);
			$this->arr['Datapool\Tools\NetworkTools']->setSelectorPageState(__CLASS__,array('Source'=>$this->entryTable));
		} else if (isset($formData['cmd']['view'])){
			$selector=array('Source'=>$formData['element']['Source'],'ElementId'=>$formData['element']['ElementId']);
			$this->arr['Datapool\Tools\NetworkTools']->setClassState(__CLASS__,'selectedCanvasElement',$selector);
			$selector=$formData['element']['Content']['Selector'];
			if (isset($this->arr['view classes'][$selector['Source']])){
				$this->arr['Datapool\Tools\NetworkTools']->setSelectorPageState($this->arr['view classes'][$selector['Source']],$selector);
			}
		}
		$selectedCanvasElement=$this->arr['Datapool\Tools\NetworkTools']->getClassState(__CLASS__,'selectedCanvasElement');
		$canvasElement=$this->arr['Datapool\Foundation\Database']->entryByKey($selectedCanvasElement);
		return $canvasElement;
	}
	
	private function getCanvas($callingClass){
		// create html
		$selectedCanvasElement=$this->arr['Datapool\Tools\NetworkTools']->getClassState(__CLASS__,'selectedCanvasElement');
		$html='';
		$isEditMode=$this->arr['Datapool\Tools\NetworkTools']->getClassState(__CLASS__,'isEditMode',FALSE);
		$selector=array('Source'=>$this->entryTable,'Group'=>'Canvas elements','Folder'=>$callingClass,'Type'=>'dataexplorer');
		foreach($this->arr['Datapool\Foundation\Database']->entryIterator($selector) as $entry){
			$html.=$this->canvasElement2html(__CLASS__,__FUNCTION__,$entry,$selectedCanvasElement);
		}
		$html='<div id="canvas">'.$html.'</div>';
		return $html;
	}

	private function getCntrHtml($callingClass){
		$selectedCanvasElement=$this->arr['Datapool\Tools\NetworkTools']->getClassState(__CLASS__,'selectedCanvasElement');
		$canvasElement=$this->arr['Datapool\Foundation\Database']->entryByKey($selectedCanvasElement);
		// get canvas widgets
		$widgetsHtml='';
		$widgetsHtml.=$this->getFileUpload($canvasElement);
		$widgetsHtml.=$this->getDeleteBtn($canvasElement);
		if (!empty($canvasElement['Content']['Widgets']["Processor"])){
			$widgetsHtml.=$this->arr[$canvasElement['Content']['Widgets']["Processor"]]->dataProcessor('widget',$canvasElement);
		}
		// form processing
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__,TRUE);
		//$this->arr['Datapool\Tools\ArrTools']->arr2file($formData);
		if (isset($formData['cmd']['edit mode'])){
			$this->arr['Datapool\Tools\NetworkTools']->setClassState(__CLASS__,'isEditMode',TRUE);	
		} else if (isset($formData['cmd']['run mode'])){
			$this->arr['Datapool\Tools\NetworkTools']->setClassState(__CLASS__,'isEditMode',FALSE);	
		} else if (!empty($formData['cmd'])){
			$entry=array('Source'=>$this->entryTable,'Group'=>'Canvas elements','Folder'=>$callingClass,'Type'=>'dataexplorer');
			$entry=$this->arr['Datapool\Tools\ArrTools']->unifyEntry($entry);
			foreach($this->cntrBtns as $elementContent=>$def){
				$key=current($def['key']);
				if (isset($formData['cmd'][$key])){
					$entry['Content']['Style']=array('text'=>$elementContent);
					if (strcmp($elementContent,'...')===0){
						$entry['Content']['Style']['color']='#000';
						$entry['Content']['Style']['background-color']='#aaa';
					} else if (strcmp($elementContent,'&#9881;')===0){
						$entry['Content']['Style']['color']='#fff';
						$entry['Content']['Style']['background-color']='#20f';
						$entry['Content']['Selector']['Source']=$this->arr[$callingClass]->getEntryTable();
					}
					$entry=$this->arr['Datapool\Foundation\Access']->addRights($entry,'ALL_R','ALL_CONTENTADMIN_R');
					$entry=$this->arr['Datapool\Foundation\Database']->updateEntry($entry);
					$selector=array('Source'=>$this->entryTable,'ElementId'=>$entry['ElementId']);
					$this->arr['Datapool\Tools\NetworkTools']->setSelectorPageState(__CLASS__,$selector);
					break;
				}
			}
		}
		// get canves buttons
		$html='';
		$editorHtml='';
		if ($this->arr['Datapool\Foundation\Access']->isContentAdmin()){
			$isEditMode=$this->arr['Datapool\Tools\NetworkTools']->getClassState(__CLASS__,'isEditMode',FALSE);
			foreach($this->cntrBtns as $elementContent=>$element){
				if (empty($isEditMode)===$element['editMode']){continue;}
				$btnArr=$element;
				$btnArr['element-content']=$elementContent;
				$btnArr['keep-element-content']=TRUE;
				$btnArr['tag']='button';
				$btnArr['class']='canvas';
				$btnArr['callingClass']=__CLASS__;
				$btnArr['callingFunction']=__FUNCTION__;
				$html.=$this->arr['Datapool\Tools\HTMLbuilder']->element($btnArr);
			}
			$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','class'=>'canvas','element-content'=>$html,'keep-element-content'=>TRUE));
			// get canvas element editor
			$selector=$this->arr['Datapool\Tools\NetworkTools']->getClassState(__CLASS__,'selectedCanvasElement');
			if (!empty($selector) && !empty($isEditMode)){
				$canvasElement=$this->arr['Datapool\Foundation\Database']->entryByKey($selector);
				if ($canvasElement){
					$definition=$this->arr['Datapool\Foundation\Definitions']->getDefinition($canvasElement);
					$editorHtml.=$this->arr['Datapool\Foundation\Definitions']->definition2form($definition,$canvasElement);
				}
			}
			if (empty($isEditMode)){$editorHtml.=$this->exportImportHtml($callingClass);}
		}
		if (empty($isEditMode)){$html.=$widgetsHtml;}
		$html.=$editorHtml;
		return $html;
	}
	
	public function canvasSelector($callingClass){
		// This method returns the geberiv selector for the canvas elements.
		$selector=array('Source'=>$this->entryTable,'Group'=>'Canvas elements','Folder'=>$callingClass,'Type'=>'dataexplorer');
		return $selector;
	}
	
	public function getCanvasElements($callingClass){
		// This method is called by HTMLbuilder to provide a canvas elements selector.
		// It returns the canvas elements in order by their position.
		$elements=array();
		$selector=$this->canvasSelector($callingClass);
		foreach($this->arr['Datapool\Foundation\Database']->entryIterator($selector) as $entry){
			if (strcmp($entry['Content']['Style']['text'],'&#9881;')===0){continue;}
			$elements[$entry['Content']['Style']['text']]=$entry;
		}
		ksort($elements);
		return $elements;
	}

	private function canvasElement2html($callingClass,$callingFunction,$canvasElement,$selectedCanvasElement=FALSE){
		$rowCount=FALSE;
		$style='';
		$element=array('tag'=>'div');
		// get canvas element style
		if (!empty($selectedCanvasElement['ElementId'])){
			if (strcmp($selectedCanvasElement['ElementId'],$canvasElement['ElementId'])===0){
				$canvasElement['Content']['Style']['border']='2px solid #d00';
			}
		}
		foreach($canvasElement['Content']['Style'] as $key=>$value){
			if (strcmp($key,'text')===0){
				$text=$value;
			} else if (!empty($value)){
				$style.=$key.':'.$value.';';
			}
		}
		$isEditMode=!empty($this->arr['Datapool\Tools\NetworkTools']->getClassState(__CLASS__,'isEditMode',FALSE));
		if ($isEditMode){
			$btnArr=array('tag'=>'button','value'=>'edit','Source'=>$canvasElement['Source'],'ElementId'=>$canvasElement['ElementId'],'keep-element-content'=>TRUE,'class'=>'canvas-element-btn');
			$btnArr['callingClass']=$callingClass;
			$btnArr['callingFunction']=$callingFunction;
			// canvas element select button
			$btnArr['style']='top:-5px;';
			$btnArr['key']=array('select');
			$btnArr['title']='Select';
			$btnArr['id']=md5('select'.$canvasElement['ElementId'].__FUNCTION__);
			$btnArr['element-content']='&#10022;';
			$text.=$this->arr['Datapool\Tools\HTMLbuilder']->element($btnArr);
			// canvas element delete button
			$btnArr['style']='bottom:-5px;';
			$btnArr['title']='Delete';
			$btnArr['key']=array('delete');
			$btnArr['id']=md5('delete'.$canvasElement['ElementId'].__FUNCTION__);
			$btnArr['element-content']='🗑';
			$text.=$this->arr['Datapool\Tools\HTMLbuilder']->element($btnArr);
			//
			$element['source']=$canvasElement['Source'];
			$element['element-id']=$canvasElement['ElementId'];
			$style.='cursor:pointer;';
		} else {
			if (!empty($canvasElement['Content']['Selector']['Source'])){
				// canvas element view button
				$element=$canvasElement;
				$element['key']=array('view');
				$element['id']=md5('view'.$canvasElement['ElementId'].__FUNCTION__);
				$element['tag']='button';
				$style.='z-index:5;';
				$rowCountSelector=$canvasElement['Content']['Selector'];
				if (!empty($rowCountSelector['Type'])){$rowCountSelector['Type'].='%';}
				$rowCount=$this->arr['Datapool\Foundation\Database']->getRowCount($rowCountSelector,TRUE);
			}
		}
		// canvas element
		if ($rowCount!==FALSE && strcmp($canvasElement['Content']['Style']['text'],'&#9881;')!==0){
			$elmentInfo=array('tag'=>'p','class'=>'canvas-info','element-content'=>'('.$rowCount.')');
			$text.=$this->arr['Datapool\Tools\HTMLbuilder']->element($elmentInfo);
		}
		$element['element-content']=$text;
		$element['keep-element-content']=TRUE;
		$element['callingClass']=$callingClass;
		$element['callingFunction']=$callingFunction;
		$element['style']=$style;
		$element['class']='canvas-element';
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element($element);
		return $html;
	}
	
	public function setCanvasElementPosition($arr){
		$canvasElement=array();
		if (!empty($arr['Source']) && !empty($arr['ElementId']) && !empty($arr['Content']['Style'])){
			$canvasElement=$this->arr['Datapool\Foundation\Database']->entryByKey(array('Source'=>$arr['Source'],'ElementId'=>$arr['ElementId']));
			if ($canvasElement){
				$canvasElement=$this->arr['Datapool\Tools\ArrTools']->arrMerge($canvasElement,$arr);
				$canvasElement=$this->arr['Datapool\Foundation\Database']->updateEntry($canvasElement);
			}
		}
		return $canvasElement;
	}
	
	private function getFileUpload($canvasElement){
		if (empty($canvasElement['Content']['Widgets']['File upload'])){return '';}
		// form processing
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__,TRUE);
		if (isset($formData['cmd']['uplaod'])){
			foreach($formData['files']['files'] as $fileIndex=>$fileArr){
				$entry=$canvasElement['Content']['Selector'];
				$entry['ElementId']=hash_file('md5',$fileArr["tmp_name"]);
				if (empty($entry['Folder'])){$entry['Folder']='Upload';}
				if (empty($entry['Name'])){$entry['Name']=$fileArr["name"];}
				if (!empty($entry['Type'])){$entry['Type']=trim($entry['Type'],'%');}
				$entry['file']=$fileArr;
				$entry=$this->arr['Datapool\Tools\FileTools']->fileUpload2entry($entry,FALSE);
				$entry=$this->arr['Datapool\Foundation\Access']->addRights($entry,'ALL_MEMBER_R','ALL_MEMBER_R');
				$this->arr['Datapool\Foundation\Database']->updateEntry($entry);
			}
		}
		// create html
		$html='';
		$uploadElement=array('tag'=>'input','type'=>'file','multiple'=>TRUE,'key'=>array('files'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$uploadBtn=array('tag'=>'button','value'=>'new','element-content'=>'Upload','key'=>array('uplaod'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$matrix=array();
		$matrix['upload']=array('value'=>$uploadElement);
		$matrix['cmd']=array('value'=>$uploadBtn);
		$html.=$this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'File upload'));
		return $html;
	}
	
	private function getDeleteBtn($canvasElement){
		if (empty($canvasElement['Content']['Widgets']['Delete selected entries'])){return '';}
		$deleteBtn=$canvasElement['Content']['Selector'];
		$deleteBtn['cmd']='delete all';
		$deleteBtn=$this->arr['Datapool\Tools\HTMLbuilder']->btn($deleteBtn);
		$matrix=array();
		$matrix['cmd']=array('value'=>$deleteBtn);
		return $this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Delete selected entries'));
	}
		
	private function exportImportHtml($callingClass){
		$selectors=array('dataexplorer'=>array('Source'=>'dataexplorer','Folder'=>$callingClass),
						 'mapping'=>array('Source'=>'mapentries','Folder'=>$callingClass),
						 'matching'=>array('Source'=>'matchentries','Folder'=>$callingClass),
						 'processing'=>array('Source'=>'canvasprocessing','Folder'=>$callingClass),
						 );
		$result=array();
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$this->arr['Datapool\Foundation\Database']->resetStatistic();
		if (isset($formData['cmd']['download backup'])){
			$dumpFile=$this->arr['Datapool\Tools\FileTools']->exportEntries($selectors);
			if (is_file($dumpFile)){
				header('Content-Type: application/zip');
				header('Content-Disposition: attachment; filename="'.date('Y-m-d').' canvas dump.zip"');
				header('Content-Length: '.fileSize($dumpFile));
				readfile($dumpFile);
			}	
		} else if (isset($formData['cmd']['import'])){
			$tmpFile=$this->arr['Datapool\Tools\FileTools']->getTmpDir().'tmp.zip';
			$success=move_uploaded_file($formData["files"]["import files"][0]['tmp_name'],$tmpFile);
			if ($success){
				foreach($selectors as $index=>$selector){$this->arr['Datapool\Foundation\Database']->deleteEntries($selector);}
				$this->arr['Datapool\Tools\FileTools']->importEntries($tmpFile);
			}
		}
		$btnArr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>'float:left;clear:both;margin:30px 10px 0 0;');
		$html='';
		$btnArr['cmd']='download backup';
		$html.=$this->arr['Datapool\Tools\HTMLbuilder']->btn($btnArr);
		$element=array('tag'=>'input','type'=>'file','multiple'=>TRUE,'key'=>array('import files'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>'float:left;clear:left;margin:30px 10px 0 0;');
		$html.=$this->arr['Datapool\Tools\HTMLbuilder']->element($element);
		$btnArr['cmd']='import';
		$btnArr['hasCover']=TRUE;
		$html.=$this->arr['Datapool\Tools\HTMLbuilder']->btn($btnArr);
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'&#9850;'));
		return $html;
	}



}
?>