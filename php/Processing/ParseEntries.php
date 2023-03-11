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

class ParseEntries{
	
	use \SourcePot\Datapool\Traits\Conversions;
	
	private $arr;

	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );
		
	private $dataTypes=array('string'=>'String','stringNoWhitespaces'=>'String without whitespaces','splitString'=>'Split string','int'=>'Integer','float'=>'Float','money'=>'Money','date'=>'Date','codepfad'=>'Codepfad','unycom'=>'UNYCOM file number');
	private $sections=array(''=>'all sections');
	
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
				return $this->runParseEntries($callingElement,$testRunOnly=FALSE);
				}
				break;
			case 'test':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runParseEntries($callingElement,$testRunOnly=TRUE);
				}
				break;
			case 'widget':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getParseEntriesWidget($callingElement);
				}
				break;
			case 'settings':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getParseEntriesSettings($callingElement);
				}
				break;
			case 'info':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getParseEntriesInfo($callingElement);
				}
				break;
		}
		return FALSE;
	}

	private function getParseEntriesWidget($callingElement){
		return $this->arr['SourcePot\Datapool\Foundation\Container']->container('Parsing','generic',$callingElement,array('method'=>'getParseEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getParseEntriesWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=array();
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['run'])){
			$result=$this->runParseEntries($arr['selector'],FALSE);
		} else if (isset($formData['cmd']['test'])){
			$result=$this->runParseEntries($arr['selector'],TRUE);
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
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Parsing widget'));
		foreach($result as $caption=>$matrix){
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		return $arr;
	}


	private function getParseEntriesSettings($callingElement){
		$html='';
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Parsing entries settings','generic',$callingElement,array('method'=>'getParseEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getParseEntriesSettingsHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$arr['html'].=$this->parserParams($arr['selector']);
		$arr['html'].=$this->parserSectionRules($arr['selector']);
		$arr['html'].=$this->parserRules($arr['selector']);
		//$selectorMatrix=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
		//$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for parsing'));
		return $arr;
	}

	private function parserParams($callingElement){
		$contentStructure=array('Source column'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE),
								'Target'=>array('htmlBuilderMethod'=>'canvasElementSelect'),
								'Type'=>array('htmlBuilderMethod'=>'select','value'=>'string','options'=>array('entries'=>'Entries','csv'=>'CSV-List entry')),
								'Mode'=>array('htmlBuilderMethod'=>'select','value'=>'string','options'=>array(0=>'Update source entry with result',1=>'Add result to selected target',2=>'Remove source entry on success')),
								);
		$contentStructure['Source column']+=$callingElement['Content']['Selector'];
		// get selector
		$parserParams=$this->callingElement2selector(__FUNCTION__,$callingElement,TRUE);
		if (empty($parserParams)){return '';}
		$parserParams=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($parserParams,'ALL_R','ALL_CONTENTADMIN_R');
		$parserParams['Content']=array();
		$parserParams=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($parserParams,TRUE);
		// form processing
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$elementId=key($formData['val']);
		if (!empty($formData['val'][$elementId]['Content'])){
			$parserParams['Content']=$formData['val'][$elementId]['Content'];
			$parserParams=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($parserParams);
		}
		// get HTML
		$arr=$parserParams;
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Parser control: Select parser target and type';
		$arr['noBtns']=TRUE;
		$row=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
		if (empty($parserParams['Content'])){$row['setRowStyle']='background-color:#a00;';}
		$matrix=array('Parameter'=>$row);
		return $this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
	}
	
	private function parserSectionRules($callingElement){
		$contentStructure=array('Regular expression'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Section name'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Parser section rules. Provide rules to divide the text into sections.';
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	private function parserRules($callingElement){
		// complete section selector
		$entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId'],'Type'=>'%|parsersectionrules');
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
			$this->sections[$entry['EntryId']]=$entry['Content']['Section name'];
		}
		//
		$contentStructure=array('Rule relevant on section'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->sections),
								'Regular expression to match or constant to be used'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Target data type'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->dataTypes),
								'Target column'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
								'Target key'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$contentStructure['Target column']+=$callingElement['Content']['Selector'];
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Parser rules: Parse selected entry and copy result to target entry';
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	private function runParseEntries($callingElement,$testRun=FALSE){
		$base=array();
		$entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId']);
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
			$key=explode('|',$entry['Type']);
			$key=array_pop($key);
			$base[$key][$entry['EntryId']]=$entry;
		}
		// loop through source entries and parse these entries
		$result=array('Source statistics'=>array('Entries'=>array('value'=>0),'CSV rows'=>array('value'=>0)));
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector']) as $sourceEntry){
			$result['Source statistics']['Entries']['value']++;
			$result=$this->parseEntry($base,$callingElement,$result,$testRun);
		}
		//if (strcmp($targetEntry['parserParams']['Type'],'csv')===0){$this->arr['SourcePot\Datapool\Tools\CSVtools']->entry2csv();}
		return $result;
	}
	
	private function parseEntry($base,$callingElement,$result,$testRun){
		$S=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$params=current($base['parserparams']);
		$params=$params['Content'];
		$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($params,__FUNCTION__.'-'.$callingElement['EntryId']);
		/*
		$keepExistingEntryId=empty($targetEntry['parserParams']['Mode']);
		if (!isset($result['Parser statistics'])){
			$result['Mapping statistics']=array('Rule source entry key missing'=>array('value'=>0),
												'CSV row added'=>array('value'=>0)
												);
			if ($keepExistingEntryId){
				$result['Mapping statistics']['Target entry updated (inserted if source is csv)']['value']=0;
			} else {
				$result['Mapping statistics']['Target entry inserted (updated if source=target)']['value']=0;
			}	
		}
		if (!isset($result['Log'])){$result['Log']=array();}
		// copy base key values across
		$baseKeys=$this->arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($sourceEntry['Source']);
		foreach($baseKeys as $key=>$def){
			if (strcmp($key,'File content')===0 || strcmp($key,'Content')===0 || strcmp($key,'Params')===0 || strcmp($key,'EntryId')===0){continue;}
			$targetEntry[$key]=$sourceEntry[$key];
		}
		// rule based mapping
		$flatSourceEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
		$flatTargetEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($targetEntry);
		foreach($targetEntry['parserRules'] as $ruleIndex=>$rule){
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
		$targetEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatTargetEntry);
		$targetEntry=$this->applyCallingElement($callingElement['Source'],$targetEntry['parserParams']['Target'],$targetEntry);
		// Save and return result
		if ($testRun){
			unset($targetEntry['parserParams']);
			unset($targetEntry['parserRules']);
			if (empty($result['Sample result'])){
				$result['Sample result']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
			} else if (mt_rand(1,100)>90){
				$result['Sample result']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
			}
		} else {
			if (strcmp($targetEntry['parserParams']['Type'],'entries')===0){
				// create entries as mapping result
				$targetEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($targetEntry,array('Source','Group','Folder','Name','Type'),0,'',$keepExistingEntryId);
				$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($targetEntry);
				if ($keepExistingEntryId){
					$result['Mapping statistics']['Target entry updated (inserted if source is csv)']['value']++;
				} else {
					$result['Mapping statistics']['Target entry inserted (updated if source=target)']['value']++;
				}
			} else if (strcmp($targetEntry['parserParams']['Type'],'csv')===0){
				// create csv list entry from mapping result
				$this->arr['SourcePot\Datapool\Tools\CSVtools']->entry2csv($targetEntry);
				$result['Mapping statistics']['CSV row added']['value']++;
			}
		}
		*/
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
	
	private function applyCallingElement($source,$elementId,$target=FALSE){
		// This method returns the target selector of the cnavas element selected by $elementId
		// and returns this selector.
		$selector=array('Source'=>$source,'EntryId'=>$elementId);
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($selector) as $entry){
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
		if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
		$type=$this->arr['SourcePot\Datapool\Foundation\Database']->class2source(__CLASS__,TRUE);
		$type.='|'.$callingFunction;
		$entrySelector=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
		if ($selectsUniqueEntry){$entrySelector=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entrySelector,array('Group','Folder','Name','Type'),0);}
		return $entrySelector;

	}

}
?>