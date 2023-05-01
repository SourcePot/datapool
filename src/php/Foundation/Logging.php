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

class Logging{
	
	private $arr;
	
	private $logLevelCntr=array(0=>array('Name'=>'Info','Lifespan'=>600,'Callback'=>FALSE,'style'=>'color:#000;'),
								1=>array('Name'=>'Info','Lifespan'=>864000,'Callback'=>FALSE,'style'=>'color:#000;'),
								2=>array('Name'=>'Warning','Lifespan'=>600,'Callback'=>FALSE,'style'=>'color:#840;'),
								3=>array('Name'=>'Warning','Lifespan'=>2592000,'Callback'=>FALSE,'style'=>'color:#840;'),
								4=>array('Name'=>'Error','Lifespan'=>2592000,'Callback'=>FALSE,'style'=>'color:#a80;'),
								5=>array('Name'=>'Error','Lifespan'=>25920000,'Callback'=>FALSE,'style'=>'color:#a80;'),
								6=>array('Name'=>'Threat','Lifespan'=>2592000,'Callback'=>FALSE,'style'=>'color:#a00;'),
								7=>array('Name'=>'Threat','Lifespan'=>25920000,'Callback'=>FALSE,'style'=>'color:#a00;'),
								8=>array('Name'=>'Breach','Lifespan'=>2592000,'Callback'=>FALSE,'style'=>'color:#f00;'),
								9=>array('Name'=>'Breach','Lifespan'=>25920000,'Callback'=>FALSE,'style'=>'color:#f00;'),
								);
	
