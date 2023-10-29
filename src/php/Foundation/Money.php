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
    
    private $epoExchangeRatesUrl='https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html';
    private $epoExchangeRates90url='';
    
    private $entryTable;
    private $entryTemplate=array();
    private $tableRatesSelector=array();
        
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
    }
    
    public function getEntryTable(){return $this->entryTable;}

    public function getEntryTemplate(){return $this->entryTemplate;}
    
    public function job($vars){
        $client = new \GuzzleHttp\Client();
        $request = new \GuzzleHttp\Psr7\Request('GET',$this->epoExchangeRatesUrl);
        // Send an asynchronous request.
        $promise = $client->sendAsync($request)->then(function($response){
            $body=((string)$response->getBody());
            $links=$this->body2links($this->epoExchangeRatesUrl,$body,'90d.xml');
            if ($links){
                $this->epoExchangeRates90url=current($links);
                $client = new \GuzzleHttp\Client();
                $request = new \GuzzleHttp\Psr7\Request('GET',$this->epoExchangeRates90url);
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
            $entry['Name']=$date;
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
        $selector['Name']='1999-01-21';
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
                $entry['Name']=$result['Date'];
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
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name'));
        $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
        return $rowIndex;
    }

}
?>