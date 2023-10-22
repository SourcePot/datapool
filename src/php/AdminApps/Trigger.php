<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\AdminApps;

class Trigger implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    private $entryTable='';
    
    public function __construct($oc){
        $this->oc=$oc;
        $this->entryTable=$this->oc['SourcePot\Datapool\Foundation\Signals']->getEntryTable();
    }

    public function init(array $oc){
        $this->oc=$oc;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Admin','Emoji'=>'&#10548;','Label'=>'Trigger','Read'=>'ADMIN_R','Class'=>__CLASS__);
        } else {
            $html='';
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Trigger widget','generic',array(),array('method'=>'triggerWidgetWrapper','classWithNamespace'=>__CLASS__),array());
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Message widget','generic',array(),array('method'=>'messageWidgetWrapper','classWithNamespace'=>__CLASS__),array());
            // add event chart
            $selector=array('Source'=>$this->entryTable);
            $settings=array('classWithNamespace'=>__CLASS__,'method'=>'getSignalsEventChart','width'=>600);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Trigger events','generic',$selector,$settings,array());    
            // finalize page
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
 
    public function triggerWidgetWrapper($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Signals']->getTriggerWidget(__CLASS__,__FUNCTION__);
        return $arr;
    }

    public function messageWidgetWrapper($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Signals']->getMessageWidget(__CLASS__,__FUNCTION__);
        return $arr;
    }

    public function getSignalsEventChart($arr=array()){
        if (!isset($arr['html'])){$arr['html']='';}
        // init settings
        $settingOptions=array('timespan'=>array('600'=>'10min','3600'=>'1hr','43200'=>'12hrs','86400'=>'1day'),
                              'width'=>array(300=>'300px',600=>'600px',1200=>'1200px'),
                              'height'=>array(300=>'300px',600=>'600px',1200=>'1200px'),
                              );
        foreach($settingOptions as $settingKey=>$options){
            if (!isset($arr['settings'][$settingKey])){$arr['settings'][$settingKey]=key($options);}
        }
        // process form
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (!empty($formData['cmd'])){
            $arr['settings']=array_merge($arr['settings'],$formData['val']['settings']);
        }
        // get instance of EventChart
        require_once($GLOBALS['dirs']['php'].'Foundation/charts/EventChart.php');
        $chart=new \SourcePot\Datapool\Foundation\Charts\EventChart($this->oc,$arr['settings']);
        // get selectors
        $selectors=array(array('Source'=>'signals','Group'=>'trigger'),array('Source'=>'signals','Group'=>'signal'));
        foreach($selectors as $selectorIndex=>$selector){    
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','Date') as $entry){
                if (!isset($entry['Content'][$selector['Group']])){continue;}
                foreach($entry['Content'][$selector['Group']] as $signalIndex=>$rawEvent){
                    $event=array('name'=>ucfirst($entry['Group']).'|'.$entry['Name'],'timestamp'=>$rawEvent['timeStamp'],'value'=>intval($rawEvent['value']));
                    $chart->addEvent($event);
                } // loop through events
            } // loop through entries
        } // loop through selectors
        $arr['html'].=$chart->getChart(ucfirst($arr['selector']['Source']));
        $cntrArr=array('callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'excontainer'=>FALSE);
        $matrix=array('Cntr'=>array());
        foreach($settingOptions as $settingKey=>$options){
            $cntrArr['options']=$options;
            $cntrArr['selected']=$arr['settings'][$settingKey];
            $cntrArr['key']=array('settings',$settingKey);
            $matrix['Cntr'][$settingKey]=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($cntrArr);
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE));
        return $arr;       
    }

}
?>