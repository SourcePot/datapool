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
use Monolog\Processor\LoadAverageProcessor;
use Monolog\Handler\StreamHandler;

final class Root{

    // header & session cockie
    private const SESSION_COCKIE=[
        'cookie_lifetime'=>43200,
        'cookie_samesite'=>'Strict',
        'cookie_secure'=>TRUE,
        'cookie_httponly'=>TRUE,
    ];
    private const HTTP_HEADER=[
        'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload',
        'Cache-Control: max-age=2',
        'X-Content-Type-Options: nosniff',
        'X-Frame-Options: SAMEORIGIN',
        "Content-Security-Policy: base-uri 'self'; frame-ancestors 'self'; default-src 'strict-dynamic' 'self' 'nonce-{{nonce}}'; object-src 'self' 'nonce-{{nonce}}'; script-src 'nonce-{{nonce}}'; style-src-attr 'unsafe-inline';img-src 'self' https://tile.openstreetmap.org https://unpkg.com/leaflet@1.9.4/dist/images/ data:; frame-src 'self' https://www.openstreetmap.org/",
    ];
    
    // all classes listed at ADD_VENDOR_CLASSES will be initiated and added to the Object Collection "oc"
    public const ADD_VENDOR_CLASSES=[
        'SourcePot\MediaPlayer\MediaPlayer',
        'SourcePot\Sms\Sms',
        'SourcePot\checkentries\checkentries',
        'SourcePot\statistic\statistic',
        'SourcePot\Asset\Rates'
    ];
    
    // SECURITY NOTICE: ALLOW_SOURCE_SELECTION should only be TRUE for Classes restricted to Admin access
    public const ALLOW_SOURCE_SELECTION=[
        'SourcePot\Datapool\AdminApps\Admin'=>TRUE,
        'SourcePot\Datapool\AdminApps\DbAdmin'=>TRUE,
        'SourcePot\Datapool\AdminApps\Settings'=>TRUE
    ];
    
    // database time zone setting should preferably be UTC as Unix timestamps are UTC based
    public const DB_TIMEZONE='UTC';
    public const NULL_DATE='9999-12-30 12:12:12';
    public const NULL_STRING='__MISSING__';
    public const ONEDIMSEPARATOR='|[]|';
    public const GUIDEINDICATOR='!GUIDE';
    public const USE_LANGUAGE_IN_TYPE=['docs'=>TRUE,'home'=>TRUE,'legal'=>TRUE];
    public const ASSETS_WHITELIST=['email.png'=>TRUE,'home.mp4'=>TRUE,'logo.jpg'=>TRUE,'dateType_example.png'=>TRUE,'login.jpg'=>TRUE,'Example_data_flow.png'=>TRUE];
    
    // profiling settings
    public const LOG_LEVEL=['production'=>'Production','monitoring'=>'Monitoring','debugging'=>'Debugging'];
    public const PROFILE=['index.php'=>TRUE,'js.php'=>FALSE,'job.php'=>TRUE,'import.php'=>FALSE,'resource.php'=>TRUE];
    public const PROFILING_BACKTRACE=4;
    public const PROFILE_WARNING_THRESHOLD_MICROSECONDS=5000;
    public const BUILDER_WARNING_THRESHOLD_MILLISECONDS=600;
    
    // required extensions
    public const REQUIRED_EXTENSIONS=[
        'ldap'=>FALSE,'curl'=>TRUE,'ffi'=>FALSE,'ftp'=>FALSE,
        'fileinfo'=>TRUE,'gd'=>TRUE,'gettext'=>TRUE,'gmp'=>FALSE,
        'intl'=>FALSE,'imap'=>FALSE,'mbstring'=>TRUE,'exif'=>TRUE,'bcmath'=>TRUE,
        'mysqli'=>TRUE,'oci8_12c'=>FALSE,'oci8_19'=>FALSE,'odbc'=>FALSE,
        'openssl'=>FALSE,'pdo_firebird'=>FALSE,'pdo_mysql'=>TRUE,'pdo_oci'=>FALSE,
        'pdo_odbc'=>FALSE,'pdo_pgsql'=>FALSE,'pdo_sqlite'=>FALSE,'pgsql'=>FALSE,
        'shmop'=>FALSE,'snmp'=>FALSE,'soap'=>FALSE,'sockets'=>FALSE,'sodium'=>FALSE,
        'sqlite3'=>FALSE,'tidy'=>FALSE,'xsl'=>FALSE,'zip'=>TRUE,'opcache'=>FALSE
    ];
    
