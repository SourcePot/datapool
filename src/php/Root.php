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
    	
	public function __construct(){
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
		foreach($this->structure['registered methods']['init'] as $classWithNamespace=>$methodArr){
			$this->oc[$classWithNamespace]->init($this->oc);
		}
		$this->oc=$this->registerVendorClasses($this->oc);
	}
	
	private function registerVendorClasses($arr){
		return $arr;
	}
	
	/**
	* @return array An associative array that contains the full generated webpage refrenced by the key "page html" as well as all objects.
	*/
	public function run($callingWWWscript){
		$this->structure['callingWWWscript']=$callingWWWscript;
		// get current temp dir
		$GLOBALS['tmp user dir']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
		// process all buttons
		$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn();
		// add "page html" to the return array
		$arr=array();
		if (strpos($callingWWWscript,'index.php')>0){
			// build webpage
			$arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBackbone($arr);
			$arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageHeader($arr);
			$arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBody($arr);
			$arr=$this->oc[$_SESSION['page state']['app']['Class']]->run($arr);
			$arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->finalizePage($arr);
			// add page statistic for the web page called by a user
			//$this->addPageStatistic($arr,$callingWWWscript);
		} else if (strpos($callingWWWscript,'js.php')>0){
			// js-call Processing
			$arr=$this->oc['SourcePot\Datapool\Foundation\Container']->jsCall($arr);
		} else if (strpos($callingWWWscript,'job.php')>0){
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
								'dataProcessor'=>TRUE,
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

	/*	
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
	*/
}
?>