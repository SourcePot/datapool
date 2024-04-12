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
    private $entryTemplate=array('Expires'=>array('index'=>FALSE,'type'=>'DATETIME','value'=>'2999-01-01 01:00:00','Description'=>'If the current date is later than the Expires-date the entry will be deleted. On insert-entry the init-value is used only if the Owner is not anonymous, set to 10mins otherwise.'),
                                 'Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
                                 'Owner'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'SYSTEM','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.')
                                 );
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }
    
    public function init(array $oc)
    {
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
    }
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getSignalSelector(string $callingClass,string $callingFunction,string|bool $name=FALSE):array
    {
        $signalSelector=array('Source'=>$this->entryTable,'Group'=>'signal','Folder'=>$callingClass.'::'.$callingFunction,'Name'=>$name);
        if ($name===FALSE){
            unset($signalSelector['Name']);
            return $signalSelector;
        }
        $signalSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($signalSelector,array('Source','Group','Folder','Name'),'0','',TRUE);
        return $signalSelector;
    }
    
    public function updateSignal(string $callingClass,string $callingFunction,string $name,$value,$dataType='int'):array
    {
        $newContent=array('value'=>$value,'dataType'=>$dataType,'timeStamp'=>time());
        // create entry template or get existing entry
        $signalSelector=$this->getSignalSelector($callingClass,$callingFunction,$name);
        $signal=array('Type'=>$this->entryTable.' '.$dataType,'Content'=>array('signal'=>array()));
        $signal=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($signal);
        $signal=array_merge($signal,$signalSelector);
        $signal=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($signal,TRUE);
        // update signal
        $signal['Content']['signal']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->add2history($signal['Content']['signal'],$newContent);
        $signal['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
        $signal['Owner']='SYSTEM';
        $signal=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($signal,TRUE);
        // update attached trigger
        $relevantTrigger=$this->updateTrigger($signal);
        // send through transmitter if trigger is active
        if (!empty($relevantTrigger)){
            foreach($relevantTrigger as $entryId=>$trigger){
                $sendOnTriggerSelector=array('Source'=>$this->entryTable,'Group'=>'Transmitter','Content'=>'%'.$entryId.'%');
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($sendOnTriggerSelector,TRUE) as $sendOnTriggerEntry){
                    if (boolval($trigger['Content']['isActive'])){
                        $this->sendTrigger($sendOnTriggerEntry,$trigger);
                    }
                }
            }
        }
        return $signal;
    }
    
    public function removeSignalsWithoutSource(string $callingClass,string $callingFunction)
    {
        $signalSelector=$this->getSignalSelector($callingClass,$callingFunction);
        $signalSourceSelector=array();
        $signalSourceSelector['Source']=$this->oc[$callingClass]->getEntryTable();
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($signalSelector,TRUE) as $signal){
            $signalSourceSelector['Name']=$signal['Name'];
            if ($this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($signalSourceSelector,TRUE)){continue;}
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($signal,TRUE);
        }
    }
    
    public function isActiveTrigger(string $EntryId,bool $isSystemCall=TRUE):bool
    {
        $selector=array('Source'=>$this->entryTable,'Group'=>'trigger','EntryId'=>$EntryId);
        $trigger=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,$isSystemCall);
        return (!empty($trigger['Content']['isActive']));
    }
    
    public function resetTrigger(string $EntryId,bool $isSystemCall=FALSE):array
    {
        $trigger=array('Source'=>$this->entryTable,'Group'=>'trigger','EntryId'=>$EntryId);
        $trigger=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($trigger,$isSystemCall);
        if (!empty($trigger['Content']['isActive'])){
            $trigger['Content']['isActive']=FALSE;
            $trigger=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($trigger,$isSystemCall);
        }
        return $trigger;
    }

    private function updateTrigger(array $signal):array
    {
        $relevantTrigger=array();
        $triggerSelector=array('Source'=>$this->entryTable,'Group'=>'trigger','Content'=>'%'.$signal['EntryId'].'%');
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($triggerSelector,TRUE,'Read','Name') as $trigger){
            $trigger['Read']=$signal['Read'];
            $trigger['Write']=$signal['Write'];
            $trigger['Content']['isActive']=$this->slopDetector($trigger,$signal);
            if (!isset($trigger['Content']['trigger'])){$trigger['Content']['trigger']=array();}
            $trigger['Content']['trigger']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->add2history($trigger['Content']['trigger'],array('timeStamp'=>time(),'value'=>intval($trigger['Content']['isActive'])),20);
            $relevantTrigger[$trigger['EntryId']]=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($trigger,TRUE);
        }
        return $relevantTrigger;
    }
    
    private function slopDetector(array $trigger,array $signal):bool
    {
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
    
    public function getTriggerWidget(string $callingClass,string $callingFunction):string
    {
        $triggerSelector=array('Source'=>$this->entryTable,'Group'=>'trigger','Folder'=>$callingClass.'::'.$callingFunction);
        $trigger=array('Type'=>$this->entryTable.' trigger','Read'=>'ADMIN_R','Write'=>'ADMIN_R');
        $trigger=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($trigger);
        $trigger=array_merge($trigger,$triggerSelector);
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
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($triggerSelector,FALSE,'Read','Name') as $trigger){
            $matrix[$trigger['EntryId']]=$this->getTriggerRow($trigger);
        }
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Trigger settings','hideKeys'=>TRUE,'keep-element-content'=>TRUE));
        return $html;
    }
    
    public function getMessageWidget(string $callingClass,string $callingFunction):string
    {
        $html='';
        $availableTransmitter=$this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Transmitter');
        if (!isset($settings['Transmitter'])){$settings['Transmitter']=key($availableTransmitter);}
        $availableRecipients=$this->oc['SourcePot\Datapool\Foundation\User']->getUserOptions();
        if (!isset($settings['Recepient'])){$settings['Recepient']=key($availableRecipients);}
        $triggerOptions=$this->getTriggerOptions();
        if (!isset($settings['Trigger'])){$settings['Trigger']=key($triggerOptions);}
        //
        $contentStructure=array('Transmitter'=>array('method'=>'select','excontainer'=>TRUE,'value'=>$settings['Transmitter'],'options'=>$availableTransmitter),
                                'Recepient'=>array('method'=>'select','excontainer'=>TRUE,'value'=>$settings['Recepient'],'options'=>$availableRecipients),
                                'Trigger'=>array('method'=>'select','excontainer'=>TRUE,'value'=>$settings['Trigger'],'options'=>$triggerOptions),
                                );
        $arr=array('callingClass'=>$callingClass,'callingFunction'=>$callingFunction);
        $arr['selector']=array('Source'=>$this->entryTable,'Group'=>'Transmitter','Folder'=>$_SESSION['currentUser']['EntryId'],'Name'=>'Message on trigger');
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Send message on trigger (selected trigger will be reseted)';
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function sendTrigger($sendOnTriggerEntry,array $trigger)
    {
        $name=$trigger['Name'].' was triggered';
        $entry2send=$sendOnTriggerEntry;
        $entry2send['Name']='';
        $entry2send['Content']=array('Subject'=>$name,'Active if'=>'Active if "'.$trigger['Content']['Active if'].'"','Threshold'=>'Threshold "'.$trigger['Content']['Threshold'].'"');
        $success=$this->oc[$sendOnTriggerEntry['Content']['Transmitter']]->send($sendOnTriggerEntry['Content']['Recepient'],$entry2send);
        $this->resetTrigger($trigger['EntryId'],TRUE);
        return $success;
    }

    private function getTriggerRow(array $trigger=array()):array
    {
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
            $signalPlot='';
            $isActive='';
            $reset='';
            $row['Cmd']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'+','keep-element-content'=>TRUE,'key'=>array('New',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
        } else {
            $signalPlot=$this->getSignalPlot(array('EntryId'=>$trigger['Content']['Signal']));
            $isActive=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(!empty($trigger['Content']['isActive']));
            $reset=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Reset','keep-element-content'=>TRUE,'key'=>array('Reset',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
            $row['Cmd']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'key'=>array('Save',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
            $row['Cmd'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'&coprod;','keep-element-content'=>TRUE,'hasCover'=>TRUE,'key'=>array('Delete',$trigger['EntryId']),'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE));
            $row['Cmd']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','keep-element-content'=>TRUE,'element-content'=>$row['Cmd'],'style'=>array('min-width'=>'60px')));
        }
        if (isset($trigger['Content']['signal'])){
            ksort($trigger['Content']['signal']);
            foreach($trigger['Content']['signal'] as $signalIndex=>$signal){
                if ($signalIndex>5){break;}
                $elementArr=array('tag'=>'p','element-content'=>intval($signal['value']));
                $row[$signalIndex]=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elementArr);
            }
        }
        $row['signalPlot']=$signalPlot;
        $row['isActive']=$isActive;
        $row['Reset']=$reset;
        return $row;
    }
    
    public function getSignalPlot(array $selector=array()):string
    {
        $html='';
        $selector['Source']='signals';
        $selector['Group']='signal';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','Date') as $entry){
            $plotDef=array('caption'=>date('Y-m-d').'_'.$entry['Name'],'plotProp'=>array('height'=>150));
            $trace=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->getTraceTemplate();
            $trace['Name']=$entry['Folder'].'::'.$entry['Name'];
            $trace['selector']=$selector;
            $trace['isSystemCall']=TRUE;
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->xyTraces2plot($plotDef,$trace);
        }
        return $html;
    }
    
    public function getSignalOptions(array $selector=array()):array
    {
        $selector['Group']='signal';
        return $this->getOptions($selector,TRUE);
    }
    
    public function getTriggerOptions(array $selector=array()):array
    {
        $selector['Group']='trigger';
        return $this->getOptions($selector,TRUE);
    }

    public function getOptions(array $selector=array(),bool $isSystemCall=FALSE):array
    {
        $options=array();
        $selector['Source']=$this->getEntryTable();
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$isSystemCall,'Read','Name') as $entry){
            $classStartPos=strrpos($entry['Folder'],'\\');
            $classStartPos=($classStartPos===FALSE)?0:$classStartPos+1;
            $classEndPos=strpos($entry['Folder'],'::');
            $options[$entry['EntryId']]=substr($entry['Folder'],$classStartPos,$classEndPos-$classStartPos).': '.$entry['Name'];
        }
        asort($options);
        return $options;
    }

}
?>