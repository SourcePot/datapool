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
	
	public $definition=array('Content'=>array('Style'=>array('text'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
															'font-size'=>array('@tag'=>'input','@type'=>'text','@default'=>'25px'),
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
																'EntryId'=>array('@tag'=>'input','@type'=>'text','@default'=>''),
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
    
	private $tags=array('run'=>array('tag'=>'button','element-content'=>'&#10006;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'24px','color'=>'#fff;','background-color'=>'#0a0'),'showEditMode'=>TRUE,'type'=>'Cntr'),
						'edit'=>array('tag'=>'button','element-content'=>'⚙','keep-element-content'=>TRUE,'style'=>array('font-size'=>'24px','color'=>'#fff','background-color'=>'#a00'),'showEditMode'=>FALSE,'type'=>'Cntr'),
						'&#9881;'=>array('tag'=>'button','element-content'=>'&#9881;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'16px','color'=>'#fff','background-color'=>'#20f'),'showEditMode'=>TRUE,'type'=>'Cntr'),
						'Select'=>array('tag'=>'button','element-content'=>'Select','keep-element-content'=>TRUE,'style'=>array('font-size'=>'17px','color'=>'#000','background-color'=>'#fff','min-width'=>'150px','text-align'=>'center'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&larr;'=>array('tag'=>'div','element-content'=>'&larr;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&uarr;'=>array('tag'=>'div','element-content'=>'&uarr;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&rarr;'=>array('tag'=>'div','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&darr;'=>array('tag'=>'div','element-content'=>'&darr;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&harr;'=>array('tag'=>'div','element-content'=>'&harr;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&varr;'=>array('tag'=>'div','element-content'=>'&varr;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&nwarr;'=>array('tag'=>'div','element-content'=>'&nwarr;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&nearr;'=>array('tag'=>'div','element-content'=>'&nearr;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&searr;'=>array('tag'=>'div','element-content'=>'&searr;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&swarr;'=>array('tag'=>'div','element-content'=>'&swarr;','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#10137'=>array('tag'=>'div','element-content'=>'&#10137','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#10154'=>array('tag'=>'div','element-content'=>'&#10154','keep-element-content'=>TRUE,'style'=>array('font-size'=>'30px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#10140'=>array('tag'=>'div','element-content'=>'&#10140','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#10141'=>array('tag'=>'div','element-content'=>'&#10141','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#9615'=>array('tag'=>'div','element-content'=>'&#9615','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#9601'=>array('tag'=>'div','element-content'=>'&#9601','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#9675'=>array('tag'=>'div','element-content'=>'&#9675','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#9679'=>array('tag'=>'div','element-content'=>'&#9679','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#9711'=>array('tag'=>'div','element-content'=>'&#9711','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#9476'=>array('tag'=>'div','element-content'=>'&#9476','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#9482'=>array('tag'=>'div','element-content'=>'&#9482','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#9552'=>array('tag'=>'div','element-content'=>'&#9552','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
						'&#9553'=>array('tag'=>'div','element-content'=>'&#9553','keep-element-content'=>TRUE,'style'=>array('font-size'=>'20px'),'showEditMode'=>TRUE,'type'=>'Add'),
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
		return $this->arr;
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
			$label=$this->arr['SourcePot\Datapool\Foundation\Database']->class2source($classWithNamespace,TRUE,TRUE);
			$this->processorOptions[$classWithNamespace]=$label;
		}
		$this->definition['Content']['Widgets']['Processor']['@options']=$this->processorOptions;
		// add save button
		$this->definition['save']=array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save');
		$this->arr['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
	}

	public function unifyEntry($entry){
		if (!empty($entry['style'])){$entry['Content']['Style']=$entry['style'];}
		if (!empty($entry['element-content'])){
			$entry['Name']=$entry['element-content'];
			$entry['Content']['Style']['text']=$entry['element-content'];
			if (strpos($entry['element-content'],'&#9881;')!==FALSE){
				$entry['Content']['Selector']['Source']=$this->arr[$entry['Folder']]->getEntryTable();
				$entry['Content']['Widgets']['Processor']='SourcePot\Datapool\Processing\CanvasProcessing';
			}
		}
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
			$return['contentHtml'].=$this->arr[$canvasElement['Content']['Widgets']["Processor"]]->dataProcessor('settings',$canvasElement);
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
		$isEditMode=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'isEditMode',FALSE);
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
		if (!$this->arr['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){$isEditMode=FALSE;}
		$matrix=array();
		foreach($this->tags as $key=>$tag){
			if ($tag['showEditMode']!==$isEditMode){continue;}
			$btn=$tag;
			$btnTemplate=array('tag'=>'button','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>array($key),'style'=>array('padding'=>'2px'));
			$btn=array_replace_recursive($btn,$btnTemplate);
			if (!isset($matrix[$tag['type']]['Btn'])){$matrix[$tag['type']]['Btn']='';}
			$matrix[$tag['type']]['Btn'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($btn);
		}
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'hideHeader'=>TRUE,'caption'=>'Canvas elements'));
		$selectedCanvasElement=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'selectedCanvasElement');
		$canvasElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($selectedCanvasElement);
		if ($isEditMode){
			if ($canvasElement){
				$definition=$this->arr['SourcePot\Datapool\Foundation\Definitions']->getDefinition($canvasElement);
				$html.=$this->arr['SourcePot\Datapool\Foundation\Definitions']->definition2form($definition,$canvasElement);
			}
		} else {
			$html.=$this->getFileUpload($canvasElement);
			$html.=$this->getDeleteBtn($canvasElement);
			$html.=$this->exportImportHtml($callingClass);
			if (!empty($canvasElement['Content']['Widgets']["Processor"])){
				$html.=$this->arr[$canvasElement['Content']['Widgets']["Processor"]]->dataProcessor('widget',$canvasElement);
			}
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
			if (strcmp($entry['Content']['Style']['text'],'&#9881;')===0){continue;}
			$elements[$entry['Content']['Style']['text']]=$entry;
		}
		ksort($elements);
		return $elements;
	}
	
	public function entryId2selector($entryId){
		$selector=array('Source'=>$this->entryTable,'EntryId'=>$entryId);
		$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($selector);
		if (isset($entry['Content']['Selector'])){
			$selector=array();
			foreach($entry['Content']['Selector'] as $key=>$value){
				if (empty($value)){continue;}
				$selector[$key]=$value;
			}
			return $selector;
		} else {
			return array();
		}
	}

	private function canvasElement2html($callingClass,$callingFunction,$canvasElement,$selectedCanvasElement=FALSE){
		$rowCount=FALSE;
		$style='';
		$element=array('tag'=>'div');
		// get canvas element style
		if (!empty($selectedCanvasElement['EntryId'])){
			if (strcmp($selectedCanvasElement['EntryId'],$canvasElement['EntryId'])===0){
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
		$isEditMode=!empty($this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'isEditMode',FALSE));
		if ($isEditMode){
			$btnArr=array('tag'=>'button','value'=>'edit','Source'=>$canvasElement['Source'],'EntryId'=>$canvasElement['EntryId'],'keep-element-content'=>TRUE,'class'=>'canvas-element-btn');
			$btnArr['callingClass']=$callingClass;
			$btnArr['callingFunction']=$callingFunction;
			// canvas element select button
			$btnArr['style']='top:-5px;';
			$btnArr['key']=array('select');
			$btnArr['title']='Select';
			$btnArr['id']=md5('select'.$canvasElement['EntryId'].__FUNCTION__);
			$btnArr['element-content']='&#10022;';
			$text.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr);
			// canvas element delete button
			$btnArr['style']='bottom:-5px;';
			$btnArr['title']='Delete';
			$btnArr['key']=array('delete');
			$btnArr['id']=md5('delete'.$canvasElement['EntryId'].__FUNCTION__);
			$btnArr['element-content']='🗑';
			$text.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr);
			//
			$element['source']=$canvasElement['Source'];
			$element['entry-id']=$canvasElement['EntryId'];
			$style.='cursor:pointer;';
		} else {
			if (!empty($canvasElement['Content']['Selector']['Source'])){
				// canvas element view button
				$element=$canvasElement;
				$element['key']=array('view');
				$element['id']=md5('view'.$canvasElement['EntryId'].__FUNCTION__);
				$element['tag']='button';
				$style.='z-index:5;';
				$rowCountSelector=$canvasElement['Content']['Selector'];
				if (!empty($rowCountSelector['Type'])){$rowCountSelector['Type'].='%';}
				$rowCount=$this->arr['SourcePot\Datapool\Foundation\Database']->getRowCount($rowCountSelector,TRUE);
			}
		}
		// canvas element
		if ($rowCount!==FALSE && strcmp($canvasElement['Content']['Style']['text'],'&#9881;')!==0){
			$elmentInfo=array('tag'=>'p','class'=>'canvas-info','element-content'=>'('.$rowCount.')');
			$text.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($elmentInfo);
		}
		$element['element-content']=$text;
		$element['keep-element-content']=TRUE;
		$element['callingClass']=$callingClass;
		$element['callingFunction']=$callingFunction;
		$element['style']=$style;
		$element['class']='canvas-element';
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
		$selectors=array('dataexplorer'=>array('Source'=>'dataexplorer','Folder'=>$callingClass),
						 'mapping'=>array('Source'=>'mapentries','Folder'=>$callingClass),
						 'matching'=>array('Source'=>'matchentries','Folder'=>$callingClass),
						 'parser'=>array('Source'=>'parseentries','Folder'=>$callingClass),
						 'processing'=>array('Source'=>'canvasprocessing','Folder'=>$callingClass),
						 );
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