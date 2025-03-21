<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Tools;

class NetworkTools implements \SourcePot\Datapool\Interfaces\Receiver{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    
    public function __construct(array $oc)
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

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
 
    public function href(array $arr):string
    {
        $script=$_SERVER['SCRIPT_NAME'];
        $suffix='';
        foreach($arr as $key=>$value){
            $key=urlencode($key);
            $value=urlencode($value);
            if (empty($suffix)){$suffix.='?';} else {$suffix.='&';}
            $suffix.=$key.'='.$value;
        }
        return $script.$suffix;
    }
    
    public function selector2class(array $selector):string
    {
        if (empty($selector['app'])){
            $classWithNamespace=$this->oc['SourcePot\Datapool\Root']->source2class($selector['Source']);
        } else {
            $classWithNamespace=$selector['app'];
        }
        return $classWithNamespace;
    }
    
    public function setPageStateBySelector(array $selector)
    {
        $classWithNamespace=$this->selector2class($selector);
        // switch app based on on selected entry, but not if it is a DataApps
        if (method_exists($classWithNamespace,'run') && strpos($classWithNamespace,'DataApps')===FALSE){
            $_SESSION['page state']['app']['Class']=$classWithNamespace;
        }
        return $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageState($classWithNamespace,$selector);
    }
    
    public function setPageState(string $callingClass,$state)
    {
        $_SESSION['page state']['selected'][$callingClass]=$state;
        $_SESSION['page state']['selected'][$callingClass]['app']=$callingClass;
        return $_SESSION['page state']['selected'][$callingClass];
    }

    public function setPageStateByKey(string $callingClass,$key,$value)
    {
        $_SESSION['page state']['selected'][$callingClass][$key]=$value;
        return $_SESSION['page state']['selected'][$callingClass][$key];
    }

    public function getPageState(string $callingClass,$initState=[])
    {
        if (method_exists($callingClass,'getEntryTable') && empty(\SourcePot\Datapool\Root::ALLOW_SOURCE_SELECTION[$callingClass])){
            // set Source based on relevant database table with regard to the calling class
            $_SESSION['page state']['selected'][$callingClass]['Source']=$this->oc[$callingClass]->getEntryTable();
        }
        $_SESSION['page state']['selected'][$callingClass]['app']=$callingClass;
        // add init state
        $initState['Source']=(isset($initState['Source']))?$initState['Source']:FALSE;
        $_SESSION['page state']['selected'][$callingClass]=array_merge($initState,$_SESSION['page state']['selected'][$callingClass]);
        return $_SESSION['page state']['selected'][$callingClass];
    }

    public function getPageStateByKey(string $callingClass,$key,$initValue=FALSE)
    {
        if (!isset($_SESSION['page state']['selected'][$callingClass][$key])){
            $_SESSION['page state']['selected'][$callingClass][$key]=$initValue;
        }
        return $_SESSION['page state']['selected'][$callingClass][$key];
    }
    
    public function setEditMode(array $selector,bool $isEditMode=FALSE):string
    {
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($selector,array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE));
        $id=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE);
        $_SESSION['page state']['isEditMode'][$id]=$isEditMode;
        return $id;
    }
    
    public function getEditMode(array $selector):bool
    {
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($selector,array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE));
        $id=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE);
        if (isset($_SESSION['page state']['isEditMode'][$id])){
            return $_SESSION['page state']['isEditMode'][$id];
        } else {
            return FALSE;
        }
    }

    public function answer(array $header,array $data,string $dataType='application/json',string $charset='UTF-8')
    {
        if (mb_strpos($dataType,'json')>0){
            $data=json_encode($data);
        } else if (mb_strpos($dataType,'xml')>0){
            $data=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2xml($data);
        }
        $headerTemplate=array(''=>'HTTP/1.1 200 OK',
                              'Access-Control-Allow-Credentials'=>'true',
                              'Access-Control-Allow-Headers'=>'Authorization',
                              'Access-Control-Allow-Methods'=>'POST',
                              'Access-Control-Allow-Origin'=>'*',
                              'Cache-Control'=>'no-cache,must-revalidate',
                              'Expires'=>'Sat, 26 Jul 1997 05:00:00 GMT',
                              'Connection'=>'keep-alive',
                              'Content-Language'=>'en',
                              'Content-Type'=>$dataType.';charset='.$charset,
                              'Content-Length'=>mb_strlen($data,$charset),
                              'Strict-Transport-Security'=>'max-age=31536000;includeSubDomains',
                              'X-API'=>$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle')
                              );
        $header=array_merge($headerTemplate,$header);
        foreach($header as $key=>$value){
            if (empty($key)){
                $header=$value;
            } else {
                $header=$key.': '.$value;
            }
            header($header);
        }
        echo $data;
    }

    /******************************************************************************************************************************************
    * DATASOURCE: Network receiver
    *
    * 'EntryId' ... arr-property selects the inbox
    * 
    */
    
    public function receive(string $id):array
    {
        $params=$this->getParams($id);
        $canvasElement=$this->id2canvasElement($id);
        // create entries
        $entryTemplate=$this->receiverSelector($id);
        
        $result=array('Pages scanned'=>0,);
        
        return $result;
    }
    
    public function receiverPluginHtml(array $arr):string
    {
        // get settings html
        $contentStructure=array('A'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'\w+','excontainer'=>TRUE),
                                'B'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'\w+','excontainer'=>TRUE),
                                'Keep source entries'=>array('method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>array(0=>'No, move entries',1=>'Yes, copy entries')),
                                );
        // get selctor
        $callingElementEntryId=$arr['selector']['EntryId'];
        $callingElement=array('Folder'=>'Settings','EntryId'=>$callingElementEntryId);
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        $elementId=key($formData['val']);
        if (isset($formData['cmd'][$elementId])){
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        }
        // get HTML
        $arr['canvasCallingClass']=$arr['selector']['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Network receiver parameter';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        if (empty($arr['selector']['Content'])){$row['trStyle']=array('background-color'=>'#a00');}
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>array('Parameter'=>$row),'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
        // settings dependend html
        $html.='Nothing here yet...';
        return $html;
    }

    public function receiverSelector(string $id):array
    {
        $Group='INBOX|'.preg_replace('/\W/','_',$id);
        return array('Source'=>$this->entryTable,'Group'=>$Group);
    }    

    private function getParams(string $id):array
    {
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,'receiverPluginHtml',array('Folder'=>'Settings','EntryId'=>$id),TRUE);
        $paramsEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector'],TRUE);
        if (isset($paramsEntry['Content'])){return $paramsEntry['Content'];} else {return [];}
    }

    private function id2canvasElement($id):array
    {
        $canvasElement=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$id);
        return $this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElement,TRUE);
    }

}
?>