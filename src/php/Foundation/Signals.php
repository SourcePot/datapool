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

class signals{
	private $oc;
	
	private $entryTable;
	private $entryTemplate=array();

	public function __construct($oc){
		$this->oc=$oc;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($oc){
		$this->oc=$oc;
		$this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
	}

	public function updateSignal($callingClass,$callingFunction,$name,$value,$dataType='int',$isSystemCall=TRUE){
		$newContent=array('value'=>$value,'dataType'=>$dataType,'timeStamp'=>time());
		// create entry template
		$signalType=$this->entryTable.' '.$dataType;
		$signal=array('Source'=>$this->entryTable,'Group'=>'signal','Folder'=>$callingClass.'::'.$callingFunction,'Name'=>$name,'Type'=>$signalType,'Read'=>'ADMIN_R','Write'=>'ADMIN_R');
		$signal=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($signal,array('Source','Group','Folder','Name','Type'),'0','',TRUE);
		$signal=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($signal);
		// get existing entry
		$lastSignal=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($signal,$isSystemCall);
		if (isset($lastSignal['Content'])){
			if (count($lastSignal['Content'])>3){
				array_pop($lastSignal['Content']);
			}
			$signal['Content']=$lastSignal['Content'];
		} else {
			$signal['Content']=array($newContent);
		}
		array_unshift($signal['Content'],$newContent);
		$signal=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($signal,$isSystemCall);
		$this->updateTrigger($signal);
		return $signal;
	}
	
	private function resetTrigger($trigger=array()){
		$counter=0;
		$trigger['Source']=$this->entryTable;
		$trigger['Group']='trigger';
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($trigger,FALSE,'Read','Name') as $trigger){
			$trigger['Content']['isActive']=FALSE;
			$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($trigger,TRUE);
			$counter++;
		}
		return $counter;
	}

