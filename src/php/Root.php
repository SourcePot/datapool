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

use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\LoadAverageProcessor;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\NativeMailerHandler;

final class Root{

    // add classes here to be initiated as part of the Object Collection
    private $registerVendorClasses=array('SourcePot\MediaPlayer\MediaPlayer','SourcePot\PIview\PIview','SourcePot\Sms\Sms',);
    
    private $currentScript='';
    
    private $oc=array();
    private $structure=array('implemented interfaces'=>array(),'registered methods'=>array(),'source2class'=>array(),'class2source'=>array());

    public $traces=array();
    
    public function __construct()
    {
        $GLOBALS['script start time']=hrtime(TRUE);
        $oc=array(__CLASS__=>$this);
        session_start();
        $this->currentScript=filter_input(INPUT_SERVER,'PHP_SELF',FILTER_SANITIZE_URL);
        // inititate the web page state
        if (empty($_SESSION['page state'])){
            $_SESSION['page state']=array('toolbox'=>'SourcePot\Datapool\Foundation\Logger','selected'=>array());
        }
        // set exeption handler and initialize directories
        $this->initDirs();
        // load all external components
        $_SESSION['page state']['autoload.php loaded']=FALSE;
        $autoloadFile=$GLOBALS['dirs']['vendor'].'/autoload.php';
        if (is_file($autoloadFile)){
            $_SESSION['page state']['autoload.php loaded']=TRUE;
            require_once $autoloadFile;
        }
        $this->initExceptionHandler();
        // initilize object collection, create objects and invoke init methods
        $oc=$this->getInstantiatedObjectCollection($oc);
        // add logger
        $oc['logger']=$this->getMonologLogger($oc,'Root');
        $oc['logger_1']=$this->getMonologLogger($oc,'Debugging');
        //
        $oc=$this->registerVendorClasses($oc);
        foreach($this->structure['registered methods']['init'] as $classWithNamespace=>$methodArr){
            $oc[$classWithNamespace]->init($oc);
        }
        $oc['logger']=$this->configureMonologLogger($oc,$oc['logger']);
        $this->oc=$oc;
    }
    
    /**
    * This method returns a Monolog logger instance.
    *
    * @return array An associative array that contains the Datapool object collection, i.e. all initiated objects of Datapool.
    */
    private function getMonologLogger(array $oc,string $channel='Root'):Logger
    {
        $logLevel=intval($oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('logLevel'));
        $logFile=$GLOBALS['dirs']['logging'].date('Y-m-d').' '.$channel.'.log';
        $logger = new Logger($channel);
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $logger->pushProcessor(new LoadAverageProcessor());
        if ($logLevel===0){
            $streamHandler = new StreamHandler($logFile,Level::Warning);
        } else if ($logLevel===1){
            $streamHandler = new StreamHandler($logFile,Level::Notice);
        } else if ($logLevel>1){
            $streamHandler = new StreamHandler($logFile,Level::Debug);
        }
        $logger->pushHandler($streamHandler);
        return $logger;
    }
    
    /**
    * This method adds a Monolog logger database handler
    *
    * @return Logger Is the logger instance
    */
    private function configureMonologLogger(array $oc,Logger $logger):Logger
    {
        // log to database handler
        require_once(__DIR__.'/Foundation/logger/DbHandler.php');
        $dbHandler = new \SourcePot\Datapool\Foundation\Logger\DbHandler($oc);
        $logger->pushHandler($dbHandler);
        return $logger;
    }
    

