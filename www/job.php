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
	
namespace Datapool;
	
mb_internal_encoding("UTF-8");
	
session_start();
	
// get basic environment information and initialize arr
$basedir=trim(str_replace('\\','/',getcwd()),'/');
$basedir=substr($basedir,0,strrpos($basedir,'/')+1);
$realpath=str_replace('\\','/',realpath('../')).'/';
$GLOBALS['script start time']=hrtime(TRUE);
$GLOBALS['realpath']=$realpath;
$GLOBALS['base dir']=$basedir;
$GLOBALS['debugging dir']=$GLOBALS['realpath'].'debugging/';
// error handling
set_exception_handler(function(\Throwable $e){
	$err=array('message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'code'=>$e->getCode(),'traceAsString'=>$e->getTraceAsString());
	$logFileContent=json_encode($err);
	$logFileName=$GLOBALS['env']['debugging dir'].time().'_exceptionsLog.json';
	file_put_contents($logFileName,$logFileContent);
	echo 'Have run into a problem...';
	exit;
});
set_error_handler(function($errno,$errstr,$errfile,$errline){
	if (!(error_reporting() && $errno)){return;}
	throw new \ErrorException($errstr,$errno,0,$errfile,$errline);
},E_ALL & ~E_WARNING & ~E_NOTICE & ~E_USER_NOTICE);
// load root script, initialize it and call run() function
require_once($GLOBALS['realpath'].'src/Root.php');
$pageObj=new Root();
$arr=$pageObj->run(__FILE__);
// all jobs settings - remove non-existing job methods and add new job methods
$jobs=array('due'=>array(),'undue'=>array());
$allJobsSettingInitContent=array('Last run'=>time(),'Min time in sec between each run'=>600,'Last run time consumption [ms]'=>0);
$allJobsSetting=array('Source'=>$arr['Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Job processing','Folder'=>'All jobs','Name'=>'Timing','Type'=>'array setting');
$allJobsSetting=$arr['Datapool\Tools\StrTools']->addElementId($allJobsSetting,array('Source','Group','Folder','Name','Type'),0);
$allJobsSetting=$arr['Datapool\Foundation\Access']->addRights($allJobsSetting,'ALL_R','ADMIN_R');
$allJobsSetting=$arr['Datapool\Foundation\Database']->entryByKeyCreateIfMissing($allJobsSetting,TRUE);
$allJobsSettingContent=$allJobsSetting['Content'];
$allJobsSetting['Content']=array();
foreach($arr['registered methods']['job'] as $class=>$initContent){
	$initContent=array_merge($allJobsSettingInitContent,$initContent);
	if (isset($allJobsSettingContent[$class])){
		$allJobsSetting['Content'][$class]=$allJobsSettingContent[$class];
	} else {
		$allJobsSetting['Content'][$class]=$initContent;
	}
	$dueTime=time()-($allJobsSetting['Content'][$class]['Last run']+$allJobsSetting['Content'][$class]['Min time in sec between each run']);
	if ($dueTime>0){$jobs['due'][$class]=$dueTime;} else {$jobs['undue'][$class]=$dueTime;}
}
// get most overdue job
$arr['page html']=$arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'h1','element-content'=>'Job processing triggered'));
if (empty($jobs['due'])){
	$matrix=$arr['Datapool\Tools\ArrTools']->arr2matrix($jobs);
	$arr['page html'].=$arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Jobs','keep-element-content'=>TRUE,'hideKeys'=>TRUE));	
} else {
	arsort($jobs['due']);
	reset($jobs['due']);
	$dueJob=key($jobs['due']);
	$dueMethod=$allJobsSetting['Content'][$dueJob]['method'];
	// job var space and run job
	$jobVars=array('Source'=>$arr['Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Job processing','Folder'=>'Var space','Name'=>$dueJob,'Type'=>'array vars');
	$jobVars=$arr['Datapool\Tools\StrTools']->addElementId($jobVars,array('Source','Group','Folder','Name','Type'),0);
	$jobVars=$arr['Datapool\Foundation\Access']->addRights($jobVars,'ADMIN_R','ADMIN_R');
	$jobVars=$arr['Datapool\Foundation\Database']->entryByKeyCreateIfMissing($jobVars,TRUE);
	$jobStartTime=hrtime(TRUE);
	$arr['Datapool\Foundation\Database']->resetStatistic();
	$jobVars['Content']=$arr[$dueJob]->$dueMethod($jobVars['Content']);
	$jobStatistic=$arr['Datapool\Foundation\Database']->getStatistic();
	$allJobsSetting['Content'][$dueJob]['Last run']=time();
	$allJobsSetting['Content'][$dueJob]['Last run time consumption [ms]']=round((hrtime(TRUE)-$jobStartTime)/1000000);
	// update job vars
	$jobVars=$arr['Datapool\Foundation\Database']->updateEntry($jobVars,TRUE);
	// show results
	$matrix=$arr['Datapool\Tools\ArrTools']->arr2matrix($allJobsSetting['Content'][$dueJob]);
	$arr['page html'].=$arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Job done','keep-element-content'=>TRUE,'hideKeys'=>TRUE));
	$matrix=$arr['Datapool\Tools\ArrTools']->arr2matrix($jobStatistic);
	$arr['page html'].=$arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Job statistic','keep-element-content'=>TRUE,'hideKeys'=>TRUE));
}
$allJobsSetting=$arr['Datapool\Foundation\Database']->updateEntry($allJobsSetting,TRUE);
echo $arr['page html'];
?>