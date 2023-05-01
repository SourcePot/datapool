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

namespace SourcePot\Datapool\Foundation;

class User{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array('Type'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'user','Description'=>'This is the data-type of Content'),
								 'Privileges'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>1,'Description'=>'Is the user level the user was granted.'),
								 'LoginId'=>array('index'=>FALSE,'type'=>'VARCHAR(512)','value'=>'','Description'=>'Is a login id derived from the passphrase.')
								 );
	
	public $definition=array('Type'=>array('@tag'=>'p','@default'=>'user','@Read'=>'NO_R'),
							 'Icon'=>array('@function'=>'entryControls','@hideDelete'=>TRUE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'),
							 'Content'=>array('Contact details'=>array('Title'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'First name'=>array('@tag'=>'input','@type'=>'text','@default'=>'John','@excontainer'=>TRUE),
																 'Middle name'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Family name'=>array('@tag'=>'input','@type'=>'text','@default'=>'Doe','@excontainer'=>TRUE),
																 'Gender'=>array('@function'=>'select','@options'=>array('male'=>'male','female'=>'female','divers'=>'divers'),'@default'=>'male','@excontainer'=>TRUE),
																 'Language'=>array('@function'=>'select','@options'=>array('en'=>'en','de'=>'de','es'=>'es','fr'=>'fr'),'@default'=>'en','@excontainer'=>TRUE),
																 'Email'=>array('@tag'=>'input','@type'=>'email','@filter'=>FILTER_SANITIZE_EMAIL,'@default'=>'a@b.com','@excontainer'=>TRUE),
																 'Phone'=>array('@tag'=>'input','@type'=>'tel','@default'=>'+49','@excontainer'=>TRUE),
																 'Mobile'=>array('@tag'=>'input','@type'=>'tel','@default'=>'+49','@excontainer'=>TRUE),
																 'Fax'=>array('@tag'=>'input','@type'=>'tel','@default'=>'+49','@excontainer'=>TRUE),
																 'My reference'=>array('@tag'=>'input','@type'=>'text','@default'=>'12345','@excontainer'=>TRUE),
																 'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'),
																 ),
												'Address'=>array('Company'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Department'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Street'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'House number'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Town'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Zip'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Country'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'),
																),
											  ),
							 'Login'=>array('@function'=>'getLoginForm','@class'=>'SourcePot\Datapool\Components\Login'),
							 'Privileges'=>array('@function'=>'setAccessByte','@default'=>1,'@Write'=>'ADMIN_R','@key'=>'Privileges','@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'),
							 'Map'=>array('@function'=>'getMapHtml','@class'=>'SourcePot\Datapool\Tools\GeoTools','@default'=>''),
							 '@hideHeader'=>TRUE,'@hideKeys'=>FALSE,
							 );

	private $userRols=array('Content'=>array(0=>array('Value'=>1,'Name'=>'Public','isAdmin'=>FALSE,'isPublic'=>TRUE,'Description'=>'Everybody not logged in'),
											 1=>array('Value'=>2,'Name'=>'Registered','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Everybody registered'),
											 2=>array('Value'=>4,'Name'=>'Member','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Initial member state'),
											 3=>array('Value'=>8,'Name'=>'Group A','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group A member'),
											 4=>array('Value'=>16,'Name'=>'Group B','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group B member'),
											 5=>array('Value'=>32,'Name'=>'Group C','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group C member'),
											 6=>array('Value'=>64,'Name'=>'Group D','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group D member'),
											 7=>array('Value'=>128,'Name'=>'Group E','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group E member'),
											 8=>array('Value'=>256,'Name'=>'Group F','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group F member'),
											 9=>array('Value'=>512,'Name'=>'Group G','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group G member'),
											 10=>array('Value'=>1024,'Name'=>'Group H','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group H member'),
											 11=>array('Value'=>2048,'Name'=>'Group I','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group I member'),
											 12=>array('Value'=>4096,'Name'=>'Group J','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group J member'),
											 13=>array('Value'=>8192,'Name'=>'Group K','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group K member'),
											 14=>array('Value'=>16384,'Name'=>'Config admin','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Configuration admin'),
											 15=>array('Value'=>32768,'Name'=>'Admin','isAdmin'=>TRUE,'isPublic'=>FALSE,'Description'=>'Administrator')
											 ),
							'Type'=>'array',
							'Read'=>'ALL_R',
							'Write'=>'ADMIN_R',
							);
	
	private $pageSettings=array();
	
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		$this->pageSettings=$this->arr['SourcePot\Datapool\Foundation\Backbone']->getSettings();
		$this->userRols();
		// check database user entry definition 
		$arr['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
		$this->getCurrentUser();
		//
		$this->initAdminAccount();
		return $this->arr;
	}
	
	public function getEntryTable(){return $this->entryTable;}

	public function getEntryTemplate(){return $this->entryTemplate;}
	
	private function userRols(){
		$entry=$this->userRols;
		$entry['Class']=__CLASS__;
		$entry['EntryId']=__FUNCTION__;
		$this->userRols=$this->arr['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($entry,TRUE);
		return $this->userRols;
	}
	
	public function getUserRols(){
		return $this->userRols['Content'];
	}
	
	public function getUserRolsString($user){
		$userRols=array();
		foreach($this->userRols['Content'] as $index=>$rolArrc){
			if ((intval($user['Privileges']) & $rolArrc['Value'])>0){$userRols[]=$rolArrc['Name'];}
		}
		return implode(', ',$userRols);
	}
	
	public function getCurrentUser(){
		if (empty($_SESSION['currentUser']['EntryId']) || empty($_SESSION['currentUser']['Privileges'])){$this->anonymousUserLogin();}
		return $_SESSION['currentUser'];
	}
	
	public function unifyEntry($entry){
		$entry['Source']=$this->entryTable;
		// This function makes class specific corrections before the entry is inserted or updated.
		if (!empty($entry['Email'])){$entry['Content']['Contact details']['Email']=$entry['Email'];}
		if (empty($entry['Params']['User registration']['Email'])){$entry['Params']['User registration']['Email']=$entry['Content']['Contact details']['Email'];}
		$entry['Name']=$this->userAbtract(array('selector'=>$entry),3);
		$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ADMIN_R','ADMIN_R');
		$entry['Group']=$this->pageSettings['pageTitle'];
		$entry['Folder']=$this->getUserRolsString($entry);
		$entry=$this->arr['SourcePot\Datapool\Tools\GeoTools']->address2location($entry);
		$entry=$this->arr['SourcePot\Datapool\Foundation\Definitions']->definition2entry($this->definition,$entry);	
		return $entry;
	}
	
	private function anonymousUserLogin(){
		$user=array('Source'=>$this->entryTable,'Type'=>'user');
		$user['Owner']='ANONYM';
		$user['LoginId']=mt_rand(1,10000000);
		$user['Expires']=date('Y-m-d H:i:s',time()+600);
		$user=$this->arr['SourcePot\Datapool\Foundation\Definitions']->definition2entry($this->definition,$user,FALSE);
		$user['Privileges']=1;
		$user=$this->unifyEntry($user);
		$this->loginUser($user);
		return $user;
	}
	
	private function initAdminAccount(){
		$noAdminAccountFound=empty($this->arr['SourcePot\Datapool\Foundation\Database']->entriesByRight('Privileges','ADMIN_R',TRUE));
		if ($noAdminAccountFound){
			$admin=array('Source'=>$this->entryTable,'Privileges'=>'ADMIN_R','Email'=>$this->pageSettings['emailWebmaster'],'Password'=>bin2hex(random_bytes(16)),'Owner'=>'SYSTEM');
			$admin['EntryId']=$this->arr['SourcePot\Datapool\Foundation\Access']->emailId($admin['Email']);
			$admin['LoginId']=$this->arr['SourcePot\Datapool\Foundation\Access']->loginId($admin['Email'],$admin['Password']);
			$admin['Content']['Contact details']['First name']='Admin';
			$admin['Content']['Contact details']['Family name']='Admin';
			$admin=$this->unifyEntry($admin);
			$success=$this->arr['SourcePot\Datapool\Foundation\Database']->insertEntry($admin);
			if ($success){
				// Save init admin details
				$adminFile=array('Class'=>__CLASS__,'EntryId'=>__FUNCTION__);
				$adminFile['Content']['Admin email']=$admin['Email'];
				$adminFile['Content']['Admin password']=$admin['Password'];
				$access=$this->arr['SourcePot\Datapool\Foundation\Filespace']->updateEntry($adminFile,TRUE);
				$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'No admin account found. I have created a new admin account, the credential can be found in ..\\setup\\User\\'.__FUNCTION__.'.json','priority'=>3,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));	
				return TRUE;
			}
		}
		return FALSE;
	}
	
	public function newlyRegisteredUserLogin($user){
		$user['Owner']=$user['EntryId'];
		$user['LoginId']=$user['LoginId'];
		$user=$this->arr['SourcePot\Datapool\Foundation\Definitions']->definition2entry($this->definition,$user,FALSE);
		$user['Privileges']='REGISTERED_R';
		$user=$this->unifyEntry($user);
		$this->loginUser($user);
		return $user;
	}
	
	public function userAbtract($arr=FALSE,$template=0){
		// This method returns formated html text from an entry based on predefined templates.
		// 	
		if (empty($arr)){
			$user=$_SESSION['currentUser'];
		} else if (!is_array($arr)){
			$user=array('Source'=>$this->entryTable,'EntryId'=>$arr);
		} else if (isset($arr['selector'])){
			$user=$arr['selector'];
		} else {
			$user=$arr;
		}
		if (!isset($user['Content'])){
			if ($template<4){$isSystemCall=TRUE;} else {$isSystemCall=FALSE;}
			$user=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($user,$isSystemCall);
			if (empty($user)){return '';}
		}
		$S=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		if ($template===0){
			$abtract='{{Content'.$S.'Contact details'.$S.'First name}}';
		} else if ($template===1){
			$abtract='{{Content'.$S.'Contact details'.$S.'First name}} {{Content'.$S.'Contact details'.$S.'Family name}}';
		} else if ($template===2){
			$abtract='{{ICON}} [p:{{Content'.$S.'Contact details'.$S.'First name}} {{Content'.$S.'Contact details'.$S.'Family name}}]';
		} else if ($template===3){
			$abtract='{{Content'.$S.'Contact details'.$S.'Family name}}, {{Content'.$S.'Contact details'.$S.'First name}}';
		} else if ($template===4){
			$abtract='{{Content'.$S.'Contact details'.$S.'Family name}}, {{Content'.$S.'Contact details'.$S.'First name}} ({{Content'.$S.'Contact details'.$S.'Email}})';
		} else if ($template===5){
			$abtract='{{Content'.$S.'Contact details'.$S.'Family name}}, {{Content'.$S.'Contact details'.$S.'First name}} ({{Content'.$S.'Address'.$S.'Town}})';
		} else if ($template===6){
			$abtract='{{Content'.$S.'Contact details'.$S.'First name}} {{Content'.$S.'Contact details'.$S.'Family name}} <{{Content'.$S.'Contact details'.$S.'Email}}>';
		}
		$user['ICON']=$this->arr['SourcePot\Datapool\Tools\MediaTools']->getIcon(array('selector'=>$user,'returnHtmlOnly'=>TRUE));
		$abtract=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->template2string($abtract,$user,array('class'=>'user-abstract'));
		if (!empty($arr['wrapResult'])){
			$wrapper=$arr['wrapResult'];
			$wrapper['element-content']=$abtract;
			$wrapper['keep-element-content']=TRUE;
			$abtract=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($wrapper);
		}
		return $abtract;
	}
	
	public function userAccountForm($arr){
		$template=array('html'=>'');
		$arr=array_merge($template,$arr);
		if (isset($arr['selector']['EntryId'])){
			if (!isset($arr['selector']['Type'])){$arr['selector']['Type']='user';}
			$arr['selector']=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector'],TRUE);
			$arr['html'].=$this->arr['SourcePot\Datapool\Foundation\Definitions']->entry2form($arr['selector']);
		}
		return $arr;
	}

	public function loginUser($user){
		$_SESSION['currentUser']=$user;
		if (strcmp($user['Owner'],'ANONYM')!==0){
			$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'User login '.$_SESSION['currentUser']['Name'],'priority'=>11,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));	
		}
	}


}
?>