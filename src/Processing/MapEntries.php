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

namespace Datapool\Processing;

class MapEntries{
	
	private $arr;

	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );

	private $dataTypes=array('string'=>'String','stringNoWhitespaces'=>'String without whitespaces','splitString'=>'Split string','int'=>'Integer','float'=>'Float','money'=>'Money','date'=>'Date','codepfad'=>'Codepfad','unycom'=>'UNYCOM file number');
	private $skipCondition=array('always'=>'always',
								 'stripos'=>'is substring of target value',
								 'stripos!'=>'is not substring  of target value',
								 'strcmp'=>'is equal to target value',
								 'lt'=>'is < than target value',
								 'le'=>'is <= than target value',
								 'eq'=>'is = to target value',
								 'ne'=>'is <> to target value',
								 'gt'=>'is > than target value',
								 'ge'=>'is >= than target value',
								 );
		
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($arr){
		$this->arr=$arr;	
		$this->entryTemplate=$arr['Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		return $this->arr;
	}
	
	public function getEntryTable(){return $this->entryTable;}

	public function dataProcessor($action='info',$callingElementSelector=array()){
		// This method is the interface of this data processing class
		// The Argument $action selects the method to be invoked and
		// argument $callingElementSelector$ provides the entry which triggerd the action.
		// $callingElementSelector ... array('Source'=>'...', 'ElementId'=>'...', ...)
		// If the requested action does not exist the method returns FALSE and 
		// TRUE, a value or an array otherwise.
		$callingElement=$this->arr['Datapool\Foundation\Database']->entryByKey($callingElementSelector);
		switch($action){
			case 'run':
				if (empty($callingElement)){
					return TRUE;
				} else {
				return $this->runMapEntries($callingElement,$testRunOnly=FALSE);
				}
				break;
			case 'test':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runMapEntries($callingElement,$testRunOnly=TRUE);
				}
				break;
			case 'widget':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getMapEntriesWidget($callingElement);
				}
				break;
			case 'settings':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getMapEntriesSettings($callingElement);
				}
				break;
			case 'info':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getMapEntriesInfo($callingElement);
				}
				break;
		}
		return FALSE;
	}

	private function getMapEntriesWidget($callingElement){
		return $this->arr['Datapool\Foundation\Container']->container('Mapping','generic',$callingElement,array('method'=>'getMapEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getMapEntriesWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=array();
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['run'])){
			$result=$this->runMapEntries($arr['selector'],FALSE);
		} else if (isset($formData['cmd']['test'])){
			$result=$this->runMapEntries($arr['selector'],TRUE);
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
		$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Mapping widget'));
		foreach($result as $caption=>$matrix){
			$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		return $arr;
	}


	private function getMapEntriesSettings($callingElement){
		$html='';
		if ($this->arr['Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->arr['Datapool\Foundation\Container']->container('Mapping entries settings','generic',$callingElement,array('method'=>'getMapEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getMapEntriesSettingsHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$arr['html'].=$this->mappingParams($arr['selector']);
		$arr['html'].=$this->mappingRules($arr['selector']);
		//$selectorMatrix=$this->arr['Datapool\Tools\ArrTools']->arr2matrix($callingElement['Content']['Selector']);
		//$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for mapping'));
		return $arr;
	}

	private function mappingParams($callingElement){
		$contentStructure=array('Target'=>array('htmlBuilderMethod'=>'canvasElementSelect'),
								'Type'=>array('htmlBuilderMethod'=>'select','value'=>'string','options'=>array('entries'=>'Entries','csv'=>'CSV-List entry')),
								'Mode'=>array('htmlBuilderMethod'=>'select','value'=>'string','options'=>array(0=>'Update source entry with result',1=>'Add result to selected target')),
								);
		// get selctor
		$mappingParams=$this->callingElement2selector(__FUNCTION__,$callingElement,TRUE);
		if (empty($mappingParams)){return '';}
		$mappingParams=$this->arr['Datapool\Foundation\Access']->addRights($mappingParams,'ALL_R','ALL_CONTENTADMIN_R');
		$mappingParams['Content']=array();
		$mappingParams=$this->arr['Datapool\Foundation\Database']->entryByKeyCreateIfMissing($mappingParams,TRUE);
		// form processing
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$elementId=key($formData['val']);
		if (!empty($formData['val'][$elementId]['Content'])){
			$mappingParams['Content']=$formData['val'][$elementId]['Content'];
			$mappingParams=$this->arr['Datapool\Foundation\Database']->updateEntry($mappingParams);
		}
		// get HTML
		$arr=$mappingParams;
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Mapping control: Select mapping target and type';
		$arr['noBtns']=TRUE;
		$row=$this->arr['Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
		if (empty($mappingParams['Content'])){$row['setRowStyle']='background-color:#a00;';}
		$matrix=array('Parameter'=>$row);
		return $this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
	}
	
	private function mappingRules($callingElement){
		$contentStructure=array('Target value or...'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'...value selected by'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE),
								'Target data type'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->dataTypes),
								'Target column'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
								'Target key'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Use rule if Compare value'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'always','options'=>$this->skipCondition),
								'Compare value'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$contentStructure['...value selected by']+=$callingElement['Content']['Selector'];
		$contentStructure['Target column']+=$callingElement['Content']['Selector'];
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Mapping rules: Map selected entry values or constants (Source value) to target entry values';
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	private function runMapEntries($callingElement,$testRun=FALSE){
		$targetEntry=array();
		$entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['ElementId']);
		foreach($this->arr['Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','ElementId',TRUE) as $entry){
			$elementIdComps=explode('___',$entry['ElementId']);
			if (count($elementIdComps)<2){
				$targetEntry[$entry['Group']]=$entry['Content'];
			} else {
				$index=intval($elementIdComps[0]);
				$targetEntry[$entry['Group']][$index]=$entry['Content'];
			}
		}
		if (empty($targetEntry['mappingParams']['Type']) || empty($targetEntry['mappingParams']['Target'])){
			return array('Errors'=>array('Params empty'=>array('value'=>'Required parameters for '.__FUNCTION__.' are not set yet. Please select/enter the parameters.')));
		}
		// loop through source entries and map these entries
		$result=array('Source statistics'=>array('Entries'=>array('value'=>0),'CSV rows'=>array('value'=>0)));
		foreach($this->arr['Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector']) as $sourceEntry){
			$result['Source statistics']['Entries']['value']++;
			$isCsvEntry=FALSE;
			if (isset($sourceEntry['Params']['File']['MIME-Type'])){
				if (strpos($sourceEntry['Params']['File']['MIME-Type'],'text/')===0){
					$isCsvEntry=TRUE;
				}
			}
			if ($isCsvEntry){
				foreach($this->arr['Datapool\Tools\CSVtools']->csvIterator($sourceEntry) as $rowIndex=>$cells){
					$result['Source statistics']['CSV rows']['value']++;
					foreach($cells as $cellKey=>$cellValue){
						$sourceEntry['File content'][$cellKey]=$cellValue;
					} // loop through cells of row
					$result=$this->mapEntry($callingElement,$sourceEntry,$targetEntry,$result,$testRun);
				} // loop through csv-rows
			} else {
				$result=$this->mapEntry($callingElement,$sourceEntry,$targetEntry,$result,$testRun);
				$sourceEntry['isNewEntry']=FALSE;
			}
		}
		if (strcmp($targetEntry['mappingParams']['Type'],'csv')===0){$this->arr['Datapool\Tools\CSVtools']->entry2csv();}
		unset($result['ElementIds']);
		return $result;
	}
	
	private function mapEntry($callingElement,$sourceEntry,$targetEntry,$result,$testRun){
		$S=$this->arr['Datapool\Tools\ArrTools']->getSeparator();
		$keepExistingElementId=empty($targetEntry['mappingParams']['Mode']);
		if (!isset($result['Mapping statistics'])){
			$result['Mapping statistics']=array('Rule source entry key missing'=>array('value'=>0),
												'CSV row added'=>array('value'=>0)
												);
			if ($keepExistingElementId){
				$result['Mapping statistics']['Target entry updated (inserted if source is csv)']['value']=0;
			} else {
				$result['Mapping statistics']['Target entry inserted (updated if source=target)']['value']=0;
			}	
		}
		if (!isset($result['Log'])){$result['Log']=array();}
		// copy base key values across
		$baseKeys=$this->arr['Datapool\Foundation\Database']->entryTemplate($sourceEntry);
		foreach($baseKeys as $key=>$def){
			if (strcmp($key,'File content')===0 || strcmp($key,'Content')===0 || strcmp($key,'Params')===0 || strcmp($key,'ElementId')===0){continue;}
			$targetEntry[$key]=$sourceEntry[$key];
		}
		// rule based mapping
		$flatSourceEntry=$this->arr['Datapool\Tools\ArrTools']->arr2flat($sourceEntry);
		$flatTargetEntry=$this->arr['Datapool\Tools\ArrTools']->arr2flat($targetEntry);
		foreach($targetEntry['mappingRules'] as $ruleIndex=>$rule){
			if (strcmp($rule['...value selected by'],'useValue')===0){
				$targetValue=$rule['Target value or...'];
			} else {
				if (!empty($rule['Target value or...'])){
					$result['Log'][$ruleIndex]['value']='Source value is not empty ('.$rule['Target value or...'].'), but is not used.';
				}
				if (isset($flatSourceEntry[$rule['...value selected by']])){
					$targetValue=$flatSourceEntry[$rule['...value selected by']];
				} else {
					$result['Mapping statistics']['Rule source entry key missing']['value']++;
					$targetValue='{{missing}}';
				}
			}
			$flatTargetEntry=$this->addValue2flatEntry($flatTargetEntry,$rule['Target column'],$rule['Target key'],$targetValue,$rule['Target data type'],$rule);
		}
		// wrapping up
		foreach($flatTargetEntry as $key=>$value){
			if (strpos($key,'Content')===0 || strpos($key,'Params')===0){continue;}
			if (!is_array($value)){continue;}
			foreach($value as $subKey=>$subValue){
				$value[$subKey]=$this->getStdValueFromValueArr($subValue);
			}
			// set order of array values
			ksort($value);
			$flatTargetEntry[$key]=implode('|',$value);
		}
		$targetEntry=$this->arr['Datapool\Tools\ArrTools']->flat2arr($flatTargetEntry);
		$targetEntry=$this->applyCallingElement($callingElement['Source'],$targetEntry['mappingParams']['Target'],$targetEntry);
		// Save and return result
		if ($testRun){
			unset($targetEntry['mappingParams']);
			unset($targetEntry['mappingRules']);
			if (empty($result['Sample result'])){
				$result['Sample result']=$this->arr['Datapool\Tools\ArrTools']->arr2matrix($targetEntry);
			} else if (mt_rand(1,100)>90){
				$result['Sample result']=$this->arr['Datapool\Tools\ArrTools']->arr2matrix($targetEntry);
			}
		} else {
			if (strcmp($targetEntry['mappingParams']['Type'],'entries')===0){
				// create entries as mapping result
				$targetEntry=$this->arr['Datapool\Tools\StrTools']->addElementId($targetEntry,array('Source','Group','Folder','Name','Type'),0,'',$keepExistingElementId);
				$this->arr['Datapool\Foundation\Database']->updateEntry($targetEntry);
				if ($keepExistingElementId){
					$result['Mapping statistics']['Target entry updated (inserted if source is csv)']['value']++;
				} else {
					$result['Mapping statistics']['Target entry inserted (updated if source=target)']['value']++;
				}
			} else if (strcmp($targetEntry['mappingParams']['Type'],'csv')===0){
				// create csv list entry from mapping result
				$this->arr['Datapool\Tools\CSVtools']->entry2csv($targetEntry);
				$result['Mapping statistics']['CSV row added']['value']++;
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

	private function addValue2flatEntry($entry,$baseKey,$key,$value,$dataType,$rule){
		$dataTypeMethod='convert2'.$dataType;
		if (!isset($entry[$baseKey])){$entry[$baseKey]=array();}
		if (!is_array($entry[$baseKey]) && empty($key)){$entry[$baseKey]=array();}
		$newValue=array($key=>$this->$dataTypeMethod($value));
		if ($this->useRule($rule,$dataType,$newValue[$key])){
			if (is_array($entry[$baseKey])){
				$entry[$baseKey]=array_merge_recursive($entry[$baseKey],$newValue);
			} else {
				$entry[$baseKey]=$newValue;
			}
		}
		return $entry;
	}

	private function useRule($rule,$dataType,$targetValue){
		// unify datatype
		if (strcmp(($rule['Use rule if Compare value']),'always')===0){return TRUE;}
		if (strlen($rule['Use rule if Compare value'])>2){
			// compare strings
			$compareValue=$rule['Compare value'];
			$targetValue=$this->getStdValueFromValueArr($targetValue);
			$targetValue=strval($targetValue);
		} else {
			// compare numbers
			$compareValue=floatval($rule['Compare value']);
			if (strcmp($dataType,'date')===0){
				$targetValue=$this->getStdValueFromValueArr($targetValue,'System');
				$targetValue=strtotime($targetValue);
				$compareValue=strtotime($rule['Compare value']);
			} else if (strcmp($dataType,'int')===0){
				$targetValue=$this->getStdValueFromValueArr($targetValue);
				$compareValue=round($compareValue);
			} else if (strcmp($dataType,'unycom')===0){
				$targetValue=$this->getStdValueFromValueArr($targetValue,'Number');
				$compareValue=round($compareValue);
			} else {
				$targetValue=$this->getStdValueFromValueArr($targetValue);
			}
		}
		$return=FALSE;
		switch($rule['Use rule if Compare value']){
			case 'stripos':
				if (stripos($targetValue,$compareValue)!==FALSE){$return=TRUE;}
				break;
			case 'stripos!':
				if (stripos($targetValue,$compareValue)===FALSE){$return=TRUE;}
				break;
			case 'strcmp':
				if (strcmp($targetValue,$compareValue)===0){$return=TRUE;}
				break;
			case 'eq':
				if ($targetValue==$compareValue){$return=TRUE;}
				break;
			case 'le':
				if ($targetValue>=$compareValue){$return=TRUE;}
				break;
			case 'lt':
				if ($targetValue>$compareValue){$return=TRUE;}
				break;
			case 'ge':
				if ($targetValue<=$compareValue){$return=TRUE;}
				break;
			case 'gt':
				if ($targetValue<$compareValue){$return=TRUE;}
				break;
			case 'ne':
				if ($targetValue!=$compareValue){$return=TRUE;}
				break;
		}
		return $return;
	}

	// data type conversions
	
	public function convert2string($value){
		return $value;
	}

	public function convert2stringNoWhitespaces($value){
		$value=preg_replace("/\s/",'',$value);
		return $value;
	}

	public function convert2splitString($value){
		$value=strtolower($value);
		$value=trim($value);
		$value=preg_split("/[^a-zäöü0-9ß]+/",$value);
		return $value;
	}

	public function convert2float($value){
		$value=$this->arr['Datapool\Tools\NumberTools']->str2float($value);
		return $value;
	}
	
	public function convert2int($value){
		$value=$this->arr['Datapool\Tools\NumberTools']->str2float($value);
		return round($value);
	}

	public function convert2money($value){
		$arr=$this->arr['Datapool\Tools\NumberTools']->str2money($value);
		return $arr;
	}

	public function convert2date($value){
		$arr=$this->arr['Datapool\Tools\NumberTools']->str2date($value);
		return $arr;
	}
	
	public function convert2codepfad($value){
		$codepfade=explode(';',$value);
		$arr=array();
		foreach($codepfade as $codePfadIndex=>$codepfad){
			$codepfadComps=explode('\\',$codepfad);
			if ($codePfadIndex===0){
				if (isset($codepfadComps[0])){$arr['FhI']=$codepfadComps[0];}
				if (isset($codepfadComps[1])){$arr['FhI Teil']=$codepfadComps[1];}
				if (isset($codepfadComps[2])){$arr['Codepfad 3']=$codepfadComps[2];}
			} else {
				if (isset($codepfadComps[0])){$arr[$codePfadIndex]['FhI']=$codepfadComps[0];}
				if (isset($codepfadComps[1])){$arr[$codePfadIndex]['FhI Teil']=$codepfadComps[1];}
				if (isset($codepfadComps[2])){$arr[$codePfadIndex]['Codepfad 3']=$codepfadComps[2];}
			}
		}
		return $arr;
	}
	
	public function convert2unycom($value){
		$keyTemplate=array('Match','Year','Type','Number');
		$regions=array('WO'=>'PCT','WE'=>'Euro-PCT','EP'=>'European patent','AP'=>'ARIPO patent','EA'=>'Eurasian patent','OA'=>'OAPI patent');
		$value=str_replace(' ','',$value);
		preg_match('/([1-2][0-9]{3})([FPRZX]{1,2})([0-9]{5})/',$value,$matches);
		if (empty($matches[0])){return array();}
		$arr=array_combine($keyTemplate,$matches);
		$arr['Region']='  ';
		$arr['Country']='  ';
		$arr['Part']='  ';
		$prefixSuffix=explode($matches[0],$value);
		if (!empty($prefixSuffix[1])){
			$prefixSuffix[1]=substr($prefixSuffix[1],0,6);
			foreach($regions as $rc=>$region){
				if (strpos($prefixSuffix[1],$rc)!==0){continue;}
				$arr['Region']=$rc;
				$arr['Region long']=$region;
				break;
			}
			$countries=$this->arr['Datapool\Tools\GeoTools']->getCountryCodes();
			foreach($countries as $alpha2code=>$countryArr){
				if (strpos($prefixSuffix[1],$alpha2code)===FALSE){continue;}
				$arr['Country']=$alpha2code;
				$arr['Country long']=$countryArr['Country'];
				break;
			}
			$part=preg_replace('/[^0-9]+/','',$prefixSuffix[1]);
			if (!empty($part)){$arr['Part']=$part;}
			$country=preg_replace('/[^A-Z]+/','',$prefixSuffix[1]);
			$country=str_replace($arr['Region'],'',$country);
			if (strcmp($arr['Country'],'  ')===0 && !empty($country)){$arr['Country']=$country;}
		}
		$arr['Reference']=$arr['Year'].$arr['Type'].$arr['Number'].$arr['Region'].$arr['Country'].$arr['Part'];
		if (!empty($prefixSuffix[0])){
			$arr['Prefix']=trim($prefixSuffix[0],'- ');
		}
		//$this->arr['Datapool\Tools\ArrTools']->arr2file($arr);
		return $arr;
	}
	
	private function applyCallingElement($source,$elementId,$target=FALSE){
		// This method returns the target selector of the cnavas element selected by $elementId
		// and returns this selector.
		$selector=array('Source'=>$source,'ElementId'=>$elementId);
		foreach($this->arr['Datapool\Foundation\Database']->entryIterator($selector) as $entry){
			if (is_bool($target)){
				return $entry;
			} else if (is_array($target)){
				foreach($entry['Content']['Selector'] as $key=>$value){
					if (empty($value)){continue;}
					if (is_array($value)){continue;}
					if (!isset($target[$key])){$target[$key]='';}
					if (strpos($value,'%')===FALSE){
						$target[$key]=str_replace('%',' '.$target[$key].' ',$value);
						$target[$key]=trim($target[$key]);
					} else {
						$target[$key]=$value;
					}
				}
				return $target;
			}
		}
		return $target;
	}
	
	public function callingElement2selector($callingFunction,$callingElement,$selectsUniqueEntry=FALSE){
		if (!isset($callingElement['Folder']) || !isset($callingElement['ElementId'])){return array();}
		$type=$this->arr['Datapool\Foundation\Database']->class2source(__CLASS__,TRUE);
		$type.='|'.$callingFunction;
		$entrySelector=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['ElementId'],'Type'=>strtolower($type));
		if ($selectsUniqueEntry){$entrySelector=$this->arr['Datapool\Tools\StrTools']->addElementId($entrySelector,array('Group','Folder','Name','Type'),0);}
		return $entrySelector;

	}

}
?>