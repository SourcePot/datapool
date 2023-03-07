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

class Explorer{
	
	private $arr;
	
	private $varSpaceKey='';
	
	private $state=array();
	private $guideEntries=array();
	
	public function __construct($arr){
		$this->arr=$arr;	
	}
	
	public function init($arr){
		$this->arr=$arr;
		return $this->arr;
	}
	
	public function getExplorer($arr,$callingClass){
		$state=$this->session2state($callingClass);
		$this->getGuideEntries($callingClass);
		$this->formProcessing($callingClass);
		$this->getGuideEntries($callingClass);
		$html=$this->getForm($callingClass);
		$arr['page html']=str_replace('{{explorer}}',$html,$arr['page html']);
		return $arr;
	}
	
	private function initStateArr(){
		$this->state=array('Source'=>array('Label'=>'Source','Selected'=>FALSE,'orderBy'=>'Source','isAsc'=>TRUE),
						   'Group'=>array('Label'=>'Group','Selected'=>FALSE,'orderBy'=>'Group','isAsc'=>TRUE),
						   'Folder'=>array('Label'=>'Folder','Selected'=>FALSE,'orderBy'=>'Folder','isAsc'=>TRUE),
						   'ElementId'=>array('Label'=>'Name','Selected'=>FALSE,'orderBy'=>'Name','isAsc'=>TRUE)
						  );
		return $this->state;
	}
	
	private function session2state($callingClass){
		// This function copies the page state to the class state var.
		$selector=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
		if (!empty($selector['ElementId'])){
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByKey($selector);
			if (!empty($entry)){$selector=$entry;}
		}
		$this->initStateArr();
		foreach($this->state as $column=>$state){
			if (isset($selector[$column])){$this->state[$column]['Selected']=$selector[$column];}
		}
		return $this->state;
	}
	
	private function getGuideEntries($callingClass){
		$this->guideEntries=array();
		$prevGuideEntry=array();
		$guideEntry=array('Source'=>'__SKIP__','Group'=>'__SKIP__','Folder'=>'__SKIP__','Name'=>'&larrhk;');
		foreach($this->state as $column=>$stateArr){
			if (empty($stateArr['Selected'])){
				break;
			} else {
				$guideEntry[$column]=$stateArr['Selected'];
				$guideEntry['Type']='__'.$column.'__';
			}
			$guideEntry=$this->completeGuideEntry($callingClass,$guideEntry,$prevGuideEntry);
			$this->guideEntries[$column]=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByKeyCreateIfMissing($guideEntry,TRUE);
			$prevGuideEntry=$this->guideEntries[$column];
		}	
		return $this->guideEntries;
	}
		
	private function completeGuideEntry($callingClass,$guideEntry,$prevGuideEntry){
		$keysToSet=array('Read','Write','Owner');
		if (empty($prevGuideEntry)){
			$entryTemplate=$this->arr[$callingClass]->getEntryTemplate();
			$prevGuideEntry=array('Read'=>$entryTemplate['Read']['value'],'Write'=>$entryTemplate['Write']['value'],'Owner'=>$entryTemplate['Owner']['value']);
		} else {
			$prevGuideEntry['Owner']=$_SESSION['currentUser']['ElementId'];
		}
		foreach($keysToSet as $keyIndex=>$key){
			$guideEntry[$key]=$this->arr['SourcePot\Datapool\Foundation\Access']->accessString2int($prevGuideEntry[$key],FALSE);
		}
		if (isset($guideEntry['ElementId'])){unset($guideEntry['ElementId']);}
		$guideEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addElementId($guideEntry,array('Source','Group','Folder'),0,'-guideEntry');
		return $guideEntry;
	}
			
