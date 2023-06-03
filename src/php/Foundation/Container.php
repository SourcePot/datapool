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

class Container{

	private $oc;
	
	public function __construct($oc){
		$this->oc=$oc;
	}

	public function init($oc){
		$this->oc=$oc;
	}
	
	public function jsCall($arr){
		$jsAnswer=array();
		if (isset($_POST['function'])){
			if (strcmp($_POST['function'],'container')===0){
				$jsAnswer['html']=$this->container(FALSE,'',array(),array(),array(),$_POST['container-id'],TRUE);
			} else if (strcmp($_POST['function'],'containerMonitor')===0){
				$jsAnswer['arr']=array('isUp2date'=>$this->containerMonitor($_POST['container-id']),'container-id'=>$_POST['container-id']);
			} else if (strcmp($_POST['function'],'loadEntry')===0){
				$jsAnswer['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->loadEntry($_POST);
			} else if (strcmp($_POST['function'],'setCanvasElementPosition')===0){
				$jsAnswer['arr']=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->setCanvasElementPosition($_POST['arr']);
			} else {
				
			}
		} else if (isset($_POST['loadImage'])){
			$jsAnswer=$this->oc['SourcePot\Datapool\Tools\MediaTools']->loadImage($_POST['loadImage']);
		} else {
			//$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($_POST,hrtime(TRUE).'-'.__FUNCTION__);
		}
		$arr['page html']=json_encode($jsAnswer,JSON_INVALID_UTF8_IGNORE);
		return $arr;
	}
	
	public function container($key=FALSE,$function='',$selector=array(),$settings=array(),$wrapperSettings=array(),$containerId=FALSE,$isJScall=FALSE){
		// This function provides a dynamic web-page container, it returns html-script.
		// The state of forms whithin the container is stored in  $_SESSION['Container'][$container-id]
		if ($isJScall){
			if (isset($_SESSION['container store'][$containerId]['callJScount'])){$_SESSION['container store'][$containerId]['callJScount']++;} else {$_SESSION['container store'][$containerId]['callJScount']=1;}
			$function=$_SESSION['container store'][$containerId]['function'];
			$containerId=$_SESSION['container store'][$containerId]['callingFunction'];
			$wrapperSettings=$_SESSION['container store'][$containerId]['wrapperSettings'];
		} else {
			$containerId=md5($key);
			if (isset($_SESSION['container store'][$containerId]['callPageCount'])){$_SESSION['container store'][$containerId]['callPageCount']++;} else {$_SESSION['container store'][$containerId]['callPageCount']=1;}
			$_SESSION['container store'][$containerId]['callingClass']=__CLASS__;
			$_SESSION['container store'][$containerId]['callingFunction']=$containerId;
			$_SESSION['container store'][$containerId]['containerId']=$containerId;
			$_SESSION['container store'][$containerId]['function']=$function;
			$_SESSION['container store'][$containerId]['selector']=$selector;
			$_SESSION['container store'][$containerId]['containerKey']=$key;
			if (!isset($_SESSION['container store'][$containerId]['settings'])){$_SESSION['container store'][$containerId]['settings']=$settings;}
			$_SESSION['container store'][$containerId]['wrapperSettings']=$wrapperSettings;
			$this->containerMonitor($containerId,$selector);
		}
		$html='<div busy-id="busy-'.$containerId.'" class="container-busy"></div>';
		//$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($_SESSION['container store'][$containerId]);
		$return=$this->$function($_SESSION['container store'][$containerId]);
		if (empty($return)){return '';}
		$html.=$return['html'];
		if (isset($return['wrapperSettings'])){
			$wrapperSettings=array_merge($wrapperSettings,$return['wrapperSettings']);
		}
		if (isset($return['settings'])){$_SESSION['container store'][$containerId]=array_merge($_SESSION['container store'][$containerId],$return['settings']);}
		$reloadBtnStyle=array('position'=>'absolute','top'=>'0','right'=>'0','margin'=>'0','padding'=>'3px','border'=>'none','background-color'=>'#ccc');
		$reloadBtnArr=array('tag'=>'button','type'=>'submit','element-content'=>'&orarr;','class'=>'reload-btn','container-id'=>'btn-'.$containerId,'style'=>$reloadBtnStyle,'key'=>array('reloadBtnArr'),'callingClass'=>__CLASS__,'callingFunction'=>$containerId,'keep-element-content'=>TRUE);
		$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($reloadBtnArr);
		// add wrappers
		$wrapperDiv=$wrapperSettings;
		$wrapperDiv['tag']='article';
		$wrapperDiv['container-id']=$containerId;
		$wrapperDiv['element-content']=$html;
		$wrapperDiv['keep-element-content']=TRUE;
		$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($wrapperDiv);
		return $html;
	}

	private function containerMonitor($containerId,$registerSelector=FALSE){
		if ($registerSelector===FALSE){
			// check if data selected by registered selector for the selected container has changed
			if (!isset($_SESSION['container monitor'][$containerId])){return TRUE;}
			$isUpToDate=TRUE;
			if (isset($_SESSION['container monitor'][$containerId]['selector']['refreshInterval'])){
				$isUpToDate=((time()-$_SESSION['container monitor'][$containerId]['refreshed'])<$_SESSION['container monitor'][$containerId]['selector']['refreshInterval']);
			}
			$newHash=$this->selector2hash($_SESSION['container monitor'][$containerId]['selector']);
			//$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file(array('current'=>$_SESSION['container monitor'][$containerId],'new hash'=>$newHash,'isUpToDate'=>$isUpToDate,'last refresh'=>time()-$_SESSION['container monitor'][$containerId]['refreshed']),__FUNCTION__.'-'.$_SESSION['container monitor'][$containerId]['selector']['Source']);
			if (strcmp($_SESSION['container monitor'][$containerId]['hash'],$newHash)===0 && $isUpToDate){
				// no change detected
				return TRUE;
			} else {
				// change detected
				$_SESSION['container monitor'][$containerId]['containerId']=$containerId;
				$_SESSION['container monitor'][$containerId]['hash']=$newHash;
				$_SESSION['container monitor'][$containerId]['refreshed']=time();
				return FALSE;
			}
		} else {
			// register the the selector
			$_SESSION['container monitor'][$containerId]=array('hash'=>$this->selector2hash($registerSelector),'selector'=>$registerSelector,'containerId'=>$containerId,'refreshed'=>time());
		}
		return TRUE;
	}
	
	private function selector2hash($registerSelector){
		if (isset($registerSelector['isSystemCall'])){$isSystemCall=$registerSelector['isSystemCall'];} else {$isSystemCall=FALSE;}
		if (isset($registerSelector['rightType'])){$rightType=$registerSelector['rightType'];} else {$rightType='Read';}
		if (isset($registerSelector['orderBy'])){$orderBy=$registerSelector['orderBy'];} else {$orderBy=FALSE;}
		if (isset($registerSelector['isAsc'])){$isAsc=$registerSelector['isAsc'];} else {$isAsc=FALSE;}
		if (isset($registerSelector['limit'])){$limit=$registerSelector['limit'];} else {$limit=FALSE;}
		if (isset($registerSelector['offset'])){$offset=$registerSelector['offset'];} else {$offset=FALSE;}
		if (isset($registerSelector['selectExprArr'])){$selectExprArr=$registerSelector['selectExprArr'];} else {$selectExprArr=array();}
		$hash='';
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($registerSelector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$selectExprArr,TRUE) as $row){
			$hash=$row['hash'];
		}
		return strval($hash);
	}
	
