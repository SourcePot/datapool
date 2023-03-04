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

class XMLtools{
	
	private $arr;
    
	public function __construct($arr){
		$this->arr=$arr;
	}
	
	public function init($arr){
		$this->arr=$arr;
		return $this->arr;
	}
	
	public function arr2style($arr){
		$style='';
		foreach($arr as $property=>$value){
			$property=strtolower($property);
			if (strpos($property,'height')!==FALSE || strpos($property,'width')!==FALSE || strpos($property,'size')!==FALSE || strpos($property,'top')!==FALSE || strpos($property,'left')!==FALSE || strpos($property,'bottom')!==FALSE || strpos($property,'right')!==FALSE){
				if (is_numeric($value)){$value=strval($value).'px';} else {$value=strval($value);}
			}
			$style.=$property.':'.$value.';';
		}
		return $style;
	}
	
	public function style2arr($style){
		$arr=array();
		$styleChunks=explode(';',$style);
		while($styleChunk=array_shift($styleChunks)){
			$styleDef=explode(':',$styleChunk);
			if (count($styleDef)!==2){continue;}
			$arr[$styleDef[0]]=$styleDef[1];
		}
		return $arr;
	}
	
	public function xml2arr($xml){
		if (extension_loaded('SimpleXML')){
			$xml=simplexml_load_string($xml);
			$json=json_encode($xml);
			$arr=json_decode($json,TRUE);	
		} else {
			$logArr=array('msg'=>'PHP extension SimpleXML missing.','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
			$arr['Datapool\Foundation\Logging']->addLog($logArr);
			$arr=array('xml'=>$xml);
		}
		return $arr;
	}
	
	public function containsTags($str){
		if (strlen($str)===strlen(strip_tags($str))){return FALSE;} else {return TRUE;}
	}
	
	public function wrapUTF8($str){
		preg_match_all("/[\x{1f000}-\x{1ffff}]/u",$str,$matches);
		foreach($matches[0] as $matchIndex=>$match){
			$str=str_replace($match,'<span style="font-size:1.5em;">'.$match.'</span>',$str);
		}
		return $str;
	}
}
?>