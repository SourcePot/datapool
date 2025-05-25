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

class Multimedia implements \SourcePot\Datapool\Interfaces\App,\SourcePot\Datapool\Interfaces\HomeApp{
    
    
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
            return ['Category'=>'Apps','Emoji'=>'&#10063;','Label'=>'Multimedia','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__];
        } else {
            $html='';
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            if (empty($selector['Group']) || empty($selector['Folder'])){
                $wrapperSetting=array('style'=>array('padding'=>'10px','clear'=>'both','border'=>'none','width'=>'auto','margin'=>'10px','border'=>'1px dotted #999;'));
                $setting=array('style'=>array('width'=>500,'height'=>400,'background-color'=>'#fff'),'autoShuffle'=>FALSE,'getImageShuffle'=>'multimedia');
                $hash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE);
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry shuffle '.$hash,'getImageShuffle',$selector,$setting,$wrapperSetting);
                if ($this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($selector)){
                    $html.=$this->oc['SourcePot\Datapool\Tools\GeoTools']->getDynamicMap();
                } else {
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h2','element-content'=>'No entries yet...']);
                }
                $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE));
            } else if (empty($selector['EntryId'])){
                $presentation=$this->oc['SourcePot\Datapool\Foundation\Explorer']->selector2setting($selector,'widget');
                if ($presentation=='entryList'){
                    $S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
                    $settings=['hideUpload'=>TRUE,'columns'=>[['Column'=>'Name','Filter'=>''],['Column'=>'Params'.$S.'File','Filter'=>'']]];
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entries','entryList',$selector,$settings,[]);    
                } else if ($presentation=='entryByEntry'){
                    foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Date',TRUE) as $entry){
                        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>'<br/>','keep-element-content'=>TRUE,'function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'multimedia','style'=>['clear'=>'none','max-width'=>300,'max-height'=>280]]);
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

    public function getHomeAppWidget(string $name):array
    {
        $element=['element-content'=>''];
        $selector=['Source'=>$this->oc['SourcePot\Datapool\Components\Home']->getEntryTable(),'Group'=>'Home','Folder'=>'Public','Name'=>$name];
        $selector['md']='<div class="center"><img src="./assets/logo.jpg" alt="Logo" style="float:none;width:320px;"/></div>';
        $selector['md'].="\n";
        $selector['md'].="\n";
        $selector['md'].="# What is Datapool?\n\nDatapool is an open-source web application for efficient automated data processing. Processes are configurated graphically as a data flow throught processing blocks.\n";
        $selector['md'].="Following the principle of *Divide-and-Conquer* multiple Datapool instances (e.g. hosted in a cloud) can interact with eachother or legacy software to handle complex problems.\n";
        $selector['md'].="This approach keeps complexity under control, responsability can be shared and the overall processing speed can be adjusted.\n";
        $selector['md'].="Data exchange is done in a transparant human readble form through lists, emails, pdf-documents or SMS improving debugging on system level.\n";
        $selector['md'].="Calendar-based trigger cann be used for time-based flow control. All calendar entries or properties such as the number of data records and their changes, generate signals. Trigger can be derived from these signals. Trigger can initiate data processing as well as the creation of messages, e.g. e-mail or SMS.\n\n";
        $selector['md'].="## The design goal for a single instance of Datapool is maximum configurability, not processing speed.\n\nFor example, a calendar date is saved as an array in all relevant formats from which mapping can choose:\n\n";
        $selector['md'].="<img src=\"".$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($GLOBALS['dirs']['assets'].'dateType_example.png')."\" alt=\"Datapool date type example\" style=\"max-width:400px;\"/>\n\n";
        $selector['md'].="## Configuration is done by selection, not by conversion!\n\n";
        $selector['md'].="# Cooperative approach\n\nDatapool is an open source software project managed on **<a href=\"https://github.com/SourcePot/datapool\" target=\"_blank\">Github SourcePot/datapool</a>**. Datappol provides interfaces for adding processors, data receiver and transmitter.\n";
        $selector['md'].="Datapool content such as dataflows can be easily be exported and imported, i.e. shared within the organization or with others or stored as backup file. A user role infrastructure provides the different levels of access control, i.e. import and export is restricted to the \"Admin\" and \"Content admin\".\n";
        $selector['md'].="# Graphical data flow builder (DataExploerer-class)\n\nA dataflow consists of two types of (canvas) elements: \"connecting elements\" and \"processing blocks\" The connecting elements have no function other then helping to visualize the data flow.\n";
        $selector['md'].="The processing blocks contain all functionallity, i.e. \"providing a database table view\", \"storing settings\" and \"linking a processor\". The settings define the target or targets canvas elements for the result data. There are basic processor, e.g. for data acquisition, mapping, parsing or data distribution. In addition, user-defined processor can be added.\n\n";
        $selector['md'].="<img src=\"".$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($GLOBALS['dirs']['assets'].'Example_data_flow.png')."\" alt=\"Datapool date type example\" style=\"\"/>\n\n";
        $selector['md'].="\n";
        $selector['md'].="\n";
        $selector['md'].="# Attributions\nThis webpage uses map data from *OpenStreetMap*. Please refer to <a href=\"https://www.openstreetmap.org/copyright\" target=\"_blank\" class=\"btn\" style=\"float:none;\">The OpenStreetMap License</a> for the license conditions.\n\nThe original intro video is by *Pressmaster*, www.pexels.com\n";
        $selector['md'].="# Contact\n## Address\n";
        $selector['md'].="## Email\n<img src=\"".$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($GLOBALS['dirs']['assets'].'email.png')."\" style=\"float:none;\">\n";
        $selector['md'].="# Legal\nThis is a private web page. The web page uses cookies for session handling.\n\n";
        $element['element-content']=$this->oc['SourcePot\Datapool\Foundation\Container']->container($selector['Name'],'mdContainer',$selector,[],['style'=>[]]);
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget presents a <b>Markdown document</b>. The content admin and admin will be able to change the content.<br/>The content must be entred for each web page language separately.';
        return $info;
    }

}
?>