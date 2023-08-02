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
	
	private $pageSettings=array();
	
	const MIN_PASSPHRASDE_LENGTH=4;
	
	public function __construct($oc){
		$this->oc=$oc;
	}

	public function init(array $oc){
		$this->oc=$oc;
		$this->pageSettings=$oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
	}

	public function run(array|bool $arr=TRUE):array{
		if ($arr===TRUE){
			return array('Category'=>'Login','Emoji'=>'&#8688;','Label'=>'Login','Read'=>'PUBLIC_R','Class'=>__CLASS__);
		} else {
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
	
	private function loginRequest($arr){
		if (empty($arr['Passphrase']) || empty($arr['Email'])){
        	$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Login failed, password and/or email were empty','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		    return 'Password and/or email password were empty';
        }
		$user['Source']=$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable();
		$user['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']);
		$user=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
		if (empty($user)){return 'Please register';}
		// reset session | keep page state
		$_SESSION=array('page state'=>$_SESSION['page state']);
		session_regenerate_id(TRUE);
		// login check
		if ($this->oc['SourcePot\Datapool\Foundation\Access']->verfiyPassword($arr['Email'],$arr['Passphrase'],$user['LoginId'])){
			$this->loginSuccess($user);
		} else {
			// check one-time password
			$loginEntry=$this->getOneTimeEntry($arr['Email']);
			if (empty($loginEntry)){
				$this->loginFailed($user);
			} else if (strcmp($loginEntry['Name'],$arr['Passphrase'])===0){
				$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($loginEntry,TRUE);
				$this->loginSuccess($user);
			} else {
				$this->loginFailed($user);
			}
		}
	}
	
	private function loginSuccess($user){
		$this->oc['SourcePot\Datapool\Foundation\User']->loginUser($user);
		$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Login successful.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(array('category'=>'Home')));
		exit;
	}

	private function loginFailed($user){
		sleep(5);
		$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Login failed.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(array('category'=>'Login')));
		exit;
	}

	private function registerRequest($arr){
		$err=FALSE;
		if (empty($arr['Passphrase']) || empty($arr['Email'])){
			$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Registration failed, password and/or email were empty','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			$err='Password and/or email password were empty';
		} else {
			$user['Source']=$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable();
			$user['Params']['User registration']=array('Email'=>$arr['Email'],'Date'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime());
			$user['Email']=$arr['Email'];
			$user['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']);
			$existingUser=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
			if ($existingUser){
				$err='You are registered already, try to login.';
			} else {
				$user['LoginId']=$this->oc['SourcePot\Datapool\Foundation\Access']->loginId($arr['Email'],$arr['Passphrase']);
				$user=$this->oc['SourcePot\Datapool\Foundation\User']->newlyRegisteredUserLogin($user);
			}
		}
		if (empty($err)){
			$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'You have been registered as user.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(array('category'=>'Admin')));
			exit;	
		} else {
			$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>$err,'priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
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
				$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Passphrase with '.strlen($arr['Passphrase']).' characters is too short (min. '.self::MIN_PASSPHRASDE_LENGTH.' characters), passphrase update failed.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));	
			} else {
				$existingUser['LoginId']=$this->oc['SourcePot\Datapool\Foundation\Access']->loginId($arr['Email'],$arr['Passphrase']);			
				$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($existingUser);
				$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Passphrase updated.','priority'=>1,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			}
		} else {
			$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'User not found, passphrase update failed.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
	}

	private function pswRequest($arr){
		// check if email is valid
		if (empty($arr['Email'])){
			$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Please provide your email address.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			return 'Failed: invalid email address';
		}
		// check if user exists
		$selector=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']));
		$existingUser=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,TRUE);
		if (empty($existingUser)){
			$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Login-link request for an unknown email.','priority'=>43,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			return 'Failed: unknown email.';
		}
		// create login entry and send email
		$msg=$this->sendOneTimePsw($arr,$existingUser);
		$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>$msg,'priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(array('category'=>'Login')));
		exit;
	}
	
	private function sendOneTimePsw($arr,$user,$isDebugging=TRUE){
		if ($this->getOneTimeEntry($arr['Email'])){
			return 'Nothing was sent. Please use the password you have already received before.';	
		}
		// create login entry
		$loginEntry=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),
						  'Group'=>$this->pageSettings['pageTitle'],
						  'Folder'=>'Login links',
						  'Name'=>$arr['Recovery']['Passphrase'],
						  'EntryId'=>$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']).'-oneTimeLink',
						  'Expires'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','PT10M')
						  );
		$loginEntry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($loginEntry,'ADMIN_R','ADMIN_R');
		// create message
		$placeholder=array('firstName'=>$user['Content']['Contact details']['First name'],
						   'pageTitle'=>$this->pageSettings['pageTitle'],
						   'psw'=>'<b>'.$arr['Recovery']['Passphrase for user'].'</b>'
						   );
		$msg=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lngText("Dear {{firstName}},",$placeholder).'<br/><br/>';
		$msg.=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lngText("You have requested a login token at {{pageTitle}}.",$placeholder).'<br/>';
		$msg.=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lngText("Please use {{psw}} to login.",$placeholder).'<br/>';
		$msg.=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lngText("This token can be used only once and it is valid for approx. 10mins.",$placeholder).'<br/><br/>';
		$msg.=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lngText("Best reagrds,",$placeholder).'<br/>';
		$msg.=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lngText("Admin",$placeholder);
		$html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>$msg,'keep-element-content'=>TRUE,'style'=>'font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:400;line-height:24px;'));
		$html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'html','element-content'=>$html,'keep-element-content'=>TRUE));
		$loginEntry['Content']=array('Message'=>$html);
		$loginEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($loginEntry,TRUE);
		// send email
        $loginEntry['Content']['To']=$arr['Email'];
        $loginEntry['Content']['From']=$this->pageSettings['emailWebmaster'];
        $loginEntry['Content']['Subject']=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lngText("Your one-time password for {{pageTitle}}",$placeholder);
        $mail=array('selector'=>$loginEntry);
        if ($isDebugging){$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($mail);}
		if ($this->oc['SourcePot\Datapool\Tools\Email']->entry2mail($mail)){
			return 'The email was sent, please check your emails.';	
		} else {
			return 'Request failed.';
		}
	}
	
	private function getOneTimeEntry($email){
		$entry=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),
					 'EntryId'=>$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($email).'-oneTimeLink'
					 );
		$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($entry,TRUE);
		return $entry;
	}

}
?>