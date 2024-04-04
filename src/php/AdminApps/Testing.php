<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\AdminApps;

class Testing implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;

    private $entryTable;
    private $entryTemplate=array();
    
    private $dataTypes=array(''=>'mixed','string'=>'string','int'=>'int','float'=>'float','bool'=>'bool','array'=>'array','null'=>'null');
    private $boolStr=array(0=>'FALSE',1=>'TRUE');
    
    public function __construct($oc){
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }

    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
    }
    
    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Admin','Emoji'=>'==','Label'=>'Testing','Read'=>'ALL_CONTENTADMIN_R','Class'=>__CLASS__);
        } else {
            $html='';
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Test configuration setting','generic',array(),array('method'=>'getTestSettingsHtml','classWithNamespace'=>__CLASS__),array('style'=>array('background-color'=>'#c9ffc9')));
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Testing result','generic',array(),array('method'=>'getTestHtml','classWithNamespace'=>__CLASS__),array());
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }

    public function getTestSettingsHtml($arr){
        $arr['html']='';
        $arr=$this->testParams($arr);
        $arr=$this->testArgs($arr);
        return $arr;
    }
    
    public function getTestHtml($arr){
        $arr['html']='';
        $arr=$this->test($arr);
        return $arr;
    }
    
    private function finalizeSelector($arr,$method,$name){
        $arr['selector']['Source']=$this->entryTable;
        $arr['selector']['Group']=$method;
        $arr['selector']['Folder']=$_SESSION['currentUser']['EntryId'];
        $arr['selector']['Type']=$this->entryTable.' array';
		$arr['selector']['Name']=$name;
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($arr['selector'],'ALL_R','ALL_CONTENTADMIN_R');
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],array('Group','Folder','Name','Type'),0);
		return $arr;
	}

    private function getParamsId(string $class, string $method):string
    {
        return md5($class.'|'.$method);
    }

    private function testParams($arr){
        $arr=$this->finalizeSelector($arr,__FUNCTION__,'settings');
		$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        if ($entry){$arr['selector']=$entry;}
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (!empty($formData['cmd'])){
            $elementId=key($formData['val']);
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        }
        // get options
        $methods=array();
        $classes=array_keys($this->oc);
        $classes=array_combine($classes,$classes);
        ksort($classes);
        if (!empty($arr['selector']['Content']['class'])){
            $methods=get_class_methods($arr['selector']['Content']['class']);
            $methods=array_combine($methods,$methods);
            ksort($methods);
        }
        $return=array('html'=>'','Parameter'=>array(),'result'=>array());
        $matchTypOptions=array('identical'=>'Identical','contains'=>'Contains','epPublication'=>'European patent publication');
        $contentStructure=array('class'=>array('method'=>'select','value'=>'','options'=>$classes,'excontainer'=>FALSE),
                                'method'=>array('method'=>'select','value'=>'','options'=>$methods,'excontainer'=>FALSE),
                                'Save'=>array('method'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
                                );
        // get HTML
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Select method to test';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){
            $row['trStyle']=array('background-color'=>'#a00');
        }
        $matrix=array('Parameter'=>$row);
        $arr[__FUNCTION__]=$arr['selector']['Content'];
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
        return $arr;
    }

    private function testArgs($arr){
        // get method properties
        if (empty($arr['testParams']['class']) || empty($arr['testParams']['method'])){return $arr;}
        $testingParamsId=$this->getParamsId($arr['testParams']['class'],$arr['testParams']['method']);
        if (!method_exists($arr['testParams']['class'],$arr['testParams']['method'])){return $arr;}
        // get content structure from args
        $contentStructure=array();
        $f=new \ReflectionMethod($arr['testParams']['class'],$arr['testParams']['method']);
        foreach($f->getParameters() as $pIndex=>$param){
            // get default value
            try{
                $default=$param->getDefaultValue();
            } catch (\Exception $e){
                $default='';
            }
            if (is_array($default)){
                $default=json_encode($default);
            } else if (is_bool($default)){
                $default=$this->boolStr[intval($default)];
            }
            // get type
            $dataType=strval($param->getType());
            $contentStructure[$param->name]=array('method'=>'element','tag'=>'input','value'=>$default,'placeholder'=>$default,'type'=>'text','excontainer'=>TRUE);
            $contentStructure[$param->name.' type ']=array('method'=>'select','value'=>$dataType,'options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE,'excontainer'=>TRUE);
        }
        //
        $arr=$this->finalizeSelector(array('html'=>$arr['html']),__FUNCTION__,$testingParamsId);
        $arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Method arguments for testing';
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $arr;
	}
    
    private function test($arr)
    {
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        $results=array();
        if (isset($formData['cmd']['array'])){
            $results=$this->runTest($arr,0);
        } else if (isset($formData['cmd']['json'])){
            $results=$this->runTest($arr,1);
        } else if (isset($formData['cmd']['html'])){
            $results=$this->runTest($arr,2);
        }
        // test control form
        $btnArr=array('tag'=>'input','type'=>'submit','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
        $cntrMatrix=array();
        $btnArr['value']='Run (array -> table)';
        $btnArr['key']=array('array');
        $cntrMatrix['Commands']['Run']=$btnArr;
        $btnArr['value']='Run (array -> json';
        $btnArr['key']=array('json');
        $cntrMatrix['Commands']['JSON']=$btnArr;
        $btnArr['value']='Run (array -> html';
        $btnArr['key']=array('html');
        $cntrMatrix['Commands']['html']=$btnArr;
        // build html  
        $arr['html']='';
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$cntrMatrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Test'));
        foreach($results as $caption=>$resultMatrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$resultMatrix,'hideHeader'=>FALSE,'hideKeys'=>TRUE,'thKeepCase'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        return $arr;
    }
    
    private function runTest(array $arr,int $outputFormat=0):array
    {
        $results=array();
        // load configuration
        $args=array();
        $config=array();
        // get params
        $params=$this->finalizeSelector(array(),'testParams','settings');
        $params=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($params['selector'],TRUE);
        $config['params']=$params['Content'];
        // get args
        $testingParamsId=$this->getParamsId($config['params']['class'],$config['params']['method']);
        $tests=$this->finalizeSelector(array(),'testArgs',$testingParamsId);
        $tests=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($tests['selector'],array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE));
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($tests,TRUE,'Read','EntryId',TRUE) as $argsEntry){
            $testIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($argsEntry['EntryId']);
            $config['tests'][$testIndex]=array();
            while ($argsEntry['Content']){
                $argName=key($argsEntry['Content']);
                $argValue=array_shift($argsEntry['Content']);
                $argType=array_shift($argsEntry['Content']);
                $config['tests'][$testIndex][$argName]=array('name'=>$argName,'value'=>$argValue,'type'=>$argType);
            }
        }
        // testing
        $results['Result']=array();
        foreach($config['tests'] as $testIndex=>$args){
            $valueArr=$this->testArgs2valueArr($args);
            $context=$this->oc['SourcePot\Datapool\Foundation\Logger']->methodTest($this->oc[$config['params']['class']],$config['params']['method'],$valueArr);
            $results=$this->addContext2results($results,$config,$testIndex,$context,$outputFormat);
        }
        return $results;
    }
    
    private function testArgs2valueArr(array $args):array
    {
        $valueArr=array();
        foreach($args as $argName=>$arrNameValueType){
            if ($arrNameValueType['type']==='array'){
                $valueArr[]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr($arrNameValueType['value']);
            } else if ($arrNameValueType['type']==='bool'){
                if (stripos($arrNameValueType['value'],'TRUE')===0){
                    $valueArr[]=TRUE;
                } else if (stripos($arrNameValueType['value'],'FALSE')===0){
                    $valueArr[]=FALSE;
                } else {
                    $valueArr[]=boolval($arrNameValueType['value']);
                }
            } else if ($arrNameValueType['type']==='int'){
                $valueArr[]=intval($arrNameValueType['value']);
            } else if ($arrNameValueType['type']==='float'){
                $valueArr[]=floatval($arrNameValueType['value']);
            } else if ($arrNameValueType['type']==='null'){
                $valueArr[]=NULL;
            } else {
                $valueArr[]=$arrNameValueType['value'];
            }
        }
        return $valueArr;
    }
    
    private function addContext2results(array $results,array $config,int $testIndex,array $context,int $outputFormat=0):array
    {
        $toRemove=array('class'=>'class','method'=>'method');
        $args=array_keys($config['tests'][$testIndex]);
        $args=implode(',',$args);
        $caption=$config['params']['class'].'&horbar;&gt;'.$config['params']['method'].'('.$args.')';
        $results[$caption][$testIndex]['Test']=$testIndex;
        foreach($context as $key=>$value){
            if (isset($toRemove[$key])){
                unset($toRemove[$key]);
                continue;
            }
            if ($value===NULL){
                $value='NULL';
            } else if (is_array($value)){
                if (empty($value)){
                    $value='[]';
                } else {
                    if ($outputFormat===1){
                        $value=json_encode($value);
                        $value=($outputFormat===2)?strval($value):htmlentities(strval($value));
                    } else {
                        $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($value);
                        $value=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE));
                    }
                }
            } else if ($value===FALSE){
                $value='FALSE';
            } else if ($value===TRUE){
                $value='TRUE';
            } else {
                $value=($outputFormat===2)?strval($value):'"'.htmlentities(strval($value)).'"';
            }
            $results[$caption][$testIndex][$key]=$value;
        }
        return $results;
    }
    
}
?>