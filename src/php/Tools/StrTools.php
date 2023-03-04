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

class StrTools{
	
	private $arr;
	
	public $emojis=array();
	
	public function __construct($arr){
		$this->arr=$arr;
	}

	public function init($arr){
		$this->arr=$arr;
		$this->loadEmojis();
		return $this->arr;
	}
	
	public function stdReplacements($str=''){
		if (is_array($str)){return $str;}
		$pageSettings=$this->arr['Datapool\Tools\HTMLbuilder']->getSettings();
		$toReplace['{{NOW}}']=$this->getDateTime('now');
		$toReplace['{{YESTERDAY}}']=$this->getDateTime('yesterday');
		$toReplace['{{TOMORROW}}']=$this->getDateTime('tomorrow');
		$toReplace['{{TIMEZONE-SERVER}}']=date_default_timezone_get();
		$toReplace['{{Expires}}']=$this->getDateTime('now','PT10M');
		$toReplace['{{ElementId}}']=$this->getElementId();
		if (!isset($_SESSION['currentUser']['ElementId'])){
			$toReplace['{{Owner}}']='SYSTEM';
		} else if (strpos($_SESSION['currentUser']['ElementId'],'EID')===FALSE){
			$toReplace['{{Owner}}']=$_SESSION['currentUser']['ElementId'];
		} else {
			$toReplace['{{Owner}}']='ANONYM';
		}
		$toReplace['{{pageTitle}}']=$pageSettings['pageTitle'];
		$toReplace['{{pageTimeZone}}']=$pageSettings['pageTimeZone'];
		//
		if (is_array($str)){
			throw new \ErrorException('Function '.__FUNCTION__.' called with argument str of type array.',0,E_ERROR,__FILE__,__LINE__);	
		} else if (is_string($str)){
			foreach($toReplace as $needle=>$replacement){
				$str=str_replace($needle,$replacement,$str);
			}
		}
		//$this->arr['Datapool\Tools\ArrTools']->arr2file($toReplace);
		return $str;
	}
	
	public function getRandomString($length){
		$hash='';
		$byteStr=random_bytes($length);
		for ($i=0;$i<$length;$i++){
			$byte=ord($byteStr[$i]);
			if ($byte>180){
				$hash.=chr(97+($byte%26));
			} else if ($byte>75){
				$hash.=chr(65+($byte%26));
			} else {
				$hash.=chr(48+($byte%10));
			}
		}
		return $hash;
	}

