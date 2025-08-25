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

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    /**
    * This method is called by client side javascript. 
    * @param array  $arr    Is provided by the Root-class, here typically an epmty array 
    * @return array Returns the $arr argument potentially with the added key['page html'] equals the json-encoded processing result. For security reasons only a suset of methods can be invoked. 
    */
    public function jsCall(array $arr):array
    {
        $jsAnswer=[];
        if (isset($_POST['function'])){
            if (strcmp($_POST['function'],'container')===0){
                $jsAnswer['html']=$this->container('','',[],[],[],$_POST['container-id'],TRUE);
            } else if (strcmp($_POST['function'],'containerMonitor')===0){
                $jsAnswer['arr']=['isUp2date'=>$this->containerMonitor($_POST['container-id']),'container-id'=>$_POST['container-id']];
            } else if (strcmp($_POST['function'],'loadEntry')===0){
                $jsAnswer['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->loadEntry($_POST);
            } else if (strcmp($_POST['function'],'setCanvasElementStyle')===0){
                $jsAnswer['arr']=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->setCanvasElementStyle($_POST);
            } else if (strcmp($_POST['function'],'entryById')===0){
                $jsAnswer['arr']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($_POST);
            } else {
                // unknown function
            }
        } else if (isset($_POST['loadImage'])){
            $jsAnswer=$this->oc['SourcePot\Datapool\Tools\MediaTools']->loadImage(['selector'=>$_POST['loadImage']]);
        } else if (!empty($_FILES)){
            $tagName=key($_FILES);
            // file upload widget request processing
            if (isset($_SESSION['name2classFunction'][$tagName])){
                $callingClass=$_SESSION['name2classFunction'][$tagName]['callingClass'];
                $callingFunction=$_SESSION['name2classFunction'][$tagName]['callingFunction'];
                $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($callingClass,$callingFunction);
                if (isset($formData['formProcessingClass']) && isset($formData['formProcessingFunction']) && isset($formData['formProcessingArg'])){
                    $formProcessingClass=$formData['formProcessingClass'];
                    $formProcessingFunction=$formData['formProcessingFunction'];
                    $formProcessingArg=$formData['formProcessingArg'];
                    if (method_exists($formProcessingClass,$formProcessingFunction)){
                        $this->oc[$formProcessingClass]->$formProcessingFunction($formProcessingArg);
                    } else {
                        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'formProcessingClass'=>$formProcessingClass,'formProcessingFunction'=>$formProcessingFunction];
                        $this->oc['logger']->log('error','Function "{class} &rarr; {function}()" returns error: formProcessing-method deos not exist "{formProcessingClass} &rarr; {formProcessingFunction}(...)".',$context);
                    }
                }
            }
        } else {
            // invalid request
        }
        $arr['page html']=json_encode($jsAnswer,JSON_INVALID_UTF8_IGNORE);
        return $arr;
    }

    public function container(string $key='',$function='',$selector=[],$settings=[],$wrapperSettings=[],$containerId=FALSE,$isJScall=FALSE):string
    {
        // This function provides a dynamic web-page container, it returns html-script.
        // The state of forms whithin the container is stored in  $_SESSION['Container'][$container-id]
        if ($isJScall){
            if (isset($_SESSION['container store'][$containerId]['callJScount'])){
                $_SESSION['container store'][$containerId]['callJScount']++;
            } else {
                $_SESSION['container store'][$containerId]['callJScount']=1;
            }
            $function=$_SESSION['container store'][$containerId]['function'];
            $containerId=$_SESSION['container store'][$containerId]['callingFunction'];
            $settings=$_SESSION['container store'][$containerId]['settings'];
            $wrapperSettings=$_SESSION['container store'][$containerId]['wrapperSettings'];
        } else {
            $containerId=md5($key);
            if (isset($_SESSION['container store'][$containerId]['callPageCount'])){
                $_SESSION['container store'][$containerId]['callPageCount']++;
            } else {
                $_SESSION['container store'][$containerId]['callPageCount']=1;
            }
            $_SESSION['container store'][$containerId]['callingClass']=__CLASS__;
            $_SESSION['container store'][$containerId]['callingFunction']=$containerId;
            $_SESSION['container store'][$containerId]['containerId']=$containerId;
            $_SESSION['container store'][$containerId]['function']=$function;
            $_SESSION['container store'][$containerId]['selector']=$selector;
            $_SESSION['container store'][$containerId]['containerKey']=$key;
            //if (!isset($_SESSION['container store'][$containerId]['settings'])){
                $_SESSION['container store'][$containerId]['settings']=$settings;
            //}
            $_SESSION['container store'][$containerId]['wrapperSettings']=$wrapperSettings;
            $this->containerMonitor($containerId,$selector);
        }
        $html='<div busy-id="busy-'.$containerId.'" class="container-busy"></div>';
        $return=$this->$function($_SESSION['container store'][$containerId]);
        if (empty($return['html'])){
            return '';
        }
        $html.=$return['html'];
        if (isset($return['wrapperSettings'])){
            $wrapperSettings=array_merge($wrapperSettings,$return['wrapperSettings']);
        }
        if (isset($return['settings'])){
            $_SESSION['container store'][$containerId]['settings']=array_replace_recursive($_SESSION['container store'][$containerId]['settings'],$return['settings']);
        }
        $reloadBtnStyle=['position'=>'absolute','top'=>'0','right'=>'0','margin'=>'0','padding'=>'0','border'=>'none','background'=>'none'];
        if (!empty($wrapperSettings['hideReloadBtn'])){
            $reloadBtnStyle['display']='none';
        }
        $reloadBtnArr=['tag'=>'button','type'=>'submit','element-content'=>'&orarr;','class'=>'reload-btn','container-id'=>'btn-'.$containerId,'style'=>$reloadBtnStyle,'key'=>['reloadBtnArr'],'callingClass'=>__CLASS__,'callingFunction'=>$containerId,'keep-element-content'=>TRUE];
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
            if (!empty($_SESSION['container monitor'][$containerId]['selector']['refreshInterval'])){
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
            $_SESSION['container monitor'][$containerId]=['hash'=>$this->selector2hash($registerSelector),'selector'=>$registerSelector,'containerId'=>$containerId,'refreshed'=>time()];
        }
        return TRUE;
    }
    
    private function selector2hash($registerSelector):string
    {
        if (empty($registerSelector['Source'])){return 'SKIP';}
        if (!empty($GLOBALS['dbInfo'][$registerSelector['Source']]['Name']['skipContainerMonitor'])){return 'SKIP';}
        //
        if (isset($registerSelector['isSystemCall'])){$isSystemCall=$registerSelector['isSystemCall'];} else {$isSystemCall=FALSE;}
        if (isset($registerSelector['rightType'])){$rightType=$registerSelector['rightType'];} else {$rightType='Read';}
        if (isset($registerSelector['orderBy'])){$orderBy=$registerSelector['orderBy'];} else {$orderBy=FALSE;}
        if (isset($registerSelector['isAsc'])){$isAsc=$registerSelector['isAsc'];} else {$isAsc=FALSE;}
        if (isset($registerSelector['limit'])){$limit=$registerSelector['limit'];} else {$limit=FALSE;}
        if (isset($registerSelector['offset'])){$offset=$registerSelector['offset'];} else {$offset=FALSE;}
        if (isset($registerSelector['selectExprArr'])){$selectExprArr=$registerSelector['selectExprArr'];} else {$selectExprArr=[];}
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
        if (empty($arr['selector']['EntryId']) && empty($arr['selector']['Name'])){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getGuideEntry($arr['selector']);
        } else {
            $entry=$arr['selector'];
        }
        unset($entry['EntryId']);
        unset($entry['Type']);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addType2entry($entry);
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name','Type'],'0','',TRUE);
        $entry['Expires']=\SourcePot\Datapool\Root::NULL_DATE;
        $entry['Owner']='SYSTEM';
        $fileName=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
        if (!is_file($fileName)){
            $typeComps=explode('|',$entry['Type']);
            $language=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->getValidLng($typeComps[1],FALSE);
            $selectorString=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->selector2string($arr['selector']);
            $entry['Params']['File']=['UploaderId'=>'SYSTEM','UploaderName'=>'System','Name'=>$arr['containerKey'].'.md','Date (created)'=>time(),'MIME-Type'=>'text/plain','Extension'=>'md'];
            $fileContent="[//]: # (This a Markdown document in ".$language."!)\n\n";
            $fileContent.="[//]: # (Use <img src=\"./assets/email.png\" style=\"float:none;\"> for the admin-email-address as image.)\n\n";
            $fileContent.='Sorry, there is no content available for <i>"'.$selectorString.'"</i> in <i>"'.$language.'"</i> yet...';
            if (!empty($arr['selector']['md'])){$fileContent=$arr['selector']['md'];}
            $entry['Params']['File']['Uploaded']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','');
            file_put_contents($fileName,$fileContent);
        }
        $entry['Read']='ALL_R';
        $entry['Write']='ALL_CONTENTADMIN_R';
        $arr=['settings'=>['style'=>['width'=>'100vw','max-width'=>'100%']]];
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,TRUE);
        $arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
        return $arr;
    }
    
    /**
    * This standard entry editor returns an array containing the editors html form under key 'html'
    * @param array arr Is an array containing the entry to be edited under key 'selector' and settings under key 'settings', e.g. arr['settings']['hideEntryControls']=TRUE, 
    *
    * @return array An array containing the editor html form under key 'html'
    */
    public function entryEditor(array $arr,bool $isDebugging=FALSE):array
    {
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
        if (empty($arr['selector'])){return $arr;}
        if (!isset($_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']])){$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$arr['settings'];}
        $settings=$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']];
        $debugArr=['arr in'=>$arr,'settings in'=>$settings];
        if (!isset($arr['html'])){$arr['html']='';}
        $tableInfo=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate($arr['selector']['Source']);
        $entryCanWrite=!empty($this->oc['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],'Write'));
        if (empty($arr['selector'])){
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>'No entry found with the selector provided']);
        } else {
            $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR;
            $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
            $debugArr['formData']=$formData;
            if (!empty($formData['cmd'])){
                if (isset($formData['cmd']['Upload'])){
                    $fileArr=current(current($formData['files']));
                    $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($fileArr,$arr['selector']);
                } else if (isset($formData['cmd']['stepIn'])){
                    if (empty($settings['selectorKey'])){$selectorKeyComps=[];} else {$selectorKeyComps=explode($S,$settings['selectorKey']);}
                    $selectorKeyComps[]=key($formData['cmd']['stepIn']);
                    $settings['selectorKey']=implode($S,$selectorKeyComps);
                } else if (isset($formData['cmd']['setSelectorKey'])){
                    $settings['selectorKey']=key($formData['cmd']['setSelectorKey']);
                } else if (isset($formData['cmd']['deleteKey'])){
                    $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arrDeleteKeyByFlatKey($arr['selector'],key($formData['cmd']['deleteKey']));
                    $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
                } else if (isset($formData['cmd']['addValue'])){
                    if (empty($formData['val']['newKey'])){
                        $this->oc['logger']->log('notice','Empty key is not allowed when adding a value',$arr);
                    } else {
                        $flatKey=$formData['cmd']['addValue'].$S.$formData['val']['newKey'];
                        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arrUpdateKeyByFlatKey($arr['selector'],$flatKey,'Enter new value here ...');
                        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
                    }
                } else if (isset($formData['cmd']['addArr'])){
                    if (empty($formData['val']['newKey'])){
                        $this->oc['logger']->log('notice','Empty key is not allowed when adding an array',$arr);
                    } else {
                        $flatKey=$formData['cmd']['addArr'].$S.$formData['val']['newKey'].$S.'...';
                        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arrUpdateKeyByFlatKey($arr['selector'],$flatKey,'to be deleted');
                        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
                    }
                } else if (isset($formData['cmd']['save']) || isset($formData['cmd']['reloadBtnArr'])){
                    $arr['selector']=array_replace_recursive($arr['selector'],$formData['val']);
                    $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
                } else {
                    
                }
                $_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$settings;
            }
            $navHtml='';
            if (empty($settings['selectorKey'])){$selectorKeyComps=[];} else {$selectorKeyComps=explode($S,$settings['selectorKey']);}
            $level=count($selectorKeyComps);
            while(count($selectorKeyComps)>0){
                $key=array_pop($selectorKeyComps);
                $btnArrKey=implode($S,$selectorKeyComps);
                $element=['tag'=>'button','element-content'=>$key.' &rarr;','key'=>['setSelectorKey',$btnArrKey],'keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
                $element['style']=['font-size'=>'0.9em','border'=>'none','border-bottom'=>'1px solid #aaa'];
                $navHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element).$navHtml;
            }
            // create table matrix
            $btnsHtml='';
            if ($entryCanWrite){
                $btnsHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'key'=>['save'],'value'=>'save','title'=>'Save','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']]);
            }
            $btnArr=$arr;
            $btnArr['cmd']='download';
            $btnsHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
            $matrix=['Nav'=>['value'=>$navHtml,'cmd'=>$btnsHtml]];
            if (!isset($settings['selectorKey'])){
                $settings['selectorKey']='';
            }
            $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($arr['selector']);
            foreach($flatEntry as $flatKey=>$value){
                if (is_object($value)){$value='{object}';}
                if (mb_strpos($flatKey,$settings['selectorKey'])!==0){continue;}
                $flatKeyComps=explode($S,$flatKey);
                if (!isset($tableInfo[$flatKeyComps[0]])){continue;}
                if (empty($settings['selectorKey'])){$subFlatKey=str_replace($settings['selectorKey'],'',$flatKey);} else {$subFlatKey=str_replace($settings['selectorKey'].$S,'',$flatKey);}
                $subFlatKeyComps=explode($S,$subFlatKey);
                $valueHtml='';
                if (count($subFlatKeyComps)>1){
                    // value is array
                    $element=['tag'=>'button','element-content'=>'{...}','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
                    $element['key']=['stepIn',$subFlatKeyComps[0]];
                    $valueHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                } else {
                    // non-array value
                    if (is_string($value)){$value=htmlentities($value);}
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
                    $deleteKey=$settings['selectorKey'].$S.$subFlatKeyComps[0];
                    $element=['tag'=>'button','element-content'=>'&xcup;','key'=>['deleteKey',$deleteKey],'hasCover'=>TRUE,'title'=>'Delete key','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
                    $cmdHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                }
                $label=array_shift($subFlatKeyComps);
                $matrix[$label]=['value'=>$valueHtml,'cmd'=>$cmdHtml];
            } // loop through flat array
            if ($level>0 && $entryCanWrite){
                $flatKey=$settings['selectorKey'];
                $element=['tag'=>'input','type'=>'text','key'=>['newKey'],'value'=>'','style'=>['color'=>'#fff','background-color'=>'#1b7e2b'],'excontainer'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
                $valueHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                $element=['tag'=>'button','element-content'=>'...','key'=>['addValue'],'value'=>$flatKey,'title'=>'Add value','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
                $cmdHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                $element=['tag'=>'button','element-content'=>'{...}','key'=>['addArr'],'value'=>$flatKey,'title'=>'Add array','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
                $cmdHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                $matrix['<i>Add</i>']=['value'=>$valueHtml,'cmd'=>$cmdHtml];
            }
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$arr['selector']['Name']]);
            if ($level==0 && empty($arr['settings']['hideEntryControls'])){
                $arr['hideKeys']=TRUE;
                $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryControls($arr);
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
        $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR;
        $SettingsTemplate=[
            'columns'=>[['Column'=>'Name','Filter'=>'']],
            'isSystemCall'=>FALSE,
            'orderBy'=>'Name',
            'isAsc'=>TRUE,
            'limit'=>10,
            'offset'=>FALSE
            ];
        $arr['html']=(isset($arr['html']))?$arr['html']:'';
        $_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=(isset($_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]))?$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]:$arr['settings'];
        // get settings
        $debugArr=['arr'=>$arr,'settings in'=>$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]];
        $settings=array_replace_recursive($SettingsTemplate,$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (!empty($formData['cmd'])){
            $debugArr['formData']=$formData;
            // update from form values
            $settings=array_replace_recursive($settings,$formData['val']);
            // command processing
            if (isset($formData['cmd']['addColumn'])){
                $settings['columns'][]=['Column'=>'EntryId','Filter'=>''];
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
        $element=['tag'=>'button','element-content'=>'➕','key'=>['addColumn'],'value'=>'add','title'=>'Add column','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
        $addColoumnBtn=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        $settings['columns'][]=['Column'=>$addColoumnBtn,'Filter'=>FALSE];
        // get selector
        $filterSkipped=FALSE;
        $selector=$this->selectorFromSetting($arr['selector'],$settings,FALSE);
        $rowCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,$settings['isSystemCall']);
        if (empty($rowCount)){
            $selector=$this->selectorFromSetting($arr['selector'],$settings,TRUE);
            $rowCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,$settings['isSystemCall']);
            $filterSkipped=TRUE;
        }
        $csvMatrix=[];
        if ($rowCount<=$settings['offset']){$settings['offset']=0;}
        if (!empty($rowCount)){
            // create html
            $filterKey='Filter';
            $matrix=[];
            $columnOptions=[];
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$settings['isSystemCall'],'Read',$settings['orderBy'],$settings['isAsc'],$settings['limit'],$settings['offset'],[],TRUE,FALSE) as $entry){
                $rowIndex=$entry['rowIndex']+intval($settings['offset'])+1;
                $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
                // setting up
                if (empty($columnOptions)){
                    $columnOptions=$this->getKeySelector($flatEntry);
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
                        if ($filterSkipped && !empty($cntrArr['Filter'])){$style=['color'=>'#fff','background-color'=>'#a00'];} else {$style=[];}
                        $filterTextField=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','title'=>'Filter list','style'=>$style,'value'=>$cntrArr['Filter'],'key'=>['columns',$columnIndex,'Filter'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']]);
                        // "order by"-buttons
                        if (strcmp(strval($settings['orderBy']),$column)===0){$styleBtnSetting=['color'=>'#fff','background-color'=>'#a00'];} else {$styleBtnSetting=[];}
                        if ($settings['isAsc']){$style=$styleBtnSetting;} else {$style=[];}
                        $element=['tag'=>'button','element-content'=>'&#9650;','key'=>['asc',$column],'value'=>$columnIndex,'style'=>['padding'=>'0','line-height'=>'1em','font-size'=>'1.5em'],'title'=>'Order ascending','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'style'=>$style,'callingFunction'=>$arr['callingFunction']];
                        $matrix[$filterKey][$columnIndex].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                        $matrix[$filterKey][$columnIndex].=$filterTextField;
                        if (!$settings['isAsc']){$style=$styleBtnSetting;} else {$style=[];}
                        $element=['tag'=>'button','element-content'=>'&#9660;','key'=>['desc',$column],'value'=>$columnIndex,'style'=>['padding'=>'0','line-height'=>'1em','font-size'=>'1.5em'],'title'=>'Order descending','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'style'=>$style,'callingFunction'=>$arr['callingFunction']];
                        $matrix[$filterKey][$columnIndex].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                        // column selector
                        $matrix['Columns'][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['options'=>$columnOptions,'value'=>$cntrArr['Column'],'keep-element-content'=>TRUE,'key'=>['columns',$columnIndex,'Column'],'title'=>'Select column or field','style'=>[],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']]);
                        // remove column button
                        if ($columnIndex>0){
                            $element=['tag'=>'button','element-content'=>'&xcup;','keep-element-content'=>TRUE,'key'=>['removeColumn',$columnIndex],'value'=>'remove','hasCover'=>TRUE,'style'=>[],'title'=>'Remove column','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
                            $matrix['Columns'][$columnIndex].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                        }
                        $matrix['Columns'][$columnIndex]=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$matrix['Columns'][$columnIndex],'keep-element-content'=>TRUE,'style'=>['width'=>'max-content']]);
                        // table rows
                        if (strpos($cntrArr['Column'],'Read ')!==FALSE || strpos($cntrArr['Column'],'Write ')!==FALSE || strpos($cntrArr['Column'],'Privileges ')!==FALSE){
                            $rightsArr=['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'selector'=>$entry];
                            $right=substr($cntrArr['Column'],0,strpos($cntrArr['Column'],' '));
                            $matrix[$rowIndex][$columnIndex]=$this->oc['SourcePot\Datapool\Foundation\Access']->rightsHtml($rightsArr,$right);
                        } else {
                            $matrix[$rowIndex][$columnIndex]='{Nothing here...}';
                        }
                        // present entry
                        $subMatix=[];
                        foreach($flatEntry as $flatColumnKey=>$value){
                            if (is_object($value)){$value='{object}';}
                            if (is_string($value)){$value=htmlentities($value);}
                            if (strcmp($flatColumnKey,$cntrArr['Column'])===0){
                                // $flatColumnKey === column selection -> standard entry presentation
                                $csvMatrix[$rowIndex][$cntrArr['Column']]=$value;
                                $matrix[$rowIndex][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->value2tableCellContent($value,[]);
                            } else if (strpos($flatColumnKey,$cntrArr['Column'].\SourcePot\Datapool\Root::ONEDIMSEPARATOR)===0){
                                // column selection is substring of $flatColumnKey -> submatrix presentation 
                                $subKey=str_replace($cntrArr['Column'],'',$flatColumnKey);
                                $subKey=trim($subKey,\SourcePot\Datapool\Root::ONEDIMSEPARATOR);
                                $subMatix[$subKey]=$value;
                            }
                        }
                        // sub matrix preesentation
                        if (!empty($subMatix)){
                            $subMatix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($subMatix);
                            $matrix[$rowIndex][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$subMatix,'hideKeys'=>TRUE,'hideHeader'=>TRUE,'keep-element-content'=>TRUE,'class'=>'matrix']);
                        }
                        // table row marking
                        $class=$this->oc['SourcePot\Datapool\Root']->source2class($arr['selector']['Source']);
                        $pageStateSelector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($arr['selector']['app']??$class);
                        if ($entry['Source']===$pageStateSelector['Source'] && $entry['EntryId']===(isset($pageStateSelector['EntryId'])?$pageStateSelector['EntryId']:'')){
                            $matrix[$rowIndex]['trStyle']=['background-color'=>'#e4e2ff'];
                        }
                    }
                } // end of loop through columns
            } // end of loop through entries
            foreach($settings['columns'] as $columnIndex=>$cntrArr){
                if ($cntrArr['Filter']===FALSE){
                    $matrix['Limit, offset'][$columnIndex]='';
                } else if ($columnIndex===0){
                    $options=[5=>'5',10=>'10',25=>'25',50=>'50',100=>'100',200=>'200'];
                    $matrix['Limit, offset'][$columnIndex]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['options'=>$options,'key'=>['limit'],'value'=>$settings['limit'],'title'=>'Rows to show','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']]);
                    $matrix['Limit, offset'][$columnIndex].=$this->getOffsetSelector($arr,$settings,$rowCount);
                    $matrix['Limit, offset'][$columnIndex].=$this->oc['SourcePot\Datapool\Tools\CSVtools']->matrix2csvDownload($csvMatrix);
                } else {
                    $matrix['Limit, offset'][$columnIndex]='';
                }
            }
            $caption=$arr['containerKey'];
            $caption.=' ('.$rowCount.')';
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$caption]);
        }
        if ($isDebugging){
            $debugArr['arr out']=$arr;
            $debugArr['settings out']=$settings;
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        return $arr;        
    }

    private function getKeySelector(array $flatEntry):array{
        $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR;
        // options for complete match
        $upperOrderKeys=[];
        $keyMatchOptions=[];
        foreach($flatEntry as $flatColumnKey=>$value){
            if (strpos($flatColumnKey,$S)!==FALSE){
                $flatKeyComps=explode($S,$flatColumnKey);
                $dimensions=count($flatKeyComps);
                $upperOrderKey='';
                for($dim=0;$dim<$dimensions;$dim++){
                    $upperOrderKey=(empty($upperOrderKey))?$flatKeyComps[$dim]:($upperOrderKey.=$S.$flatKeyComps[$dim]);
                    if (!isset($upperOrderKeys[$upperOrderKey])){
                        $upperOrderKeys[$upperOrderKey]=['count'=>0,'dim'=>$dim];
                    }
                    $upperOrderKeys[$upperOrderKey]['count']++;
                }
                
            }
            $keyMatchOptions[$flatColumnKey]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($flatColumnKey);
        }
        // options for sub key matches
        $subKeyOptions=[];
        foreach($upperOrderKeys as $upperOrderKey=>$keyCount){
            if ($keyCount>1){
                $subKeyOptions[$upperOrderKey]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($upperOrderKey);
            }
        }
        // options for special value presentation
        $presentationOptions=['Read rights'=>'Read rights','Write rights'=>'Write rights'];
        if (isset($flatEntry['Privileges'])){$presentationOptions['Privileges column']='Privileges column';}
        // combine options
        $columnOptions=$presentationOptions+$subKeyOptions+$keyMatchOptions;
        ksort($columnOptions);
        return $columnOptions;
    }
    
    private function getOffsetSelector(array $arr,array $settings,int $rowCount):string
    {
        $limit=intval($settings['limit']);
        if ($rowCount<=$limit){return '';}
        $options=[];
        $optionCount=ceil($rowCount/$limit);
        for($index=0;$index<$optionCount;$index++){
            $offset=$index*$limit;
            $upperOffset=$offset+$limit;
            $options[$offset]=strval($offset+1).'...'.strval(($upperOffset>$rowCount)?$rowCount:$upperOffset);
        }
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['options'=>$options,'key'=>['offset'],'value'=>$settings['offset'],'title'=>'Offset from which rows will be shown','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']]);
        return $html;
    }

    private function selectorFromSetting(array $selector,array $settings,bool $resetFilter=FALSE):array
    {
        // This function is a suporting function for entryList() only.
        // It has no further use.
        $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR;
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
        $targetId=(isset($arr['containerId']))?$arr['containerId']:$arr['callingFunction'];
        $arr['class']=(isset($arr['class']))?$arr['class']:'comment';
        $arr['style']=(isset($arr['style']))?$arr['style']:[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$targetId);
        if (isset($formData['cmd']['Add comment'])){
            if (empty($formData['val']['comment'])){
                $this->oc['logger']->log('warning','Adding comment failed: comment was empty',$formData['val']);         
            } else {
                $arr['selector']['Source']=key($formData['cmd']['Add comment']);
                $arr['selector']['EntryId']=key($formData['cmd']['Add comment'][$arr['selector']['Source']]);
                $arr['selector']['timeStamp']=current($formData['cmd']['Add comment'][$arr['selector']['Source']]);
                $arr['selector']['Content']['Comments'][$arr['selector']['timeStamp']]=['Comment'=>$formData['val']['comment'],'Author'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()];
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector']);
            }
        }
        if (isset($arr['selector']['Content']['Comments'])){$Comments=$arr['selector']['Content']['Comments'];} else {$Comments=[];}
        $commentsHtml='';
        foreach($Comments as $creationTimestamp=>$comment){
            $footer=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.$creationTimestamp);
            $footer.=' '.$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($comment['Author'],3);
            $commentHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>$comment['Comment'],'keep-element-content'=>FALSE,'class'=>$arr['class']]);
            $commentHtml=preg_replace("/([\x{1f000}-\x{1ffff}])/u",' <span class="emoji">${1}</span> ',$commentHtml);
            $commentHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>$footer,'keep-element-content'=>FALSE,'class'=>$arr['class'].'-footer']);
            $commentsHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$commentHtml,'keep-element-content'=>TRUE,'class'=>$arr['class']]);
        }
        $textId=$targetId.'-text';
        $newComment='';
        if (isset($arr['selector']['Write'])){
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],'Write')){
                $newComment.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h3','element-content'=>'New comment','style'=>['float'=>'left','clear'=>'both','margin'=>'0 5px']]);
                $newComment.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'textarea','element-content'=>'','placeholder'=>'e.g. My new comment','key'=>['comment'],'id'=>$textId,'style'=>['float'=>'left','clear'=>'both','margin'=>'5px','font-size'=>'1.2rem'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$targetId]);
                $newComment.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Emojis for '.$textId,'generic',$arr['selector'],['method'=>'emojis','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder','target'=>$textId]);
                $newComment.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'Add','key'=>['Add comment',$arr['selector']['Source'],$arr['selector']['EntryId']],'value'=>time(),'style'=>['float'=>'left','clear'=>'both','margin'=>'5px'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$targetId]);
                $appArr=['html'=>$newComment,'icon'=>'&#9871;','title'=>'Add comment','style'=>['clear'=>'both']];
                $newComment=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
            }
        }
        $arr['html'].=$commentsHtml.$newComment;
        return $arr;
    }
    
    /**
    * This method adds an html-form to the parameter arr['html'].
    * Through the form a transmitter can be selected and the selected entry can be sent through this transmitter.
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
        $arr['settings']['Recipient']=$arr['settings']['Recipient']??($this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId());
        // create form
        $matrix=[];
        $selectArr=['callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'excontainer'=>FALSE];
        $selectArr['options']=$availableTransmitter;
        $selectArr['key']=['settings','Transmitter'];
        $selectArr['selected']=$arr['settings']['Transmitter'];
        $matrix['Transmitter']['Value']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $selectArr['options']=$this->oc['SourcePot\Datapool\Foundation\User']->getUserOptions([],$arr['settings']['relevantFlatUserContentKey']);
        $selectArr['key']=['settings','Recipient'];
        $selectArr['selected']=$arr['settings']['Recipient'];
        $matrix['Recipient']['Value']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $selectArr['excontainer']=TRUE;
        $selectArr['tag']='input';
        $selectArr['type']='text';
        //$selectArr['value']=(isset($arr['selector']['Content']['Subject']))?$arr['selector']['Content']['Subject']:((isset($arr['selector']['Name']))?$arr['selector']['Name']:'...');
        $selectArr['value']=$selectArr['value']??$arr['selector']['Content']['Subject']??$arr['selector']['Name']??'...';
        $selectArr['key']=['selector','Content','Subject'];
        $matrix['Subject']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($selectArr);
        $selectArr['excontainer']=FALSE;
        $selectArr['type']='submit';
        $selectArr['value']='Send';
        $selectArr['hasCover']=TRUE;
        $selectArr['key']=['send'];
        $matrix['']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($selectArr);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE]);
        return $arr;
    }    

    /**
    * This method add an html-string to the parameter $arr['html'] which contains an image presentation of entries selected by the parameter arr['selector'].
    * @param array  $arr    Contains the entry selector and settings 
    * @return array
    */
    public function getImageShuffle(array $arr):array
    {
        $arr['html']=$arr['html']??'';
        $arr['callingFunction'].='-shuffle';
        $settingsTemplate=['isSystemCall'=>FALSE,'orderBy'=>'rand()','isAsc'=>FALSE,'limit'=>4,'offset'=>0,'autoShuffle'=>TRUE,'getImageShuffle'=>$arr['selector']['Source']];
        $settings=array_replace_recursive($settingsTemplate,$arr['settings']);
        $items=[];
        $presentArrTemplate=['callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'class'=>'imageShuffle','settings'=>['presentEntry'=>__FUNCTION__]];
        $entrySelector=$arr['selector']+['Params'=>'%image%'];
        $idPrefix=__FUNCTION__.'-'.$arr['containerId'].'-';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entrySelector,$settings['isSystemCall'],'Read',$settings['orderBy'],$settings['isAsc'],$settings['limit'],$settings['offset']) as $entry){
            $presentArr=$presentArrTemplate;
            $presentArr['selector']=$entry;
            if (count($items)===0){$display='inherit';} else {$display='none';}
            $item=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->loadEntry($presentArr);
            $items[]=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$item,'keep-element-content'=>TRUE,'id'=>$idPrefix.count($items),'class'=>'imageShuffleItem','style'=>['display'=>$display],'function'=>__FUNCTION__]); 
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>implode('',$items),'keep-element-content'=>TRUE,'class'=>'imageShuffleItemWrapper','style'=>['width'=>$settings['style']['width']??320,'height'=>$settings['style']['height']??400]]);
        // get << and >> button
        if (!empty($entry['rowCount'])){
            // button div
            $btnHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'a','element-content'=>'&#10094;&#10094;','keep-element-content'=>TRUE,'id'=>$idPrefix.'prev','class'=>'js-button','style'=>['clear'=>'left','min-width'=>'8em','padding'=>'3px 0']]);
            $btnHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'a','element-content'=>'&#10095;&#10095;','keep-element-content'=>TRUE,'id'=>$idPrefix.'next','class'=>'js-button','style'=>['float'=>'right','min-width'=>'8em','padding'=>'3px 0']]);
            $btnWrapper=['tag'=>'div','element-content'=>$btnHtml,'keep-element-content'=>TRUE,'id'=>$idPrefix.'btnWrapper','class'=>'imageShuffleBtnWrapper'];
            if ($settings['autoShuffle']){
                $btnWrapper['style']['display']='none';
            }
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnWrapper);
        }   
        return $arr;
    }
    
}
?>