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

namespace SourcePot\Datapool\Processing;

class CanvasProcessing{
	
	private $arr;
	
	private $vars=array();

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
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		return $this->arr;
	}

	public function getEntryTable(){return $this->entryTable;}
	
	public function dataProcessor($action='info',$callingElementSelector=array(),$vars=array()){
		// This method is the interface of this data processing class
		// The Argument $action selects the method to be invoked and
		// argument $callingElementSelector$ provides the entry which triggerd the action.
		// $callingElementSelector ... array('Source'=>'...', 'EntryId'=>'...', ...)
		// If the requested action does not exist the method returns FALSE and 
		// TRUE, a value or an array otherwise.
		$this->vars=$vars;
		$callingElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector);
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
		return $this->arr['SourcePot\Datapool\Foundation\Container']->container('Canvas processing','generic',$callingElement,array('method'=>'getCanvasProcessingWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getCanvasProcessingWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=$this->vars;
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
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
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'CanvasProcessing widget'));
		foreach($result as $caption=>$matrix){
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		$arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
		return $arr;
	}

	private function getCanvasProcessingSettings($callingElement){
		$html='';
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('CanvasProcessing entries settings','generic',$callingElement,array('method'=>'getCanvasProcessingSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getCanvasProcessingSettingsHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$arr['html'].=$this->canvasProcessingRules($arr['selector']);
		//$selectorMatrix=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
		//$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for CanvasProcessing'));
		return $arr;
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
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}
	
	public function runCanvasProcessingOnClass($class,$isTestRun=FALSE,$vars=array()){
		$this->vars=$vars;
		$canvasElementsSelector=array('Source'=>$this->arr['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'Group'=>'Canvas elements');
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($canvasElementsSelector,TRUE,'Read','EntryId',TRUE) as $canvasElement){
			if (empty($canvasElement['Content']['Widgets']['Processor'])){continue;}
			if (strpos($canvasElement['Content']['Widgets']['Processor'],'SourcePot\Datapool\Processing\CanvasProcessing')===FALSE){continue;}
			$this->runCanvasProcessing($canvasElement,$isTestRun);
		}
		return $this->vars;
	}
	
	public function runCanvasProcessing($callingElement,$isTestRun=TRUE){
		// get job processing canvas elements
		$canvasElements=$this->arr['SourcePot\Datapool\AdminApps\Settings']->getSetting(__CLASS__,__FUNCTION__,array(),'Processing steps',TRUE);
		if (empty($canvasElements)){
			$canvasElements=$this->getCanvasElements($callingElement);
		}
		$currentCanvasElement=array_shift($canvasElements);
		$this->arr['SourcePot\Datapool\AdminApps\Settings']->setSetting(__CLASS__,__FUNCTION__,$canvasElements,'Processing steps',TRUE);
		$targetCanvasElement=array('Source'=>$this->arr['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$currentCanvasElement['Content']['Process']);
		$targetCanvasElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($targetCanvasElement,TRUE);
		if ($targetCanvasElement){
			if (isset($this->vars[$currentCanvasElement['Content']['Process']])){
				$result=$this->vars[$currentCanvasElement['Content']['Process']];
			} else {
				$result=array();
			}
			$processor=$targetCanvasElement['Content']['Widgets']['Processor'];
			$result=$this->arr[$processor]->dataProcessor($isTestRun?'test':'run',$targetCanvasElement,$result);
			$this->vars[$currentCanvasElement['Content']['Process']]=$result;
		}
		$result['Canvas processing']['Stack']['value']=count($canvasElements);
		return $result;
	}
	
	public function callingElement2selector($callingFunction,$callingElement,$selectsUniqueEntry=FALSE){
		if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
		$type=$this->arr['SourcePot\Datapool\Foundation\Database']->class2source(__CLASS__,TRUE);
		$type.='|'.$callingFunction;
		$entrySelector=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
		if ($selectsUniqueEntry){$entrySelector=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entrySelector,array('Group','Folder','Name','Type'),0);}
		return $entrySelector;
	}

	private function getCanvasElements($callingElement,$group='canvasProcessingRules'){
		$canvasElements=array();
		$canvasElementsSelector=array('Source'=>$this->entryTable,'Group'=>$group,'Folder'=>$callingElement['Folder']);
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($canvasElementsSelector,TRUE,'Read','EntryId',TRUE) as $entry){
			$canvasElements[$entry['EntryId']]=$entry;
		}
		return $canvasElements;
	}

}
?>