        // csv-file settings
    public const CSV_SETTINGS=['separator'=>";",'enclosure'=>"\"",'escape'=>"\\",'eol'=>"\n"];

    private $oc=[];
    private $placeholder=[];
    private $implementedInterfaces=[];
    private $script='';

    private $loggerCache=[];

    private $currentUser=[];

    private $detections=['warning'=>FALSE,'error'=>FALSE];
    private $profile=[];
    private $builderProgress=[];
    
    public function __construct($script)
    {
        $this->getNonce(TRUE);
        $this->script=$script;
        // initialize the environment, setup the Object Collection (oc) with a temporary logger and setting up the user
        $this->oc=[__CLASS__=>$this,'logger'=>$this,'logger_1'=>$this];
        $GLOBALS['script start time']=hrtime(TRUE);
        date_default_timezone_set('UTC');
        // session start
        $this->builderProgress[hrtime(TRUE)]=__CLASS__.'&rarr;__constructor() called';
        session_start(self::SESSION_COCKIE);
        $this->builderProgress[hrtime(TRUE)]='Session started';
        $this->updateCurrentUser();
        $this->builderProgress[hrtime(TRUE)]='Current user updated';
        // inititate the web page state
        if (empty($_SESSION['page state'])){
            $_SESSION['page state']=['selectedCategory'=>'Home','selectedApp'=>[],'selected'=>[]];
        }
        // set exception handler and initialize directories
        $this->builderProgress[hrtime(TRUE)]='Page state initialized';
        $this->initDirs();
        // load all external components
        $this->builderProgress[hrtime(TRUE)]='Directories initialized';
        $_SESSION['page state']['autoload.php loaded']=FALSE;
        $autoloadFile=$GLOBALS['dirs']['vendor'].'/autoload.php';
        if (is_file($autoloadFile)){
            $_SESSION['page state']['autoload.php loaded']=TRUE;
            require_once $autoloadFile;
        }
        $this->initExceptionHandler();
        // initilize Object Collection, create objects and add logger
        $this->getInstantiatedObjectCollection();
        $this->oc['logger']=$this->getMonologLogger('Root');
        $this->oc['logger_1']=$this->getMonologLogger('Debugging');
        $this->registerVendorClasses();
        // distribute the object collection within the project
        $this->builderProgress[hrtime(TRUE)]='Object collection initialized incl. vendor classes ';
        foreach($this->oc as $classWithNamespace=>$obj){
            if (!is_object($this->oc[$classWithNamespace])){continue;}
            // get implemented interfaces
            if ($classWithNamespace!==__CLASS__ && $classWithNamespace!=='logger' && $classWithNamespace!=='logger_1'){
                foreach(class_implements($classWithNamespace) as $interface){
                    $this->implementedInterfaces[$interface][$classWithNamespace]=$classWithNamespace;
                }
            }
            // for classes with method loadOc -> the complete ObjectCollection will be handed over 
            if (method_exists($this->oc[$classWithNamespace],'loadOc')){
                $this->oc[$classWithNamespace]->loadOc($this->oc);
            }
        }
        $this->builderProgress[hrtime(TRUE)]='Interfaces registered & loadOC-methods invoked';
        $this->oc['logger']=$this->configureMonologLogger($this->oc['logger']);
        $this->emptyLoggerCache();
        $this->builderProgress[hrtime(TRUE)]='logger initialized';
        // invoke init methoods
        foreach($this->oc as $classWithNamespace=>$obj){
            if ($classWithNamespace===__CLASS__ || $classWithNamespace==='logger' || $classWithNamespace==='logger_1'){continue;}
            if (!is_object($obj)){
                continue;
            }
            // init methods
            if (!method_exists($this->oc[$classWithNamespace],'init')){
                continue;
            }
            $this->oc[$classWithNamespace]->init();
            $this->builderProgress[hrtime(TRUE)]=$classWithNamespace.'→init() invoked';
        }
        $this->checkExtensions();
        $this->builderProgress[hrtime(TRUE)]='checkExtensions done';
        $this->oc['SourcePot\Datapool\Foundation\User']->initAdminAccount();
        $this->builderProgress[hrtime(TRUE)]='initAdminAccount done';
    }
    
