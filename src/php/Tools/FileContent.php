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

final class FileContent{
    
    private $costs=array('EUR'=>'/(EUR\s*[\-0-9,.]+\d{2})/u','USD'=>'/(USD\s*[\-0-9,.]+\d{2})/u',);
    private $costAlias=array('endbetrag'=>'brutto','endsumm'=>'brutto','total'=>'brutto','mwst'=>'vat','msatzsteu'=>'vat','amtlich'=>'amt','amtsgeb'=>'amt','zwischensumme'=>FALSE);
    
    public function __construct()
    {

    }
    
    public function init(array $oc)
    {
        $this->oc=$oc;
    }

    /**
    * The method adds entry meta data and returns the enriched entry.
    *
    * @param array $entry Is the orginal entry  
    * @return array $entry Is the enriched entry
    */
    public function enrichEntry(array $entry):array
    {
        $entry['currentUserId']='ANONYM';
        $entry['currentUser']='Doe, John';
        if (!empty($_SESSION['currentUser']['EntryId'])){
            $entry['currentUserId']=$_SESSION['currentUser']['EntryId'];
            $entry['currentUser']=$_SESSION['currentUser']['Content']['Contact details']['First name'].' '.$_SESSION['currentUser']['Content']['Contact details']['Family name'];
        }
        $entry['nowTimeStamp']=time();
        $entry['nowDateTimeUTC']=date('Y-m-d H:i:s');
        $entry['nowDateUTC']=date('Y-m-d');
        $entry['nowTimeUTC']=date('H:i:s');
        $entry['+10DaysDateUTC']=date('Y-m-d 12:00:00',86400+time());
        if (!empty($entry['Content']['File content'])){
            $entry=$this->addCosts($entry,$entry['Content']['File content']);
            $entry=$this->addUnycom($entry,$entry['Content']['File content']);
        }
        return $entry;
    }

    private function addUnycom(array $entry,string $text):array
    {
        preg_match('/([0-9]{4})([XPEF]{1,2})([0-9]{5})(\s{1,2}|WO|WE|EP|AP|EA|OA)([A-Z ]{0,2})([0-9]{0,2})/',$text,$matches);
        if (isset($matches[0])){
            $unycom=$matches[0];
            $needlePos=stripos($text,$unycom);
            $fhi=substr($text,$needlePos-10,10);
            preg_match('/([A-Zabc1-9]{3,6})(\s*(-|—|—)\s*)/',$fhi,$match);
            $fhi=(isset($match[1]))?$match[1].' - ':'';
            $entry['Content']['UNYCOM']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert2unycom($fhi.$unycom);
            $entry['Content']['UNYCOM list']=implode(';',$matches);
        }
        return $entry;
    }

    private function addCosts(array $entry,string $text):array
    {
        foreach($this->costs as $key=>$regex){
            $parts=preg_split($regex,$text,-1,PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $entry['Content']['Costs'][$key]=array();
            if (count($parts)===1){continue;}
            for($index=0;$index<(count($parts)-1);$index+=2){
                $description=mb_strtolower($parts[$index]);
                if (empty($entry['Content']['Costs'][$key])){
                    $descriptionArr=explode("\n",$description);
                    $description=array_pop($descriptionArr);
                    if (strlen($description)<30){$description=strval(array_pop($descriptionArr)).' '.$description;}
                }
                $description=preg_replace('/\s+/u',' ',trim($description));
                $description=$this->costAlias($description);
                if (empty($description)){continue;}
                $entry['Content']['Costs'][$key][$description]=(isset($entry['Content']['Costs'][$key][$description]))?$entry['Content']['Costs'][$key][$description]:0;
                $entry['Content']['Costs'][$key][$description]+=$this->oc['SourcePot\Datapool\Tools\MiscTools']->str2float($parts[$index+1]);
            }
            foreach($entry['Content']['Costs'][$key] as $description=>$costs){
                $entry['Content']['Costs'][$key][$description]=$key.' '.$costs;
            }
        }
        return $entry;
    }

    private function costAlias(string $str)
    {
        if (empty($str)){return '';}
        foreach($this->costAlias as $needle=>$alias){
            if (mb_stripos($str,$needle)===FALSE){continue;}
            if ($alias===FALSE){return '';}
            preg_match('/([^0-9]*)([0-9,.]+)(.*)/',$str,$match);
            if ($alias=='vat' && isset($match[2])){
                $rate=strtr($match[2],array(','=>'.'));
                $rate=floatval($rate);
                $rate=sprintf("%01.2f",$rate);
                $alias.=' '.$rate;
            }
            return $alias;
        }
        return $str;
    }
}
?>