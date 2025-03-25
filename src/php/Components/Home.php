<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Components;

class Home implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    private $entryTable='';
    
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'],
                            ];
    
    public function __construct($oc)
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

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    public function unifyEntry($entry):array
    {
        $entry['Read']=intval($entry['Content']['Read access']??$this->oc['SourcePot\Datapool\Foundation\Access']->accessString2int($this->entryTemplate['Read']['value']));
        return $entry;
    }

    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return array('Category'=>'Home','Emoji'=>'&#9750;','Label'=>'Home','Read'=>'ALL_R','Class'=>__CLASS__);
        } else {
            $pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
            $html='';
            // Show query widget and HomeApps to ALL_MEMBERS
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->hasRights(FALSE,'ALL_MEMBER_R')){
                $html.=$this->honeAppWidgets();
            } 
            // Show Welcome Page sections to the public, admin and content admin
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->hasRights(FALSE,'PUBLIC_R') || $this->oc['SourcePot\Datapool\Foundation\Access']->hasRights(FALSE,'ALL_CONTENTADMIN_R')){
                // top web page section
                $selector=array('Source'=>$this->entryTable,'Group'=>'Home','Folder'=>'Public','Name'=>'Top paragraph');
                $selector['md']='<div class="center"><img src="./assets/logo.jpg" alt="Logo" style="float:none;width:320px;"/></div>';
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container($selector['Name'],'mdContainer',$selector,[],array('style'=>[]));
                // center web page section
                if (empty($pageSettings['homePageContent'])){
                    // do nothing
                } else if (strcmp($pageSettings['homePageContent'],'imageShuffle')===0){
                    // show image shuffle
                    $width=380;
                    $height=320;
                    $wrapperSetting=array('style'=>array('float'=>'none','padding'=>'10px','border'=>'none','width'=>'fit-content','margin'=>'10px auto'));
                    $setting=array('hideReloadBtn'=>TRUE,'style'=>array('width'=>$width,'height'=>$height),'autoShuffle'=>TRUE,'getImageShuffle'=>'home');
                    $selector=array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Multimedia']->getEntryTable());
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry shuffle','getImageShuffle',$selector,$setting,$wrapperSetting);                            
                } else if (strcmp($pageSettings['homePageContent'],'video')===0){
                    // show intro video
                    $videoSrc=$GLOBALS['relDirs']['assets'].'/home.mp4';
                    $mime=@mime_content_type($videoSrc);
                    if ($mime){
                        $mediaHtml='<video width="100%" loop="true" autoplay="true" style="z-index:1;min-width:720px;" controls muted><source src="'.$videoSrc.'" type="'.$mime.'" /></video>';
                        $mediaHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','class'=>'bg-media','element-content'=>$mediaHtml,'keep-element-content'=>TRUE));
                        $arr['toReplace']['{{bgMedia}}']=$mediaHtml;
                        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','class'=>'transparent','element-content'=>' ','keep-element-content'=>TRUE));
                    } else {
                        $this->oc['logger']->log('error','Intro video File "{file}" missing. Please add this file.',array('file'=>$videoSrc));
                    }
                }
                // bottom web page section
                $selector=array('Source'=>$this->entryTable,'Group'=>'Home','Folder'=>'Public','Name'=>'Bottom paragraph');
                $selector['md']="# What is Datapool?\n\nDatapool is an open-source web application for efficient automated data processing. Processes are configurated graphically as a data flow throught processing blocks.\n";
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
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container($selector['Name'],'mdContainer',$selector,[],array('style'=>[]));
            }
            // Show the legal paragraph to everybody
            $selector=array('Source'=>$this->entryTable,'Group'=>'Home','Folder'=>'Public','Name'=>'Legal paragraph');
            $selector['md']="# Attributions\nThis webpage uses map data from *OpenStreetMap*. Please refer to <a href=\"https://www.openstreetmap.org/copyright\" target=\"_blank\" class=\"btn\" style=\"float:none;\">The OpenStreetMap License</a> for the license conditions.\n\nThe original intro video is by *Pressmaster*, www.pexels.com\n";
            $selector['md'].="# Contact\n## Address\n";
            $selector['md'].="## Email\n<img src=\"".$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($GLOBALS['dirs']['assets'].'email.png')."\" style=\"float:none;\">\n";
            $selector['md'].="# Legal\nThis is a private web page. The web page uses cookies for session handling.\n\n";
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container($selector['Name'],'mdContainer',$selector,[],array('style'=>[]));
            // finalize
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }

    private function honeAppWidgets():string
    {
        $widgetArr=[];
        foreach($this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\HomeApp') as $widgetClass){
            $maxHeight=($widgetClass==='SourcePot\Datapool\Foundation\Haystack')?'auto':'33vh';
            $table=$this->oc[$widgetClass]->getEntryTable();
            $priority=$this->oc[$widgetClass]->getHomeAppPriority();
            $priority=str_pad(strval($this->oc[$widgetClass]->getHomeAppPriority()),2,'0',STR_PAD_LEFT);
            $caption=$this->oc[$widgetClass]->getHomeAppCaption();
            $widgetHtml='';
            if (!empty($caption)){
                $widgetHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h2','class'=>'widget','element-content'=>$caption,'keep-element-content'=>TRUE]);
            }
            $widgetHtml.=$this->oc[$widgetClass]->getHomeAppWidget();
            $widgetHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','class'=>'widget','element-content'=>$widgetHtml,'keep-element-content'=>TRUE,'style'=>['max-height'=>$maxHeight,'overflow-y'=>'auto']]);
            $widgetArr[$priority.'_'.$table]=$widgetHtml;
        }
        ksort($widgetArr);
        return implode(PHP_EOL,$widgetArr);
    }

}
?>