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

final class Root{
	/** Directory structure
	* the "src\" directory contains php-files only, Root.php is the entry point:
	* 'Tools\' ... Contains generic support classes.
	* 'Foundation\' ... Contains controller and model classes such as database connector, logging etc. These clases might require methods of support classes.
	* 'Processing\' ... Contains data Processing classes which must contain the dataProcessor method.
	* 'Components\' ... Contains basic view classes such as Home, Login and Logout.
	* 'AdminApp\', 'DataApps', 'GenericApps' ,.. Contain all other views/apps.
	*
	* the "www\" directory is the www-root directory. All files of this directory are publically available:
	* 'media\' ... Contains all non-php-files such as style-script-files, javascript-files, pictures etc.
	* 'tmp\' ... Contains all temporaray files presented to the user. There is one temporaray directory for each active user. 
	*/
	
	private $arr;
    
	/**
	* @return array An associative array that contains the empty webpage refrenced by the key "page html" as well as the placeholder for all objects to be created.
	*/
	public function __construct($arr=array()){
		$GLOBALS['source dir']=$GLOBALS['realpath'].'src/';
		$GLOBALS['setup dir']=$GLOBALS['realpath'].'setup/';
		$GLOBALS['public dir']=$GLOBALS['realpath'].'www/';
		$GLOBALS['filespace dir']=$GLOBALS['realpath'].'filespace/';
		$GLOBALS['font dir']=$GLOBALS['realpath'].'fonts/';
		$GLOBALS['media dir']=$GLOBALS['public dir'].'media/';
		$GLOBALS['tmp dir']=$GLOBALS['public dir'].'tmp/';
		$GLOBALS['script start time']=hrtime(TRUE);
		$GLOBALS['expirationPeriod']=600;
		//unset($_SESSION['page state']);
		if (empty($_SESSION['page state'])){
			$pageState=array('lngCode'=>'en','cssFile'=>'dark.css','toolbox'=>FALSE);
			$pageStateInitFile=$GLOBALS['setup dir'].'pageStateInit.json';
			if (!is_file($pageStateInitFile)){
				$fileContent=json_encode($pageState);
				file_put_contents($pageStateInitFile,$fileContent);
			}
			if (is_file($pageStateInitFile)){
				$pageState=file_get_contents($pageStateInitFile);
				$pageState=json_decode($pageState,TRUE);
			}
			$_SESSION['page state']=$pageState;
		}
		$arr['page html']='';
		$arr['registered methods']=array();
		$this->arr=$arr;
	}

	/**
	* @return array An associative array that contains the full generated webpage refrenced by the key "page html" as well as all objects.
	*/
	public function run($type){
		$arr=$this->arr;
		// create all objects and get structure
		$orderedInitialization=array('Tools/ArrTools.php'=>array('dirname'=>'Tools','component'=>'ArrTools.php'),
									 'Foundation/Access.php'=>array('dirname'=>'Foundation','component'=>'Access.php'),
									 'Tools/StrTools.php'=>array('dirname'=>'Tools','component'=>'StrTools.php'),
									 'Tools/FileTools.php'=>array('dirname'=>'Tools','component'=>'FileTools.php'),
									 'Tools/HTMLbuilder.php'=>array('dirname'=>'Tools','component'=>'HTMLbuilder.php'),
									 'Foundation/Haystack.php'=>array('dirname'=>'Foundation','component'=>'Haystack.php'),
									 'Foundation/Database.php'=>array('dirname'=>'Foundation','component'=>'Database.php'),
									 'Foundation/User.php'=>array('dirname'=>'Foundation','component'=>'User.php'),
									 'Tools/NetworkTools.php'=>array('dirname'=>'Tools','component'=>'NetworkTools.php'),
									 );
		$dirs=scandir($GLOBALS['source dir']);
		foreach($dirs as $dirIndex=>$dirname){
			if (empty(str_replace('.','',$dirname)) || strpos($dirname,'.php')!==FALSE){continue;}
			$dir=$GLOBALS['source dir'].$dirname;
			$Components=scandir($dir);
			// loop through all components found in $dir
			foreach($Components as $componentIndex=>$component){
				if (empty(str_replace('.','',$component))){continue;}
				$orderedInitialization[$dirname.'/'.$component]=array('dirname'=>$dirname,'component'=>$component);
			}
		}
		foreach($orderedInitialization as $dir=>$componentArr){
			$arr=$this->createComponent($GLOBALS['source dir'],$componentArr['dirname'],$componentArr['component'],$arr);
		}
		// loop through components and invoke the init method
		foreach($arr['registered methods']['init'] as $classWithNamespace=>$returnArr){$arr=$arr[$classWithNamespace]->init($arr);}
		//
		$GLOBALS['tmp user dir']=$arr['Datapool\Tools\FileTools']->getTmpDir();
		// generic button form processing
		$arr=$arr['Datapool\Tools\HTMLbuilder']->btn($arr);
		// add "page html" to the return array
		if (strpos($type,'index.php')>0){
			// webpage call, get selected Source from current app
			if (method_exists($_SESSION['page state']['app']['Class'],'getEntryTable')){
				$_SESSION['page state']['selected']['Source']=$arr[$_SESSION['page state']['app']['Class']]->getEntryTable();
			} else {
				$_SESSION['page state']['selected']['Source']=FALSE;
			}
			// build webpage
			$arr=$arr['Datapool\Tools\HTMLbuilder']->addHtmlPageBackbone($arr);
			$arr=$arr['Datapool\Tools\HTMLbuilder']->addHtmlPageHeader($arr);
			$arr=$arr['Datapool\Tools\HTMLbuilder']->addHtmlPageBody($arr);
			$arr=$arr[$_SESSION['page state']['app']['Class']]->run($arr);
			$arr=$arr['Datapool\Tools\HTMLbuilder']->finalizePage($arr);
			// add page statistic for the web page called by a user
			$this->addPageStatistic($arr,$type);
		} else if (strpos($type,'js.php')>0){
			// js-call Processing
			$arr=$arr['Datapool\Foundation\Container']->jsCall($arr);
		} else if (strpos($type,'job.php')>0){
			// job Processing
		} else {
			// invalid
		}
		$this->arr=$arr;
		return $arr;
	}
	
