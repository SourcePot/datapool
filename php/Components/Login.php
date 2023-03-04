<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace Datapool\Components;

class Login{
	
	private $arr;
	
	private $pageSettings=array();
	
	const MIN_PASSPHRASDE_LENGTH=4;
	
	public function __construct($arr){
		$this->arr=$arr;
	}

	public function init($arr){
		$this->arr=$arr;
		$this->pageSettings=$this->arr['Datapool\Tools\HTMLbuilder']->getSettings();
		return $this->arr;
	}

	public function job($vars){
		return $vars;
	}

	public function run($arr=TRUE){
		if ($arr===TRUE){
			return array('Category'=>'Login','Emoji'=>'&#8688;','Label'=>'Login','Read'=>'PUBLIC_R','Class'=>__CLASS__);
		} else {
			$arr['page html']=str_replace('{{content}}',$this->getLoginForm(),$arr['page html']);
			return $arr;
		}
	}
	
	public function getLoginForm(){
		$loginArr=$this->arr['Datapool\Tools\LoginForms']->getLoginForm();
		//$this->arr['Datapool\Tools\ArrTools']->arr2file($loginArr['result'],hrtime(TRUE).'-'.__FUNCTION__);
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
		if (empty($arr['Passphrase']) || empty($arr['Email'])){return 'Password and/or email password were empty';}
		$user['Source']=$this->arr['Datapool\Foundation\User']->getEntryTable();
		$user['ElementId']=$this->arr['Datapool\Foundation\Access']->emailId($arr['Email']);
		$user=$this->arr['Datapool\Foundation\Database']->entryByKey($user,TRUE);
		if (empty($user)){return 'Please register';}
		if ($this->arr['Datapool\Foundation\Access']->verfiyPassword($arr['Email'],$arr['Passphrase'],$user['LoginId'])){
			$this->loginSuccess($user);
		} else {
			// check one-time password
			$loginEntry=$this->getOneTimeEntry($arr['Email']);
			if (empty($loginEntry)){
				$this->loginFailed($user);
			} else if (strcmp($loginEntry['Name'],$arr['Passphrase'])===0){
				$this->arr['Datapool\Foundation\Database']->deleteEntries($loginEntry,TRUE);
				$this->loginSuccess($user);
			} else {
				$this->loginFailed($user);
			}
		}
	}
	
	private function loginSuccess($user){
		$this->arr['Datapool\Foundation\User']->loginUser($user);
		$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'Login successful.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		header("Location: ".$this->arr['Datapool\Tools\NetworkTools']->href(array('category'=>'Home')));
		exit;
	}

	private function loginFailed($user){
		sleep(5);
		$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'Login failed.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		header("Location: ".$this->arr['Datapool\Tools\NetworkTools']->href(array('category'=>'Login')));
		exit;
	}

