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
    
    private $entryTable='';
    private $entryTemplate=[];
    
    public function __construct($oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }
    
    public function getEntryTable()
    {
        return $this->entryTable;
    }

    public function getEntryTemplate()
    {
        return $this->entryTemplate;
    }
    
    /**
    * This method returns the definition name from the class argument provided. 
    * @return string
    */
    private function class2name(string $class):string
    {
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
    public function addDefintion(string $callingClass,array $definition)
    {
        $entry=['Source'=>$this->entryTable,'Group'=>'Templates','Folder'=>$callingClass,'Name'=>$this->class2name($callingClass),'Owner'=>'SYSTEM'];
        $entry['EntryId']=md5(json_encode($entry));
        $entry['Content']=$definition;
        return $this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,TRUE);
    }
    
    /**
    * This method returns the definition for the provided entry, based on the entryentry['Class'] or ['Type'].
    * @param array $entry Is the orginal entry
    * @return array
    */
    public function getDefinition(array $entry):array|bool
    {
        $selector=['Source'=>$this->entryTable,'Group'=>'Templates'];
        if (!empty($entry['app'])){
            $selector['Name']=$this->class2name($entry['app']);
        } else if (!empty($entry['Type'])){
            $typeComps=explode('|',$entry['Type']);
            $selector['Name']=array_pop($typeComps);
        } else {
            $entry['function']=__FUNCTION__;
            $this->oc['logger']->log('error','Function "{function}": Entry missing Type-key or Class-key.',$entry);
        }
        $definition=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($selector,TRUE);
        return (is_array($definition))?$definition:[];
    }
    
    /**
    * This method returns an entry from the definition and the entry provided as argument, as well as default values.
    * Default values originate from the database entry template as well as default values provided by the defintion.
    * @return array
    */
    public function definition2entry(array $definition,array $entry=[]):array
    {
        $flatArrayKeySeparator=\SourcePot\Datapool\Root::ONEDIMSEPARATOR;
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
        $flatDefinition=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($definition);
        $defaultArrKeys2remove=[];
        $defaultArr=[];
        foreach($flatDefinition as $definitionKey=>$definitionValue){
            if (mb_strpos($definitionKey,'@default')!==FALSE){
                $defaultKey=str_replace($flatArrayKeySeparator.'@default','',$definitionKey);
                $defaultArr[$defaultKey]=$definitionValue;
            } else if (mb_strpos($definitionKey,'@type')!==FALSE && strcmp($definitionValue,'btn')===0){
                $defaultKey=str_replace($flatArrayKeySeparator.'@type','',$definitionKey);
                $defaultArrKeys2remove[$defaultKey]=FALSE;    // to remove if default value is empty
            } else if (mb_strpos($definitionKey,'@type')!==FALSE && strcmp($definitionValue,'method')===0){
                $defaultKey=str_replace($flatArrayKeySeparator.'@type','',$definitionKey);
                $defaultArrKeys2remove[$defaultKey]=TRUE;    // to remove if default value is empty
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
        return $entry;
    }
    
    /**
    * This method returns an element which can be presented on the webpage based on the entry and flatSelectorKey argument provided.
    * You can use the wildcard character '*' at the end of $flatSelectorKey.
    * In a first step the method trys to get any exsiting definition for the provided entry. If this fails, a standard text (input) field will be returned.
    * If the definition exsists, the webpaghe element will be created based on this definition. 
    * @return array
    */
    public function selectorKey2element(array $entry,string $flatSelectorKey,$value=NULL,string $callingClass='',string $callingFunction='',bool $skipKeysWithNoDefintion=FALSE,$definition=[]):array|string
    {
        $value=strval($value);
        if (empty($definition)){
            $definition=$this->getDefinition($entry);
        }
        $selectorKeyComps=explode(\SourcePot\Datapool\Root::ONEDIMSEPARATOR,$flatSelectorKey);
        $element=[];
        if (empty($definition)){
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write')){
                if (strlen($value)>20){
                    $element=array('tag'=>'textarea','keep-element-content'=>TRUE,'element-content'=>$value,'key'=>$selectorKeyComps);
                } else {
                    $element=array('tag'=>'input','type'=>'text','value'=>$value,'key'=>$selectorKeyComps);
                }
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
                $definitionKey=trim($definitionKey,\SourcePot\Datapool\Root::ONEDIMSEPARATOR.'*');
                if (mb_strpos($flatSelectorKey,$definitionKey)===FALSE){
                    // not the correct definition key
                } else {
                    $definitionAttr=array_pop($definitionKeyComps);
                    $sPos=mb_strpos($definitionAttr,\SourcePot\Datapool\Root::ONEDIMSEPARATOR);
                    if ($sPos!==FALSE){
                        $tmp=$definitionAttr;
                        $definitionAttr=mb_substr($definitionAttr,0,$sPos);
                        $subKey=mb_substr($tmp,$sPos+strlen(\SourcePot\Datapool\Root::ONEDIMSEPARATOR));
                        $element[$definitionAttr][$subKey]=$definitionValue;
                    } else {
                        $element[$definitionAttr]=$definitionValue;
                    }
                }
            }
            if (empty($element) && $skipKeysWithNoDefintion){
                return [];
            }
            foreach($element as $definitionAttr=>$definitionValue){
                if (!is_array($definitionValue)){continue;}
                $element[$definitionAttr]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($definitionValue);
            }
            $element['selector']['Read']=(isset($entry['Read']))?$entry['Read']:'ADMIN_R';
            $element['selector']['Write']=(isset($entry['Write']))?$entry['Write']:'ADMIN_R';
            $element['selector']['Owner']=(isset($entry['Owner']))?$entry['Owner']:'SYSTEM';
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
    public function definition2html(array $definition,array $entry,string $callingClass='',string $callingFunction=''):string
    {
        if (empty($callingClass)){$callingClass=__CLASS__;}
        if (empty($callingFunction)){$callingFunction=__FUNCTION__;}
        // flatten arrays
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
        $flatDefinition=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($definition['Content']);
        $attrIdentifier=(\SourcePot\Datapool\Root::ONEDIMSEPARATOR).'@';
        $entryArr=[];
        foreach($flatDefinition as $definitionKey=>$definitionValue){
            $definitionKeyComps=explode($attrIdentifier,$definitionKey);
            $definitionKey=array_shift($definitionKeyComps);
            // add attributes with value to entryArr
            $definitionKeyAttr=array_pop($definitionKeyComps);
            if (!empty($definitionKeyAttr)){
                $entryArr[$definitionKey][$definitionKeyAttr]=$definitionValue;
            }
            // add entry value to entryArr
            if (isset($flatEntry[$definitionKey])){
                $entryArr[$definitionKey]['value']=$flatEntry[$definitionKey];
            }
        }
        // create matrices
        $matrices=[];
        $tableCntrArr=[];
        foreach($entryArr as $key=>$defArr){
            // get key components
            $keyComps=explode(\SourcePot\Datapool\Root::ONEDIMSEPARATOR,$key);
            $keyArr=$keyComps;
            $key=array_pop($keyComps);
            if (empty($keyComps)){$caption=$key;} else {$caption=implode(' &rarr; ',$keyComps);}
            if (!isset($settings[$caption])){$settings[$caption]=[];}
            if (isset($defArr['tag']) || isset($defArr['function'])){
                $defArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($defArr);
                $defArr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntry);
                $defArr['callingClass']=$callingClass;
                $defArr['callingFunction']=$callingFunction;
                $defArr['key']=(empty($defArr['key']))?$keyArr:$defArr['key'];
                $tableCntrArr[$caption]['isApp']=(empty($defArr['isApp']))?FALSE:$defArr['isApp'];
                $tableCntrArr[$caption]['hideCaption']=(empty($defArr['hideCaption']))?FALSE:$defArr['hideCaption'];
                $tableCntrArr[$caption]['hideHeader']=(empty($defArr['hideHeader']))?TRUE:$defArr['hideHeader'];
                $tableCntrArr[$caption]['hideKeys']=(empty($defArr['hideKeys']))?FALSE:$defArr['hideKeys'];
                $value=$this->elementDef2element($defArr);
                if (empty($value)){
                    // The element has probably no Read access on entry level or element level
                    // You can overwrite entry Read access on the element level with '@Read'
                } else {
                    $matrices[$caption][$key]['Value']=$value;
                }
            } else if (empty($key)){
                // empty key
            } else {
                // unknown tags
                $definition['currentKey']=$key;
                //$this->oc['logger']->log('warning','Definition error: Folder: "{Folder}", Name: "{Name}", key: "{currentKey}", tag or function not set.',$definition);
            }
        }
        // create html
        $html='';
        foreach($matrices as $caption=>$matrix){
            $tableCntr=$tableCntrArr[$caption];
            $tableArr=['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>$caption,'hideCaption'=>$tableCntr['hideCaption'],'hideHeader'=>$tableCntr['hideHeader'],'hideKeys'=>$tableCntr['hideKeys'],'style'=>['box-shadow'=>'none','border'=>'none']];
            $tableHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table($tableArr);
            if (empty($tableCntr['isApp'])){
                $html.=$tableHtml;
            } else {
                $app=['html'=>$tableHtml,'icon'=>$tableCntr['isApp'],'title'=>$caption];
                $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($app);
            }
        }
        return $html;
    }

    public function entry2form(array $entry=[]):string
    {
        $definition=$this->getDefinition($entry);
        $html='';
        if (empty($definition)){
            $msg='Problem: Method "'.__FUNCTION__.'" no definition found for the provided entry with';
            $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->traceHtml($msg);
        } else {
            if ($this->oc['SourcePot\Datapool\Tools\MiscTools']->startsWithUpperCase($definition['Name'])){
                // entry is stored in setup dirspace
                $dataStorageClass='SourcePot\Datapool\Foundation\Filespace';
            } else {
                // entry is stored in database
                $dataStorageClass='SourcePot\Datapool\Foundation\Database';    
            }
            if (empty($entry)){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>'Called '.__FUNCTION__.' with empty entry.']);
            } else {
                // form processing
                $this->oc[$dataStorageClass]->resetStatistic();
                $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
                if (isset($formData['cmd']['delete'])){
                    $this->oc[$dataStorageClass]->deleteEntries($entry);
                } else if (!empty($formData['cmd'])){
                    $entry['entryIsUpdated']=TRUE;
                    $entry=array_replace_recursive($entry,$formData['val']);
                    if ($formData['hasValidFiles']){
                        $flatFile=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($formData['files']);
                        $fileArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatArrLeaves($flatFile);
                        if ($fileArr['error']==0){
                            $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($fileArr,$entry);
                        }
                    } else {
                        $this->oc[$dataStorageClass]->updateEntry($entry,FALSE,FALSE);
                    }
                    $statistics=$this->oc[$dataStorageClass]->getStatistic();
                    $entryType=(isset($entry['Source']))?strval($entry['Source']):strval($entry['Class']);
                    $context=['type'=>$entryType,'EntryId'=>$entry['EntryId'],'statistics'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics)];
                    $this->oc['logger']->log('info','{type}-entry selected by "EntryId={EntryId}" processed: {statistics}',$context);    
                }
                if (isset($this->oc['SourcePot\Datapool\Tools\MediaTools'])){
                    $iconArr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getIcon(['selector'=>$entry]);
                    $html.=$iconArr['html'];
                }
                $html.=$this->definition2html($definition,$entry,__CLASS__,__FUNCTION__);
            }
        }
        return $html;
    }
    
    private function elementDef2element(array $element,$outputStr=NULL):array|string
    {
        $read=(empty($element['selector']['Read']))?'ALL_R':$element['selector']['Read'];
        $write=(empty($element['selector']['Write']))?'ALL_R':$element['selector']['Write'];
        $element['selector']=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($element['selector'],$read,$write);
        // check read access
        $access=$this->oc['SourcePot\Datapool\Foundation\Access']->access($element['selector'],'Read');
        if (!$access){
            return ['tag'=>'p','element-content'=>'&#10074;&#10074;','keep-element-content'=>TRUE,'style'=>[]];
        }
        // check if element requests method
        if (!empty($element['function'])){
            $html=$this->defArr2html($element);
            return $html;
        }
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
        $outputStr=(is_array($outputStr))?(json_encode($outputStr)):strval($outputStr);
        // compile tag
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($element['selector'],'Write')){
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
            } else if (strcmp($element['tag'],'meter')===0){
                $element['value']=$outputStr;
                $element['title']=$outputStr;
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
                if (empty($outputStr)){$element=[];} else {$element['element-content']=$outputStr;}
            }
        }
        return $element;
    }
    
    private function defArr2html(array $element):string
    {
        $class=$element['class']??'SourcePot\Datapool\Tools\HTMLbuilder';
        $function=$element['function'];
        if (method_exists($class,$function)){
            $element['keep-element-content']=TRUE;
            $return=$this->oc[$class]->$function($element);
            if (is_array($return)){
                $html=$return['html'];
            } else {
                $html=$return;
            }
        } else {
            $errArr=['tag'=>'p','element-content'=>$class.'→'.$function.'() not found.'];
            $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($errArr);
        }
        return $html??'';
    }
    
}
?>