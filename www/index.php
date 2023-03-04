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
set_exception_handler(function(\Throwable $e){
	//fallback page
	$html='';
	$html.='<!DOCTYPE html>';
	$html.='<html xmlns="http://www.w3.org/1999/xhtml" lang="en">';
	$html.='<head>';
	$html.='</head>';
	$html.='<body style="color:#fff;background-color:#444;font-family: Verdana, Arial, Helvetica, sans-serif;font-size:20px;">';
	$html.='<p style="width:fit-content;margin: 20px auto;">We are very sorry for the interruption.</p>';
	$html.='<p style="width:fit-content;margin: 20px auto;">The web page will be up and running as soon as possible.</p>';
	$html.='<p style="width:fit-content;margin: 20px auto;">But some improvements might take a while.</p>';
	$html.='<p style="width:fit-content;margin: 20px auto;">The Admin <span style="font-size:2em;">👷</span></p>';
	$html.='</body>';
	$html.='</html>';
	echo $html;
	// logging
	$err=array('message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'code'=>$e->getCode(),'traceAsString'=>$e->getTraceAsString());
	$logFileContent=json_encode($err);
	$logFileName=$GLOBALS['env']['debugging dir'].time().'_exceptionsLog.json';
	file_put_contents($logFileName,$logFileContent);
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
echo $arr['page html'];
?>