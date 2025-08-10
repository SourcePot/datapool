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
    
    private const APP_ACCESS='ADMIN_R';
    
    private $oc;

    private $entryTable='';
    private $entryTemplate=[];
    
    private $boolStr=[0=>'FALSE',1=>'TRUE'];
    
    public function __construct($oc){
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
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return ['Category'=>'Admin','Emoji'=>'==','Label'=>'Testing','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            $html='';
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Test configuration setting','generic',[],['method'=>'getTestSettingsHtml','classWithNamespace'=>__CLASS__],['style'=>['background-color'=>'#c9ffc9']]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Testing result','generic',[],['method'=>'getTestHtml','classWithNamespace'=>__CLASS__],[]);
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
        $arr['selector']['Folder']=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId();
        $arr['selector']['Name']=$name;
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($arr['selector'],'ALL_R','ALL_CONTENTADMIN_R');
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],['Group','Folder','Name'],0);
		return $arr;
	}

    private function getParamsId(string $class, string $method):string
    {
        return md5($class.'|'.$method);
    }

    private function testParams($arr){
        // get entry
        $arr=$this->finalizeSelector($arr,__FUNCTION__,'settings');
		$arr['selector']['Content']=[];
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        $elementId=key($formData['val']);
        if (isset($formData['val'][$elementId])){
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        }
        // get options
        $methods=[];
        $classes=array_keys($this->oc);
        $classes=array_combine($classes,$classes);
        ksort($classes);
        if (!empty($arr['selector']['Content']['class'])){
            if (class_exists($arr['selector']['Content']['class'])){
                $methods=get_class_methods($arr['selector']['Content']['class']);
                $methods=array_combine($methods,$methods);
                ksort($methods);
            }
        }
        $contentStructure=[
            'class'=>['method'=>'select','value'=>'','options'=>$classes,'excontainer'=>TRUE],
            'method'=>['method'=>'select','value'=>'','options'=>$methods,'excontainer'=>TRUE],
        ];
        // get HTML
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Select method to test';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){
            $row['trStyle']=['background-color'=>'#a00'];
        }
        $matrix=['Parameter'=>$row];
        $arr[__FUNCTION__]=$arr['selector']['Content'];
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
        return $arr;
    }

    private function testArgs($arr){
        // get method properties
        if (empty($arr['testParams']['class']) || empty($arr['testParams']['method'])){return $arr;}
        $testingParamsId=$this->getParamsId($arr['testParams']['class'],$arr['testParams']['method']);
        if (!method_exists($arr['testParams']['class'],$arr['testParams']['method'])){return $arr;}
        // get content structure from args
        $contentStructure=[];
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
            $contentStructure[$param->name]=['method'=>'element','tag'=>'input','value'=>$default,'placeholder'=>$default,'type'=>'text','excontainer'=>TRUE];
            $contentStructure[$param->name.' type ']=['method'=>'select','value'=>$dataType,'options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE,'excontainer'=>TRUE];
        }
        //
        $arr=$this->finalizeSelector(['html'=>$arr['html']],__FUNCTION__,$testingParamsId);
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
        $results=[];
        if (isset($formData['cmd']['array'])){
            $results=$this->runTest($arr,0);
        } else if (isset($formData['cmd']['json'])){
            $results=$this->runTest($arr,1);
        } else if (isset($formData['cmd']['html'])){
            $results=$this->runTest($arr,2);
        }
        // test control form
        $btnArr=['tag'=>'input','type'=>'submit','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
        $cntrMatrix=[];
        $btnArr['value']='Run (array -> table)';
        $btnArr['key']=['array'];
        $cntrMatrix['Commands']['Run']=$btnArr;
        $btnArr['value']='Run (array -> json';
        $btnArr['key']=['json'];
        $cntrMatrix['Commands']['JSON']=$btnArr;
        $btnArr['value']='Run (array -> html';
        $btnArr['key']=['html'];
        $cntrMatrix['Commands']['html']=$btnArr;
        // build html  
        $arr['html']='';
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$cntrMatrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Test']);
        foreach($results as $caption=>$resultMatrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$resultMatrix,'hideHeader'=>FALSE,'hideKeys'=>TRUE,'thKeepCase'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$caption]);
        }
        return $arr;
    }
    
    private function runTest(array $arr,int $outputFormat=0):array
    {
        $results=[];
        // load configuration
        $args=[];
        $config=[];
        // get params
        $params=$this->finalizeSelector([],'testParams','settings');
        $params=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($params['selector'],TRUE);
        $config['params']=$params['Content'];
        // get args
        $testingParamsId=$this->getParamsId($config['params']['class'],$config['params']['method']);
        $tests=$this->finalizeSelector([],'testArgs',$testingParamsId);
        $tests=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($tests['selector'],['Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE]);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($tests,TRUE,'Read','EntryId',TRUE) as $argsEntry){
            $testIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($argsEntry['EntryId']);
            $config['tests'][$testIndex]=[];
            while ($argsEntry['Content']){
                $argName=key($argsEntry['Content']);
                $argValue=array_shift($argsEntry['Content']);
                $argType=array_shift($argsEntry['Content']);
                $config['tests'][$testIndex][$argName]=['name'=>$argName,'value'=>$argValue,'type'=>$argType];
            }
        }
        // testing
        $results['Result']=[];
        foreach($config['tests'] as $testIndex=>$args){
            $valueArr=$this->testArgs2valueArr($args);
            $context=$this->oc['SourcePot\Datapool\Foundation\Logger']->methodTest($this->oc[$config['params']['class']],$config['params']['method'],$valueArr);
            $results=$this->addContext2results($results,$config,$testIndex,$context,$outputFormat);
        }
        return $results;
    }
    
    private function testArgs2valueArr(array $args):array
    {
        $valueArr=[];
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
                $valueArr[]=intval(round(floatval($arrNameValueType['value'])));
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
        $toRemove=['class'=>'class','method'=>'method'];
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
                        $value=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'class'=>'','style'=>['border-left'=>'1px solid #aaa']]);
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
