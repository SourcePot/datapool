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
$fromAbsPathRoot=str_replace('/src/www','',$fromAbsPath);
if (strlen($fromAbsPath)===strlen($fromRelPath)){
	$diff=FALSE;
} else {
	$diff=str_replace($fromRelPath,'',$fromAbsPathRoot);
}
$GLOBALS['dirSuffix']=array('root'=>'../../',
							'vendor'=>'../../vendor',
							'src'=>'../',
							'setup'=>'../setup',
							'filespace'=>'../filespace',
							'debugging'=>'../debugging',
							'ftp'=>'../ftp',
							'fonts'=>'../fonts',
							'php'=>'../php',
							'traits'=>'../php/Traits',
							'public'=>'./',
							'media'=>'./media',
							'tmp'=>'./tmp'
							);
$GLOBALS['dirs']=array();
foreach($GLOBALS['dirSuffix'] as $dirName=>$suffix){
	if (strpos($suffix,'../../')===0){
		$prefix='/';
	} else if (strpos($suffix,'../')===0){
		$prefix='/src/';
	} else {
		$prefix='/src/www/';
	}
	$suffix=trim($suffix,'/');
	$cleanSuffix=trim($suffix,'./');
	if (empty($diff)){
		$GLOBALS['relDirs'][$dirName]=$suffix.'/';
	} else {
		$GLOBALS['relDirs'][$dirName]='.'.$diff.$prefix;		
		if (!empty($cleanSuffix)){$GLOBALS['relDirs'][$dirName].=$cleanSuffix.'/';}
	}
	$suffix=trim($suffix,'./');
	$GLOBALS['dirs'][$dirName]=$fromAbsPathRoot.$prefix.$cleanSuffix;
}
// error handling
set_exception_handler(function(\Throwable $e){
	// logging
	if (!is_dir($GLOBALS['dirs']['debugging'])){mkdir($GLOBALS['dirs']['debugging'],0770,TRUE);}
	$err=array('message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'code'=>$e->getCode(),'traceAsString'=>$e->getTraceAsString());
	$logFileContent=json_encode($err);
	$logFileName=$GLOBALS['dirs']['debugging'].'/'.time().'_exceptionsLog.json';
	file_put_contents($logFileName,$logFileContent);
	//fallback page
	$html='';
	$html.='<!DOCTYPE html>';
	$html.='<html xmlns="http://www.w3.org/1999/xhtml" lang="en">';
	$html.='<head>';
	$html.='</head>';
	$html.='<body style="color:#fff;background-color:#444;font-family: Verdana, Arial, Helvetica, sans-serif;font-size:20px;">';
	$html.='<p style="width:fit-content;margin: 20px auto;">We are very sorry for the interruption.</p>';
	$html.='<p style="width:fit-content;margin: 20px auto;">The web page will be up and running as soon as possible.</p>';
	if (strpos($err['message'],'Access denied')===FALSE){
		$html.='<p style="width:fit-content;margin: 20px auto;">But some improvements might take a while.</p>';
	} else {
		$html.='<p style="width:fit-content;margin: 20px auto;">The problem is: '.$err['message'].'</p>';
	}
	$html.='<p style="width:fit-content;margin: 20px auto;">The Admin <span style="font-size:2em;">👷</span></p>';
	$html.='</body>';
	$html.='</html>';
	echo $html;
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