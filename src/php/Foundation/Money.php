<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Money{
    
    private $oc;
    
    private $ecbExchangeRatesUrl='https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html';
    private $ecbExchangeRates90url='';
    private $ecbExchangeRatesHistUrl='https://data.ecb.europa.eu/data/data-categories/ecbeurosystem-policy-and-exchange-rates/exchange-rates/reference-rates?searchTerm=&filterSequence=frequency&sort=relevance&filterType=basic&showDatasetModal=false&filtersReset=false&resetAll=false&frequency%5B%5D=D';
    
    private $entryTable;
    private $entryTemplate=array();
    private $tableRatesSelector=array();
    
    private $currencies=array();
    private $currencyAlias=array('EUR'=>array('EUR','€'),'AUD'=>array('AUD','AU$'),'USD'=>array('USD','US$','$'));
        
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }
    
    public function init($oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
        $this->tableRatesSelector=array('Source'=>$this->entryTable,'Group'=>'ECB','Folder'=>'Rates','Owner'=>'SYSTEM');
        $this->getOldRatesIfRequired();
        $this->currencies=$this->getCurrencies();
    }
    
    public function getEntryTable(){return $this->entryTable;}

    public function getEntryTemplate(){return $this->entryTemplate;}
    
    public function job($vars){
        $client = new \GuzzleHttp\Client();
        $request = new \GuzzleHttp\Psr7\Request('GET',$this->ecbExchangeRatesUrl);
        // Send an asynchronous request.
        $promise = $client->sendAsync($request)->then(function($response){
            $body=((string)$response->getBody());
            $links=$this->body2links($this->ecbExchangeRatesUrl,$body,'90d.xml');
            if ($links){
                $this->ecbExchangeRates90url=current($links);
                $client = new \GuzzleHttp\Client();
                $request = new \GuzzleHttp\Psr7\Request('GET',$this->ecbExchangeRates90url);
                $promise = $client->sendAsync($request)->then(function($response){
                                $body=((string)$response->getBody());
                                $rates=$this->body2rates($body);
                                $this->addRates2table($rates);
                           });
                $promise->wait();
            }
        });
        $promise->wait();
        return $vars;
    }
    
    private function body2links($url,$body,$filter=FALSE){
        $urlArr=parse_url($url);
        $links=array();
        $chunks=explode('href="',$body);
        foreach($chunks as $chunk){
            $href=substr($chunk,0,strpos($chunk,'"'));
            if ($filter){
                if (stripos($href,$filter)===FALSE){continue;}
            }
            if ($href[0]=='/'){
                $links[]=$urlArr['host'].$href;
            } else {
                $links[]=$href;
            }
        }
        return $links;
    }

    private function body2rates($body){
        $rates=array();
        $chunks=explode('time="',$body);
        array_shift($chunks);
        foreach($chunks as $chunk){
            $date=substr($chunk,0,strpos($chunk,'"'));
            $subChunks=explode('currency="',$chunk);
            array_shift($subChunks);
            foreach($subChunks as $subChunk){
                $currency=substr($subChunk,0,strpos($subChunk,'"'));
                $rateChunk=explode('rate="',$subChunk);
                $rateChunk=array_pop($rateChunk);
                $rate=substr($rateChunk,0,strpos($rateChunk,'"'));
                $rates[$date][$currency]=$rate;
            }
        }
        return $rates;
    }
    
    private function addRates2table($rates){
        $count=0;
        $entry=$this->tableRatesSelector;
        foreach($rates as $date=>$rateArr){
            $entry['Date']=$date.' 16:00:00';
            $entry['Name']=$date.' CET';
            $entry['Read']='ALL_MEMBER_R';
            $entry['Content']=$rateArr;
            $entry['EntryId']=$entry['Name'].' ECBrates';
            $newEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
            if ($newEntry){$count++;}
        }
        return $count;
    }
    
    private function getOldRatesIfRequired(){
        $selector=$this->tableRatesSelector;
        $selector['Name']='1999-01-21 CET';
        $rowCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,TRUE);
        if (empty($rowCount)){
            $dir=$GLOBALS['dirs']['setup'];
            $setupFiles=scandir($dir);
            foreach($setupFiles as $fileIndex=>$fileName){
                if (strpos($fileName,'ECB Data Portal')===FALSE){continue;}
                $csvFile=$dir.$fileName;
                if (!is_file($csvFile)){continue;}
                $this->ratesCsv2table($csvFile);
                break;
            }
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    private function ratesCsv2table($csvFile){
        $csv=new \SplFileObject($csvFile);
        $csv->setCsvControl(',','"','\\');
        $currencies=array();
        $keys=array();
        $rowIndex=0;
        while($csv->valid()){
            $csvArr=$csv->fgetcsv();
            $result=array();
            foreach($csvArr as $columnIndex=>$cellValue){
                if (isset($keys[$columnIndex])){
                    $result[$keys[$columnIndex]]=$cellValue;
                } else {
                    $cellValueArr=preg_split('/\/|\./',$cellValue);
                    //Bulgarian lev/Euro (EXR.D.BGN.EUR.SP00.A)
                    if (count($cellValueArr)>1){
                        $keys[$columnIndex]=$cellValueArr[3];
                        $currencies[$cellValueArr[3]]=$cellValueArr[0];
                    } else {
                        $keys[$columnIndex]=ucfirst(strtolower($cellValueArr[0]));
                    }
                }
            }
            if ($rowIndex!==0){
                $entry=$this->tableRatesSelector;
                $entry['Date']=$result['Date'].' 16:00:00';
                $entry['Name']=$result['Date'].' CET';
                $entry['Read']='ALL_MEMBER_R';
                unset($result['Date']);
                $entry['Content']=$result;
                $entry['EntryId']=$entry['Name'].' ECBrates';
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
            }
            $csv->next();
            $rowIndex++;
        }
        $entry=$this->tableRatesSelector;
        $entry['Name']=date('Y-m-d');
        $entry['Date']=$entry['Name'].' 12:00:00';
        $entry['Read']='ALL_MEMBER_R';
        $entry['Folder']='Currencies';
        $entry['Content']=$currencies;
        $entry['EntryId']=$entry['Folder'];
        $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
        return $rowIndex;
    }
    
    public function getCurrencies(){
        $currencies=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(array('Source'=>$this->entryTable,'EntryId'=>'Currencies'),TRUE);
        if (isset($currencies['Content'])){
            return $currencies['Content'];
        } else {
            return array();
        }
    }
    
    public function getRates($date,$timezone='Europe/Berlin'){
        $cetTimezoneObj=new \DateTimeZone('CET');
        $timezoneObj=new \DateTimeZone($timezone);
        $datetimeObj=new \DateTime($date,$timezoneObj);
        $timestamp=$datetimeObj->getTimestamp();
        $datetimeObj->setTimezone($cetTimezoneObj);
        $dateTimeString=$datetimeObj->format('Y-m-d');
        // range start date
        $startTimestamp=$timestamp-259200;
        $startDatetimeObj=new \DateTime('@'.$startTimestamp);
        $startDatetimeObj->setTimezone($cetTimezoneObj);
        // range end date
        $endTimestamp=$timestamp+259200;
        $endDatetimeObj=new \DateTime('@'.$endTimestamp);
        $endDatetimeObj->setTimezone($cetTimezoneObj);
        // create selector
        $selector=$this->tableRatesSelector;
        $selector['Date>=']=$startDatetimeObj->format('Y-m-d H:i:s');
        $selector['Date<=']=$endDatetimeObj->format('Y-m-d H:i:s');
        $ratesMatch=array('Date'=>$dateTimeString,'Rates'=>array(),'Date match'=>FALSE,'Error'=>'No data');
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','Date',TRUE) as $entry){
            $date=substr($entry['Date'],0,10);
            if ($date>=$dateTimeString){
                $ratesMatch=array('Date'=>$date,'Date match'=>($date==$dateTimeString),'Rates'=>$entry['Content'],'Error'=>'');
                break;
            }
        }
        foreach($ratesMatch['Rates'] as $currency=>$rate){
            $ratesMatch['Rates'][$currency]=floatval($rate);
        }
        return $ratesMatch;
    }

    public function currencyConversion($amount,$sourceCurrency='USD',$date=FALSE,$timezone='Europe/Berlin'){
        $sourceCurrency=strtoupper($sourceCurrency);
        $result=array('Error'=>'',$sourceCurrency=>$amount);
        if (empty($date)){$date=date('Y-m-d');}
        // get EUR amount
        if ($sourceCurrency=='EUR'){
            $result['EUR']=$amount;
        } else {
            $rates=$this->getRates($date,$timezone);
            $result['Date']=$rates['Date'];
            $result['Date match']=$rates['Date match'];
            $result['Error']=$rates['Error'];
            foreach($rates['Rates'] as $currency=>$rate){
                $currency=strtoupper($currency);
                if ($currency==$sourceCurrency){
                    $result['EUR']=$amount/$rate;
                    break;
                }
            }
        }
        // get amount in target currency
        if (!isset($result['EUR'])){
            $result['Error']='Source currency unknown';
        } else {
            foreach($rates['Rates'] as $currency=>$rate){
                $currency=strtoupper($currency);
                if ($rate==0){continue;}
                $result[$currency]=$result['EUR']*$rate;
            }
        }
        return $result;
    }
    
    public function str2money($string,$lang='de'){
        $lang=strtolower($lang);
        $result=array('Currency'=>'','Amount'=>0,'Lang'=>$lang);
        foreach($this->currencies as $code=>$name){
            if (isset($this->currencyAlias[$code])){
                $aliasCodes=$this->currencyAlias[$code];
            } else {
                $aliasCodes=array($code);
            }
            foreach($aliasCodes as $aliasCode){
                if (stripos($string,$aliasCode)===FALSE){continue;}
                $result['Currency']=$code;
                $result['Currency name']=$name;
                break 2;
            }
        }
        // get number from string
        $result['Number string']=preg_replace('/[^0-9\.\,\-]/','',$string);
        // validate language for number format
        $dotChunk=mb_strrchr($result['Number string'],'.');
        $dotCount=mb_strlen($result['Number string'])-mb_strlen(str_replace('.','',$result['Number string']));
        if ($dotCount>1){
            // e.g. 1.234.456,78 -> 1234456,78
            $result['Lang']='de';
            $numberStr=str_replace('.','',$result['Number string']);
        } else {
            $numberStr=$result['Number string'];
        }
        $commaChunk=mb_strrchr($result['Number string'],',');
        $commaCount=mb_strlen($result['Number string'])-mb_strlen(str_replace(',','',$result['Number string']));
        if ($commaCount>1){
            // e.g. 1,234,456.78 -> 1234456,78
            $result['Lang']='en';
            $numberStr=str_replace(',','',$result['Number string']);
        } else {
            $numberStr=$result['Number string'];
        }
        if ($dotCount===1 && $commaCount===1){
            if (mb_strlen($commaChunk)>mb_strlen($dotChunk)){
                // e.g. 1,234.56
                $result['Lang']='en';
            } else {
                // e.g. 1.234,56
                $result['Lang']='de';
            }
        } else if ($dotCount===1 && mb_strlen($numberStr)>7){
            // e.g. 1234.567
            $result['Lang']='en';
        } else if ($commaCount===1 && mb_strlen($numberStr)>7){
            // e.g. 1234,567
            $result['Lang']='de';
        }
        // convert to float based on number format
        if ($result['Lang']==='en'){
            $numberStr=str_replace(',','',$numberStr);
            $result['Amount']=floatval($numberStr);
        } else {
            $numberStr=str_replace('.','',$numberStr);
            $numberStr=str_replace(',','.',$numberStr);
            $result['Amount']=floatval($numberStr);
        }
        // enrich result
        $result['Amount (US)']=$result['Currency'].' '.number_format($result['Amount'],2);
        $result['Amount (DE)']=number_format($result['Amount'],2,',','').' '.$result['Currency'];
        $result['Amount (DE full)']=number_format($result['Amount'],2,',','.').' '.$result['Currency'];
        $result['Amount (FR)']=number_format($result['Amount'],2,'.',' ').' '.$result['Currency'];
        return $result;
    }

}
?>