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

class Login implements \SourcePot\Datapool\Interfaces\App{
    
    private const APP_ACCESS='PUBLIC_R';
    
    private $oc;
    
    const MIN_PASSPHRASDE_LENGTH=4;
    
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
            return ['Category'=>'Login','Emoji'=>'&#8614;','Label'=>'Login','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            // update signals - normal login
            $loginCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount(['Source'=>'logger','Name'=>'Login for%'],TRUE);
            $description='Login count within a time span defined by: '.\SourcePot\Datapool\Foundation\Logger::LOG_LEVEL_CONFIG['info']['lifetime'];
            $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'Login ok',$loginCount,'int',['description'=>$description]);
            // login with one-time token
            $description='One-time token login count within a time span defined by: '.\SourcePot\Datapool\Foundation\Logger::LOG_LEVEL_CONFIG['info']['lifetime'];
            $loginCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount(['Source'=>'logger','Name'=>'One-time login%'],TRUE);
            $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'Login one-time psw',$loginCount,'int',['description'=>$description]); 
            // failed login
            $description='Faild login count within a time span defined by: '.\SourcePot\Datapool\Foundation\Logger::LOG_LEVEL_CONFIG['notice']['lifetime'];
            $loginCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount(['Source'=>'logger','Name'=>'Login failed%'],TRUE);
            $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'Login failed',$loginCount,'int',['description'=>$description]);
            // registration
            $description='Registrations within a time span defined by: '.\SourcePot\Datapool\Foundation\Logger::LOG_LEVEL_CONFIG['info']['lifetime'];
            $loginCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount(['Source'=>'logger','Name'=>'%registered as new user%'],TRUE);
            $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'New registration',$loginCount,'int',['description'=>$description]); 
            // compile page
            $bgStyle=['background-image'=>'url(\''.$GLOBALS['relDirs']['assets'].'/login.jpg\')'];
            $arr['toReplace']['{{bottomArticle}}']='';
            $arr['toReplace']['{{bgMedia}}']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','class'=>'bg-media','style'=>$bgStyle,'element-content'=>' ']).PHP_EOL;
            $arr['toReplace']['{{content}}']=$this->getLoginFormHtml([]);
            return $arr;
        }
    }
    
    public function getLoginFormHtml(array $arr):string
    {
        $loginArr=$this->oc['SourcePot\Datapool\Tools\LoginForms']->getLoginForm($arr);
        //$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($loginArr['result'],hrtime(TRUE).'-'.__FUNCTION__);
        if (strcmp($loginArr['result']['cmd'],'Login')===0){
            $this->loginRequest($loginArr['result']);
        } else if (strcmp($loginArr['result']['cmd'],'Register')===0){
            $this->registerRequest($loginArr['result']);
        } else if (strcmp($loginArr['result']['cmd'],'pswRequest')===0){
            $this->pswRequest($loginArr['result']);
        } else if (strcmp($loginArr['result']['cmd'],'Update')===0){
            $this->updateRequest($loginArr['result']);
        }
        return $loginArr['html'];
    }
    
    private function loginRequest(array $arr)
    {
        if (empty($arr['Passphrase']) || empty($arr['Email'])){
            $this->oc['logger']->log('notice','Login failed, password and/or email were empty',['lifetime'=>'P30D']);    
            return 'Password and/or email password were empty';
        }
        // standard user login
        $user=['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email'])];
        $user=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
        if (empty($user)){return 'Please register';}
        // login check
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->verfiyPassword($arr['Email'],$arr['Passphrase'],$user['LoginId'])){
            $this->loginSuccess($user,$arr['Email']);
        } else {
            // has one-time login
            $user=['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$this->getOneTimeEntryEntryId($arr['Email'])];
            $user=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->verfiyPassword($arr['Email'],$arr['Passphrase'],$user['LoginId'])){
                $this->oc['logger']->log('info','One-time login "{email}" at "{dateTime}" was successful.',['lifetime'=>'P30D','email'=>$arr['Email'],'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')]);    
                // delete temporary user, switch back to original user and login
                $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($user,TRUE);
                $user['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']);
                $this->loginSuccess($user,$arr['Email']);
            } else {
                // one-time login failed
                $this->loginFailed($user,$arr['Email']);
            }  
        }
        exit;
    }
    
    private function resetSession()
    {
        $_SESSION=[]; // reset session
        session_regenerate_id(TRUE);
    }
    
    private function loginSuccess($user,$email)
    {
        $this->resetSession();
        // return to login page
        if ($this->oc['SourcePot\Datapool\Components\TwoFactorAuthentication']->isTwoFactorAuthenticationRequired($user)){
            $user['Privileges']=2;
            $this->oc['SourcePot\Datapool\Foundation\User']->loginUser($user);
            $_SESSION['page state']['selectedApp']['Login']['Class']='SourcePot\Datapool\Components\TwoFactorAuthentication';
            header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(['category'=>'Login']));
        } else {
            $this->oc['SourcePot\Datapool\Foundation\User']->loginUser($user);
            $this->oc['logger']->log('info','Login for "{email}" at "{dateTime}" was successful.',['lifetime'=>'P30D','email'=>$email,'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')]);    
            header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(['category'=>'Home']));
        }
    }

    private function loginFailed($user,$email)
    {
        $_SESSION['currentUser']=[];
        $this->oc['SourcePot\Datapool\Root']->updateCurrentUser();
        $this->oc['logger']->log('notice','Login failed at "{dateTime}" for "{email}".',['lifetime'=>'P30D','email'=>$email,'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')]);    
        // return to login page
        sleep(30);
        header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(['category'=>'Login']));
    }

    private function registerRequest($arr)
    {
        $err=FALSE;
        if (empty($arr['Passphrase']) || empty($arr['Email'])){
            $this->oc['logger']->log('notice','Registration failed, password and/or email were empty.',['lifetime'=>'P30D']);    
            $err='Password and/or email password were empty';
        } else {
            $user['Source']=$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable();
            $user['Params']['User registration']=['Email'=>$arr['Email'],'Date'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime()];
            $user['Email']=$arr['Email'];
            $user['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']);
            $existingUser=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
            if ($existingUser){
                $err='You ({email}) are registered already, try to login.';
            } else {
                $user['LoginId']=$this->oc['SourcePot\Datapool\Foundation\Access']->loginId($arr['Email'],$arr['Passphrase']);
                $user=$this->oc['SourcePot\Datapool\Foundation\User']->newlyRegisteredUserLogin($user);
            }
        }
        if (empty($err)){
            $this->oc['logger']->log('info','You have been registered as new user "{email}" at "{dateTime}".',['lifetime'=>'P30D','email'=>$arr['Email'],'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')]);    
            header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(['category'=>'Admin']));
        } else {
            $this->oc['logger']->log('notice',$err,['lifetime'=>'P30D','email'=>$arr['Email']]);    
            header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(['category'=>'Login']));
        }
        return $err;
    }

    private function updateRequest($arr)
    {
        $user=[
            'Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),
            'EntryId'=>$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']),
        ];
        $existingUser=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
        if ($existingUser){
            if (strlen($arr['Passphrase'])<self::MIN_PASSPHRASDE_LENGTH){
                $this->oc['logger']->log('notice','Passphrase with {length} characters is too short (min. {minLength} characters), passphrase update failed.',['length'=>strlen($arr['Passphrase']),'minLength'=>self::MIN_PASSPHRASDE_LENGTH]);    
            } else {
                $existingUser['LoginId']=$this->oc['SourcePot\Datapool\Foundation\Access']->loginId($arr['Email'],$arr['Passphrase']);            
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($existingUser);
                $this->oc['logger']->log('info','Passphrase for "{email}" updated.',['lifetime'=>'P30D','email'=>$arr['Email']]);    
            }
        } else {
            $this->oc['logger']->log('warning','User not found, passphrase update failed.',['lifetime'=>'P30D']);    
        }
    }

    private function pswRequest($arr)
    {
        // check if email is valid
        if (empty($arr['Email'])){
            $this->oc['logger']->log('notice','Please provide your email address.',[]);    
            return 'Failed: invalid email address';
        }
        // check if user exists
        $selector=['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email'])];
        $existingUser=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,TRUE);
        if (empty($existingUser)){
            $this->oc['logger']->log('warning','Login-link request for an unknown email.',['lifetime'=>'P30D']);    
            return 'Failed: unknown email.';
        }
        // create login entry and send email
        $this->sendOneTimePsw($arr['Email'],$existingUser);
        header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(['category'=>'Login']));
        exit;
    }
    
    private function sendOneTimePsw($email,$user):bool
    {
        if ($this->getOneTimeEntry($email)){
            $this->oc['logger']->log('notice','Nothing was sent. Please use the password you have already received before.',['lifetime'=>'P30D','email'=>$email]);    
            return FALSE;    
        }
        // create login entry
        $pswArr=$this->oc['SourcePot\Datapool\Tools\LoginForms']->getOneTimePswArr();
        $loginEntry=$user;
        $loginEntry['EntryId']=$this->getOneTimeEntryEntryId($email);
        $loginEntry['LoginId']=$this->oc['SourcePot\Datapool\Foundation\Access']->loginId($email,$pswArr['string']);
        $loginEntry['Content']=['To'=>$email];
        $loginEntry['Content']['From']=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('emailWebmaster');
        $loginEntry['Content']['Subject']='Your one-time password for '.$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');
        $msg='Dear '.$user['Content']['Contact details']['First name'].'.<br/><br/>';
        $msg.='You have requested a login token at '.$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle').'.<br/>';
        $msg.='Please use "<b>'.$pswArr['string'].'</b>" or "<b>'.$pswArr['phrase'].'</b>" to login.<br/>';
        $msg.='This token can be used only once and it is valid for approx. 10mins.<br/><br/>';
        $msg.='Best reagrds,<br/><br/>';
        $msg.='Your Admin';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'p','element-content'=>$msg,'keep-element-content'=>TRUE,'style'=>'font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:400;line-height:24px;']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'html','element-content'=>$html,'keep-element-content'=>TRUE]);
        $loginEntry['Content']['Message']=$html;
        // send email
        if ($this->oc['SourcePot\Datapool\Tools\Email']->send($user['EntryId'],$loginEntry)){
            $loginEntry['Content']['Contact details']=$user['Content']['Contact details'];
            $loginEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($loginEntry,FALSE);
            $this->oc['logger']->log('info','The one time passphrase was sent to "{email}", please check your emails.',['lifetime'=>'P30D','email'=>$email]);    
            return TRUE;    
        } else {
            $this->oc['logger']->log('notice','The request to send the one time passphrase to "{email}" has failed.',['lifetime'=>'P30D','email'=>$email]);    
            return FALSE;
        }
    }

    private function getOneTimeEntryEntryId($email):string
    {
        return $this->oc['SourcePot\Datapool\Foundation\Access']->emailId($email).'-oneTimeLink';
    }

    private function getOneTimeEntry($email)
    {
        $entry=['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$this->getOneTimeEntryEntryId($email),];
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($entry,TRUE);
        return $entry;
    }

}
?>