	private $entryTable;
	private $entryTemplate=array();
	
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		$this->registerToolbox();
		return $this->arr;
	}
	
	public function getEntryTable(){return $this->entryTable;}

	public function getEntryTemplate(){return $this->entryTemplate;}
	
	public function addLog($arr){
		// This function add a log entry.
		// Following keys must be provided:
		// 'msg' ... is the log message
		// 'callingClass' ... is the class which called the log
		// 'callingFunction' ... is the function which called the log
		// Optional keys are:
		// 'priority' ... is set to 1 if not provided, all priorities 
		//	0 to <10 ... logs are visible to the public
		//	10 to <20 ... logs are visible to registered users
		//	30 to <30 ... logs are visible to members
		//	>= 40  ... logs are visible to admin
		if (!isset($arr['callingClass']) || !isset($arr['callingFunction']) || !isset($arr['msg'])){
			throw new \ErrorException('Function '.__FUNCTION__.': Missing callingClass, callingFunction and/or msg key in argument arr.',0,E_ERROR,__FILE__,__LINE__);
		}
		$maxNameLength=40;
		if (isset($arr['priority'])){$arr['priority']=intval($arr['priority']);} else {$arr['priority']=10;}
		if (strlen($arr['msg'])>$maxNameLength){$name=substr($arr['msg'],0,$maxNameLength).'...';} else {$name=$arr['msg'];}
		$level=$arr['priority']%10;
		$logEntry=array('Source'=>$this->entryTable,'Group'=>$_SESSION['currentUser']['EntryId'],'Folder'=>$arr['callingClass'].'::'.$arr['callingFunction'],'Name'=>$name,'Type'=>'log '.$this->logLevelCntr[$level]['Name']);
		$logEntry['Content']=$this->logLevelCntr[$level];
		$logEntry['Content']['Message']=$arr['msg'];
		$logEntry['Content']['User id']=$_SESSION['currentUser']['EntryId'];
		$logEntry['Content']['User name']=$_SESSION['currentUser']['Name'];
		$logEntry['Content']['Priority']=$arr['priority'];
		$logEntry['Content']['Level']=$level;
		if ($level>5){$logEntry['Content']['IP']=$this->getIP(FALSE);} else {$logEntry['Content']['IP']=$this->getIP(TRUE);}
		// set log visibility based on log priority
		if ($arr['priority']<10){
			$readR='ALL_R';
		} else if ($arr['priority']<20){
			$readR='ALL_REGISTERED_R';
		} else if ($arr['priority']<30){
			$readR='ALL_MEMBER_R';
		} else {
			$readR='ADMIN_R';
		}
		$logEntry=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($logEntry,$readR,'ADMIN_R');
		$logEntry['Expires']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','PT'.$this->logLevelCntr[$level]['Lifespan'].'S');
		// Update log instead of adding log if log with same message, from the same IP and same user is detected within 10seconds
		$minPause=round(time()/10);
		$logEntry['EntryId']=md5($logEntry['Content']['Message'].$logEntry['Content']['User id'].$logEntry['Content']['IP'].$minPause);
		// add log to database
		$logEntry=$this->arr['SourcePot\Datapool\Foundation\Database']->insertEntry($logEntry);
		return $logEntry;
	}
	
	private function getIP($hashOnly=TRUE){
		if (array_key_exists('HTTP_X_FORWARDED_FOR',$_SERVER)){
			$ip=$_SERVER["HTTP_X_FORWARDED_FOR"];
		} else if (array_key_exists('REMOTE_ADDR',$_SERVER)){
			$ip=$_SERVER["REMOTE_ADDR"];
		} else if (array_key_exists('HTTP_CLIENT_IP',$_SERVER)){
			$ip=$_SERVER["HTTP_CLIENT_IP"];
		}
		if (empty($ip)){
			return 'empty';
		} else if ($hashOnly){
			$ip=password_hash($ip,PASSWORD_DEFAULT);
		}
		return $ip;
	}

	public function logsToolbox($arr){
		$arr['classWithNamespace']=__CLASS__;
		$arr['method']='logsToolboxHtml';
		$selector=array('Source'=>$this->entryTable,'Type'=>'log%');
		$html=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Logging','generic',$selector,$arr,array());
		return $html;
	}

	public function logsToolboxHtml($arr){
		$maxCount=50;
		$html='';
		$needle=date('Y-m-d').' ';
		$selector=array('Source'=>$this->entryTable,'Type'=>'log%');
		if (!$this->arr['SourcePot\Datapool\Foundation\Access']->isAdmin()){$selector['Group']=$_SESSION['currentUser']['EntryId'];}
		foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Date',FALSE,$maxCount) as $logEntry){
			$levelArr=$logEntry['Content']['Level'];
			$levelArr=$this->logLevelCntr[$levelArr];
			$text=$logEntry['Date'].': '.htmlspecialchars($logEntry['Content']['Message']);
			$text=str_replace($needle,'',$text);
			if ($logEntry['isFirst']){$style=$levelArr['style'].'font-weight:bold;';} else {$style=$levelArr['style'];} 
			$pTagArr=array('tag'=>'p','class'=>'log','style'=>$style,'element-content'=>$text,'keep-element-content'=>TRUE,'source'=>$logEntry['Source'],'elementid'=>$logEntry['EntryId'],'title'=>htmlspecialchars($logEntry['Content']['Message']));
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($pTagArr);
		}
		$divTagArr=array('tag'=>'div','class'=>'log','element-content'=>$html,'keep-element-content'=>TRUE,'id'=>'log-div');
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($divTagArr);
		$wrapperStyle=array('width'=>'100%','margin'=>'0','padding'=>'0','height'=>'80px');
		return array('html'=>$html,'wrapperSettings'=>array('class'=>'toolbox','style'=>$wrapperStyle));
	}	
	
	public function registerToolbox(){
		$toolbox=array('Name'=>'Logs',
					   'Content'=>array('class'=>__CLASS__,'method'=>'logsToolbox','args'=>array('maxCount'=>10),'settings'=>array())
					   );
		$toolbox=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($toolbox,'ALL_R','ADMIN_R');
		$toolbox=$this->arr['SourcePot\Datapool\Foundation\Toolbox']->registerToolbox(__CLASS__,$toolbox);
		if (empty($_SESSION['page state']['toolbox']) && !empty($toolbox['EntryId'])){$_SESSION['page state']['toolbox']=$toolbox['EntryId'];}
		return $toolbox;
	}
	
	public function addLog2entry($entry,$logType='Content log',$logContent=array(),$updateEntry=FALSE){
		if (empty($_SESSION['currentUser']['EntryId'])){$userId='ANONYM';} else {$userId=$_SESSION['currentUser']['EntryId'];}
		if (!isset($entry['Params'][$logType])){$entry['Params'][$logType]=array();}
		$trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,5);	
		$logContent['timestamp']=time();
		$logContent['time']=date('Y-m-d H:i:s');
		$logContent['timezone']=date_default_timezone_get();
		$logContent['method_0']=$trace[1]['class'].'::'.$trace[1]['function'];
		$logContent['method_1']=$trace[2]['class'].'::'.$trace[2]['function'];
		$logContent['method_2']=$trace[3]['class'].'::'.$trace[3]['function'];
		$logContent['userId']=(empty($_SESSION['currentUser']['EntryId']))?'ANONYM':$_SESSION['currentUser']['EntryId'];
		$entry['Params'][$logType][]=$logContent;
		// remove expired logs
		foreach($entry['Params'][$logType] as $logIndex=>$logArr){
			if (!isset($logArr['Expires'])){continue;}
			$expires=strtotime($logArr['Expires']);
			if ($expires<time()){unset($entry['Params'][$logType][$logIndex]);}
		}
		if ($updateEntry){
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
		}
		return $entry;
	}
	
}
?>