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
	private $sections=array(''=>'all sections','CONSTANT'=>'CONSTANT');
	
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
		$arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
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
		$contentStructure=array('Source column'=>array('htmlBuilderMethod'=>'keySelect','value'=>'useValue','addSourceValueColumn'=>TRUE),
								'Target on success'=>array('htmlBuilderMethod'=>'canvasElementSelect'),
								'EntryId'=>array('htmlBuilderMethod'=>'select','value'=>'string','options'=>array('keepEntryId'=>'Keep EntryId','entrIdFromName'=>'EntryId from Name')),
								'Target on failure'=>array('htmlBuilderMethod'=>'canvasElementSelect'),
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
		$contentStructure=array('Keyword'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Section name'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Provide rules to divide the text into sections.';
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
								'Match index'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>array(0,1,2,3,4,5,6,7,8,9,10)),
								'Target data type'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->dataTypes),
								'Target column'=>array('htmlBuilderMethod'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
								'Target key'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Match required'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'boolean','options'=>array('No','Yes')),
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
		$this->arr['SourcePot\Datapool\Foundation\Database']->resetStatistic();
		$result=array('Source statistics'=>array('Entries'=>array('value'=>0),'CSV rows'=>array('value'=>0)));
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector']) as $sourceEntry){
			$result['Source statistics']['Entries']['value']++;
			$result=$this->parseEntry($base,$sourceEntry,$result,$testRun);
		}
		$statistics=$this->arr['SourcePot\Datapool\Foundation\Database']->getStatistic();
		$result['Statistics']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($statistics);
		return $result;
	}
	
	private function parseEntry($base,$sourceEntry,$result,$testRun){
		$S=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$params=current($base['parserparams']);
		$canvasElements=$this->arr['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements($params['Folder']);
		$params=$params['Content'];
		// get source text
		$flatSourceEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
		if (isset($flatSourceEntry[$params['Source column']])){
			$fullText=$flatSourceEntry[$params['Source column']];
			$fullText=preg_replace('/[\t ]+/',' ',$fullText);
			$fullText=preg_replace('/(\n )+|(\n )+/',"\n",$fullText);
			$fullText=preg_replace('/(\n)+/',"\n",$fullText);
			$lastSection='START';
			$base['parsersectionrules'][$lastSection]=array('Content'=>array('Keyword'=>'_____','Section name'=>'START'));
			$textSections[$lastSection]=$fullText;
		} else {
			return $result;
		}
		// create text sections
		foreach($base['parsersectionrules'] as $entryId=>$sectionRule){
			$keywordPos=strpos($textSections[$lastSection],$sectionRule['Content']['Keyword']);
			if ($keywordPos!==FALSE){
				$tmpText=$textSections[$lastSection];
				$textSections[$lastSection]=substr($tmpText,0,$keywordPos);
				if ($testRun){$result['Parser text sections'][$base['parsersectionrules'][$lastSection]['Content']['Section name']]=array('value'=>$textSections[$lastSection]);}
				$lastSection=$entryId;
				$textSections[$lastSection]=substr($tmpText,$keywordPos);
				if ($testRun){$result['Parser text sections'][$base['parsersectionrules'][$lastSection]['Content']['Section name']]=array('value'=>$textSections[$lastSection]);}
			}
		}
		// parse sections
		$parserFailed=FALSE;
		$targetEntry=array();
		foreach($base['parserrules'] as $ruleEntryId=>$rule){
			$relevantText='';
			if (empty($rule['Content']['Rule relevant on section'])){
				$relevantText=$fullText;
			} else if (isset($textSections[$rule['Content']['Rule relevant on section']])){
				$relevantText=$textSections[$rule['Content']['Rule relevant on section']];
			}
			if (strcmp($rule['Content']['Rule relevant on section'],'CONSTANT')===0){
				$matches[0][0]=$rule['Content']['Regular expression to match or constant to be used'];
			} else {
				preg_match_all('/'.$rule['Content']['Regular expression to match or constant to be used'].'/',$relevantText,$matches);
			}
			if (isset($rule['Content']['Match required'])){$matchRequired=boolval($rule['Content']['Match required']);} else {$matchRequired=FALSE;}
			
			if (!isset($matches[0][0])){
				$ruleFailed=TRUE;
				$matchText='No match.';
			} else if (isset($matches[$rule['Content']['Match index']])){
				$ruleFailed=FALSE;
				$matchText=$matches[$rule['Content']['Match index']][0];
				foreach($matches[$rule['Content']['Match index']] as $hitIndex=>$value){
					if (count($matches[$rule['Content']['Match index']])>1){$targetKey=$rule['Content']['Target key'].' '.$hitIndex;} else {$targetKey=$rule['Content']['Target key'];}
					$targetEntry=$this->addValue2flatEntry($targetEntry,$rule['Content']['Target column'],$targetKey,$value,$rule['Content']['Target data type']);
				}
			} else {
				$ruleFailed=TRUE;
				$matchText='Match, but Match index '.$rule['Content']['Match index'].' is not set.';
			}
			if ($testRun){$result['Parser rule matches'][$ruleEntryId]=array('Match'=>$matchText,'Rule failed'=>strval($ruleFailed),'Match required'=>strval($matchRequired));}
			if ($ruleFailed && $matchRequired){$parserFailed=TRUE;break;}
		}
		if ($parserFailed){
			$selector=$this->arr['SourcePot\Datapool\Foundation\DataExplorer']->entryId2selector($params['Target on failure']);
			$targetEntry=array_replace_recursive($sourceEntry,$selector);
		} else {
			$selector=$this->arr['SourcePot\Datapool\Foundation\DataExplorer']->entryId2selector($params['Target on success']);
			$targetEntry=array_replace_recursive($sourceEntry,$selector,$targetEntry);
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
			if (strcmp($params['EntryId'],'entrIdFromName')===0){
				$sourceFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($targetEntry);
				$targetEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($targetEntry,array('Name'),'0','',FALSE);
				$targetFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($targetEntry);
				if (is_file($sourceFile)){
					rename($sourceFile,$targetFile);
				}
			}
		}
		if ($testRun){
			$result['Sample result']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
		} else {
			$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($targetEntry);
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
			$entry[$baseKey]=array_merge_recursive($entry[$baseKey],$newValue);
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