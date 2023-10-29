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

namespace SourcePot\Datapool\Traits;

trait Conversions{

    private $currencies=array('USD','USD'=>'US$','EUR','EUR'=>'€','AFN','EUR','ALL','DZD','USD','AOA','XCD','ARS','AMD','AWG','AUD','AZN','BSD','BHD','BDT','BBD','BYN','BZD','XOF','BMD','BTN',
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

    public function convert2bool($value){
        return !empty($value);
    }
    
    public function convert2string($value){
        return $value;
    }

    public function convert2stringNoWhitespaces($value){
        $value=preg_replace("/\s/",'',$value);
        return $value;
    }

    public function convert2splitString($value){
        $value=strtolower($value);
        $value=trim($value);
        $value=preg_split("/[^a-zäöü0-9ß]+/",$value);
        return $value;
    }

    public function convert2float($value){
        $value=$this->str2float($value);
        return $value;
    }
    
    public function convert2int($value){
        $value=$this->str2float($value);
        return round($value);
    }

    public function convert2money($value){
        $arr=$this->str2money($value);
        return $arr;
    }

    public function convert2date($value){
        $arr=$this->str2date($value);
        return $arr;
    }
    
    public function convert2codepfad($value){
        $codepfade=explode(';',$value);
        $arr=array();
        foreach($codepfade as $codePfadIndex=>$codepfad){
            $codepfadComps=explode('\\',$codepfad);
            if ($codePfadIndex===0){
                if (isset($codepfadComps[0])){$arr['FhI']=$codepfadComps[0];}
                if (isset($codepfadComps[1])){$arr['FhI Teil']=$codepfadComps[1];}
                if (isset($codepfadComps[2])){$arr['Codepfad 3']=$codepfadComps[2];}
            } else {
                if (isset($codepfadComps[0])){$arr[$codePfadIndex]['FhI']=$codepfadComps[0];}
                if (isset($codepfadComps[1])){$arr[$codePfadIndex]['FhI Teil']=$codepfadComps[1];}
                if (isset($codepfadComps[2])){$arr[$codePfadIndex]['Codepfad 3']=$codepfadComps[2];}
            }
        }
        return $arr;
    }
    
    public function convert2unycom($value){
        $keyTemplate=array('Match','Year','Type','Number');
        $regions=array('WO'=>'PCT','WE'=>'Euro-PCT','EP'=>'European patent','EU'=>'Unitary Patent','AP'=>'ARIPO patent','EA'=>'Eurasian patent','OA'=>'OAPI patent');
        preg_match('/([0-9]\s*[0-9]\s*[0-9]\s*[0-9]|[0-9]\s*[0-9])(\s*[FPRZX]{1,2})([0-9\s]{5,6})/',$value,$matches);
        if (empty($matches[0])){return array();}
        $arr=array_combine($keyTemplate,$matches);
        $arr['Region']='  ';
        $arr['Country']='  ';
        $arr['Part']='  ';
        $prefixSuffix=explode($matches[0],$value);
        if (!empty($prefixSuffix[1])){
            $suffix=preg_replace('/\s+/','',$prefixSuffix[1]);
            $suffix=strtoupper($suffix);
            $suffix=str_split($suffix,2);
            foreach($regions as $rc=>$region){
                if (strpos($suffix[0],$rc)!==0){continue;}
                array_shift($suffix);
                $arr['Region']=$rc;
                $arr['Region long']=$region;
                break;
            }
            if (!empty($suffix[0])){
                // get country codes from file
                $file=$GLOBALS['dirs']['setup'].'/countryCodes.json';
                if (is_file($file)){
                    $cc=file_get_contents($file);
                    $countries=json_decode($cc,TRUE,512,JSON_INVALID_UTF8_IGNORE);
                    foreach($countries as $alpha2code=>$countryArr){
                        if (strpos($suffix[0],$alpha2code)===FALSE){continue;}
                        $arr['Country']=$alpha2code;
                        $arr['Country long']=$countryArr['Country'];
                        break;
                    }
                }
                // get part
                $suffix=implode('',$suffix);
                $part=preg_replace('/[^0-9]+/','',$suffix);
                $nonNumericSuffix=str_replace($part,'',$suffix);
                if (strlen($part)<2){$part='0'.$part;}
                if (!empty($part)){$arr['Part']=$part;}
                if (empty($arr['Country long']) && strlen($nonNumericSuffix)>1){
                    // get country, if country is empty
                    $arr['Country']=substr($nonNumericSuffix,0,2);
                }
            }
        }
        foreach($keyTemplate as $key){$arr[$key]=preg_replace('/\s+/','',$arr[$key]);}
        if (strlen($arr['Year'])===2){
            if (intval($arr['Year'])<50){
                $arr['Year']='20'.$arr['Year'];
            } else {
                $arr['Year']='19'.$arr['Year'];
            }
        }
        $reference=$arr['Year'].$arr['Type'].$arr['Number'].$arr['Region'].$arr['Country'].$arr['Part'];
        $arr=array('Reference'=>$reference,'Full'=>$reference)+$arr;
        $arr['Prefix']=trim($prefixSuffix[0],'- ');
        if (!empty($arr['Prefix'])){$arr['Full']=$arr['Prefix'].' - '.$arr['Full'];}
        //$this->arr2file($arr);
        return $arr;
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
        $return=array('Currency'=>'EUR');
        if (is_int($string) || is_float($string)){
            $value=$string;
        } else {
            $string=$this->convert2stringNoWhitespaces($string);
            $value=$this->str2float($string,TRUE);
            foreach($this->currencies as $targetLabel=>$needle){
                if (strpos($string,$needle)===FALSE){continue;}
                if (strlen($targetLabel)>2){$needle=$targetLabel;}
                $return['Currency']=$needle;
                break;
            }
        }
        if ($value!==FALSE){
            $return['Amount']=$value;
            $return['Amount (US)']=number_format($value,2);    
            $return['Amount (DE)']=number_format($value,2,',','');    
            $return['Amount (DE full)']=number_format($value,2,',','.');    
            $return['Amount (FR)']=number_format($value,2,'.',' ');    
        }
        return $return;
    }
    
    public function str2date($string){
        $date=array('year'=>'','month'=>'','day'=>'');
        $string=strtolower(trim($string));
        $string=preg_replace('/\s+/',' ',$string);
        $string=preg_replace('/([0-9])(\s+)([0-9])/','$1$3',$string);
        $string=preg_replace('/([a-zA-Zöüä])(\s+)([a-zA-Zöüä])/','$1$3',$string);
        // check if month name is provided
        $needleFound='';
        foreach($this->months as $country=>$months){
            if (!empty($date['month'])){break;}
            foreach($months as $monthStr=>$needle){
                $monthStr=strval($monthStr);
                if (mb_stripos($string,$needle)===FALSE){continue;}
                $needleFound=strtolower($needle);
                $date['month']=$monthStr;
            }
        }
        if (empty($date['month'])){
            $chunks=preg_split('/[^0-9]+/',$string);
            $date['year']=array_pop($chunks);
            foreach($chunks as $index=>$value){
                if (intval($value)>12){
                    $date['day']=$value;
                    unset($chunks[$index]);
                    reset($chunks);
                    $date['month']=current($chunks);
                    break;
                }
            }
            if (empty($date['day'])){
                $date['day']=array_shift($chunks);
                $date['month']=array_shift($chunks);
            }
        } else {
            $string=preg_replace('/[^0-9]/',' ',$string);
            $string=trim($string);
            $chunks=preg_split('/\s+/',$string);
            $date['day']=array_shift($chunks);
            $date['year']=array_shift($chunks);
        }
        if (empty($date['day']) || empty($date['month'])){return FALSE;}
        if (strlen($date['day'])<2){$date['day']='0'.$date['day'];}
        if (strlen($date['month'])<2){$date['month']='0'.$date['month'];}
        if ($date['year']<70){
            $date['year']='20'.$date['year'];
        } else if ($date['year']>=70 && $date['year']<100){
            $date['year']='19'.$date['year'];
        } else if ($date['year']<1000){
            $date['year']='0'.$date['year'];
        }
        $dates=$this->date2dates(implode('-',$date));
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
