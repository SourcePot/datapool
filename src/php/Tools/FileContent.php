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

    private $payments=array('EUR'=>'/(EUR\s*[\-0-9,.]+\d{2})/u','USD'=>'/(USD\s*[\-0-9,.]+\d{2})/u',);

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
            $entry=$this->addUnycomRefs($entry);
            $entry=$this->addPayments($entry);
        }
        return $entry;
    }

    private function addUnycomRefs(array $entry):array
    {
        $entry['UNYCOM cases']=array();
        $entry['UNYCOM patents']=array();
        $entry['UNYCOM families']=array();
        $entry['UNYCOM inventions']=array();
        $entry['UNYCOM contracts']=array();
        preg_match_all('/([0-9]{4}[XPEF]{1,2}[0-9]{5})((\s{1,2}|WO|WE|EP|AP|EA|OA)[A-Z ]{0,2}[0-9]{0,2})/',$entry['Content']['File content'],$matches);
        if (!empty($matches[0][0])){
            foreach($matches[0] as $matchIndex=>$match){
                if ($match[4]=='P' || $match[5]=='P'){$entry['UNYCOM patents'][$match]=$match;}
                $familyRef=preg_replace('/[A-Z]+/','F',$matches[1][$matchIndex]);
                $inventionRef=preg_replace('/[A-Z]+/','E',$matches[1][$matchIndex]);
                $entry['UNYCOM cases'][$match]=$match;
                $entry['UNYCOM families'][$familyRef]=$familyRef;
                $entry['UNYCOM inventions'][$inventionRef]=$inventionRef;
            }
        }
        preg_match_all('/[0-9]{4}V[0-9]{5}/',$entry['Content']['File content'],$matches);
        if (!empty($matches[0][0])){
            foreach($matches[0] as $matchIndex=>$match){
                $entry['UNYCOM contracts'][$match]=$match;
            }
        }
        $entry['UNYCOM cases']=implode(';',$entry['UNYCOM cases']);
        $entry['UNYCOM patents']=implode(';',$entry['UNYCOM patents']);
        $entry['UNYCOM families']=implode(';',$entry['UNYCOM families']);
        $entry['UNYCOM inventions']=implode(';',$entry['UNYCOM inventions']);
        $entry['UNYCOM contracts']=implode(';',$entry['UNYCOM contracts']);
        return $entry;
    }

    private function addPayments(array $entry):array
    {
        foreach($this->payments as $key=>$regex){
            $parts=preg_split($regex,$entry['Content']['File content'],-1,PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $entry['Content']['Payments'][$key]=array();
            if (count($parts)===1){continue;}
            for($index=0;$index<(count($parts)-1);$index+=2){
                $description=mb_strtolower($parts[$index]);
                if (empty($entry['Content']['Payments'][$key])){
                    $descriptionArr=explode("\n",$description);
                    $description=array_pop($descriptionArr);
                    if (strlen($description)<30){$description=strval(array_pop($descriptionArr)).' '.$description;}
                }
                $description=preg_replace('/\s+/u',' ',trim($description));
                $entry['Content']['Payments'][$key][$description]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->str2float($parts[$index+1]);
            }
        }
        return $entry;
    }

}
?>