<?php
/*
* This file is part of the Datapool package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/

declare(strict_types=1);

namespace SourcePot\Datapool;

final class Root{

	private $oc=array();
    private $structure=array('registered methods'=>array(),'source2class'=>array(),'class2source'=>array());
    
	/**
	* @return array An associative array that contains the Datapool object collection, i.e. all initiated objects of Datapool.
	* This method can be used to add external objects to the Datapool object collection. 
	*/
	private function registerVendorClasses($oc,$isDebugging=FALSE){
		// instantiate OPS add-on
		$classesWithNamespace=array('\SourcePot\Ops\OpsEntries');
		$debugArr=array('classesWithNamespace'=>$classesWithNamespace);
		foreach($classesWithNamespace as $classIndex=>$classWithNamespace){
			if (class_exists($classWithNamespace)){
				$oc[$classWithNamespace]=new $classWithNamespace($oc);
				$oc[$classWithNamespace]->dataProcessor(array(),'info');
				$this->updateStructure($oc,$classWithNamespace);			 
				$debugArr['Successful class instantiations'][$classIndex]=$classWithNamespace;
			} else {
				$isDebugging=TRUE;
				$debugArr['Failed class instantiations'][$classIndex]=$classWithNamespace;
			}
		}
		if ($isDebugging){
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $oc;
	}
	
	public function __construct($relPath='./'){
		$GLOBALS['script start time']=hrtime(TRUE);
		session_start();
		// set exeption handler and initialize directories
		$this->initDirs($relPath);
		$this->initExceptionHandler();
		// iterate through the directory template and create all package directories
		foreach($GLOBALS['relDirs'] as $dirName=>$relDir){
			if (!is_dir($relDir)){
				if (strcmp($dirName,'public')===0 || strcmp($dirName,'tmp')===0 || strcmp($dirName,'media')===0){
					mkdir($relDir,0775,TRUE);
				} else {
					mkdir($relDir,0770,TRUE);		
				}
			}
		}
		// inititate the web page state
		if (empty($_SESSION['page state'])){
			$_SESSION['page state']=array('lngCode'=>'en','cssFile'=>'light.css','toolbox'=>FALSE,'selected'=>array());
		}
		// load all external components
		$_SESSION['page state']['autoload.php loaded']=FALSE;
		$autoloadFile=$GLOBALS['dirs']['vendor'].'/autoload.php';
		if (is_file($autoloadFile)){
			$_SESSION['page state']['autoload.php loaded']=TRUE;
			require_once $autoloadFile;
		}
		// initilize object collection, create objects and invoke init methods
		$oc=array(__CLASS__=>$this);
		$this->oc=$this->getInstantiatedObjectCollection($oc);
		$this->oc=$this->registerVendorClasses($this->oc);
		foreach($this->structure['registered methods']['init'] as $classWithNamespace=>$methodArr){
			$this->oc[$classWithNamespace]->init($this->oc);
		}
	}
	
	public function getOc(){
		return $this->oc;
	}
		
	/**
	* @return array An associative array that contains the full generated webpage referenced by the key "page html".
	*/
	public function run(){
		$this->structure['callingWWWscript']=$_SERVER['PHP_SELF'];
		// get current temp dir
		$GLOBALS['tmp user dir']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
		// process all buttons
		$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn();
		// add "page html" to the return array
		$arr=array();
		if (strpos($_SERVER['PHP_SELF'],'index.php')>0){
			// build webpage
			$arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBackbone($arr);
			$arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageHeader($arr);
			$arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBody($arr);
			$arr=$this->oc[$_SESSION['page state']['app']['Class']]->run($arr);
			$arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->finalizePage($arr);
			// add page statistic for the web page called by a user
			//$this->addPageStatistic($arr,$callingWWWscript);
		} else if (strpos($_SERVER['PHP_SELF'],'js.php')>0){
			// js-call Processing
			$arr=$this->oc['SourcePot\Datapool\Foundation\Container']->jsCall($arr);
		} else if (strpos($_SERVER['PHP_SELF'],'job.php')>0){
			// job Processing
			$arr=$this->oc['SourcePot\Datapool\Foundation\Job']->trigger($arr);
		} else {
			// invalid
		}
		return $arr;
	}
	
	public function getRegisteredMethods($method=FALSE){
		if (empty($method)){
			return $this->structure['registered methods'];
		} else if (isset($this->structure['registered methods'][$method])){
			return $this->structure['registered methods'][$method];
		} else {
			throw new \ErrorException('Function '.__FUNCTION__.': Argument method = "'.$method.'" is invalid.',0,E_ERROR,__FILE__,__LINE__);
		}
	}

	public function source2class($source){
		if (isset($this->structure['source2class'][$source])){
			return $this->structure['source2class'][$source];
		} else {
			return FALSE;
		}
	}

	public function class2source($class){
		if (isset($this->structure['class2source'][$class])){
			return $this->structure['class2source'][$class];
		} else {
			return FALSE;
		}
	}

	/**
	* This method creates the ../src/setup/objectList.csv file that contains row by row all objects that make-up the Datapool object collection.
	* The objects will be initiated row by row. If a different order is required, the file needs to be edited accordingly.
	*/
	private function createObjList($objListFile){
		$orderedInitialization=array('MiscTools.php'=>'301|',
									 'Access.php'=>'302|',
									 'Filespace.php'=>'303|',
									 'Backbone.php'=>'304|',
									 'Database.php'=>'305|',
									 'Definitions.php'=>'306|',
									 'Dictionary.php'=>'307|',
									 'HTMLbuilder.php'=>'308|',
									 'Logging.php'=>'309|',
									 'User.php'=>'310|'
									 );
		$fileIndex=0;
		$objectsArr=array('000|Header|'.$fileIndex=>array('class','classWithNamespace','file','type'));
		// scan dirs
		$dir=$GLOBALS['dirs']['php'];
		$dirs=scandir($dir);
		foreach($dirs as $dirIndex=>$dirName){
			if (strpos($dirName,'.php')!==FALSE || empty(trim($dirName,'.'))){continue;}
			$type=match($dirName){'Traits'=>'100|Trait','Interfaces'=>'200|Interface','Foundation'=>'400|Kernal object','Tools'=>'500|Kernal object','Processing'=>'600|Kernal object',default=>'700|Application object'};
			// scan files
			$subDir=$dir.'/'.$dirName.'/';
			$files=scandir($subDir);
			// loop through all components found in $dir
			foreach($files as $filesIndex=>$file){
				if (strpos($file,'.php')===FALSE || empty(trim($file,'.'))){continue;}
				$cleanType=trim($type,'|0123456789');
				$class=str_replace('.php','',$file);
				$classWithNamespace=__NAMESPACE__.'\\'.$dirName.'\\'.$class;
				if (isset($orderedInitialization[$file])){	
					$objectsArr[$orderedInitialization[$file].$cleanType.'|'.$fileIndex]=array($class,$classWithNamespace,$subDir.$file,$cleanType);
				} else {
					$objectsArr[$type.'|'.$fileIndex]=array($class,$classWithNamespace,$subDir.$file,$cleanType);
				}
				$fileIndex++;
			}
		}
		ksort($objectsArr);
		$fileHandler=fopen($objListFile,'w');
		foreach ($objectsArr as $key=>$fields){
			fputcsv($fileHandler,$fields,';');
		}
		fclose($fileHandler);
	}
	
	private function getInstantiatedObjectCollection($oc=array()){
		$objListFile=$GLOBALS['relDirs']['setup'].'/objectList.csv';
		if (!is_file($objListFile)){$this->createObjList($objListFile);}
		$headerArr=array();
		if (($handle=fopen($objListFile,"r"))!==FALSE){
			while (($rowArr=fgetcsv($handle,1000,";"))!==FALSE){
				if (empty($headerArr)){
					$headerArr=$rowArr;
				} else {
					$objDef=array_combine($headerArr,$rowArr);
					$classWithNamespace=$objDef['classWithNamespace'];
					require_once $objDef['file'];
					if (strcmp($objDef['type'],'Kernal object')===0 || strcmp($objDef['type'],'Application object')===0){
						$oc[$classWithNamespace]=new $classWithNamespace($oc);
						$this->updateStructure($oc,$classWithNamespace);
					}
				}
			}
			fclose($handle);
		}
 		return $oc;
	}
	
	private function updateStructure($oc,$classWithNamespace){
		$methods2register=array('init'=>FALSE,
								'job'=>FALSE,
								'run'=>TRUE,			// class->run(), which returns menu definition
								'unifyEntry'=>FALSE,
								'dataProcessor'=>array(),
								'getTrigger'=>FALSE,	
								'dataSource'=>TRUE,
								'dataSink'=>TRUE,
								);
		// analyse class structure
		if (method_exists($oc[$classWithNamespace],'getEntryTable')){
			$source=$oc[$classWithNamespace]->getEntryTable();
			$this->structure['source2class'][$source]=$classWithNamespace;
			$this->structure['class2source'][$classWithNamespace]=$source;
		}
		// get registered methods
		foreach($methods2register as $method=>$invokeArgs){
			if (!isset($this->structure['registered methods'][$method])){$this->structure['registered methods'][$method]=array();}
			if (method_exists($oc[$classWithNamespace],$method)){
				if ($invokeArgs===FALSE){
					$this->structure['registered methods'][$method][$classWithNamespace]=array('class'=>$classWithNamespace,'method'=>$method);
				} else {
					$this->structure['registered methods'][$method][$classWithNamespace]=$oc[$classWithNamespace]->$method($invokeArgs);
				}
			}
		}
		if (stripos($classWithNamespace,'logging')!==FALSE){$GLOBALS['logging class']=$classWithNamespace;}
		return $this->structure;
	}
	
	private function initDirs($relPath='./'){
		$thisDir='/src/php';
		$fromRelPath=strtr(realpath($relPath),array('\\'=>'/'));
		$fromAbsPath=strtr(__DIR__,array('\\'=>'/'));
		$fromAbsPathRoot=str_replace($thisDir,'',$fromAbsPath);
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
									'php'=>'./',
									'traits'=>'../php/Traits',
									'public'=>'../www',
									'media'=>'../www/media',
									'tmp'=>'../www/tmp'
									);
		$GLOBALS['dirs']=array();
		foreach($GLOBALS['dirSuffix'] as $dirName=>$suffix){
			if (strpos($suffix,'../../')===0){
				$prefix='/';
			} else if (strpos($suffix,'../')===0){
				$prefix='/src/';
			} else {
				$prefix=$thisDir.'/';
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
		return $GLOBALS['dirs'];
	}

	private function initExceptionHandler(){
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
			if (strpos($_SERVER['PHP_SELF'],'js.php')!==FALSE){
				$html.='Have run into a problem, please check debugging dir...';
			} else {
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
			}
			echo $html;
			exit;
		});
		set_error_handler(function($errno,$errstr,$errfile,$errline){
			if (!(error_reporting() && $errno)){return;}
			throw new \ErrorException($errstr,$errno,0,$errfile,$errline);
		},E_ALL & ~E_WARNING & ~E_NOTICE & ~E_USER_NOTICE);
	}

}
?>