	private function formProcessing($callingClass){
		// form processing
		$selector=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['select'])){
			$stateKey=$formData['cmd']['select'];
			// set selector value
			$hadMatch=FALSE;
			$resetKeyRequest=FALSE;
			foreach($this->state as $column=>$state){
				if (isset($formData['val'][$column])){$value=$formData['val'][$column];} else {$value=$state['Selected'];}
				if ($hadMatch){$value=FALSE;}
				if (strcmp($column,$stateKey)===0){
					$hadMatch=TRUE;
					if (strpos($value,'__')!==FALSE){	
						$value=FALSE;
						$selector=$this->setSelectorByKey($callingClass,'ElementId',FALSE);
					}
				}
				$selector=$this->setSelectorByKey($callingClass,$column,$value);	
			}
		} else if (isset($formData['cmd']['update file']) || isset($formData['cmd']['add files'])){
			$key=key($formData['cmd']);
			foreach($formData['files'][$key] as $fileIndex=>$fileArr){
				$this->arr['SourcePot\Datapool\Tools\FileTools']->file2entries($fileArr,$this->getEntryTemplate($callingClass));
			}
		} else if (isset($formData['cmd']['add'])){
			$column=$formData['cmd']['add'];
			$selector[$column]=$formData['val']['add'][$column];
			$selector['currentKey']=$column;
			$selector=$this->setSelectorByKey($callingClass,$column,$formData['val']['add'][$column]);
		} else if (isset($formData['cmd']['edit'])){
			$column=$formData['cmd']['edit'];
			if (strlen(current($formData['val']['edit']))>2){
				$entry=$formData['val']['edit'];
				if (strcmp(key($entry),$column)===0){$newSelectedValue=$entry[$column];} else {$newSelectedValue=$selector[$column];}
				$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntries($selector,$entry);
				$selector=$this->setSelectorByKey($callingClass,$column,$newSelectedValue);
			} else {
				$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Too short entry provided, changes were discarded.','priority'=>12,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));	
			}
		} else if (isset($formData['cmd']['delete'])){
			$selectedColumn=key($formData['cmd']['delete']);
			$this->arr['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector);
			$selector=$this->setSelectorByKey($callingClass,$selectedColumn,FALSE);
		} else if (isset($formData['cmd']['download'])){
			$this->arr['SourcePot\Datapool\Tools\FileTools']->entry2fileDownload($selector);
		}
	}
	
	private function setSelectorByKey($callingClass,$key,$value){
		$this->state[$key]['Selected']=$value;
		$selector[$key]=$value;
		return $this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey($callingClass,$key,$value);
	}
	
	private function getForm($callingClass){
		$html='';
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'h1','element-content'=>ucfirst(strval($this->state['Source']['Selected']))));
		// add explorer components
		$result=$this->addSelector($callingClass);
		$html.=$result['html'];
		$arr=$this->addEntry($callingClass,$result['stateKey']);
		$appHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$arr=$this->editEntry($callingClass,$result['setKey']);
		$appHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$arr=$this->miscToolsEntry($callingClass,$result['setKey']);
		$appHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$arr=$this->sendEmail($callingClass,$result['setKey']);
		$appHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$arr=$this->setRightsEntry($callingClass,'Read');
		$appHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$arr=$this->setRightsEntry($callingClass,'Write');
		$appHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->app($arr);
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$appHtml,'keep-element-content'=>TRUE,'style'=>array('float'=>'left','clear'=>'both','padding'=>'5px','margin'=>'0.5em')));
		// add wrapper
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE));
		return $html;
	}

	private function addSelector($callingClass){
		$html='';
		$setKey='Source';
		foreach($this->state as $stateKey=>$state){
			$html.=$this->getSelector($callingClass,$stateKey);
			if ($state['Selected']===FALSE){break;}
			$setKey=$stateKey;
		}	
		$wrapper=array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>array('float'=>'left','clear'=>'both'));
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($wrapper);
		return array('html'=>$html,'stateKey'=>$stateKey,'setKey'=>$setKey);
	}
	
	private function getSelector($callingClass,$stateKey){
		$html='';
		if (strcmp($stateKey,'Source')===0){
			//$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'h2','element-content'=>$this->state[$stateKey]['Selected']));
		} else {
			$selector=array();
			foreach($this->state as $selectorStateKey=>$selectorState){
				$column=$selectorStateKey;
				$label=$selectorState['Label'];
				$orderBy=$selectorState['orderBy'];
				$isAsc=$selectorState['isAsc'];
				if (strcmp($selectorStateKey,$stateKey)===0){break;}
				$selector[$selectorStateKey]=$selectorState['Selected'];
			}
			$options=array('__'.$column.'__'=>'&larrhk;');
			if (strcmp($column,'ElementId')===0){$selector['Name!']='&larrhk;';} else {$selector[$column.'!']='__SKIP__';}
			foreach($this->arr['SourcePot\Datapool\Foundation\Database']->getDistinct($selector,$column,FALSE,'Read',$orderBy,$isAsc) as $row){
				if (!isset($row[$label])){$row=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByKey($row);}
				$options[$row[$column]]=$row[$label];
			}
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('label'=>$label,'options'=>$options,'hasSelectBtn'=>TRUE,'key'=>$column,'value'=>$this->state[$column]['Selected'],'callingClass'=>__CLASS__,'callingFunction'=>'formProcessing','class'=>'explorer'));
			$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','class'=>'explorer','element-content'=>$html,'keep-element-content'=>TRUE));
		}
		return $html;
	}
	
	private function addEntry($callingClass,$stateKey){
		$h2Arr=array('tag'=>'h3','element-content'=>'Add');
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($h2Arr);
		if (strcmp($stateKey,'ElementId')===0){
			if (empty($this->state['ElementId']['Selected'])){
				$key=array('add files');
				$label='Add file(s)';
				$fileElement=array('tag'=>'input','type'=>'file','key'=>$key,'multiple'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>'formProcessing');
			} else {
				$key=array('update file');
				$label='Update entry file';
				$fileElement=array('tag'=>'input','type'=>'file','key'=>$key,'callingClass'=>__CLASS__,'callingFunction'=>'formProcessing');
			}	
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($fileElement);
		} else {
			$key=array('add');
			$label='Add '.$stateKey;
			$fileElement=array('tag'=>'input','type'=>'text','key'=>array('add',$stateKey),'callingClass'=>__CLASS__,'callingFunction'=>'formProcessing');
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($fileElement);
			
		}
		$addBtn=array('tag'=>'button','element-content'=>$label,'key'=>$key,'value'=>$stateKey,'callingClass'=>__CLASS__,'callingFunction'=>'formProcessing','style'=>array('font-size'=>'1.15em'));
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($addBtn);
		$wrapper=array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>array('float'=>'left','clear'=>'both','margin'=>'35px 0.5em 0 0'));
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($wrapper);
		$arr=array('html'=>$html,'icon'=>'&#10010;','style'=>array('clear'=>'left'));
		return $arr;
	}

	private function editEntry($callingClass,$setKey){
		if (strcmp($setKey,'Source')===0){return array('html'=>'','icon'=>'&#9998;');}
		$h2Arr=array('tag'=>'h3','element-content'=>'Edit');
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($h2Arr);
		$btnKey=$setKey;
		if (strcmp($setKey,'ElementId')===0){
			$selector=array('Source'=>$this->state['Source']['Selected'],'ElementId'=>$this->state['ElementId']['Selected']);
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByKey($selector);
			if (!empty($entry)){$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Entry editor','entryEditor',$entry,array(),array());}
		} else {
			$value=$this->state[$setKey]['Selected'];
			$fileElement=array('tag'=>'input','type'=>'text','value'=>$value,'key'=>array('edit',$setKey),'callingClass'=>__CLASS__,'callingFunction'=>'formProcessing');
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($fileElement);
			$addBtn=array('tag'=>'button','element-content'=>'Edit '.$setKey,'key'=>array('edit'),'value'=>$btnKey,'callingClass'=>__CLASS__,'callingFunction'=>'formProcessing','style'=>array('font-size'=>'1.15em'));
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($addBtn);
		}
		$wrapper=array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>'float:left;clear:both;margin:35px 0 0 0;padding-left:0.5em;');
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($wrapper);
		$arr=array('html'=>$html,'icon'=>'&vellip;');
		return $arr;
	}
	
	private function miscToolsEntry($callingClass,$setKey){
		$html='';
		$fileElement=array('tag'=>'button','element-content'=>'&#8892;','key'=>array('download',$setKey),'keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>'formProcessing');
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($fileElement);
		$fileElement=array('tag'=>'button','element-content'=>'&xcup;','key'=>array('delete',$setKey),'keep-element-content'=>TRUE,'title'=>'Delete selected...','hasCover'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>'formProcessing');
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($fileElement);
		$wrapper=array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>array('float'=>'left','clear'=>'both','margin'=>'35px 0 0 0','padding-left'=>'0.5em'));
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($wrapper);
		$arr=array('html'=>$html,'icon'=>'...');
		return $arr;
	}

	private function sendEmail($callingClass,$setKey){
		$html='';
		$selector=$this->selectorFromState($callingClass);
		if (!empty($selector['ElementId'])){
			$matrix=array();
			$mail=array('selector'=>$this->arr['SourcePot\Datapool\Foundation\Database']->entryByKey($selector));
			$template=array('val'=>array('To'=>'','Subject'=>'Das wollte ich Dir schicken...','From'=>$this->arr['SourcePot\Datapool\Foundation\User']->userAbtract(FALSE,5)),
							'filter'=>array('To'=>FILTER_SANITIZE_EMAIL,'Subject'=>'','From'=>FILTER_SANITIZE_EMAIL)
							);
			$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
			$formData['val']=array_merge($template['val'],$formData['val']);
			if (isset($formData['cmd']['send'])){
				$mail=array_merge($mail,$formData['val']);
				$this->arr['SourcePot\Datapool\Tools\NetworkTools']->entry2mail($mail);
			}
			foreach($template['filter'] as $key=>$filter){
				$element=array('tag'=>'input','type'=>'text','value'=>$formData['val'][$key],'key'=>array($key),'filter'=>$filter,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
				$matrix[$key]=array('Value'=>$element);
			}
			$element=array('tag'=>'input','type'=>'submit','value'=>'Send','key'=>array('send'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
			$matrix['&rarr;']=array('Value'=>$element);
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Send entry as email'));
			$wrapper=array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>array('float'=>'left','clear'=>'both','margin'=>'35px 0 0 0','padding-left'=>'0.5em'));
			$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($wrapper);
		}
		$arr=array('html'=>$html,'icon'=>'@');
		return $arr;
	}
	
	private function setRightsEntry($callingClass,$right){
		$icon=ucfirst($right);
		$selector=$this->selectorFromState($callingClass);
		if (strcmp($selector['currentKey'],'Source')===0){return array('html'=>'','icon'=>$icon[0]);}
		$writableEntries=0;
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Write') as $entry){$writableEntries++;}
		if ($writableEntries===0){return array('html'=>'','icon'=>$icon[0]);}
		//
		$h2Arr=array('tag'=>'h3','element-content'=>'Set '.$right.' rights');
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($h2Arr);
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->integerEditor($this->getGuideEntry(),$right,$this->arr['SourcePot\Datapool\Foundation\User']->getUserRols());
		$wrapper=array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>array('float'=>'left','clear'=>'both','margin'=>'35px 0 0 0','padding-left'=>'0.5em'));
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($wrapper);
		$arr=array('html'=>$html,'icon'=>$icon[0]);
		return $arr;
	}
	
	private function selectorFromState($callingClass){
		$selector=array('currentKey'=>'Source');
		foreach($this->state as $column=>$state){
			if (!empty($state['Selected'])){
				$selector[$column]=$state['Selected'];
				$selector['currentKey']=$column;
			}
		}
		return $selector;
	}
	
	private function getEntryTemplate($callingClass){
		$entryTemplate=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageState($callingClass);
		if (empty($entryTemplate['ElementId'])){
			$entryTemplate=$this->getGuideEntry();
			unset($entryTemplate['Name']);
			unset($entryTemplate['Type']);
			unset($entryTemplate['ElementId']);
		}
		return $entryTemplate;
	}
	
	private function getGuideEntry(){
		$guideEntries=$this->guideEntries;
		return array_pop($guideEntries);
	}

	public function guideEntry2selector($guideEntry){
		if (empty($guideEntry['ElementId'])){return $guideEntry;}
		if (empty(strpos($guideEntry['ElementId'],'-guideEntry'))){return $guideEntry;}
		$selector=array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE);
		foreach($selector as $key=>$initValue){
			if (!isset($guideEntry[$key])){continue;}
			if (strpos($guideEntry[$key],'__')===0 || strcmp($guideEntry[$key],'&larrhk;')===0){continue;}
			$selector[$key]=$guideEntry[$key];
		}
		return $selector;
	}

	
}
?>