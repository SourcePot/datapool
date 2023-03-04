<?php
declare(strict_types=1);

namespace Datapool\Processing;

class CanvasProcessing{
	
	private $arr;

	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );
	
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		return $this->arr;
	}

	public function job($vars){
		return $vars;
	}

	public function getEntryTable(){return $this->entryTable;}
	
	public function dataProcessor($action='info',$callingElementSelector=array()){
		// This method is the interface of this data processing class
		// The Argument $action selects the method to be invoked and
		// argument $callingElementSelector$ provides the entry which triggerd the action.
		// $callingElementSelector ... array('Source'=>'...', 'ElementId'=>'...', ...)
		// If the requested action does not exist the method returns FALSE and 
		// TRUE, a value or an array otherwise.
		$callingElement=$this->arr['Datapool\Foundation\Database']->entryByKey($callingElementSelector);
		switch($action){
			case 'run':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runCanvasProcessing($callingElement,$testRunOnly=FALSE);
				}
				break;
			case 'test':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runCanvasProcessing($callingElement,$testRunOnly=TRUE);
				}
				break;
			case 'widget':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getCanvasProcessingWidget($callingElement);
				}
				break;
			case 'settings':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getCanvasProcessingSettings($callingElement);
				}
				break;
			case 'info':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getCanvasProcessingInfo($callingElement);
				}
				break;
		}
		return FALSE;
	}

	private function getCanvasProcessingWidget($callingElement){
		return $this->arr['Datapool\Foundation\Container']->container('Canvas processing','generic',$callingElement,array('method'=>'getCanvasProcessingWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getCanvasProcessingWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=array();
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['run'])){
			$result=$this->runCanvasProcessing($arr['selector'],FALSE);
		} else if (isset($formData['cmd']['test'])){
			$result=$this->runCanvasProcessing($arr['selector'],TRUE);
		}
		// build html
		$btnArr=array('tag'=>'input','type'=>'submit','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$matrix=array();
		$btnArr['value']='Test';
		$btnArr['key']=array('test');
		$matrix['Commands']['Test']=$btnArr;
		$btnArr['value']='Run';
		$btnArr['key']=array('run');
		$matrix['Commands']['Run']=$btnArr;
		$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'CanvasProcessing widget'));
		foreach($result as $caption=>$matrix){
			$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		return $arr;
	}

	private function getCanvasProcessingSettings($callingElement){
		$html='';
		if ($this->arr['Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->arr['Datapool\Foundation\Container']->container('CanvasProcessing entries settings','generic',$callingElement,array('method'=>'getCanvasProcessingSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getCanvasProcessingSettingsHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'h1','element-content'=>'Convas processor parameters'));
		$arr['html'].=$this->canvasProcessingParams($arr['selector']);
		$arr['html'].=$this->canvasProcessingRules($arr['selector']);
		//$selectorMatrix=$this->arr['Datapool\Tools\ArrTools']->arr2matrix($callingElement['Content']['Selector']);
		//$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for CanvasProcessing'));
		return $arr;
	}
	
	private function canvasProcessingParams($callingElement){
		$return=array('html'=>'','Parameter'=>array(),'result'=>array());
		if (!isset($callingElement['Content']['Selector']['Source'])){return $html;}
		$contentStructure=array('Column to match'=>array('htmlBuilderMethod'=>'keySelect','standardColumsOnly'=>TRUE),
							  'Match with'=>array('htmlBuilderMethod'=>'canvasElementSelect'),
							  'Match failure'=>array('htmlBuilderMethod'=>'canvasElementSelect'),
							  'Match success'=>array('htmlBuilderMethod'=>'canvasElementSelect'),
							  );
		$contentStructure['Column to match']+=$callingElement['Content']['Selector'];
		// get selctorB
		$canvasProcessingParams=$this->callingElement2selector(__FUNCTION__,$callingElement,TRUE);
		$canvasProcessingParams=$this->arr['Datapool\Foundation\Access']->addRights($canvasProcessingParams,'ALL_R','ALL_CONTENTADMIN_R');
		$canvasProcessingParams['Content']=array('Column to match'=>'Name');
		$canvasProcessingParams=$this->arr['Datapool\Foundation\Database']->entryByKeyCreateIfMissing($canvasProcessingParams,TRUE);
		// form processing
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$elementId=key($formData['val']);
		if (!empty($formData['val'][$elementId]['Content'])){
			$canvasProcessingParams['Content']=$formData['val'][$elementId]['Content'];
			$canvasProcessingParams=$this->arr['Datapool\Foundation\Database']->updateEntry($canvasProcessingParams);
		}
		// get HTML
		$arr=$canvasProcessingParams;
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Choose the column to be used for canvasProcessing, the entries you want to match with and success/failure targets';
		$arr['noBtns']=TRUE;
		$matrix=array('Parameter'=>$this->arr['Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE));
		return $this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
	}

	private function canvasProcessingRules($callingElement){
		$contentStructure=array('Process'=>array('htmlBuilderMethod'=>'canvasElementSelect','excontainer'=>TRUE),
							   );
		if (!isset($callingElement['Content']['Selector']['Source'])){return $html;}
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Processing steps (attached data processing will be triggered)';
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}
	
	public function runCanvasProcessingOnClass($class,$isTestRun=TRUE){
		$canvasElementsSelector=array('Source'=>$this->arr['Datapool\Foundation\DataExplorer']->getEntryTable(),'Group'=>'Canvas elements');
		foreach($this->arr['Datapool\Foundation\Database']->entryIterator($canvasElementsSelector,TRUE,'Read','ElementId',TRUE) as $canvasElement){
			if (empty($canvasElement['Content']['Widgets']['Processor'])){continue;}
			if (strpos($canvasElement['Content']['Widgets']['Processor'],'Datapool\Processing\CanvasProcessing')===FALSE){continue;}
			$this->runCanvasProcessing($canvasElement,$isTestRun);
		}
	}
	
	public function runCanvasProcessing($callingElement,$isTestRun=TRUE){
		// get job processing canvas elements
		$canvasElements=$this->arr['Datapool\AdminApps\Settings']->getSetting(__CLASS__,__FUNCTION__,array(),'Processing steps',TRUE);
		if (empty($canvasElements)){
			$canvasElements=$this->getCanvasElements($callingElement);
		}
		$currentCanvasElement=array_shift($canvasElements);
		$this->arr['Datapool\AdminApps\Settings']->setSetting(__CLASS__,__FUNCTION__,$canvasElements,'Processing steps',TRUE);
		$targetCanvasElement=array('Source'=>$this->arr['Datapool\Foundation\DataExplorer']->getEntryTable(),'ElementId'=>$currentCanvasElement['Content']['Process']);
		$targetCanvasElement=$this->arr['Datapool\Foundation\Database']->entryByKey($targetCanvasElement,TRUE);
		if ($targetCanvasElement){
			$processor=$targetCanvasElement['Content']['Widgets']['Processor'];
			$result=$this->arr[$processor]->dataProcessor($isTestRun?'test':'run',$targetCanvasElement);
		}
		$result['Canvas processing']['Stack']['value']=count($canvasElements);
		return $result;
	}
	
	public function callingElement2selector($callingFunction,$callingElement,$selectsUniqueEntry=FALSE){
		if (!isset($callingElement['Folder']) || !isset($callingElement['ElementId'])){return array();}
		$type=$this->arr['Datapool\Foundation\Database']->class2source(__CLASS__,TRUE);
		$type.='|'.$callingFunction;
		$entrySelector=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['ElementId'],'Type'=>strtolower($type));
		if ($selectsUniqueEntry){$entrySelector=$this->arr['Datapool\Tools\StrTools']->addElementId($entrySelector,array('Group','Folder','Name','Type'),0);}
		return $entrySelector;
	}

	private function getCanvasElements($callingElement,$group='canvasProcessingRules'){
		$canvasElements=array();
		$canvasElementsSelector=array('Source'=>$this->entryTable,'Group'=>$group,'Folder'=>$callingElement['Folder']);
		foreach($this->arr['Datapool\Foundation\Database']->entryIterator($canvasElementsSelector,TRUE,'Read','ElementId',TRUE) as $entry){
			$canvasElements[$entry['ElementId']]=$entry;
		}
		return $canvasElements;
	}

}
?>