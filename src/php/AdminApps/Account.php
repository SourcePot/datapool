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

namespace SourcePot\Datapool\AdminApps;

class Account{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array();
    
	public function __construct($arr){
		$this->entryTable=$arr['SourcePot\Datapool\Foundation\User']->getEntryTable();
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\User']->getEntryTemplate();
		$this->arr=$arr;
	}

	public function init($arr){
		$this->arr=$arr;
		return $this->arr;
	}

	public function job($vars){
		return $vars;
	}
	
	public function run($arr=TRUE){
		if ($arr===TRUE){
			return array('Category'=>'Admin','Emoji'=>'&#9787;','Label'=>'Account','Read'=>'ALL_REGISTERED_R','Class'=>__CLASS__);
		} else {
			$html=$this->account();
			$arr['page html']=str_replace('{{content}}',$html,$arr['page html']);
			return $arr;
		}
	}
	
	private function account(){
		$html='';
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->isAdmin()){
			// is admin
			$user=array('Source'=>$this->entryTable);
			$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('User','entryList',$user,array(),array());	
			$userSelector=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
			if (isset($userSelector['EntryId'])){$user=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($userSelector);} else {$user=array('Source'=>$this->entryTable,'Type'=>'user');}
		} else {
			// is non-admin user
			$user=$_SESSION['currentUser'];
		}
		$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Account','generic',$user,array('classWithNamespace'=>'SourcePot\Datapool\Foundation\User','method'=>'userAccountForm'),array());	
		return $html;
	}
	
	
}
?>