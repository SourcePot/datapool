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

use DateTime;

class Money implements \SourcePot\Datapool\Interfaces\Job{
    
    private $oc;
        
    private $entryTable='';
    private $entryTemplate=[];
        
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
    
    /**
    * Housekeeping method periodically executed by job.php (this script should be called once per minute through a CRON-job)
    * @param    string $vars Initial persistent data space
    * @return   array  Array Updateed persistent data space
    */
    public function job(array $vars):array
    {
        $vars['OK']=$vars['Problem']=[];
        $dateTime=new \DateTime('now');
        for($count=0;$count<6;$count++){
            try{
                $this->oc['SourcePot\Asset\Rates']->getRates($dateTime);
                $vars['OK'][]=$dateTime->format('Y-m-d');
            } catch (\Exception $e){
                $vars['Problem'][]=$e->getMessage();
            }
            $dateTime->sub(new \DateInterval('P'.mt_rand(1,100).'M'));
            usleep(30000);
        }
        return $vars;
    }

    public function str2money($string,string $lang=''):array
    {
        $string=strval($string);
        $asset=new \SourcePot\Asset\Asset();
        $asset->setFromString($string);
        return $asset->getArray();
    }

    public function date2exchageRates($value):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'value'=>$value];
        $rates=new \SourcePot\Asset\Rates();
        $result=['String'=>$value];
        try{
            $dateTime=new \DateTime($value);
            foreach($rates->getRate($dateTime,'*') as $key=>$value){
                if (is_object($value)){
                    $result['DateTime']=$value->format('Y-m-d H:i:s');
                } else {
                    $result[$key]=$value;
                }
            }
        } catch (\Exception $e){
            $this->oc['logger']->log('notice','Function "{class} &rarr; {function}()" failed because date could not be retrieved from "{value}".',$context);
        }
        return $result;
    }

    public function excelDate2exchageRates($value):array
    {
        $unixTimestamp=intval(86400*(floatval($value)-25569));
        return $this->date2exchageRates('@'.$unixTimestamp);
    }

    public function addMoney(array $a,array $b):array
    {
        if (!empty($a['Timestamp'])){
            $dateTime=new \DateTime('@'.$a['Timestamp']);
        } else {
            $dateTime=new \DateTime('now');
        }
        $asset=new \SourcePot\Asset\Asset($a['Amount'],$a['Currency']??'EUR',$dateTime);
        if (!empty($b['Timestamp'])){
            $dateTime=new \DateTime('@'.$b['Timestamp']);
        } else {
            $dateTime=new \DateTime('now');
        }
        $asset->addAsset($b['Amount'],$b['Currency']??'EUR',$dateTime);
        $result=$asset->getArray();
        $result['combineValueCount']=intval($a['combineValueCount']??1)+intval($b['combineValueCount']??1);
        return $result;
    }

}
?>