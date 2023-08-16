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

class Signals{
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array();

    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }
    
    public function init($oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
    }
    
    public function getEntryTable(){return $this->entryTable;}
    
    private function getSignalSelector($callingClass,$callingFunction,$name){
        $signalSelector=array('Source'=>$this->entryTable,'Group'=>'signal','Folder'=>$callingClass.'::'.$callingFunction,'Name'=>$name);
        return $signalSelector;
    }

    public function updateSignal($callingClass,$callingFunction,$name,$value,$dataType='int',$read='ADMIN_R',$write='ADMIN_R'){
        $newContent=array('value'=>$value,'dataType'=>$dataType,'timeStamp'=>time());
        // create entry template
        $signal=$this->getSignalSelector($callingClass,$callingFunction,$name);
        $signal['Type']=$this->entryTable.' '.$dataType;
        $signal['Read']=$read;
        $signal['Write']=$write;
        $signal=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($signal,array('Source','Group','Folder','Name','Type'),'0','',TRUE);
        $signal=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($signal);
        $signal['Content']=array('signal'=>array());
        // get entry and update the entry
        $signal=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($signal,TRUE);
        $signal['Content']['signal']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->add2history($signal['Content']['signal'],$newContent,20);
        $signal=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($signal,TRUE);
        // update attached trigger
        $this->updateTrigger($signal);
        return $signal;
    }
    
    public function isActiveTrigger($EntryId,$isSystemCall=FALSE){
        $selector=array('Source'=>$this->entryTable,'Group'=>'trigger','EntryId'=>$EntryId);
        $trigger=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,$isSystemCall);
        return (!empty($trigger['Content']['isActive']));
    }
    
    public function resetTrigger($EntryId,$isSystemCall=FALSE){
        $trigger=array('Source'=>$this->entryTable,'Group'=>'trigger','EntryId'=>$EntryId);
        $trigger=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($trigger,$isSystemCall);
        if (!empty($trigger['Content']['isActive'])){
            $trigger['Content']['isActive']=FALSE;
            $trigger=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($trigger,$isSystemCall);
        }
        return $trigger;
    }

    private function updateTrigger($signal){
        $triggerSelector=array('Source'=>$this->entryTable,'Group'=>'trigger','Content'=>'%'.$signal['EntryId'].'%');
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($triggerSelector,TRUE,'Read','Name') as $trigger){
            $trigger['Read']=$signal['Read'];
            $trigger['Write']=$signal['Write'];
            if (!isset($trigger['Content']['trigger'])){$trigger['Content']['trigger']=array();}
            $trigger['Content']['signal']=$signal['Content']['signal'];
            $trigger['Content']['isActive']=$this->slopDetector($trigger,$signal);
            $trigger['Content']['trigger']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->add2history($trigger['Content']['trigger'],array('timeStamp'=>time(),'value'=>intval($trigger['Content']['isActive'])),20);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($trigger,TRUE);
        }
        return TRUE;
    }
    
    private function slopDetector($trigger,$signal){
        $arr=array();
        foreach($signal['Content']['signal'] as $index=>$signalArr){
            if (strcmp($signalArr['dataType'],'int')===0 || strcmp($signalArr['dataType'],'bool')===0){
                $arr['values'][$index]=intval($signalArr['value']);
            } else if (strcmp($signalArr['dataType'],'float')===0){
                $arr['values'][$index]=floatval($signalArr['value']);    
            }
        }
        $threshold=intval($trigger['Content']['Threshold']);
        $condtionMet=match($trigger['Content']['Active if']){
                        'stable'=>abs($arr['values'][0]-$arr['values'][1])<=$threshold && abs($arr['values'][1]-$arr['values'][2])<=$threshold,
                        'above'=>$arr['values'][0]>=$threshold,
                        'up'=>($arr['values'][0]-$arr['values'][1])>=$threshold,
                        'down'=>($arr['values'][1]-$arr['values'][0])>=$threshold,
                        'min'=>($arr['values'][0]-$arr['values'][1])>=$threshold && ($arr['values'][2]-$arr['values'][1])>=$threshold,
                        'max'=>($arr['values'][1]-$arr['values'][0])>=$threshold && ($arr['values'][1]-$arr['values'][2])>=$threshold,
                        'below'=>$arr['values'][0]<=$threshold,
                        };
        
        return ($condtionMet)?$condtionMet:((isset($trigger['Content']['isActive']))?$trigger['Content']['isActive']:FALSE);
    }
    
    public function getTriggerWidget($callingClass,$callingFunction){
        $triggerType=$this->entryTable.' trigger';
        $trigger=array('Source'=>$this->entryTable,'Group'=>'trigger','Folder'=>$callingClass.'::'.$callingFunction,'Type'=>$triggerType,'Read'=>'ADMIN_R','Write'=>'ADMIN_R');
        $trigger=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($trigger);
        // trigger form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['Save']) || isset($formData['cmd']['New'])){
            $trigger['EntryId']=key(current($formData['cmd']));
            $trigger=array_replace_recursive($trigger,$formData['val'][$trigger['EntryId']]);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($trigger);
        } else if (isset($formData['cmd']['Reset'])){
            $trigger['EntryId']=key(current($formData['cmd']));
            $this->resetTrigger($trigger['EntryId']);
        } else if (isset($formData['cmd']['Delete'])){
            $trigger['EntryId']=key(current($formData['cmd']));
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries(array('Source'=>$trigger['Source'],'EntryId'=>$trigger['EntryId']));
        }
        // get trigger rows
        $matrix=array('New'=>$this->getTriggerRow());
        $triggerSelector=array('Source'=>$this->entryTable,'Group'=>'trigger','Folder'=>$callingClass.'::'.$callingFunction);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($triggerSelector,FALSE,'Read','Name') as $trigger){
            $matrix[$trigger['EntryId']]=$this->getTriggerRow($trigger);
        }
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Trigger settings','hideKeys'=>TRUE,'keep-element-content'=>TRUE));
        return $html;
    }

    private function getTriggerRow($trigger=array()){
        $callingFunction='getTriggerWidget';
        $arr=array('Signal'=>$this->getSignalOptions());
        $arr['Active if']=array('stable'=>'&#9596;&#9598;&#9596;&#9598;&#9596;&#9598;&#9596;&#9598; (stable range)',
                                'above'=>'&#9601;&#9601; &#10514; &#9620;&#9620; (trigger above th.)',    
                                'up'=>'&#9601;&#9601;&#9601;&#9585;&#9620; (rel. step up)',
                                'max'=>'&#9601;&#9601;&#9585;&#9586;&#9601; (peak)',
                                'min'=>'&#9620;&#9620;&#9586;&#9585;&#9620; (dip)',
                                'down'=>'&#9620;&#9620;&#9620;&#9586;&#9601; (rel. step down)',
                                'below'=>'&#9620;&#9620; &#10515; &#9601;&#9601; (trigger below th.)',    
                                );
        $row=array();
        $isNewRow=empty($trigger['EntryId']);
        if (!isset($trigger['EntryId'])){$trigger['EntryId']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getEntryId();}
        if (isset($trigger['Name'])){$value=$trigger['Name'];} else {$value='My new trigger';}
        $row['Name']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'text','value'=>$value,'key'=>array($trigger['EntryId'],'Name'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE));
        if (isset($trigger['Content']['Signal'])){$selected=$trigger['Content']['Signal'];} else {$selected='';}
        $row['Signal']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$arr['Signal'],'selected'=>$selected,'keep-element-content'=>TRUE,'key'=>array($trigger['EntryId'],'Content','Signal'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE));
        if (isset($trigger['Content']['Threshold'])){$value=$trigger['Content']['Threshold'];} else {$value='1';}
        $row['Threshold']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'text','value'=>$value,'key'=>array($trigger['EntryId'],'Content','Threshold'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE));
        if (isset($trigger['Content']['Active if'])){$selected=$trigger['Content']['Active if'];} else {$selected='up';}
        $row['Active if']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$arr['Active if'],'selected'=>$selected,'keep-element-content'=>TRUE,'key'=>array($trigger['EntryId'],'Content','Active if'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE));
        if ($isNewRow){
            $isActive='';
            $reset='';
            $row['Cmd']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'+','keep-element-content'=>TRUE,'key'=>array('New',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
        } else {
            $isActive=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(!empty($trigger['Content']['isActive']));
            $reset=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Reset','keep-element-content'=>TRUE,'key'=>array('Reset',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
            $row['Cmd']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'key'=>array('Save',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
            $row['Cmd'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'&coprod;','keep-element-content'=>TRUE,'hasCover'=>TRUE,'key'=>array('Delete',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
        }
        if (isset($trigger['Content']['signal'])){
            ksort($trigger['Content']['signal']);
            foreach($trigger['Content']['signal'] as $signalIndex=>$signal){
                if ($signalIndex>5){break;}
                $elementArr=array('tag'=>'p','element-content'=>intval($signal['value']));
                $row[$signalIndex]=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elementArr);
            }
        }
        $row['isActive']=$isActive;
        $row['Reset']=$reset;
        return $row;
    }
    
    public function getSignalOptions($selector=array()){
        $selector['Group']='signal';
        return $this->getOptions($selector);
    }
    
    public function getTriggerOptions($selector=array()){
        $selector['Group']='trigger';
        return $this->getOptions($selector);
    }

    public function getOptions($selector=array()){
        $options=array();
        $selector['Source']=$this->getEntryTable();
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Name') as $entry){
            $classStartPos=strrpos($entry['Folder'],'\\')+1;
            $classEndPos=strpos($entry['Folder'],'::');
            $options[$entry['EntryId']]=substr($entry['Folder'],$classStartPos,$classEndPos-$classStartPos).': '.$entry['Name'];
        }
        asort($options);
        return $options;
    }

    public function canvasElement2signal($canvasElement,$rowCount=FALSE){
        if ($rowCount===FALSE){
            $rowCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($canvasElement['Content']['Selector'],TRUE);
        }
        $this->updateSignal($canvasElement['Folder'],__FUNCTION__,$canvasElement['Content']['Style']['Text'],$rowCount,'int','ALL_CONTENTADMIN_R','ALL_CONTENTADMIN_R');
    }

    public function event2signal($callingClass,$callingFunction,$events){
        $setEvents=array();
        // set value of upcomming and current event signal
        foreach($events as $EntryId=>$event){
            $eventName=$event['Name'];
            if (strcmp($event['State'],'Finnishing event')===0){
                $setEvents[$eventName]=TRUE;
                $signal=$this->updateSignal($callingClass,$callingFunction,$eventName,1,'bool','ALL_CONTENTADMIN_R','ALL_CONTENTADMIN_R');            
            } else if (!isset($setEvents[$eventName])){
                $setEvents[$eventName]=FALSE;
                $signal=$this->updateSignal($callingClass,$callingFunction,$eventName,0,'bool','ALL_CONTENTADMIN_R','ALL_CONTENTADMIN_R');            
            }
        }
        // set value of old event signals
        $signalSelector=$this->getSignalSelector($callingClass,$callingFunction,'%');
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($signalSelector,TRUE,'Read','Name') as $signal){
            if (isset($setEvents[$signal['Name']])){continue;}
            $setEvents[$eventName]=FALSE;
            $signal=$this->updateSignal($callingClass,$callingFunction,$signal['Name'],0,'bool','ALL_CONTENTADMIN_R','ALL_CONTENTADMIN_R');            
        }
        return count($setEvents);
    }

}
?>