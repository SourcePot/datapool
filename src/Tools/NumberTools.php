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

class NumberTools{
	
	private $arr;
	
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
	
	public function __construct($arr){
		$this->arr=$arr;
	}

	public function init($arr){
		$this->arr=$arr;
		return $this->arr;
	}
	
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
	
}
?>