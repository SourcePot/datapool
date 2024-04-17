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
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );

    public $definition=array('EntryId'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@Write'=>0));

    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }

    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
        $oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
    }

    public function job($vars){
        return $vars;
    }

    public function getEntryTable(){
        return $this->entryTable;
    }
    
    public function getEntryTemplate(){
        return $this->entryTemplate;
    }

    public function unifyEntry($entry){
        // This function makes class specific corrections before the entry is inserted or updated.
        return $entry;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Apps','Emoji'=>'&#10063;','Label'=>'Multimedia','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
        } else {
            $html='';
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            if (empty($selector['Group'])){
                $wrapperSetting=array('style'=>array('padding'=>'10px','clear'=>'both','border'=>'none','width'=>'auto','margin'=>'10px','border'=>'1px dotted #999;'));
                $setting=array('style'=>array('width'=>500,'height'=>400,'background-color'=>'#fff'),'autoShuffle'=>FALSE,'getImageShuffle'=>'multimedia');
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry shuffle','getImageShuffle',$selector,$setting,$wrapperSetting);
                $html.=$this->oc['SourcePot\Datapool\Tools\GeoTools']->getDynamicMap();
                $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE));
            } else if (empty($selector['Group']) || empty($selector['EntryId'])){
                //$wrapperSetting=array();
                $wrapperSetting=array('html'=>$this->oc['SourcePot\Datapool\Tools\GeoTools']->getDynamicMap());
                $settings=array('orderBy'=>'Name','isAsc'=>FALSE,'limit'=>5,'hideUpload'=>TRUE);
                $settings['columns']=array(array('Column'=>'Name','Filter'=>''));
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Mutlimedia entries','entryList',$selector,$settings,$wrapperSetting);
            } else {
                $presentArr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
                $presentArr['settings']=array('presentEntry'=>__CLASS__.'::'.__FUNCTION__);
                $presentArr['selector']=$selector;
                $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->presentEntry($presentArr);
            }
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
        
}
?>