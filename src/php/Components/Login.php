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
    
    private $oc;
    
    const MIN_PASSPHRASDE_LENGTH=4;
    
    public function __construct($oc){
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Login','Emoji'=>'&#8614;','Label'=>'Login','Read'=>'PUBLIC_R','Class'=>__CLASS__);
        } else {
            $bgStyle=array('background-image'=>'url(\''.$GLOBALS['relDirs']['assets'].'/login.jpg\')');
            $arr['toReplace']['{{bgMedia}}']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','class'=>'bg-media','style'=>$bgStyle,'element-content'=>' ')).PHP_EOL;
            $arr['toReplace']['{{content}}']=$this->getLoginForm();
            return $arr;
        }
    }
    
    public function getLoginForm(){
        $loginArr=$this->oc['SourcePot\Datapool\Tools\LoginForms']->getLoginForm();
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
            $this->oc['logger']->log('notice','Login failed, password and/or email were empty',array('user'=>$_SESSION['currentUser']['Name']));    
            return 'Password and/or email password were empty';
        }
        // standard user login
        $user=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']));
        $user=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
        if (empty($user)){return 'Please register';}
        // login check
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->verfiyPassword($arr['Email'],$arr['Passphrase'],$user['LoginId'])){
            $this->loginSuccess($user,$arr['Email']);
        } else {
            // has one-time login
            $user=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$this->getOneTimeEntryEntryId($arr['Email']));
            $user=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->verfiyPassword($arr['Email'],$arr['Passphrase'],$user['LoginId'])){
                $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($user,TRUE);
                $this->oc['logger']->log('info','One-time login for {email} at {dateTime} was successful.',array('email'=>$arr['Email'],'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')));    
                $user['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']);
                $this->loginSuccess($user,$arr['Email']);
            } else {
                // one-time login failed
                $this->loginFailed($user,$arr['Email']);
            }  
        }
        // update signals
        $loginCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount(array('Source'=>'logger','Group'=>'error','Name'=>'Login failed%'),TRUE);
        $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'Login failed',$loginCount,'int'); 
        $loginCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount(array('Source'=>'logger','Group'=>'info','Name'=>'Login for%'),TRUE);
        $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'Login ok',$loginCount,'int'); 
        $loginCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount(array('Source'=>'logger','Group'=>'info','Name'=>'One-time login%'),TRUE);
        $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'Login one-time psw',$loginCount,'int'); 
        exit;
    }
    
    private function resetSession(){
        //$_SESSION=array('page state'=>$_SESSION['page state']); // reset session | keep page state
        $_SESSION=array(); // reset session
        session_regenerate_id(TRUE);
    }
    
    private function loginSuccess($user,$email){
        $this->resetSession();
        $this->oc['SourcePot\Datapool\Foundation\User']->loginUser($user);
        $this->oc['logger']->log('info','Login for {email} at {dateTime} was successful.',array('email'=>$email,'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')));    
        header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(array('category'=>'Home')));
    }

    private function loginFailed($user,$email){
        $_SESSION['currentUser']['Privileges']=1;
        $this->oc['logger']->log('notice','Login failed for {email} at {dateTime}.',array('email'=>$email,'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')));    
        sleep(30);
        header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(array('category'=>'Login')));
    }

    private function registerRequest($arr){
        $err=FALSE;
        if (empty($arr['Passphrase']) || empty($arr['Email'])){
            $this->oc['logger']->log('notice','Registration failed, password and/or email were empty.',array());    
            $err='Password and/or email password were empty';
        } else {
            $user['Source']=$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable();
            $user['Params']['User registration']=array('Email'=>$arr['Email'],'Date'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime());
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
            $this->oc['logger']->log('info','You have been registered as new user ({email}) at {dateTime}.',array('email'=>$arr['Email'],'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')));    
            header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(array('category'=>'Admin')));
            exit;    
        } else {
            $this->oc['logger']->log('notice',$err,array('email'=>$arr['Email']));    
        }
        return $err;
    }

    private function updateRequest($arr){
        $user=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),
                    'EntryId'=>$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']),
                    );
        $existingUser=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
        if ($existingUser){
            if (strlen($arr['Passphrase'])<self::MIN_PASSPHRASDE_LENGTH){
                $this->oc['logger']->log('notice','Passphrase with {length} characters is too short (min. {minLength} characters), passphrase update failed.',array('length'=>strlen($arr['Passphrase']),'minLength'=>self::MIN_PASSPHRASDE_LENGTH));    
            } else {
                $existingUser['LoginId']=$this->oc['SourcePot\Datapool\Foundation\Access']->loginId($arr['Email'],$arr['Passphrase']);            
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($existingUser);
                $this->oc['logger']->log('info','Passphrase for "{email}" updated.',array('email'=>$arr['Email']));    
            }
        } else {
            $this->oc['logger']->log('warning','User not found, passphrase update failed.',array());    
        }
    }

    private function pswRequest($arr){
        // check if email is valid
        if (empty($arr['Email'])){
            $this->oc['logger']->log('notice','Please provide your email address.',array());    
            return 'Failed: invalid email address';
        }
        // check if user exists
        $selector=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']));
        $existingUser=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,TRUE);
        if (empty($existingUser)){
            $this->oc['logger']->log('warning','Login-link request for an unknown email.',array());    
            return 'Failed: unknown email.';
        }
        // create login entry and send email
        $this->sendOneTimePsw($arr['Email'],$existingUser);
        header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(array('category'=>'Login')));
        exit;
    }
    
    private function sendOneTimePsw($email,$user)
    {
        if ($this->getOneTimeEntry($email)){
            $this->oc['logger']->log('notice','Nothing was sent. Please use the password you have already received before.',array('email'=>$email));    
            return FALSE;    
        }
        // create login entry
        $pswArr=$this->oc['SourcePot\Datapool\Tools\LoginForms']->getOneTimePswArr();
        $loginEntry=$user;
        $loginEntry['EntryId']=$this->getOneTimeEntryEntryId($email);
        $loginEntry['LoginId']=$this->oc['SourcePot\Datapool\Foundation\Access']->loginId($email,$pswArr['string']);
        $loginEntry['Content']=array('To'=>$email);
        $loginEntry['Content']['From']=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('emailWebmaster');
        $loginEntry['Content']['Subject']='Your one-time password for '.$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');
        $msg='Dear '.$user['Content']['Contact details']['First name'].'.<br/><br/>';
        $msg.='You have requested a login token at '.$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle').'.<br/>';
        $msg.='Please use "<b>'.$pswArr['string'].'</b>" or "<b>'.$pswArr['phrase'].'</b>" to login.<br/>';
        $msg.='This token can be used only once and it is valid for approx. 10mins.<br/><br/>';
        $msg.='Best reagrds,<br/><br/>';
        $msg.='Your Admin';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>$msg,'keep-element-content'=>TRUE,'style'=>'font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:400;line-height:24px;'));
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'html','element-content'=>$html,'keep-element-content'=>TRUE));
        $loginEntry['Content']['Message']=$html;
        $loginEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($loginEntry,FALSE);
        // send email
        $mail=array('selector'=>$loginEntry);
        if ($this->oc['SourcePot\Datapool\Tools\Email']->entry2mail($mail,TRUE)){
            $this->oc['logger']->log('info','The one time passphrase was sent to {email}, please check your emails.',array('email'=>$email));    
            return TRUE;    
        } else {
            $this->oc['logger']->log('notice','The request to send the one time passphrase to {email} has failed.',array('email'=>$email));    
            return FALSE;
        }
    }

    private function getOneTimeEntryEntryId($email):string{
        return $this->oc['SourcePot\Datapool\Foundation\Access']->emailId($email).'-oneTimeLink';
    }

    private function getOneTimeEntry($email)
    {
        $entry=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),
                     'EntryId'=>$this->getOneTimeEntryEntryId($email),
                     );
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($entry,TRUE);
        return $entry;
    }

}
?>