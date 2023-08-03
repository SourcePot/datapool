<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Processing;

class CanvasTrigger implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    private $slopeOptions=array('&#9472;&#9472;&#9488;__','__&#9484;&#9472;&#9472;');
    private $typeOptions=array('empty'=>'Canvas element is empty','stable'=>'Content stable','increase'=>'Content increase','decrease'=>'Content decrease');
        
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }
    
    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
    }

    public function job($vars){
        if (empty($vars['CanvasTrigger to process'])){
            $selector=array('Source'=>'dataexplorer','Group'=>'Canvas elements','Content'=>'%CanvasTrigger%');
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','EntryId',TRUE) as $entry){
                $vars['CanvasTrigger to process'][$entry['EntryId']]=$entry;
            }
        }
        if (!empty($vars['CanvasTrigger to process'])){
            $callingElement=array_shift($vars['CanvasTrigger to process']);
            $vars['Result']=$this->runCanvasTrigger($callingElement,FALSE);    
            $vars['CanvasTrigger to go']=count($vars['CanvasTrigger to process']);
        }
        return $vars;
    }

    public function getEntryTable():string{return $this->entryTable;}
    
    public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
        // This method is the interface of this data processing class
        // The Argument $action selects the method to be invoked and
        // argument $callingElementSelector$ provides the entry which triggerd the action.
        // $callingElementSelector ... array('Source'=>'...', 'EntryId'=>'...', ...)
        // If the requested action does not exist the method returns FALSE and 
        // TRUE, a value or an array otherwise.
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        switch($action){
            case 'run':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runCanvasTrigger($callingElement,$testRunOnly=FALSE);
                }
                break;
            case 'test':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runCanvasTrigger($callingElement,$testRunOnly=TRUE);
                }
                break;
            case 'widget':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getCanvasTriggerWidget($callingElement);
                }
                break;
            case 'settings':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getCanvasTriggerSettings($callingElement);
                }
                break;
            case 'info':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getCanvasTriggerInfo($callingElement);
                }
                break;
        }
        return FALSE;
    }

    private function getCanvasTriggerWidget($callingElement){
        $callingElement['refreshInterval']=60;
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Canvas trigger','generic',$callingElement,array('method'=>'getCanvasTriggerWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
    public function getCanvasTriggerWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].='';
        return $arr;
    }
    
    private function getCanvasTriggerSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('CanvasTrigger entries settings','generic',$callingElement,array('method'=>'getCanvasTriggerSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getCanvasTriggerSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Signals']->getTriggerWidget(__CLASS__,__FUNCTION__);
        return $arr;
    }
    
    private function runCanvasTrigger($callingElement,$testRunOnly){
        $result=array('Info'=>array('value'=>'Nothing to do here...'));
        return $result;
    }

    public function callingElement2arr($callingClass,$callingFunction,$callingElement){
        if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
        $type=$this->oc['SourcePot\Datapool\Root']->class2source(__CLASS__);
        $type.='|'.$callingFunction;
        $entry=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Type'),0);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ALL_CONTENTADMIN_R');
        $entry['Content']=array();
        $arr=array('callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'selector'=>$entry);
        return $arr;
    }

}
?>