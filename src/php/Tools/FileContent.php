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

    private const COST_TEXT_MAX_LENGTH=50;

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
        $this->currencies['US$']=$this->currencies['USD'];
        $this->currencies['CA$']=$this->currencies['CAD'];
        $this->currencies['AU$']=$this->currencies['AUD'];
    }

    /**
    * The method adds entry meta data and returns the enriched entry.
    *
    * @param array $entry Is the orginal entry  
    * @return array $entry Is the enriched entry
    */
    public function enrichEntry(array $entry):array
    {
        if (isset($entry['Date'])){
            if (is_array($entry['Date'])){
                $this->oc['logger']->log('notice','Entry mal format: key "Date" is array for Entry Source="{Source}", Group="{Group}", Folder="{Folder}", Name="{Name}".',$entry);
            } else {
                $pageTimeZone=\SourcePot\Datapool\Root::getUserTimezone();
                $dateWebPageTimeZone=\DateTime::createFromFormat('Y-m-d H:i:s',$entry['Date'],new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
                if ($dateWebPageTimeZone){
                    $dateWebPageTimeZone->setTimeZone(new \DateTimeZone($pageTimeZone));
                    $entry['Date ('.$pageTimeZone.')']=$dateWebPageTimeZone->format('Y-m-d H:i:s');
                }
            }
        }
        $currentUser=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $entry['currentUserId']=$currentUser['EntryId'];
        $entry['currentUser']=$currentUser['Content']['Contact details']['First name'].' '.$currentUser['Content']['Contact details']['Family name'];
        $entry['nowTimeStamp']=time();
        $entry['nowDateTimeUTC']=date('Y-m-d H:i:s');
        $entry['nowDateUTC']=date('Y-m-d');
        $entry['nowTimeUTC']=date('H:i:s');
        $entry['+1DayFromNowUTC']=date('Y-m-d H:i:s',86400+time());
        $entry['+10DaysFromNowUTC']=date('Y-m-d H:i:s',864000+time());
        $entry['attachedFile']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);    
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
            $unycomArr=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert2unycom($case);
            $pList[$unycomArr['Reference']]=$unycomArr['Reference'];
            $fList[$unycomArr['Family']]=$unycomArr['Family'];
            if (empty($entry['UNYCOM'])){
                $entry['UNYCOM']=$unycomArr;
            }
        }
        $entry['UNYCOM P-list']=implode(';',$pList);
        $entry['UNYCOM F-List']=implode(';',$fList);
        return $entry;
    }

    private function addCosts(array $entry,string $text):array
    {
        $text=preg_replace('/\s+/',' ',$text);
        $entry['Costs (left)']=$entry['Costs (right)']=[];
        foreach($this->currencies as $code=>$name){
            $safeCode=preg_quote($code,'/');
            $regexp='/([\-\+ ]{0,2}[0-9,.]{1,20}\s{0,1}'.$safeCode.')|('.$safeCode.'\s{0,1}[\-\+ ]{0,2}[0-9,.]{1,20})/';
            $parts=preg_split($regexp,$text,-1,PREG_SPLIT_DELIM_CAPTURE);
            if (count($parts)<2){continue;}
            $rightIndex=$leftIndex=0;
            foreach($parts as $i=>$part){
                if ($i%2==0){
                    if (isset($entry['Costs (left)'][$code][$leftIndex])){
                        if (strlen($part)>self::COST_TEXT_MAX_LENGTH){
                            $part=substr($part,0,self::COST_TEXT_MAX_LENGTH).'...';
                        }
                        $entry['Costs (left)'][$code][$leftIndex]=trim($part).' | '.$entry['Costs (left)'][$code][$leftIndex];
                        $leftIndex++;
                    }
                    $entry['Costs (right)'][$code][$rightIndex]=trim($part);
                } else {
                    $entry['Costs (left)'][$code][$leftIndex]=trim($part);
                    if (isset($entry['Costs (right)'][$code][$rightIndex])){
                        if (strlen($entry['Costs (right)'][$code][$rightIndex])>self::COST_TEXT_MAX_LENGTH){
                            $entry['Costs (right)'][$code][$rightIndex]='...'.substr($entry['Costs (right)'][$code][$rightIndex],-1*self::COST_TEXT_MAX_LENGTH);
                        }
                        $entry['Costs (right)'][$code][$rightIndex]=$entry['Costs (right)'][$code][$rightIndex].' | '.trim($part);
                        $rightIndex++;
                    }
                }
            }
        }
        return $entry;
    }

}
?>