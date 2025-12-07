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
    
    private const APP_ACCESS='ADMIN_R';
    private const DERIVED_SIGNAL_INDICATOR='Derived';
    
    private $oc;
    private $entryTable='';
    
    public function __construct($oc){
        $this->oc=$oc;
        $this->entryTable=$this->oc['SourcePot\Datapool\Foundation\Signals']->getEntryTable();
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return ['Category'=>'Admin','Emoji'=>'&#10548;','Label'=>'Trigger','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            $this->oc['SourcePot\Datapool\Foundation\Explorer']->appProcessing('SourcePot\Datapool\Foundation\Signals');
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState('SourcePot\Datapool\Foundation\Signals',['Group'=>FALSE,'Folder'=>FALSE]);
            if ($selector['Group']==='Transmitter'){
                $visibility=['EntryId'=>FALSE,'Folder'=>FALSE];
            }else if (strpos(strval($selector['Folder']),self::DERIVED_SIGNAL_INDICATOR)!==FALSE){
                $visibility=['EntryId'=>FALSE];
            } else {
                $visibility=[];
            }
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer('SourcePot\Datapool\Foundation\Signals',$visibility);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState('SourcePot\Datapool\Foundation\Signals',['Group'=>FALSE,'Folder'=>FALSE]);
            $html='';
            if ($selector['Group']==='signal'){
                if (empty($selector['Folder'])){
                    $html.=$this->signalsOverview($selector);
                } else if (strpos(strval($selector['Folder']),self::DERIVED_SIGNAL_INDICATOR)!==FALSE){
                    $html.=$this->signalsDerived($selector);
                } else {
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Signals']->selector2plot($selector,['height'=>120]);
                }
            } else if ($selector['Group']==='trigger'){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Trigger widget','generic',[],['method'=>'triggerWidgetWrapper','classWithNamespace'=>__CLASS__],[]);
            } else if ($selector['Group']==='Transmitter'){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Message widget','generic',[],['method'=>'messageWidgetWrapper','classWithNamespace'=>__CLASS__],[]);
            } else {
                $metaOverwrite=['yMin'=>0,'xMin'=>time()-1800,'caption'=>'Performance'];
                $html.=$this->oc['SourcePot\Datapool\Foundation\Signals']->selector2plot(['Source'=>'signals','Group'=>'signal','Folder'=>'SourcePot\Datapool\Root::run'],$metaOverwrite);
                $metaOverwrite=['yMin'=>0,'caption'=>'Logins'];
                $html.=$this->oc['SourcePot\Datapool\Foundation\Signals']->selector2plot(['Source'=>'signals','Group'=>'signal','Folder'=>'SourcePot\Datapool\Components\Login::run'],$metaOverwrite);
            }
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

    private function signalsOverview(array $selector):string
    {
        $html='';
        // non-derived signals
        $folders=[];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Folder',$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$selectExprArr=[],$removeGuideEntries=TRUE,TRUE) as $signal){
            $signal['Name']=$signal['EntryId']=FALSE;
            $folders[$signal['Folder']]=$signal;
        }
        $matrix=[];
        foreach($folders as $folder=>$signal){
            $signalCountSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($signal);
            $signalCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($signalCountSelector);
            $btnArr=['cmd'=>'select','selector'=>$signal];
            $matrix[$folder]=['Signals'=>$signalCount,'Go to...'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr)];
        }
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Non-derived signals','hideKeys'=>FALSE,'hideHeader'=>FALSE]);
        // derived signals
        $signalsDerivedSelector=$selector;
        $signalsDerivedSelector['Folder']=self::DERIVED_SIGNAL_INDICATOR.'%';
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Derived signals manager','generic',$signalsDerivedSelector,['method'=>'signalsDerivedManager','classWithNamespace'=>__CLASS__],[]);
        return $html;
    }

    public function signalsDerivedManager(array $arr):array
    {
        // command processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['new'])){
            $guideEntry=$arr['selector'];
            $guideEntry['Folder']=trim($arr['selector']['Folder'],'% ').' '.$formData['val']['Folder'];
            $guideEntry['Owner']=$_SESSION['currentUser']['EntryId'];
            $this->oc['SourcePot\Datapool\Foundation\Explorer']->getGuideEntry($guideEntry);
        }
        // derived signals table
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($arr['selector'],FALSE,'Read','Folder',$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$selectExprArr=[],$removeGuideEntries=FALSE,TRUE) as $derivedSignal){
            $folder=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','keep-element-content'=>TRUE,'value'=>html_entity_decode($derivedSignal['Folder']),'excontainer'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['Name']]);
            $derivedSignal['Name']=$derivedSignal['EntryId']=FALSE;
            $selectBtn=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn(['cmd'=>'select','selector'=>$derivedSignal]);
            $deleteBtn=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn(['selector'=>$derivedSignal,'cmd'=>'delete']);
            $matrix[$derivedSignal['Folder']]=['Folder'=>$folder,''=>$selectBtn.$deleteBtn];
        }
        // new entry template
        $newName=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','keep-element-content'=>TRUE,'value'=>'','placeholder'=>'Derived signal name','excontainer'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['Folder']]);
        $newBtn=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'+','keep-element-content'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['new','Folder']]);
        $matrix['new']=['Folder'=>$newName,''=>$newBtn];
        //
        $arr['html']=$arr['html']??'';
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Derived signals','hideKeys'=>TRUE,'hideHeader'=>FALSE]);
        return $arr;
    }

    private function signalsDerived(array $selector):string
    {
        $name=trim(str_replace(self::DERIVED_SIGNAL_INDICATOR,'',$selector['Folder']));
        $html='';
        $selector['Name']='Params '.$name;
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($selector);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Derived signal params','generic',$selector+['disableAutoRefresh'=>TRUE],['method'=>'derivedSignalParams','classWithNamespace'=>__CLASS__],[]);
        $selector['Name']='Rules '.$name;
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($selector);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Derived signal rules','generic',$selector+['disableAutoRefresh'=>TRUE],['method'=>'derivedSignalRules','classWithNamespace'=>__CLASS__],[]);
        return $html;
    }

    public function derivedSignalParams($arr):array
    {
    // rules
        $timespanOptions=[
            'h'=>'Hour',
            'd'=>'Day',
            'm'=>'Month',
        ];
        $contentStructure=[
            'Timespan'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$timespanOptions,'keep-element-content'=>TRUE,'excontainer'=>TRUE],
        ];
		// rules list
        $arr['selector']=$arr['selector'];
		$arr['contentStructure']=$contentStructure;
		$caption='Params for '.$arr['selector']['Group'].' &rarr; '.$arr['selector']['Folder'];
		$row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$caption]);
		return $arr;
	}
    
    public function derivedSignalRules($arr):array
    {
		// rules
        $signalOptions=$this->oc['SourcePot\Datapool\Foundation\Signals']->getOptions(['Group'=>'signal']);
        $processingOptions=[
            'avarage'=>'Avarage',
            'min'=>'Min',
            'minExclZero'=>'Min exclude zero',
            'Max'=>'Max',
        ];
        $contentStructure=[
            'Signal'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$signalOptions,'keep-element-content'=>TRUE,'excontainer'=>TRUE],
            'Processing'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$processingOptions,'keep-element-content'=>TRUE,'excontainer'=>TRUE],
        ];
		// rules list
        $arr['selector']=$arr['selector'];
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Rules for '.$arr['selector']['Group'].' &rarr; '.$arr['selector']['Folder'];
		$arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $arr;
	}

    public function messageWidgetWrapper($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Signals']->getMessageWidget(__CLASS__,__FUNCTION__);
        return $arr;
    }

}
?>