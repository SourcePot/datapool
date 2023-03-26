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

class CalcEntries{
	
	use \SourcePot\Datapool\Traits\Conversions;
	
	private $arr;

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
		$return=array();
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['run'])){
			$return=$this->runCalcEntries($arr['selector'],FALSE);
		} else if (isset($formData['cmd']['test'])){
			$return=$this->runCalcEntries($arr['selector'],TRUE);
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
		foreach($return as $caption=>$matrix){
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
		$calculationParams=$this->callingElement2selector(__FUNCTION__,$callingElement,TRUE);
		if (empty($calculationParams)){return '';}
		$calculationParams=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($calculationParams,'ALL_R','ALL_CONTENTADMIN_R');
		$calculationParams['Content']=array();
		$calculationParams=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($calculationParams,TRUE);
		// form processing
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$elementId=key($formData['val']);
		if (isset($formData['cmd'][$elementId])){
			$calculationParams['Content']=$formData['val'][$elementId]['Content'];
			$calculationParams=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($calculationParams);
		}
		// get HTML
		$arr=$calculationParams;
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Calculation control';
		$arr['noBtns']=TRUE;
		$row=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
		if (empty($calculationParams['Content'])){$row['setRowStyle']='background-color:#a00;';}
		$matrix=array('Parameter'=>$row);
		return $this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
	}
	
	private function calculationRules($callingElement){
		$addKeys=array('0001'=>'Result 0001','0002'=>'Result 0002','0003'=>'Result 0003','0004'=>'Result 0004','0005'=>'Result 0005','0006'=>'Result 0006','0007'=>'Result 0007','0008'=>'Result 0008','0009'=>'Result 0009');
		$contentStructure=array('"A" selected by...'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>$addKeys),
								'or value "A"'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Operation'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>array('+'=>'+','-'=>'-','*'=>'*','/'=>'/')),
								'"B" selected by...'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>$addKeys),
								'or value "B"'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								''=>array('htmlBuilderMethod'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE),
								'Target data type'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->dataTypes),
								'Target column'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
								'Target key'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$contentStructure['"A" selected by...']+=$callingElement['Content']['Selector'];
		$contentStructure['"B" selected by...']+=$callingElement['Content']['Selector'];
		$contentStructure['Target column']+=$callingElement['Content']['Selector'];
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Calculation rules';
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	private function failureRules($callingElement){
		$addKeys=array('0001'=>'Result 0001','0002'=>'Result 0002','0003'=>'Result 0003','0004'=>'Result 0004','0005'=>'Result 0005','0006'=>'Result 0006','0007'=>'Result 0007','0008'=>'Result 0008','0009'=>'Result 0009');
		$contentStructure=array('Value'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>FALSE,'addColumns'=>$addKeys),
								'Failure if Result...'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'stripos','options'=>$this->failureCondition),
								'Compare value'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$contentStructure['Value']+=$callingElement['Content']['Selector'];
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Failure rules';
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	private function conditionalValueRules($callingElement){
		$addKeys=array('0001'=>'Result 0001','0002'=>'Result 0002','0003'=>'Result 0003','0004'=>'Result 0004','0005'=>'Result 0005','0006'=>'Result 0006','0007'=>'Result 0007','0008'=>'Result 0008','0009'=>'Result 0009');
		$contentStructure=array('Condition'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>FALSE,'addColumns'=>$addKeys),
								'Use value if...'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'eq','options'=>$this->conditionalValue),
								''=>array('htmlBuilderMethod'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE),
								'Value'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Target column'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
								'Target key'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$contentStructure['Condition']+=$callingElement['Content']['Selector'];
		$contentStructure['Target column']+=$callingElement['Content']['Selector'];
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Conditional value rules';
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	private function runCalcEntries($callingElement,$testRun=FALSE){
		$base=array();
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
		// loop through source entries and parse these entries
		$this->arr['SourcePot\Datapool\Foundation\Database']->resetStatistic();
		$return=array('Calculate statistics'=>array('Entries'=>array('value'=>0),
													'Failure'=>array('value'=>0),
													'Success'=>array('value'=>0),
													)
					);
		// loop through entries
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector']) as $sourceEntry){
			if ($entry['isSkipRow']){
				$return['Calculate statistics']['Skip rows']['value']++;
				continue;
			}
			$return=$this->calcEntry($base,$sourceEntry,$return,$testRun);
		}
		$statistics=$this->arr['SourcePot\Datapool\Foundation\Database']->getStatistic();
		$return['Statistics']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($statistics);
		return $return;
	}
	
	private function calcEntry($base,$sourceEntry,$return,$testRun){
		$debugArr=array();
		$params=current($base['calculationparams']);
		$targetEntry=array();
		$flatSourceEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
		// loop through calculation rules
		if (!empty($base['calculationrules'])){
			foreach($base['calculationrules'] as $ruleEntryId=>$rule){
				$ruleIndex=substr($ruleEntryId,0,strpos($ruleEntryId,'__'));
				foreach(array('A','B') as $index){
					$key=$rule['Content']['"'.$index.'" selected by...'];
					$debugArr[]=array('ruleEntryId'=>$ruleIndex,'key'=>$key);
					if (strcmp($key,'useValue')===0){
						$value[$index]=$rule['Content']['or value "'.$index.'"'];
					} else if (isset($result[$key])){
						$value[$index]=$result[$key];
					} else if (isset($flatSourceEntry[$key])){
						$value[$index]=$flatSourceEntry[$key];
					} else {
						$value[$index]='{{Key missing}}';
					}
				}
				$result[$ruleIndex]=match($rule['Content']['Operation']){
					'+'=>floatval($value['A'])+floatval($value['B']),
					'-'=>floatval($value['A'])-floatval($value['B']),
					'*'=>floatval($value['A'])*floatval($value['B']),
					'/'=>(floatval($value['B'])===0)?'NaN':floatval($value['A'])/floatval($value['B']),
					'%'=>(floatval($value['B'])===0)?'NaN':floatval($value['A'])%floatval($value['B']),
				};
				$sourceEntry=$this->addValue2flatEntry($sourceEntry,$rule['Content']['Target column'],$rule['Content']['Target key'],$result[$ruleIndex],$rule['Content']['Target data type']);
				$return['Calc rule'][$ruleIndex]=array('A'=>$value['A'],'Operation'=>$rule['Content']['Operation'],'B'=>$value['B'],'Result'=>$result[$ruleIndex]);
			}
		}
		// loop through calculation rules
		if (!empty($base['conditionalvaluerules'])){
			foreach($base['conditionalvaluerules'] as $ruleEntryId=>$rule){
				if (isset($result[$rule['Content']['Condition']])){
					$value=$result[$rule['Content']['Condition']];
				} else if (isset($flatSourceEntry[$rule['Content']['Condition']])){
					$value=$flatSourceEntry[$rule['Content']['Condition']];
				} else {
					$value='{{Key missing}}';
				}
				$conditionMet=match($rule['Content']['Use value if...']){
					'lt'=>floatval($value)<0,
					'gt'=>floatval($value)>0,
					'eq'=>floatval($value)==0,
					'ne'=>floatval($value)!=0,
				};
				if ($conditionMet){$sourceEntry[$rule['Content']['Target column']][$rule['Content']['Target key']]=$rule['Content']['Value'];}
			}
		}
		// loop through failurerules rules
		$failureMet=FALSE;
		if (!empty($base['failurerules'])){
			foreach($base['failurerules'] as $ruleEntryId=>$rule){
				if (isset($result[$rule['Content']['Value']])){
					$value=$result[$rule['Content']['Value']];
				} else if (isset($flatSourceEntry[$rule['Content']['Value']])){
					$value=$flatSourceEntry[$rule['Content']['Value']];
				} else {
					$value='{{Key missing}}';
				}
				$failureMet=match($rule['Content']['Failure if Result...']){
					'stripos'=>stripos($value,$rule['Content']['Compare value'])!==FALSE,
					'stripos!'=>stripos($value,$rule['Content']['Compare value'])===FALSE,
					'lt'=>floatval($value)<floatval($rule['Content']['Compare value']),
					'le'=>floatval($value)<=floatval($rule['Content']['Compare value']),
					'gt'=>floatval($value)>floatval($rule['Content']['Compare value']),
					'ge'=>floatval($value)>=floatval($rule['Content']['Compare value']),
					'eq'=>floatval($value)==floatval($rule['Content']['Compare value']),
					'ne'=>floatval($value)!=floatval($rule['Content']['Compare value']),
				};
				if ($failureMet){break;}
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
		$return['Calculate statistics']['Entries']['value']++;
		if ($failureMet){
			$sourceEntry=array_replace_recursive($sourceEntry,$base['entryTemplates'][$params['Content']['Target on failure']]);
			$return['Calculate statistics']['Failure']['value']++;
		} else {
			$sourceEntry=array_replace_recursive($sourceEntry,$base['entryTemplates'][$params['Content']['Target on success']]);
			$return['Calculate statistics']['Success']['value']++;
		}
		if ($testRun){
			if (isset($return['Sample result'])){
				if (mt_rand(1,100)>50){$return['Sample result']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($sourceEntry);}
			} else {
				$return['Sample result']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($sourceEntry);
			}
		} else {
			$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($sourceEntry);
		}
		$return['Target']=array('Source'=>array('value'=>$sourceEntry['Source']),'EntryId'=>array('value'=>$sourceEntry['EntryId']),'Name'=>array('value'=>$sourceEntry['Name']));
		return $return;
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