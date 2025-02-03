<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\GenericApps;

class Multimedia implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );

    public function __construct($oc){
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

    public function job($vars):array
    {
        return $vars;
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    public function unifyEntry($entry):array
    {
        // This function makes class specific corrections before the entry is inserted or updated.
        return $entry;
    }

    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return array('Category'=>'Apps','Emoji'=>'&#10063;','Label'=>'Multimedia','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
        } else {
            $html='';
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            if (empty($selector['Group']) || empty($selector['Folder'])){
                $wrapperSetting=array('style'=>array('padding'=>'10px','clear'=>'both','border'=>'none','width'=>'auto','margin'=>'10px','border'=>'1px dotted #999;'));
                $setting=array('style'=>array('width'=>500,'height'=>400,'background-color'=>'#fff'),'autoShuffle'=>FALSE,'getImageShuffle'=>'multimedia');
                $hash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE);
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry shuffle '.$hash,'getImageShuffle',$selector,$setting,$wrapperSetting);
                $html.=$this->oc['SourcePot\Datapool\Tools\GeoTools']->getDynamicMap();
                $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE));
            } else if (empty($selector['EntryId'])){
                $presentation=$this->oc['SourcePot\Datapool\Foundation\Explorer']->selector2setting($selector,'widget');
                if ($presentation=='entryList'){
                    $S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
                    $settings=array('hideUpload'=>TRUE,'columns'=>array(array('Column'=>'Name','Filter'=>''),array('Column'=>'Params'.$S.'File','Filter'=>'')));
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entries','entryList',$selector,$settings,array());    
                } else if ($presentation=='entryByEntry'){
                    foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Date',TRUE) as $entry){
                        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>'<br/>','keep-element-content'=>TRUE,'function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'multimedia','style'=>array('clear'=>'none','max-width'=>300,'max-height'=>280)));
                    }
                } else {
                    $html.='Selected widget = '.$presentation.' is not implemented';
                }
            } else {
                $presentArr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
                $presentArr['selector']=$selector;
                $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->presentEntry($presentArr);
            }
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
        
}
?>