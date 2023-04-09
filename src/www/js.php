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
	
namespace SourcePot\Datapool;
	
mb_internal_encoding("UTF-8");
	
session_start();
	
// get basic environment information and initialize arr
$GLOBALS['script start time']=hrtime(TRUE);
$fromRelPath=strtr(realpath('./'),array('\\'=>'/'));
$fromAbsPath=strtr(__DIR__,array('\\'=>'/'));
$fromAbsPathRoot=substr($fromAbsPath,0,strrpos($fromAbsPath,'/www'));
$fromAbsPathRoot=substr($fromAbsPath,0,strrpos($fromAbsPath,'/src'));
if (strlen($fromRelPath)<strlen($fromAbsPathRoot)){
	$diff=str_replace($fromRelPath,'',$fromAbsPathRoot);
} else {
	$diff=FALSE;
}
$GLOBALS['dirSuffix']=array('root'=>'../..',
							'vendor'=>'../vendor',
							'src'=>'..',
							'setup'=>'../setup',
							'filespace'=>'../filespace',
							'debugging'=>'../debugging',
							'ftp'=>'../ftp',
							'fonts'=>'../fonts',
							'php'=>'../php',
							'traits'=>'../php/Traits',
							'public'=>'.',
							'media'=>'./media',
							'tmp'=>'./tmp'
							);
$GLOBALS['dirs']=array();
foreach($GLOBALS['dirSuffix'] as $dirName=>$suffix){
	if (strpos($suffix,'../../')===0){
		$prefix='';
	} else if (strpos($suffix,'../')===0){
		$prefix='/src/';
	} else {
		$prefix='/src/www/';
	}
	$GLOBALS['dirs'][$dirName]=$fromAbsPathRoot.$prefix.ltrim($suffix,'./');
	if ($diff){
		$GLOBALS['relDirs'][$dirName]='.'.$diff.$prefix.ltrim($suffix,'./').'/';		
	} else {
		$GLOBALS['relDirs'][$dirName]=$suffix.'/';
	}
}
// error handling
set_exception_handler(function(\Throwable $e){
	if (!is_dir($GLOBALS['dirs']['debugging'])){mkdir($GLOBALS['dirs']['debugging'],0770,TRUE);}
	$err=array('message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'code'=>$e->getCode(),'traceAsString'=>$e->getTraceAsString());
	$logFileContent=json_encode($err);
	$logFileName=$GLOBALS['dirs']['debugging'].'/'.time().'_exceptionsLog.json';
	file_put_contents($logFileName,$logFileContent);
	echo 'Have run into a problem...';
	exit;
});
set_error_handler(function($errno,$errstr,$errfile,$errline){
	if (!(error_reporting() && $errno)){return;}
	throw new \ErrorException($errstr,$errno,0,$errfile,$errline);
},E_ALL & ~E_WARNING & ~E_NOTICE & ~E_USER_NOTICE);
// load root script, initialize it and call run() function
require_once($GLOBALS['dirs']['php'].'/Root.php');
$pageObj=new Root();
$arr=$pageObj->run(__FILE__);
echo $arr['page html'];
?>