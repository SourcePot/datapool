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
// load root script, initialize it and call run() function
require_once($GLOBALS['realpath'].'src/Root.php');
$pageObj=new Root();
$arr=$pageObj->run(__FILE__);
echo $arr['page html'];
?>