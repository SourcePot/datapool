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

class MapEntries{
	
	use \SourcePot\Datapool\Traits\Conversions;
	
	private $arr;

	private $vars=array();

	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );

	private $dataTypes=array('string'=>'String','stringNoWhitespaces'=>'String without whitespaces','splitString'=>'Split string','int'=>'Integer','float'=>'Float','bool'=>'Boolean','money'=>'Money','date'=>'Date','codepfad'=>'Codepfad','unycom'=>'UNYCOM file number');
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
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		return $this->arr;
	}
	
	public function getEntryTable(){return $this->entryTable;}

	public function dataProcessor($action='info',$callingElementSelector=array(),$vars=array()){
		// This method is the interface of this data processing class
		// The Argument $action selects the method to be invoked and
		// argument $callingElementSelector$ provides the entry which triggerd the action.
		// $callingElementSelector ... array('Source'=>'...', 'EntryId'=>'...', ...)
		// If the requested action does not exist the method returns FALSE and 
		// TRUE, a value or an array otherwise.
		$this->vars=$vars;
		$callingElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
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
		return $this->arr['SourcePot\Datapool\Foundation\Container']->container('Mapping','generic',$callingElement,array('method'=>'getMapEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getMapEntriesWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=$this->vars;
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
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
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Mapping widget'));
		foreach($result as $caption=>$matrix){
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		$arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
		return $arr;
	}


	private function getMapEntriesSettings($callingElement){
		$html='';
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Mapping entries settings','generic',$callingElement,array('method'=>'getMapEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getMapEntriesSettingsHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$arr['html'].=$this->mappingParams($arr['selector']);
		$arr['html'].=$this->mappingRules($arr['selector']);
		//$selectorMatrix=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
		//$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for mapping'));
		return $arr;
	}

	private function mappingParams($callingElement){
		$contentStructure=array('Target'=>array('htmlBuilderMethod'=>'canvasElementSelect','excontainer'=>TRUE),
								'Mode'=>array('htmlBuilderMethod'=>'select','value'=>'entries','excontainer'=>TRUE,'options'=>array('entries'=>'Entries (EntryId will be created from Name)','csv'=>'Create csv','zip'=>'Create zip')),
								'Run...'=>array('htmlBuilderMethod'=>'select','value'=>0,'excontainer'=>TRUE,'options'=>array(0=>'when triggered',86400=>'once a day',604800=>'once a week',2592000=>'once every 30 days')),
								'Save'=>array('htmlBuilderMethod'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
								);
		// get selctor
		$mappingParams=$this->callingElement2selector(__FUNCTION__,$callingElement,TRUE);
		if (empty($mappingParams)){return '';}
		$mappingParams=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($mappingParams,'ALL_R','ALL_CONTENTADMIN_R');
		$mappingParams['Content']=array();
		$mappingParams=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($mappingParams,TRUE);
		// form processing
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$elementId=key($formData['val']);
		if (isset($formData['cmd'][$elementId])){
			$mappingParams['Content']=$formData['val'][$elementId]['Content'];
			$mappingParams=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($mappingParams,TRUE);
		}
		// get HTML
		$arr=$mappingParams;
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Mapping control: Select mapping target and type';
		$arr['noBtns']=TRUE;
		$row=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
		if (empty($mappingParams['Content'])){$row['setRowStyle']='background-color:#a00;';}
		$matrix=array('Parameter'=>$row);
		return $this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
	}
	
	private function mappingRules($callingElement){
		$contentStructure=array('Target value or...'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'...value selected by'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>array('Linked file'=>'Linked file')),
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
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}

	private function runMapEntries($callingElement,$testRun=FALSE){
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
		$result=array('Mapping statistics'=>array('Entries'=>array('value'=>0),
												  'CSV-Entries'=>array('value'=>0),
												  'Files added to zip'=>array('value'=>0),
												  'Skip rows'=>array('value'=>0),
												  'Output format'=>array('value'=>'Entries'),
												 )
					);
		// loop through entries
		$params=current($base['mappingparams']);
		$base['Attachment name']=date('Y-m-d His').' '.implode('-',$base['entryTemplates'][$params['Content']['Target']]);
		$base['zipRequested']=(!$testRun && strcmp($params['Content']['Mode'],'zip')===0);
		$base['csvRequested']=(!$testRun && (strcmp($params['Content']['Mode'],'csv')===0 || strcmp($params['Content']['Mode'],'zip')===0));
		if ($base['zipRequested']){
			$zipName=date('Y-m-d His').' '.__FUNCTION__.'.zip';
			$zipFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->getTmpDir().$zipName;
			$zip= new \ZipArchive;
			$zip->open($zipFile,\ZipArchive::CREATE);
		}
		$deleteEntries=array('Source'=>$callingElement['Content']['Selector']['Source'],'EntryIds'=>array());
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
			//if (time()-$base['Script start timestamp']>30){break;}
			if ($entry['isSkipRow']){
				$result['Mapping statistics']['Skip rows']['value']++;
				continue;
			}
			if ($base['zipRequested']){
				// open temporary zip-archive		
				$attachment=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
				if (is_file($attachment)){
					$result['Mapping statistics']['Files added to zip']['value']++;
					$sourceEntry['Linked file']=preg_replace('/[^0-9a-zA-ZöüäÖÜÄß\-]/','_',$sourceEntry['Name']).'.'.$sourceEntry['Params']['File']['Extension'];
					$zip->addFile($attachment,$sourceEntry['Linked file']);
				} else {
					$sourceEntry['Linked file']='';
				}
				$deleteEntries['EntryIds'][]="'".$sourceEntry['EntryId']."'";
			}
			// map entry
			if ($this->arr['SourcePot\Datapool\Tools\CSVtools']->isCSV($sourceEntry)){
				foreach($this->arr['SourcePot\Datapool\Tools\CSVtools']->csvIterator($sourceEntry) as $rowIndex=>$rowArr){
					$result['Mapping statistics']['CSV-Entries']['value']++;
					$sourceEntry['File content']=array_replace($sourceEntry['Content'],$rowArr);
					$result=$this->mapEntry($base,$sourceEntry,$result,$testRun);
				}
			} else {
				$result=$this->mapEntry($base,$sourceEntry,$result,$testRun);
			}
		}
		if ($base['csvRequested'] || $base['zipRequested']){
			$result['Mapping statistics']['Output format']['value']='CSV';
			$this->arr['SourcePot\Datapool\Tools\CSVtools']->entry2csv();
		}			
		if ($base['zipRequested']){
			if (isset($result['Target']['Source'])){
				// add csv file to zip archive
				$csvFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file(array('Source'=>$result['Target']['Source']['value'],'EntryId'=>$result['Target']['EntryId']['value']));
				$zipFileName=preg_replace('/[^a-zA-Z0-9\-]/','_',$result['Target']['Name']['value']);
				$zip->addFile($csvFile,$zipFileName.'.csv');
			}
			$zip->close();	
			if (isset($result['Target']['Source'])){
				// create zip entry
				$zipEntry=array('Source'=>$result['Target']['Source']['value'],'Name'=>$result['Target']['Name']['value'].' (complete)');
				$zipEntry=array_replace_recursive($sourceEntry,$base['entryTemplates'][$params['Content']['Target']],$zipEntry);
				$zipEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($zipEntry,array('Name'),'0','',FALSE);
				// add file info
				$pathArr=pathinfo($zipFile);
				$zipEntry['Params']['File']['Source']=$zipFile;
				$zipEntry['Params']['File']['Size']=filesize($zipEntry['Params']['File']['Source']);
				$zipEntry['Params']['File']['Name']=$pathArr['basename'];
				$zipEntry['Params']['File']['Extension']=$pathArr['extension'];
				$zipEntry['Params']['File']['MIME-Type']=mime_content_type($zipFile);
				$zipEntry['Params']['File']['Date (created)']=filectime($zipEntry['Params']['File']['Source']);
				$zipEntry['Type']=$zipEntry['Source'].' '.str_replace('/',' ',$zipEntry['Params']['File']['MIME-Type']);
				// save entry and file
				$zipEntry=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($zipEntry,TRUE);
				$entryFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($zipEntry);
				$this->arr['SourcePot\Datapool\Foundation\Filespace']->tryCopy($zipFile,$entryFile);
				$result['Mapping statistics']['Output format']['value']='Zip + csv';
			}
			$this->deleteEntriesById($deleteEntries['Source'],$deleteEntries['EntryIds']);
		}
		$result['Statistics']=$this->arr['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
		$result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
		$result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
		return $result;
	}
	
	private function mapEntry($base,$sourceEntry,$result,$testRun){
		$params=current($base['mappingparams']);
		$targetEntry=array();
		$flatSourceEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
		foreach($base['mappingrules'] as $ruleIndex=>$rule){
			if (strcmp($rule['Content']['...value selected by'],'useValue')===0){
				$targetValue=$rule['Content']['Target value or...'];
			} else {
				if (isset($flatSourceEntry[$rule['Content']['...value selected by']])){
					$targetValue=$flatSourceEntry[$rule['Content']['...value selected by']];
				} else {
					$targetValue='{{missing}}';
				}
			}
			$targetEntry=$this->addValue2flatEntry($targetEntry,$rule['Content']['Target column'],$rule['Content']['Target key'],$targetValue,$rule['Content']['Target data type'],$rule['Content']);
		}
		// wrapping up
		foreach($targetEntry as $key=>$value){
			if (strpos($key,'Content')===0 || strpos($key,'Params')===0){continue;}
			if (!is_array($value)){continue;}
			foreach($value as $subKey=>$subValue){
				$value[$subKey]=$this->getStdValueFromValueArr($subValue);
			}
			// set order of array values
			ksort($value);
			$targetEntry[$key]=implode('|',$value);
		}
		$result['Mapping statistics']['Entries']['value']++;
		$sourceEntry=$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog2entry($sourceEntry,'Processing log',array('mode'=>$params['Content']['Mode']),FALSE);
		if ($base['csvRequested'] || $base['zipRequested']){
			unset($sourceEntry['Content']);
			unset($sourceEntry['Params']);
			$targetEntry=array_replace_recursive($sourceEntry,$targetEntry,$base['entryTemplates'][$params['Content']['Target']]);
			$targetEntry['Name']=$base['Attachment name'];
			$targetEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($targetEntry,array('Name'),'0','',FALSE);
			if (!$testRun){$this->arr['SourcePot\Datapool\Tools\CSVtools']->entry2csv($targetEntry);}
		} else {
			$sourceEntry=array_replace_recursive($sourceEntry,$targetEntry);
			$targetEntry=$this->arr['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Content']['Target']],TRUE,$testRun);
		}
		$result['Target']['Source']['value']=$targetEntry['Source'];
		$result['Target']['EntryId']['value']=$targetEntry['EntryId'];
		$result['Target']['Name']['value']=$targetEntry['Name'];
		if (!isset($result['Sample result']) || mt_rand(0,100)>90){
			$result['Sample result']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
		}
		return $result;
	}
	
	private function deleteEntriesById($Source,$EntryIds){
		if (empty($EntryIds)){return $this->arr['SourcePot\Datapool\Foundation\Database']->getStatistic();}
		foreach($EntryIds as $EntryId){
			$entrySelector=array('Source'=>$Source,'EntryId'=>$EntryId);
			$fileToDelete=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($entrySelector);
			if (is_file($fileToDelete)){
				$this->arr['SourcePot\Datapool\Foundation\Database']->addStatistic('removed',1);
				unlink($fileToDelete);
			}
		}
		$sql="DELETE FROM ".$Source." WHERE `EntryId` IN(".implode(',',$EntryIds).");";
		$stmt=$this->arr['SourcePot\Datapool\Foundation\Database']->executeStatement($sql);
		$this->arr['SourcePot\Datapool\Foundation\Database']->addStatistic('deleted',$stmt->rowCount());
		return $this->arr['SourcePot\Datapool\Foundation\Database']->getStatistic();
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
				$entry[$baseKey]=array_replace_recursive($entry[$baseKey],$newValue);
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
	
	public function callingElement2selector($callingFunction,$callingElement,$selectsUniqueEntry=FALSE){
		if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
		$type=$this->arr['class2source'][__CLASS__];
		$type.='|'.$callingFunction;
		$entrySelector=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
		if ($selectsUniqueEntry){$entrySelector=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entrySelector,array('Group','Folder','Name','Type'),0);}
		return $entrySelector;

	}

}
?>