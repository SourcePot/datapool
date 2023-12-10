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

class Account implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array();
    
    public function __construct($oc){
        $this->entryTable=$oc['SourcePot\Datapool\Foundation\User']->getEntryTable();
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\User']->getEntryTemplate();
    }

    public function init(array $oc){
        $this->oc=$oc;
    }
    
    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Admin','Emoji'=>'&#9787;','Label'=>'Account','Read'=>'ALL_REGISTERED_R','Class'=>__CLASS__);
        } else {
            $html=$this->account();
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    private function account(){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin()){
            // is admin
            $user=array('Source'=>$this->entryTable,'disableAutoRefresh'=>TRUE);
            $settings=array('orderBy'=>'Privileges','isAsc'=>FALSE,'limit'=>5,'hideUpload'=>TRUE);
            $settings['columns']=array(array('Column'=>'Name','Filter'=>''),array('Column'=>'Content|[]|Contact details|[]|Email','Filter'=>''),array('Column'=>'Privileges','Filter'=>''));
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container(__CLASS__.' accounts','entryList',$user,$settings,array());    
            $class=$this->oc['SourcePot\Datapool\Root']->source2class($user['Source']);
            $userSelector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($class);
            if (isset($userSelector['EntryId'])){
                $user=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($userSelector);
            } else {
                $user=$_SESSION['currentUser'];
            }
        } else {
            // is non-admin user
            $user=$_SESSION['currentUser'];
        }
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Account','generic',$user,array('classWithNamespace'=>'SourcePot\Datapool\Foundation\User','method'=>'userAccountForm'),array());    
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin()){
            if (isset($user['Params'])){
                $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryLogs(array('selector'=>$user));
            } else {
                $html.='Please select a user...';
            }
        }
        return $html;
    }
    
    public function clientAccessTest($arr){
        $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file(array('_FILES'=>$_FILES,'arr'=>$arr));
        $arr=array('console'=>'Datapool timestamp: '.time());
        return $arr;
    }
    
    
}
?>