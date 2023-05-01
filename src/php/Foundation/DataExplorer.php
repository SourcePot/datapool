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

namespace SourcePot\Datapool\Foundation;

class DataExplorer{
	
	private $arr;

	private $entryTable;
	private $entryTemplate=array();
	
	public $definition=array('Content'=>array('Style'=>array('Text'=>array('@tag'=>'input','@type'=>'Text','@default'=>''),
															'Style class'=>array('@function'=>'select','@options'=>array('canvas-std'=>'Standard','canvas-red'=>'Red','canvas-green'=>'Green','canvas-dark'=>'Dark','canvas-text'=>'Text','canvas-symbol'=>'Symbol','canvas-processor'=>'processor'),'@default'=>'canvas-std'),
															'top'=>array('@tag'=>'input','@type'=>'Text','@default'=>'0px'),
															'left'=>array('@tag'=>'input','@type'=>'Text','@default'=>'0px'),
															),
											'Selector'=>array('Source'=>array('@function'=>'select','@options'=>array()),
																'Group'=>array('@tag'=>'input','@type'=>'Text','@default'=>''),
																'Folder'=>array('@tag'=>'input','@type'=>'Text','@default'=>''),
																'Name'=>array('@tag'=>'input','@type'=>'Text','@default'=>''),
																'EntryId'=>array('@tag'=>'input','@type'=>'Text','@default'=>''),
																'Type'=>array('@tag'=>'input','@type'=>'Text','@default'=>''),
																),
											 'Widgets'=>array('Processor'=>array('@function'=>'select','@options'=>array(),'@default'=>0),
															   'File upload'=>array('@function'=>'select','@options'=>array('No','Yes'),'@default'=>0),
															   'Delete selected entries'=>array('@function'=>'select','@options'=>array('No','Yes'),'@default'=>1),
																),
											  ),
							);
    
