<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Definitions{
	
	private $oc;
	
	private $entryTable;
	private $entryTemplate=array();
	
	public function __construct($oc){
		$this->oc=$oc;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($oc){
		$this->oc=$oc;
		$this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
	}
	
	public function getEntryTable(){return $this->entryTable;}

	public function getEntryTemplate(){return $this->entryTemplate;}
	
	/**
	* This method returns the definition name from the class argument provided. 
	* @return string
	*/
	private function class2name($class){
		$source=$this->oc['SourcePot\Datapool\Root']->class2source($class);
		if ($source){
			return $source;
		} else {
			$classComps=explode('\\',$class);
			return array_pop($classComps);
		}
	}
	
	/**
	* This method creates a definition entry and returns this entry based on arguments callingClass and the provided defintion.
	* If callingClass provides the getEntryTable() method, i.e. employs data storage in the database, the corresponding database table will be used as definition name.
	* Otherwise the class name excluding the namespace will be used. It is than assumed, that the class employs data storage in files in the setup dir space.
	* To force data storage in files, preceding character cann be added to the callingClass argument, e.g. "!" 
	* @return array
	*/
	public function addDefintion($callingClass,$definition){
		$entry=array('Source'=>$this->entryTable,'Group'=>'Templates','Folder'=>$callingClass,'Name'=>$this->class2name($callingClass),'Type'=>'definition','Owner'=>'SYSTEM');
		$entry['EntryId']=md5(json_encode($entry));
		$entry['Content']=$definition;
		return $this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,TRUE);
	}
	
	/**
	* This method returns the definition for the provided entry, based on the entry['Type'] or entry['Class'].
	* Only the first part (everything up to the first space character) of entry['Type'] is used.
	* @return array
	*/
	public function getDefinition($entry,$isDebugging=FALSE){
		$selector=array('Source'=>$this->entryTable,'Group'=>'Templates');
		if (!empty($entry['Class'])){
			$selector['Name']=$this->class2name($entry['Class']);	
		} else if (!empty($entry['Type'])){
			$typeComps=explode(' ',$entry['Type']);
			$selector['Name']=array_shift($typeComps);
		} else {
			throw new \ErrorException('Function '.__FUNCTION__.': Entry missing Type- or Class-key.',0,E_ERROR,__FILE__,__LINE__);
		}
		$arr=array('entry'=>$entry,'selector'=>$selector,'definition'=>array());
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $entry){
			$arr['definition']=$entry;
			break;
		}
		if ($isDebugging){$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($arr);}
		return $arr['definition'];
	}
	
	/**
	* This method returns an entry from the definition and the entry provided as argument, as well as default values.
	* Default values originate from the database entry template as well as default values provided by the defintion.
	* @return array
	*/
	public function definition2entry($definition,$entry=array(),$isDebugging=FALSE){
		$debugArr=array('definition'=>$definition,'entry_in'=>$entry);
		$flatArrayKeySeparator=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
		$flatDefinition=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($definition);
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
		$entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntry);
		$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addEntryDefaults($entry);
		if ($isDebugging){
			$debugArr['defaultArrKeys2remove']=$defaultArrKeys2remove;
			$debugArr['entry_out']=$entry;
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $entry;
	}
	
	/**
	* This method returns an element which can be presented on the webpage based on the entry and flatSelectorKey argument provided.
	* You can use the wildcard character '*' at the end of $flatSelectorKey.
	* In a first step the method trys to get any exsiting definition for the provided entry. If this fails, a standard text (input) field will be returned.
	* If the definition exsists, the webpaghe element will be created based on this definition. 
	* @return array
	*/
	public function selectorKey2element($entry,$flatSelectorKey,$value=NULL,$callingClass=FALSE,$callingFunction=FALSE){
		$value=strval($value);
		$definition=$this->getDefinition($entry);	
		$S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$selectorKeyComps=explode($S,$flatSelectorKey);
		$element=array();
		if (empty($definition)){
			if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write')){
				$element=array('tag'=>'input','type'=>'text','value'=>$value,'key'=>$selectorKeyComps);
			} else if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Read')){
				$element=array('tag'=>'p','element-content'=>$value,'keep-element-content'=>TRUE);
			} else {
				$element=array('tag'=>'p','element-content'=>'access denied','keep-element-content'=>TRUE);
			}
		} else {
			$flatDefinition=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($definition['Content']);
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
			foreach($element as $definitionAttr=>$definitionValue){
				$element[$definitionAttr]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($definitionValue);
			}
			$element['key']=$selectorKeyComps;
			$element['callingClass']=$callingClass;
			$element['callingFunction']=$callingFunction;
			$element=$this->elementDef2element($element,$value);
		}
		return $element;
	}
	
	/**
	* This method returns a complex html form consisting of tables and based on the definition for the entry provided as method argument.	
	* @return array
	*/
	public function definition2html($definition,$entry,$callingClass=FALSE,$callingFunction=FALSE,$isDebugging=FALSE){
		$debugArr=array('definition'=>$definition,'entry'=>$entry,'elements'=>array());
		if (empty($callingClass)){$callingClass=__CLASS__;}
		if (empty($callingFunction)){$callingFunction=__FUNCTION__;}
		// flatten arrays
		$flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
		$flatDefinition=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($definition['Content']);
		$S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
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
		$settings=array();
		$matrices=array();
		foreach($entryArr as $key=>$defArr){
			// get key components
			$keyComps=explode($S,$key);
			$keyArr=$keyComps;
			$key=array_pop($keyComps);
			if (empty($keyComps)){$caption=$key;} else {$caption=implode(' &rarr; ',$keyComps);}
			if (!isset($settings[$caption])){$settings[$caption]=array();}
			if (isset($defArr['tag']) || isset($defArr['function'])){
				$defArr=array_merge($flatEntry,$defArr);
				$defArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($defArr);
				$defArr['callingClass']=$callingClass;
				$defArr['callingFunction']=$callingFunction;
				if (empty($defArr['key'])){$defArr['key']=$keyArr;}
				$value=$this->elementDef2element($defArr);
				$debugArr['elements'][$key]=array('defArr'=>$defArr,'value'=>$value);
				if (empty($value)){
					// The element has probably no Read access on entry level or element level
					// You can overwrite entry Read access on the element level with '@Read'
				} else {
					$matrices[$caption][$key]['Value']=$value;
				}
			} else {
				$settings[$caption]=$defArr;
			}
		}
		$debugArr['settings']=$settings;
		if (isset($settings[''])){
			$globSetting=$settings[''];
			unset($settings['']);
		} else {
			$globSetting=array();
		}
		// create html
		$html='';
		foreach($matrices as $caption=>$matrix){
			$tableArr=array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>$caption);
			$tableArr=array_replace_recursive($globSetting,$settings[$caption],$tableArr);
			$html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table($tableArr);
		}
		if ($isDebugging){
			$debugArr['callingClass']=$callingClass;
			$debugArr['callingFunction']=$callingFunction;
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $html;
	}

	public function entry2form($entry=array(),$isDebugging=FALSE){
		$definition=$this->getDefinition($entry);
		$debugArr=array('definition'=>$definition,'entry_in'=>$entry,'entry_updated'=>array());
		$html='';
		if (empty($definition)){
			$html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->traceHtml('Problem: Method "'.__FUNCTION__.'" no definition found for the provided entry.');
		} else {
			if ($this->oc['SourcePot\Datapool\Tools\MiscTools']->startsWithUpperCase($definition['Name'])){
				// entry is stored in setup dirspace
				$dataStorageClass='SourcePot\Datapool\Foundation\Filespace';
			} else {
				// entry is stored in database
				$dataStorageClass='SourcePot\Datapool\Foundation\Database';	
			}
			if (empty($entry)){
				$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>'Called '.__FUNCTION__.' with empty entry.'));
			} else {
				// form processing
				$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
				$debugArr['formData']=$formData;
				if (isset($formData['cmd']['delete'])){
					$this->oc[$dataStorageClass]->deleteEntries($entry);
				} else if (!empty($formData['cmd'])){
					$entry['entryIsUpdated']=TRUE;
					$entry=array_replace_recursive($entry,$formData['val']);
					if ($formData['hasValidFiles']){
						$flatFile=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($formData['files']);
						$fileArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatArrLeaves($flatFile);
						if ($fileArr['error']==0){
							$debugArr['entry_updated']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entries($fileArr,$entry);
						}
					} else {
						$entry=$this->oc[$dataStorageClass]->unifyEntry($entry);
						$debugArr['entry_updated']=$this->oc[$dataStorageClass]->updateEntry($entry);
					}
					$statistics=$this->oc[$dataStorageClass]->getStatistic();
					if (isset($this->oc['SourcePot\Datapool\Foundation\Logging'])){
						$entryType=(isset($entry['Source']))?ucfirst(strval($entry['Source'])):$entry['Class'];
						$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>$entryType.'-entry processed: '.$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics),'priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));	
					}
				}
				if (isset($this->oc['SourcePot\Datapool\Tools\MediaTools'])){
					$iconArr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getIcon(array('selector'=>$entry));
					$html.=$iconArr['html'];
				}
				$html.=$this->definition2html($definition,$entry,__CLASS__,__FUNCTION__,$isDebugging);
			}
		}
		if ($isDebugging){
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $html;
	}
	
	private function elementDef2element($element,$outputStr=NULL){
		$element=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($element);
		// check access
		if (!$this->oc['SourcePot\Datapool\Foundation\Access']->access($element,'Read')){
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
		if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($element,'Write')){
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
				$element['class']='gen_'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($element['key'],TRUE);
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
			$selectorKeys=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($defArr['Source']);
			$selectorKeys+=array('Source'=>array());
			foreach($selectorKeys as $selectorKey=>$templateArr){
				if (isset($defArr[$selectorKey])){$defArr['selector'][$selectorKey]=$defArr[$selectorKey];}
				if (strcmp($selectorKey,'Read')!==0 && strcmp($selectorKey,'Write')!==0){unset($defArr[$selectorKey]);}
			}
			$return=$this->oc[$class]->$function($defArr);
			if (is_array($return)){$html=$return['html'];} else {$html=$return;}
		} else {
			$errArr=array('tag'=>'p','element-content'=>'Function '.$function.'() not found.');
			$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($errArr);
		}
		return $html;
	}
	
}
?>