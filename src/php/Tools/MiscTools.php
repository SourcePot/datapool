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

namespace SourcePot\Datapool\Tools;

class MiscTools{
	
	private $currencies=array('US$','€','AFN','EUR','ALL','DZD','USD','AOA','XCD','ARS','AMD','AWG','AUD','AZN','BSD','BHD','BDT','BBD','BYN','BZD','XOF','BMD','BTN',
							  'INR','BOB','BOV','BAM','BWP','NOK','BRL','BND','BGN','BIF','CVE','KHR','XAF','CAD','KYD','CLF','CLP','CNY','COP','COU','KMF','CDF',
							  'NZD','CRC','HRK','CUC','CUP','ANG','CZK','DKK','DJF','DOP','EGP','SVC','ERN','ETB','FKP','FJD','XPF','GMD','GEL','GHS','GIP','GTQ',
							  'GBP','GNF','GYD','HTG','HNL','HKD','HUF','ISK','IDR','XDR','IRR','IQD','ILS','JMD','JPY','JOD','KZT','KES','KPW','KRW','KWD','KGS',
							  'LAK','LBP','LSL','ZAR','LRD','LYD','CHF','MOP','MKD','MGA','MWK','MYR','MVR','MRU','MUR','XUA','MXN','MXV','MDL','MNT','MAD','MZN',
							  'MMK','NAD','NPR','NIO','NGN','OMR','PKR','PAB','PGK','PYG','PEN','PHP','PLN','QAR','RON','RUB','RWF','SHP','WST','STN','SAR','RSD',
							  'SCR','SLE','SGD','XSU','SBD','SOS','SSP','LKR','SDG','SRD','SZL','SEK','CHE','CHW','SYP','TWD','TJS','TZS','THB','TOP','TTD','TND',
							  'TRY','TMT','UGX','UAH','AED','USN','UYI','UYU','UZS','VUV','VEF','VED','VND','YER','ZMW','ZWL');
	
	private $months=array('DE'=>array('01'=>'Januar','02'=>'Februar','03'=>'März','04'=>'April','05'=>'Mai','06'=>'Juni','07'=>'Juli','08'=>'August','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Dezember'),
						  'US'=>array('01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'),
						  'UK'=>array('01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'),
						  'DE short'=>array('01'=>'Jan','02'=>'Feb','03'=>'Mär','04'=>'Apr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Dez'),
						  'US short'=>array('01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Dec'),
						  'UK short'=>array('01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Dec'),
						  );
	
	const ONEDIMSEPARATOR='|[]|';

	public $emojis=array();
	private $emojiFile='';
	
	public function __construct(){
		$this->emojiFile=$GLOBALS['setup dir'].'emoji.json';
		$this->loadEmojis($this->emojiFile);
	}
		