	private $tags=array('run'=>array('tag'=>'button','element-content'=>'&#10006;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'24px','color'=>'#fff;','background-color'=>'#0a0'),'showEditMode'=>TRUE,'type'=>'Cntr','Read'=>'ALL_CONTENTADMIN_R'),
						'edit'=>array('tag'=>'button','element-content'=>'⚙','keep-element-content'=>TRUE,'style'=>array('font-size'=>'24px','color'=>'#fff','background-color'=>'#a00'),'showEditMode'=>FALSE,'type'=>'Cntr','Read'=>'ALL_CONTENTADMIN_R'),
						'&#9881;'=>array('tag'=>'button','element-content'=>'&#9881;','keep-element-content'=>TRUE,'class'=>'canvas-processor','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R','title'=>'Step processing'),
						'&#128337;'=>array('tag'=>'button','element-content'=>'&#128337;','keep-element-content'=>TRUE,'class'=>'canvas-trigger','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R','title'=>'Trigger'),
						'Select'=>array('tag'=>'button','element-content'=>'Select','keep-element-content'=>TRUE,'class'=>'canvas-std','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R'),
						'Text'=>array('tag'=>'div','element-content'=>'Text','keep-element-content'=>TRUE,'class'=>'canvas-text','showEditMode'=>TRUE,'type'=>'Elements','Read'=>'ALL_CONTENTADMIN_R'),
						'&larr;'=>array('tag'=>'div','element-content'=>'&larr;','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&uarr;'=>array('tag'=>'div','element-content'=>'&uarr;','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&rarr;'=>array('tag'=>'div','element-content'=>'&rarr;','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&darr;'=>array('tag'=>'div','element-content'=>'&darr;','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&harr;'=>array('tag'=>'div','element-content'=>'&harr;','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&varr;'=>array('tag'=>'div','element-content'=>'&varr;','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&nwarr;'=>array('tag'=>'div','element-content'=>'&nwarr;','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&nearr;'=>array('tag'=>'div','element-content'=>'&nearr;','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&searr;'=>array('tag'=>'div','element-content'=>'&searr;','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&swarr;'=>array('tag'=>'div','element-content'=>'&swarr;','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&#10137'=>array('tag'=>'div','element-content'=>'&#10137','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&#10154'=>array('tag'=>'div','element-content'=>'&#10154','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'&#10140'=>array('tag'=>'div','element-content'=>'&#10140','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Connectors','Read'=>'ALL_CONTENTADMIN_R'),
						'|'=>array('tag'=>'div','element-content'=>'|','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Misc','Read'=>'ALL_CONTENTADMIN_R'),
						'&#9601'=>array('tag'=>'div','element-content'=>'&#9601','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Misc','Read'=>'ALL_CONTENTADMIN_R'),
						'&#9675'=>array('tag'=>'div','element-content'=>'&#9675','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Misc','Read'=>'ALL_CONTENTADMIN_R'),
						'&#9679'=>array('tag'=>'div','element-content'=>'&#9679','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Misc','Read'=>'ALL_CONTENTADMIN_R'),
						'&#9711'=>array('tag'=>'div','element-content'=>'&#9711','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Misc','Read'=>'ALL_CONTENTADMIN_R'),
						'&#9476'=>array('tag'=>'div','element-content'=>'&#9476','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Misc','Read'=>'ALL_CONTENTADMIN_R'),
						'&#9482'=>array('tag'=>'div','element-content'=>'&#9482','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Misc','Read'=>'ALL_CONTENTADMIN_R'),
						'&#9552'=>array('tag'=>'div','element-content'=>'&#9552','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Misc','Read'=>'ALL_CONTENTADMIN_R'),
						'&#9553'=>array('tag'=>'div','element-content'=>'&#9553','keep-element-content'=>TRUE,'class'=>'canvas-symbol','showEditMode'=>TRUE,'type'=>'Misc','Read'=>'ALL_CONTENTADMIN_R'),
						);
	
	private $processorOptions=array();
	
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		$this->completeDefintion();
		//$this->canvasElementsVersionUpdate();
		return $this->arr;
	}
	
	private function canvasElementsVersionUpdate(){
		$selector=array('Source'=>$this->entryTable,'Group'=>'Canvas elements','Type'=>'dataexplorer');
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($selector) as $entry){
			if (!isset($entry['Content']['Style']['Text'])){continue;}
			$oldEntry=$entry;
			/*
			$entry['Content']['Style']=array('Text'=>$entry['Content']['Style']['Text'],
											 'Style class'=>empty($entry['Content']['Selector']['Source'])?'canvas-symbol':'canvas-std',
											 'top'=>$entry['Content']['Style']['top'],
											 'left'=>$entry['Content']['Style']['left']
											 );
			*/
			if (!empty($entry['Content']['Selector']['Source'])){
				$entry['Content']['Style']['Style class']='canvas-std';
			} else if (mb_strlen($entry['Content']['Style']['Text'])>1){
				$entry['Content']['Style']['Style class']='canvas-text';
			} else {
				$entry['Content']['Style']['Style class']='canvas-symbol';
			}
			//$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file(array('old'=>$oldEntry['Content'],'new'=>$entry['Content']));
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
		}
	}
	
	public function getEntryTable(){return $this->entryTable;}

	public function getEntryTemplate(){return $this->entryTemplate;}
	
	private function completeDefintion(){
		// add Source selector
		$sourceOptions=array(''=>'&larrhk;');
		$dbInfo=$this->arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplate();
		foreach($dbInfo as $Source=>$entryTemplate){$sourceOptions[$Source]=$Source;}
		$functionOptions=array(''=>'&larrhk;');
		$this->definition['Content']['Selector']['Source']['@options']=$sourceOptions;
		// add data processors
		$this->processorOptions=array(''=>'&larrhk;');
		foreach($this->arr['registered methods']['dataProcessor'] as $classWithNamespace=>$defArr){
			$label=$this->arr['class2source'][$classWithNamespace];
			$this->processorOptions[$classWithNamespace]=ucfirst($label);
		}
		$this->definition['Content']['Widgets']['Processor']['@options']=$this->processorOptions;
		// add save button
		$this->definition['save']=array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save');
		$this->arr['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
	}

	public function unifyEntry($entry){
		if (!empty($entry['class'])){$entry['Content']['Style']['Style class']=$entry['class'];}
		if (!empty($entry['element-content'])){
			$entry['Name']=$entry['element-content'];
			$entry['Content']['Style']['Text']=$entry['element-content'];
			if (strpos($entry['element-content'],'&#9881;')!==FALSE){
				$entry['Content']['Selector']['Source']=$this->arr[$entry['Folder']]->getEntryTable();
				$entry['Content']['Widgets']['Processor']='SourcePot\Datapool\Processing\CanvasProcessing';
			}
			if (strpos($entry['element-content'],'&#128337;')!==FALSE){
				$entry['Content']['Selector']['Source']=$this->arr[$entry['Folder']]->getEntryTable();
				$entry['Content']['Widgets']['Processor']='SourcePot\Datapool\Processing\CanvasTrigger';
			}

		}
		$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_MEMBER_R','ALL_CONTENTADMIN_R');
		$entry=$this->arr['SourcePot\Datapool\Foundation\Definitions']->definition2entry($this->definition,$entry);
		return $entry;
	}

	public function getDataExplorer($callingClass){
		$return=array('Content'=>array('Selector'=>array(),'Widgets'=>array()),'selector'=>array());
		// get explorer html
		$canvasElement=$this->canvasFormProcessing($callingClass);
		//if (!empty($canvasElement)){$return=$canvasElement['Content'];}
		$cntrHtml=$this->getCntrHtml($callingClass);
		$canvasHtml=$this->getCanvas($callingClass);
		$articleArr=array('tag'=>'article','class'=>'explorer','element-content'=>$canvasHtml.$cntrHtml,'keep-element-content'=>TRUE,'style'=>array());
		$return['explorerHtml']=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($articleArr);
		// get content html
		if (isset($canvasElement['Content']['Selector'])){$return['selector']=$canvasElement['Content']['Selector'];}
		$return['contentHtml']='';
		if (!empty($canvasElement['Content']['Widgets']["Processor"])){
			$return['contentHtml'].=$this->arr[$canvasElement['Content']['Widgets']["Processor"]]->dataProcessor($canvasElement,'settings');
		}
     	return $return;
	}
	
	private function canvasFormProcessing($callingClass){
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,'getCanvas');
		if (isset($formData['cmd']['select'])){
			$canvasElement=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'selectedCanvasElement',$formData['element']);
		} else if (isset($formData['cmd']['delete'])){
			$this->arr['SourcePot\Datapool\Foundation\Database']->deleteEntries($formData['element']);
			$canvasElement=array();
		} else if (isset($formData['cmd']['view'])){
			$canvasElement=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'selectedCanvasElement',$formData['element']);
			$selector=$canvasElement['Content']['Selector'];
			if (isset($this->arr['source2class'][$selector['Source']])){
				$classWithNamespace=$this->arr['source2class'][$selector['Source']];
				$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageState($classWithNamespace,$selector);
			}
		} else {
			$canvasElement=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'selectedCanvasElement');	
		}
		return $canvasElement;
	}
	
	private function getCanvas($callingClass){
		// create html
		$selectedCanvasElement=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'selectedCanvasElement');
		$html='';
		$selector=array('Source'=>$this->entryTable,'Group'=>'Canvas elements','Folder'=>$callingClass,'Type'=>'dataexplorer');
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($selector) as $entry){
			$html.=$this->canvasElement2html(__CLASS__,__FUNCTION__,$entry,$selectedCanvasElement);
		}
		$html='<div id="canvas">'.$html.'</div>';
		return $html;
	}
	
	private function getCntrHtml($callingClass){
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__,TRUE);
		if (isset($formData['cmd']['run'])){
			$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'isEditMode',FALSE);
		} else if (isset($formData['cmd']['edit'])){
			$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'isEditMode',TRUE);
		} else if (!empty($formData['cmd'])){
			$entry=array('Source'=>$this->entryTable,'Group'=>'Canvas elements','Folder'=>$callingClass,'Type'=>'dataexplorer');
			$entry=array_merge($this->tags[key($formData['cmd'])],$entry);
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->unifyEntry($entry);	
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
		}
		// build control html
		$isEditMode=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'isEditMode',FALSE);
		$isEditMode=$this->arr['SourcePot\Datapool\Foundation\Access']->accessSpecificValue('ALL_CONTENTADMIN_R',$isEditMode,FALSE);
		if (!$this->arr['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){$isEditMode=FALSE;}
		$matrix=array();
		foreach($this->tags as $key=>$tag){
			if ($this->arr['SourcePot\Datapool\Foundation\Access']->accessSpecificValue('ALL_CONTENTADMIN_R',FALSE,TRUE)){continue;}
			if ($tag['showEditMode']!==$isEditMode){continue;}
			$btn=$tag;
			$btnTemplate=array('tag'=>'button','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>array($key),'style'=>array('position'=>'relative','padding'=>'2px','margin'=>'5px','line-height'=>'35px'));
			$btn=array_replace_recursive($btn,$btnTemplate);
			if (!isset($matrix[$tag['type']]['Btn'])){$matrix[$tag['type']]['Btn']='';}
			$matrix[$tag['type']]['Btn'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($btn);
		}
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'hideHeader'=>TRUE,'caption'=>'Canvas elements'));
		$selectedCanvasElement=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'selectedCanvasElement');
		$canvasElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($selectedCanvasElement);
		if ($isEditMode){
			if ($canvasElement){
				$html.=$this->arr['SourcePot\Datapool\Foundation\Definitions']->entry2form($canvasElement);
			}
		} else {
			$html.=$this->getFileUpload($canvasElement);
			$html.=$this->getDeleteBtn($canvasElement);
			if (!empty($canvasElement['Content']['Widgets']["Processor"])){
				$html.=$this->arr[$canvasElement['Content']['Widgets']["Processor"]]->dataProcessor($canvasElement,'widget');
			}
			$html.=$this->exportImportHtml($callingClass);
		}
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
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($selector) as $entry){
			if (strcmp($entry['Content']['Style']['Text'],'&#9881;')===0 || strcmp($entry['Content']['Style']['Text'],'&#128337;')===0){continue;}
			$elements[$entry['Content']['Style']['Text']]=$entry;
		}
		ksort($elements);
		return $elements;
	}
	
	public function entryId2selector($entryId){
		$selector=array('Source'=>$this->entryTable,'EntryId'=>$entryId);
		$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($selector,TRUE);
		if (isset($entry['Content']['Selector'])){
			$selector=array();
			foreach($entry['Content']['Selector'] as $key=>$value){
				if (empty($value)){continue;}
				$selector[$key]=$value;
			}
			krsort($selector);
			return $selector;
		} else {
			return array();
		}
	}

	private function canvasElement2html($callingClass,$callingFunction,$canvasElement,$selectedCanvasElement=FALSE){
		$rowCount=FALSE;
		$element=array('tag'=>'div');
		// get canvas element style
		$style=array('left'=>$canvasElement['Content']['Style']['left'],'top'=>$canvasElement['Content']['Style']['top']);
		if (!empty($selectedCanvasElement['EntryId'])){
			if (strcmp($selectedCanvasElement['EntryId'],$canvasElement['EntryId'])===0){
				$style['border']='3px solid #d00';
			}
		}
		$text=$canvasElement['Content']['Style']['Text'];
		$isEditMode=!empty($this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'isEditMode',FALSE));
		$isEditMode=$this->arr['SourcePot\Datapool\Foundation\Access']->accessSpecificValue('ALL_CONTENTADMIN_R',$isEditMode,FALSE);
		if ($isEditMode){
			$btnArr=array('tag'=>'button','value'=>'edit','Source'=>$canvasElement['Source'],'EntryId'=>$canvasElement['EntryId'],'keep-element-content'=>TRUE,'class'=>'canvas-element-btn');
			$btnArr['callingClass']=$callingClass;
			$btnArr['callingFunction']=$callingFunction;
			// canvas element select button
			$btnArr['style']=array('top'=>'-5px');
			$btnArr['key']=array('select');
			$btnArr['title']='Select';
			$btnArr['id']=md5('select'.$canvasElement['EntryId'].__FUNCTION__);
			$btnArr['element-content']='&#10022;';
			$text.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr);
			// canvas element delete button
			$btnArr['style']=array('bottom'=>'-5px');
			$btnArr['title']='Delete';
			$btnArr['key']=array('delete');
			$btnArr['id']=md5('delete'.$canvasElement['EntryId'].__FUNCTION__);
			$btnArr['element-content']='🗑';
			$text.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr);
			//
			$element['source']=$canvasElement['Source'];
			$element['entry-id']=$canvasElement['EntryId'];
			$style['cursor']='pointer';
		} else {
			if (!empty($canvasElement['Content']['Selector']['Source'])){
				// canvas element view button
				$element=$canvasElement;
				$element['key']=array('view');
				$element['id']=md5('view'.$canvasElement['EntryId'].__FUNCTION__);
				$element['tag']='button';
				$style['z-index']='5';
				$style['box-sizing']='content-box';
				$rowCountSelector=$canvasElement['Content']['Selector'];
				if (!empty($rowCountSelector['Type'])){$rowCountSelector['Type'].='%';}
				$rowCount=$this->arr['SourcePot\Datapool\Foundation\Database']->getRowCount($rowCountSelector,TRUE);
			}
		}
		// canvas element
		if ($rowCount!==FALSE && strcmp($canvasElement['Content']['Style']['Text'],'&#9881;')!==0 && strcmp($canvasElement['Content']['Style']['Text'],'&#128337;')!==0){
			$elmentInfo=array('tag'=>'p','class'=>'canvas-info','element-content'=>'('.$rowCount.')');
			$text.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($elmentInfo);
		}
		$element['class']=$canvasElement['Content']['Style']['Style class'];
		$element['element-content']=$text;
		$element['keep-element-content']=TRUE;
		$element['callingClass']=$callingClass;
		$element['callingFunction']=$callingFunction;
		$element['style']=$style;
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
		return $html;
	}
	
	public function setCanvasElementPosition($arr){
		$canvasElement=array();
		if (!empty($arr['Source']) && !empty($arr['EntryId']) && !empty($arr['Content']['Style'])){
			$canvasElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById(array('Source'=>$arr['Source'],'EntryId'=>$arr['EntryId']));
			if ($canvasElement){
				$canvasElement=array_replace_recursive($canvasElement,$arr);
				$canvasElement=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($canvasElement);
			}
		}
		return $canvasElement;
	}
	
	private function getFileUpload($canvasElement){
		if (empty($canvasElement['Content']['Widgets']['File upload'])){return '';}
		// form processing
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__,TRUE);
		if (isset($formData['cmd']['uplaod'])){
			foreach($formData['files']['files'] as $fileIndex=>$fileArr){
				$entry=$canvasElement['Content']['Selector'];
				$entry['EntryId']=hash_file('md5',$fileArr["tmp_name"]);
				if (empty($entry['Folder'])){$entry['Folder']='Upload';}
				if (empty($entry['Name'])){$entry['Name']=$fileArr["name"];}
				if (!empty($entry['Type'])){$entry['Type']=trim($entry['Type'],'%');}
				$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_MEMBER_R','ALL_MEMBER_R');	
				$entry=$this->arr['SourcePot\Datapool\Foundation\Filespace']->file2entries($fileArr,$entry);
			}
		}
		// create html
		$html='';
		$uploadElement=array('tag'=>'input','type'=>'file','multiple'=>TRUE,'key'=>array('files'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$uploadBtn=array('tag'=>'button','value'=>'new','element-content'=>'Upload','key'=>array('uplaod'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$matrix=array();
		$matrix['upload']=array('value'=>$uploadElement);
		$matrix['cmd']=array('value'=>$uploadBtn);
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'File upload'));
		return $html;
	}
	
	private function getDeleteBtn($canvasElement){
		if (empty($canvasElement['Content']['Widgets']['Delete selected entries'])){return '';}
		$deleteBtn=$canvasElement['Content']['Selector'];
		$deleteBtn['cmd']='delete all';
		$deleteBtn=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($deleteBtn);
		$matrix=array();
		$matrix['cmd']=array('value'=>$deleteBtn);
		return $this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Delete selected entries'));
	}
		
	private function exportImportHtml($callingClass){
		if (!$this->arr['SourcePot\Datapool\Foundation\Access']->accessSpecificValue('ALL_CONTENTADMIN_R')){return '';}
		//
		$selectors=array('dataexplorer'=>array('Source'=>'dataexplorer','Folder'=>$callingClass));
		foreach($this->arr['registered methods']['dataProcessor'] as $classWithNamespace=>$ret){
			$source=$this->arr['class2source'][$classWithNamespace];
			$selectors[$source]=array('Source'=>$source,'Folder'=>$callingClass);
		}
		$callingClassName=substr($callingClass,strrpos($callingClass,'\\')+1);
		$className=substr(__CLASS__,strrpos(__CLASS__,'\\')+1);
		$result=array();
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$this->arr['SourcePot\Datapool\Foundation\Database']->resetStatistic();
		if (isset($formData['cmd']['download backup'])){
			$dumpFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->exportEntries($selectors);
			if (is_file($dumpFile)){
				header('Content-Type: application/zip');
				header('Content-Disposition: attachment; filename="'.date('Y-m-d').' '.$className.' '.$callingClassName.' dump.zip"');
				header('Content-Length: '.fileSize($dumpFile));
				readfile($dumpFile);
			}	
		} else if (isset($formData['cmd']['import'])){
			$tmpFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->getTmpDir().'tmp.zip';
			$success=move_uploaded_file($formData["files"]["import files"][0]['tmp_name'],$tmpFile);
			if ($success){
				foreach($selectors as $index=>$selector){$this->arr['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector);}
				$this->arr['SourcePot\Datapool\Foundation\Filespace']->importEntries($tmpFile);
			}
		}
		$btnArr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>'float:left;clear:both;margin:30px 10px 0 0;');
		$html='';
		$btnArr['cmd']='download backup';
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
		$element=array('tag'=>'input','type'=>'file','multiple'=>TRUE,'key'=>array('import files'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>'float:left;clear:left;margin:30px 10px 0 0;');
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
		$btnArr['cmd']='import';
		$btnArr['hasCover']=TRUE;
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'&#9850;'));
		return $html;
	}

}
?>