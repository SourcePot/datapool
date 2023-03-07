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

namespace SourcePot\Datapool\Components;

class Logout{
	
	private $arr;
	
	public function __construct($arr){
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
			return array('Category'=>'Logout','Emoji'=>'&#10006;','Label'=>'Logout','Read'=>'ALL_REGISTERED_R','Class'=>__CLASS__);
		} else {
			$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'User logout '.$_SESSION['currentUser']['Name'],'priority'=>11,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));	
			$this->arr['SourcePot\Datapool\Tools\NetworkTools']->resetSession();
			// load Home-app
			header("Location: ".$this->arr['SourcePot\Datapool\Tools\NetworkTools']->href(array('app'=>'SourcePot\Datapool\Components\Home')));
			exit;
			return $arr;
		}
	}

}
?>