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
		$GLOBALS['source dir']=$GLOBALS['realpath'].'php/';
		$GLOBALS['traits dir']=$GLOBALS['realpath'].'php/Traits/';
		$GLOBALS['vendor dir']=$GLOBALS['realpath'].'vendor/';
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
			$pageState=array('lngCode'=>'en','cssFile'=>'dark.css','toolbox'=>FALSE,'selected'=>array());
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
		// load all external components
		$_SESSION['page state']['autoload.php loaded']=FALSE;
		if (is_dir($GLOBALS['vendor dir'])){
			$autoloadFile=$GLOBALS['vendor dir'].'autoload.php';
			if (is_file($autoloadFile)){
				$_SESSION['page state']['autoload.php loaded']=TRUE;
				require_once $autoloadFile;
			}
		}
		$this->arr=$arr;
	}

	/**
	* @return array An associative array that contains the full generated webpage refrenced by the key "page html" as well as all objects.
	*/
	public function run($type){
		$arr=$this->arr;
		// include traits
		$traits=scandir($GLOBALS['traits dir']);
		foreach($traits as $traitIndex=>$trait){
			if (strpos($trait,'.php')===FALSE){continue;}
			require_once $GLOBALS['traits dir'].$trait;
		}
		// create all objects and get structure
		$orderedInitialization=array('Tools/MiscTools.php'=>array('dirname'=>'Tools','component'=>'MiscTools.php'),
									 'Foundation/Access.php'=>array('dirname'=>'Foundation','component'=>'Access.php'),
									 'Foundation/Filespace.php'=>array('dirname'=>'Foundation','component'=>'Filespace.php'),
									 'Foundation/Backbone.php'=>array('dirname'=>'Foundation','component'=>'Backbone.php'),
									 'Foundation/Database.php'=>array('dirname'=>'Foundation','component'=>'Database.php'),
									 'Foundation/Definitions.php'=>array('dirname'=>'Foundation','component'=>'Definitions.php'),
									 'Foundation/Dictionary.php'=>array('dirname'=>'Foundation','component'=>'Dictionary.php'),
									 'Tools/HTMLbuilder.php'=>array('dirname'=>'Tools','component'=>'HTMLbuilder.php'),
									 'Foundation/Logging.php'=>array('dirname'=>'Foundation','component'=>'Logging.php'),
									 'Foundation/User.php'=>array('dirname'=>'Foundation','component'=>'User.php'),
									 );
		$dirs=scandir($GLOBALS['source dir']);
		foreach($dirs as $dirIndex=>$dirname){
			if (strlen($dirname)<3 || strpos($dirname,'Traits')!==FALSE || strpos($dirname,'.php')!==FALSE){continue;}
			$dir=$GLOBALS['source dir'].$dirname.'/';
			$Components=scandir($dir);
			// loop through all components found in $dir
			foreach($Components as $componentIndex=>$component){
				if (strpos($component,'.php')===FALSE){continue;}
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
		$GLOBALS['tmp user dir']=$arr['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
		// generic button form processing
		$arr=$arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($arr);
		// add "page html" to the return array
		if (strpos($type,'index.php')>0){
			// build webpage
			$arr=$arr['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBackbone($arr);
			$arr=$arr['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageHeader($arr);
			$arr=$arr['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBody($arr);
			$arr=$arr[$_SESSION['page state']['app']['Class']]->run($arr);
			$arr=$arr['SourcePot\Datapool\Foundation\Backbone']->finalizePage($arr);
			// add page statistic for the web page called by a user
			$this->addPageStatistic($arr,$type);
		} else if (strpos($type,'js.php')>0){
			// js-call Processing
			$arr=$arr['SourcePot\Datapool\Foundation\Container']->jsCall($arr);
		} else if (strpos($type,'job.php')>0){
			// job Processing
			$arr=$this->runJob($arr);
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
	* @return array The method runs the most overdue job, updates the job setting, adds generated webpage refrenced by the key "page html" to the provided array and returns the completed array.
	*/
	private function runJob($arr){
		// all jobs settings - remove non-existing job methods and add new job methods
		$jobs=array('due'=>array(),'undue'=>array());
		$allJobsSettingInitContent=array('Last run'=>time(),'Min time in sec between each run'=>600,'Last run time consumption [ms]'=>0);
		$allJobsSetting=array('Source'=>$arr['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Job processing','Folder'=>'All jobs','Name'=>'Timing','Type'=>'array setting');
		$allJobsSetting=$arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($allJobsSetting,array('Source','Group','Folder','Name','Type'),0);
		$allJobsSetting=$arr['SourcePot\Datapool\Foundation\Access']->addRights($allJobsSetting,'ALL_R','ADMIN_R');
		$allJobsSetting=$arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($allJobsSetting,TRUE);
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
		$arr['page html']=$arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'h1','element-content'=>'Job processing triggered'));
		if (empty($jobs['due'])){
			$matrix=$arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($jobs);
			$arr['page html'].=$arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Jobs','keep-element-content'=>TRUE,'hideKeys'=>TRUE));	
		} else {
			arsort($jobs['due']);
			reset($jobs['due']);
			$dueJob=key($jobs['due']);
			$dueMethod=$allJobsSetting['Content'][$dueJob]['method'];
			// job var space and run job
			$jobVars=array('Source'=>$arr['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Job processing','Folder'=>'Var space','Name'=>$dueJob,'Type'=>'array vars');
			$jobVars=$arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($jobVars,array('Source','Group','Folder','Name','Type'),0);
			$jobVars=$arr['SourcePot\Datapool\Foundation\Access']->addRights($jobVars,'ADMIN_R','ADMIN_R');
			$jobVars=$arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($jobVars,TRUE);
			$jobStartTime=hrtime(TRUE);
			$arr['SourcePot\Datapool\Foundation\Database']->resetStatistic();
			$jobVars['Content']=$arr[$dueJob]->$dueMethod($jobVars['Content']);
			$jobStatistic=$arr['SourcePot\Datapool\Foundation\Database']->getStatistic();
			$allJobsSetting['Content'][$dueJob]['Last run']=time();
			$allJobsSetting['Content'][$dueJob]['Last run time consumption [ms]']=round((hrtime(TRUE)-$jobStartTime)/1000000);
			// update job vars
			$jobVars=$arr['SourcePot\Datapool\Foundation\Database']->updateEntry($jobVars,TRUE);
			// show results
			$matrix=$arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($allJobsSetting['Content'][$dueJob]);
			$arr['page html'].=$arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Job done','keep-element-content'=>TRUE,'hideKeys'=>TRUE));
			$matrix=$arr['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($jobStatistic);
			$arr['page html'].=$arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Job statistic','keep-element-content'=>TRUE,'hideKeys'=>TRUE));
		}
		$arr['SourcePot\Datapool\Foundation\Database']->updateEntry($allJobsSetting,TRUE);
		return $arr;
	}
	
	/**
	* @return array An entry as associative array with all statistic data at the end when the web page was created. The entry is added to the database logging table.
	*/
	private function addPageStatistic($arr,$calledBy){
		//if (mt_rand(0,1000)<950){return FALSE;}	// only save sample
		$userId=(isset($_SESSION['currentUser']['EntryId']))?$_SESSION['currentUser']['EntryId']:'ANONYM';
		$statistic=array('Source'=>$arr[$GLOBALS['logging class']]->getEntryTable(),'Group'=>$userId,'Name'=>'Page statistic type '.$calledBy,'Type'=>'statistic');
		if (isset($_SESSION['page state']['app'])){$statistic['Folder']=$_SESSION['page state']['app']['Class'];} else {$statistic['Folder']='No app call';}
		$statistic['EntryId']=$arr['SourcePot\Datapool\Tools\MiscTools']->getEntryId();
		$statistic['Date']=$arr['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
		$statistic['Expires']=$arr['SourcePot\Datapool\Tools\MiscTools']->getDateTime('tomorrow');
		$statistic['Owner']='SYSTEM';
		$statistic=$arr['SourcePot\Datapool\Foundation\Access']->addRights($statistic,'ALL_MEMBER_R','ALL_MEMBER_R');
		$statistic['Content']=array('app'=>$_SESSION['page state']['app']);
		$statistic['Content']['selected']=$arr['SourcePot\Datapool\Tools\NetworkTools']->getPageState($_SESSION['page state']['app']['Class']);
		$timeConsumption=round((hrtime(TRUE)-$GLOBALS['script start time'])/1000000);
		$statistic['Content']['Script time consumption [ms]']=$timeConsumption;
		$arr['SourcePot\Datapool\Foundation\Database']->insertEntry($statistic);
		if ($timeConsumption>900){
			$msg='Page performance warning: Page creation took '.$timeConsumption.'ms.';
			$arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>$msg,'priority'=>42,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
		return $statistic;
	}
	
}
?>