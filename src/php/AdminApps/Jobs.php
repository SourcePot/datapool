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

class Jobs implements \SourcePot\Datapool\Interfaces\App{
    
    private const APP_ACCESS='ADMIN_R';
    
    private $oc;
    
    public function __construct($oc){
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return ['Category'=>'Admin','Emoji'=>'&#128119;','Label'=>'Jobs','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            // get page content
            $html=$this->getJobListHtml();
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    } 
    
    private function getJobListHtml():string{
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['trigger'])){
            $arr=['run'=>key($formData['cmd']['trigger'])];
            $arr=$this->oc['SourcePot\Datapool\Foundation\Job']->trigger($arr);
        }
        $matrix=[];
        foreach($this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Job') as $class){
            $matrix[$class]=['Value'=>['tag'=>'button','element-content'=>$class,'keep-element-content'=>TRUE,'style'=>['font-size'=>'0.9rem','width'=>'100%'],'key'=>['trigger',$class],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]];
        }
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'hideKeys'=>TRUE,'hideHeader'=>TRUE]);
        $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($arr['jobVars']??[]);
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE]);
        $html.=$arr['page html']??'';
        return $html;
    }

}
?>