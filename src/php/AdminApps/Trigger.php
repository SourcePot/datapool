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
            } else {
                $visibility=[];
            }
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer('SourcePot\Datapool\Foundation\Signals',$visibility);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState('SourcePot\Datapool\Foundation\Signals',['Group'=>FALSE,'Folder'=>FALSE]);
            $html='';
            if ($selector['Group']==='signal'){
                if (empty($selector['Folder'])){
                    $html.=$this->signalWidget($selector);
                } else {
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Signals']->selector2plot($selector,['height'=>120]);
                }
            } else if ($selector['Group']==='trigger'){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Trigger widget','generic',[],['method'=>'triggerWidgetWrapper','classWithNamespace'=>__CLASS__],[]);
            } else if ($selector['Group']==='Transmitter'){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Message widget','generic',[],['method'=>'messageWidgetWrapper','classWithNamespace'=>__CLASS__],[]);
            } else {
                $metaOverwrite=['yMin'=>0,'xMin'=>time()-3600,'caption'=>'Performance'];
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

    private function signalWidget(array $selector):string
    {
        $html='';
        $lastSignalClass=FALSE;
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Folder',TRUE) as $signal){
            if ($signal['isSkipRow']){continue;}
            $folderComps=explode('::',$signal['Folder']);
            if (!empty($lastSignalClass) && $lastSignalClass!==$folderComps[0]){
                $containserSelector=$selector;
                $containserSelector['Folder']=$lastSignalClass.'%';
                $html.=$this->oc['SourcePot\Datapool\Foundation\Signals']->selector2plot($containserSelector,['caption'=>$lastSignalClass]);
            }
            $lastSignalClass=$folderComps[0];
        }
        return $html;
    }

    public function messageWidgetWrapper($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Signals']->getMessageWidget(__CLASS__,__FUNCTION__);
        return $arr;
    }

}
?>