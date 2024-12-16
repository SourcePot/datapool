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

class Documents implements \SourcePot\Datapool\Interfaces\App{
    
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
            return array('Category'=>'Apps','Emoji'=>'&#9783;','Label'=>'Documents','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
        } else {
            $html='';
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            if (empty($selector['EntryId'])){
                $S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
                $containerTitle='Documents';
                $containerTitle.=(empty($selector['Group']))?'.':'';
                $containerTitle.=(empty($selector['Folder']))?'.':'';
                $containerTitle.=(empty($selector['EntryId']))?'.':'';
                if (empty($selector['Group'])){
                    $settings=array('hideUpload'=>TRUE,'columns'=>array(array('Column'=>'Group','Filter'=>''),array('Column'=>'Folder','Filter'=>''),array('Column'=>'Name','Filter'=>'')));
                } else if (empty($selector['Folder'])){
                    $settings=array('hideUpload'=>TRUE,'columns'=>array(array('Column'=>'Folder','Filter'=>''),array('Column'=>'Name','Filter'=>'')));
                } else if (empty($selector['EntryId'])){
                    $settings=array('hideUpload'=>TRUE,'columns'=>array(array('Column'=>'Name','Filter'=>''),array('Column'=>'Params'.$S.'File','Filter'=>'')));
                }
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container($containerTitle,'entryList',$selector,$settings,array());
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