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

class MatchEntries{
	
	private $arr;

	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
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
		$callingElement=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector);
		switch($action){
			case 'run':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runMatchEntries($callingElement,$testRunOnly=FALSE);
				}
				break;
			case 'test':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runMatchEntries($callingElement,$testRunOnly=TRUE);
				}
				break;
			case 'widget':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getMatchEntriesWidget($callingElement);
				}
				break;
			case 'settings':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getMatchEntriesSettings($callingElement);
				}
				break;
			case 'info':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getMatchEntriesInfo($callingElement);
				}
				break;
		}
		return FALSE;
	}

	private function getMatchEntriesWidget($callingElement){
		return $this->arr['SourcePot\Datapool\Foundation\Container']->container('Matching','generic',$callingElement,array('method'=>'getMatchEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getMatchEntriesWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=array();
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['run'])){
			$result=$this->runMatchEntries($arr['selector'],FALSE);
		} else if (isset($formData['cmd']['test'])){
			$result=$this->runMatchEntries($arr['selector'],TRUE);
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
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Matching widget'));
		foreach($result as $caption=>$matrix){
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		return $arr;
	}

	private function getMatchEntriesSettings($callingElement){
		$html='';
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Matching entries settings','generic',$callingElement,array('method'=>'getMatchEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getMatchEntriesSettingsHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'h1','element-content'=>'Match entries selected by Selector A with Selector B selected entries'));
		$arr['html'].=$this->matchingParams($arr['selector']);
		$arr['html'].=$this->matchingRules($arr['selector']);
		//$selectorMatrix=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
		//$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for Matching'));
		return $arr;
	}
	
	private function matchingParams($callingElement){
		$return=array('html'=>'','Parameter'=>array(),'result'=>array());
		if (empty($callingElement['Content']['Selector']['Source'])){return $html;}
		$contentStructure=array('Column to match'=>array('htmlBuilderMethod'=>'keySelect','standardColumsOnly'=>TRUE),
							  'Match with'=>array('htmlBuilderMethod'=>'canvasElementSelect'),
							  'Match failure'=>array('htmlBuilderMethod'=>'canvasElementSelect'),
							  'Match success'=>array('htmlBuilderMethod'=>'canvasElementSelect'),
							  );
		$contentStructure['Column to match']+=$callingElement['Content']['Selector'];
		// get selctorB
		$matchingParams=$this->callingElement2selector(__FUNCTION__,$callingElement,TRUE);;
		$matchingParams=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($matchingParams,'ALL_R','ALL_CONTENTADMIN_R');
		$matchingParams['Content']=array('Column to match'=>'Name');
		$matchingParams=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($matchingParams,TRUE);
		// form processing
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$elementId=key($formData['val']);
		if (!empty($formData['val'][$elementId]['Content'])){
			$matchingParams['Content']=$formData['val'][$elementId]['Content'];
			$matchingParams=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($matchingParams);
		}
		// get HTML
		$arr=$matchingParams;
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Choose the column to be used for matching, the entries you want to match with and success/failure targets';
		$arr['noBtns']=TRUE;
		$matrix=array('Parameter'=>$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE));
		return $this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
	}

	private function matchingRules($callingElement){
		$contentStructure=array('Operation'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'strcmp','options'=>array('skipIfFound'=>'Skip entry if needle found','skipIfNotFound'=>'Skip entry if needle is not found')),
								 'Entry'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'strcmp','options'=>array('Entry A'=>'Entry A','Entry B'=>'Entry B')),
								 'Column'=>array('htmlBuilderMethod'=>'keySelect','standardColumsOnly'=>TRUE,'excontainer'=>TRUE),
								 'Needle'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$contentStructure['Column']+=$callingElement['Content']['Selector'];
		if (empty($callingElement['Content']['Selector']['Source'])){return $html;}
		$arr=$this->callingElement2selector(__FUNCTION__,$callingElement,FALSE);
		$arr['canvasCallingClass']=$callingElement['Folder'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Filter-rules: Skip entries if one of the conditions is met';
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $html;
	}
		
	public function runMatchEntries($callingElement,$isTestRun=TRUE){
		$result=array('Statistics'=>array('Entry A count'=>array('value'=>0),
										  'Skipped entry A count'=>array('value'=>0),
										  'Skipped entry B count'=>array('value'=>0),
										  'Successful matches'=>array('value'=>0),
										  'Failed matches'=>array('value'=>0)
										  )
					 );
		$settings=array();
		$entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId']);
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
			$elementIdComps=explode('___',$entry['EntryId']);
			if (count($elementIdComps)<2){
				$settings[$entry['Group']]=$entry['Content'];
			} else {
				$index=intval($elementIdComps[0]);
				$settings[$entry['Group']][$index]=$entry['Content'];
			}
		}
		$currentSelectorB=$this->applyCallingElement($callingElement['Source'],$settings['matchingParams']['Match with'],array());
		$column2match=$settings['matchingParams']['Column to match'];
		$rules=array('Entry A'=>array(),'Entry B'=>array());
		foreach($settings['matchingRules'] as $elementId=>$rule){$rules[$rule['Entry']][]=$rule;}
		
		
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector']) as $entryA){
			if ($entryA['isSkipRow']){continue;}
			$result['Statistics']['Entry A count']['value']++;
			$currentSelectorB[$column2match]=$entryA[$column2match];
			if ($this->skipMatch($entryA,$rules['Entry A'])){
				$result['Statistics']['Skipped entry A count']['value']++;
				continue;
			}
			if (!empty($rules['Entry B'])){$skipDueToEntryBrule=TRUE;} else {$skipDueToEntryBrule=FALSE;}
			$hadMatch=FALSE;
			foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($currentSelectorB) as $entryB){
				if ($entryB['isSkipRow']){continue;}
				$hadMatch=$entryB['Content'];
				if ($this->skipMatch($entryB,$rules['Entry B'])){
					continue;
				} else {
					$skipDueToEntryBrule=FALSE;
				}
			} // loop through B entries
			if ($skipDueToEntryBrule){
				$result['Statistics']['Skipped entry B count']['value']++;
				continue;	
			}
			if ($hadMatch){
				$result['Statistics']['Successful matches']['value']++;
				$entryA=$this->applyCallingElement($callingElement['Source'],$settings['matchingParams']['Match success'],$entryA);
				$entryA['Content']['Match']=$hadMatch;
			} else {
				$result['Statistics']['Failed matches']['value']++;	
				$entryA=$this->applyCallingElement($callingElement['Source'],$settings['matchingParams']['Match failure'],$entryA);
			}
			if (!$isTestRun){
				$entryA['Content']['Match selector']=$currentSelectorB;
				$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entryA);
			}
			$firstLoopEntriesA=TRUE;
		} // loop through A entries
		return $result;
	}
	
	private function skipMatch($entry,$rules){
		$skip=FALSE;
		foreach($rules as $ruleIndex=>$rule){
			$haystack=$entry[$rule['Column']];
			$needle=$rule['Needle'];
			if (is_array($haystack)){$haystack=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2json($haystack);}
			if (strcmp($rule['Operation'],'skipIfNotFound')===0){
				if (strpos($haystack,$needle)===FALSE){
					return TRUE;
				}
			} else if (strcmp($rule['Operation'],'skipIfFound')===0){
				if (strpos($haystack,$needle)!==FALSE){
					return TRUE;
				}
			}
		}
		return $skip;
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