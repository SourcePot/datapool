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

class Explorer{
	
	private $oc;
	
	private $selectorTemplate=array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'EntryId'=>FALSE);
	private $settingsTemplate=array('Source'=>array('orderBy'=>'Source','isAsc'=>TRUE,'limit'=>FALSE,'offset'=>FALSE),
									'Group'=>array('orderBy'=>'Group','isAsc'=>TRUE,'limit'=>FALSE,'offset'=>FALSE),
									'Folder'=>array('orderBy'=>'Folder','isAsc'=>TRUE,'limit'=>FALSE,'offset'=>FALSE),
									'EntryId'=>array('orderBy'=>'Name','isAsc'=>TRUE,'limit'=>FALSE,'offset'=>FALSE)
									);
									
	const GUIDEINDICATOR='!GUIDE';
	private $state=array();
	
	public function __construct($oc){
		$this->oc=$oc;	
	}
	
	public function init($oc){
		$this->oc=$oc;
	}

	public function getGuideIndicator(){
		return self::GUIDEINDICATOR;
	}

	public function getExplorer($callingClass){
		$this->appProcessing($callingClass);
		$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h1','element-content'=>'Database explorer'));
		$selectorsHtml=$this->getSelectors($callingClass);
		$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$selectorsHtml,'keep-element-content'=>TRUE));
		$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE));
		return $html;
	}

	private function getSelectors($callingClass){
		$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
		$stateKeys=array('selectedKey'=>key($selector),'nextKey'=>key($selector));
		$html='';
		$selector=array_merge($this->selectorTemplate,$selector);
		foreach($this->selectorTemplate as $column=>$initValue){
			$selected=(isset($selector[$column]))?$selector[$column]:$initValue;
			$selectorHtml='';
			$options=array(self::GUIDEINDICATOR=>'&larrhk;');
			foreach($this->oc['SourcePot\Datapool\Foundation\Database']->getDistinct($selector,$column,FALSE,'Read',$this->settingsTemplate[$column]['orderBy'],$this->settingsTemplate[$column]['isAsc']) as $row){
				if (strcmp($column,'EntryId')===0){
					$entrySelector=array_merge($selector,array('EntryId'=>$row['EntryId']));
					$row=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($entrySelector);
					$label='Name';
				} else {
					$label=$column;
				}
				if (strcmp($row[$label],self::GUIDEINDICATOR)===0){continue;}
				$options[$row[$column]]=$row[$label];
			}
			$selectorHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('label'=>$label,'options'=>$options,'hasSelectBtn'=>TRUE,'key'=>array('selector',$column),'value'=>$selector[$column],'keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'explorer'));
			$selectorHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','class'=>'explorer','element-content'=>$selectorHtml,'keep-element-content'=>TRUE));
			if (strcmp($column,'Source')!==0 || strcmp($callingClass,'SourcePot\Datapool\AdminApps\Admin')===0){
				// non-Admin pages should not provide the Source-selector
				$html.=$selectorHtml;
			}
			$stateKeys['nextKey']=$column;
			if ($selected===FALSE){break;} else {$stateKeys['selectedKey']=$column;}
		}
		$html.=$this->addApps($callingClass,$stateKeys);
		return $html;
	}

	private function addApps($callingClass,$stateKeys){
		$html='';
		$arr=$this->addEntry($callingClass,$stateKeys);
		$appHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$arr=$this->editEntry($callingClass,$stateKeys);
		$appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$arr=$this->miscToolsEntry($callingClass,$stateKeys);
		$appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$arr=$this->sendEmail($callingClass,$stateKeys);
		$appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$arr=$this->setRightsEntry($callingClass,$stateKeys,'Read');
		$appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$arr=$this->setRightsEntry($callingClass,$stateKeys,'Write');
		$appHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$commentsArr=$this->comments($callingClass,$stateKeys);
		$appHtml.=$commentsArr['html'];
		$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$appHtml,'keep-element-content'=>TRUE,'style'=>array('float'=>'left','clear'=>'both','padding'=>'5px','margin'=>'0.5em')));
		return $html;
	}
	
	private function deleteGuideEntry($selector){
		$entry=$this->getGuideEntry($selector);
		$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($entry,TRUE);
		return $entry;
	}
	
	private function getGuideEntry($selector,$templateB=array()){
		if (empty($selector['Source'])){
			return array('Read'=>0,'Write'=>0);
		} else if (!empty($selector['EntryId'])){
			$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
		} else {
			$unseledtedDetected=FALSE;
			$selector=array_merge($this->selectorTemplate,$selector);
			$templateA=array('Name'=>self::GUIDEINDICATOR,'Type'=>$selector['Source'].' '.self::GUIDEINDICATOR,'Owner'=>$_SESSION['currentUser']['EntryId'],'Read'=>'ALL_MEMBER_R','Write'=>'ADMIN_R');
			$entry=array_replace_recursive($templateA,$templateB);
			foreach($selector as $column=>$selected){
				if (empty($selected)){$unseledtedDetected=TRUE;}
				$entry[$column]=($unseledtedDetected)?self::GUIDEINDICATOR:$selected;
			}
			$entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Type'),'0',self::GUIDEINDICATOR,FALSE);
			$entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Read');
			$entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Write');
			$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry);
		}
		return $entry;
	}
	
	private function selector2guideEntry($selector){
		foreach($selector as $key=>$value){
			if ($value===FALSE){
				$selector[$key]=self::GUIDEINDICATOR;
			}
		}
		$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($selector,TRUE);
		return $entry;
	}
	
	private function appProcessing($callingClass){
		// process selectors
		$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
		$guideEntry=$this->selector2guideEntry($selector);
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'getSelectors');
		if (isset($formData['cmd']['select'])){
			$resetFromHere=FALSE;
			foreach($formData['val']['selector'] as $column=>$selected){
				if (strcmp($selected,self::GUIDEINDICATOR)===0){$resetFromHere=TRUE;}
				$newSelector[$column]=$resetFromHere?FALSE:$selected;
				if (isset($selector[$column])){
					if ($newSelector[$column]!=$selector[$column]){$resetFromHere=TRUE;}
				}
			}
			$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($callingClass,$newSelector);
		}
		// add entry app
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'addEntry');
		if (isset($formData['cmd']['add']) && !empty($formData['val'][$formData['cmd']['add']])){
			$selector=array_merge($selector,$formData['val']);
			$guideEntry=$this->getGuideEntry($selector);
			$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($callingClass,$selector);
		} else if (isset($formData['cmd']['add files'])){
			if ($formData['hasValidFiles']){
				if (isset($guideEntry['Read'])){$selector['Read']=$guideEntry['Read'];}
				if (isset($guideEntry['Write'])){$selector['Write']=$guideEntry['Write'];}
				foreach($formData['files']['add files'] as $fileIndex=>$fileArr){
					if ($fileArr['error']){continue;}
					$this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entries($fileArr,$selector);
				}
			}		
		} else if (isset($formData['cmd']['update file'])){
			if ($formData['hasValidFiles']){
				foreach($formData['files']['update file'] as $fileIndex=>$fileArr){
					if ($fileArr['error']){continue;}
					$this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entries($fileArr,$selector);
				}
			}	
		}
		// editEntry
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'editEntry');
		if (isset($formData['cmd']['edit'])){
			$oldGuideEntry=$this->deleteGuideEntry($selector);
			$newSelector=array_merge($selector,$formData['val']);
			$this->getGuideEntry($newSelector,array('Read'=>$oldGuideEntry['Read'],'Write'=>$oldGuideEntry['Write'],'Owner'=>$oldGuideEntry['Owner']));
			$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntries($selector,$newSelector);
			$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($callingClass,$newSelector);
		}
		
	}
	
	private function addEntry($callingClass,$stateKeys){
		$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
		if (strcmp($stateKeys['nextKey'],'Source')===0){
			return array('html'=>'','icon'=>'&#10010;','class'=>'explorer');
		} else {
			$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h3','element-content'=>'Add'));
			if (strcmp($stateKeys['selectedKey'],'Folder')===0){
				$key=array('add files');
				$label='Add file(s)';
				$fileElement=array('tag'=>'input','type'=>'file','key'=>$key,'multiple'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('clear'=>'left'));
				$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileElement);
			} else if (strcmp($stateKeys['selectedKey'],'EntryId')===0){
				$key=array('update file');
				$label='Update entry file';
				$fileElement=array('tag'=>'input','type'=>'file','key'=>$key,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('clear'=>'left'));
				$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileElement);
			} else {
				$key=array('add');
				$label='Add '.$stateKeys['nextKey'];
				$fileElement=array('tag'=>'input','type'=>'text','key'=>array($stateKeys['nextKey']),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('clear'=>'left'));
				$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileElement);
			}
			$addBtn=array('tag'=>'button','element-content'=>$label,'key'=>$key,'value'=>$stateKeys['nextKey'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('font-size'=>'1.15em'));
			$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($addBtn);
		}
		$arr=array('html'=>$html,'icon'=>'&#10010;','title'=>'Add new "'.$stateKeys['selectedKey'].'"','class'=>'explorer');
		return $arr;
	}

	private function editEntry($callingClass,$stateKeys){
		if (strcmp($stateKeys['selectedKey'],'Source')===0){return array('html'=>'','icon'=>'&#9998;','class'=>'explorer');}
		$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
		$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h3','element-content'=>'Edit'));
		if (strcmp($stateKeys['selectedKey'],'EntryId')===0){
			$selector=array('Source'=>$selector['Source'],'EntryId'=>$selector['EntryId']);
			$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
			if (!empty($entry)){$html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry editor','entryEditor',$entry,array(),array());}
		} else {
			
			$fileElement=array('tag'=>'input','type'=>'text','value'=>$selector[$stateKeys['selectedKey']],'key'=>array($stateKeys['selectedKey']),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('clear'=>'left'));
			$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileElement);
			$addBtn=array('tag'=>'button','element-content'=>'Edit '.$stateKeys['selectedKey'],'key'=>array('edit'),'value'=>$stateKeys['selectedKey'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('font-size'=>'1.15em'));
			$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($addBtn);
		}
		$arr=array('html'=>$html,'icon'=>'&#9998;','title'=>'Edit selected "'.$stateKeys['selectedKey'].'"','class'=>'explorer');
		return $arr;
	}
	
	private function miscToolsEntry($callingClass,$stateKeys){
		$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h3','element-content'=>'Misc tools'));
		$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
		$guideEntry=$this->getGuideEntry($selector);
		$selector['Read']=(isset($guideEntry['Read']))?$guideEntry['Read']:'ALL_MEMBER_R';
		$selector['Write']=(isset($guideEntry['Write']))?$guideEntry['Write']:'ADMIN_R';
		$btnHtml='';
		$btnArr=array('selector'=>$selector);
		foreach(array('download all','export','delete') as $cmd){
			$btnArr['cmd']=$cmd;
			$btnHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
		}
		$wrapperElement=array('tag'=>'div','element-content'=>$btnHtml,'keep-element-content'=>TRUE,'style'=>array('clear'=>'both'));
		$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($wrapperElement);
		$arr=array('html'=>$html,'icon'=>'...','title'=>'Misc tools, e.g. entry deletion and download','class'=>'explorer');
		return $arr;
	}

	private function sendEmail($callingClass,$setKeys){
		$arr=array('html'=>'','callingClass'=>$callingClass,'callingFunction'=>__FUNCTION__,'icon'=>'@','title'=>'Send entry as email','class'=>'explorer');
		$arr['selector']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
		if (!empty($arr['selector']['EntryId'])){
			$arr=$this->oc['SourcePot\Datapool\Tools\Email']->datasink($arr,'transmitterWidget');
		}
		return $arr;
	}
	
	private function comments($callingClass,$setKeys){
		$arr=array('html'=>'');
		if (strcmp($setKeys['selectedKey'],'EntryId')!==0){
			$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h3','element-content'=>'Misc tools'));
			$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
			$arr=array('selector'=>$this->getGuideEntry($selector),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'comment');
			$arr=$this->oc['SourcePot\Datapool\Foundation\Container']->comments($arr);
		}
		return $arr;
	}
	
	private function setRightsEntry($callingClass,$stateKeys,$right){
		$icon=ucfirst($right);
		$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
		if (strcmp($stateKeys['selectedKey'],'Source')===0){
			// Source level
			return array('html'=>'','icon'=>$icon[0],'class'=>'explorer');
		}
		// check if there are any entries with write access
		$writableEntries=0;
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Write') as $entry){$writableEntries++;}
		if ($writableEntries===0){
			// no entries with write access found
			return array('html'=>'','icon'=>$icon[0],'class'=>'explorer');
		}
		// create html
		$entry=$this->getGuideEntry($selector);
		$html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->integerEditor(array('selector'=>$entry,'key'=>$right));
		$arr=array('html'=>$html,'icon'=>$icon[0],'title'=>'Setting "'.$right.'" access right','class'=>'explorer');
		return $arr;
	}
	
}
?>