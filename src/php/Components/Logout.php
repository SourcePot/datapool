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

    private const APP_ACCESS='ALL_REGISTERED_R';
    
    private $oc;
    
    public function __construct($oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return array('Category'=>'Logout','Emoji'=>'&#10006;','Label'=>'Logout','Read'=>self::APP_ACCESS,'Class'=>__CLASS__);
        } else {
            $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
            $this->oc['logger']->log('info','User logout {user} at {dateTime}',['user'=>$user['Name'],'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')]);
            // reset session | keep page state
            $_SESSION=array('page state'=>$_SESSION['page state']);
            session_regenerate_id(TRUE);
            // load Home-app
            header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(['app'=>'SourcePot\Datapool\Components\Home']));
            exit;
        }
    }

}
?>