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
    private $pageSettings=array();
    
    public function __construct($oc){
        $this->oc=$oc;
    }

    public function init($oc){
        $this->oc=$oc;
        $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
    }

    public function getIP($hashOnly=TRUE){
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

    public function href($arr){
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

    public function setPageState($callingClass,$state){
        $_SESSION['page state']['selected'][$callingClass]=$state;
        return $_SESSION['page state']['selected'][$callingClass];
    }

    public function setPageStateByKey($callingClass,$key,$value){
        $_SESSION['page state']['selected'][$callingClass][$key]=$value;
        return $_SESSION['page state']['selected'][$callingClass][$key];
    }

    public function getPageState($callingClass,$initState=array()){
        if (empty($_SESSION['page state']['selected'][$callingClass])){
            $_SESSION['page state']['selected'][$callingClass]=$initState;
        }
        if (method_exists($callingClass,'getEntryTable')){
            $_SESSION['page state']['selected'][$callingClass]['Source']=$this->oc[$callingClass]->getEntryTable();
        } else if (!isset($_SESSION['page state']['selected'][$callingClass]['Source'])){
            $_SESSION['page state']['selected'][$callingClass]['Source']=FALSE;
        }
        return $_SESSION['page state']['selected'][$callingClass];
    }

    public function getPageStateByKey($callingClass,$key,$initValue=FALSE){
        if (!isset($_SESSION['page state']['selected'][$callingClass][$key])){$_SESSION['page state']['selected'][$callingClass][$key]=$initValue;}
        return $_SESSION['page state']['selected'][$callingClass][$key];
    }

    /** HTML request methods, e.g. to be used for REST interface
    *    
    *
    */
    
    public function request($request,$isDebugging=FALSE){
        $requestTemplate=array('method'=>'POST','url'=>'https://ops.epo.org/3.2','resource'=>'auth/accesstoken','query'=>array(),'header'=>array(),'data'=>array(),'options'=>array(),'dataType'=>'application/x-www-form-urlencoded');
        $request=array_merge($requestTemplate,$request);
        $request=$this->requestUrl($request);
        $request=$this->requestHeader($request);
        $request=$this->requestData($request);
        $curl=curl_init();
        curl_setopt($curl,\CURLOPT_HTTPHEADER,$request['header']);
        curl_setopt($curl,\CURLOPT_HEADER,TRUE);
        curl_setopt($curl,\CURLOPT_URL,$request['url']);
        curl_setopt($curl,\CURLOPT_RETURNTRANSFER,TRUE);
        curl_setopt($curl,\CURLOPT_TIMEOUT,10);
        curl_setopt($curl,\CURLOPT_CONNECTTIMEOUT,2);
        foreach($request['options'] as $option=>$value){
            curl_setopt($curl,$option,$value);
        }
        switch(strtoupper($request['method'])){
            case 'GET':
                curl_setopt($curl,\CURLOPT_HTTPGET,TRUE);
                break;
            case 'POST':
                curl_setopt($curl,\CURLOPT_POST,TRUE);
                curl_setopt($curl,\CURLOPT_POSTFIELDS,$request['data']);
                break;
            case 'DELETE':
                curl_setopt($curl,\CURLOPT_CUSTOMREQUEST,'DELETE');
                break;
            default:
                curl_setopt($curl,\CURLOPT_HTTPGET,TRUE);
                break;
        }
        //curl_setopt($curl,\CURLOPT_CAINFO,$certificateFile); <---------------------    
        $response=curl_exec($curl);
        $response=$this->decodeResponse($response);
        if ($isDebugging){$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file(array('request'=>$request,'response'=>$response));}
        return $response;
    }

    private function requestUrl($request,$isDebugging=FALSE){
        $request['url']=trim($request['url'],'/');
        $request['url']=$request['url'].'/'.$request['resource'];
        if (!empty($request['query'])){$request['url']=$request['url'].'?'.http_build_query($request['query']);}
        if ($isDebugging){$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($request);}
        return $request;
    }
    
    private function requestHeader($request,$isDebugging=FALSE){
        $template=array('Accept'=>'application/json','Content-Type'=>'multipart/form-data','Accept-Charset'=>'utf-8');
        $request['header']=array_merge($template,$request['header']);
        if (empty($request['header']['User-agent'])){
            $request['header']['User-agent']=$this->pageSettings['pageTitle'];
        }
        $request['contentType']=$request['header']['Content-Type'];
        $header=array();
        foreach($request['header'] as $key=>$value){
            $header[]=$key.': '.$value;
        }
        $request['header']=$header;
        if ($isDebugging){$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($request);}
        return $request;
    }

    private function requestData($request,$isDebugging=FALSE){
        if (is_string($request['data'])){
            if (is_file($request['data'])){
                $mime=mime_content_type($request['data']);
                if ($mime){$request['data']=['name'=>new \CurlFile($request['data'],$mime,basename($request['data']))];}
            }
        }
        if (strpos($request['dataType'],'json')!==FALSE){
            $request['data']=json_encode($request['data']);
        } else if (strpos($request['dataType'],'urlencoded')!==FALSE){
            $request['data']=http_build_query($request['data']);
        }
        return $request;
    }
    
    private function decodeResponse($response){
        $arr=array('header'=>array(),'data'=>array());
        if (empty($response)){return $arr;}
        $strChnuks=explode("\r\n",$response);
        // get header
        while($strChunk=array_shift($strChnuks)){
            if (empty($strChunk)){break;}
            $keyEndPos=strpos($strChunk,':');
            if ($keyEndPos===FALSE){
                $key=$strChunk;
                $value=TRUE;
            } else {
                $key=substr($strChunk,0,$keyEndPos);
                $value=trim(substr($strChunk,$keyEndPos+1));
            }
            $arr['header'][strtolower($key)]=$value;
        }
        // get data
        if (isset($arr['header']['content-type'])){
            $contentType=$arr['header']['content-type'];
        } else {
            $contentType='string';
        }
        foreach($strChnuks as $strChunk){
            if (strpos($contentType,'json')!==FALSE){
                $tmpArr=json_decode($strChunk,TRUE);
                if ($tmpArr){$arr['data']=array_replace_recursive($arr['data'],$tmpArr);}
            } else if (strpos($contentType,'xml')!==FALSE){
                $tmpArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->xml2arr($strChunk);
                if ($tmpArr){$arr['data']=array_replace_recursive($arr['data'],$tmpArr);}
            } else {
                parse_str($strChunk,$arr['data']);
            }
        }
        return $arr;
    }
    
    public function answer($header,$data,$dataType='application/json',$charset='UTF-8'){
        if (strpos($dataType,'json')>0){
            $data=json_encode($data);
        } else if (strpos($dataType,'xml')>0){
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
                              'X-API'=>$this->pageSettings['pageTitle']
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