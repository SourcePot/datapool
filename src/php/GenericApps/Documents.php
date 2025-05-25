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
    
    private $entryTable='';
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            ];

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
            return ['Category'=>'Apps','Emoji'=>'&#9783;','Label'=>'Documents','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__];
        } else {
            $html='';
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            if (empty($selector['EntryId'])){
                $presentation=$this->oc['SourcePot\Datapool\Foundation\Explorer']->selector2setting($selector,'widget');
                if ($presentation=='entryList'){
                    $containerTitle=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE);
                    if (empty($selector['Group'])){
                        $settings=['hideUpload'=>TRUE,'columns'=>[['Column'=>'Group','Filter'=>''],['Column'=>'Folder','Filter'=>''],['Column'=>'Name','Filter'=>'']]];
                    } else if (empty($selector['Folder'])){
                        $settings=['hideUpload'=>TRUE,'columns'=>[['Column'=>'Folder','Filter'=>''],['Column'=>'Name','Filter'=>'']]];
                    } else if (empty($selector['EntryId'])){
                        $settings=['hideUpload'=>TRUE,'columns'=>[['Column'=>'Name','Filter'=>''],['Column'=>'Params'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator().'File','Filter'=>'']]];
                    }
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container($containerTitle,'entryList',$selector,$settings,[]); 
                } else if ($presentation=='entryByEntry'){
                    foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Date',TRUE) as $entry){
                        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>'<br/>','keep-element-content'=>TRUE,'function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'multimedia','style'=>['clear'=>'none','max-width'=>300,'max-height'=>280]]);
                    }
                } else {
                    $html.='Selected widget = '.$presentation.' is not implemented';
                }
            } else {
                $presentArr=['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
                $presentArr['settings']=['presentEntry'=>__CLASS__.'::'.__FUNCTION__];
                $presentArr['selector']=$selector;
                $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->presentEntry($presentArr);
            }
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
        
}
?>