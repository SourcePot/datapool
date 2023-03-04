<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace Datapool\Tools;

class NetworkTools{
	
	private $arr;
	private $pageSettings=array();
	
	public function __construct($arr){
		$this->arr=$arr;
	}

	public function init($arr){
		$this->arr=$arr;
		$this->pageSettings=$this->arr['Datapool\Tools\HTMLbuilder']->getSettings();
		return $this->arr;
	}
		
	public function resetSession(){
		$_SESSION=array('page state'=>$_SESSION['page state']);
		$this->arr['Datapool\Tools\FileTools']->removeTmpDir();
		session_regenerate_id(TRUE);
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

	public function mergePageState($callingClass,$state){
		if (isset($_SESSION['page state']['selected'][$callingClass])){
			$_SESSION['page state']['selected'][$callingClass]=array_merge(	$_SESSION['page state']['selected'][$callingClass],$state);	
		} else {
			$_SESSION['page state']['selected'][$callingClass]=$state;
		}
		return $_SESSION['page state']['selected'][$callingClass];
	}

	public function setPageStateByKey($callingClass,$key,$value){
		$_SESSION['page state']['selected'][$callingClass][$key]=$value;
		return $_SESSION['page state']['selected'][$callingClass];
	}

	public function getPageState($callingClass,$initState=array()){
		if (!is_array($initState)){
			throw new \ErrorException('Function '.__FUNCTION__.': initState must be array-type.',0,E_ERROR,__FILE__,__LINE__);
		}
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
		if ($isDebugging){$this->arr['Datapool\Tools\ArrTools']->arr2file($requestArr);}
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
		if ($isDebugging){$this->arr['Datapool\Tools\ArrTools']->arr2file($requestArr);}
		return $requestArr;
	}
	
	private function requestDecodeResponse($requestArr){
		foreach($requestArr['response'] as $index=>$response){
			$json=$this->arr['Datapool\Tools\ArrTools']->json2arr($response);
			if (stripos(trim($response),'<?xml ')===0){
				// is xml encoded
				$requestArr['response'][$index]=$this->arr['Datapool\Tools\XMLtools']->xml2arr($response);
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
			if (isset($this->arr['Datapool\Foundation\Logging'])){
				$logArr=array('msg'=>'CURL error '.curl_error($curl).' '.curl_errno($curl),'priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
				$this->arr['Datapool\Foundation\Logging']->addLog($logArr);
			}
			$requestArr['response']=array('error'=>curl_error($curl),'no'=>curl_errno($curl));
		} else {
			$requestArr['response']=explode("\r\n\r\n",$response);
			$requestArr=$this->requestDecodeResponse($requestArr);
			$requestArr['response']['status']=(int)curl_getinfo($curl,\CURLINFO_HTTP_CODE);
			curl_close($curl);
		}
		if ($isDebugging){$this->arr['Datapool\Tools\ArrTools']->arr2file($requestArr);}
		return $requestArr;
	}
	
	/** simple mailer
	*	
	*
	*/
	
	public function entry2mail($mail){
		// This methode converts an entry to an emial address, the $mail-keys are:
		// 'selector' ... selects the entry
		// 'To' ... is the recipients emal address, use array for multiple addressees
		$mail['selector']=$this->arr['Datapool\Foundation\Database']->entryByKey($mail['selector'],TRUE);
		if (empty($mail['selector'])){
			$logArr=array('msg'=>'No email sent. Could not find the selected entry or no read access for the selected entry','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
			$this->arr['Datapool\Foundation\Logging']->addLog($logArr);	
		} else {
			if (!empty($mail['selector']['Content']['To']) && empty($mail['To'])){
				$mail['To']=$mail['selector']['Content']['To'];
				unset($mail['selector']['Content']['To']);
			}
			if (!empty($mail['selector']['Content']['From']) && empty($mail['From'])){
				$mail['From']=$mail['selector']['Content']['From'];
				unset($mail['selector']['Content']['From']);
			}
			if (!empty($mail['selector']['Content']['Subject']) && empty($mail['Subject'])){
				$mail['Subject']=$mail['selector']['Content']['Subject'];
				unset($mail['selector']['Content']['Subject']);
			}
			if (empty($mail['Subject'])){$mail['Subject']=$mail['selector']['Name'];}	
			// get message parts
			$flatContent=$this->arr['Datapool\Tools\ArrTools']->arr2flat($mail['selector']['Content']);
			$msgTextPlain='';
			$msgTextHtml='';
			foreach($flatContent as $flatContentKey=>$flatContentValue){
				$flatContentValue=trim($flatContentValue);
				if (strpos($flatContentValue,'{{')===0){
					continue;
				} else if (strpos($flatContentValue,'<')!==0){
					$flatContentValue='<p>'.$flatContentValue.'</p>';
				}
				$msgTextPlain=strip_tags($flatContentValue)."\r\n";
				$msgTextHtml.=$flatContentValue;
			}
			// create text part of the message
			$textBoundery='text-'.md5($mail['selector']['ElementId']);
			$message='';
			$msgPrefix="Content-Type: multipart/alternative; boundary=\"".$textBoundery."\"\r\n";
			$message.="\r\n\r\n--".$textBoundery."\r\n";
			$message.="Content-Type: text/plain; charset=UTF-8\r\n\r\n";
			$message.=chunk_split($msgTextPlain);
			$message.="\r\n--".$textBoundery."\r\n";
			$message.="Content-Type: text/html; charset=UTF-8\n";
			$message.="Content-Transfer-Encoding: quoted-printable\r\n\r\n";
			$message.=chunk_split($msgTextHtml);
			$message.="\r\n\r\n--".$textBoundery."--\r\n";
			// get attched file			
			$mixedBoundery='multipart-'.md5($mail['selector']['ElementId']);
			$file=$this->arr['Datapool\Tools\FileTools']->selector2file($mail['selector']);
			if (is_file($file)){
				$msgPrefix='--'.$mixedBoundery."\r\n".$msgPrefix;
				// get file content
				$msgFile=file_get_contents($file);
				$msgFile=base64_encode($msgFile);
				// attach to message
				$message.="\r\n\r\n--".$mixedBoundery."\r\n";
				$message.="Content-Type: ".mime_content_type($file)."; name=\"".$mail['selector']['Params']['File']['Name']."\"\n";
				$message.="Content-Transfer-Encoding: base64\n";
				$message.="Content-Disposition: attachment; filename=\"".$mail['selector']['Params']['File']['Name']."\"\r\n\r\n";
				$message.=chunk_split($msgFile);
				$message.="\r\n\r\n--".$mixedBoundery."--\r\n";
				$message=$msgPrefix.$message;
				$header=array('Content-Type'=>"multipart/mixed; boundary=\"".$mixedBoundery."\"");
			} else {
				$header=array('Content-Type'=>"multipart/alternative; boundary=\"".$textBoundery."\"");
			}
			$mail['message']=$message;
			// add headers
			if (empty($mail['From'])){
				$header['From']=$this->pageSettings['emailWebmaster'];
			} else {
				$header['From']=$mail['From'];
			}
			if (empty($mail['To']) || empty($mail['Subject'])){
				$logArr=array('msg'=>'On of the following was empty: "To" or "Subject". The email was not sent.','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
				$this->arr['Datapool\Foundation\Logging']->addLog($logArr);
				return FALSE;
			} else {
				$header['MIMI-Version']='1.0';
				$mail['To']=addcslashes(mb_encode_mimeheader($mail['To'],"UTF-8"),'"');
				$mail['Subject']=addcslashes(mb_encode_mimeheader($mail['Subject'],"UTF-8"),'"');
				$header['From']=addcslashes(mb_encode_mimeheader($header['From'],"UTF-8"),'"');
				@mail($mail['To'],$mail['Subject'],$mail['message'],$header);
				return TRUE;
			}
		}
		return FALSE;
	}

}
?>