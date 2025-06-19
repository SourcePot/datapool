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

    private const MAX_SIGNAL_DEPTH=500;
    
    private $entryTable='';
    private $entryTemplate=[
        'Expires'=>['type'=>'DATETIME','value'=>\SourcePot\Datapool\Root::NULL_DATE,'Description'=>'If the current date is later than the Expires-date the entry will be deleted. On insert-entry the init-value is used only if the Owner is not anonymous, set to 10mins otherwise.'],
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'],
        'Owner'=>['type'=>'VARCHAR(100)','value'=>'SYSTEM','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.']
        ];
    
    private const ACTIVE_IF=['stable'=>'&#9596;&#9598;&#9596;&#9598;&#9596;&#9598;&#9596;&#9598; (stable range)',
        'above'=>'&#9601;&#9601; &#10514; &#9620;&#9620; (trigger above th.)',    
        'up'=>'&#9601;&#9601;&#9601;&#9585;&#9620; (rel. step up)',
        'max'=>'&#9601;&#9601;&#9585;&#9586;&#9601; (peak)',
        'min'=>'&#9620;&#9620;&#9586;&#9585;&#9620; (dip)',
        'down'=>'&#9620;&#9620;&#9620;&#9586;&#9601; (rel. step down)',
        'below'=>'&#9620;&#9620; &#10515; &#9601;&#9601; (trigger below th.)',    
        ];

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
    
    public function getEntryTemplate()
    {
        return $this->entryTemplate;
    }

    public function getSignalSelector(string $callingClass,string $callingFunction,string|bool $name=FALSE):array
    {
        $signalSelector=['Source'=>$this->entryTable,'Group'=>'signal','Folder'=>$callingClass.'::'.$callingFunction,'Name'=>$name];
        if ($name===FALSE){
            unset($signalSelector['Name']);
            return $signalSelector;
        }
        // add EntryId only, if entry Name is complete
        if (strpos($name,'%')===FALSE){
            $signalSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($signalSelector,['Source','Group','Folder','Name'],'0','',TRUE);
        }
        return $signalSelector;
    }
    
    public function updateSignal(string $callingClass,string $callingFunction,string $name,$value,$dataType='int',array $params=[]):array
    {
        $newContent=['value'=>$value,'dataType'=>$dataType,'timeStamp'=>time()];
        $params=array_merge(['maxSignalDepth'=>self::MAX_SIGNAL_DEPTH],$params);
        // create entry template or get existing entry
        $signalSelector=$this->getSignalSelector($callingClass,$callingFunction,$name);
        $signal=['Type'=>$this->entryTable.' '.$dataType,'Content'=>['signal'=>[]]];
        $signal=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($signal,'ALL_CONTENTADMIN_R','ALL_CONTENTADMIN_R');
        $signal=array_merge($signal,$signalSelector);
        $signal=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($signal,TRUE);
        // update signal
        $signal['Content']['signal']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->add2history($signal['Content']['signal'],$newContent,$params['maxSignalDepth']);
        $signal['Params']['signal']=$params;
        $signal['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
        $signal['Owner']='SYSTEM';
        $signal['Expires']=date('Y-m-d H:i:s',34560000+time()); // a signal which is not updated within 400 days will be deleted
        $signal=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($signal,TRUE);
        // update attached trigger
        $relevantTrigger=$this->updateTrigger($signal);
        // send through transmitter if trigger is active
        if (!empty($relevantTrigger)){
            foreach($relevantTrigger as $entryId=>$trigger){
                $sendOnTriggerSelector=['Source'=>$this->entryTable,'Group'=>'Transmitter','Content'=>'%'.$entryId.'%'];
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
        $signalSourceSelector=[];
        $signalSourceSelector['Source']=$this->oc[$callingClass]->getEntryTable();
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($signalSelector,TRUE) as $signal){
            $signalSourceSelector['Name']=$signal['Name'];
            if ($this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($signalSourceSelector,TRUE)){continue;}
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($signal,TRUE);
        }
    }
    
    public function isActiveTrigger(string $EntryId,bool $isSystemCall=TRUE):bool
    {
        $selector=['Source'=>$this->entryTable,'Group'=>'trigger','EntryId'=>$EntryId];
        $trigger=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,$isSystemCall);
        return (!empty($trigger['Content']['isActive']));
    }
    
    public function resetTrigger(string $EntryId,bool $isSystemCall=FALSE):array
    {
        $trigger=['Source'=>$this->entryTable,'Group'=>'trigger','EntryId'=>$EntryId];
        $trigger=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($trigger,$isSystemCall);
        if (!empty($trigger['Content']['isActive'])){
            $trigger['Content']['isActive']=FALSE;
            $trigger=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($trigger,$isSystemCall);
        }
        return $trigger;
    }

    private function updateTrigger(array $signal):array
    {
        $relevantTrigger=[];
        $triggerSelector=['Source'=>$this->entryTable,'Group'=>'trigger','Content'=>'%'.$signal['EntryId'].'%'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($triggerSelector,TRUE,'Read','Name') as $trigger){
            $trigger['Read']=$signal['Read'];
            $trigger['Write']=$signal['Write'];
            $trigger['Content']['isActive']=$this->slopDetector($trigger,$signal);
            if (!isset($trigger['Content']['trigger'])){$trigger['Content']['trigger']=[];}
            $trigger['Content']['trigger']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->add2history($trigger['Content']['trigger'],['timeStamp'=>time(),'value'=>intval($trigger['Content']['isActive'])],20);
            $relevantTrigger[$trigger['EntryId']]=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($trigger,TRUE);
        }
        return $relevantTrigger;
    }
    
    private function slopDetector(array $trigger,array $signal):bool
    {
        $arr=[];
        foreach($signal['Content']['signal'] as $index=>$signalArr){
            if (strcmp($signalArr['dataType'],'int')===0 || strcmp($signalArr['dataType'],'bool')===0){
                $arr['values'][$index]=intval($signalArr['value']);
            } else if (strcmp($signalArr['dataType'],'float')===0){
                $arr['values'][$index]=floatval($signalArr['value']);    
            }
        }
        if (!isset($arr['values'][0]) || !isset($arr['values'][1])){
            $condtionMet=FALSE;    
        } else {
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
        }    
        return ($condtionMet)?$condtionMet:((isset($trigger['Content']['isActive']))?$trigger['Content']['isActive']:FALSE);
    }

    public function getTriggerWidget(string $callingClass,string $callingFunction):string
    {
        $triggerSelector=['Source'=>$this->entryTable,'Group'=>'trigger','Folder'=>$callingClass.'::'.$callingFunction];
        $trigger=['Type'=>$this->entryTable.' trigger','Read'=>'ADMIN_R','Write'=>'ADMIN_R'];
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
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries(['Source'=>$trigger['Source'],'EntryId'=>$trigger['EntryId']]);
        }
        // get trigger rows
        $matrix=['New'=>$this->getTriggerRow()];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($triggerSelector,FALSE,'Read','Name') as $trigger){
            $matrix[$trigger['EntryId']]=$this->getTriggerRow($trigger);
        }
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'caption'=>'Trigger settings','hideKeys'=>TRUE,'keep-element-content'=>TRUE]);
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
        $contentStructure=[
            'Transmitter'=>['method'=>'select','excontainer'=>TRUE,'value'=>$settings['Transmitter'],'options'=>$availableTransmitter],
            'Recepient'=>['method'=>'select','excontainer'=>TRUE,'value'=>$settings['Recepient'],'options'=>$availableRecipients],
            'Trigger'=>['method'=>'select','excontainer'=>TRUE,'value'=>$settings['Trigger'],'options'=>$triggerOptions],
            ];
        $arr=['callingClass'=>$callingClass,'callingFunction'=>$callingFunction];
        $arr['selector']=['Source'=>$this->entryTable,'Group'=>'Transmitter','Folder'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),'Name'=>'Message on trigger'];
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],['Source','Group','Folder','Name'],'0','',FALSE);
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
        $entry2send['Content']=['Subject'=>$name,'Active if'=>'Active if "'.$trigger['Content']['Active if'].'"','Threshold'=>'Threshold "'.$trigger['Content']['Threshold'].'"'];
        $success=$this->oc[$sendOnTriggerEntry['Content']['Transmitter']]->send($sendOnTriggerEntry['Content']['Recepient'],$entry2send);
        $this->resetTrigger($trigger['EntryId'],TRUE);
        return $success;
    }

    private function getTriggerRow(array $trigger=[]):array
    {
        $callingFunction='getTriggerWidget';
        $arr=['Signal'=>$this->getSignalOptions()];
        $arr['Active if']=self::ACTIVE_IF;
        $row=[];
        $isNewRow=empty($trigger['EntryId']);
        if (!isset($trigger['EntryId'])){$trigger['EntryId']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getEntryId();}
        if (isset($trigger['Name'])){$value=$trigger['Name'];} else {$value='My new trigger';}
        $row['Name']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','value'=>$value,'key'=>array($trigger['EntryId'],'Name'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE]);
        if (isset($trigger['Content']['Signal'])){$selected=$trigger['Content']['Signal'];} else {$selected='';}
        $row['Signal']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['options'=>$arr['Signal'],'selected'=>$selected,'keep-element-content'=>TRUE,'key'=>array($trigger['EntryId'],'Content','Signal'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE]);
        if (isset($trigger['Content']['Threshold'])){$value=$trigger['Content']['Threshold'];} else {$value='1';}
        $row['Threshold']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','value'=>$value,'key'=>array($trigger['EntryId'],'Content','Threshold'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE]);
        if (isset($trigger['Content']['Active if'])){$selected=$trigger['Content']['Active if'];} else {$selected='up';}
        $row['Active if']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['options'=>$arr['Active if'],'selected'=>$selected,'keep-element-content'=>TRUE,'key'=>array($trigger['EntryId'],'Content','Active if'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE]);
        if ($isNewRow){
            $signalPlot='';
            $isActive='';
            $reset='';
            $row['Cmd']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'+','keep-element-content'=>TRUE,'key'=>['New',$trigger['EntryId']],'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE]);
        } else {
            $signalPlot=$this->getSignalDisplay(['EntryId'=>$trigger['Content']['Signal']],['float'=>'right']);
            $isActive=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(!empty($trigger['Content']['isActive']));
            $reset=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'Reset','keep-element-content'=>TRUE,'key'=>['Reset',$trigger['EntryId']],'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE]);
            $row['Cmd']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'key'=>['Save',$trigger['EntryId']],'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE]);
            $row['Cmd'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'&coprod;','keep-element-content'=>TRUE,'hasCover'=>TRUE,'key'=>['Delete',$trigger['EntryId']],'value'=>$trigger['EntryId'],'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>FALSE]);
            $row['Cmd']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','keep-element-content'=>TRUE,'element-content'=>$row['Cmd'],'style'=>['min-width'=>'60px']]);
        }
        if (isset($trigger['Content']['signal'])){
            ksort($trigger['Content']['signal']);
            foreach($trigger['Content']['signal'] as $signalIndex=>$signal){
                if ($signalIndex>5){break;}
                $elementArr=['tag'=>'p','element-content'=>intval($signal['value'])];
                $row[$signalIndex]=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elementArr);
            }
        }
        $row['signalPlot']=$signalPlot;
        $row['isActive']=$isActive;
        $row['Reset']=$reset;
        return $row;
    }

    public function signalDisplayWrapper(array $arr):array{
        $arr['html']=$this->getSignalDisplay($arr['selector'],[]);
        return $arr;
    }

    public function getSignalDisplay(array $selector=[],array $style=[]):string
    {
        $matrices=[];
        $selector['Source']=$this->getEntryTable();
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','Date') as $entry){
            $signalParams=['min'=>0,'max'=>FALSE];
            $folderComps=explode('\\',$entry['Folder']);
            $folder=array_pop($folderComps);
            $name=$entry['Name'];
            foreach($entry['Content']['signal'] as $index=>$signal){
                // get signal parameters
                if ($signal['dataType']==='int'){
                    $value=round(floatval($signal['value']));
                    if ($index===0){$signalParams['currentValue']=$value;}
                    if ($signalParams['min']===FALSE){$signalParams['min']=$value;}
                    if ($signalParams['max']===FALSE){$signalParams['max']=$value;}
                    if ($signalParams['min']>$value){$signalParams['min']=$value;}
                    if ($signalParams['max']<$value){$signalParams['max']=$value;}
                } else if ($signal['dataType']==='float'){
                    $value=floatval($signal['value']);
                    if ($index===0){$signalParams['currentValue']=$value;}
                    if ($signalParams['min']===FALSE){$signalParams['min']=$value;}
                    if ($signalParams['max']===FALSE){$signalParams['max']=$value;}
                    if ($signalParams['min']>$value){$signalParams['min']=$value;}
                    if ($signalParams['max']<$value){$signalParams['max']=$value;}
                } else if ($signal['dataType']==='bool'){
                    $value=boolval(intval($signal['value']));
                    if ($index===0){$signalParams['currentValue']=$value;}
                    $signalParams['min']=FALSE;
                    $signalParams['max']=TRUE;
                } else if ($signal['dataType']==='string'){
                    $value=strval($signal['value']);
                    if ($index===0){$signalParams['currentValue']=$value;}
                    if (!is_array($signalParams['min'])){
                        $signalParams['min']=array($value=>1);
                    } else if (isset($signalParams['min'][$value])){
                        $signalParams['min'][$value]++;
                    } else {
                        $signalParams['min'][$value]=1;
                    }
                } else {
                    $value=$signal['value'];
                    if ($index===0){$signalParams['currentValue']=$value;}
                    $signalParams['min']=$signalParams['max']='';
                }
            }
            // signal parameters -> html tag
            if (!isset($signalParams['currentValue'])){
                $signalValue=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>'empty','keep-element-content'=>TRUE]);
            } else if (is_bool($signalParams['currentValue'])){
                $element=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($signalParams['currentValue'],[],FALSE);
                $signalValue=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
            } else if (is_numeric($signalParams['currentValue'])){
                $title='value='.$signalParams['currentValue']."\n";
                $title.='min='.$signalParams['min']."\n";
                $title.='max='.$signalParams['max'];
                if ($signalParams['min']==0 && $signalParams['max']==0){$signalParams['max']=1;}
                $signalValue=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'meter','min'=>$signalParams['min'],'max'=>$signalParams['max'],'value'=>$signalParams['currentValue'],'title'=>$title,'style'=>['width'=>'100px'],'element-content'=>' ']);
                $signalValue.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>$signalParams['min'],'style'=>['float'=>'left','clear'=>'left']]);
                $signalValue.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>$signalParams['max'],'style'=>['float'=>'right','clear'=>'right']]);
            } else {
                $subMatrix=['value'=>[],'count'=>[]];
                foreach($signalParams['min'] as $value=>$valueCount){
                    $valueString=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>$value,'keep-element-content'=>TRUE,'class'=>(($value==$signalParams['currentValue'])?'status-on':'status-off')]);
                    $subMatrix['value'][$value]=$valueString;
                    $subMatrix['count'][$value]=$valueCount;
                }
                ksort($subMatrix['value']);
                ksort($subMatrix['count']);
                $signalValue=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$subMatrix,'caption'=>'','hideKeys'=>FALSE,'hideHeader'=>TRUE,'keep-element-content'=>TRUE,'class'=>'matrix','style'=>['width'=>'100px']]);
            }
            $matrices[$folder][$name]=['Value'=>$signalValue];
        }
        $html='';
        if (count($matrices)===1){
            $matrix=current($matrices);
            ksort($matrix);
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'caption'=>$folder,'hideKeys'=>FALSE,'hideHeader'=>TRUE,'keep-element-content'=>TRUE,'style'=>$style]);
        } else{
            ksort($matrices);
            foreach($matrices as $folder=>$matrix){
                ksort($matrix);
                $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'caption'=>$folder,'hideKeys'=>FALSE,'hideHeader'=>TRUE,'keep-element-content'=>TRUE,'style'=>$style]);
            }
        }
        return $html;
    }

    public function selector2plot(array $selector=[],array $params=[]):string|array
    {
        if (empty($selector['id'])){
            // draw plot pane request
            return $this->selector2plotPlane(__CLASS__,__FUNCTION__,$selector,$params);
        } else {
            // return plot data request
            return $this->plotId2data($selector['id']);
        }
    }

    public function signal2plot(string|array $callingClass,string $callingFunction='',string $name=''):string|array
    {
        if (empty($callingClass['function'])){
            // draw plot pane request
            $selector=$this->getSignalSelector($callingClass,$callingFunction,$name);
            return $this->selector2plotPlane(__CLASS__,__FUNCTION__,$selector);
        } else {
            // return plot data request
            return $this->plotId2data($callingClass['id']);
        }
    }

    private function selector2plotPlane(string $callingClass,string $callingFunction,array $selector,array $params=[]):string
    {
        $html='';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','Name') as $entry){
            $plotDataMeta=array_merge(['Source'=>$entry['Source'],'EntryId'=>$entry['EntryId'],'id'=>$entry['EntryId'],'callingClass'=>$callingClass,'callingFunction'=>$callingFunction],($entry['Params']['signal']??[]),$params);
            $_SESSION['plots'][$entry['EntryId']]=$plotDataMeta;
            $elArr=['tag'=>'div','class'=>'plot','keep-element-content'=>TRUE,'element-content'=>'Plot "'.$entry['EntryId'].'" placeholder','id'=>$entry['EntryId']];
            $plotHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $elArr=['tag'=>'a','class'=>'plot','keep-element-content'=>TRUE,'element-content'=>'SVG','id'=>'svg-'.$entry['EntryId']];
            $plotHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $elArr=['tag'=>'div','class'=>'plot-wrapper','style'=>[],'keep-element-content'=>TRUE,'element-content'=>$plotHtml];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
        }
        $elArr=['tag'=>'div','class'=>'plot-wrapper','style'=>[],'keep-element-content'=>TRUE,'element-content'=>$html];
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);    
    }

    private function plotId2data($id):array
    {
        $selector=$_SESSION['plots'][$id]??[];
        $timezone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($selector,TRUE);
        if (isset($selector['min'])){$selector['min']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($selector['min'],$selector['dataType']);}
        if (isset($selector['max'])){$selector['max']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($selector['max'],$selector['dataType']);}
        $plotData=['use'=>'signalPlot','meta'=>$selector,'data'=>[]];
        if ($entry){
            $folderComps=explode('\\',$entry['Folder']);
            $plotData['meta']['title']=array_pop($folderComps);
            $plotData['meta']['title'].=' → '.$entry['Name'];
            $timeIndices=[];
            foreach($entry['Content']['signal'] as $index=>$signal){

                $debugArr['signals'][$entry['EntryId']]=$entry;

                $plotData['data'][$index]['DateTime']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.$signal['timeStamp'],'',$timezone);
                // get time index
                $currentTimeIndex=10*(time()-$signal['timeStamp']);
                while(isset($timeIndices[$currentTimeIndex])){
                    $currentTimeIndex=$currentTimeIndex+1;
                }
                $timeIndices[$currentTimeIndex]=TRUE;
                $currentTimeIndex=$currentTimeIndex/10;
                // add data to plot
                $plotData['data'][$index]['History [sec]']=$currentTimeIndex;
                if ($signal['dataType']==='int'){
                    $plotData['data'][$index]['Value']=round(floatval($signal['value']));
                } else if ($signal['dataType']==='float'){
                    $plotData['data'][$index]['Value']=floatval($signal['value']);
                } else if ($signal['dataType']==='bool'){
                    $plotData['data'][$index]['Value']=boolval($signal['value']);
                } else {
                    $plotData['data'][$index]['Value']=$signal['value'];
                }
            }
        }
        return $plotData;
    }
    
    public function getSignalOptions(array $selector=[]):array
    {
        $selector['Group']='signal';
        return $this->getOptions($selector,TRUE);
    }
    
    public function getTriggerOptions(array $selector=[]):array
    {
        $selector['Group']='trigger';
        return $this->getOptions($selector,TRUE);
    }

    public function getOptions(array $selector=[],bool $isSystemCall=FALSE):array
    {
        $options=[];
        $selector['Source']=$this->getEntryTable();
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$isSystemCall,'Read','Name') as $entry){
            $classStartPos=strrpos($entry['Folder'],'\\');
            $classStartPos=($classStartPos===FALSE)?0:$classStartPos+1;
            $classEndPos=mb_strpos($entry['Folder'],'::');
            $options[$entry['EntryId']]=mb_substr($entry['Folder'],$classStartPos,$classEndPos-$classStartPos).': '.$entry['Name'];
            //$options[$entry['EntryId']]=$entry['Folder'].': '.$entry['Name'];
        }
        asort($options);
        return $options;
    }

}
?>