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
    
    private const APP_ACCESS='ALL_REGISTERED_R';
    
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
            return array('Category'=>'Admin','Emoji'=>'&#9787;','Label'=>'Account','Read'=>self::APP_ACCESS,'Class'=>__CLASS__);
        } else {
            $html=$this->account();
            $html.=$this->userRols();
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    private function account()
    {
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin()){
            // is admin
            $user=array('Source'=>$this->entryTable,'disableAutoRefresh'=>TRUE,'app'=>__CLASS__);
            $settings=array('orderBy'=>'Privileges','isAsc'=>FALSE,'limit'=>5,'hideUpload'=>TRUE);
            $settings['columns']=[['Column'=>'Name','Filter'=>''],['Column'=>'Content|[]|Contact details|[]|Email','Filter'=>''],['Column'=>'Privileges column','Filter'=>'']];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container(__CLASS__.' accounts','entryList',$user,$settings,[]);    
            $userSelector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            if (isset($userSelector['EntryId'])){
                $user=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($userSelector);
            } else {
                $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
            }
        } else {
            // is non-admin user
            $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        }
        unset($user['app']);
        $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateBySelector($user);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Account','generic',$user,array('classWithNamespace'=>'SourcePot\Datapool\Foundation\User','method'=>'userAccountForm'),[]);    
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin()){
            if (!isset($user['Params'])){
                $html.='Please select a user...';
            }
        }
        return $html;
    }

    private function userRols():string
    {
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin()){
            $selector=['Class'=>'SourcePot\Datapool\Foundation\User','EntryId'=>'userRols'];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('User rols','generic',$selector,array('classWithNamespace'=>__CLASS__,'method'=>'userRolsWidget'),[]);    
        }
        return $html;
    }

    public function userRolsWidget($arr):array
    {
        $arr['html']=$arr['html']??'';
        $userRols=$this->oc['SourcePot\Datapool\Foundation\User']->userRols();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (!empty($formData['cmd'])){
            $userRols['Content']=array_replace_recursive($userRols['Content'],$formData['val']);
            $this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($userRols,TRUE);
        }
        foreach($userRols['Content'] as $index=>$row){
            $userRols['Content'][$index]['Name']=['tag'=>'input','type'=>'text','value'=>$row['Name'],'key'=>[$index,'Name'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
            $userRols['Content'][$index]['Description']=['tag'=>'input','type'=>'text','value'=>$row['Description'],'key'=>[$index,'Description'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$userRols['Content'],'keep-element-content'=>TRUE,'caption'=>'User rols']);
        return $arr;
    }
    
}
?>