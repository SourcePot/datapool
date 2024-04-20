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
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    public function init(array $oc)
    {
        $this->oc=$oc;
    }
    
    public function jsCall(array $arr):array
    {
        $jsAnswer=array();
        if (isset($_POST['function'])){
            if (strcmp($_POST['function'],'container')===0){
                $jsAnswer['html']=$this->container(FALSE,'',array(),array(),array(),$_POST['container-id'],TRUE);
            } else if (strcmp($_POST['function'],'containerMonitor')===0){
                $jsAnswer['arr']=array('isUp2date'=>$this->containerMonitor($_POST['container-id']),'container-id'=>$_POST['container-id']);
            } else if (strcmp($_POST['function'],'loadEntry')===0){
                $jsAnswer['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->loadEntry($_POST);
            } else if (strcmp($_POST['function'],'setCanvasElementStyle')===0){
                $jsAnswer['arr']=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->setCanvasElementStyle($_POST);
            } else if (strcmp($_POST['function'],'entryById')===0){
                $jsAnswer['arr']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($_POST);
            } else if (strcmp($_POST['function'],'getPlotData')===0){
                $jsAnswer=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->getPlotData($_POST);
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
    
    public function container($key=FALSE,$function='',$selector=array(),$settings=array(),$wrapperSettings=array(),$containerId=FALSE,$isJScall=FALSE):string
    {
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
        if (empty($return['html'])){return '';}
        $html.=$return['html'];
        if (isset($return['wrapperSettings'])){
            $wrapperSettings=array_merge($wrapperSettings,$return['wrapperSettings']);
        }
        if (isset($return['settings'])){$_SESSION['container store'][$containerId]['settings']=array_replace_recursive($_SESSION['container store'][$containerId]['settings'],$return['settings']);}
        $reloadBtnStyle=array('position'=>'absolute','top'=>'0','right'=>'0','margin'=>'0','padding'=>'3px','border'=>'none','background-color'=>'#ccc');
        if (!empty($wrapperSettings['hideReloadBtn'])){$reloadBtnStyle['display']='none';}
        $reloadBtnArr=array('tag'=>'button','type'=>'submit','element-content'=>'&orarr;','class'=>'reload-btn','container-id'=>'btn-'.$containerId,'style'=>$reloadBtnStyle,'key'=>array('reloadBtnArr'),'callingClass'=>__CLASS__,'callingFunction'=>$containerId,'keep-element-content'=>TRUE);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($reloadBtnArr);
        // add wrappers
        if (isset($wrapperSettings['html'])){
            $htmlSuffix=$wrapperSettings['html'];
            unset($wrapperSettings['html']);
        } else {
            $htmlSuffix='';
        }
        $wrapperDiv=$wrapperSettings;
        $wrapperDiv['tag']='article';
        $wrapperDiv['container-id']=$containerId;
        $wrapperDiv['element-content']=$html.$htmlSuffix;
        $wrapperDiv['keep-element-content']=TRUE;
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($wrapperDiv);
        return $html;
    }

    private function containerMonitor($containerId,$registerSelector=FALSE):bool
    {
        if ($registerSelector===FALSE){
            // check if data selected by registered selector for the selected container has changed
            if (!isset($_SESSION['container monitor'][$containerId])){return TRUE;}
            $isUpToDate=TRUE;
            if (isset($_SESSION['container monitor'][$containerId]['selector']['refreshInterval'])){
                $isUpToDate=((time()-$_SESSION['container monitor'][$containerId]['refreshed'])<$_SESSION['container monitor'][$containerId]['selector']['refreshInterval']);
            }
            $newHash=$this->selector2hash($_SESSION['container monitor'][$containerId]['selector']);
            if (strcmp($_SESSION['container monitor'][$containerId]['hash'],$newHash)===0 && $isUpToDate){
                // no change detected
                return TRUE;
            } else {
                // change detected
                $_SESSION['container monitor'][$containerId]['containerId']=$containerId;
                $_SESSION['container monitor'][$containerId]['hash']=$newHash;
                $_SESSION['container monitor'][$containerId]['refreshed']=time();
                return !empty($_SESSION['container monitor'][$containerId]['selector']['disableAutoRefresh']);
            }
        } else {
            // register the the selector
            $_SESSION['container monitor'][$containerId]=array('hash'=>$this->selector2hash($registerSelector),'selector'=>$registerSelector,'containerId'=>$containerId,'refreshed'=>time());
        }
        return TRUE;
    }
    
    private function selector2hash($registerSelector):string
    {
        if (!empty($GLOBALS['dbInfo'][$registerSelector['Source']]['Name']['skipContainerMonitor'])){return 'SKIP';}
        //
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

    public function generic(array $arr):array
    {
        // This method provides a generic container widget
        if (!isset($arr['html'])){$arr['html']='';}
        if (empty($arr['settings']['method']) || empty($arr['settings']['classWithNamespace'])){
            $arr['html'].='Generic container called without required settings "method" or "classWithNamespace".';
        } else if (method_exists($arr['settings']['classWithNamespace'],$arr['settings']['method'])){
            $method=$arr['settings']['method'];
            if (isset($this->oc[$arr['settings']['classWithNamespace']])){
                $arr=$this->oc[$arr['settings']['classWithNamespace']]->$method($arr);
            } else {
                $arr['html'].='Method '.__FUNCTION__.' failed to call '.$arr['settings']['classWithNamespace'].'::'.$method.'(arr). Maybe objectList.csv is not up-to-date.';
            }
        } else {
            $msg='Generic container called with with invalid method setting. Check container settings "classWithNamespace"='.$arr['settings']['classWithNamespace'].' and/or "method"='.$arr['settings']['method'].'.';    
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->traceHtml($msg);
        }
        return $arr;
    }
    
    public function mdContainer(array $arr)
    {
        $entry=$arr['selector'];
        $entry['Type']='md '.$_SESSION['page state']['lngCode'];
        if (empty($entry['Group'])){$entry['Group']=__CLASS__;}
        if (empty($entry['Folder'])){$entry['Folder']=$_SESSION['page state']['lngCode'];}
        if (empty($entry['Name'])){$entry['Name']='Description';}
        $entry['Params']['File']=array('UploaderId'=>'SYSTEM','UploaderName'=>'System','Name'=>$arr['containerKey'].'.md','Date (created)'=>time(),'MIME-Type'=>'text/plain','Extension'=>'md');
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name','Type'),'0','',FALSE);
        $fileName=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
        if (!is_file($fileName)){
            $fileContent="[//]: # (This a Markdown document!)\n\n";
            $entry['Params']['File']['Uploaded']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','');
            file_put_contents($fileName,$fileContent);
        }
        $arr=array('settings'=>array('style'=>array('width'=>'100vw','max-width'=>'100%')));
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE,TRUE,TRUE,'');
        $arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
        return $arr;
    }
    
    public function entryEditor(array $arr,bool $isDebugging=FALSE):array
    {
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
                    $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($fileArr,$arr['selector']);
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
                if (mb_strpos($flatKey,$settings['selectorKey'])!==0){continue;}
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
    
    private function entryList(array $arr,bool $isDebugging=FALSE):array
    {
        $S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
        $SettingsTemplate=array('columns'=>array(array('Column'=>'Name','Filter'=>'')),
                                'isSystemCall'=>FALSE,
                                'orderBy'=>'Name',
                                'isAsc'=>TRUE,
                                'limit'=>10,
                                'offset'=>FALSE
                                );
        $arr['html']=(isset($arr['html']))?$arr['html']:'';
        $_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=(isset($_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]))?$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]:$arr['settings'];
        // get settings
        $debugArr=array('arr'=>$arr,'settings in'=>$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]);
        $settings=array_replace_recursive($SettingsTemplate,$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]);
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
        $element=array('tag'=>'button','element-content'=>'➕','key'=>array('addColumn'),'value'=>'add','title'=>'Add column','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
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
        $csvMatrix=array();
        if ($rowCount<=$settings['offset']){$settings['offset']=0;}
        if (!empty($rowCount)){
            // create html
            $filterKey='Filter';
            $matrix=array();
            $columnOptions=array();
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$settings['isSystemCall'],'Read',$settings['orderBy'],$settings['isAsc'],$settings['limit'],$settings['offset'],array(),TRUE,FALSE) as $entry){
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
                        $cntrArr=$arr;
                        $cntrArr['callingFunction']=__FUNCTION__;
                        $cntrArr['selector']=$entry;
                        $matrix[$rowIndex][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryControls($cntrArr);
                    } else {
                        $matrix[$filterKey][$columnIndex]='';
                        // filter text field
                        if ($filterSkipped && !empty($cntrArr['Filter'])){$style=array('color'=>'#fff','background-color'=>'#a00');} else {$style=array();}
                        $filterTextField=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'text','style'=>$style,'value'=>$cntrArr['Filter'],'key'=>array('columns',$columnIndex,'Filter'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
                        // "order by"-buttons
                        if (strcmp(strval($settings['orderBy']),$column)===0){$styleBtnSetting=array('color'=>'#fff','background-color'=>'#a00');} else {$styleBtnSetting=array();}
                        if ($settings['isAsc']){$style=$styleBtnSetting;} else {$style=array();}
                        $element=array('tag'=>'button','element-content'=>'&#9650;','key'=>array('asc',$column),'value'=>$columnIndex,'style'=>array('padding'=>'0','line-height'=>'1em','font-size'=>'1.5em'),'title'=>'Order ascending','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'style'=>$style,'callingFunction'=>$arr['callingFunction']);
                        $matrix[$filterKey][$columnIndex].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                        $matrix[$filterKey][$columnIndex].=$filterTextField;
                        if (!$settings['isAsc']){$style=$styleBtnSetting;} else {$style=array();}
                        $element=array('tag'=>'button','element-content'=>'&#9660;','key'=>array('desc',$column),'value'=>$columnIndex,'style'=>array('padding'=>'0','line-height'=>'1em','font-size'=>'1.5em'),'title'=>'Order descending','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'style'=>$style,'callingFunction'=>$arr['callingFunction']);
                        $matrix[$filterKey][$columnIndex].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                        // column selector
                        $matrix['Columns'][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$columnOptions,'value'=>$cntrArr['Column'],'keep-element-content'=>TRUE,'key'=>array('columns',$columnIndex,'Column'),'style'=>array(),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
                        // remove column button
                        if ($columnIndex>0){
                            $element=array('tag'=>'button','element-content'=>'&xcup;','keep-element-content'=>TRUE,'key'=>array('removeColumn',$columnIndex),'value'=>'remove','hasCover'=>TRUE,'style'=>array(),'title'=>'Remove column','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
                            $matrix['Columns'][$columnIndex].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                        }
                        $matrix['Columns'][$columnIndex]=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$matrix['Columns'][$columnIndex],'keep-element-content'=>TRUE,'style'=>array('width'=>'max-content')));
                        // table rows
                        if (strcmp($cntrArr['Column'],'preview')===0){
                            $mediaArr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview(array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'selector'=>$entry,'style'=>array('width'=>'100%','max-width'=>300,'max-height'=>250)));
                            $matrix[$rowIndex][$columnIndex]=$mediaArr['html'];
                        } else {
                            $matrix[$rowIndex][$columnIndex]='{Nothing here...}';
                        }
                        foreach($flatEntry as $flatColumnKey=>$value){
                            if (strcmp($flatColumnKey,$cntrArr['Column'])!==0){continue;}
                            $csvMatrix[$rowIndex][$cntrArr['Column']]=$value;
                            $matrix[$rowIndex][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->value2tabelCellContent($value,array());
                        }
                    }
                } // end of loop through columns
            } // end of loop through entries
            foreach($settings['columns'] as $columnIndex=>$cntrArr){
                if ($cntrArr['Filter']===FALSE){
                    $matrix['Limit, offset'][$columnIndex]='';
                } else if ($columnIndex===0){
                    $options=array(5=>'5',10=>'10',25=>'25',50=>'50',100=>'100',200=>'200');
                    $matrix['Limit, offset'][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$options,'key'=>array('limit'),'value'=>$settings['limit'],'title'=>'Rows to show','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
                    $matrix['Limit, offset'][$columnIndex].=$this->getOffsetSelector($arr,$settings,$rowCount);
                    $matrix['Limit, offset'][$columnIndex].=$this->oc['SourcePot\Datapool\Tools\CSVtools']->matrix2csvDownload($csvMatrix);
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
    
    private function getOffsetSelector(array $arr,array $settings,int $rowCount):string
    {
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

    private function selectorFromSetting(array $selector,array $settings,bool $resetFilter=FALSE):array
    {
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

    public function comments(array $arr):array
    {
        $arr['html']=(isset($arr['html']))?$arr['html']:'';
        if (empty($arr['selector'])){return $arr;}
        $arr['class']=(isset($arr['class']))?$arr['class']:'comment';
        $arr['style']=(isset($arr['style']))?$arr['style']:array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['Add comment'])){
            if (empty($formData['val']['comment'])){
                $this->oc['logger']->log('warning','Adding comment failed: comment was empty',$formData['val']);         
            } else {
            $arr['selector']['Source']=key($formData['cmd']['Add comment']);
                $arr['selector']['EntryId']=key($formData['cmd']['Add comment'][$arr['selector']['Source']]);
                $arr['selector']['timeStamp']=current($formData['cmd']['Add comment'][$arr['selector']['Source']]);
                $arr['selector']['Content']['Comments'][$arr['selector']['timeStamp']]=array('Comment'=>$formData['val']['comment'],'Author'=>$_SESSION['currentUser']['EntryId']);
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
            }
        }
        if (isset($arr['selector']['Content']['Comments'])){$Comments=$arr['selector']['Content']['Comments'];} else {$Comments=array();}
        $commentsHtml='';
        foreach($Comments as $creationTimestamp=>$comment){
            $footer=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.$creationTimestamp);
            $footer.=' '.$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($comment['Author'],3);
            $commentHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$comment['Comment'],'class'=>$arr['class']));
            $commentHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$footer,'keep-element-content'=>TRUE,'class'=>$arr['class'].'-footer'));
            $commentsHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$commentHtml,'keep-element-content'=>TRUE,'class'=>$arr['class']));
        }
        $targetId=(isset($arr['containerId']))?$arr['containerId'].'-textarea':$arr['callingFunction'].'-textarea';
        $newComment='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],'Write')){
            $newComment.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h3','element-content'=>'New comment','style'=>array('float'=>'left','clear'=>'both','margin'=>'0 5px')));
            $newComment.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'textarea','element-content'=>'','placeholder'=>'e.g. My new comment','key'=>array('comment'),'id'=>$targetId,'style'=>array('float'=>'left','clear'=>'both','margin'=>'5px','font-size'=>'1.5em'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
            $newComment.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Emojis for '.$targetId,'generic',$arr['selector'],array('method'=>'emojis','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder','target'=>$targetId));
            $newComment.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Add','key'=>array('Add comment',$arr['selector']['Source'],$arr['selector']['EntryId']),'value'=>time(),'style'=>array('float'=>'left','clear'=>'both','margin'=>'5px'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
            $appArr=array('html'=>$newComment,'icon'=>'&#9871;','style'=>$arr['style'],'title'=>'Add comment','style'=>$arr['style'],'class'=>$arr['class']);
            $newComment=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['html'].=$commentsHtml.$newComment;
        return $arr;
    }
    
    public function tools(array $arr):array
    {
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
    
    /**
    * This method adds an html-form to the parameter arr['html'].
    * Through the form a transmitter can be selected and the selected entry can be sent through thi transmitter.
    * @param array  $arr    Contains the entry selector of the entry to be sent and settings 
    * @return array
    */
    public function sendEntry(array $arr):array
    {
        if (!isset($arr['html'])){$arr['html']='';}
        // init settings
        $availableTransmitter=$this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Transmitter');
        $arr['settings']['Transmitter']=(isset($arr['settings']['Transmitter']))?$arr['settings']['Transmitter']:current($availableTransmitter);
        $arr['settings']['relevantFlatUserContentKey']=$this->oc[$arr['settings']['Transmitter']]->getRelevantFlatUserContentKey();
        // get entry
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
        // process form
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (!empty($formData['cmd'])){
            $arr['settings']=array_merge($arr['settings'],$formData['val']['settings']);
            $arr['settings']['relevantFlatUserContentKey']=$this->oc[$arr['settings']['Transmitter']]->getRelevantFlatUserContentKey();
            $arr['selector']['Content']=array_merge($arr['selector']['Content'],$formData['val']['selector']['Content']);
            if (isset($formData['cmd']['send'])){
                $this->oc[$arr['settings']['Transmitter']]->send($arr['settings']['Recipient'],$arr['selector']);
            }
        }
        $availableRecipients=$this->oc['SourcePot\Datapool\Foundation\User']->getUserOptions(array(),$arr['settings']['relevantFlatUserContentKey']);
        $arr['settings']['Recipient']=(isset($arr['settings']['Recipient']))?$arr['settings']['Recipient']:current($availableRecipients);
        // create form
        $matrix=array();
        $selectArr=array('callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'excontainer'=>FALSE);
        $selectArr['options']=$availableTransmitter;
        $selectArr['key']=array('settings','Transmitter');
        $selectArr['selected']=$arr['settings']['Transmitter'];
        $matrix['Transmitter']['Value']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $selectArr['options']=$availableRecipients;
        $selectArr['key']=array('settings','Recipient');
        $selectArr['selected']=$arr['settings']['Recipient'];
        $matrix['Recipient']['Value']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $selectArr['excontainer']=TRUE;
        $selectArr['tag']='input';
        $selectArr['type']='text';
        //$selectArr['value']=(isset($arr['selector']['Content']['Subject']))?$arr['selector']['Content']['Subject']:((isset($arr['selector']['Name']))?$arr['selector']['Name']:'...');
        $selectArr['value']=$selectArr['value']??$arr['selector']['Content']['Subject']??$arr['selector']['Name']??'...';
        $selectArr['key']=array('selector','Content','Subject');
        $matrix['Subject']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($selectArr);
        $selectArr['excontainer']=FALSE;
        $selectArr['type']='submit';
        $selectArr['value']='Send';
        $selectArr['hasCover']=TRUE;
        $selectArr['key']=array('send');
        $matrix['']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($selectArr);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE));
        return $arr;
    }    

    /**
    * This method add an html-string to the parameter $arr['html'] which contains an image presentation of entries selected by the parameter arr['selector'].
    * @param array  $arr    Contains the entry selector and settings 
    * @param array  $isDebugging    If TRUE the method will create a debug-file when called
    * @return array
    */
    public function getImageShuffle(array $arr,bool $isDebugging=FALSE):array
    {
        if (!isset($arr['html'])){$arr['html']='';}
        $selectBtnHtml='';
        $settingsTemplate=array('isSystemCall'=>FALSE,'orderBy'=>'rand()','isAsc'=>FALSE,'limit'=>4,'offset'=>0,'autoShuffle'=>FALSE,'presentEntry'=>TRUE,'getImageShuffle'=>$arr['selector']['Source']);
        $settingsTemplate['style']=array('width'=>600,'height'=>400,'cursor'=>'pointer','position'=>'absolute','top'=>0,'left'=>0,'z-index'=>2);
        $settings=array_replace_recursive($settingsTemplate,$arr['settings']);
        $arr['wrapper']=array('style'=>$settings['style']);
        $debugArr=array('arr'=>$arr,'settings'=>$settings);
        $entrySelector=$arr['selector'];
        $entrySelector['Params']='%image%';
        $arr['style']=array('float'=>'none','display'=>'block','margin'=>'0 auto');
        $entry=array('rowCount'=>0,'rowIndex'=>0);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entrySelector,$settings['isSystemCall'],'Read',$settings['orderBy'],$settings['isAsc'],$settings['limit'],$settings['offset']) as $entry){
            $arr['selector']=$entry;
            $imgFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
            if (!is_file($imgFile)){continue;}
            if ($settings['autoShuffle']){
                $arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->scaleImageToCover($arr,$imgFile,$settings['style']);
            } else {
                $arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->scaleImageToContain($arr,$imgFile,$settings['style']);
            }
            $arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
            if ($arr['wrapper']['style']['z-index']===2){$arr['wrapper']['style']['z-index']=1;}
            $selectBtnHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn(array('cmd'=>'select','selector'=>$entry,'style'=>array('font-size'=>'0.1rem','margin'=>'0 5px 5px 0','line-height'=>'0.3rem')));
        }
        if (!empty($entry['rowCount'])){
            $arr['html']=$selectBtnHtml.$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'style'=>array('clear'=>'both','position'=>'relative','width'=>$settings['style']['width'],'height'=>$settings['style']['height'])));
            // button div
            $btnHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','element-content'=>'&#10094;&#10094;','keep-element-content'=>TRUE,'id'=>__FUNCTION__.'-'.$arr['containerId'].'-prev','class'=>'js-button','style'=>array('clear'=>'left','min-width'=>'8em','padding'=>'3px 0')));
            $btnHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','element-content'=>'&#10095;&#10095;','keep-element-content'=>TRUE,'id'=>__FUNCTION__.'-'.$arr['containerId'].'-next','class'=>'js-button','style'=>array('float'=>'right','min-width'=>'8em','padding'=>'3px 0')));
            $btnWrapper=array('tag'=>'div','element-content'=>$btnHtml,'keep-element-content'=>TRUE,'id'=>'btns-'.$arr['containerId'].'-wrapper','style'=>array('clear'=>'both','position'=>'relative','width'=>$settings['style']['width'],'margin'=>'10px 0'));
            if ($settings['autoShuffle']){$btnWrapper['style']['display']='none';}
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnWrapper);
            if (!empty($settings['presentEntry'])){
                $entryPlaceholder=array('tag'=>'div','element-content'=>'...','id'=>'present-'.$arr['containerId'].'-entry','title'=>$settings['getImageShuffle'],'style'=>array('clear'=>'both','position'=>'relative','width'=>$settings['style']['width'],'margin'=>'0'));    
                $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($entryPlaceholder);
            }
        }   
        if ($isDebugging){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        $arr['wrapperSettings']['hideReloadBtn']=TRUE;
        return $arr;
    }
    
}
?>