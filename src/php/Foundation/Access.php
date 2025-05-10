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

class Access{
    
    private $oc;

    private $access=['NO_R'=>0,
                    'PUBLIC_R'=>1,
                    'REGISTERED_R'=>2,
                    'MEMBER_R'=>4,
                    'ALL_DATA_R'=>65532,
                    'SENTINEL_R'=>1024,
                    'ALL_DATA_SENTINEL_R'=>1272,
                    'ALL_FRIENDS_R'=>768,
                    'ALL_FAMILY_R'=>6144,
                    'PARENTS_R'=>8192,
                    'ADMIN_R'=>32768,
                    'ALL_CONTENTADMIN_R'=>49152,
                    'ALL_REGISTERED_R'=>65534,
                    'ALL_MEMBER_R'=>65532,
                    'ALL_R'=>65535
                    ];
        
    public function __construct($oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $access=['Class'=>__CLASS__,'EntryId'=>__FUNCTION__,'Content'=>$this->access];
        $access=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($access,TRUE);
        $this->access=$access['Content'];
    }
    
    public function getAccessOptions():array
    {
        return $this->access;
    }
    
    public function getAccessOptionsStrings():array
    {
        return array_flip($this->access);
    }
    
    public function accessString2int($string='NO_R'):int
    {
        if (isset($this->access[$string])){
            return $this->access[$string];
        } else {
            return $this->access['NO_R'];
        }
    }

    /**
    * This method adds rights to an entry
    *
    * @return array An associative array that contains the resulting entry
    */
    public function addRights(array $entry,$Read=FALSE,$Write=FALSE,$Privileges=FALSE):array
    {
        //  set defaults if right argument are empty
        $rigthTypes=['Read'=>(empty($Read))?'ALL_CONTENTADMIN_R':$Read,'Write'=>(empty($Write))?'ADMIN_R':$Read,'Privileges'=>(empty($Privileges))?'PUBLIC_R':$Privileges];
        // add rights to the entry
        foreach($rigthTypes as $type=>$default){
            // set to default value
            if (!isset($entry[$type])){$entry[$type]=$default;}
            // set to method argument
            if (isset($this->access[$$type])){$entry[$type]=$this->access[$$type];}
            // replace alias values
            $entry=$this->replaceRightConstant($entry,$type);
            $entry[$type]=intval($entry[$type]);
        }
        return $entry;        
    }
    
    public function replaceRightConstant(array $entry,string $type='Read'):array
    {
        if (!isset($entry[$type])){return $entry;}
        if (is_array($entry[$type])){return $entry;}
        if (isset($this->access[$entry[$type]])){$entry[$type]=$this->access[$entry[$type]];}
        return $entry;
    }

    private function isEntryWithoutContent(array $entry):bool
    {
        if (isset($entry['app'])){unset($entry['app']);}
        return empty($entry);
    }
    
    public function access($entry,$type='Write',$user=FALSE,$isSystemCall=FALSE,$ignoreOwner=FALSE)
    {
        if ($this->isEntryWithoutContent($entry)){
            $this->oc['logger']->log('critical','Function "{class} &rarr; {function}()" access test on empty entry.',array('class'=>__CLASS__,'function'=>__FUNCTION__,'type'=>$type));
            return FALSE;
            //return 'EMPTY ENTRY';
        }
        if ($isSystemCall===TRUE){
            // system call access
            return 'SYSTEMCALL';
        }
        if (empty($entry)){return FALSE;}
        if (empty($user)){
            $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
            if (empty($user)){
                return FALSE;
            }
        }
        if (empty($entry['Owner'])){$entry['Owner']='Entry Owner Missing';}
        if (empty($user['Owner'])){$user['Owner']='User EntryId Missing';}
        if (strcmp($user['Owner'],'SYSTEM')===0 || $ignoreOwner){$user['EntryId']='User id Invalid';}
        if (strcmp($entry['Owner'],$user['EntryId'])===0){
            // owner access
            return 'CREATOR MATCH';
        } else if (isset($entry[$type])){
            // standard access
            $accessLevel=intval($entry[$type]) & intval($user['Privileges']);
            if ($accessLevel>0){return $accessLevel;}
        } else if (!empty($entry['Source'])){
            $this->oc['logger']->log('notice','Function "{class} &rarr; {function}()" missing "entry[{type}]", access to incomplete entry denied',array('class'=>__CLASS__,'function'=>__FUNCTION__,'type'=>$type));
        }
        return FALSE;
    }
    
