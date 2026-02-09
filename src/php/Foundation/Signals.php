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
        $this->oc['SourcePot\Datapool\Foundation\Explorer']->getGuideEntry(['Source'=>$this->getEntryTable(),'Group'=>'trigger']);
        $this->oc['SourcePot\Datapool\Foundation\Explorer']->getGuideEntry(['Source'=>$this->getEntryTable(),'Group'=>'Transmitter']);
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
        if (mb_substr($name,-1,1)!=='%'){
            $signalSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($signalSelector,['Source','Group','Folder','Name'],'0','',TRUE);
        }
        return $signalSelector;
    }
    
    public function updateSignal(string $callingClass,string $callingFunction,string $name,$value,$dataType='int',array $params=[],$timeStamp=NULL):array
    {
        $value=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($value,$dataType);
        $newContent=['value'=>$value,'dataType'=>$dataType,'timeStamp'=>$timeStamp,'label'=>$params['label']??'','color'=>$params['color']??''];
        // create entry template or get existing entry
        $signalSelector=$this->getSignalSelector($callingClass,$callingFunction,$name);
        $signal=['Type'=>$this->entryTable.' '.$dataType,'Content'=>['signal'=>[]]];
        $signal=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($signal,'ALL_CONTENTADMIN_R','ALL_CONTENTADMIN_R');
        $signal=array_merge($signal,$signalSelector);
        $signal=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($signal,TRUE);
        // update signal
        $signal['Content']['signal']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->add2history($signal['Content']['signal'],$newContent,$params['maxSignalDepth']??self::MAX_SIGNAL_DEPTH);
        $signal['Params']['signal']=$params;
        $signal['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
        $signal['Owner']='SYSTEM';
        $signal['Expires']=date('Y-m-d H:i:s',34560000+time()); // a signal which is not updated within 400 days will be deleted
        $signal=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($signal,TRUE);
        // update attached trigger
        $relevantTrigger=$this->updateTrigger($signal);
        // send through transmitter if trigger is active
        $trigger2reset=[];
        if (!empty($relevantTrigger)){
            // process trigger
            foreach($relevantTrigger as $entryId=>$trigger){
                $sendOnTriggerSelector=['Source'=>$this->entryTable,'Group'=>'Transmitter','Content'=>'%'.$entryId.'%'];
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($sendOnTriggerSelector,TRUE) as $sendOnTriggerEntry){
                    if (boolval($trigger['Content']['isActive'])){
                        $triggerEntryId=$this->sendTrigger($sendOnTriggerEntry,$trigger);
                        $trigger2reset[$triggerEntryId]=$triggerEntryId;
                    }
                }
            }
            // reset trigger
            foreach($trigger2reset as $triggerEntryId){
                $this->resetTrigger($triggerEntryId,TRUE);
            }
        }
        $this->oc['SourcePot\Datapool\AdminApps\DerivedSignals']->signal2derivedSignal($signal);
        return $signal;
    }

    public function getSignalProperties(string $callingClass,string $callingFunction,string $name,string $timespanDefinedByFormat='',string $timezone=''):array
    {
        $signalSelector=$this->getSignalSelector($callingClass,$callingFunction,$name);
        return $this->getSignalPropertiesById($signalSelector,$timespanDefinedByFormat,$timezone);
    }
    public function getSignalPropertiesById(array $signalSelector,string $timespanDefinedByFormat='',string $timezone=''):array
    {
        $properties=['min'=>FALSE,'minExZero'=>FALSE,'max'=>FALSE,'avg'=>FALSE,'range'=>FALSE,'sum'=>FALSE,'count'=>0,'avgTimestamp'=>0];
        $signal=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($signalSelector,TRUE);
        foreach($signal['Content']['signal'] as $index=>$signalItem){
            if (!$this->isRelevantSignalItem($signalItem,$timespanDefinedByFormat,$timezone)){
                continue;
            }
            $properties['count']++;
            $properties['sum']+=$signalItem['value'];
            if ($properties['min']===FALSE || $properties['min']>$signalItem['value']){
                $properties['min']=$signalItem['value'];
            }
            if (!empty($signalItem['value']) && ($properties['minExZero']===FALSE || $properties['minExZero']>$signalItem['value'])){
                $properties['minExZero']=$signalItem['value'];
            }
            if ($properties['max']===FALSE || $properties['max']<$signalItem['value']){
                $properties['max']=$signalItem['value'];
            }
            $properties['avgTimestamp']+=$signalItem['timeStamp'];
        }
        $properties['avg']=($properties['count']==0)?FALSE:($properties['sum']/$properties['count']);
        $properties['range']=$properties['max']-$properties['min'];
        $properties['avgTimestamp']=round($properties['avgTimestamp']/($properties['count']?:1));
        return $properties;
    }

    private function isRelevantSignalItem($signalItem,$timespanDefinedByFormat,$timezone):bool
    {
        if (empty($timespanDefinedByFormat) || empty($timezone)){
            return TRUE;
        }
        $targetTimeZone=new \DateTimeZone($timezone);
        $currentDateTime=new \DateTime('@'.time());
        $currentDateTime->setTimezone($targetTimeZone);
        $itemDateTime=new \DateTime('@'.$signalItem['timeStamp']);
        $itemDateTime->setTimezone($targetTimeZone);
        return ($currentDateTime->format($timespanDefinedByFormat)==$itemDateTime->format($timespanDefinedByFormat));
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
            $trigger['Content']['trigger']=$trigger['Content']['trigger']??[];
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

    private function sendTrigger($sendOnTriggerEntry,array $trigger):string
    {
        $name=$trigger['Name'].' was triggered';
        $entry2send=$sendOnTriggerEntry;
        $entry2send['Name']='';
        $entry2send['Content']=['Subject'=>$name,'Active if'=>'Active if "'.$trigger['Content']['Active if'].'"','Threshold'=>'Threshold "'.$trigger['Content']['Threshold'].'"'];
        $this->oc[$sendOnTriggerEntry['Content']['Transmitter']]->send($sendOnTriggerEntry['Content']['Recepient'],$entry2send);
        return $trigger['EntryId'];
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
            $signalPlot=$this->selector2plot(['EntryId'=>$trigger['Content']['Signal']],['xMax'=>time(),'style'=>['width'=>340,'height'=>50,'bottom'=>40]]);
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

    public function selector2plot(array $selector=[],array $metaOverwrite=['yMin'=>0,'caption'=>'Plots']):string|array
    {
        $settings=['method'=>'signalsChart','classWithNamespace'=>__CLASS__];
        $settings=array_merge($metaOverwrite,$settings);
        $selector['Source']=$selector['Source']??$this->getEntryTable();
        $hash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE);
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Signal chart a '.$hash,'generic',$selector,$settings,['style'=>['border'=>'none','width'=>'auto']]);
        return $html;
    }

    public function signal2plot(string|array $callingClass,string $callingFunction='',string $name='',$metaOverwrite=['yMin'=>0]):string
    {
        $selector=$this->getSignalSelector($callingClass,$callingFunction,$name);
        return $this->selector2plot($selector,$metaOverwrite);
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
            $options[$entry['EntryId']]=mb_substr($entry['Folder'],$classStartPos).': '.$entry['Name'];
        }
        asort($options);
        return $options;
    }

    /**
     *  HTML Signal Charts
     */

    public function signalsChart($arr,$isSystemCall=TRUE):array
    {
        $elArr=['tag'=>'h1','keep-element-content'=>TRUE,'element-content'=>$arr['settings']['caption']??''];
        if (empty($arr['settings']['caption'])){
            $html='';
        } else {
            $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
        }
        $index=0;
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($arr['selector'],$isSystemCall,'Read',$arr['selector']['orderBy']??FALSE,$arr['selector']['isAsc']??TRUE,$arr['selector']['limit']??FALSE,$arr['selector']['offset']??FALSE) as $signal){
            if (!isset($signal['Content']['signal'])){continue;}
            $signalParms=array_merge($signal['Params']['signal'],$arr['settings']);
            $style=($index===0)?['margin-top'=>0]:[];
            $elArr=['tag'=>'p','class'=>'signal-chart','keep-element-content'=>TRUE,'element-content'=>$signal['Folder'].' &rarr; '.$signal['Name'],'style'=>$style];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $html.=$this->signalPlot($signal,$signalParms);
            $index++;
        }
        $elArr=['tag'=>'div','class'=>'signal-chart','style'=>$arr['selector']['style']??[],'keep-element-content'=>TRUE,'element-content'=>$html];
        $arr['html']=$arr['html']??'';
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
        return $arr;
    }

    public function signalPlot($signal,$metaOverwrite=[]):string
    {
        $metaOverwrite['tickLength']=($metaOverwrite['tickLength']??6)?:6;
        $metaOverwrite=array_merge($metaOverwrite,$signal['Params']['signal']??[]);
        $metaOverwrite=$this->metaArrCleanup($metaOverwrite);
        $plotBaseId=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash([$signal['EntryId']],TRUE);
        $plot=['tag'=>'div','class'=>'signal-plot','style'=>[],'id'=>$plotBaseId.'-plot','keep-element-content'=>TRUE];
        $plot['style']['width']=$metaOverwrite['style']['width']??600;
        $plot['style']['height']=$metaOverwrite['style']['height']??100;
        $plot['style']['left']=$metaOverwrite['style']['left']??60;
        $plot['style']['bottom']=$metaOverwrite['style']['bottom']??50;
        $infoPanel=['tag'=>'div','class'=>'signal-plot-info','style'=>['width'=>200],'id'=>$plotBaseId.'-info','keep-element-content'=>TRUE];
        $plotWrapper=['tag'=>'div','class'=>'signal-plot-wrapper','style'=>['width'=>$plot['style']['width']+$plot['style']['left']+$infoPanel['style']['width']+45,'height'=>$plot['style']['height']+$plot['style']['bottom']],'keep-element-content'=>TRUE];
        // reorganize data & get data properties
        $data=[];
        $meta=['xScaler'=>1,'xOffset'=>0,'yScaler'=>1,'yOffset'=>0,'xMin'=>NULL,'xMax'=>NULL,'yMin'=>NULL,'yMax'=>NULL,'dateFormat'=>'Y-m-d H:i:s'];
        $value=0;
        $item=['timeStamp'=>$item['timeStamp']??time()];
        foreach($signal['Content']['signal'] as $item){
            $value=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($item['value'],'float');
            if (!empty($metaOverwrite['normalizer'])){
                $normalizer=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($metaOverwrite['normalizer']['value'],'float');
                $value=$value/$normalizer;
                $value-=1;
            }
            if (isset($data[$item['timeStamp']]['value'])){
                $value+=$data[$item['timeStamp']]['value'];
            }
            // check if inside pre-set range
            if (isset($metaOverwrite['xMin']) && ($item['timeStamp']??0)<intval($metaOverwrite['xMin'])){continue;}
            if (isset($metaOverwrite['xMax']) && ($item['timeStamp']??0)>intval($metaOverwrite['xMax'])){continue;}
            // update min-, max-values
            if ($meta['xMin']>$item['timeStamp'] || $meta['xMin']===NULL){$meta['xMin']=$item['timeStamp'];}
            if ($meta['xMax']<$item['timeStamp'] || $meta['xMax']===NULL){$meta['xMax']=$item['timeStamp'];}
            if ($meta['yMin']>$value || $meta['yMin']===NULL){$meta['yMin']=$value;}
            if ($meta['yMax']<$value || $meta['yMax']===NULL){$meta['yMax']=$value;}
            // add datapoint
            $data[$item['timeStamp']]=['timeStamp'=>$item['timeStamp'],'value'=>$value,'label'=>$item['label']??'-','color'=>$item['color']??''];
        }
        $meta=array_merge($meta,$metaOverwrite);
        // sorting and scaling data
        ksort($data);
        if ($meta['xMax']==$meta['xMin']){
            $meta['xMax']=$item['timeStamp']+1;
            $meta['xMin']=$meta['xMin']-2;
        }
        if ($meta['yMax']==$meta['yMin']){
            $meta['yMax']=1.05*($value?:1);
            $meta['yMin']=0.95*$value;
        }
        $meta['xScaler']=$plot['style']['width']/($meta['xMax']-$meta['xMin']);
        $meta['yScaler']=$plot['style']['height']/($meta['yMax']-$meta['yMin']);
        $meta['xOffset']=$meta['xMin']*$meta['xScaler'];
        $meta['yOffset']=$meta['yMin']*$meta['yScaler'];
        // generate bars html
        $barBase=0;
        $html='';
        $html.=$this->getY0axis($meta,$plot);
        foreach($data as $timeStamp=>$item){
            $barHeight=$this->value2pixel($item['value']+$meta['yMin']-$barBase,$meta,TRUE);
            $barBottom=$this->value2pixel($barBase,$meta,TRUE);
            $bar=['tag'=>'div','class'=>'signal-bar','style'=>['background-color'=>($item['color']?:'#10a').'4','border-top'=>'1px solid '.($item['color']?:'#10a')],'keep-element-content'=>TRUE,'element-content'=>''];
            if ($barHeight<0){
                $barBottom=$barBottom+$barHeight;
                $barHeight=-$barHeight;
                $bar['style']['background-color']='#f004';
                $bar['style']['border-bottom']='1px solid #f00';
            }
            $bar['id']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash([$signal['EntryId'],$timeStamp],TRUE);
            $bar['style']['bottom']=$barBottom;
            $bar['style']['height']=$barHeight;
            $bar['style']['width']=6;
            $bar['style']['left']=round($timeStamp*$meta['xScaler']-$meta['xOffset']-intdiv($bar['style']['width'],2));
            $bar['data-value']=$item['value'];
            $bar['data-timestamp']=$item['timeStamp'];
            $bar['data-label']=preg_replace('[^A-ZÄÜÖa-zäüö0-9\-\_@ \\//]','',strval($item['label']));
            //
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($bar);
        }
        // cursor lines
        $cursorX=['tag'=>'div','style'=>['height'=>$plot['style']['height']+20,'width'=>0,'left'=>0,'bottom'=>-10],'class'=>'signal-cursor-x','element-content'=>''];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($cursorX);
        $cursorY=['tag'=>'div','style'=>['height'=>0,'width'=>$plot['style']['width']+20,'left'=>-10,'bottom'=>0],'class'=>'signal-cursor-y','element-content'=>''];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($cursorY);
        $plot['element-content']=$html;
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($plot);
        // generate wrapper, ticks, labels
        $plot['countX']=floor($plot['style']['width']/100);
        $tick=['tag'=>'div','style'=>['height'=>$metaOverwrite['tickLength'],'width'=>0,'border'=>'1px solid #000'],'class'=>'signal-tick','element-content'=>''];
        $label=['tag'=>'p','style'=>['bottom'=>0],'class'=>'signal-label','element-content'=>'','keep-element-content'=>TRUE];
        for($tickIndexX=0;$tickIndexX<=$plot['countX'];$tickIndexX++){
            $label['style']['left']=$tick['style']['left']=round($tickIndexX*$plot['style']['width']/$plot['countX']+$plot['style']['left']);
            $timeStamp=round(($label['style']['left']+$meta['xOffset']-$plot['style']['left'])/$meta['xScaler']);
            $dateTimeStr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.$timeStamp,'','',$meta['dateFormat'],\SourcePot\Datapool\Root::getUserTimezone());
            $label['element-content']=str_replace(' ','<br/>',$dateTimeStr);
            //
            $label['style']['left']-=30;
            $tick['style']['bottom']=$plot['style']['bottom']-$metaOverwrite['tickLength'];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($tick);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($label);
        }
        $plot['countY']=floor($plot['style']['height']/25);
        $tick['style']['height']=0;
        $tick['style']['width']=$metaOverwrite['tickLength'];
        for($tickIndexY=0;$tickIndexY<=$plot['countY'];$tickIndexY++){
            $label['style']['bottom']=$tick['style']['bottom']=$tickIndexY*$plot['style']['height']/$plot['countY']+$plot['style']['bottom'];
            $value=($label['style']['bottom']+$meta['yOffset']-$plot['style']['bottom'])/$meta['yScaler'];
            $label['element-content']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($value);
            //
            $label['style']['left']=0;
            $label['style']['bottom']-=5;
            $tick['style']['left']=$plot['style']['left']-$metaOverwrite['tickLength'];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($tick);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($label);
        }
        // create info panel template
        $hEl=['tag'=>'h3','class'=>'info','element-content'=>'Info panel','style'=>['float'=>'left','margin'=>'0 0 0.25rem 0']];
        $infoPanelHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($hEl);
        // Label
        $pEl=['tag'=>'p','class'=>'info','element-content'=>'Label','style'=>['float'=>'left','clear'=>'left']];
        $infoRowHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pEl);
        $pEl=['tag'=>'p','class'=>'info','id'=>$plotBaseId.'-label','element-content'=>'...','style'=>['float'=>'right','clear'=>'right','max-width'=>'130px']];
        $infoRowHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pEl);
        $rowEl=['tag'=>'div','class'=>'info','element-content'=>$infoRowHtml,'style'=>['width'=>'100%','padding'=>'0 0 0.25rem 0'],'keep-element-content'=>TRUE];
        $infoPanelHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($rowEl);
        // Value
        $pEl=['tag'=>'p','class'=>'info','element-content'=>'Value','style'=>['clear'=>'left']];
        $infoRowHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pEl);
        $pEl=['tag'=>'p','class'=>'info','id'=>$plotBaseId.'-value','element-content'=>'...','style'=>['float'=>'right','clear'=>'right','max-width'=>'130px']];
        $infoRowHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pEl);
        $rowEl=['tag'=>'div','class'=>'info','element-content'=>$infoRowHtml,'style'=>['width'=>'100%','padding'=>'0 0 0.25rem 0'],'keep-element-content'=>TRUE];
        $infoPanelHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($rowEl);
        // Date
        $pEl=['tag'=>'p','class'=>'info','element-content'=>'Date','style'=>['clear'=>'left']];
        $infoRowHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pEl);
        $pEl=['tag'=>'p','class'=>'info','id'=>$plotBaseId.'-timestamp','element-content'=>'...','style'=>['float'=>'right','clear'=>'right','max-width'=>'130px']];
        $infoRowHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pEl);
        $rowEl=['tag'=>'div','class'=>'info','element-content'=>$infoRowHtml,'style'=>['width'=>'100%','padding'=>'0 0 0.25rem 0'],'keep-element-content'=>TRUE];
        $infoPanelHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($rowEl);
        // Timezone
        $pEl=['tag'=>'p','class'=>'info','element-content'=>'Timezone','style'=>['clear'=>'left']];
        $infoRowHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pEl);
        $pEl=['tag'=>'p','class'=>'info','id'=>$plotBaseId.'-timezone','element-content'=>\SourcePot\Datapool\Root::getUserTimezone(),'style'=>['float'=>'right','clear'=>'right','max-width'=>'130px']];
        $infoRowHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pEl);
        $rowEl=['tag'=>'div','class'=>'info','element-content'=>$infoRowHtml,'style'=>['width'=>'100%','padding'=>'0 0 0.25rem 0'],'keep-element-content'=>TRUE];
        $infoPanelHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($rowEl);
        // Descritpion
        $pEl=['tag'=>'p','class'=>'info','element-content'=>$signal['Params']['signal']['description']??'Signal description missing','style'=>['font-style'=>'italic','clear'=>'left']];
        $infoPanelHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pEl);
        //
        $infoPanel['element-content']=$infoPanelHtml;
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($infoPanel);
        // add plot wrapper
        $plotWrapper['element-content']=$html;
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($plotWrapper);
        return $html;
    }

    private function value2pixel($value,array $meta,$scaleY=TRUE):int
    {
        if ($scaleY){
            return intval($value*$meta['yScaler']-$meta['yOffset']);
        }
        return intval($value*$meta['yScaler']-$meta['yOffset']);
    }
    
    private function getY0axis(array $meta, array $plot):string
    {
        $axisEl=['tag'=>'div','style'=>['position'=>'absolute','left'=>0,'width'=>$plot['style']['width'],'height'=>0,'border-bottom'=>'1px solid #444'],'element-content'=>''];
        if ($meta['yMin']<0 || $meta['yMax']>0){
            $axisEl['style']['bottom']=$this->value2pixel(0,$meta,TRUE);
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element($axisEl);
        } else {
            return '';
        }
        
    }

    private function metaArrCleanup(array $meta):array
    {
        $typeKeys=['xMax'=>'int','xMin'=>'int','xOffset'=>'int','yMax'=>'float','yMin'=>'float','yOffset'=>'float','yScaler'=>'float'];
        foreach($typeKeys as $key=>$type){
            if (!isset($meta[$key])){continue;}
            $meta[$key]=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($meta[$key],$type);
        }
        return $meta;
    }
}
?>