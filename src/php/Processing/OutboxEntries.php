<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Processing;

class OutboxEntries implements \SourcePot\Datapool\Interfaces\Processor{
	
	private $oc;
	
	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );
	
	private $outboxClass='';
	private $base=array();

	private $conditions=array('stripos'=>'contains',
							 'stripos!'=>'does not contain',
							);

	public function __construct($oc){
		$this->oc=$oc;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init(array $oc){
		$this->oc=$oc;
		$this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
	}

	public function getEntryTable():string{return $this->entryTable;}
	
	public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
		// This method is the interface of this data processing class
		// The Argument $action selects the method to be invoked and
		// argument $callingElementSelector$ provides the entry which triggerd the action.
		// $callingElementSelector ... array('Source'=>'...', 'EntryId'=>'...', ...)
		// If the requested action does not exist the method returns FALSE and 
		// TRUE, a value or an array otherwise.
		$callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
		switch($action){
			case 'run':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runOutboxEntries($callingElement,$testRunOnly=0);
				}
				break;
			case 'test':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runOutboxEntries($callingElement,$testRunOnly=1);
				}
				break;
			case 'widget':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getOutboxEntriesWidget($callingElement);
				}
				break;
			case 'settings':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getOutboxEntriesSettings($callingElement);
				}
				break;
			case 'info':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getOutboxEntriesInfo($callingElement);
				}
				break;
		}
		return FALSE;
	}

	private function getOutboxEntriesWidget($callingElement){
		return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Outbox','generic',$callingElement,array('method'=>'getOutboxEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getOutboxEntriesWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=array();
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['run'])){
			$result=$this->runOutboxEntries($arr['selector'],0);
		} else if (isset($formData['cmd']['test'])){
			$result=$this->runOutboxEntries($arr['selector'],1);
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
		$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Outbox widget'));
		foreach($result as $caption=>$matrix){
			$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		$arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
		return $arr;
	}

	private function getOutboxEntriesSettings($callingElement){
		$html='';
		if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Outbox entries settings','generic',$callingElement,array('method'=>'getOutboxEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getOutboxEntriesSettingsHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$arr['html'].=$this->outboxParams($arr['selector']);
		$arr['callingClass']=$arr['selector']['Folder'];
		if (isset($this->oc[$this->outboxClass])){$arr=$this->oc[$this->outboxClass]->dataSink($arr,'settingsWidget');}
		$arr['html'].=$this->outboxRules($arr['selector']);
		return $arr;
	}
	
	private function outboxParams($callingElement){
		$return=array('html'=>'','Parameter'=>array(),'result'=>array());
		if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
		$options=$this->oc['SourcePot\Datapool\Root']->getRegisteredMethods('dataSink');
		$contentStructure=array('Outbox source'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>0,'options'=>$options),
								'When done'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'always','options'=>array('Keep entries','Delete sent entries')),
								'Save'=>array('htmlBuilderMethod'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
								);
		// get selctorB
		$arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);;
		$arr['selector']['Content']=array('Column to delay'=>'Name');
		$arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
		// form processing
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
		$elementId=key($formData['val']);
		if (isset($formData['cmd'][$elementId])){
			$arr['selector']['Content']=$formData['val'][$elementId]['Content'];
			$arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
		}
		// load outbox class
		if (isset($arr['selector']['Content']['Outbox source'])){$this->outboxClass=$arr['selector']['Content']['Outbox source'];}
		// get HTML
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Forward entries from outbox';
		$arr['noBtns']=TRUE;
		$row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
		if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
		$matrix=array('Parameter'=>$row);
		return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
	}

	private function outboxRules($callingElement){
		$contentStructure=array('Text'=>array('htmlBuilderMethod'=>'element','tag'=>'textarea','element-content'=>'','keep-element-content'=>TRUE,'excontainer'=>TRUE),
								' '=>array('htmlBuilderMethod'=>'element','tag'=>'p','keep-element-content'=>TRUE,'element-content'=>'OR'),
								'use column'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE),
								'Add to'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'always','options'=>array('Subject'=>'Subject','Message'=>'Message')),
								);
		$contentStructure['use column']+=$callingElement['Content']['Selector'];
		$arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Email creation rules';
		$html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	public function runOutboxEntries($callingElement,$testRun=1){
		$base=$this->getBaseArr($callingElement);
		$outboxParams=current($base['outboxparams']);
		$outboxParams=$outboxParams['Content'];
		if (isset($this->oc[$outboxParams['Outbox source']])){
			$outBoxMeta=$this->oc[$outboxParams['Outbox source']]->dataSink(array('callingClass'=>$callingElement['Folder']),'meta');
			$base['outBoxSettings']=$this->oc[$outboxParams['Outbox source']]->dataSink(array('callingClass'=>$callingElement['Folder']),'settings');
			// loop through source entries and parse these entries
			$this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
			$result=array('Outbox statistics'=>array('Emails sent'=>array('value'=>0),
													 'Entries removed'=>array('value'=>0),
													 'Emails failed'=>array('value'=>0),
													 'Entries processed'=>array('value'=>0),
													)
						 );
			foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE,'Read','Date',FALSE) as $entry){
				$result=$this->processEntry($entry,$base,$callingElement,$result,$testRun);
			}
			$result[$outboxParams['Outbox source']]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($outBoxMeta);
		} else {
			$result=array('Outbox statistics'=>array('Error'=>array('value'=>'Please select the outbox.')));
		}
		$result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
		$result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
		$result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
		return $result;
	}
	
	private function processEntry($entry,$base,$callingElement,$result,$testRun,$isDebugging=FALSE){
		$outboxParams=current($base['outboxparams']);
		$outboxParams=$outboxParams['Content'];
		$orgEntry=$entry;
		$flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
		$entry['Content']=array();
		// process outbox rules
		if (empty($base['outboxrules'])){
			$result['Outbox statistics']['Error']['Value']='Outbox rules missing';
			return $result;
		}
		foreach($base['outboxrules'] as $ruleId=>$rule){
			$flatKeyNeedle=$rule['Content']['use column'];
			$emailPart=$rule['Content']['Add to'];
			if (!empty($rule['Content']['Text'])){
				$entry['Content'][$emailPart]=(isset($entry['Content'][$emailPart]))?$entry['Content'][$emailPart].' '.$rule['Content']['Text']:$rule['Content']['Text'];
			} else if (!empty($flatEntry[$flatKeyNeedle])){
				$entry['Content'][$emailPart]=(isset($entry['Content'][$emailPart]))?$entry['Content'][$emailPart].' '.$flatEntry[$flatKeyNeedle]:$flatEntry[$flatKeyNeedle];
			}
		}
		$SubjectPrefix=$base['outBoxSettings']['Content']['Subject prefix'];
		$entry['Content']['Subject']=(empty($entry['Content']['Subject']))?$entry['Name']:$entry['Content']['Subject'];
		$entry['Content']['Subject']=(empty($SubjectPrefix))?$entry['Content']['Subject']:$SubjectPrefix.': '.$entry['Content']['Subject'];
		// create email
		$mail=array('selector'=>$entry);
		$mail['To']=$base['outBoxSettings']['Content']['Recipient e-mail address'];
		$mail['callingClass']=$callingElement['Folder'];
		if ($testRun){
			$result['Outbox statistics']['Emails sent']['value']++;	
		} else if ($this->oc[$outboxParams['Outbox source']]->dataSink($mail,'sendEntry')){
			if (empty(intval($outboxParams['When done']))){
				$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog2entry($orgEntry,'Processing log',array('forwarded'=>'By email to "'.$mail['To'].'"'),TRUE);
			} else {
				$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($orgEntry,TRUE);
				$result['Outbox statistics']['Entries removed']['value']++;
			}
			$result['Outbox statistics']['Emails sent']['value']++;	
		} else {
			$result['Outbox statistics']['Emails failed']['value']++;	
		}
		$result['Outbox statistics']['Entries processed']['value']++;
		$emailIndex=(isset($result['Content created by outbox rules']))?count($result['Content created by outbox rules'])+1:1;
		$emailCaption='Sample '.$emailIndex;
		if ($emailIndex<2 || mt_rand(0,100)>80){
			$result['Content created by outbox rules'][$emailCaption]=array();
			if (isset($mail['To'])){$result['Content created by outbox rules'][$emailCaption]['To']=$mail['To'];}
			if (isset($mail['selector']['Content']['Subject'])){$result['Content created by outbox rules'][$emailCaption]['Subject']=$mail['selector']['Content']['Subject'];}
			if (isset($mail['selector']['Content']['Message'])){$result['Content created by outbox rules'][$emailCaption]['Message']=$mail['selector']['Content']['Message'];}
		}
		if ($isDebugging){
			$debugArr=array('base'=>$base,'entry'=>$orgEntry,'flatEntry'=>$flatEntry,'mail'=>$mail);
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $result;
	}
	
	public function callingElement2arr($callingClass,$callingFunction,$callingElement){
		if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
		$type=$this->oc['SourcePot\Datapool\Root']->class2source(__CLASS__);
		$type.='|'.$callingFunction;
		$entry=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
		$entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Type'),0);
		$entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ALL_CONTENTADMIN_R');
		$entry['Content']=array();
		$arr=array('callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'selector'=>$entry);
		return $arr;
	}

	private function getBaseArr($callingElement){
		$base=array('Script start timestamp'=>hrtime(TRUE));
		$entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId']);
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
			$key=explode('|',$entry['Type']);
			$key=array_pop($key);
			$base[$key][$entry['EntryId']]=$entry;
			// entry template
			foreach($entry['Content'] as $contentKey=>$content){
				if (is_array($content)){continue;}
				if (strpos($content,'EID')!==0 || strpos($content,'eid')===FALSE){continue;}
				$template=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->entryId2selector($content);
				if ($template){$base['entryTemplates'][$content]=$template;}
			}
		}
		return $base;
	}

}
?>