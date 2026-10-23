<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\AdminApps;

class Receiver implements \SourcePot\Datapool\Interfaces\App{
    
    private const APP_ACCESS='ALL_CONTENTADMIN_R';
    
    private $oc=[];
    
    private $entryTable='';
    private $entryTemplate=[];
    
    public function __construct(array $oc)
    {
        $this->entryTable=$oc['SourcePot\Datapool\Foundation\User']->getEntryTable();
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\User']->getEntryTemplate();
    }
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }
    
    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return ['Category'=>'Admin','Emoji'=>'R','Label'=>'Receiver','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            $html=$this->getReceiverHtml();
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    private function getReceiverHtml():string
    {
        $html='';
        $arr=[];
        foreach($this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Receiver') as $class){
            $receiverHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h1','element-content'=>$class,'keep-element-content'=>TRUE]);
            $selectorClass=$class;
            if (strpos($class,'Email')!==FALSE){$selectorClass.='-rec';}
            foreach($this->oc['SourcePot\Datapool\Foundation\Filespace']->entryIterator(['Class'=>$selectorClass]) as $entryId=>$entry){
                $referenceEntry=$this->allTableGetEntryById(strval($entryId));
                $headLine=($this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->selector2string($referenceEntry))?:$entryId;
                $receiverHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h1','element-content'=>$headLine,'keep-element-content'=>TRUE]);
                $receiverHtml.=$this->oc[$class]->receiverPluginHtml(['selector'=>$entry]);
            }
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$receiverHtml,'keep-element-content'=>TRUE]);
        }

        return $html;
    }

    private function allTableGetEntryById(string $entryId):array
    {
        foreach($GLOBALS['dbInfo'] as $source=>$infoArr){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(['Source'=>$source,'EntryId'=>$entryId]);
            if (empty($entry)){continue;}
            return $entry;
        }
        return [];
    }
}
?>