	private function registerRequest($arr){
		$err=FALSE;
		if (empty($arr['Passphrase']) || empty($arr['Email'])){
			$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'Password and/or email password were empty','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			$err='Password and/or email password were empty';
		} else {
			$user['Source']=$this->arr['Datapool\Foundation\User']->getEntryTable();
			$user['Params']['User registration']=array('Email'=>$arr['Email'],'Date'=>$this->arr['Datapool\Tools\StrTools']->getDateTime());
			$user['Email']=$arr['Email'];
			$user['ElementId']=$this->arr['Datapool\Foundation\Access']->emailId($arr['Email']);
			$existingUser=$this->arr['Datapool\Foundation\Database']->entryByKey($user,TRUE);
			if ($existingUser){
				$err='You are registered already, try to login.';
			} else {
				$user['LoginId']=$this->arr['Datapool\Foundation\Access']->loginId($arr['Email'],$arr['Passphrase']);
				$user=$this->arr['Datapool\Foundation\User']->newlyRegisteredUserLogin($user);
				$this->arr['Datapool\Foundation\Database']->updateEntry($user,TRUE);
			}
		}
		if (empty($err)){
			$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'You have been registered as user.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			header("Location: ".$this->arr['Datapool\Tools\NetworkTools']->href(array('category'=>'Admin')));
			exit;	
		} else {
			$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>$err,'priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
		return $err;
	}

	private function updateRequest($arr){
		$user=array('Source'=>$this->arr['Datapool\Foundation\User']->getEntryTable(),
					'ElementId'=>$this->arr['Datapool\Foundation\Access']->emailId($arr['Email']),
					);
		$existingUser=$this->arr['Datapool\Foundation\Database']->entryByKey($user,TRUE);
		if ($existingUser){
			if (strlen($arr['Passphrase'])<self::MIN_PASSPHRASDE_LENGTH){
				$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'Passphrase with '.strlen($arr['Passphrase']).' characters is too short (min. '.self::MIN_PASSPHRASDE_LENGTH.' characters), passphrase update failed.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));	
			} else {
				$existingUser['LoginId']=$this->arr['Datapool\Foundation\Access']->loginId($arr['Email'],$arr['Passphrase']);			
				$this->arr['Datapool\Foundation\Database']->updateEntry($existingUser);
				$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'Passphrase updated.','priority'=>1,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			}
		} else {
			$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'User not found, passphrase update failed.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
	}

	private function pswRequest($arr){
		// check if email is valid
		if (empty($arr['Email'])){
			$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'Please provide your email address.','priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			return 'Failed: invalid email address';
		}
		// check if user exists
		$selector=array('Source'=>$this->arr['Datapool\Foundation\User']->getEntryTable(),'ElementId'=>$this->arr['Datapool\Foundation\Access']->emailId($arr['Email']));
		$existingUser=$this->arr['Datapool\Foundation\Database']->entryByKey($selector,TRUE);
		if (empty($existingUser)){
			$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'Login-link request for an unknown email.','priority'=>43,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			return 'Failed: unknown email.';
		}
		// create login entry and send email
		$msg=$this->sendOneTimePsw($arr,$existingUser);
		$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>$msg,'priority'=>2,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		header("Location: ".$this->arr['Datapool\Tools\NetworkTools']->href(array('category'=>'Login')));
		exit;
	}
	
	private function sendOneTimePsw($arr,$user,$isDebugging=TRUE){
		if ($this->getOneTimeEntry($arr['Email'])){
			return 'Nothing was sent. Please use the password you have already received before.';	
		}
		// create login entry
		$loginEntry=array('Source'=>$this->arr['Datapool\Foundation\User']->getEntryTable(),
						  'Group'=>$this->pageSettings['pageTitle'],
						  'Folder'=>'Login links',
						  'Name'=>$arr['Recovery']['Passphrase'],
						  'ElementId'=>$this->arr['Datapool\Foundation\Access']->emailId($arr['Email']).'-oneTimeLink',
						  'Expires'=>$this->arr['Datapool\Tools\StrTools']->getDateTime(600)
						  );
		$loginEntry=$this->arr['Datapool\Foundation\Access']->addRights($loginEntry,'ADMIN_R','ADMIN_R');
		// create message
		$placeholder=array('firstName'=>$user['Content']['Contact details']['First name'],
						   'pageTitle'=>$this->pageSettings['pageTitle'],
						   'psw'=>'<b>'.$arr['Recovery']['Passphrase for user'].'</b>'
						   );
		$msg=$this->arr['Datapool\Foundation\Dictionary']->lngText("Dear {{firstName}},",$placeholder).'<br/><br/>';
		$msg.=$this->arr['Datapool\Foundation\Dictionary']->lngText("You have requested a one-time password at {{pageTitle}}.",$placeholder).'<br/>';
		$msg.=$this->arr['Datapool\Foundation\Dictionary']->lngText("Please use {{psw}} to login.",$placeholder).'<br/>';
		$msg.=$this->arr['Datapool\Foundation\Dictionary']->lngText("This password can be used only once and it is valid for approx. 10mins.",$placeholder).'<br/><br/>';
		$msg.=$this->arr['Datapool\Foundation\Dictionary']->lngText("Best reagrds,",$placeholder).'<br/>';
		$msg.=$this->arr['Datapool\Foundation\Dictionary']->lngText("Admin",$placeholder);
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>$msg,'keep-element-content'=>TRUE,'style'=>'font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:400;line-height:24px;'));
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'html','element-content'=>$html,'keep-element-content'=>TRUE));
		$loginEntry['Content']=array('Message'=>$html);
		$loginEntry=$this->arr['Datapool\Foundation\Database']->updateEntry($loginEntry,TRUE);
		// send email
		$mail=array('To'=>$arr['Email'],'From'=>$this->pageSettings['emailWebmaster'],'selector'=>$loginEntry);
		$mail['Subject']=$this->arr['Datapool\Foundation\Dictionary']->lngText("Your one-time password for {{pageTitle}}",$placeholder);
		if ($isDebugging){$this->arr['Datapool\Tools\ArrTools']->arr2file($mail);}
		if ($this->arr['Datapool\Tools\NetworkTools']->entry2mail($mail)){
			return 'The email was sent, please check your emails.';	
		} else {
			return 'Request failed.';
		}
	}
	
	private function getOneTimeEntry($email){
		$entry=array('Source'=>$this->arr['Datapool\Foundation\User']->getEntryTable(),
					 'ElementId'=>$this->arr['Datapool\Foundation\Access']->emailId($email).'-oneTimeLink'
					 );
		$entry=$this->arr['Datapool\Foundation\Database']->entryByKey($entry,TRUE);
		return $entry;
	}

}
?>