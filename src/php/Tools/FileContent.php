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
            if (is_array($entry['Date'])){
                $this->oc['logger']->log('notice','Entry mal format: key "Date" is array for Entry Source="{Source}", Group="{Group}", Folder="{Folder}", Name="{Name}".',$entry);
            } else {
                $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
                $dateWebPageTimeZone=\DateTime::createFromFormat('Y-m-d H:i:s',$entry['Date'],new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
                if ($dateWebPageTimeZone){
                    $dateWebPageTimeZone->setTimeZone(new \DateTimeZone($pageTimeZone));
                    $entry['Date ('.$pageTimeZone.')']=$dateWebPageTimeZone->format('Y-m-d H:i:s');
                }
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
        foreach($this->currencies as $code=>$name){
            $regexp='/('.$code.'\s{1,2}[\-\+]{0,1}([0-9]+[,. ]{0,1})+)|([\-\+]{0,1}([0-9]+[,. ]{0,1})+\s{1,2}'.$code.')/';
            $parts=preg_split($regexp,$text, -1,PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $partCount=(is_bool($parts))?1:count($parts);
            if ($partCount<2){continue;}
            $entry['Costs (left)'][$code]['Gross']=$entry['Costs (left)'][$code]['Max amount']=$entry['Costs (left)'][$code]['Net']=$entry['Costs (left)'][$code]['VAT']=0;
            $entry['Costs (right)'][$code]['Gross']=$entry['Costs (right)'][$code]['Max amount']=$entry['Costs (right)'][$code]['Net']=$entry['Costs (right)'][$code]['VAT']=0;
            $desc=$value=NULL;
            foreach($parts as $i=>$part){
                $leftValid=$rightValid=FALSE;
                $part=preg_replace('/\s+/',' ',$part);
                $descDetectStr=preg_replace('/[0-9,. \+\-]+/','',$part);
                if (strpos($part,$code)!==FALSE){
                    // value
                    $value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->str2float($part);
                    $leftValid=TRUE;
                } else if (strlen($descDetectStr)>2){
                    // description
                    $desc=(isset($desc))?$part:substr($part,-50);
                    $rightValid=TRUE;
                } else {
                    continue;
                }
                if ($leftValid){
                    $desc=strval($desc);
                    if (strpos($desc,'MwSt')!==FALSE || strpos($desc,'USt')!==FALSE || strpos($desc,'msatzsteuer')!==FALSE || strpos($desc,'wertsteuer')!==FALSE){
                        $entry['Costs (left)'][$code]['VAT']+=$value;
                    } else if (stripos($desc,'endsumme')!==FALSE || stripos($desc,'endbetrag')!==FALSE || stripos($desc,'total')!==FALSE){
                        $entry['Costs (left)'][$code]['Gross']=$value;
                    }
                    $entry['Costs (left)'][$code]['Max amount']=($value>$entry['Costs (left)'][$code]['Max amount'])?$value:$entry['Costs (left)'][$code]['Max amount'];
                    $entry['Costs (left)'][$code]['Net']=$entry['Costs (left)'][$code]['Gross']-$entry['Costs (left)'][$code]['VAT'];
                    $entry['Costs (left)'][$code][$desc][]=$value;
                }
                if ($rightValid && isset($value)){
                    $desc=strval($desc);
                    if (strpos($desc,'MwSt')!==FALSE || strpos($desc,'USt')!==FALSE || strpos($desc,'msatzsteuer')!==FALSE || strpos($desc,'wertsteuer')!==FALSE){
                        $entry['Costs (right)'][$code]['VAT']+=$value;
                    } else if (stripos($desc,'endsumme')!==FALSE || stripos($desc,'endbetrag')!==FALSE || stripos($desc,'total')!==FALSE){
                        $entry['Costs (right)'][$code]['Gross']=$value;
                    }
                    $entry['Costs (right)'][$code]['Max amount']=($value>$entry['Costs (right)'][$code]['Max amount'])?$value:$entry['Costs (right)'][$code]['Max amount'];
                    $entry['Costs (right)'][$code]['Net']=$entry['Costs (right)'][$code]['Gross']-$entry['Costs (right)'][$code]['VAT'];
                    $entry['Costs (right)'][$code][$desc][]=$value;
                }
            }
        }
        return $entry;
    }

}
?>