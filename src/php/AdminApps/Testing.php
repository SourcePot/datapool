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
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Test','generic',array(),array('method'=>'getTestSettingsHtml','classWithNamespace'=>__CLASS__),array());
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }

    public function getTestSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr=$this->testParams($arr);
        $arr=$this->testArgs($arr);
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
            $row['setRowStyle']='background-color:#a00;';
        }
        $matrix=array('Parameter'=>$row);
        $arr[__FUNCTION__]=$arr['selector']['Content'];
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
        return $arr;
    }

    private function testArgs($arr){
        // get method properties
        if (empty($arr['testParams']['class']) || empty($arr['testParams']['method'])){return $arr;}
        $testingParamsId=md5($arr['testParams']['class'].'|'.$arr['testParams']['method']);
        if (!method_exists($arr['testParams']['class'],$arr['testParams']['method'])){return $arr;}
        // get content structure from args
        $contentStructure=array();
        $f=new \ReflectionMethod($arr['testParams']['class'],$arr['testParams']['method']);
        foreach($f->getParameters() as $pIndex=>$param){
            $key=$param->name.' ['.$param->getType().']';
            try{
                $default=$param->getDefaultValue();
            } catch (\Exception $e){
                $default='';
            }
            if (is_array($default)){
                $default=json_encode($default);
            } else if (is_bool($default)){
                
            }
            $contentStructure[$key]=array('method'=>'element','tag'=>'input','value'=>$default,'placeholder'=>$default,'type'=>'text','excontainer'=>TRUE);
        }
        //
        $arr=$this->finalizeSelector(array('html'=>$arr['html']),__FUNCTION__,$testingParamsId);
        $arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Arguments';
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $arr;
	}
    
    private function runTest()
    {
        
        //$this->oc['SourcePot\Datapool\Foundation\Logger']->methodTest($oc['SourcePot\Datapool\Tools\MiscTools'],'str2float',array('A 1,234','de'));
    
    }
    
}
?>