    /**
    * This method updates the current user based on the session, environment variable or creates an anonymous user otherwise
    *
    * @return array An associative array that contains the current user
    */
    public function updateCurrentUser($loginUser=[]):void
    {
        if (!empty($loginUser)){
            // remote client | BE CAREFUL, THIE OPTION BYPASSES THE LOGIN
            $this->currentUser=$loginUser;
            $_SESSION['currentUser']=$this->currentUser;
        } else if (empty($_SESSION['currentUser']['EntryId']) || empty($_SESSION['currentUser']['Privileges']) || empty($_SESSION['currentUser']['Owner'])){
            // empty session -> anonymous user
            $loginId=strval(mt_rand(1,999999999));
            $this->currentUser=['Source'=>'user','Group'=>'Public user','Folder'=>'Public','Name'=>'Anonymous','LoginId'=>$loginId,'Expires'=>date('Y-m-d H:i:s',time()+300),'Privileges'=>1,'Read'=>'ALL_MEMBER_R','Write'=>'ADMIN_R'];
            $this->currentUser['Content']=['Contact details'=>['First name'=>'Anonym','Family name'=>'Anonym'],'Address'=>[]];
            $this->currentUser['Params']=[];
            $this->currentUser['EntryId']=$this->currentUser['Owner']='ANONYM_'.password_hash($loginId,PASSWORD_DEFAULT);;
            $_SESSION['currentUser']=$this->currentUser;
        } else {
            // get user from session
            $this->currentUser=$_SESSION['currentUser'];
        }
    }

    public function getCurrentUser():array
    {
        return $this->currentUser;
    }

    public function getCurrentUserEntryId():string
    {
        return $this->currentUser['EntryId'];
    }

    public function getNonce($requestNew=FALSE):string
    {
        if ($requestNew || empty($GLOBALS['nonce'])){
            $GLOBALS['nonce']=base64_encode(random_bytes(20));
        }
        return $GLOBALS['nonce'];
    }

    public function getScript():string
    {
        return $this->script;
    }

    /**
    * This method returns a Monolog logger instance.
    *
    * @return array An associative array that contains the Datapool object collection, i.e. all initiated objects of Datapool.
    */
    private function getMonologLogger(string $channel='Root'):Logger
    {
        $logLevel=intval($this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('logLevel'));
        $logFile=$GLOBALS['dirs']['logging'].date('Y-m-d').' '.$channel.'.log';
        $logger = new Logger($channel);
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $logger->pushProcessor(new LoadAverageProcessor());
        if ($logLevel==='debugging'){
            $streamHandler = new StreamHandler($logFile,Level::Debug);
        } else if ($logLevel==='monitoring'){
            $streamHandler = new StreamHandler($logFile,Level::Notice);
        } else{
            $streamHandler = new StreamHandler($logFile,Level::Warning);
        }
        $logger->pushHandler($streamHandler);
        return $logger;
    }
    
    /**
    * This method adds a Monolog logger database handler
    *
    * @return Logger Is the logger instance
    */
    private function configureMonologLogger(Logger $logger):Logger
    {
        // log to database handler
        require_once(__DIR__.'/Foundation/logger/DbHandler.php');
        $dbHandler = new \SourcePot\Datapool\Foundation\Logger\DbHandler($this->oc);
        $logger->pushHandler($dbHandler);
        return $logger;
    }
    
    /**
    * This method stores log entries up to the time when $this->oc['logger'] is fully setup
    */
    public function log($level,$msg,$context):void
    {
        $this->loggerCache[]=['level'=>$level,'msg'=>$msg,'context'=>$context];
    }

    private function emptyLoggerCache():void
    {
        foreach($this->loggerCache as $logArr){
            $this->oc['logger']->log($logArr['level'],$logArr['msg'],$logArr['context']);
        }
    }

