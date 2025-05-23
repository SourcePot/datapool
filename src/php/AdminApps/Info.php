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
            return array('Category'=>'Admin','Emoji'=>'?','Label'=>'Info','Read'=>self::APP_ACCESS,'Class'=>__CLASS__);
        } else {
            // get page content
            ob_start();
            phpinfo();
            $phpinfo=ob_get_contents();
            ob_get_clean();
            $arr['toReplace']['{{content}}']=str_replace('<table>','<table style="float:none;">',$phpinfo);
            return $arr;
        }
    }
}
?>