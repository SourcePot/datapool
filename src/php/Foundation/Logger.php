<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

use Monolog\LogRecord;
use Monolog\Level;

class Logger
{
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array();
    
    private $levelConfig=array('emergency'=>array('hashIp'=>FALSE,'lifetime'=>'P1Y','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>TRUE,'style'=>array('color'=>'#f00','min-width'=>'6rem')),
                               'alert'=>array('hashIp'=>FALSE,'lifetime'=>'P30D','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>TRUE,'style'=>array('color'=>'#f44','min-width'=>'6rem')),
                               'critical'=>array('hashIp'=>FALSE,'lifetime'=>'P30D','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>TRUE,'style'=>array('color'=>'#f88','min-width'=>'6rem')),
                               'error'=>array('hashIp'=>FALSE,'lifetime'=>'P10D','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>TRUE,'style'=>array('color'=>'#faa','min-width'=>'6rem')),
                               'warning'=>array('hashIp'=>TRUE,'lifetime'=>'P10D','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>TRUE,'style'=>array('color'=>'#fcc','min-width'=>'6rem')),
                               'notice'=>array('hashIp'=>TRUE,'lifetime'=>'P1D','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>FALSE,'addTrace'=>FALSE,'style'=>array('color'=>'#ff0','min-width'=>'6rem')),
                               'info'=>array('hashIp'=>TRUE,'lifetime'=>'P1D','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>FALSE,'addTrace'=>FALSE,'style'=>array('color'=>'#fff','min-width'=>'6rem')),
                               'debug'=>array('hashIp'=>TRUE,'lifetime'=>'PT10M','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>FALSE,'style'=>array('color'=>'#fff','min-width'=>'6rem')),
                               );
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }
 
    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
    
    public function job($vars){
        // create and update signals for relevant logging Groups
        $relevantGroups=array('emergency','alert','critical','error','warning');
        $selector=array('Source'=>$this->entryTable);
        foreach($relevantGroups as $Group){
            $selector['Group']=$Group;
            $value=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,TRUE);
            $signal=$this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,'Entries',$Group,$value,'int');
        }
        // cleanup
        if (isset($signal['Date'])){
            $toDeleteSelector=$this->oc['SourcePot\Datapool\Foundation\Signals']->getSignalSelector(__CLASS__,'Entries');
            $toDeleteSelector['Date<']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime($signal['Date'],'-P1D');
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($toDeleteSelector,TRUE);
        }
        return $vars;
    }    
    
    /**
     * Is a test fixture for class methods based on arguments provided.
     * If the method test finishes without an exceptions, an log entry is created with provided logLevel.
     * In case of an exception the LogLevel will be "error".
     *
     * @param $classInstance the method belongs to
     * @param string $method Name of the method
     * @param array $args Array containing all method arguments for the test
     * @param string $logLevel Is the LogLevel if test finishes without exceptions
     * @param string $logger Is tghe name of the logger instance to be used. Instances are created within class Root
     *
     * @return bool TRUE if no exception is triggered, FALSE if an exception is triggered otherwise
     */
    public function methodTest($classInstance,string $method,array $args,string $logLevel='info',string $logger='logger_1'):array
    {
        $class=get_class($classInstance);
        $context=array('class'=>$class,'method'=>$method);
        $msg='{class} &rarr; {function}()';
        try{
            $paramsStr='';
            $f=new \ReflectionMethod($class,$method);
            foreach($f->getParameters() as $pIndex=>$param){
                $paramsStr.='{'.$param->name.'},';
                $context[$param->name]=$args[$pIndex];
            }
            $return=call_user_func_array(array($classInstance,$method),$args);
            $context['return']=$return;
            $context['return dataType']=gettype($return);
            $msg.='('.trim($paramsStr,', ').') returned {return}';
        } catch (\Exception $e){
            $logLevel='error';
            $context['exception']=$e->getMessage();
            $msg.=' threw exception {exception}';
        }
        $this->oc[$logger]->log($logLevel,$msg,$context);
        return $context;
    }
    
    public function addLog(LogRecord $record)
    {
        $level=mb_strtolower($record->level->name);
        $context=array_merge($record->context,$record->extra);
        $lifetime=(empty($context['lifetime']))?$this->levelConfig[$level]['lifetime']:$context['lifetime'];
        $context['ip']=$this->oc['SourcePot\Datapool\Root']->getIP($this->levelConfig[$level]['hashIp']);
        $context['timestamp']=time();
        $entry=$this->levelConfig[$level];
        $entry['Owner']=(empty($entry['Owner']))?$_SESSION['currentUser']['EntryId']:$entry['Owner'];
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Read');
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Write');
        $entry['Source']=$this->entryTable;
        $entry['Group']=$level;
        $entry['Folder']=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId();
        $entry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now',$lifetime);
        $entry['Content']=$context;
        $entry['Content']['msg']=$record->message;
        if ($this->levelConfig[$level]['addTrace']){
            $entry['Content']['trace']=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            // remove traces due to logging itsself
            unset($entry['Content']['trace'][0]);
            unset($entry['Content']['trace'][1]);
            unset($entry['Content']['trace'][2]);
            unset($entry['Content']['trace'][3]);
        }
        $entry['Name']=mb_substr($entry['Content']['msg'],0,100);
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name'),0);
        $entry['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
        // write to database
        if (!empty($this->oc['SourcePot\Datapool\Foundation\Database']->getDbStatus())){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
        }
    }
    
    public function getLogsHtml(array $arr):array
    {
        $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        $sourceTimezone=\SourcePot\Datapool\Root::DB_TIMEZONE;
        $today=$this->oc['SourcePot\Datapool\GenericApps\Calendar']->getTimezoneDate('now',$sourceTimezone,$pageTimeZone);
        $today=mb_substr($today,0,11);
        $columns=array('Date','Group','Content'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator().'msg');
        $arr['settings']=array_replace_recursive(array('orderBy'=>'Date','isAsc'=>FALSE,'limit'=>FALSE,'offset'=>0,'columns'=>$columns,'class'=>'log'),$arr['settings']);
        $arr['selector']['Source']=$this->entryTable;
        $arr['html']=' ';
        $_SESSION[__CLASS__]['age']=10;
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($arr['selector'],FALSE,'Read',$arr['settings']['orderBy'],$arr['settings']['isAsc'],$arr['settings']['limit'],$arr['settings']['offset']) as $log){
            if (isset($log['Content']['timestamp'])){
                $age=time()-$log['Content']['timestamp'];
                if ($_SESSION[__CLASS__]['age']>$age){$_SESSION[__CLASS__]['age']=$age;}
            }
            $rowHtml='';
            $flatLog=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($log);
            $flatLog['Date']=$this->oc['SourcePot\Datapool\GenericApps\Calendar']->getTimezoneDate($flatLog['Date'],$sourceTimezone,$pageTimeZone);
            $flatLog['Date']=str_replace($today,'',$flatLog['Date']);
            foreach($arr['settings']['columns'] as $column){
                if (!isset($flatLog[$column])){continue;}
                $rowHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$flatLog[$column],'keep-element-content'=>TRUE,'style'=>$this->levelConfig[$log['Group']]['style'],'class'=>$arr['settings']['class']));
            }
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$rowHtml,'keep-element-content'=>TRUE,'class'=>$arr['settings']['class']));
        }
        return $arr;
    }
    
    public function getMyLogs():string
    {
        $arr=array();
        $arr['selector']=array('Source'=>$this->entryTable,'Folder'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId());
        $arr['settings']=array('method'=>'getLogsHtml','classWithNamespace'=>__CLASS__);
        $arr['wrapper']=array('class'=>'toolbox','style'=>array('overflow-y'=>'scroll','background-color'=>'#444'));
        $contentHtml=$this->oc['SourcePot\Datapool\Foundation\Container']->container('My Logs '.__FUNCTION__,'generic',$arr['selector'],$arr['settings'],$arr['wrapper']);
        // add to app
        $appArr=array('class'=>'toolbox','icon'=>'Logger');
        if ($_SESSION[__CLASS__]['age']<2){$appArr['open']=TRUE;}
        $appArr['html']=$contentHtml;
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        return $html;
    } 
    
}
?>