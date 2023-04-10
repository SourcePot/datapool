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

class CanvasTrigger{
	
	private $arr;
	
	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );
	
	private $slopeOptions=array('&#9472;&#9472;&#9488;__','__&#9484;&#9472;&#9472;');
	private $typeOptions=array('empty'=>'Canvas element is empty','stable'=>'Content stable','increase'=>'Content increase','decrease'=>'Content decrease');
		
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

	public function job($vars){
		$vars['Result']=$this->runCanvasTrigger(array('Source'=>'dataexplorer'),FALSE);
		return $vars;
	}

	public function getEntryTable(){return $this->entryTable;}
	
	public function dataProcessor($action='info',$callingElementSelector=array()){
		// This method is the interface of this data processing class
		// The Argument $action selects the method to be invoked and
		// argument $callingElementSelector$ provides the entry which triggerd the action.
		// $callingElementSelector ... array('Source'=>'...', 'EntryId'=>'...', ...)
		// If the requested action does not exist the method returns FALSE and 
		// TRUE, a value or an array otherwise.
		$callingElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector);
		switch($action){
			case 'run':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runCanvasTrigger($callingElement,$testRunOnly=FALSE);
				}
				break;
			case 'test':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runCanvasTrigger($callingElement,$testRunOnly=TRUE);
				}
				break;
			case 'widget':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getCanvasTriggerWidget($callingElement);
				}
				break;
			case 'settings':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getCanvasTriggerSettings($callingElement);
				}
				break;
			case 'info':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getCanvasTriggerInfo($callingElement);
				}
				break;
		}
		return FALSE;
	}

	private function getCanvasTriggerWidget($callingElement){
		$callingElement['refreshInterval']=60;
		return $this->arr['SourcePot\Datapool\Foundation\Container']->container('Canvas trigger','generic',$callingElement,array('method'=>'getCanvasTriggerWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getCanvasTriggerWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=array();
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['run'])){
			$result=$this->runCanvasTrigger($arr['selector'],FALSE);
		} else if (isset($formData['cmd']['test'])){
			$result=$this->runCanvasTrigger($arr['selector'],TRUE);
		} else if (isset($formData['cmd']['Reset'])){
			$this->resetTrigger(key($formData['cmd']['Reset']));
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
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'CanvasTrigger widget'));
		foreach($result as $caption=>$matrix){
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		// get current trigger state
		$matrix=array();
		$triggerArr=$this->getTrigger();
		foreach($triggerArr['trigger'] as $triggerId=>$trigger){
			foreach($trigger as $key=>$value){
				if (is_bool($value)){
					$matrix[$triggerId][$key]=$this->arr['SourcePot\Datapool\Tools\MiscTools']->bool2element($value);
				} else {
					$matrix[$triggerId][$key]=$value;
				}
			}
			$btnArr=array('cmd'=>'Reset','key'=>array('Reset',$triggerId),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
			$matrix[$triggerId]['Reset']=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
		}
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Trigger status','keep-element-content'=>TRUE,'hideKeys'=>TRUE));
		$arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
		return $arr;
	}

	private function getCanvasTriggerSettings($callingElement){
		$html='';
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('CanvasTrigger entries settings','generic',$callingElement,array('method'=>'getCanvasTriggerSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getCanvasTriggerSettingsHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$arr['html'].=$this->canvasTriggerRules($arr['selector']);
		//$selectorMatrix=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
		//$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for CanvasTrigger'));
		return $arr;
	}
	
	private function canvasTriggerRules($callingElement){
		$triggerInitName='Trigger '.hrtime(TRUE);
		$contentStructure=array('Trigger name'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','value'=>$triggerInitName,'excontainer'=>TRUE),
								'Process'=>array('htmlBuilderMethod'=>'canvasElementSelect','excontainer'=>TRUE),
							    'Type'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>1,'options'=>$this->typeOptions),
								'Slope'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>1,'options'=>$this->slopeOptions),
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
		
	public function runCanvasTrigger($callingElement,$isTestRun=TRUE){
		// get canvas processing rules
		$base=array('Script start timestamp'=>time(),'canvastriggerrules'=>array());
		$entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId']);
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
			$key=explode('|',$entry['Type']);
			$key=array_pop($key);
			$base[$key][$entry['EntryId']]=$entry;
			// entry template
			foreach($entry['Content'] as $contentKey=>$content){
				if (strpos($content,'EID')!==0 || strpos($content,'eid')===FALSE){continue;}
				$template=$this->arr['SourcePot\Datapool\Foundation\DataExplorer']->entryId2selector($content);
				if ($template){$base['entryTemplates'][$content]=$template;}
			}
		}
		$this->arr['SourcePot\Datapool\Foundation\Database']->resetStatistic();
		$result=array('Trigger statistics'=>array('Signals processed'=>array('value'=>0),
												 'Trigger processed'=>array('value'=>0),
												 'Trigger activated'=>array('value'=>0),
												 'Trigger existed'=>array('value'=>0),
												 'Trigger initialized'=>array('value'=>0),
												 )
					 );
		$signals=$this->updateTriggerSignals($callingElement);
		$triggerEntry=array('Source'=>$this->entryTable,'Group'=>'Canvas trigger','Folder'=>$callingElement['Folder'],'Name'=>'Trigger','Type'=>$this->entryTable.' array');
		$triggerEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($triggerEntry,array('Group','Folder','Name','Type'),0);
		$tmpTriggerEntry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($triggerEntry,TRUE);
		if (isset($tmpTriggerEntry['Content'])){$pastTrigger=$tmpTriggerEntry['Content'];}
		$trigger=array();
		// loop through trigger rules
		foreach($base['canvastriggerrules'] as $ruleId=>$rule){
			$result['Trigger statistics']['Trigger processed']['value']++;
			$relevantCanvasElement=array('Source'=>$callingElement['Source'],'EntryId'=>$rule['Content']['Process']);
			$relevantCanvasElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($relevantCanvasElement,TRUE);
			$signalId=$relevantCanvasElement['Source'].'|'.$relevantCanvasElement['EntryId'].'|'.$rule['Content']['Type'];
			if (!isset($signals['Content'][$signalId])){continue;}
			$result['Trigger statistics']['Signals processed']['value']++;
			$trigger['signals'][$signalId]=$signals['Content'][$signalId];
			// init trigger
			$triggerId=$rule['Source'].'|'.$rule['EntryId'];
			if (isset($pastTrigger['trigger'][$triggerId])){
				$trigger['trigger'][$triggerId]=$pastTrigger['trigger'][$triggerId];
				$result['Trigger statistics']['Trigger existed']['value']++;
			} else {
				$result['Trigger statistics']['Trigger initialized']['value']++;
				$trigger['trigger'][$triggerId]['trigger name']=$rule['Content']['Trigger name'];
				$trigger['trigger'][$triggerId]['active']=FALSE;
			}
			// get clean bool values
			$slope=boolval(intval($rule['Content']['Slope']));
			$detectedSignal=boolval(intval($signals['Content'][$signalId]['Detected signal']));
			$lastDetectedSignal=boolval(intval($signals['Content'][$signalId]['Last detected signal']));
			// detect slopes
			if ($slope && $detectedSignal && !$lastDetectedSignal){
				$trigger['trigger'][$triggerId]['active']=TRUE;
				$result['Trigger statistics']['Trigger activated']['value']++;
			} else if (!$slope && !$detectedSignal && $lastDetectedSignal){
				$trigger['trigger'][$triggerId]['active']=TRUE;
				$result['Trigger statistics']['Trigger activated']['value']++;
			}
		}
		// save trigger
		$triggerEntry['Content']=$trigger;
		$triggerEntry=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($triggerEntry,TRUE);
		//
		$result['Statistics']=$this->arr['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
		$result['Statistics']['Script start']=array('Value'=>date('Y-m-d H:i:s',$base['Script start timestamp']));
		$result['Statistics']['Time consumption [sec]']=array('Value'=>time()-$base['Script start timestamp']);
		return $result;
	}
	
	private function getSignals($callingElement){
		// get old signals Content array
		$signalsSelector=array('Source'=>$this->entryTable,'Group'=>'Canvas trigger','Folder'=>$callingElement['Folder'],'Name'=>'Signals','Content'=>array(),'Type'=>$this->entryTable.' array');
		$signalsSelector=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($signalsSelector,array('Group','Folder','Name','Type'),0);
		$signals=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($signalsSelector,TRUE);
		if (empty($signals)){
			return $signalsSelector;
		} else {
			return $signals;
		}
	}

	public function updateTriggerSignals($callingElement){
		// get old signals Content array
		$signals=$this->getSignals($callingElement);
		$signalsContent=$signals['Content'];
		// create new signals Content array
		$newSignalsContent=array();
		$relevantCanvasElementsSelector=array('Source'=>'dataexplorer','Group'=>'Canvas elements');
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator(array('Source'=>'dataexplorer','Group'=>'Canvas elements'),TRUE,'Read') as $entryId=>$entry){
			if (empty($entry['Content']['Selector']['Source'])){continue;}
			$entryCount=$this->arr['SourcePot\Datapool\Foundation\Database']->getRowCount($entry['Content']['Selector'],TRUE);
			// get signals
			foreach($this->typeOptions as $type=>$typeName){
				$signalId=$entry['Source'].'|'.$entry['EntryId'].'|'.$type;
				// get values
				if (isset($signalsContent[$signalId]['Current value'])){
					$lastValue=$signalsContent[$signalId]['Current value'];
				}
				$newSignalsContent[$signalId]['Current value']=$entryCount;
				if (is_null($lastValue)){
					$newSignalsContent[$signalId]['Last value']=$newSignalsContent[$signalId]['Current value'];
				} else {
					$newSignalsContent[$signalId]['Last value']=$lastValue;
				}
				// get signal state
				if (isset($signalsContent[$signalId]['Detected signal'])){
					$lastDetectedSignal=$signalsContent[$signalId]['Detected signal'];
				}
				$newSignalsContent[$signalId]['Detected signal']=match($type){
					'empty'=>empty(intval($entryCount)),
					'increase'=>(intval($signalsContent[$signalId]['Current value'])-intval($signalsContent[$signalId]['Last value']))>0,
					'decrease'=>(intval($signalsContent[$signalId]['Current value'])-intval($signalsContent[$signalId]['Last value']))<0,
					'stable'=>(intval($signalsContent[$signalId]['Current value'])-intval($signalsContent[$signalId]['Last value']))==0,
				};
				if (is_null($lastDetectedSignal)){
					$newSignalsContent[$signalId]['Last detected signal']=$newSignalsContent[$signalId]['Detected signal'];
				} else {
					$newSignalsContent[$signalId]['Last detected signal']=$lastDetectedSignal;
				}
			}
			$signals['Content']=$newSignalsContent;
			$signals=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($signals,TRUE);
		}
		return $signals;
	}
	
	public function resetTrigger($resetTriggerId){
		$triggerEntriesSelector=array('Source'=>$this->entryTable,'Group'=>'Canvas trigger','Name'=>'Trigger');
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($triggerEntriesSelector,TRUE,'Read') as $entryId=>$entry){
			foreach($entry['Content']['trigger'] as $triggerId=>$trigger){
				if (strcmp($resetTriggerId,$triggerId)!==0){continue;}
				//$entry['Content']['trigger'][$triggerId]['active']=TRUE;
				$entry['Content']['trigger'][$triggerId]['active']=FALSE;
				return $this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
			}
		}
		return FALSE;
	}
	
	public function getTrigger(){
		$return=array('trigger'=>array(),'options'=>array(),'isActive'=>array());
		$triggerEntriesSelector=array('Source'=>$this->entryTable,'Group'=>'Canvas trigger','Name'=>'Trigger');
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($triggerEntriesSelector,TRUE,'Read') as $entryId=>$entry){
			foreach($entry['Content']['trigger'] as $triggerId=>$trigger){
				$return['trigger'][$triggerId]=$trigger;
				$return['options'][$triggerId]=$this->arr['class2source'][$entry['Folder']].' &rarr; '.$trigger['trigger name'];
				$return['isActive'][$triggerId]=$trigger['active'];
			}
		}
		return $return;
	}

	public function callingElement2selector($callingFunction,$callingElement,$selectsUniqueEntry=FALSE){
		if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
		$type=$this->arr['SourcePot\Datapool\Foundation\Database']->class2source(__CLASS__,TRUE);
		$type.='|'.$callingFunction;
		$entrySelector=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
		if ($selectsUniqueEntry){$entrySelector=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entrySelector,array('Group','Folder','Name','Type'),0);}
		return $entrySelector;
	}


}
?>