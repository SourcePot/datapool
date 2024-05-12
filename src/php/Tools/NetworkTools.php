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

class NetworkTools{
    
    private $oc;
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    public function init(array $oc)
    {
        $this->oc=$oc;
    }

    public function getIP(bool $hashOnly=TRUE):string
    {
        if (array_key_exists('HTTP_X_FORWARDED_FOR',$_SERVER)){
            $ip=$_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (array_key_exists('REMOTE_ADDR',$_SERVER)){
            $ip=$_SERVER["REMOTE_ADDR"];
        } else if (array_key_exists('HTTP_CLIENT_IP',$_SERVER)){
            $ip=$_SERVER["HTTP_CLIENT_IP"];
        }
        if (empty($ip)){
            return 'empty';
        } else if ($hashOnly){
            $ip=md5($ip);
        }
        return $ip;
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
        if (method_exists($classWithNamespace,'run')){
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

    public function getPageState(string $callingClass,$initState=array())
    {
        if (empty($_SESSION['page state']['selected'][$callingClass])){
            $_SESSION['page state']['selected'][$callingClass]=$initState;
        }
        if (method_exists($callingClass,'getEntryTable') && empty(\SourcePot\Datapool\Root::ALLOW_SOURCE_SELECTION[$callingClass])){
            // set Source selector to database table relevant for calling class
            $_SESSION['page state']['selected'][$callingClass]['Source']=$this->oc[$callingClass]->getEntryTable();
        } else if (!isset($_SESSION['page state']['selected'][$callingClass]['Source'])){
            // 
            $_SESSION['page state']['selected'][$callingClass]['Source']=FALSE;
        }
        $_SESSION['page state']['selected'][$callingClass]['app']=$callingClass;
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
    
}
?>