	// Standard html widgets emploeyed by the container method

	public function generic($arr){
		// This method provides a generic container widget
		if (!isset($arr['html'])){$arr['html']='';}
		if (empty($arr['settings']['method']) || empty($arr['settings']['classWithNamespace'])){
			$arr['html'].='Generic container called without required settings "method" or "classWithNamespace".';
		} else if (method_exists($arr['settings']['classWithNamespace'],$arr['settings']['method'])){
			$method=$arr['settings']['method'];
			$arr=$this->oc[$arr['settings']['classWithNamespace']]->$method($arr);
		} else {
			$arr['html'].='Generic container called with with invalid method setting. Check container settings "classWithNamespace" and/or "method".';	
		}
		return $arr;
	}

	public function entryEditor($arr,$isDebugging=FALSE){
		$arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
		if (empty($arr['selector'])){return $arr;}
		if (!isset($_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']])){$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$arr['settings'];}
		$settings=$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']];
		$debugArr=array('arr in'=>$arr,'settings in'=>$settings);
		if (!isset($arr['html'])){$arr['html']='';}
		$definition=$this->oc['SourcePot\Datapool\Foundation\Definitions']->getDefinition($arr['selector']);
		$tableInfo=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($arr['selector']['Source']);
		$flatDefinition=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($definition);
		$entryCanWrite=!empty($this->oc['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],'Write'));
		if (empty($arr['selector'])){
			$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>'No entry found with the selector provided'));
		} else {
			$S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
			$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
			$debugArr['formData']=$formData;
			if (!empty($formData['cmd'])){
				if (isset($formData['cmd']['Upload'])){
					$fileArr=current(current($formData['files']));
					$entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entries($fileArr,$arr['selector']);
				} else if (isset($formData['cmd']['stepIn'])){
					if (empty($settings['selectorKey'])){$selectorKeyComps=array();} else {$selectorKeyComps=explode($S,$settings['selectorKey']);}
					$selectorKeyComps[]=key($formData['cmd']['stepIn']);
					$settings['selectorKey']=implode($S,$selectorKeyComps);
				} else if (isset($formData['cmd']['setSelectorKey'])){
					$settings['selectorKey']=key($formData['cmd']['setSelectorKey']);
				} else if (isset($formData['cmd']['deleteKey'])){
					$arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arrDeleteKeyByFlatKey($arr['selector'],key($formData['cmd']['deleteKey']));
					$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
				} else if (isset($formData['cmd']['addValue'])){
					$flatKey=$formData['cmd']['addValue'].$S.$formData['val']['newKey'];
					$arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arrUpdateKeyByFlatKey($arr['selector'],$flatKey,'Enter new value here ...');
					$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
				} else if (isset($formData['cmd']['addArr'])){
					$flatKey=$formData['cmd']['addArr'].$S.$formData['val']['newKey'].$S.'...';
					$arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arrUpdateKeyByFlatKey($arr['selector'],$flatKey,'to be deleted');
					$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
				} else if (isset($formData['cmd']['save']) || isset($formData['cmd']['reloadBtnArr'])){
					$arr['selector']=array_replace_recursive($arr['selector'],$formData['val']);
					$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
				} else {
					
				}
				$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$settings;
			}
			$navHtml='';
			if (empty($settings['selectorKey'])){$selectorKeyComps=array();} else {$selectorKeyComps=explode($S,$settings['selectorKey']);}
			$level=count($selectorKeyComps);
			while(count($selectorKeyComps)>0){
				$key=array_pop($selectorKeyComps);
				$btnArrKey=implode($S,$selectorKeyComps);
				$element=array('tag'=>'button','element-content'=>$key.' &rarr;','key'=>array('setSelectorKey',$btnArrKey),'keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
				$element['style']=array('font-size'=>'0.9em','border'=>'none','border-bottom'=>'1px solid #aaa');
				$navHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element).$navHtml;
			}
			// create table matrix
			$btnsHtml='';
			if ($entryCanWrite){
				$btnsHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'key'=>array('save'),'value'=>'save','title'=>'Save','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
			}
			$btnArr=$arr;
			$btnArr['cmd']='download';
			$btnsHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
			$matrix=array('Nav'=>array('value'=>$navHtml,'cmd'=>$btnsHtml));
			if (!isset($settings['selectorKey'])){$settings['selectorKey']='';}
			$flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($arr['selector']);
			foreach($flatEntry as $flatKey=>$value){
				if (strpos($flatKey,$settings['selectorKey'])!==0){continue;}
				$flatKeyComps=explode($S,$flatKey);
				if (!isset($tableInfo[$flatKeyComps[0]])){continue;}
				if (empty($settings['selectorKey'])){$subFlatKey=str_replace($settings['selectorKey'],'',$flatKey);} else {$subFlatKey=str_replace($settings['selectorKey'].$S,'',$flatKey);}
				$subFlatKeyComps=explode($S,$subFlatKey);
				$valueHtml='';
				if (count($subFlatKeyComps)>1){
					// value is array
					$element=array('tag'=>'button','element-content'=>'{...}','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
					$element['key']=array('stepIn',$subFlatKeyComps[0]);
					$valueHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
				} else {
					// non-array value
					$element=$this->oc['SourcePot\Datapool\Foundation\Definitions']->selectorKey2element($arr['selector'],$flatKey,$value,$arr['callingClass'],$arr['callingFunction']);
					if (empty($element)){
						$valueHtml='';
					} else if (is_array($element)){
						$element['excontainer']=TRUE;
						$element['callingClass']=$arr['callingClass'];
						$element['callingFunction']=$arr['callingFunction'];
						$valueHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
					} else {
						$valueHtml=$element;
					}
				}
				$cmdHtml='';
				if (count($flatKeyComps)>1 && $level>0 && $entryCanWrite){
					$element=array('tag'=>'button','element-content'=>'&xcup;','key'=>array('deleteKey',$flatKey),'hasCover'=>TRUE,'title'=>'Delete key','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
					$cmdHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
				}
				$label=array_shift($subFlatKeyComps);
				$matrix[$label]=array('value'=>$valueHtml,'cmd'=>$cmdHtml);
			} // loop through flat array
			if ($level>0 && $entryCanWrite){
				$flatKey=$settings['selectorKey'];
				$element=array('tag'=>'input','type'=>'text','key'=>array('newKey'),'value'=>'','style'=>array('color'=>'#fff','background-color'=>'#1b7e2b'),'excontainer'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
				$valueHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
				$element=array('tag'=>'button','element-content'=>'...','key'=>array('addValue'),'value'=>$flatKey,'title'=>'Add value','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
				$cmdHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
				$element=array('tag'=>'button','element-content'=>'{...}','key'=>array('addArr'),'value'=>$flatKey,'title'=>'Add array','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
				$cmdHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
				$matrix['<i>Add</i>']=array('value'=>$valueHtml,'cmd'=>$cmdHtml);
			}
			$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$arr['selector']['Name']));
			if ($level==0){
				$arr['hideKeys']=TRUE;
				$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryControls($arr);
				$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryLogs($arr);
			}
		}
		if ($isDebugging){
			$debugArr['arr out']=$arr;
			$debugArr['settings out']=$settings;
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $arr;		
	}
	
	private function entryList($arr,$isDebugging=FALSE){
		if (!isset($arr['html'])){$arr['html']='';}
		if (!isset($_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']])){$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$arr['settings'];}
		$settings=$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']];
		$debugArr=array('arr'=>$arr,'settings in'=>$settings);
		$S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		// get settings
		if (!isset($settings['columns'])){
			$settings['columns']=array(array('Column'=>'Name','Filter'=>''),array('Column'=>'Date','Filter'=>''),array('Column'=>'preview','Filter'=>''));
		}
		if (!isset($settings['isSystemCall'])){$settings['isSystemCall']=FALSE;}
		if (!isset($settings['orderBy'])){$settings['orderBy']='Date';}
		if (!isset($settings['isAsc'])){$settings['isAsc']=FALSE;}
		if (!isset($settings['limit'])){$settings['limit']=10;}
		if (!isset($settings['offset'])){$settings['offset']=FALSE;}
		// form processing
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
		if (!empty($formData['cmd'])){
			$debugArr['formData']=$formData;
			// update from form values
			$settings=array_replace_recursive($settings,$formData['val']);
			// command processing
			if (isset($formData['cmd']['addColumn'])){
				$settings['columns'][]=array('Column'=>'EntryId','Filter'=>'');
			} else if (isset($formData['cmd']['removeColumn'])){
				$key2remove=key($formData['cmd']['removeColumn']);
				unset($settings['columns'][$key2remove]);
			} else if (isset($formData['cmd']['desc'])){
				$settings['orderBy']=key($formData['cmd']['desc']);
				$settings['isAsc']=FALSE;
			} else if (isset($formData['cmd']['asc'])){
				$settings['orderBy']=key($formData['cmd']['asc']);
				$settings['isAsc']=TRUE;
			}
			$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$settings;
		}
		// add column button
		$element=array('tag'=>'button','element-content'=>'➕','key'=>array('addColumn'),'value'=>'add','title'=>'add column','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
		$addColoumnBtn=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
		$settings['columns'][]=array('Column'=>$addColoumnBtn,'Filter'=>FALSE);
		// get selector
		$filterSkipped=FALSE;
		$selector=$this->selectorFromSetting($arr['selector'],$settings,FALSE);
		$rowCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,$settings['isSystemCall']);
		if (empty($rowCount)){
			$selector=$this->selectorFromSetting($arr['selector'],$settings,TRUE);
			$rowCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,$settings['isSystemCall']);
			$filterSkipped=TRUE;
		}
		if ($rowCount<=$settings['offset']){$settings['offset']=0;}
		if (!empty($rowCount)){
			// create html
			$filterKey='Filter';
			$matrix=array();
			$columnOptions=array();
			foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$settings['isSystemCall'],'Read',$settings['orderBy'],$settings['isAsc'],$settings['limit'],$settings['offset']) as $entry){
				$rowIndex=$entry['rowIndex']+intval($settings['offset'])+1;
				//$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($entry,__FUNCTION__.'-'.$entry['EntryId']);
				$flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
				// setting up
				if (empty($columnOptions)){
					$columnOptions['preview']='&#10004; File preview';
					foreach($flatEntry as $flatColumnKey=>$value){
						$columnOptions[$flatColumnKey]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($flatColumnKey);
					}
				}
				foreach($settings['columns'] as $columnIndex=>$cntrArr){
					$column=explode($S,$cntrArr['Column']);
					$column=array_shift($column);
					// columns selector row
					$matrix['Columns'][$columnIndex]='';
					if ($cntrArr['Filter']===FALSE){
						// add column button
						$matrix['Columns'][$columnIndex]=$cntrArr['Column'];
						$matrix[$filterKey][$columnIndex]='';
						$btnArr=$arr;
						$btnArr['selector']=$entry;
						$btnArr['cmd']='select';
						$matrix[$rowIndex][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
						$btnArr['cmd']='download';
						$matrix[$rowIndex][$columnIndex].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
						$btnArr['cmd']='delete';
						$matrix[$rowIndex][$columnIndex].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
					} else {
						$matrix[$filterKey][$columnIndex]='';
						// filter text field
						if ($filterSkipped && !empty($cntrArr['Filter'])){$style=array('background-color'=>'#800');} else {$style=array();}
						$filterTextField=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'text','style'=>$style,'value'=>$cntrArr['Filter'],'key'=>array('columns',$columnIndex,'Filter'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
						// "order by"-buttons
						if (strcmp(strval($settings['orderBy']),$column)===0){$styleBtnSetting=array('color'=>'#fff','background-color'=>'#a00');} else {$styleBtnSetting=array();}
						if ($settings['isAsc']){$style=$styleBtnSetting;} else {$style=array();}
						$element=array('tag'=>'button','element-content'=>'&#9650;','key'=>array('asc',$column),'value'=>$columnIndex,'style'=>array('padding'=>'0','line-height'=>'1em','font-size'=>'1.5em'),'title'=>'order ascending','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'style'=>$style,'callingFunction'=>$arr['callingFunction']);
						$matrix[$filterKey][$columnIndex].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
						$matrix[$filterKey][$columnIndex].=$filterTextField;
						if (!$settings['isAsc']){$style=$styleBtnSetting;} else {$style=array();}
						$element=array('tag'=>'button','element-content'=>'&#9660;','key'=>array('desc',$column),'value'=>$columnIndex,'style'=>array('padding'=>'0','line-height'=>'1em','font-size'=>'1.5em'),'title'=>'order descending','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'style'=>$style,'callingFunction'=>$arr['callingFunction']);
						$matrix[$filterKey][$columnIndex].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
						// remove column button
						$matrix['Columns'][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$columnOptions,'value'=>$cntrArr['Column'],'keep-element-content'=>TRUE,'key'=>array('columns',$columnIndex,'Column'),'style'=>array(),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
						if ($columnIndex>0){
							$element=array('tag'=>'button','element-content'=>'&xcup;','keep-element-content'=>TRUE,'key'=>array('removeColumn',$columnIndex),'value'=>'remove','hasCover'=>TRUE,'sytle'=>array('font-size'=>'1.5em'),'title'=>'remove column','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
							$matrix['Columns'][$columnIndex].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
						}
						// table rows
						if (strcmp($cntrArr['Column'],'preview')===0){
							$mediaArr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview(array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'selector'=>$entry,'style'=>array('width'=>'100%','max-width'=>300,'max-height'=>250)));
							$matrix[$rowIndex][$columnIndex]=$mediaArr['html'];
						} else {
							$matrix[$rowIndex][$columnIndex]='{Nothing here...}';
						}
						foreach($flatEntry as $flatColumnKey=>$value){
							if (strcmp($flatColumnKey,$cntrArr['Column'])!==0){continue;}
							$matrix[$rowIndex][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->getIframe($value,array());
						}
					}
				} // end of loop through columns
			} // end of loop through entries
			foreach($settings['columns'] as $columnIndex=>$cntrArr){
				if ($cntrArr['Filter']===FALSE){
					$matrix['Limit, offset'][$columnIndex]='';
				} else if ($columnIndex===0){
					$options=array(5=>'5',10=>'10',25=>'25',50=>'50',100=>'100',200=>'200');
					$matrix['Limit, offset'][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$options,'key'=>array('limit'),'value'=>$settings['limit'],'title'=>'rows to show','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
					$matrix['Limit, offset'][$columnIndex].=$this->getOffsetSelector($arr,$settings,$rowCount);
				} else {
					$matrix['Limit, offset'][$columnIndex]='';
				}
			}
			$caption=$arr['containerKey'];
			$caption.=' ('.$rowCount.')';
			$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		if ($isDebugging){
			$debugArr['arr out']=$arr;
			$debugArr['settings out']=$settings;
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $arr;		
	}
	
	private function getOffsetSelector($arr,$settings,$rowCount){
		$limit=intval($settings['limit']);
		if ($rowCount<=$limit){return '';}
		$options=array();
		$optionCount=ceil($rowCount/$limit);
		for($index=0;$index<$optionCount;$index++){
			$offset=$index*$limit;
			$upperOffset=$offset+$limit;
			$options[$offset]=strval($offset+1).'...'.strval(($upperOffset>$rowCount)?$rowCount:$upperOffset);
		}
		$html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$options,'key'=>array('offset'),'value'=>$settings['offset'],'title'=>'Offset from which rows will be shown','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
		return $html;
	}

	private function selectorFromSetting($selector,$settings,$resetFilter=FALSE){
		// This function is a suporting function for entryList() only.
		// It has no further use.
		$S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		foreach($settings['columns'] as $columnIndex=>$cntrArr){
			if (!isset($cntrArr['Filter'])){$settings['columns'][$columnIndex]['Filter']='';}
			if ($resetFilter){$cntrArr['Filter']='';}
			$column=explode($S,$cntrArr['Column']);
			$column=array_shift($column);
			if (!empty($cntrArr['Filter'])){$selector[$column]='%'.$cntrArr['Filter'].'%';}
		}
		return $selector;		
	}

	public function comments($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
		if (isset($formData['cmd']['Add comment'])){
			$arr['selector']['Source']=key($formData['cmd']['Add comment']);
			$arr['selector']['EntryId']=key($formData['cmd']['Add comment'][$arr['selector']['Source']]);
			$arr['selector']['timeStamp']=current($formData['cmd']['Add comment'][$arr['selector']['Source']]);
			$arr['selector']['Content']['Comments'][$arr['selector']['timeStamp']]=array('Comment'=>$formData['val']['comment'],'Author'=>$_SESSION['currentUser']['EntryId']);
			$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
		}
		if (isset($arr['selector']['Content']['Comments'])){$Comments=$arr['selector']['Content']['Comments'];} else {$Comments=array();}
		$commentsHtml='';
		foreach($Comments as $creationTimestamp=>$comment){
			$footer=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
			$footer.=' '.$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($comment['Author'],3);
			$commentHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$comment['Comment'],'class'=>'comment'));
			$commentHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$footer,'keep-element-content'=>TRUE,'class'=>'comment-footer'));
			$commentsHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$commentHtml,'keep-element-content'=>TRUE,'class'=>'comment'));
		}
		$targetId=$arr['callingFunction'].'-textarea';
		$newComment='';
		if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],'Write')){
			$newComment.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h3','element-content'=>'New comment','style'=>array('float'=>'left','clear'=>'both','margin'=>'30px 0 5px 5px')));
			$newComment.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'textarea','element-content'=>'...','key'=>array('comment'),'id'=>$targetId,'style'=>array('float'=>'left','clear'=>'both','margin'=>'5px','font-size'=>'1.5em'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
			$newComment.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Emojis for '.$arr['callingFunction'],'generic',$arr['selector'],array('method'=>'emojis','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder','target'=>$targetId));
			$newComment.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Add','key'=>array('Add comment',$arr['selector']['Source'],$arr['selector']['EntryId']),'value'=>time(),'style'=>array('float'=>'left','clear'=>'both','margin'=>'5px'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
			$newComment=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$newComment,'icon'=>'&#9998;','style'=>array('clear'=>'both','margin'=>'3px 5px')));
		}
		$arr['html'].=$commentsHtml.$newComment;
		return $arr;
	}
	
	public function tools($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$html='';
		$btn=$arr;
		$btn['style']=array('margin'=>'30px 5px 0 0');
		$btn['cmd']='download';
		$html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btn);
		$btn['cmd']='delete';
		$html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btn);
		$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'...'));
		return $arr;
	}