    /**
    * @return array An associative array that contains the Datapool object collection, i.e. all initiated objects of Datapool.
    * This method can be used to add external objects to the Datapool object collection. 
    */
    private function registerVendorClasses()
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        // instantiate external classes
        foreach(self::ADD_VENDOR_CLASSES as $classIndex=>$classWithNamespace){
            $context['classWithNamespace']=$classWithNamespace;
            if (class_exists($classWithNamespace)){
                $this->oc[$classWithNamespace]=new $classWithNamespace($this->oc);
            } else {
                $this->oc['logger']->log('error','Method "{class} &rarr; {function}()": Failed to register class "{classWithNamespace}"',$context);
            }
        }
    }
        
    public function getOc():array
    {
        return $this->oc;
    }
    
    public function addPlaceholder(string $key,string $value):array
    {
        $this->placeholder[$key]=$value;
        return $this->oc;
    }

    public function getPlaceholder(string $key):string
    {
        return (isset($this->placeholder[$key]))?$this->placeholder[$key]:\SourcePot\Datapool\Root::NULL_STRING;
    }

    public function substituteWithPlaceholder(array $arr):array
    {
        $newPlaceHolder=[];
        $accessOptions=$this->oc['SourcePot\Datapool\Foundation\Access']->getAccessOptions();
        $flatArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($arr);
        // substitute placeholders
        if ((string)$flatArr['EntryId']==='{{EntryId}}'){
            $flatArr['EntryId']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getEntryId();
        }
        foreach($flatArr as $flatKey=>$flatValue){
            if (is_object($flatValue) || empty($flatValue)){continue;}
            if (isset($accessOptions[$flatValue])){
                $flatArr[$flatKey]=$accessOptions[$flatValue];
            } else if (is_string($flatValue)){
                $flatArr[$flatKey]=strtr($flatValue,$this->placeholder);
            }
            $newPlaceHolder['{{'.$flatKey.'}}']=$flatArr[$flatKey];
        }
        // substitute new placeholders
        foreach($flatArr as $flatKey=>$flatValue){
            if (!is_string($flatValue)){continue;}
            $flatArr[$flatKey]=strtr($flatValue,$newPlaceHolder);
        }
        $arr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatArr);
        return $arr;
    }
    
    private function checkExtensions():void
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        foreach(self::REQUIRED_EXTENSIONS as $extension=>$isREquired){
            if (!$isREquired){continue;}
            if (!extension_loaded($extension)){
                $context['extension']=$extension;
                $this->oc['logger']->log('critical','PHP extension "{extension}" not loaded',$context);
            }
        }
    }

    /**
    * @return array An associative array that contains the full generated webpage referenced by the key "page html".
    */
    public function run():array
    {
        $this->builderProgress[hrtime(TRUE)]=__CLASS__.'&rarr;run() method called';
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        // get current temp dir
        if ($this->script!=='resource.php' && $this->script!=='job.php'){
            $GLOBALS['tmp user dir']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
        }
        // process all buttons
        $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn();
        $GLOBALS['script init time']=hrtime(TRUE);
        // get trace
        // add "page html" to the return array
        $arr=[];
        $this->builderProgress[hrtime(TRUE)]='All buttons processed';
        if ($this->script==='index.php'){
            // build webpage
            $appClassWithNamespace=$this->oc['SourcePot\Datapool\Foundation\Menu']->selectedApp()['Class'];
            $this->builderProgress[hrtime(TRUE)]='Checked web app access';
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBackbone($arr);
            $this->builderProgress[hrtime(TRUE)]='Webpage backbone built';
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageHeader($arr);
            $this->builderProgress[hrtime(TRUE)]='Webpage header added';
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBody($arr);
            $this->builderProgress[hrtime(TRUE)]='Webpage body static parts added';
            $arr=$this->oc[$appClassWithNamespace]->run($arr);
            $this->builderProgress[hrtime(TRUE)]='Webpage body content added';
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->finalizePage($arr);
            $this->builderProgress[hrtime(TRUE)]='Webpage finalized';
        } else if ($this->script==='js.php'){
            // js-call Processing
            $arr=$this->oc['SourcePot\Datapool\Foundation\Container']->jsCall($arr);
            $this->oc['SourcePot\Datapool\Foundation\User']->userStatusLog();
        } else if ($this->script==='job.php'){
            // job Processing
            $arr=$this->oc['SourcePot\Datapool\Foundation\Job']->trigger($arr);
        } else if ($this->script==='import.php'){
            // import Processing
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBackbone($arr);
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageHeader($arr);
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->addHtmlPageBody($arr);
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin()){
                $arr=$this->oc['SourcePot\Datapool\Foundation\Legacy']->importPage($arr);
            } else {
                $pageContent='Access to content of "'.$this->script.'" denied...';
                $arr['toReplace']['{{content}}']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h1','element-content'=>$pageContent,'keep-element-content'=>TRUE]);
            }
            $arr=$this->oc['SourcePot\Datapool\Foundation\Backbone']->finalizePage($arr);
        } else if ($this->script==='resource.php'){
            // client request processing
            $arr=$this->oc['SourcePot\Datapool\Foundation\ClientAccess']->request($arr);
        } else {
            // invalid
            $this->detections['error']=TRUE;
            $this->oc['logger']->log('error','Invalid script or run-method missing "{script}" called',['script'=>$this->script]);
            exit;  
        }
        // send header
        $this->sendHeader();
        $this->builderProgress[hrtime(TRUE)]='Header sent';
        // script time consumption in ms
        $description='When a script is called, the time consumption in miliseconds and timestamp is added to the signal';
        $timeConsumption=intval((hrtime(TRUE)-$GLOBALS['script start time'])/1000000);
        $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,$this->script.' time consumption [ms]',$timeConsumption,'int',['description'=>$description]);
        $this->builderProgress[hrtime(TRUE)]='Time consumption signal updated. Page content will be echoed next';
        // write log files
        $logLevel=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('logLevel');
        $this->writeBuilderProgress($logLevel);
        $this->writeProfile($logLevel);
        return $arr;
    }

    public function sendHeader()
    {
        $nonce=$this->getNonce(FALSE);
        foreach(self::HTTP_HEADER as $headerLine){
            $headerLine=str_replace('{{nonce}}',$nonce,$headerLine);
            header($headerLine);
        }
    }

    public function getImplementedInterfaces(string $interface=''):array
    {
        if (empty($interface)){
            return $this->implementedInterfaces;
        } else if (isset($this->implementedInterfaces[$interface])){
            return $this->implementedInterfaces[$interface];
        } else {
            throw new \ErrorException('Function '.__FUNCTION__.': Argument interface = "'.$interface.'" is invalid.',0,E_ERROR,__FILE__,__LINE__);
        }
    }

    public function source2class($source):string
    {
        foreach($this->oc as $classWithNamespace=>$obj){
            if (!is_object($obj)){continue;}
            if (!method_exists($this->oc[$classWithNamespace],'getEntryTable')){continue;}
            $entryTable=$this->oc[$classWithNamespace]->getEntryTable();
            if ($entryTable===$source){return $classWithNamespace;}
        }
        return '';
    }

    public function class2source(string $classWithNamespace):string
    {
        if (!isset($this->oc[$classWithNamespace])){
            return '';
        } else if (method_exists($this->oc[$classWithNamespace],'getEntryTable')){
            return $this->oc[$classWithNamespace]->getEntryTable();
        } else {
            return '';
        }
    }

    public function class2fileMeta(string $classWithNamespace):array
    {
        $meta=['class'=>$classWithNamespace];
        $classComps=explode('\\',$classWithNamespace);
        $meta['className']=array_pop($classComps);
        $meta['fileName']=$meta['className'].'.php';
        $meta['subDir']=array_pop($classComps);
        $meta['dir']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel(__DIR__.'/'.$meta['subDir']);
        $meta['file']=$meta['dir'].'/'.$meta['fileName'];
        return $meta;
    }
    
    /**
    * This method creates the ../src/setup/objectList.csv file that contains row by row all objects that make-up the Datapool object collection.
    * The objects will be initiated row by row. If a different order is required, the file needs to be edited accordingly.
    * In each class the Datapool object collection array can be accessed by $this->oc[...],
    * with "..." being the classWithNamespace which refers to the intantiated object of this class.
    */
    private function createObjList(string $objListFile)
    {
        $orderedInitialization=[
            'MiscTools.php'=>'301|',
            'Access.php'=>'302|',
            'Filespace.php'=>'303|',
            'Backbone.php'=>'304|',
            'Database.php'=>'305|',
            'Definitions.php'=>'306|',
            'Dictionary.php'=>'307|',
            'User.php'=>'308|',
            'HTMLbuilder.php'=>'309|',
            'Logging.php'=>'310|',
            'Logger.php'=>'311|',
            'Home.php'=>'701|',
            'Account.php'=>'702|',
            'Login.php'=>'901|',
            'Logout.php'=>'902|',
            ];
        $fileIndex=0;
        $objectsArr=['000|Header|'.$fileIndex=>['class','classWithNamespace','file','type']];
        // scan dirs
        $dir=$GLOBALS['dirs']['php'];
        $dirs=scandir($dir);
        foreach($dirs as $dirIndex=>$dirName){
            if (mb_strpos($dirName,'.php')!==FALSE || mb_strpos($dirName,'.md')!==FALSE || empty(trim($dirName,'.'))){continue;}
            $type=match($dirName){
                'Interfaces'=>'200|Interface',
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
                if (mb_strpos($file,'.php')===FALSE){continue;}
                $cleanType=trim($type,'|0123456789');
                $class=str_replace('.php','',$file);
                $classWithNamespace=__NAMESPACE__.'\\'.$dirName.'\\'.$class;
                if (isset($orderedInitialization[$file])){    
                    $objectsArr[$orderedInitialization[$file].$cleanType.'|'.$fileIndex]=[$class,$classWithNamespace,$subDir.$file,$cleanType];
                } else {
                    $objectsArr[$type.'|'.$fileIndex]=[$class,$classWithNamespace,$subDir.$file,$cleanType];
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
    private function getInstantiatedObjectCollection()
    {
        $objListNeedsToBeRebuild=FALSE;
        $objListFile=$GLOBALS['dirs']['setup'].'objectList.csv';
        if (!is_file($objListFile)){
            $this->createObjList($objListFile);
        }
        $headerArr=[];
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
                            $this->oc[$classWithNamespace]=new $classWithNamespace($this->oc);
                        }
                    } else {
                        $objListNeedsToBeRebuild=TRUE;
                    }
                }
            }
            fclose($handle);
        }
        if ($objListNeedsToBeRebuild){unlink($objListFile);}
    }

    private function initDirs():array
    {
        $relThisDirSuffix='/src/php';
        $wwwDirIndicator='/src/www';
        // relative dirs from root
        $GLOBALS['dirDefs']=[
            'root'=>['relPath'=>'.','permissions'=>0770],
            'vendor'=>['relPath'=>'./vendor','permissions'=>0770],
            'src'=>['relPath'=>'./src','permissions'=>0770],
            'setup'=>['relPath'=>'./src/setup','permissions'=>0770],
            'filespace'=>['relPath'=>'./src/filespace','permissions'=>0770],
            'privat tmp'=>['relPath'=>'./src/tmp_private','permissions'=>0770],
            'debugging'=>['relPath'=>'./src/debugging','permissions'=>0770],
            'logging'=>['relPath'=>'./src/logging','permissions'=>0770],
            'ftp'=>['relPath'=>'./src/ftp','permissions'=>0770],
            'fonts'=>['relPath'=>'./src/fonts','permissions'=>0770],
            'php'=>['relPath'=>'./src/php','permissions'=>0770],
            'public'=>['relPath'=>'./src/www','permissions'=>0775],
            'media'=>['relPath'=>'./src/www/media','permissions'=>0775,'createIndexFile'=>TRUE],
            'assets'=>['relPath'=>'./src/www/assets','permissions'=>0775,'createIndexFile'=>TRUE],
            'tmp'=>['relPath'=>'./src/www/tmp','permissions'=>0775,'createIndexFile'=>TRUE],
        ];
        $absRootPath=strtr(__DIR__,['\\'=>'/']);
        $absRootPath=strtr($absRootPath,[$relThisDirSuffix=>'']);
        // get absolute dirs
        $GLOBALS['dirs']=[];
        $GLOBALS['relDirs']=[];
        foreach($GLOBALS['dirDefs'] as $label=>$def){
            $GLOBALS['dirs'][$label]=str_replace('.',$absRootPath,$def['relPath']).'/';
            $relDirComps=explode($wwwDirIndicator,$def['relPath']);
            if (count($relDirComps)===2){
                $GLOBALS['relDirs'][$label]='.'.$relDirComps[1];
            }
            // create non-exsisting dirs
            if (!is_dir($GLOBALS['dirs'][$label])){
                mkdir($GLOBALS['dirs'][$label],$def['permissions'],TRUE);
                
            }
            // create www-index.php files
            if (!empty($def['createIndexFile'])){
                // create index.php content
                $indexFileContent='<?php'.PHP_EOL;
                foreach(self::HTTP_HEADER as $headerLine){
                    $indexFileContent.="header(\"$headerLine\");".PHP_EOL;
                }
                $indexFileContent.="echo '<p>FORBIDDEN :)<p>';".PHP_EOL;
                $indexFileContent.='?>';
                // save index.php
                $indexFileName=$GLOBALS['dirs'][$label].'/index.php';
                if (!is_file($indexFileName)){
                    file_put_contents($indexFileName,$indexFileContent);
                    chmod($indexFileName,0774);
                }
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
            $err=['date'=>date('Y-m-d H:i:s'),'additional info'=>$addInfo,'message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'code'=>$e->getCode(),'traceAsString'=>$e->getTraceAsString()];
            $logFileContent=json_encode($err);
            $logFileName=$GLOBALS['dirs']['debugging'].'/'.time().'_exceptionsLog.json';
            file_put_contents($logFileName,$logFileContent);
            //fallback page
            $html='';
            if ($this->script==='js.php'){
                $html.='Have run into a problem, please check debugging dir...';
            } else {
                $html.=$this->getBackupPageContent();
            }
            echo $html;
            exit;
        });
    }
    
    public function getBackupPageContent($msg='But some improvements might take a while.'):string
    {
        foreach(self::HTTP_HEADER as $headerLine){header($headerLine);}
        $builderProgressHtml='<li>'.implode('</li><li>',$this->builderProgress).'</li>';
        $builderProgressHtml='<ol style="position:absolute;bottom:0;font-size:0.6rem;">'.$builderProgressHtml.'</ol>';
        $html='<!DOCTYPE html>';
        $html.='<html xmlns="http://www.w3.org/1999/xhtml" lang="en">';
        $html.='<head>';
        $html.='</head>';
        $html.='<body style="color:#000;background-color:#fff;font:80% sans-serif;font-size:20px;">';
        $html.='<p style="width:fit-content;margin: 1rem auto;">We are very sorry for the interruption.</p>';
        $html.='<p style="width:fit-content;margin: 1rem auto;">The web page will be up and running as soon as possible.</p>';
        $html.='<p style="width:fit-content;margin: 1rem auto;">'.$msg.'</p>';
        $html.='<p style="width:fit-content;margin: 1rem auto;">The Admin <span style="font-size:2rem;">👷</span></p>';
        $html.=$builderProgressHtml;
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
        if (is_file($fileName)){
            $content=$this->file_get_contents_utf8($fileName);
            return $this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr($content);
        } else {
            return [];
        }
    }

    public function add2profile($callingClass,$callingFunction,$name,$startTimeStamp=NULL):bool
    {
        $profileTimeStamp=hrtime(TRUE);
        $timeConsumption=intval(($profileTimeStamp-$startTimeStamp)/1000);
        $row=['CallingClass'=>$callingClass,'CallingFunction'=>$callingFunction,'Name'=>$name];
        $row=$this->addTrace2row($row);
        $row['Finished [ms]']=intval(($profileTimeStamp-$GLOBALS['script start time'])/1000000);
        $row['Time consumption [us]']=$timeConsumption;
        if ($row['Time consumption [us]']>self::PROFILE_WARNING_THRESHOLD_MICROSECONDS){
            $this->detections['warning']=TRUE;
        }
        $this->profile[$profileTimeStamp]=$row;
        return TRUE;     
    }

    private function addTrace2row($row):array
    {
        $btOffset=2;
        $btIndex=self::PROFILING_BACKTRACE+$btOffset;
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,$btIndex+1);
        for($i=$btIndex;$i>$btOffset;$i--){
            $row['Trace '.$i]=(isset($trace[$i]))?$trace[$i]['function'].'['.$trace[$i]['line'].']':'';
        }
        $row['Hash']=md5(implode('|',$row));
        return $row;
    }
    
    private function writeProfile($logLevel)
    {
        if (empty(self::PROFILE[$this->script])){return FALSE;}
        if ($logLevel==='production' && !$this->detections['error']){return FALSE;}
        if ($logLevel==='monitoring' && !$this->detections['warning']){return FALSE;}
        // write profile
        $scriptComps=explode('.',$this->script);
        $scriptName=array_shift($scriptComps);
        $profileFileName=time().'-profile-'.$scriptName.'.csv';
        $profileFile=$GLOBALS['dirs']['logging'].$profileFileName;
        $this->arr2csvFile($profileFile,$this->profile);
    }

    private function writeBuilderProgress($logLevel)
    {
        if (empty(self::PROFILE[$this->script])){return FALSE;}
        if ($logLevel==='production' && !$this->detections['error']){return FALSE;}
        // check building progress
        $builderProgress=[];
        $oldTimestamp=$GLOBALS['script start time'];
        foreach($this->builderProgress as $timestamp=>$description){
            $timeConsumptionStep=intval(($timestamp-$oldTimestamp)/1000000);
            $timeConsumption=intval(($timestamp-$GLOBALS['script start time'])/1000000);
            $builderProgress[$timestamp]=['Time consumption step [ms]'=>$timeConsumptionStep,'Time consumption script [ms]'=>$timeConsumption,'Stage'=>$description];
            $oldTimestamp=$timestamp;
            if ($timeConsumptionStep>self::BUILDER_WARNING_THRESHOLD_MILLISECONDS){
                $this->detections['warning']=TRUE;
            }
        }
        if ($logLevel==='monitoring' && !$this->detections['warning']){return FALSE;}
        // write building progress
        $scriptComps=explode('.',$this->script);
        $scriptName=array_shift($scriptComps);
        $builderFileName=time().'-builder-'.$scriptName.'.csv';
        $builderFile=$GLOBALS['dirs']['logging'].$builderFileName;
        $this->arr2csvFile($builderFile,$builderProgress);
    }

    private function arr2csvFile(string $file,array $arr):bool
    {
        try{
            $fp=fopen($file,'w');
            $columns=array_keys(current($arr));
            fputcsv($fp,$columns,self::CSV_SETTINGS['separator'],self::CSV_SETTINGS['enclosure'],self::CSV_SETTINGS['escape'],self::CSV_SETTINGS['eol']);
            foreach($arr as $rowIndex=>$row){
                fputcsv($fp,$row,self::CSV_SETTINGS['separator'],self::CSV_SETTINGS['enclosure'],self::CSV_SETTINGS['escape'],self::CSV_SETTINGS['eol']);
            }
            fclose($fp);
        } catch(\Exception $e){
            return FALSE;
        }
        return TRUE;
    }

    public function getIP(bool $hashOnly=TRUE, string $salt=''):string
    {
        if (array_key_exists('HTTP_X_FORWARDED_FOR',$_SERVER)){
            $ip=$_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (array_key_exists('REMOTE_ADDR',$_SERVER)){
            $ip=$_SERVER["REMOTE_ADDR"];
        } else if (array_key_exists('HTTP_CLIENT_IP',$_SERVER)){
            $ip=$_SERVER["HTTP_CLIENT_IP"];
        }
        if (empty($ip)){
            return 'empty';
        } else if ($hashOnly){
            $ip=hash('sha256',$ip.$salt,FALSE);
        }
        return $ip;
    }

}
?>