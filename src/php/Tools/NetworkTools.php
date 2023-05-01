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
	
	private $arr;
	private $pageSettings=array();
	
	public function __construct($arr){
		$this->arr=$arr;
	}

	public function init($arr){
		$this->arr=$arr;
		$this->pageSettings=$this->arr['SourcePot\Datapool\Foundation\Backbone']->getSettings();
		return $this->arr;
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
		if (empty($_SESSION['page state']['selected'][$callingClass])){$_SESSION['page state']['selected'][$callingClass]=$initState;}
		if (method_exists($callingClass,'getEntryTable')){
			$_SESSION['page state']['selected'][$callingClass]['Source']=$this->arr[$callingClass]->getEntryTable();
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
	
	private function requestUrl($requestArr,$isDebugging=FALSE){
		$requestArr['url']=trim($requestArr['url'],'/');
		$requestArr['url']=$requestArr['url'].'/'.$requestArr['resource'];
		if (!empty($requestArr['query'])){$requestArr['url']=$requestArr['url'].'?'.http_build_query($requestArr['query']);}
		if ($isDebugging){$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($requestArr);}
		return $requestArr;
	}
	
	private function requestHeader($requestArr,$isDebugging=FALSE){
		//$template=array('Accept'=>'application/json','Content-Type'=>'multipart/form-data','Accept-Charset'=>'utf-8','Authorization'=>'AccessKey ...');
		$template=array('Accept'=>'application/json','Content-Type'=>'multipart/form-data','Accept-Charset'=>'utf-8');
		$requestArr['header']=array_merge($template,$requestArr['header']);
		if (empty($requestArr['header']['User-agent'])){$requestArr['header']['User-agent']=$this->pageSettings['pageTitle'];}
		$requestArr['contentType']=$requestArr['header']['Content-Type'];
		$header=array();
		foreach($requestArr['header'] as $key=>$value){
			$header[]=$key.': '.$value;
		}
		$requestArr['header']=$header;
		if ($isDebugging){$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($requestArr);}
		return $requestArr;
	}
	
	private function requestDecodeResponse($requestArr){
		foreach($requestArr['response'] as $index=>$response){
			$json=$this->arr['SourcePot\Datapool\Tools\MiscTools']->json2arr($response);
			if (stripos(trim($response),'<?xml ')===0){
				// is xml encoded
				$requestArr['response'][$index]=$this->arr['SourcePot\Datapool\Tools\MiscTools']->xml2arr($response);
			} else if (!empty($json)){
				// json encoded
				$requestArr['response'][$index]=$json;
			} else {
				// text
				$lines=explode("\r\n",$response);
				if (count($lines)>1){
					$requestArr['response'][$index]=array();
					foreach($lines as $line){
						$keyValue=explode(':',$line);
						(isset($keyValue[1]))?$requestArr['response'][$index][$keyValue[0]]=trim($keyValue[1]):$requestArr['response'][$index][]=trim($keyValue[0]);
					}
				}
			} 
		}
		return $requestArr;
	}
	
	public function performRequest($method='GET',$url="https://rest.messagebird.com/",$resource='balance',$query=array(),$header=array(),$data=array(),$options=array(),$isDebugging=FALSE){
		$requestArr=array('method'=>$method,'url'=>$url,'resource'=>$resource,'query'=>$query,'header'=>$header,'data'=>$data);
		$requestArr=$this->requestUrl($requestArr);
		$requestArr=$this->requestHeader($requestArr);
		// post data encoding
		if (!is_array($requestArr['data'])){
			if (is_file($requestArr['data'])){
				$mime=mime_content_type($requestArr['data']);
				if ($mime){$data=['name'=>new \CurlFile($requestArr['data'],$mime,basename($data))];}
			}
		} else if (stripos($requestArr['contentType'],'json')!==FALSE){
			$requestArr['data']=json_encode($requestArr['data']);
		}
		// curl processing
		$curl=curl_init();
		curl_setopt($curl,\CURLOPT_HTTPHEADER,$requestArr['header']);
        curl_setopt($curl,\CURLOPT_HEADER,TRUE);
        curl_setopt($curl,\CURLOPT_URL,$requestArr['url']);
        curl_setopt($curl,\CURLOPT_RETURNTRANSFER,TRUE);
        curl_setopt($curl,\CURLOPT_TIMEOUT,10);
        curl_setopt($curl,\CURLOPT_CONNECTTIMEOUT,2);
        foreach($options as $option=>$value){curl_setopt($curl,$option,$value);}
		switch(strtoupper($method)){
			case 'GET':
				curl_setopt($curl,\CURLOPT_HTTPGET,TRUE);
				break;
			case 'POST':
				curl_setopt($curl,\CURLOPT_POST,TRUE);
				curl_setopt($curl,\CURLOPT_POSTFIELDS,$data);
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
		if ($response===false){
			if (isset($this->arr['SourcePot\Datapool\Foundation\Logging'])){
				$logArr=array('msg'=>'CURL error '.curl_error($curl).' '.curl_errno($curl),'priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
				$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog($logArr);
			}
			$requestArr['response']=array('error'=>curl_error($curl),'no'=>curl_errno($curl));
		} else {
			$requestArr['response']=explode("\r\n\r\n",$response);
			$requestArr=$this->requestDecodeResponse($requestArr);
			$requestArr['response']['status']=(int)curl_getinfo($curl,\CURLINFO_HTTP_CODE);
			curl_close($curl);
		}
		if ($isDebugging){$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($requestArr);}
		return $requestArr;
	}

}
?>