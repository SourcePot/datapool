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

class InboxEntries{
	
	private $arr;
	
	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );
	
	private $inboxClass='';
	private $base=array();

	private $conditions=array('stripos'=>'contains',
							 'stripos!'=>'does not contain',
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
	
	public function dataProcessor($action='info',$callingElementSelector=array()){
		// This method is the interface of this data processing class
		// The Argument $action selects the method to be invoked and
		// argument $callingElementSelector$ provides the entry which triggerd the action.
		// $callingElementSelector ... array('Source'=>'...', 'EntryId'=>'...', ...)
		// If the requested action does not exist the method returns FALSE and 
		// TRUE, a value or an array otherwise.
		$callingElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
		switch($action){
			case 'run':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runInboxEntries($callingElement,$testRunOnly=0);
				}
				break;
			case 'test':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runInboxEntries($callingElement,$testRunOnly=1);
				}
				break;
			case 'widget':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getInboxEntriesWidget($callingElement);
				}
				break;
			case 'settings':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getInboxEntriesSettings($callingElement);
				}
				break;
			case 'info':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getInboxEntriesInfo($callingElement);
				}
				break;
		}
		return FALSE;
	}

	private function getInboxEntriesWidget($callingElement){
		return $this->arr['SourcePot\Datapool\Foundation\Container']->container('Inbox','generic',$callingElement,array('method'=>'getInboxEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getInboxEntriesWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=array();
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['run'])){
			$result=$this->runInboxEntries($arr['selector'],0);
		} else if (isset($formData['cmd']['test'])){
			$result=$this->runInboxEntries($arr['selector'],1);
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
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Inbox widget'));
		foreach($result as $caption=>$matrix){
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		$arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
		return $arr;
	}

	private function getInboxEntriesSettings($callingElement){
		$html='';
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Inbox entries settings','generic',$callingElement,array('method'=>'getInboxEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getInboxEntriesSettingsHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$arr['html'].=$this->inboxParams($arr['selector']);
		$dataSourceArr=$arr;
		$dataSourceArr['callingClass']=$arr['selector']['Folder'];
		$arr=$this->arr[$this->inboxClass]->dataSource($dataSourceArr,'settingsWidget');
		$arr['html'].=$this->inboxConditionRules($arr['selector']);
		$arr['html'].=$this->inboxForwardingRules($arr['selector']);
		return $arr;
	}
	
	private function inboxParams($callingElement){
		$return=array('html'=>'','Parameter'=>array(),'result'=>array());
		if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
		$contentStructure=array('Inbox source'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>0,'options'=>$this->arr['registered methods']['dataSource']),
								'Save'=>array('htmlBuilderMethod'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
							);
		// get selctorB
		$inboxParams=$this->callingElement2selector(__FUNCTION__,$callingElement,TRUE);;
		$inboxParams=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($inboxParams,'ALL_R','ALL_CONTENTADMIN_R');
		$inboxParams['Content']=array('Column to delay'=>'Name');
		$inboxParams=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($inboxParams,TRUE);
		// form processing
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$elementId=key($formData['val']);
		if (isset($formData['cmd'][$elementId])){
			$inboxParams['Content']=$formData['val'][$elementId]['Content'];
			$inboxParams=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($inboxParams,TRUE);
			// synchronize with data source
			$callingElement['Content']['Selector']=$this->arr[$inboxParams['Content']['Inbox source']]->dataSource(array('callingClass'=>$callingElement['Folder']),'selector');
			$callingElement=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($callingElement,TRUE);
		}
		// load inbox class
		$this->inboxClass=$inboxParams['Content']['Inbox source'];
		// get HTML
		$arr=$inboxParams;
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Forward entries from inbox';
		$arr['noBtns']=TRUE;
		$matrix=array('Parameter'=>$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE));
		return $this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
	}

	private function inboxConditionRules($callingElement){
		$contentStructure=array('Column'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
								'Condition'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'stripos','options'=>$this->conditions),
								'Value A'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								' '=>array('htmlBuilderMethod'=>'element','tag'=>'p','keep-element-content'=>TRUE,'element-content'=>'OR'),
								'Value B'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$contentStructure['Column']+=$callingElement['Content']['Selector'];
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Define conditions';
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}
		
	private function inboxForwardingRules($callingElement){
		$base=$this->getBaseArr($callingElement);
		$conditionRuleOptions=array(''=>'-');
		if (!empty($base['inboxconditionrules'])){
			foreach($base['inboxconditionrules'] as $ruleId=>$rule){
				$ruleIndex=substr($ruleId,0,strpos($ruleId,'_'));
				$conditionRuleOptions[$ruleId]='Rule '.$ruleIndex;
			}
		}
		$contentStructure=array('Condition A'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'stripos','options'=>$conditionRuleOptions),
								' '=>array('htmlBuilderMethod'=>'element','tag'=>'p','keep-element-content'=>TRUE,'element-content'=>'AND'),
								'Condition B'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'stripos','options'=>$conditionRuleOptions),
								'  '=>array('htmlBuilderMethod'=>'element','tag'=>'p','keep-element-content'=>TRUE,'element-content'=>'AND'),
								'Condition C'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'stripos','options'=>$conditionRuleOptions),
								'Forward to'=>array('htmlBuilderMethod'=>'canvasElementSelect','excontainer'=>TRUE),
								);
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Forwarding conditions';
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	public function runInboxEntries($callingElement,$testRun=1){
		
		$base=$this->getBaseArr($callingElement);
		
		//$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($base);
		
		$inboxParams=current($base['inboxparams']);
		$inboxParams=$inboxParams['Content'];
		$meta=$this->arr[$inboxParams['Inbox source']]->dataSource(array('callingClass'=>$callingElement['Folder']),'meta');
		// loop through source entries and parse these entries
		$this->arr['SourcePot\Datapool\Foundation\Database']->resetStatistic();
		$result=array('Inbox statistics'=>array('Items processed'=>array('value'=>0),
												'Itmes forwarded'=>array('value'=>0),
												'Itmes already processed and skipped'=>array('value'=>0),
												)
					 );
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE,'Read','Date',FALSE) as $entry){
			$result=$this->processEntry($entry,$base,$callingElement,$result,$testRun);
			$result['Inbox statistics']['Items processed']['value']++;
		}
		if ($testRun){$result[$inboxParams['Inbox source']]=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($meta);}
		$result['Statistics']=$this->arr['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
		$result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
		$result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
		return $result;
	}
	
	private function processEntry($entry,$base,$callingElement,$result,$testRun,$isDebugging=FALSE){
		$userId=empty($_SESSION['currentUser']['EntryId'])?'ANONYM':$_SESSION['currentUser']['EntryId'];
		$params=current($base['inboxparams']);
		$flatEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
		// process condition rules
		if (empty($base['inboxconditionrules'])){
			$result['Statistics']['Error']['value']='Condition rules missing';
			return $result;
		}
		$ruleArr=array();
		foreach($base['inboxconditionrules'] as $ruleId=>$rule){
			$ruleIndex=substr($ruleId,0,strpos($ruleId,'_'));
			$conditionMet[$ruleId]=FALSE;
			foreach($flatEntry as $flatKey=>$value){
				if (strpos($flatKey,$rule['Content']['Column'])!==0){continue;}
				$needlePosA=(empty($rule['Content']['Value A']))?FALSE:mb_stripos($value,$rule['Content']['Value A']);
				$needlePosB=(empty($rule['Content']['Value B']))?FALSE:mb_stripos($value,$rule['Content']['Value B']);
				$conditionMet[$ruleId]=match($rule['Content']['Condition']){
					'stripos'=>$needlePosA!==FALSE || $needlePosB!==FALSE,
					'stripos!'=>$needlePosA===FALSE && $needlePosB===FALSE,
				};
				if ($conditionMet[$ruleId]){break;}
			}
			$ruleArr['Condition rule '.$ruleIndex]['Failed']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->bool2element($conditionMet[$ruleId]===FALSE);
		}
		// process forwarding rules
		if (empty($base['inboxforwardingrules'])){
			$result['Statistics']['Error']['value']='Forwarding rules missing';
			return $result;
		}
		foreach($base['inboxforwardingrules'] as $ruleId=>$rule){
			$ruleIndex=substr($ruleId,0,strpos($ruleId,'_'));
			$forward[$ruleId]=TRUE;
			foreach($rule['Content'] as $key=>$condRuleId){
				if (strpos($key,'Condition')===FALSE || empty($condRuleId)){continue;}
				if (empty($conditionMet[$condRuleId])){
					$forward[$ruleId]=FALSE;
					break;
				}
			}
			$ruleArr['Forward rule '.$ruleIndex]['Failed']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->bool2element($forward[$ruleId]===FALSE);
			if ($forward[$ruleId]){$forward[$ruleId]=$rule['Content']['Forward to'];}
		}
		// forward relevant entries
		$inboxEntry=$entry;
		foreach($forward as $ruleId=>$forwardTo){
			$ruleIndex=substr($ruleId,0,strpos($ruleId,'_'));
			if ($forwardTo){
				// conditions to forward entry  met
				$statisticsKey='Forwarding rule '.$ruleIndex.' success';
				$targetSelector=$base['entryTemplates'][$forwardTo];
				$processingLogText='Rule "'.$ruleId.'" condition met, forwarded entry to "'.implode(' &rarr; ',$targetSelector).'"';
				$inboxEntry=$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog2entry($entry,'Processing log',array('forwarded'=>$processingLogText),FALSE);
				if ($this->itemAlreadyProcessed($entry,$processingLogText)){
					$result['Inbox statistics']['Itmes already processed and skipped']['value']++;
				} else {
					$targetEntry=$this->arr['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($inboxEntry,$targetSelector,TRUE,$testRun,TRUE,TRUE);
					$result['Inbox statistics']['Itmes forwarded']['value']++;
				}
			} else {
				// conditions to forward entry NOT met
				$statisticsKey='Forwarding rule '.$ruleIndex.' failed';
				$targetEntry=$entry;
				
			}
			// update statistic
			if (isset($result['Inbox statistics'][$statisticsKey]['value'])){
				$result['Inbox statistics'][$statisticsKey]['value']++;
			} else {
				$result['Inbox statistics'][$statisticsKey]['value']=1;
			}
		}
		if (count($result)<6 || (count($result)<30 && mt_rand(1,100)>70)){
			$caption='E.g.: '.substr($entry['Name'],0,25);
			$result[$caption]=$ruleArr;
		}
		if ($isDebugging){
			$debugArr['result']=$result;
			$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $result;
	}
	
	private function itemAlreadyProcessed($item,$processingLogText){
		if (!isset($item['Params']['Processing log'])){return FALSE;}
		foreach($item['Params']['Processing log'] as $log){
			if (!isset($log['forwarded'])){continue;}
			if (strcmp($log['forwarded'],$processingLogText)===0){
				return TRUE;
			}
		}
		return FALSE;
	}
	
	public function callingElement2selector($callingFunction,$callingElement,$selectsUniqueEntry=FALSE){
		if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
		$type=$this->arr['class2source'][__CLASS__];
		$type.='|'.$callingFunction;
		$entrySelector=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
		if ($selectsUniqueEntry){$entrySelector=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entrySelector,array('Group','Folder','Name','Type'),0);}
		return $entrySelector;
	}

	private function getBaseArr($callingElement){
		$base=array('Script start timestamp'=>hrtime(TRUE));
		$entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId']);
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
			$key=explode('|',$entry['Type']);
			$key=array_pop($key);
			$base[$key][$entry['EntryId']]=$entry;
			// entry template
			foreach($entry['Content'] as $contentKey=>$content){
				if (is_array($content)){continue;}
				if (strpos($content,'EID')!==0 || strpos($content,'eid')===FALSE){continue;}
				$template=$this->arr['SourcePot\Datapool\Foundation\DataExplorer']->entryId2selector($content);
				if ($template){$base['entryTemplates'][$content]=$template;}
			}
		}
		return $base;
	}

}
?>