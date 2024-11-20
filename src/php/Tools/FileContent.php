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
    
    private $costs=array('EUR'=>'/(EUR\s*[\-0-9,.]+\d{2})/u','USD'=>'/(USD\s*[\-0-9,.]+\d{2})/u',);
    private $costAlias=array('endbetrag'=>'brutto','endsumm'=>'brutto','total'=>'brutto','mwst'=>'vat','msatzsteu'=>'vat','amtlich'=>'amt','amtsgeb'=>'amt','zwischensumme'=>FALSE);
    
    public function __construct()
    {

    }

    Public function loadOc(array $oc):void
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
        $entry['UNYCOM']=array();
        $pList=$fList=array();
        preg_match_all(\SourcePot\Datapool\Tools\MiscTools::UNYCOM_REGEX,$text,$matches,PREG_OFFSET_CAPTURE);
        foreach($matches[0] as $match){
            $prefix=substr($text,$match[1]-10,10);
            $prefixComps=preg_split('/[^A-Za-z0-9 ]+/',$prefix);
            if (count($prefixComps)>1){
                array_pop($prefixComps);
                $prefix=array_pop($prefixComps);    
            } else {
                $prefix='';
            }
            $case=substr($text,intval($match[1]),16);
            $unycomArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert2unycom($case,$prefix);
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
        $costDescription=array();
        foreach($this->costs as $key=>$regex){
            $parts=preg_split($regex,$text,-1,PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $entry['Costs'][$key]=array();
            if (count($parts)===1){continue;}
            for($index=0;$index<(count($parts)-1);$index+=2){
                $description=mb_strtolower($parts[$index]);
                if (empty($entry['Costs'][$key])){
                    $descriptionArr=explode("\n",$description);
                    $description=strval(array_pop($descriptionArr));
                    if (strlen($description)<30){$description=strval(array_pop($descriptionArr)).' '.$description;}
                }
                $description=preg_replace('/\s+/u',' ',trim($description));
                $costDescription[]=$description;
                $description=$this->costAlias($description);
                if (empty($description)){continue;}
                $entry['Costs'][$key][$description]=(isset($entry['Costs'][$key][$description]))?$entry['Costs'][$key][$description]:0;
                $entry['Costs'][$key][$description]+=$this->oc['SourcePot\Datapool\Tools\MiscTools']->str2float($parts[$index+1]);
            }
            $netto=0;
            foreach($entry['Costs'][$key] as $description=>$costs){
                $description=strval($description);
                if (mb_stripos($description,'vat')!==FALSE){
                    $netto-=$costs;
                } else if (mb_stripos($description,'brutto')!==FALSE){
                    $netto+=$costs;
                }
                $entry['Costs'][$key][$description]=$key.' '.$costs;
            }
            $entry['Costs'][$key]['netto']=$key.' '.$netto;
        }
        $entry['Costs description']=implode(' | ',$costDescription);
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