	public function getHash($arr,$short=FALSE){
		if (is_array($arr)){$hash=json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_IGNORE);} else {$hash=strval($arr);}
		$hash=hash('sha256',$hash);
		if (!empty($short)){
			// short hash
			$hash=base_convert($hash,16,32);
			$hash=str_replace('0','',$hash);
			$hash=str_replace('|','x',$hash);
		}
		return $hash;
	}	
	
	public function getContentId($text,$context=''){
		return $GLOBALS['toolsobj']->getHash($text.$context);
	}
	
	public function getElementId($base=FALSE,$timestamp=FALSE){
		//	Creates and returns the unique ElementId
		if ($base){$suffix=$this->getHash($base,TRUE);} else {$suffix=mt_rand(100000,999999);}
		if ($timestamp===FALSE){
			$timestamp=time();
		} else {
			$timestamp=$timestamp;
		}
		$elementId="EID".$timestamp.'-'.$suffix."eid";
		return $elementId;
	}
	
	public function getElementIdAge($elementId){
		// Returns the age of a provided ElementId
		if (strpos($elementId,'eid')===FALSE || strpos($elementId,'EID')===FALSE){return 0;}
		$timestamp=substr($elementId,3,strpos($elementId,'-')-1);
		$timestamp=intval($timestamp);
		return time()-$timestamp;
	}
	
	public function addElementId($entry,$relevantKeys=array('Source','Group','Folder','Name','Type'),$timestampToUse=FALSE,$suffix='',$keepExistingElementId=FALSE){
		if (!empty($entry['ElementId']) && $keepExistingElementId){return $entry;}
		$base=array();
		foreach($relevantKeys as $keyIindex=>$relevantKey){
			if (isset($entry[$relevantKey])){$base[]=$entry[$relevantKey];}
		}
		if ($timestampToUse===FALSE){
			if (empty($entry['Date'])){
				$timestamp=time();
			} else {
				$timestamp=strtotime($entry['Date']);
			}
		} else {
			$timestamp=$timestampToUse;
		}
		$entry['ElementId']=$this->getElementId($base,$timestamp);
		if (!empty($suffix)){$entry['ElementId'].=$suffix;}
		return $entry;
	}

	public function getDateTime($datetime='now',$addDateInterval=FALSE,$usePageStateTimezone=FALSE){
		// This is the standard method to get a formated date-string.
		// It returns the date based on the selected timezone.
		$dateTime=new \DateTime($datetime);
		if (!empty($addDateInterval)){
			$dateTime->add(new \DateInterval($addDateInterval));
		}
		if (empty($usePageStateTimezone)){
			$timezone=date_default_timezone_get();
		} else {
			$timezone=$_SESSION['page state']['timezone'];
		}
		$dateTime->setTimezone(new \DateTimeZone($timezone));
		return $dateTime->format('Y-m-d H:i:s');
	}

	public function template2string($template='Hello [p:{{key}}]...',$arr=array('key'=>'world'),$element=array()){
		$flatArr=$this->arr['Datapool\Tools\ArrTools']->arr2flat($arr);
		foreach($flatArr as $flatArrKey=>$flatArrValue){
			$template=str_replace('{{'.$flatArrKey.'}}',(string)$flatArrValue,$template);
		}
		$template=preg_replace('/{{[^{}]+}}/','',$template);
		preg_match_all('/(\[\w+:)([^\]]+)(\])/',$template,$matches);
		if (isset($matches[0][0])){
			foreach($matches[0] as $matchIndex=>$match){
				$element['tag']=trim($matches[1][$matchIndex],'[:');
				$element['element-content']=$matches[2][$matchIndex];
				$replacement=$this->arr['Datapool\Tools\HTMLbuilder']->element($element);
				$template=str_replace($match,$replacement,$template);
			}
		}
		return $template;
	}
	
	public function code2utf($code){
		if($code<128)return chr($code);
		if($code<2048)return chr(($code>>6)+192).chr(($code&63)+128);
		if($code<65536)return chr(($code>>12)+224).chr((($code>>6)&63)+128).chr(($code&63)+128);
		if($code<2097152)return chr(($code>>18)+240).chr((($code>>12)&63)+128).chr((($code>>6)&63)+128) .chr(($code&63)+128);
		return '';
	}
	
	private function emojiList2file($targetFile){
		//$html=file_get_contents('https://unicode.org/emoji/charts/full-emoji-list.html');
		$html=file_get_contents('D:/FullEmojiList.htm');
		if (empty($html)){return FALSE;}
		$result=array();
		$rows=explode('</tr>',$html);
		while($row=array_shift($rows)){
			$startPos=strpos($row,'<tr');
			if ($startPos===FALSE){continue;}
			$row=substr($row,$startPos);
			if (strpos($row,'</th>')===FALSE){
				// is content row
				$cells=explode('</td>',$row);
				foreach($cells as $cellIndex=>$cell){
					if (stripos($cell,'"code"')!==FALSE){
						$cell=strtolower(strip_tags($cell));
						$cell=trim($cell);
						$key2arr=explode(' ',$cell);
					}
					if (stripos($cell,'"name"')!==FALSE){
						foreach($key2arr as $key2index=>$key2){
							$key2=trim($key2,'u+');
							$key2=hexdec($key2);
							$result[$key0][$key1][$key2]=html_entity_decode(trim(strip_tags($cell)));
						}
					}
				}
			} else {
				// is key row
				if (stripos($row,'"bighead"')!==FALSE){
					$key0=html_entity_decode(strip_tags($row));
				} else if (stripos($row,'"mediumhead"')!==FALSE){
					$key1=html_entity_decode(strip_tags($row));	
				}
			}
		}
		$targetFile=$GLOBALS['setup dir'].'emoji.json';
		$this->arr['Datapool\Tools\ArrTools']->arr2file($result,$targetFile);
		return $result;
	}

	private function loadEmojis(){
		$sourceFile=$GLOBALS['setup dir'].'emoji.json';
		if (is_file($sourceFile)){
			$json=file_get_contents($sourceFile);
			$this->emojis=$this->arr['Datapool\Tools\ArrTools']->json2arr($json);
		} else {
			$this->emojis=$this->emojiList2file($sourceFile);
		}
		
	}

	public function float2str($float,$prec=3,$base=1000){
		// Thanks to "c0x at mail dot ru" based on https://www.php.net/manual/en/function.log.php
		$e=array('a','f','p','n','u','m','','k','M','G','T','P','E');
		$p=min(max(floor(log(abs($float), $base)),-6),6);
		return round((float)$float/pow($base,$p),$prec).' '.$e[$p+6];
	}

}
?>