    /**
    * @return array An associative array that contains the Datapool object collection, i.e. all initiated objects of Datapool.
    * This method can be used to add external objects to the Datapool object collection. 
    */
    private function registerVendorClasses(array $oc,bool $isDebugging=FALSE):array
    {
        // instantiate external classes
        $debugArr=array('registerVendorClasses'=>$this->registerVendorClasses);
        foreach($this->registerVendorClasses as $classIndex=>$classWithNamespace){
            if (class_exists($classWithNamespace)){
                $oc[$classWithNamespace]=new $classWithNamespace($oc);
                //$oc[$classWithNamespace]->dataProcessor(array(),'info');
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
        
    public function getOc():array
    {
        return $this->oc;
    }
        
    /**
    * @return array An associative array that contains the full generated webpage referenced by the key "page html".
    */
    public function run():array
    {
        $this->structure['callingWWWscript']=$this->currentScript;
        $pathInfo=pathinfo($this->currentScript);
        // get current temp dir
        if (strpos($this->currentScript,'resource.php')===FALSE && strpos($this->currentScript,'job.php')===FALSE){
            $GLOBALS['tmp user dir']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
        }
        // process all buttons
        $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn();
        $GLOBALS['script init time']=hrtime(TRUE);
        // get trace
        // add "page html" to the return array
        $arr=array();
        if (strpos($this->currentScript,'index.php')>0){
            // build webpage
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBackbone($arr);
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageHeader($arr);
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBody($arr);
            $arr=$this->oc[$_SESSION['page state']['app']['Class']]->run($arr);
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->finalizePage($arr);
            // add page statistic for the web page called by a user
            //$this->addPageStatistic($arr,$callingWWWscript);
        } else if ($pathInfo['basename']=='js.php'){
            // js-call Processing
            $arr=$this->oc['SourcePot\Datapool\Foundation\Container']->jsCall($arr);
        } else if ($pathInfo['basename']=='job.php'){
            // job Processing
            $arr=$this->oc['SourcePot\Datapool\Foundation\Job']->trigger($arr);
        } else if ($pathInfo['basename']=='resource.php'){
            // client request processing
            $arr=$this->oc['SourcePot\Datapool\Foundation\ClientAccess']->request($arr);
        } else {
            // invalid
            $this->oc['logger']->log('error','Invalid script "{script}" called',array('script'=>$pathInfo['basename']));    
        }
        // script time consumption in ms
        $scriptTimeConsumption=round((hrtime(TRUE)-$GLOBALS['script start time'])/1000000);
        $scriptInitTimeConsumption=round(($GLOBALS['script init time']-$GLOBALS['script start time'])/1000000);
        $context=array('scriptTimeConsumption'=>$scriptTimeConsumption,'scriptInitTimeConsumption'=>$scriptInitTimeConsumption,'script'=>$pathInfo['basename']);
        if ($scriptTimeConsumption>5000){
            $this->oc['logger']->log('warning','Script "{script}" took {scriptTimeConsumption}ms Initialization took {scriptInitTimeConsumption}ms.',$context);    
        }
        return $arr;
    }
    
    public function getRegisteredMethods(string $method=''):array
    {
        if (empty($method)){
            return $this->structure['registered methods'];
        } else if (isset($this->structure['registered methods'][$method])){
            return $this->structure['registered methods'][$method];
        } else {
            throw new \ErrorException('Function '.__FUNCTION__.': Argument method = "'.$method.'" is invalid.',0,E_ERROR,__FILE__,__LINE__);
        }
    }

    public function getImplementedInterfaces(string $interface=''):array
    {
        if (empty($interface)){
            return $this->structure['implemented interfaces'];
        } else if (isset($this->structure['implemented interfaces'][$interface])){
            return $this->structure['implemented interfaces'][$interface];
        } else {
            throw new \ErrorException('Function '.__FUNCTION__.': Argument interface = "'.$interface.'" is invalid.',0,E_ERROR,__FILE__,__LINE__);
        }
    }

    public function source2class(string $source):string
    {
        if (isset($this->structure['source2class'][$source])){
            return $this->structure['source2class'][$source];
        } else {
            return '';
        }
    }

    public function class2source(string $class):string
    {
        if (isset($this->structure['class2source'][$class])){
            return $this->structure['class2source'][$class];
        } else {
            return '';
        }
    }

    /**
    * This method creates the ../src/setup/objectList.csv file that contains row by row all objects that make-up the Datapool object collection.
    * The objects will be initiated row by row. If a different order is required, the file needs to be edited accordingly.
    * In each class the Datapool object collection array can be accessed by $this->oc[...],
    * with "..." being the classWithNamespace which refers to the intantiated object of this class.
    */
    private function createObjList(string $objListFile)
    {
        $orderedInitialization=array('MiscTools.php'=>'301|',
                                     'Access.php'=>'302|',
                                     'Filespace.php'=>'303|',
                                     'Backbone.php'=>'304|',
                                     'Database.php'=>'305|',
                                     'Definitions.php'=>'306|',
                                     'Dictionary.php'=>'307|',
                                     'HTMLbuilder.php'=>'308|',
                                     'Logging.php'=>'309|',
                                     'Logger.php'=>'310|',
                                     'User.php'=>'311|',
                                     'Home.php'=>'701|',
                                     'Account.php'=>'702|',
                                     'Login.php'=>'901|',
                                     'Logout.php'=>'902|',
                                     );
        $fileIndex=0;
        $objectsArr=array('000|Header|'.$fileIndex=>array('class','classWithNamespace','file','type'));
        // scan dirs
        $dir=$GLOBALS['dirs']['php'];
        $dirs=scandir($dir);
        foreach($dirs as $dirIndex=>$dirName){
            if (strpos($dirName,'.php')!==FALSE || strpos($dirName,'.md')!==FALSE || empty(trim($dirName,'.'))){continue;}
            $type=match($dirName){'Interfaces'=>'200|Interface',
                                  'Foundation'=>'400|Kernal object',
                                  'Tools'=>'500|Kernal object',
                                  'Processing'=>'600|Kernal object',
                                  default=>'800|Application object'
                                 };
            // scan files
            $subDir=$dir.$dirName.'/';
            $files=scandir($subDir);
            // loop through all components found in $dir
            foreach($files as $filesIndex=>$file){
                if (strpos($file,'.php')===FALSE){continue;}
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
    
    /**
    * This method returns the Datapool object collection from the object list file.
    * If the object list file does not exist, it will be created.
    */
    private function getInstantiatedObjectCollection(array $oc=array()):array
    {
        $objListNeedsToBeRebuild=FALSE;
        $objListFile=$GLOBALS['dirs']['setup'].'objectList.csv';
        if (!is_file($objListFile)){
            $this->createObjList($objListFile);
        }
        $headerArr=array();
        if (($handle=fopen($objListFile,"r"))!==FALSE){
            while (($rowArr=fgetcsv($handle,1000,";"))!==FALSE){
                if (empty($headerArr)){
                    $headerArr=$rowArr;
                } else {
                    $objDef=array_combine($headerArr,$rowArr);
                    $classWithNamespace=$objDef['classWithNamespace'];
                    if (is_file($objDef['file'])){
                        require_once $objDef['file'];
                        if (strcmp($objDef['type'],'Kernal object')===0 || strcmp($objDef['type'],'Application object')===0){
                            $oc[$classWithNamespace]=new $classWithNamespace($oc);
                            $this->updateStructure($oc,$classWithNamespace);
                        }
                    } else {
                        $objListNeedsToBeRebuild=TRUE;
                    }
                }
            }
            fclose($handle);
        }
        if ($objListNeedsToBeRebuild){unlink($objListFile);}
        return $oc;
    }
    
    private function updateStructure(array $oc,string $classWithNamespace):array
    {
        $methods2register=array('init'=>FALSE,
                                'job'=>FALSE,
                                'run'=>TRUE,            // class->run(), which returns menu definition
                                'unifyEntry'=>FALSE,
                                'dataProcessor'=>array(),
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
        // get classes with implemented interfaces
        foreach(class_implements($classWithNamespace) as $interface){
            if (in_array($interface,class_implements($classWithNamespace))){
                $this->structure['implemented interfaces'][$interface][$classWithNamespace]=$classWithNamespace;
            }
        }
        return $this->structure;
    }
    
    private function initDirs():array
    {
        $relThisDirSuffix='/src/php';
        $wwwDirIndicator='/src/www';
        // relative dirs from root
        $GLOBALS['dirDefs']=array('root'=>array('relPath'=>'.','permissions'=>0770),
                                    'vendor'=>array('relPath'=>'./vendor','permissions'=>0770),
                                    'src'=>array('relPath'=>'./src','permissions'=>0770),
                                    'setup'=>array('relPath'=>'./src/setup','permissions'=>0770),
                                    'filespace'=>array('relPath'=>'./src/filespace','permissions'=>0770),
                                    'privat tmp'=>array('relPath'=>'./src/tmp_private','permissions'=>0770),
                                    'debugging'=>array('relPath'=>'./src/debugging','permissions'=>0770),
                                    'logging'=>array('relPath'=>'./src/logging','permissions'=>0770),
                                    'ftp'=>array('relPath'=>'./src/ftp','permissions'=>0770),
                                    'fonts'=>array('relPath'=>'./src/fonts','permissions'=>0770),
                                    'php'=>array('relPath'=>'./src/php','permissions'=>0770),
                                    'public'=>array('relPath'=>'./src/www','permissions'=>0775),
                                    'media'=>array('relPath'=>'./src/www/media','permissions'=>0775),
                                    'assets'=>array('relPath'=>'./src/www/assets','permissions'=>0775),
                                    'tmp'=>array('relPath'=>'./src/www/tmp','permissions'=>0775),
                                    );
        $absRootPath=strtr(__DIR__,array('\\'=>'/'));
        $absRootPath=strtr($absRootPath,array($relThisDirSuffix=>''));
        // get absolute dirs
        $GLOBALS['dirs']=array();
        $GLOBALS['relDirs']=array();
        foreach($GLOBALS['dirDefs'] as $label=>$def){
            $GLOBALS['dirs'][$label]=str_replace('.',$absRootPath,$def['relPath']).'/';
            $relDirComps=explode($wwwDirIndicator,$def['relPath']);
            if (count($relDirComps)===2){
                $GLOBALS['relDirs'][$label]='.'.$relDirComps[1];
            }
            if (!is_dir($GLOBALS['dirs'][$label])){
                mkdir($GLOBALS['dirs'][$label],$def['permissions'],TRUE);
            }    
        }
        return $GLOBALS['dirs'];
    }

    private function initExceptionHandler()
    {
        // error handling
        set_exception_handler(function(\Throwable $e){
            $errMessage=$e->getMessage();
            $addInfo='';
            // special error handling
            if (stripos($errMessage,'Call to a member function')!==FALSE && stripos($errMessage,'on null')!==FALSE){
                // object list might need an update if new classes where added
                $deleted=unlink($GLOBALS['dirs']['setup'].'objectList.csv');
                if ($deleted){
                    $addInfo.='Due to the detected error the object list was deleted. This should trigger the creation of an updated object list and might solve the problem.';
                } else {
                    $addInfo.='Due to the detected error the deletion of the object list was triggered but failed (Maybe it did not exist in the first place?)!';
                }
            }
            // logging
            if (!is_dir($GLOBALS['dirs']['debugging'])){mkdir($GLOBALS['dirs']['debugging'],0770,TRUE);}
            $err=array('date'=>date('Y-m-d H:i:s'),'additional info'=>$addInfo,'message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'code'=>$e->getCode(),'traceAsString'=>$e->getTraceAsString());
            $logFileContent=json_encode($err);
            $logFileName=$GLOBALS['dirs']['debugging'].'/'.time().'_exceptionsLog.json';
            file_put_contents($logFileName,$logFileContent);
            //fallback page
            $html='';
            if (strpos($this->currentScript,'js.php')!==FALSE){
                $html.='Have run into a problem, please check debugging dir...';
            } else {
                $html.=$this->getBackupPageContent();
            }
            echo $html;
            exit;
        });
    }
    
    public function getBackupPageContent($msg='But some improvements might take a while.'){
        $html='<!DOCTYPE html>';
        $html.='<html xmlns="http://www.w3.org/1999/xhtml" lang="en">';
        $html.='<head>';
        $html.='</head>';
        $html.='<body style="color:#000;background-color:#fff;font:80% sans-serif;font-size:20px;">';
        $html.='<p style="width:fit-content;margin: 20px auto;">We are very sorry for the interruption.</p>';
        $html.='<p style="width:fit-content;margin: 20px auto;">The web page will be up and running as soon as possible.</p>';
        $html.='<p style="width:fit-content;margin: 20px auto;">'.$msg.'</p>';
        $html.='<p style="width:fit-content;margin: 20px auto;">The Admin <span style="font-size:2em;">👷</span></p>';
        $html.='</body>';
        $html.='</html>';
        return $html;
    }

    public function file_get_contents_utf8(string $fn):string
    {
        $content=@file_get_contents($fn);
        $content=mb_convert_encoding($content,'UTF-8',mb_detect_encoding($content,'UTF-16,UTF-8,ISO-8859-1',TRUE));
        // clean up - remove BOM
        $bom=pack('H*','EFBBBF');
        $content=preg_replace("/^$bom/",'',$content);
        return $content;
    }

    public function file2arr(string $fileName):array
    {
        $arr=array();
        if (is_file($fileName)){
            $content=$this->file_get_contents_utf8($fileName);
            if (!empty($content)){
                $arr=json_decode($content,TRUE,512,JSON_INVALID_UTF8_IGNORE);
                if (empty($arr)){$arr=json_decode(stripslashes($content),TRUE,512,JSON_INVALID_UTF8_IGNORE);}
            }
        }
        return $arr;
    }    
}
?>