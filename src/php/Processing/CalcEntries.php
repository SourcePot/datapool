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

class CalcEntries{
	
	use \SourcePot\Datapool\Traits\Conversions;
	
	private $arr;
	private $ruleOptions=array();

	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );

	private $dataTypes=array('string'=>'String','stringNoWhitespaces'=>'String without whitespaces','splitString'=>'Split string','int'=>'Integer','float'=>'Float','bool'=>'Boolean','money'=>'Money','date'=>'Date','codepfad'=>'Codepfad','unycom'=>'UNYCOM file number');
	private $failureCondition=array('stripos'=>'&#8839;','stripos!'=>"&#8837;",'lt'=>'&#60;','le'=>'&#8804;','eq'=>'&#61;','ne'=>'&#8800;','gt'=>'&#62;','ge'=>'&#8805;');
	private $conditionalValue=array('lt'=>'&#60; 0','gt'=>"&#62; 0",'eq'=>'&#61; 0','ne'=>'&#8800; 0');
		
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

	public function dataProcessor($callingElementSelector=array(),$action='info'){
		// This method is the interface of this data processing class
		// The Argument $action selects the method to be invoked and
		// argument $callingElementSelector$ provides the entry which triggerd the action.
		// $callingElementSelector ... array('Source'=>'...', 'EntryId'=>'...', ...)
		// If the requested action does not exist the method returns FALSE and 
		// TRUE, a value or an array otherwise.
		$callingElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
		// get action
		switch($action){
			case 'run':
				if (empty($callingElement)){
					return TRUE;
				} else {
				return $this->runCalcEntries($callingElement,$testRunOnly=FALSE);
				}
				break;
			case 'test':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runCalcEntries($callingElement,$testRunOnly=TRUE);
				}
				break;
			case 'widget':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getCalcEntriesWidget($callingElement);
				}
				break;
			case 'settings':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getCalcEntriesSettings($callingElement);
				}
				break;
			case 'info':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getCalcEntriesInfo($callingElement);
				}
				break;
		}
		return FALSE;
	}

	private function getCalcEntriesWidget($callingElement){
		return $this->arr['SourcePot\Datapool\Foundation\Container']->container('Calculate','generic',$callingElement,array('method'=>'getCalcEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getCalcEntriesWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=array();
		$formData=$this->arr['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['run'])){
			$result=$this->runCalcEntries($arr['selector'],FALSE);
		} else if (isset($formData['cmd']['test'])){
			$result=$this->runCalcEntries($arr['selector'],TRUE);
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
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Calculate widget'));
		foreach($result as $caption=>$matrix){
			if (!is_array($matrix)){continue;}
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		$arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
		return $arr;
	}

	private function getCalcEntriesSettings($callingElement){
		$html='';
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Calculate entries settings','generic',$callingElement,array('method'=>'getCalcEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getCalcEntriesSettingsHtml($arr){
		// initialize rule options
		$entriesSelector=array('Source'=>$this->entryTable,'Name'=>$arr['selector']['EntryId']);
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
			if (strpos($entry['Type'],'rules')===FALSE || strpos($entry['Type'],'|')===FALSE){continue;}
			$typeComps=explode('|',$entry['Type']);
			$rulePrefix=str_replace('rules',' rule',$typeComps[1]);
			$ruleIndex=$this->ruleId2ruleIndex($entry['EntryId'],ucfirst($rulePrefix));
			$this->ruleOptions[$typeComps[1]][$ruleIndex]=$ruleIndex;
		}
		// get html
		if (!isset($arr['html'])){$arr['html']='';}
		$arr['html'].=$this->calculationParams($arr['selector']);
		$arr['html'].=$this->calculationRules($arr['selector']);
		$arr['html'].=$this->conditionalValueRules($arr['selector']);
		$arr['html'].=$this->failureRules($arr['selector']);
		//$selectorMatrix=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
		//$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for calculation'));
		return $arr;
	}

	private function calculationParams($callingElement){
		$contentStructure=array('Target on success'=>array('htmlBuilderMethod'=>'canvasElementSelect','excontainer'=>TRUE),
								'Target on failure'=>array('htmlBuilderMethod'=>'canvasElementSelect','excontainer'=>TRUE),
								'Save'=>array('htmlBuilderMethod'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
								);
		// get selctor
		$arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
		$arr['selector']['Content']=array();
		$arr['selector']=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
		// form processing
		$formData=$this->arr['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
		$elementId=key($formData['val']);
		if (isset($formData['cmd'][$elementId])){
			$arr['selector']['Content']=$formData['val'][$elementId]['Content'];
			$arr['selector']=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
		}
		// get HTML
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Calculation control';
		$arr['noBtns']=TRUE;
		$row=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
		if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
		$matrix=array('Parameter'=>$row);
		return $this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
	}
	
	private function calculationRules($callingElement){
		$addKeys=(isset($this->ruleOptions[strtolower(__FUNCTION__)]))?$this->ruleOptions[strtolower(__FUNCTION__)]:array();
		$contentStructure=array('"A" selected by...'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>$addKeys),
								'Default value "A"'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Operation'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>array('+'=>'+','-'=>'-','*'=>'*','/'=>'/')),
								'"B" selected by...'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>$addKeys),
								'Default value "B"'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								''=>array('htmlBuilderMethod'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE),
								'Target data type'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->dataTypes),
								'Target column'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
								'Target key'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$contentStructure['"A" selected by...']+=$callingElement['Content']['Selector'];
		$contentStructure['"B" selected by...']+=$callingElement['Content']['Selector'];
		$contentStructure['Target column']+=$callingElement['Content']['Selector'];
		$arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Calculation rules';
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	private function failureRules($callingElement){
		$addKeys=(isset($this->ruleOptions['calculationrules']))?$this->ruleOptions['calculationrules']:array();
		$contentStructure=array('Value'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>current($addKeys),'addSourceValueColumn'=>FALSE,'addColumns'=>$addKeys),
								'Failure if Result...'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'stripos','keep-element-content'=>TRUE,'options'=>$this->failureCondition),
								'Compare value'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$contentStructure['Value']+=$callingElement['Content']['Selector'];
		$arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Failure rules';
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	private function conditionalValueRules($callingElement){
		$addKeys=(isset($this->ruleOptions['calculationrules']))?$this->ruleOptions['calculationrules']:array();
		$contentStructure=array('Condition'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>current($addKeys),'addSourceValueColumn'=>FALSE,'addColumns'=>$addKeys),
								'Use value if...'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'eq','keep-element-content'=>TRUE,'options'=>$this->conditionalValue),
								''=>array('htmlBuilderMethod'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE),
								'Value'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Target column'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
								'Target key'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$contentStructure['Condition']+=$callingElement['Content']['Selector'];
		$contentStructure['Target column']+=$callingElement['Content']['Selector'];
		$arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Conditional value rules';
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	private function runCalcEntries($callingElement,$testRun=FALSE){
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
		// loop through source entries and parse these entries
		$this->arr['SourcePot\Datapool\Foundation\Database']->resetStatistic();
		$result=array('Calculate statistics'=>array('Entries'=>array('value'=>0),
													'Failure'=>array('value'=>0),
													'Success'=>array('value'=>0),
													)
					);
		// loop through entries
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
			if ($entry['isSkipRow']){
				$result['Calculate statistics']['Skip rows']['value']++;
				continue;
			}
			$result=$this->calcEntry($base,$sourceEntry,$result,$testRun);
		}
		$result['Statistics']=$this->arr['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
		$result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
		$result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
		return $result;
	}
	
	private function calcEntry($base,$sourceEntry,$result,$testRun){
		$debugArr=array();
		$log='';
		$params=current($base['calculationparams']);
		$flatSourceEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
		// loop through calculation rules
		$ruleResults=array();
		if (!empty($base['calculationrules'])){
			foreach($base['calculationrules'] as $ruleEntryId=>$rule){
				$calculationRuleIndex=$this->ruleId2ruleIndex($ruleEntryId,'Calculation rule');
				foreach(array('A','B') as $index){
					$key=$rule['Content']['"'.$index.'" selected by...'];
					$debugArr[]=array('ruleEntryId'=>$calculationRuleIndex,'key'=>$key);
					if (strcmp($key,'useValue')===0){
						$value[$index]=floatval($rule['Content']['Default value "'.$index.'"']);
					} else if (isset($ruleResults[$key])){
						$value[$index]=floatval($ruleResults[$key]);
					} else if (isset($flatSourceEntry[$key])){
						$value[$index]=floatval($flatSourceEntry[$key]);
					} else {
						$value[$index]=floatval($rule['Content']['Default value "'.$index.'"']);
					}
				}
				$ruleResults[$calculationRuleIndex]=match($rule['Content']['Operation']){
						'+'=>$value['A']+$value['B'],
						'-'=>$value['A']-$value['B'],
						'*'=>$value['A']*$value['B'],
						'/'=>($value['B']==0)?FALSE:($value['A']/$value['B']),
						'%'=>($value['B']==0)?FALSE:($value['A']%$value['B']),
				};
				$sourceEntry=$this->addValue2flatEntry($sourceEntry,$rule['Content']['Target column'],$rule['Content']['Target key'],$ruleResults[$calculationRuleIndex],$rule['Content']['Target data type']);
				$result['Calc rule'][$calculationRuleIndex]=array('A'=>$value['A'],'Operation'=>$rule['Content']['Operation'],'B'=>$value['B'],'Result'=>$ruleResults[$calculationRuleIndex]);
			}
		}
		// loop through conditional value rules
		if (!empty($base['conditionalvaluerules'])){
			foreach($base['conditionalvaluerules'] as $ruleEntryId=>$rule){
				$conditionalvalueRuleIndex=$this->ruleId2ruleIndex($ruleEntryId,'Conditionalvalue rule');
				if (isset($ruleResults[$rule['Content']['Condition']])){
					$value=$ruleResults[$rule['Content']['Condition']];
				} else if (isset($flatSourceEntry[$rule['Content']['Condition']])){
					$value=$flatSourceEntry[$rule['Content']['Condition']];
				} else {
					$ruleResults[$conditionalvalueRuleIndex]=FALSE;
				}
				if (!isset($ruleResults[$conditionalvalueRuleIndex])){
					$ruleResults[$conditionalvalueRuleIndex]=match($rule['Content']['Use value if...']){
						'lt'=>floatval($value)<0,
						'gt'=>floatval($value)>0,
						'eq'=>intval($value)==0,
						'ne'=>intval($value)!=0,
					};
				}
				$log.='|'.$conditionalvalueRuleIndex.' = '.intval($ruleResults[$conditionalvalueRuleIndex]);
				if ($ruleResults[$conditionalvalueRuleIndex]){
					$sourceEntry[$rule['Content']['Target column']][$rule['Content']['Target key']]=$rule['Content']['Value'];
				}
				$result['Conditional value rules'][$conditionalvalueRuleIndex]=array('Condition'=>$value,
																	   'Use value if'=>$this->conditionalValue[$rule['Content']['Use value if...']],
																	   'Condition met'=>$this->arr['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleResults[$conditionalvalueRuleIndex]),
																	   );
			}
		}
		// loop through failurerules rules
		$isFailure=FALSE;
		if (!empty($base['failurerules'])){
			foreach($base['failurerules'] as $ruleEntryId=>$rule){
				$failureRuleIndex=$this->ruleId2ruleIndex($ruleEntryId,'Failure rule');
				if (isset($ruleResults[$rule['Content']['Value']])){
					$value=$ruleResults[$rule['Content']['Value']];
				} else if (isset($flatSourceEntry[$rule['Content']['Value']])){
					$value=$flatSourceEntry[$rule['Content']['Value']];
				} else {
					$ruleResults[$failureRuleIndex]=FALSE;
				}
				if (!isset($ruleResults[$failureRuleIndex])){
					$ruleResults[$failureRuleIndex]=match($rule['Content']['Failure if Result...']){
						'stripos'=>stripos($value,$rule['Content']['Compare value'])!==FALSE,
						'stripos!'=>stripos($value,$rule['Content']['Compare value'])===FALSE,
						'lt'=>floatval($value)<floatval($rule['Content']['Compare value']),
						'le'=>floatval($value)<=floatval($rule['Content']['Compare value']),
						'gt'=>floatval($value)>floatval($rule['Content']['Compare value']),
						'ge'=>floatval($value)>=floatval($rule['Content']['Compare value']),
						'eq'=>floatval($value)==floatval($rule['Content']['Compare value']),
						'ne'=>floatval($value)!=floatval($rule['Content']['Compare value']),
					};
				}
				$log.='|'.$failureRuleIndex.' = '.intval($ruleResults[$failureRuleIndex]);
				if ($ruleResults[$failureRuleIndex]){$isFailure=TRUE;}
				$result['Failure rules'][$failureRuleIndex]=array('Value'=>$value,
														   'Failure if Result'=>$this->failureCondition[$rule['Content']['Failure if Result...']],
														   'Compare value'=>$rule['Content']['Compare value'],
														   'Condition met'=>$this->arr['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleResults[$failureRuleIndex]),
														   );
			}
		}
		// wrapping up
		foreach($sourceEntry as $key=>$value){
			if (strpos($key,'Content')===0 || strpos($key,'Params')===0){continue;}
			if (!is_array($value)){continue;}
			foreach($value as $subKey=>$subValue){
				$value[$subKey]=$this->getStdValueFromValueArr($subValue);
			}
			// set order of array values
			ksort($value);
			$sourceEntry[$key]=implode('|',$value);
		}
		$result['Calculate statistics']['Entries']['value']++;
		if ($isFailure){
			$result['Calculate statistics']['Failure']['value']++;
			$sourceEntry=$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog2entry($sourceEntry,'Processing log',array('failed'=>trim($log,'| ')),FALSE);
			$sourceEntry=$this->arr['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Content']['Target on failure']],TRUE,$testRun);
				if (!isset($result['Sample result (failure)']) || mt_rand(0,100)>90){
				$result['Sample result (failure)']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($sourceEntry);
			}	
		} else {
			$result['Calculate statistics']['Success']['value']++;
			$sourceEntry=$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog2entry($sourceEntry,'Processing log',array('success'=>trim($log,'| ')),FALSE);
			$sourceEntry=$this->arr['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Content']['Target on success']],TRUE,$testRun);
				if (!isset($result['Sample result (success)']) || mt_rand(0,100)>90){
				$result['Sample result (success)']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($sourceEntry);
			}		
		}
		return $result;
	}
	
	private function getStdValueFromValueArr($value,$useKeyIfPresent='NOBODY-SHOULD-USE-THIS-KEY-IN-THE-VALUEARR'){
		if (isset($value[$useKeyIfPresent])){
			$value=$value[$useKeyIfPresent];
		} else if (is_array($value)){
			reset($value);
			$value=current($value);
		}
		return $value;
	}

	private function addValue2flatEntry($entry,$baseKey,$key,$value,$dataType){
		$dataTypeMethod='convert2'.$dataType;
		if (!isset($entry[$baseKey])){$entry[$baseKey]=array();}
		if (!is_array($entry[$baseKey]) && empty($key)){$entry[$baseKey]=array();}
		$newValue=array($key=>$this->$dataTypeMethod($value));
		if (is_array($entry[$baseKey])){
			$entry[$baseKey]=array_replace_recursive($entry[$baseKey],$newValue);
		} else {
			$entry[$baseKey]=$newValue;
		}
		return $entry;
	}
	
	private function ruleId2ruleIndex($ruleId,$ruleType='Calc rule'){
		$ruleIndex=substr($ruleId,0,strpos($ruleId,'__'));
		$ruleIndex=$ruleType.' '.$ruleIndex;
		return $ruleIndex;
	}
	
	public function callingElement2arr($callingClass,$callingFunction,$callingElement){
		if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
		$type=$this->arr['SourcePot\Datapool\Root']->class2source(__CLASS__);
		$type.='|'.$callingFunction;
		$entry=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
		$entry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Type'),0);
		$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ALL_CONTENTADMIN_R');
		$entry['Content']=array();
		$arr=array('callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'selector'=>$entry);
		return $arr;
	}


}
?>