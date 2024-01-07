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

class Logger extends \Psr\Log\AbstractLogger
{
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array();
    
    private $levelConfig=array('emergency'=>array('hashIp'=>FALSE,'lifetime'=>'P1Y','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>TRUE,'style'=>array('color'=>'#f00','min-width'=>'6rem')),
                               'alert'=>array('hashIp'=>FALSE,'lifetime'=>'P30D','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>TRUE,'style'=>array('color'=>'#f44','min-width'=>'6rem')),
                               'critical'=>array('hashIp'=>FALSE,'lifetime'=>'P30D','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>TRUE,'style'=>array('color'=>'#f88','min-width'=>'6rem')),
                               'error'=>array('hashIp'=>FALSE,'lifetime'=>'P10D','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>TRUE,'style'=>array('color'=>'#faa','min-width'=>'6rem')),
                               'warning'=>array('hashIp'=>TRUE,'lifetime'=>'P1D','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>TRUE,'style'=>array('color'=>'#fcc','min-width'=>'6rem')),
                               'notice'=>array('hashIp'=>TRUE,'lifetime'=>'PT5M','Read'=>'ALL_MEMBER_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>FALSE,'style'=>array('color'=>'#fff','min-width'=>'6rem')),
                               'info'=>array('hashIp'=>TRUE,'lifetime'=>'PT5M','Read'=>'ALL_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>FALSE,'style'=>array('color'=>'#fff','min-width'=>'6rem')),
                               'debug'=>array('hashIp'=>TRUE,'lifetime'=>'PT10M','Read'=>'ALL_CONTENTADMIN_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM','addTrace'=>FALSE,'style'=>array('color'=>'#fff','min-width'=>'6rem')),
                               );
    
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }
    
    public function init($oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
        $this->registerToolbox();
    }
    
    public function getEntryTable(){return $this->entryTable;}

    public function getEntryTemplate(){return $this->entryTemplate;}
    
    public function log($level, string|\Stringable $message, array $context=[]):void
    {
        $context['ip']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getIP($this->levelConfig[$level]['hashIp']);
        $entry=$this->levelConfig[$level];
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Read');
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Write');
        $entry['Source']=$this->entryTable;
        $entry['Group']=$level;
        $entry['Type']=$entry['Source'].' '.$level;
        $entry['Folder']=isset($_SESSION['currentUser']['EntryId'])?$_SESSION['currentUser']['EntryId']:'ANONYM';
        $entry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now',$this->levelConfig[$level]['lifetime']);
        $entry['Content']=$context;
        $entry['Content']['msg']=$this->interpolate($message,$context);
        $entry=($this->levelConfig[$level]['addTrace'])?$this->addTrace($entry):$entry;
        $entry['Name']=substr($entry['Content']['msg'],0,50);
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name','Type'),0);
        $entry['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
        if (empty($this->oc['SourcePot\Datapool\Foundation\Database']->getDbStatus())){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($entry,hrtime(TRUE).'_'.$level.'_log.json');
        } else {
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
        }
    }
    
    private function addTrace($entry)
    {
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,5);
        for($index=1;$index<4;$index++){
            if (!isset($trace[$index])){break;}
            $entry['Content']['trace'][]=$trace[$index];
        }
        return $entry;
    }
    
    private function interpolate( string $message, array $context=array()):string
    {
        $replace=array();
        foreach ($context as $key=>$val){
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{'.$key.'}']=$val;
            }
        }
        return strtr($message,$replace);
    }
    
    public function getLogsHtml($arr){
        $pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
        $sourceTimezone=$this->oc['SourcePot\Datapool\Foundation\Database']->getDbTimezone();
        $targetTimezone=$pageSettings['pageTimeZone'];
        $today=$this->oc['SourcePot\Datapool\GenericApps\Calendar']->getTimezoneDate('now',$sourceTimezone,$targetTimezone);
        $today=substr($today,0,11);
        $columns=array('Date','Group','Content'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator().'msg');
        $arr['settings']=array_replace_recursive(array('orderBy'=>'Date','isAsc'=>FALSE,'limit'=>FALSE,'offset'=>0,'columns'=>$columns,'class'=>'log'),$arr['settings']);
        $arr['selector']['Source']=$this->entryTable;
        $arr['html']=(isset($arr['html']))?$arr['html']:'';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($arr['selector'],FALSE,'Read',$arr['settings']['orderBy'],$arr['settings']['isAsc'],$arr['settings']['limit'],$arr['settings']['offset']) as $log){
            $rowHtml='';
            $flatLog=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($log);
            $flatLog['Date']=$this->oc['SourcePot\Datapool\GenericApps\Calendar']->getTimezoneDate($flatLog['Date'],$sourceTimezone,$targetTimezone);
            $flatLog['Date']=str_replace($today,'',$flatLog['Date']);
            foreach($arr['settings']['columns'] as $column){
                if (!isset($flatLog[$column])){continue;}
                $rowHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$flatLog[$column],'keep-element-content'=>TRUE,'style'=>$this->levelConfig[$log['Group']]['style'],'class'=>$arr['settings']['class']));
            }
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$rowHtml,'keep-element-content'=>TRUE,'class'=>$arr['settings']['class']));
        }
        return $arr;
    }
    
    public function getMyLogs(){
        $arr=array();
        $arr['selector']=array('Source'=>$this->entryTable,'Folder'=>$_SESSION['currentUser']['EntryId']);
        $arr['settings']=array('method'=>'getLogsHtml','classWithNamespace'=>__CLASS__);
        $arr['wrapper']=array('class'=>'toolbox','style'=>array('overflow-y'=>'scroll','background-color'=>'#000'));
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('My Logs '.__FUNCTION__,'generic',$arr['selector'],$arr['settings'],$arr['wrapper']);
        return $html;
    } 
    
    public function registerToolbox(){
        $toolbox=array('Name'=>'Logger',
                       'Content'=>array('class'=>__CLASS__,'method'=>'getMyLogs','args'=>array('maxCount'=>10),'settings'=>array())
                       );
        $toolbox=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($toolbox,'ALL_R','ADMIN_R');
        $toolbox=$this->oc['SourcePot\Datapool\Foundation\Toolbox']->registerToolbox(__CLASS__,$toolbox);
        if (empty($_SESSION['page state']['toolbox']) && !empty($toolbox['EntryId'])){$_SESSION['page state']['toolbox']=$toolbox['EntryId'];}
        return $toolbox;
    }


}
?>