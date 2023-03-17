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

namespace SourcePot\Datapool\Foundation;

class Definitions{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array();
	
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

	public function getEntryTemplate(){return $this->entryTemplate;}
	
	public function addDefintion($callingClass,$definition){
		// This function adds a definition entry to the database defintions table.		
		$Type=$this->arr['SourcePot\Datapool\Foundation\Database']->class2source($callingClass,TRUE);
		$entry=array('Source'=>$this->entryTable,'Group'=>'Templates','Folder'=>$callingClass,'Name'=>$Type,'Type'=>'definition','Owner'=>'SYSTEM');
		$entry['EntryId']=md5(json_encode($entry));
		$entry['Content']=$definition;
		$this->arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,TRUE);
	}
	
	public function getDefinition($entry,$isDebugging=FALSE){
		// This function returns the definition entry from the defintions-table of the database.
		// The definition entry-Name equals the corresponding entry-Type and lowercase origin table-name, e.g. User -> user
		if (empty($entry['Type'])){throw new \ErrorException('Function '.__FUNCTION__.': Entry missing Type-key.',0,E_ERROR,__FILE__,__LINE__);	}
		$debugArr=array('entry'=>$entry);
		$Name=explode(' ',$entry['Type']);
		$Name=array_shift($Name);
		$Name=strtolower($Name);
		$selector=array('Source'=>$this->entryTable,'Group'=>'Templates','Name'=>$Name);
		$definition=array();
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $entry){
			$definition=$entry['Content'];
			break;
		}
		if ($isDebugging){
			$debugArr['selector']=$selector;
			$debugArr['definition']=$definition;
			$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $definition;
	}
	
	public function definition2entry($definition,$entry=array(),$isDebugging=FALSE){
		$debugArr=array('definition'=>$definition,'entry_in'=>$entry);
		$flatArrayKeySeparator=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$flatEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
		$flatDefinition=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($definition);
		$defaultArrKeys2remove=array();
		$defaultArr=array();
		foreach($flatDefinition as $definitionKey=>$definitionValue){
			if (strpos($definitionKey,'@default')!==FALSE){
				$defaultKey=str_replace($flatArrayKeySeparator.'@default','',$definitionKey);
				$defaultArr[$defaultKey]=$definitionValue;
			} else if (strpos($definitionKey,'@type')!==FALSE && strcmp($definitionValue,'btn')===0){
				$defaultKey=str_replace($flatArrayKeySeparator.'@type','',$definitionKey);
				$defaultArrKeys2remove[$defaultKey]=FALSE;	// to remove if default value is empty
			} else if (strpos($definitionKey,'@type')!==FALSE && strcmp($definitionValue,'method')===0){
				$defaultKey=str_replace($flatArrayKeySeparator.'@type','',$definitionKey);
				$defaultArrKeys2remove[$defaultKey]=TRUE;	// to remove if default value is empty
			}
		}
		foreach($defaultArrKeys2remove as $toRemoveKey=>$onlyIfEmpty){
			if (isset($defaultArr[$toRemoveKey])){
				if (($onlyIfEmpty && empty($defaultArr[$toRemoveKey])) || !$onlyIfEmpty){unset($defaultArr[$toRemoveKey]);}
			}
		}
		$flatEntry=$flatEntry+$defaultArr;
		$entry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntry);
		$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->addEntryDefaults($entry);
		if ($isDebugging){
			$debugArr['defaultArrKeys2remove']=$defaultArrKeys2remove;
			$debugArr['entry_out']=$entry;
			$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $entry;
	}
	
	public function selectorKey2element($entry,$flatSelectorKey,$value=NULL,$callingClass=FALSE,$callingFunction=FALSE){
		// If the $flatSelectorKey matches any definition-key an element-array with the provided value or default value is returned.
		// You can use the wildcard character '*' at the end of $flatSelectorKey.
		$value=strval($value);
		$definition=$this->getDefinition($entry);	
		$S=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$selectorKeyComps=explode($S,$flatSelectorKey);
		//$flatSelectorKey=implode($S,$selectorKeyComps);
		//$element=array();
		$element=$entry;
		if (empty($definition)){
			$element=array('tag'=>'input','type'=>'text','value'=>$value,'key'=>$selectorKeyComps);
		} else {
			$flatDefinition=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($definition);
			foreach($flatDefinition as $definitionKey=>$definitionValue){
				$definitionKeyComps=explode('@',$definitionKey);
				if (count($definitionKeyComps)!==2){
					throw new \ErrorException('Function '.__FUNCTION__.': Defintion format error with definition-Key '.$definitionKey.'.',0,E_ERROR,__FILE__,__LINE__);
				}
				$definitionKey=array_shift($definitionKeyComps);
				$definitionKey=trim($definitionKey,$S.'*');
				if (strpos($flatSelectorKey,$definitionKey)!==FALSE){
					$definitionAttr=array_pop($definitionKeyComps);
					$sPos=strpos($definitionAttr,$S);
					if ($sPos!==FALSE){
						$tmp=$definitionAttr;
						$definitionAttr=substr($definitionAttr,0,$sPos);
						$subKey=substr($tmp,$sPos+strlen($S));
						$element[$definitionAttr][$subKey]=$definitionValue;
					} else {
						$element[$definitionAttr]=$definitionValue;
					}
				}
			}
			foreach($element as $definitionAttr=>$definitionValue){$element[$definitionAttr]=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flat2arr($definitionValue);}
			$element['key']=$selectorKeyComps;
			$element['callingClass']=$callingClass;
			$element['callingFunction']=$callingFunction;
			$element=$this->elementDef2element($element,$value);
		}
		return $element;
	}
	
	public function definition2html($definition,$entry=array(),$callingClass=FALSE,$callingFunction=FALSE,$isDebugging=FALSE){
		$debugArr=array('definition'=>$definition,'entry'=>$entry,'elements'=>array());
		if (empty($callingClass)){$callingClass=__CLASS__;}
		if (empty($callingFunction)){$callingFunction=__FUNCTION__;}
		// flatten arrays
		$flatEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
		$flatDefinition=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($definition);
		$S=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$entryArr=array();
		foreach($flatDefinition as $definitionKey=>$definitionValue){
			$definitionKeyComps=explode('@',$definitionKey);
			$definitionKey=array_shift($definitionKeyComps);
			$definitionKey=trim($definitionKey,$S);
			if (isset($flatEntry[$definitionKey])){$entryArr[$definitionKey]['value']=$flatEntry[$definitionKey];}
			// get attribute
			$definitionKeyAttr=array_pop($definitionKeyComps);
			if (empty($definitionKeyAttr)){continue;}
			$entryArr[$definitionKey][$definitionKeyAttr]=$definitionValue;
		}
		// create matrices
		$matrices=array();
		foreach($entryArr as $key=>$defArr){
			$defArr=array_merge($flatEntry,$defArr);
			$defArr=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flat2arr($defArr);
			$keyComps=explode($S,$key);
			if (empty($defArr['key'])){$defArr['key']=$keyComps;}
			$defArr['callingClass']=$callingClass;
			$defArr['callingFunction']=$callingFunction;
			$key=array_pop($keyComps);
			if (empty($keyComps)){$caption=$key;} else {$caption=implode(' &rarr; ',$keyComps);}
			$value=$this->elementDef2element($defArr);
			$debugArr['elements'][$key]=array('defArr'=>$defArr,'value'=>$value);
			if (empty($value)){
				// The element has probably no Read access on entry level or element level
				// You can overwrite entry Read access on the element level with '@Read'
			} else {
				$matrices[$caption][$key]['Value']=$value;
			}
		}
		// create html
		$html='';
		foreach($matrices as $caption=>$matrix){
			if (strpos($caption,'&rarr;')===FALSE || !empty($definition['hideKeys'])){$hideKeys=TRUE;} else {$hideKeys=FALSE;}
			if (!empty($definition['hideCaption'])){$caption='';}
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'skipEmptyRows'=>!empty($definition['skipEmptyRows']),'hideHeader'=>TRUE,'hideKeys'=>$hideKeys,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		if ($isDebugging){
			$debugArr['callingClass']=$callingClass;
			$debugArr['callingFunction']=$callingFunction;
			$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $html;
	}
		
	public function definition2form($definition,$entry=array(),$isDebugging=FALSE){
		$debugArr=array('definition'=>$definition,'entry_in'=>$entry,'entry_updated'=>array());
		$html='';
		if (empty($entry)){
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>'Called '.__FUNCTION__.' with empty entry.'));
		} else {
			// form processing
			$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
			$debugArr['formData']=$formData;
			if (isset($formData['cmd']['delete'])){
				$this->arr['SourcePot\Datapool\Foundation\Database']->deleteEntries($entry);
			} else if (!empty($formData['cmd'])){
				$entry['entryIsUpdated']=TRUE;
				$entry=array_replace_recursive($entry,$formData['val']);
				if (empty(current($formData['files']))){
					$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->unifyEntry($entry);
					$debugArr['entry_updated']=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
				} else {
					$fileArr=current(current($formData['files']));
					$entry=$this->arr['SourcePot\Datapool\Foundation\Filespace']->file2entries($fileArr,$entry);
				}
				$statistics=$this->arr['SourcePot\Datapool\Foundation\Database']->getStatistic();
				if (isset($this->arr['SourcePot\Datapool\Foundation\Logging'])){
					$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>ucfirst($entry['Source']).'-entry processed: '.$this->arr['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics),'priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));	
				}
			}
			if (isset($this->arr['SourcePot\Datapool\Tools\MediaTools'])){
				$iconArr=$this->arr['SourcePot\Datapool\Tools\MediaTools']->getIcon(array('selector'=>$entry));
				$html.=$iconArr['html'];
			}
			$html.=$this->definition2html($definition,$entry,__CLASS__,__FUNCTION__);
		}
		if ($isDebugging){
			$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $html;
	}
	
	private function elementDef2element($element,$outputStr=NULL){
		$element=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($element);
		// check access
		if (!$this->arr['SourcePot\Datapool\Foundation\Access']->access($element,'Read')){
			return array();
			return array('tag'=>'p','element-content'=>'Read access denied');
		}
		// check if element requests method
		if (!empty($element['function'])){return $this->defArr2html($element);}
		// get output string
		if (isset($outputStr)){
			// nothing to do
		} else if (isset($element['element-content'])){
			$outputStr=$element['element-content'];
			unset($element['element-content']);
		} else if (isset($element['value'])){
			$outputStr=$element['value'];
			unset($element['value']);
		} else if (isset($element['default'])){
			$outputStr=$element['default'];
			unset($element['default']);
		}
		$outputStr=strval($outputStr);
		// compile tag
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->access($element,'Write')){
			// write access
			if (!isset($element['tag'])){
				$element['tag']='input';
				$element['type']='text';
			}
			if (strcmp($element['tag'],'input')===0){
				if (strcmp($element['type'],'text')===0 && strlen($outputStr)>15){
					$element['tag']='textarea';
					$element['element-content']=$outputStr;
				} else if (strcmp($element['type'],'file')!==0){
					$element['value']=$outputStr;
				}
			} else {
				$element['element-content']=$outputStr;
			}
		} else {
			// read access
			if (!isset($element['tag'])){$element['tag']='p';}
			if (strcmp($element['tag'],'input')===0){
				$element['disabled']=TRUE;
				$element['value']=$outputStr;
			} else {
				$element['tag']='div';
				if (isset($element['style'])){unset($element['style']);}
				$element['class']='gen_'.$this->arr['SourcePot\Datapool\Tools\MiscTools']->getHash($element['key'],TRUE);
				if (empty($outputStr)){$element=array();} else {$element['element-content']=$outputStr;}
			}
		}
		return $element;
	}
	
	private function defArr2html($defArr){
		$html='';
		$function=$defArr['function'];
		if (isset($defArr['class'])){$class=$defArr['class'];} else {$class='SourcePot\Datapool\Tools\HTMLbuilder';}
		if (method_exists($class,$function)){
			$defArr['keep-element-content']=TRUE;
			$defArr['selector']=array();
			$selectorKeys=$this->arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($defArr['Source']);
			$selectorKeys+=array('Source'=>array());
			foreach($selectorKeys as $selectorKey=>$templateArr){
				if (isset($defArr[$selectorKey])){$defArr['selector'][$selectorKey]=$defArr[$selectorKey];}
				if (strcmp($selectorKey,'Read')!==0 && strcmp($selectorKey,'Write')!==0){unset($defArr[$selectorKey]);}
			}
			$return=$this->arr[$class]->$function($defArr);
			if (is_array($return)){$html=$return['html'];} else {$html=$return;}
		} else {
			$errArr=array('tag'=>'p','element-content'=>'Function '.$function.'() not found.');
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($errArr);
		}
		return $html;
	}
	
}
?>