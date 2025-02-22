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

    private $oc;
    
    private $currencies=[];
    private $costAlias=array('endbetrag'=>'brutto','endsumm'=>'brutto','total'=>'brutto','mwst'=>'vat','msatzsteu'=>'vat','amtlich'=>'amt','amtsgeb'=>'amt','zwischensumme'=>FALSE);
    
    public function __construct()
    {

    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
        $rates=new \SourcePot\Asset\Rates();
        $this->currencies=$rates->getCurrencies();
        $this->currencies['€']=$this->currencies['EUR'];
        $this->currencies['£']=$this->currencies['GBP'];
        $this->currencies['US\$']=$this->currencies['USD'];
        $this->currencies['CA\$']=$this->currencies['CAD'];
        $this->currencies['AU\$']=$this->currencies['AUD'];
    }

    /**
    * The method adds entry meta data and returns the enriched entry.
    *
    * @param array $entry Is the orginal entry  
    * @return array $entry Is the enriched entry
    */
    public function enrichEntry(array $entry):array
    {
        $currentUser=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        if (isset($entry['Date'])){
            $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
            $dateWebPageTimeZone=\DateTime::createFromFormat('Y-m-d H:i:s',$entry['Date'],new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
            if ($dateWebPageTimeZone){
                $dateWebPageTimeZone->setTimeZone(new \DateTimeZone($pageTimeZone));
                $entry['Date ('.$pageTimeZone.')']=$dateWebPageTimeZone->format('Y-m-d H:i:s');
            }
        }
        $entry['currentUserId']=$currentUser['EntryId'];
        $entry['currentUser']=$currentUser['Content']['Contact details']['First name'].' '.$currentUser['Content']['Contact details']['Family name'];
        $entry['nowTimeStamp']=time();
        $entry['nowDateTimeUTC']=date('Y-m-d H:i:s');
        $entry['nowDateUTC']=date('Y-m-d');
        $entry['nowTimeUTC']=date('H:i:s');
        $entry['+1DayFromNowUTC']=date('Y-m-d H:i:s',86400+time());
        $entry['+10DaysFromNowUTC']=date('Y-m-d H:i:s',864000+time());
        if (!empty($entry['Content']['File content'])){
            $entry=$this->addCosts($entry,$entry['Content']['File content']);
            $entry=$this->addUnycom($entry,$entry['Content']['File content']);
        }
        return $entry;
    }

    private function addUnycom(array $entry,string $text):array
    {
        $entry['UNYCOM']=$pList=$fList=[];
        $unycomObj = new \SourcePot\Match\UNYCOM();
        foreach($unycomObj->fetchCase($text) as $case){
            $unycomArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert2unycom($case);
            $pList[]=$unycomArr['Reference'];
            $fList[]=$unycomArr['Family'];
            if (empty($entry['UNYCOM'])){$entry['UNYCOM']=$unycomArr;}
        }
        $entry['UNYCOM P-list']=implode(';',$pList);
        $entry['UNYCOM F-List']=implode(';',$fList);
        return $entry;
    }

    private function addCosts(array $entry,string $text):array
    {
        $entry['Costs (left)']=$entry['Costs (right)']=[];
        $isBefore=$isAfter=FALSE;
        $maxValue=0;
        $regexAmount='/[\-\+]{0,1}([0-9]+[,. ]{0,1})+/';
        foreach($this->currencies as $code=>$name){
            $parts=preg_split('/'.$code.'/',$text);
            if (count($parts)<2){continue;}
            $entry['Costs (left)'][$code]['Max amount']=$entry['Costs (left)'][$code]['Max amount']??0;
            $entry['Costs (right)'][$code]['Max amount']=$entry['Costs (right)'][$code]['Max amount']??0;
            $entry['Costs (left)'][$code]['VAT']=$entry['Costs (left)'][$code]['VAT']??0;
            $entry['Costs (right)'][$code]['VAT']=$entry['Costs (right)'][$code]['VAT']??0;
            foreach($parts as $i=>$part){
                $amountStr=$descBefore=$descAfter='';
                if ($i===0){
                    $descBefore=substr($part,-50);
                    $descAfter=$parts[$i+1];
                } else if (!isset($parts[$i+1])){
                    $descBefore=$part;
                    $descAfter='';
                } else {
                    $descBefore=$part;
                    $descAfter=$parts[$i+1];
                }
                if ($isBefore){
                    $amountStr=substr($parts[$i],-20);
                } else if ($isAfter){
                    if (isset($parts[$i+1])){
                        $amountStr=substr($parts[$i+1],0,20);
                    }
                } else {
                    $amountStr=substr($parts[$i],-20);
                    preg_match($regexAmount,$amountStr,$match);
                    if (empty($match[0])){
                        $isAfter=TRUE;
                        $amountStr=substr($parts[$i+1],0,20);
                    } else {
                        $isBefore=TRUE;
                    }
                }
                preg_match($regexAmount,$amountStr,$match);
                if (isset($match[0])){
                    $value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->str2float($match[0]);
                    $entry['Costs (left)'][$code]['Max amount']=($value>$entry['Costs (left)'][$code]['Max amount'])?$value:$entry['Costs (left)'][$code]['Max amount'];
                    $entry['Costs (right)'][$code]['Max amount']=($value>$entry['Costs (right)'][$code]['Max amount'])?$value:$entry['Costs (right)'][$code]['Max amount'];
                    if (isset($prevMatch)){
                        $descBefore=trim(str_replace($prevMatch,'',$descBefore));
                    }
                    if (!empty($descBefore)){
                        $entry['Costs (left)'][$code][$descBefore]=$value;
                        if (strpos($descBefore,'MwSt')!==FALSE || strpos($descBefore,'USt')!==FALSE || strpos($descBefore,'msatzsteuer')!==FALSE || strpos($descBefore,'wertsteuer')!==FALSE){
                            $entry['Costs (left)'][$code]['VAT']=$value;
                        }
                    }
                    $descAfter=trim(str_replace($match[0],'',$descAfter));
                    if (!empty($descAfter)){
                        $entry['Costs (right)'][$code][$descAfter]=$value;
                        if (strpos($descAfter,'MwSt')!==FALSE || strpos($descAfter,'USt')!==FALSE || strpos($descAfter,'msatzsteuer')!==FALSE || strpos($descAfter,'wertsteuer')!==FALSE){
                            $entry['Costs (left)'][$code]['VAT']=$value;
                        }
                    }
                    $prevMatch=$match[0];
                }
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