	private function updateTrigger($signal){
		$triggerSelector=array('Source'=>$this->entryTable,'Group'=>'trigger','Content'=>'%'.$signal['EntryId'].'%');
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($triggerSelector,FALSE,'Read','Name') as $trigger){
			$trigger['Content']['signal']=$signal['Content'];
			$detectArr=$this->slopDetector($signal);
			if ($detectArr[$trigger['Content']['Active if']]){$trigger['Content']['isActive']=TRUE;}
			$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($trigger,TRUE);
		}
		return TRUE;
	}
	
	private function slopDetector($signal){
		$arr=array();
		foreach($signal['Content'] as $index=>$signalArr){
			if (strcmp($signalArr['dataType'],'int')===0 || strcmp($signalArr['dataType'],'bool')===0){
				$arr['values'][$index]=intval($signalArr['value']);
			} else if (strcmp($signalArr['dataType'],'float')===0){
				$arr['values'][$index]=floatval($signalArr['value']);	
			}
		}
		$arr['zero']=$arr['values'][0]===0;
		$arr['up']=$arr['values'][0]>$arr['values'][1]; 
		$arr['down']=$arr['values'][0]<$arr['values'][1];
		$arr['max']=$arr['values'][0]<$arr['values'][1] && $arr['values'][1]>$arr['values'][2];
		$arr['min']=$arr['values'][0]>$arr['values'][1] && $arr['values'][1]<$arr['values'][2];
		return $arr;
	}
	
	public function getTriggerWidget($callingClass,$callingFunction){
		$triggerType=$this->entryTable.' trigger';
		$trigger=array('Source'=>$this->entryTable,'Group'=>'trigger','Folder'=>$callingClass.'::'.$callingFunction,'Type'=>$triggerType,'Read'=>'ADMIN_R','Write'=>'ADMIN_R');
		$trigger=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($trigger);
		// trigger form processing
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['Save']) || isset($formData['cmd']['New'])){
			$trigger['EntryId']=key(current($formData['cmd']));
			$trigger=array_replace_recursive($trigger,$formData['val'][$trigger['EntryId']]);
			$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($trigger);
		} else if (isset($formData['cmd']['Reset'])){
			$trigger['EntryId']=key(current($formData['cmd']));
			$this->resetTrigger($trigger);
		} else if (isset($formData['cmd']['Delete'])){
			$trigger['EntryId']=key(current($formData['cmd']));
			$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($trigger);
		}
		// get trigger rows
		$matrix=array('New'=>$this->getTriggerRow());
		$triggerSelector=array('Source'=>$this->entryTable,'Group'=>'trigger','Folder'=>$callingClass.'::'.$callingFunction);
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($triggerSelector,FALSE,'Read','Name') as $trigger){
			$matrix[$trigger['EntryId']]=$this->getTriggerRow($trigger);
		}
		$html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Trigger settings','hideKeys'=>TRUE,'keep-element-content'=>TRUE));
		return $html;
	}

	private function getTriggerRow($trigger=array()){
		$callingFunction='getTriggerWidget';
		$arr['Signal']=array();
		$signalSelector=array('Source'=>$this->entryTable,'Group'=>'signal');
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($signalSelector,TRUE,'Read','Folder') as $signal){
			$class=substr($signal['Folder'],0,strpos($signal['Folder'],'::'));
			$classComps=explode('\\',$class);
			$class=array_pop($classComps);
			$arr['Signal'][$signal['EntryId']]=$class.' '.$signal['Name'];
		}
		$arr['Active if']=array('zero'=>'&#9601;&#9601;&#9601;&#9601;&#9601;','up'=>'&#9601;&#9601;&#9601;&#9585;&#9620;',
								'max'=>'&#9601;&#9601;&#9585;&#9586;&#9601;','min'=>'&#9620;&#9620;&#9586;&#9585;&#9620;',
								'down'=>'&#9620;&#9620;&#9620;&#9586;&#9601;'
								);
		$row=array();
		$isNewRow=empty($trigger['EntryId']);
		if (!isset($trigger['EntryId'])){$trigger['EntryId']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getEntryId();}
		if (isset($trigger['Name'])){$value=$trigger['Name'];} else {$value='My new trigger';}
		$row['Name']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'text','value'=>$value,'key'=>array($trigger['EntryId'],'Name'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE));
		if (isset($trigger['Content']['Signal'])){$selected=$trigger['Content']['Signal'];} else {$selected='';}
		$row['Signal']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$arr['Signal'],'selected'=>$selected,'key'=>array($trigger['EntryId'],'Content','Signal'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE));
		if (isset($trigger['Content']['Active if'])){$selected=$trigger['Content']['Active if'];} else {$selected='up';}
		$row['Active if']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$arr['Active if'],'selected'=>$selected,'keep-element-content'=>TRUE,'key'=>array($trigger['EntryId'],'Content','Active if'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE));
		if ($isNewRow){
			$isActive='';
			$reset='';
			$row['Cmd']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'+','keep-element-content'=>TRUE,'key'=>array('New',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
		} else {
			$isActive=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(!empty($trigger['Content']['isActive']));
			$reset=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Reset','keep-element-content'=>TRUE,'key'=>array('Reset',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
			$row['Cmd']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'key'=>array('Save',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
			$row['Cmd'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'&coprod;','keep-element-content'=>TRUE,'hasCover'=>TRUE,'key'=>array('Delete',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
		}
		for($index=2;$index>=0;$index--){
			$elementArr=array('tag'=>'p');
			if (isset($trigger['Content']['signal'][$index])){
				$elementArr['element-content']=intval($trigger['Content']['signal'][$index]['value']);
			} else {
				if ($isNewRow){$elementArr['element-content']='';} else {$elementArr['element-content']='-';}
			}
			$row[$index]=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elementArr);
		}
		$row['isActive']=$isActive;
		$row['Reset']=$reset;
		return $row;
	}

}
?>