	/**
	* @return array An associative array that contains added objects and registered standard methods such as "job", "run" etc. with their class including the full namspace.
	*/
	private function createComponent($srcDir,$dir,$component,$arr){
		/* This function creates an object from the class provided by coomponent,
		* it stores the object in $arr for later use and checks the
		* if the object provides the init() and job() function.
		*/
		require_once($srcDir.$dir.'/'.$component);
		$class=str_replace('.php','',$component);
		$classWithNamespace=__NAMESPACE__.'\\'.$dir.'\\'.$class;
		$arr[$classWithNamespace]=new $classWithNamespace($arr);
		// get registered methods
		$methods2register=array('init'=>FALSE,
								'job'=>FALSE,
								'run'=>TRUE,
								'unifyEntry'=>FALSE,
								'dataProcessor'=>TRUE
								);
		foreach($methods2register as $method=>$invokeArgs){
			if (method_exists($arr[$classWithNamespace],$method)){
				if ($invokeArgs===FALSE){
					$arr['registered methods'][$method][$classWithNamespace]=array('class'=>$classWithNamespace,'method'=>$method);
				} else {
					$arr['registered methods'][$method][$classWithNamespace]=$arr[$classWithNamespace]->$method($invokeArgs);
				}
			}
		}
		if (stripos($classWithNamespace,'\Tools\\')===FALSE && stripos($classWithNamespace,'\Foundation\\')===FALSE){
			if (method_exists($arr[$classWithNamespace],'getEntryTable')){
				$source=$arr[$classWithNamespace]->getEntryTable();
				$arr['view classes'][$source]=$classWithNamespace;
			}
		}
		if (stripos($classWithNamespace,'logging')!==FALSE){$GLOBALS['logging class']=$classWithNamespace;}
		return $arr;
	}
	
	/**
	* @return array An entry as associative array with all statistic data at the end when the web page was created. The entry is added to the database logging table.
	*/
	private function addPageStatistic($arr,$calledBy){
		//if (mt_rand(0,1000)<950){return FALSE;}	// only save sample
		$userId=(isset($_SESSION['currentUser']['ElementId']))?$_SESSION['currentUser']['ElementId']:'ANONYM';
		$statistic=array('Source'=>$arr[$GLOBALS['logging class']]->getEntryTable(),'Group'=>$userId,'Name'=>'Page statistic type '.$calledBy,'Type'=>'statistic');
		if (isset($_SESSION['page state']['app'])){$statistic['Folder']=$_SESSION['page state']['app']['Class'];} else {$statistic['Folder']='No app call';}
		$statistic['ElementId']=$arr['Datapool\Tools\StrTools']->getElementId();
		$statistic['Date']=$arr['Datapool\Tools\StrTools']->getDateTime();
		$statistic['Expires']=$arr['Datapool\Tools\StrTools']->getDateTime('tomorrow');
		$statistic['Owner']='SYSTEM';
		$statistic=$arr['Datapool\Foundation\Access']->addRights($statistic,'ALL_MEMBER_R','ALL_MEMBER_R');
		$statistic['Content']=array('app'=>$_SESSION['page state']['app'],'selected'=>$_SESSION['page state']['selected']);
		$timeConsumption=round((hrtime(TRUE)-$GLOBALS['script start time'])/1000000);
		$statistic['Content']['Script time consumption [ms]']=$timeConsumption;
		$arr['Datapool\Foundation\Database']->insertEntry($statistic);
		if ($timeConsumption>200){
			$msg='Page performance warning: Page creation took '.$timeConsumption.'ms.';
			$arr['Datapool\Foundation\Logging']->addLog(array('msg'=>$msg,'priority'=>42,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
		return $statistic;
	}
	
}
?>