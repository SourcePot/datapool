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

class Transmitter implements \SourcePot\Datapool\Interfaces\App{
    
    private const APP_ACCESS='ALL_CONTENTADMIN_R';
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=[];
    
    public function __construct($oc)
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
            return array('Category'=>'Admin','Emoji'=>'@','Label'=>'Transmitter','Read'=>self::APP_ACCESS,'Class'=>__CLASS__);
        } else {
            $html=$this->getTransmitterHtml();
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    private function getTransmitterHtml():string
    {
        $html='';
        $arr=[];
        foreach($this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Transmitter') as $class){
            $transmitterHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h1','element-content'=>$class,'keep-element-content'=>TRUE]);
            $transmitterHtml.=$this->oc[$class]->transmitterPluginHtml($arr);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$transmitterHtml,'keep-element-content'=>TRUE]);
        }

        return $html;
    }

    
}
?>