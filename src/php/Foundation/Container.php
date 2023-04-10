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

class Container{

	private $arr;
	
	public function __construct($arr){
		$this->arr=$arr;
	}

	public function init($arr){
		$this->arr=$arr;
		return $this->arr;
	}
	
	public function jsCall($arr){
		$jsAnswer=array();
		if (isset($_POST['function'])){
			if (strcmp($_POST['function'],'container')===0){
				$jsAnswer['html']=$this->container(FALSE,'',array(),array(),array(),$_POST['container-id'],TRUE);
			} else if (strcmp($_POST['function'],'containerMonitor')===0){
				$jsAnswer['arr']=array('isUp2date'=>$this->containerMonitor($_POST['container-id']),'container-id'=>$_POST['container-id']);
			} else if (strcmp($_POST['function'],'loadEntry')===0){
				$selector=array('Source'=>$_POST['Source'],'EntryId'=>$_POST['EntryId']);
				$jsAnswer['html']=$this->arr['SourcePot\Datapool\Foundation\Container']->container($selector['EntryId'],'selectedView',$selector,array());
			} else if (strcmp($_POST['function'],'setCanvasElementPosition')===0){
				$jsAnswer['arr']=$this->arr['SourcePot\Datapool\Foundation\DataExplorer']->setCanvasElementPosition($_POST['arr']);
			} else {
				
			}
		} else if (isset($_POST['loadImage'])){
			$jsAnswer=$this->arr['SourcePot\Datapool\Tools\MediaTools']->loadImage($_POST['loadImage']);
		} else {
			//$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($_POST,hrtime(TRUE).'-'.__FUNCTION__);
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
		//$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($_SESSION['container store'][$containerId]);
		$return=$this->$function($_SESSION['container store'][$containerId]);
		if (empty($return)){return '';}
		$html.=$return['html'];
		if (isset($return['wrapperSettings'])){
			$wrapperSettings=array_merge($wrapperSettings,$return['wrapperSettings']);
		}
		if (isset($return['settings'])){$_SESSION['container store'][$containerId]=array_merge($_SESSION['container store'][$containerId],$return['settings']);}
		$reloadBtnStyle=array('position'=>'absolute','top'=>'0','right'=>'0','margin'=>'0','padding'=>'3px','border'=>'none','background-color'=>'#ccc');
		$reloadBtnArr=array('tag'=>'button','type'=>'submit','element-content'=>'&orarr;','class'=>'reload-btn','container-id'=>'btn-'.$containerId,'style'=>$reloadBtnStyle,'key'=>'reloadBtnArr','callingClass'=>__CLASS__,'callingFunction'=>$containerId,'keep-element-content'=>TRUE);
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($reloadBtnArr);
		// add wrappers
		$wrapperDiv=$wrapperSettings;
		$wrapperDiv['tag']='article';
		$wrapperDiv['container-id']=$containerId;
		$wrapperDiv['element-content']=$html;
		$wrapperDiv['keep-element-content']=TRUE;
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($wrapperDiv);
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
			//$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file(array('current'=>$_SESSION['container monitor'][$containerId],'new hash'=>$newHash,'isUpToDate'=>$isUpToDate,'last refresh'=>time()-$_SESSION['container monitor'][$containerId]['refreshed']),__FUNCTION__.'-'.$_SESSION['container monitor'][$containerId]['selector']['Source']);
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
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($registerSelector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$selectExprArr,TRUE) as $row){
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
			$arr=$this->arr[$arr['settings']['classWithNamespace']]->$method($arr);
		} else {
			$arr['html'].='Generic container called with with invalid method setting. Check container settings "classWithNamespace" and/or "method".';	
		}
		return $arr;
	}

