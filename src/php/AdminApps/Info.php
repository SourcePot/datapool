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

class Info implements \SourcePot\Datapool\Interfaces\App{
    
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
            return array('Category'=>'Admin','Emoji'=>'?','Label'=>'Info','Read'=>'ADMIN_R','Class'=>__CLASS__);
        } else {
            // get page content
            phpinfo();
            $arr['toReplace']['{{content}}']='Refer to the phpinfo() tables above';
            return $arr;
        }
    }
}
?>