<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Components;

class Logout implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    
    public function __construct($oc){
        $this->oc=$oc;
    }

    public function init(array $oc){
        $this->oc=$oc;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Logout','Emoji'=>'&#10006;','Label'=>'Logout','Read'=>'ALL_REGISTERED_R','Class'=>__CLASS__);
        } else {
            $this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'User logout '.$_SESSION['currentUser']['Name'],'priority'=>11,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));    
            // reset session | keep page state
            $_SESSION=array('page state'=>$_SESSION['page state']);
            session_regenerate_id(TRUE);
            // load Home-app
            header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(array('app'=>'SourcePot\Datapool\Components\Home')));
            exit;
        }
    }

}
?>