	public function getImageShuffle($arr,$isDebugging=FALSE){
		if (!isset($arr['html'])){$arr['html']='';}
		$settingsTemplate=array('isSystemCall'=>FALSE,'orderBy'=>'rand()','isAsc'=>FALSE,'limit'=>10,'offset'=>0,'width'=>600,'height'=>400,'autoShuffle'=>FALSE,'presentEntry'=>TRUE);
		$settings=array_merge($settingsTemplate,$arr['settings']);
		$debugArr=array('arr'=>$arr,'settings'=>$settings);
		$entrySelector=$arr['selector'];
		$entrySelector['Type']='%image%';
		$arr['wrapperStyle']=array('cursor'=>'pointer','position'=>'absolute','top'=>0,'left'=>0,'width'=>$settings['width'],'height'=>$settings['height'],'z-index'=>2);
		$arr['wrapperStyle']['background-color']=(isset($arr['wrapperSettings']['style']['background-color']))?$arr['wrapperSettings']['style']['background-color']:'#fff';
		$arr['style']=array('float'=>'none','display'=>'block','margin'=>'0 auto');
		$entry=array('rowCount'=>0,'rowIndex'=>0);
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entrySelector,$settings['isSystemCall'],'Read',$settings['orderBy'],$settings['isAsc'],$settings['limit'],$settings['offset']) as $entry){
			$arr['selector']=$entry;
			$imgFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
			if ($settings['autoShuffle']){
				$arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->scaleImageToCover($arr,$imgFile,$settings);
			} else {
				$arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->scaleImageToContain($arr,$imgFile,$settings);
			}
			$arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
			if ($arr['wrapperStyle']['z-index']===2){$arr['wrapperStyle']['z-index']=1;}
		}
		if (!empty($entry['rowCount'])){
			$arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'style'=>array('clear'=>'both','position'=>'relative','width'=>$settings['width'],'height'=>$settings['height'])));
			// button div
			$btnHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','element-content'=>'&#10094;&#10094;','keep-element-content'=>TRUE,'id'=>__FUNCTION__.'-'.$arr['containerId'].'-prev','class'=>'js-button','style'=>array('clear'=>'left','min-width'=>'8em','padding'=>'3px 0')));
			$btnHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','element-content'=>'&#10095;&#10095;','keep-element-content'=>TRUE,'id'=>__FUNCTION__.'-'.$arr['containerId'].'-next','class'=>'js-button','style'=>array('float'=>'right','min-width'=>'8em','padding'=>'3px 0')));
			$btnWrapper=array('tag'=>'div','element-content'=>$btnHtml,'keep-element-content'=>TRUE,'id'=>'btns-'.$arr['containerId'].'-wrapper','style'=>array('clear'=>'both','position'=>'relative','width'=>$settings['width'],'margin'=>'10px 0'));
			if ($settings['autoShuffle']){$btnWrapper['style']['display']='none';}
			$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnWrapper);
		}
		if (!empty($settings['presentEntry'])){
			$entryPlaceholder=array('tag'=>'div','element-content'=>'...','id'=>'present-'.$arr['containerId'].'-entry','style'=>array('clear'=>'both','position'=>'relative','width'=>$settings['width'],'margin'=>'0'));	
			$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($entryPlaceholder);
		}
		if ($isDebugging){
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		
		return $arr;
	}
	
}
?>