	private function selectedView($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		if (empty($arr['selector']['Source'])){
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'P','element-content'=>'Nothing to show here. Entry-source missing.'));
		} else {
			// get setting
			$setting=array('Show entry list'=>TRUE,
						   'Show entry editor'=>TRUE,
						   'Show comments editor'=>TRUE,
						   'Show tools'=>TRUE,
						   'Show entry viewer'=>TRUE,
						   'Show map'=>TRUE,
						   'Show userAbstract'=>TRUE,
						   'Show getPreview'=>TRUE,
						   'Key tags'=>array(),
						   'wrapperSettings'=>array('style'=>array('max-width'=>'none','width'=>'100%','padding'=>'0','margin'=>'0','border-left'=>'0','border-right'=>'0'))
						   );
			$arr['setting']=$this->arr['SourcePot\Datapool\AdminApps\Settings']->getSetting(__CLASS__,__FUNCTION__,$setting,$arr['selector']['Source'],TRUE);
			// compile html
			if (empty($arr['selector']['EntryId'])){
				if (!empty($setting['Show entry list'])){$arr=$this->entryList($arr);}
			} else {
				$arr['selector']=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
				if (empty($arr['selector'])){
					$arr['html']=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'P','element-content'=>'Nothing to show here. Entry does not exist.'));
				} else {
					if ($this->arr['SourcePot\Datapool\Tools\CSVtools']->isCSV($arr['selector'])){$arr=$this->arr['SourcePot\Datapool\Tools\CSVtools']->csvEditor($arr);}
					$arr=$this->arr['SourcePot\Datapool\Tools\MediaTools']->presentEntry($arr);
					if (!empty($arr['settingNeedsUpdate'])){
						$this->arr['SourcePot\Datapool\AdminApps\Settings']->setSetting(__CLASS__,__FUNCTION__,$arr['setting'],$arr['selector']['Source'],TRUE);
					}
					if (!empty($arr['setting']['Show comments editor'])){$arr=$this->comments($arr);}
					if (empty($arr['setting']['Show tools'])){$arr=$this->tools($arr);}
					if (!empty($arr['setting']['Show map'])){$arr=$this->arr['SourcePot\Datapool\Tools\GeoTools']->getMapHtml($arr,FALSE);}
				}
			}
			$arr=array_merge($arr,$arr['setting']);
			unset($arr['setting']);
		}
		return $arr;
	}

	public function entryEditor($arr,$isDebugging=FALSE){
		$arr['selector']=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
		if (empty($arr['selector'])){return $arr;}
		if (!isset($_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']])){$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$arr['settings'];}
		$settings=$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']];
		$debugArr=array('arr in'=>$arr,'settings in'=>$settings);
		if (!isset($arr['html'])){$arr['html']='';}
		$definition=$this->arr['SourcePot\Datapool\Foundation\Definitions']->getDefinition($arr['selector']);
		$tableInfo=$this->arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($arr['selector']['Source']);
		$flatDefinition=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($definition);
		$entryCanWrite=!empty($this->arr['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],'Write'));
		if (empty($arr['selector'])){
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>'No entry found with the selector provided'));
		} else {
			$S=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
			$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing($arr['callingClass'],$arr['callingFunction']);
			$debugArr['formData']=$formData;
			if (!empty($formData['cmd'])){
				if (isset($formData['cmd']['Upload'])){
					$fileArr=current(current($formData['files']));
					$entry=$this->arr['SourcePot\Datapool\Foundation\Filespace']->file2entries($fileArr,$arr['selector']);
				} else if (isset($formData['cmd']['stepIn'])){
					if (empty($settings['selectorKey'])){$selectorKeyComps=array();} else {$selectorKeyComps=explode($S,$settings['selectorKey']);}
					$selectorKeyComps[]=$formData['cmd']['stepIn'];
					$settings['selectorKey']=implode($S,$selectorKeyComps);
				} else if (isset($formData['cmd']['setSelectorKey'])){
					$settings['selectorKey']=$formData['cmd']['setSelectorKey'];
				} else if (isset($formData['cmd']['deleteKey'])){
					$arr['selector']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arrDeleteKeyByFlatKey($arr['selector'],$formData['cmd']['deleteKey']);
					$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
				} else if (isset($formData['cmd']['addValue'])){
					$flatKey=$formData['cmd']['addValue'].$S.$formData['val']['newKey'];
					$arr['selector']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arrUpdateKeyByFlatKey($arr['selector'],$flatKey,'Enter new value here ...');
					$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
				} else if (isset($formData['cmd']['addArr'])){
					$flatKey=$formData['cmd']['addArr'].$S.$formData['val']['newKey'].$S.'...';
					$arr['selector']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arrUpdateKeyByFlatKey($arr['selector'],$flatKey,'to be deleted');
					$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
				} else if (isset($formData['cmd']['save']) || isset($formData['cmd']['reloadBtnArr'])){
					$arr['selector']=array_replace_recursive($arr['selector'],$formData['val']);
					$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
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
				$element=array('tag'=>'button','element-content'=>$key.' &rarr;','key'=>array('setSelectorKey'),'value'=>$btnArrKey,'keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
				$element['style']=array('font-size'=>'0.9em','border'=>'none','border-bottom'=>'1px solid #aaa');
				$navHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element).$navHtml;
			}
			// create table matrix
			$btnsHtml='';
			if ($entryCanWrite){
				$btnsHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'key'=>array('save'),'value'=>'save','title'=>'Save','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
			}
			$btnArr=$arr['selector'];
			$btnArr['cmd']='download';
			$btnsHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
			$matrix=array('Nav'=>array('value'=>$navHtml,'cmd'=>$btnsHtml));
			if (!isset($settings['selectorKey'])){$settings['selectorKey']='';}
			$flatEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($arr['selector']);
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
					$element['key']=array('stepIn');
					$element['value']=$subFlatKeyComps[0];
					$valueHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
				} else {
					// non-array value
					$element=$this->arr['SourcePot\Datapool\Foundation\Definitions']->selectorKey2element($arr['selector'],$flatKey,$value,$arr['callingClass'],$arr['callingFunction']);
					if (is_array($element)){
						$element['excontainer']=TRUE;
						$element['callingClass']=$arr['callingClass'];
						$element['callingFunction']=$arr['callingFunction'];
						$valueHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
					} else {
						$valueHtml=$element;
					}
				}
				$cmdHtml='';
				if (count($flatKeyComps)>1 && $level>0 && $entryCanWrite){
					$element=array('tag'=>'button','element-content'=>'&xcup;','key'=>array('deleteKey'),'value'=>$flatKey,'hasCover'=>TRUE,'title'=>'Delete key','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
					$cmdHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
				}
				$label=array_shift($subFlatKeyComps);
				$matrix[$label]=array('value'=>$valueHtml,'cmd'=>$cmdHtml);
			}
			if ($level>0 && $entryCanWrite){
				$flatKey=$settings['selectorKey'];
				$element=array('tag'=>'input','type'=>'text','key'=>array('newKey'),'value'=>'','style'=>array('color'=>'#fff','background-color'=>'#1b7e2b'),'excontainer'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
				$valueHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
				$element=array('tag'=>'button','element-content'=>'...','key'=>array('addValue'),'value'=>$flatKey,'title'=>'Add value','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
				$cmdHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
				$element=array('tag'=>'button','element-content'=>'{...}','key'=>array('addArr'),'value'=>$flatKey,'title'=>'Add array','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
				$cmdHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
				$matrix['<i>Add</i>']=array('value'=>$valueHtml,'cmd'=>$cmdHtml);
			}
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$arr['selector']['Name']));
			if ($level==0){
				$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryControls($arr);
			}
		}
		if ($isDebugging){
			$debugArr['arr out']=$arr;
			$debugArr['settings out']=$settings;
			$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $arr;		
	}
	
	private function entryList($arr,$isDebugging=FALSE){
		if (!isset($arr['html'])){$arr['html']='';}
		if (!isset($_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']])){$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$arr['settings'];}
		$settings=$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']];
		$debugArr=array('arr'=>$arr,'settings in'=>$settings);
		$S=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		// get settings
		if (!isset($settings['columns'])){
			$settings['columns']=array(array('Column'=>'Name','Filter'=>''),array('Column'=>'Date','Filter'=>''),array('Column'=>'preview','Filter'=>''));
		}
		if (!isset($settings['isSystemCall'])){$settings['isSystemCall']=FALSE;}
		if (!isset($settings['orderBy'])){$settings['orderBy']='Date';}
		if (!isset($settings['isAsc'])){$settings['isAsc']=FALSE;}
		if (!isset($settings['limit'])){$settings['limit']=5;}
		if (!isset($settings['offset'])){$settings['offset']=FALSE;}
		// form processing
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing($arr['callingClass'],$arr['callingFunction']);
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
				$settings['orderBy']=$formData['cmd']['desc'];
				$settings['isAsc']=FALSE;
			} else if (isset($formData['cmd']['asc'])){
				$settings['orderBy']=$formData['cmd']['asc'];
				$settings['isAsc']=TRUE;
			}
			$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$settings;
		}
		// add column button
		$element=array('tag'=>'button','element-content'=>'➕','key'=>array('addColumn'),'value'=>'add','title'=>'add column','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
		$addColoumnBtn=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
		$settings['columns'][]=array('Column'=>$addColoumnBtn,'Filter'=>FALSE);
		// get selector
		$filterSkipped=FALSE;
		$selector=$this->selectorFromSetting($arr['selector'],$settings,FALSE);
		$rowCount=$this->arr['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,$settings['isSystemCall']);
		if (empty($rowCount)){
			$selector=$this->selectorFromSetting($arr['selector'],$settings,TRUE);
			$rowCount=$this->arr['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,$settings['isSystemCall']);
			$filterSkipped=TRUE;
		}
		if ($rowCount<=$settings['offset']){$settings['offset']=0;}
		if (!empty($rowCount)){
			// create html
			$filterKey='Filter';
			$matrix=array();
			$columnOptions=array();
			foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$settings['isSystemCall'],'Read',$settings['orderBy'],$settings['isAsc'],$settings['limit'],$settings['offset']) as $entry){
				$rowIndex=$entry['rowIndex']+intval($settings['offset'])+1;
				if ($entry['isSkipRow']){
					$rowCount--;
					continue;
				}
				//$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($entry,__FUNCTION__.'-'.$entry['EntryId']);
				$flatEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
				// setting up
				if (empty($columnOptions)){
					$columnOptions['preview']='&#10004; File preview';
					foreach($flatEntry as $flatColumnKey=>$value){
						$columnOptions[$flatColumnKey]=$this->arr['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($flatColumnKey);
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
						$btnArr=$entry;
						$btnArr['cmd']='select';
						$matrix[$rowIndex][$columnIndex]=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
						$btnArr['cmd']='download';
						$matrix[$rowIndex][$columnIndex].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
						$btnArr['cmd']='delete';
						$matrix[$rowIndex][$columnIndex].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
					} else {
						$matrix[$filterKey][$columnIndex]='';
						// filter text field
						if ($filterSkipped && !empty($cntrArr['Filter'])){$style=array('background-color'=>'#800');} else {$style=array();}
						$filterTextField=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'input','type'=>'text','style'=>$style,'value'=>$cntrArr['Filter'],'key'=>array('columns',$columnIndex,'Filter'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
						// order by buttons
						if (strcmp(strval($settings['orderBy']),$column)===0){$styleBtnSetting=array('color'=>'#fff','background-color'=>'#a00');} else {$styleBtnSetting=array();}
						if ($settings['isAsc']){$style=$styleBtnSetting;} else {$style=array();}
						$element=array('tag'=>'button','element-content'=>'&#9650;','key'=>array('asc'),'value'=>$column,'style'=>array('padding'=>'0','line-height'=>'1em','font-size'=>'1.5em'),'title'=>'order ascending','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'style'=>$style,'callingFunction'=>$arr['callingFunction']);
						$matrix[$filterKey][$columnIndex].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
						$matrix[$filterKey][$columnIndex].=$filterTextField;
						if (!$settings['isAsc']){$style=$styleBtnSetting;} else {$style=array();}
						$element=array('tag'=>'button','element-content'=>'&#9660;','key'=>array('desc'),'value'=>$column,'style'=>array('padding'=>'0','line-height'=>'1em','font-size'=>'1.5em'),'title'=>'order descending','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'style'=>$style,'callingFunction'=>$arr['callingFunction']);
						$matrix[$filterKey][$columnIndex].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
						// remove column button
						$matrix['Columns'][$columnIndex]=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$columnOptions,'value'=>$cntrArr['Column'],'keep-element-content'=>TRUE,'key'=>array('columns',$columnIndex,'Column'),'style'=>array(),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
						if ($columnIndex>0){
							$element=array('tag'=>'button','element-content'=>'&xcup;','keep-element-content'=>TRUE,'key'=>array('removeColumn',$columnIndex),'value'=>'remove','hasCover'=>TRUE,'sytle'=>array('font-size'=>'1.5em'),'title'=>'remove column','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
							$matrix['Columns'][$columnIndex].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
						}
						// table rows
						if (strcmp($cntrArr['Column'],'preview')===0){
							$mediaArr=$this->arr['SourcePot\Datapool\Tools\MediaTools']->getPreview(array('selector'=>$entry,'style'=>array('width'=>'100%','max-height'=>100,'max-height'=>100)));
							$matrix[$rowIndex][$columnIndex]=$mediaArr['html'];
						} else {
							$matrix[$rowIndex][$columnIndex]='?';
						}
						foreach($flatEntry as $flatColumnKey=>$value){
							if (strcmp($flatColumnKey,$cntrArr['Column'])!==0){continue;}
							$matrix[$rowIndex][$columnIndex]=$value;
						}
					}
				} // end of loop through columns
			} // end of loop through entries
			foreach($settings['columns'] as $columnIndex=>$cntrArr){
				if ($cntrArr['Filter']===FALSE){
					$matrix['Limit<br/>offset'][$columnIndex]='';
				} else if ($columnIndex===0){
					$max=$rowCount-intval($settings['limit']);
					if ($max<0){$max=0;}
					$otions=array(5=>'5',10=>'10',25=>'25',50=>'50',100=>'100',200=>'200');
					$matrix['Limit<br/>offset'][$columnIndex]=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$otions,'key'=>array('limit'),'value'=>$settings['limit'],'title'=>'rows to show','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
					if ($rowCount>intval($settings['limit'])){
						$element=array('tag'=>'input','type'=>'range','min'=>'0','max'=>$max,'key'=>array('offset'),'value'=>strval(intval($settings['offset'])),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
						$matrix['Limit<br/>offset'][$columnIndex].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);	
					}
				} else {
					$matrix['Limit<br/>offset'][$columnIndex]='';
				}
			}
			$caption=$arr['containerKey'];
			$caption.=' ('.$rowCount.')';
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		if ($isDebugging){
			$debugArr['arr out']=$arr;
			$debugArr['settings out']=$settings;
			$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $arr;		
	}

	private function selectorFromSetting($selector,$settings,$resetFilter=FALSE){
		// This function is a suporting function for entryList() only.
		// It has no further use.
		$S=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
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
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing($arr['callingClass'],$arr['callingFunction']);
		if (isset($formData['cmd']['Add comment'])){
			//$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($formData);
			$timestamp=$formData['cmd']['Add comment'];
			$arr['selector']['Content']['Comments'][$timestamp]=array('Comment'=>$formData['val']['comment'],'Author'=>$_SESSION['currentUser']['EntryId']);
			$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
		}
		if (isset($arr['selector']['Content']['Comments'])){$Comments=$arr['selector']['Content']['Comments'];} else {$Comments=array();}
		$commentsHtml='';
		foreach($Comments as $creationTimestamp=>$comment){
			$footer=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
			$footer.=' '.$this->arr['SourcePot\Datapool\Foundation\User']->userAbtract($comment['Author'],2);
			$commentHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>$comment['Comment'],'class'=>'comment'));
			$commentHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>$footer,'class'=>'comment-footer'));
			$commentsHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$commentHtml,'keep-element-content'=>TRUE,'class'=>'comment'));
		}
		$targetId=$arr['callingFunction'].'-textarea';
		$newComment='';
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],'Write')){
			$newComment.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'h3','element-content'=>'New comment','style'=>array('float'=>'left','clear'=>'both','margin'=>'30px 0 5px 5px')));
			$newComment.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'textarea','element-content'=>'...','key'=>array('comment'),'id'=>$targetId,'style'=>array('float'=>'left','clear'=>'both','margin'=>'5px','font-size'=>'1.5em'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
			$newComment.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Emojis for '.$arr['callingFunction'],'generic',$arr['selector'],array('method'=>'emojis','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder','target'=>$targetId));
			$newComment.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'button','element-content'=>'Add','key'=>array('Add comment'),'value'=>time(),'style'=>array('float'=>'left','clear'=>'both','margin'=>'5px'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
			$newComment=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$newComment,'icon'=>'&#9998;','style'=>array('clear'=>'both')));
		}
		$arr['html'].=$commentsHtml.$newComment;
		return $arr;
	}
	
	public function tools($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$html='';
		$btn=$arr['selector'];
		$btn['style']=array('margin'=>'30px 5px 0 0');
		$btn['cmd']='download';
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btn);
		$btn['cmd']='delete';
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btn);
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'...'));
		return $arr;
	}

}
?>