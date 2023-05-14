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

class Toolbox{
	
	private $oc;
		
	private $entryTable;
	private $entryTemplate=array();
	
	private $toolboxes=array();
	
	public function __construct($oc){
		$this->oc=$oc;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
		$this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
	}
	
	public function init($oc){
		$this->oc=$oc;
	}
	
	public function registerToolbox($callingClass,$toolboxEntry){
		$toolboxTemplate=array('Source'=>$this->entryTable,'Group'=>'Settings','Folder'=>$callingClass,'Type'=>'toolbox','owner'=>'SYSTEM');
		if (empty($toolboxEntry['Name'])){$toolboxEntry['Name']='NAME WAS NOT PROVIDED';}
		$toolboxEntry=array_merge($toolboxEntry,$toolboxTemplate);
		$toolboxEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($toolboxEntry,array('Source','Group','Folder','Name'),0);
		if ($this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin()){
			$toolboxEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($toolboxEntry);
		} else {
			$toolboxEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($toolboxEntry);
		}
		if (!empty($toolboxEntry)){$this->toolboxes[$toolboxEntry['EntryId']]=$toolboxEntry;}
		return $toolboxEntry;
	}
	
	private function getToolboxMenu(){
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['select'])){
			$_SESSION['page state']['toolbox']=key($formData['cmd']['select']);
		}
		$html='';
		foreach($this->toolboxes as $elementId=>$toolboxEntry){
			if (strcmp($elementId,$_SESSION['page state']['toolbox'])===0){$style='border-bottom:1px solid #a44;';} else {$style='';}
			if (!$this->oc['SourcePot\Datapool\Foundation\Access']->access($toolboxEntry,'Read')){continue;}
			$element=array('tag'=>'input','type'=>'submit','key'=>array('select',$elementId),'value'=>$toolboxEntry['Name'],'style'=>$style,'class'=>'bottom-menu','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
			$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
		}
		if (empty($html)){return $html;}
		$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'bottom-wrapper'));
		$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'id'=>'bottom-menu'));
		return $html;
	}
	
	public function getToolbox($arr){
		$arr['toReplace']['{{toolbox}}']=$this->getToolboxMenu();
		if (!empty($_SESSION['page state']['toolbox'])){
			$toolbox=array('Source'=>$this->entryTable,'EntryId'=>$_SESSION['page state']['toolbox']);
			$toolbox=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($toolbox);
			if (empty($toolbox)){
				foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator(array('Source'=>$this->entryTable,'Name'=>'Logs','Group'=>'Settings'),TRUE) as $toolbox){
					$_SESSION['page state']['toolbox']=$toolbox['EntryId'];
				}
			}
			if (!empty($toolbox)){
				$toolboxProviderClass=$toolbox['Content']['class'];
				$toolboxProviderMethod=$toolbox['Content']['method'];
				$toolboxProviderArgs=$toolbox['Content']['args'];
				//$toolbox=array('Name'=>'Logs','class'=>__CLASS__,'method'=>'showLogs','args'=>array('maxCount'=>10),'settings'=>array());
				$appArr=array('class'=>'toolbox','icon'=>$toolbox['Name']);
				$appArr['html']=$this->oc[$toolboxProviderClass]->$toolboxProviderMethod($toolboxProviderArgs);
				$toolboxHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
				$arr['toReplace']['{{toolbox}}'].=$toolboxHtml;
			}
		}
		return $arr;
		
	}
}
?>