    public function accessSpecificValue($right,$successValue=TRUE,$failureValue=FALSE,$user=FALSE,$isSystemCall=FALSE,$ignoreOwner=FALSE){
        $accessArr=$this->replaceRightConstant(array('Read'=>$right),'Read');
        if ($this->access($accessArr,'Read',$user,$isSystemCall,$ignoreOwner)){
            return $successValue;
        } else {
            return $failureValue;
        }
    }
    
    public function emailId($email){
        $emailId=md5($email."kjHD1W82IQ9iBS");
        return $emailId;
    }

    public function loginId($email,$password){
        $emailId=$this->emailId($email);
        $userPass=$password.$emailId;
        $loginId=password_hash($userPass,PASSWORD_DEFAULT);
        return $loginId;
    }
    
    public function verfiyPassword($email,$password,$loginId){
        if (empty($email) || empty($password) || empty($loginId)){return FALSE;}
        $emailId=$this->emailId($email);
        $userPass=$password.$emailId;
        if (password_verify($userPass,$loginId)===TRUE){
            $this->rehashPswIfNeeded(array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$emailId),$userPass,$loginId);
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    private function rehashPswIfNeeded($user,$userPass,$loginId){
        if (password_needs_rehash($loginId,PASSWORD_DEFAULT)){
            $user['LoginId']=password_hash($userPass,PASSWORD_DEFAULT);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($user,TRUE);
            $this->oc['logger']->log('warning','User account login id for {EntryId}" was rehashed.',array('EntryId'=>$user['EntryId']));    
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function isAdmin($user=FALSE){
        return $this->hasRights($user,'ADMIN_R');
    }
    
    public function isContentAdmin($user=FALSE){
        return $this->hasRights($user,'ALL_CONTENTADMIN_R');
    }
    
    public function isMember($user=FALSE){
        return $this->hasRights($user,'ALL_MEMBER_R');
    }
    
    public function isPublic($user=FALSE){
        return $this->hasRights($user,'PUBLIC_R');
    }
    
    public function isRegistered($user=FALSE){
        return $this->hasRights($user,'REGISTERED_R');
    }
    
    public function hasRights($user=FALSE,string $right='ADMIN_R')
    {
        if (empty($user)){
            $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
            if (empty($user)){return FALSE;}
        }
        if (($user['Privileges'] & $this->access[$right])>0){
            return TRUE;
        } else {
            return FALSE;
        }    
    }

    public function hasAccess($user=FALSE,int $right=32768)
    {
        if (empty($user)){
            $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
            if (empty($user)){return FALSE;}
        }
        if (($user['Privileges'] & $right)>0){
            return TRUE;
        } else {
            return FALSE;
        }    
    }
    
    public function rightsHtml(array $arr,string $right='Privileges'):string
    {
        $arr['html']='';
        if (isset($arr['selector'][$right])){
            foreach($this->oc['SourcePot\Datapool\Foundation\User']->getUserRols(TRUE) as $value=>$name){
                if ((intval($value) & intval($arr['selector'][$right]))>0){
                    if (!empty($arr['html'])){$arr['html'].='<br/>';}
                    if (stripos($name,'admin')!==FALSE){
                        $arr['html'].='<span style="font-weight:bold;color:red;">'.$name.'</span>';
                    } else {
                        $arr['html'].=$name;
                    }
                }
            }
            if ($arr['html']==='Registered'){
                $arr['html']='<span style="font-weight:bold;color:green;">'.$arr['html'].'</span>';
            }
        } else {
            $arr['html']='{Nothing here...}';
        }
        return $arr['html'];
    }

}
?>