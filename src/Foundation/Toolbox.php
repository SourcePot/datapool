<?php
declare(strict_types=1);

namespace Datapool\Foundation;

class Toolbox{
	
	private $arr;
		
	private $entryTable;
	private $entryTemplate=array();
	
	private $toolboxes=array();
	
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
		$this->entryTemplate=$arr['Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
	}
	
	public function init($arr){
		$this->arr=$arr;
		return $this->arr;
	}
	
	public function registerToolbox($callingClass,$toolboxEntry){
		$toolboxTemplate=array('Source'=>$this->entryTable,'Group'=>'Settings','Folder'=>$callingClass,'Type'=>'toolbox','owner'=>'SYSTEM');
		if (empty($toolboxEntry['Name'])){$toolboxEntry['Name']='NAME WAS NOT PROVIDED';}
		$toolboxEntry=array_merge($toolboxEntry,$toolboxTemplate);
		$toolboxEntry=$this->arr['Datapool\Tools\StrTools']->addElementId($toolboxEntry,array('Source','Group','Folder','Name'),0);
		if ($this->arr['Datapool\Foundation\Access']->isAdmin()){
			$toolboxEntry=$this->arr['Datapool\Foundation\Database']->updateEntry($toolboxEntry);
		} else {
			$toolboxEntry=$this->arr['Datapool\Foundation\Database']->entryByKey($toolboxEntry);
		}
		if (!empty($toolboxEntry)){$this->toolboxes[$toolboxEntry['ElementId']]=$toolboxEntry;}
		return $toolboxEntry;
	}
	
	private function getToolboxMenu(){
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['select'])){
			$_SESSION['page state']['toolbox']=key($formData['cmd']['select']);
		}
		$html='';
		foreach($this->toolboxes as $elementId=>$toolboxEntry){
			if (strcmp($elementId,$_SESSION['page state']['toolbox'])===0){$style='border-bottom:1px solid #a44;';} else {$style='';}
			if (!$this->arr['Datapool\Foundation\Access']->access($toolboxEntry,'Read')){continue;}
			$element=array('tag'=>'input','type'=>'submit','key'=>array('select',$elementId),'value'=>$toolboxEntry['Name'],'style'=>$style,'class'=>'bottom-menu','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
			$html.=$this->arr['Datapool\Tools\HTMLbuilder']->element($element);
		}
		if (empty($html)){return $html;}
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'bottom-wrapper'));
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'id'=>'bottom-menu'));
		return $html;
	}
	
	public function getToolbox(){
		$menuHtml=$this->getToolboxMenu();
		if (empty($_SESSION['page state']['toolbox'])){return '';}
		$toolbox=array('Source'=>$this->entryTable,'ElementId'=>$_SESSION['page state']['toolbox']);
		$toolbox=$this->arr['Datapool\Foundation\Database']->entryByKey($toolbox);
		if (empty($toolbox)){
			foreach($this->arr['Datapool\Foundation\Database']->entryIterator(array('Source'=>$this->entryTable,'Name'=>'Logs','Group'=>'Settings'),TRUE) as $toolbox){
				$_SESSION['page state']['toolbox']=$toolbox['ElementId'];
			}
		}
		$toolboxProviderClass=$toolbox['Content']['class'];
		$toolboxProviderMethod=$toolbox['Content']['method'];
		$toolboxProviderArgs=$toolbox['Content']['args'];
		$toolbox=array('Name'=>'Logs','class'=>__CLASS__,'method'=>'showLogs','args'=>array('maxCount'=>10),'settings'=>array());
		$arr=array('class'=>'toolbox','icon'=>'+','default-min-width'=>'100%','default-max-width'=>'100%','default-max-height'=>'80px');
		$arr['html']=$this->arr[$toolboxProviderClass]->$toolboxProviderMethod($toolboxProviderArgs);
		$toolboxHtml=$this->arr['Datapool\Tools\HTMLbuilder']->app($arr);
		return $menuHtml.$toolboxHtml;
	}
}
?>