	/******************************************************************************************************************************************
	* XML tools
	*/

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
		$arr=array('xml'=>$xml);
		if (extension_loaded('SimpleXML')){
			$xml=simplexml_load_string($xml);
			$json=json_encode($xml);
			$arr=json_decode($json,TRUE);	
		} else {
			throw new \ErrorException('Function '.__FUNCTION__.': PHP extension SimpleXML missing.',0,E_ERROR,__FILE__,__LINE__);
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
	
	/******************************************************************************************************************************************
	* Number tools
	*/

	public function str2float($string,$falseOnFailure=FALSE){
		if (!is_string($string)){return $string;}
		$string=preg_replace("/[^0-9\.\,\-]/",'',$string);
		$string=trim($string,'.,');
		$dotPos=strpos($string,'.');
		$commaPos=strpos($string,',');
		if ($commaPos!==FALSE && $dotPos===FALSE){
			$string=str_replace(',','|',$string);
		} else if ($commaPos===FALSE && $dotPos!==FALSE){
			$string=str_replace('.','|',$string);
		} else if ($commaPos!==FALSE && $dotPos!==FALSE){
			if ($dotPos<$commaPos){
				$string=str_replace('.','',$string);
				$string=str_replace(',','|',$string);
			} else {
				$string=str_replace('.','|',$string);
				$string=str_replace(',','',$string);
			}
		}
		$string=str_replace('|','.',$string);
		if (empty($string) && $falseOnFailure){return FALSE;}
		$float=floatval($string);
		return $float;
	}

	public function str2money($string,$currency=FALSE){
		$value=$this->str2float($string,TRUE);
		foreach($this->currencies as $needle){
			if (strpos($string,$needle)===FALSE){continue;}
			$currency=$needle;
			break;
		}
		$return=array();
		if ($value!==FALSE){$return['Amount']=$value;}
		if ($currency!==FALSE){
			$return['Currency']=$currency;
			$return['Unit']=$currency;
		}
		return $return;
	}
	
	public function str2date($string){
		$dates=$this->date2dates('2099-12-31');
		// look for moth string
		$date=array('year'=>'','month'=>'','day'=>'');
		foreach($this->months as $country=>$months){
			if (!empty($date['month'])){break;}
			foreach($months as $monthStr=>$needle){
				$monthStr=strval($monthStr);
				if (!empty($date['month'])){break;}
				if (mb_stripos($string,$needle)===FALSE){continue;}
				$date['month']=$monthStr;
				$string=str_replace($monthStr,'',$string);
				$chunks=preg_split("/[^0-9]/",$string);
				foreach($chunks as $chunk){
					if (empty($chunk)){continue;}
					if (strlen($chunk)===4){
						$date['year']=$chunk;
						continue;
					}
					if (intval($chunk)>31){$date['year']=$chunk;} else {$date['day']=$chunk;}
				}
			}
		}
		if (!empty($date['month'])){
			$dates=$this->date2dates(implode('-',$date));
		} else {
			// German date format
			$dateComps=explode('.',$string);
			if (count($dateComps)===3){
				$dates=$this->date2dates($dateComps[2].'-'.$dateComps[1].'-'.$dateComps[0]);
			} else {
				// US and UK date fomats
				$dateComps=explode('/',$string);
				if (count($dateComps)===3){
					if (intval($dateComps[0])>12){
						// UK
						$dates=$this->date2dates($dateComps[2].'-'.$dateComps[1].'-'.$dateComps[0]);
					} else if (intval($dateComps[1])>12){
						// US
						$dates=$this->date2dates($dateComps[2].'-'.$dateComps[0].'-'.$dateComps[1]);
					} else {
						// US
						$dates=$this->date2dates($dateComps[2].'-'.$dateComps[0].'-'.$dateComps[1]);
					}
				}
			}
		}
		return $dates;
	}
	
	private function date2dates($date){
		$dateComps=explode('-',$date);
		foreach($dateComps as $key=>$value){
			if (strlen($value)<2){$dateComps[$key]='0'.$value;}
		}
		$systemDate=implode('-',$dateComps);
		$dates=array('System'=>$systemDate,'Timestamp'=>'','US'=>'','UK'=>'','DE'=>'');
		$dates['Timestamp']=strtotime($systemDate.' 12:00:00');
		$dates['US']=date('m/d/Y',$dates['Timestamp']);
		$dates['UK']=date('d/m/Y',$dates['Timestamp']);
		$dates['DE']=date('d.m.Y',$dates['Timestamp']);
		$dates['day']=intval($dateComps[2]);
		$dates['month']=intval($dateComps[1]);
		$dates['year']=intval($dateComps[0]);
		$dates['US long']=$this->months['US'][$dateComps[1]].' '.$dates['day'].', '.$dateComps[0];
		$dates['UK long']=$dates['day'].' '.$this->months['US'][$dateComps[1]].' '.$dateComps[0];
		$dates['DE long']=$dates['day'].'. '.$this->months['DE'][$dateComps[1]].' '.$dateComps[0];
		return $dates;
	}
	
	/******************************************************************************************************************************************
	* String tools
	*/

	public function base64decodeIfEncoded($str){
		$decoded=base64_decode($str,TRUE);
		if (empty($decoded)){return $str;}
		$encoded=base64_encode($decoded);
		if ($encoded===$str){
			return $decoded;
		} else {
			return $str;
		}
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
	
	public function getEntryId($base=FALSE,$timestamp=FALSE){
		//	Creates and returns the unique EntryId
		if ($base){$suffix=$this->getHash($base,TRUE);} else {$suffix=mt_rand(100000,999999);}
		if ($timestamp===FALSE){
			$timestamp=time();
		} else {
			$timestamp=$timestamp;
		}
		$entryId="EID".$timestamp.'-'.$suffix."eid";
		return $entryId;
	}
	
	public function getEntryIdAge($entryId){
		// Returns the age of a provided EntryId
		if (strpos($entryId,'eid')===FALSE || strpos($entryId,'EID')===FALSE){return 0;}
		$timestamp=substr($entryId,3,strpos($entryId,'-')-1);
		$timestamp=intval($timestamp);
		return time()-$timestamp;
	}
	
	public function addEntryId($entry,$relevantKeys=array('Source','Group','Folder','Name','Type'),$timestampToUse=FALSE,$suffix='',$keepExistingEntryId=FALSE){
		if (!empty($entry['EntryId']) && $keepExistingEntryId){return $entry;}
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
		$entry['EntryId']=$this->getEntryId($base,$timestamp);
		if (!empty($suffix)){$entry['EntryId'].=$suffix;}
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
	
	public function code2utf($code){
		if($code<128)return chr($code);
		if($code<2048)return chr(($code>>6)+192).chr(($code&63)+128);
		if($code<65536)return chr(($code>>12)+224).chr((($code>>6)&63)+128).chr(($code&63)+128);
		if($code<2097152)return chr(($code>>18)+240).chr((($code>>12)&63)+128).chr((($code>>6)&63)+128) .chr(($code&63)+128);
		return '';
	}
	
	private function emojiList2file(){
		$html=file_get_contents('https://unicode.org/emoji/charts/full-emoji-list.html');
		//$html=file_get_contents('D:/FullEmojiList.htm');
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
		$this->arr2file($result,$this->emojiFile);
		return $result;
	}

	private function loadEmojis($emojiFile){
		if (is_file($emojiFile)){
			$json=file_get_contents($emojiFile);
			$this->emojis=$this->json2arr($json);
		} else {
			$this->emojis=$this->emojiList2file();
		}
		
	}

	public function float2str($float,$prec=3,$base=1000){
		// Thanks to "c0x at mail dot ru" based on https://www.php.net/manual/en/function.log.php
		$e=array('a','f','p','n','u','m','','k','M','G','T','P','E');
		$p=min(max(floor(log(abs($float), $base)),-6),6);
		return round((float)$float/pow($base,$p),$prec).' '.$e[$p+6];
	}

	/******************************************************************************************************************************************
	* Array tools
	*/

	public function getSeparator(){return self::ONEDIMSEPARATOR;}
	
	public function arr2file($inArr,$fileName=FALSE,$addDateTime=FALSE){
		/*	This function converts t$inArr to json format and saves the json data to a file. 
		*	If the fileName argument is empty, it will be created from the name of the calling class and function.
		*	The function returns the byte count written to the file or false in case of an error.
		*/
		if (empty($fileName)){
			$trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
			$fileName='';
			if ($addDateTime){$fileName.=date('Y-m-d h_m_s').' ';}
			$fileName.=$trace[1]['class'].' '.$trace[1]['function'];
			$fileName=mb_ereg_replace("[^A-Za-z0-9\-\_ ]",'_', $fileName);
			$file=$GLOBALS['base dir'].'debugging/'.$fileName.'.json';
		} else if (strpos($fileName,'/')===FALSE && strpos($fileName,'\\')===FALSE){
			$fileName=mb_ereg_replace("[^A-Za-z0-9\-\_ ]",'_', $fileName);
			$file=$GLOBALS['base dir'].'debugging/'.$fileName.'.json';
		} else {
			$file=$fileName;
		}
		$json=$this->arr2json($inArr);
		return file_put_contents($file,$json);
	}
		
	/**
	* @return string This method converts an array to the corresponding json string.
	*/
	public function arr2json($arr){
		return json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_IGNORE);
	}
	
	/**
	* @return arr This method converts a json string to the corresponding array.
	*/
	public function json2arr($json){
		if (is_string($json)){
			$arr=json_decode($json,TRUE,512,JSON_INVALID_UTF8_IGNORE);
			if (empty($arr)){$arr=json_decode(stripslashes($json),TRUE,512,JSON_INVALID_UTF8_IGNORE);}
			return $arr;
		} else {
			return array();
		}
	}
	
	/**
	* @return arr This method converts an array to the corresponding flat array.
	*/
	public function arr2flat($arr){
		if (!is_array($arr)){return $arr;}
		$flat=array();
		$this->arr2flatHelper($arr,$flat);
		return $flat;
	}
	
	private function arr2flatHelper($arr,&$flat,$oldKey=''){
		$result=array();
		foreach ($arr as $key=>$value){
			if (strlen(strval($oldKey))===0){$newKey=$key;} else {$newKey=$oldKey.self::ONEDIMSEPARATOR.$key;}
			if (is_array($value)){
				$result[$newKey]=$this->arr2flatHelper($value,$flat,$newKey); 
			} else {
				$result[$newKey]=$value;
				$flat[$newKey]=$value;
			}
		}
		return $result;
	}
	
	/**
	* @return arr This method converts a flat array to the corresponding array.
	*/
	public function flat2arr($arr){
		if (!is_array($arr)){return $arr;}
		$result=array();
		foreach($arr as $key=>$value){
			$result=array_replace_recursive($result,$this->flatKey2arr($key,$value));
		}
		return $result;
	}
	
	private function flatKey2arr($key,$value){
		if (!is_string($key)){return array($key=>$value);}
		$k=explode(self::ONEDIMSEPARATOR,$key);
		while(count($k)>0){
			$subKey=array_pop($k);
			$value=array($subKey=>$value);
		}
		return $value;
	}
	
	/**
	* @return arr This method deletes a key-value-pair selecting the key by the corresponding flat key.
	*/
	public function arrDeleteKeyByFlatKey($arr,$flatKey){
		$flatArr=$this->arr2flat($arr);
		foreach($flatArr as $arrKey=>$arrValue){
			if (strpos($arrKey,$flatKey)===FALSE){continue;}
			unset($flatArr[$arrKey]);
		}
		$arr=$this->flat2arr($flatArr);
		return $arr;
	}
	
	/**
	* @return arr This method updates a key-value-pair selecting the key by the corresponding flat key.
	*/
	public function arrUpdateKeyByFlatKey($arr,$flatKey,$value){
		$flatArr=$this->arr2flat($arr);
		$flatArr[$flatKey]=$value;
		$arr=$this->flat2arr($flatArr);
		return $arr;	
	}
		
	/**
	* @return string This method returns a string representing the provided flat key for a web page.
	*/
	public function flatKey2label($key){
		return str_replace(self::ONEDIMSEPARATOR,' &rarr; ',$key);
	}
	
	/**
	* @return string This method returns a string for a web page created from a statistics array, e.g. array('matches'=>0,'updated'=>0,'inserted'=>0,'deleted'=>0,'removed'=>0,'file added'=>0)
	*/
	public function statistic2str($statistic){
		$str=array();
		foreach($statistic as $key=>$value){
			if (is_array($value)){
				$str[]=$key.': '.implode(', ',$value);
			} else {
				$str[]=$key.'='.$value;
			}
		}
		return implode(' | ',$str);
	}
	
	/**
	* @return array This method returns an array which is a matrix used to create an html-table and a representation of the provided array.
	*/
	public function arr2matrix($arr){
		$matrix=array();
		$rowIndex=0;
		$rows=array();
		$maxColumnCount=0;
		foreach($this->arr2flat($arr) as $flatKey=>$value){
			$columns=explode(self::ONEDIMSEPARATOR,$flatKey);
			$columnCount=count($columns);
			$rows[$rowIndex]=array('columns'=>$columns,'value'=>$value);
			if ($columnCount>$maxColumnCount){$maxColumnCount=$columnCount;}
			$rowIndex++;
		}
		foreach($rows as $rowIndex=>$rowArr){
			for($i=0;$i<$maxColumnCount;$i++){
				$key='';
				if (isset($rowArr['columns'][$i])){
					if ($rowIndex===0 ){
						$key=$rowArr['columns'][$i];
					} else if (isset($rows[($rowIndex-1)]['columns'][$i])){
						if (strcmp($rows[($rowIndex-1)]['columns'][$i],$rowArr['columns'][$i])===0){
							$key='&#10149;';
						} else {
							$key=$rowArr['columns'][$i];
						}
					} else {
						$key=$rowArr['columns'][$i];
					}
				}
				$matrix[$rowIndex][$i]=$key;
			}
			$matrix[$rowIndex]['value']=$rowArr['value'];
		}
		return $matrix;
	}
	
	/**
	* @return array This method adds the values with the same key of multiple arrays.
	*/
	public function addArrValuesKeywise(...$arrays){
		// Example: Arguments "array('deleted'=>2,'inserted'=>1,'steps'=>'Open web page','done'=>FALSE)" and "array('deleted'=>0,'inserted'=>4,'steps'=>'Close web page','done'=>TRUE)"
		// will return array('deleted'=>2,'inserted'=>5,'steps'=>'Open web page|Close web page','done'=>TRUE)
		$result=array();
		array_walk_recursive($arrays,function($item,$key) use (&$result){
			if (is_numeric($item)){
				$result[$key]=isset($result[$key])?intval($item)+intval($result[$key]):intval($item);
			} else if (is_string($item)){
				$result[$key]=isset($result[$key])?$result[$key].'|'.$item:$item;
			} else {
				$result[$key]=$item;
			}
		});
		return $result;
	}
}
?>