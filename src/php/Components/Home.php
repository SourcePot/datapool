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

class Home implements \SourcePot\Datapool\Interfaces\App,\SourcePot\Datapool\Interfaces\HomeApp{
    
    private const APP_ACCESS='ALL_R';
    
    private $oc;
    private $entryTable='';

    private $pageSettings=[];
    private $hasHomeWidgetApp=FALSE;
    private $backgroundMediaInfo='';

    public const WIDGET_SETTINGS_SELECTOR=['app'=>'SourcePot\Datapool\AdminApps\Settings','Source'=>'settings','Group'=>'Home page','Folder'=>'Widgets','Name'=>'Home page'];
    
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
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
            return array('Category'=>'Home','Emoji'=>'&#9750;','Label'=>'Home','Read'=>self::APP_ACCESS,'Class'=>__CLASS__);
        } else {
            $this->pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
            if (strcmp($this->pageSettings['homePageContent'],'video')===0){
                // show intro video
                $videoSrc=$GLOBALS['relDirs']['assets'].'/home.mp4';
                $mime=@mime_content_type($videoSrc);
                if ($mime){
                    $mediaHtml='<video width="100%" loop="true" autoplay="true" style="z-index:1;min-width:720px;" controls muted><source src="'.$videoSrc.'" type="'.$mime.'" nonce="{{nonce}}"/></video>';
                    $mediaHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','class'=>'bg-media','element-content'=>$mediaHtml,'keep-element-content'=>TRUE]);
                    $arr['toReplace']['{{bgMedia}}']=$mediaHtml;
                } else {
                    $this->oc['logger']->log('error','Intro video File "{file}" missing. Please add this file.',array('file'=>$videoSrc));
                }
            } else if (strcmp($this->pageSettings['homePageContent'],'imageShuffle')===0){
                $settings=['isSystemCall'=>FALSE,'orderBy'=>'rand()','isAsc'=>FALSE,'limit'=>4,'offset'=>0,'autoShuffle'=>TRUE];
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator(['Source'=>'multimedia','Params'=>'%image%'],FALSE,'Read',$settings['orderBy'],$settings['isAsc'],$settings['limit'],$settings['offset']) as $entry){
                    $entry=$this->oc['SourcePot\Datapool\Tools\MediaTools']->addTmpFile(['selector'=>$entry])['selector'];
                    $url=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($entry['Params']['TmpFile']['Source']);
                    $mediaHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','class'=>'bg-media','element-content'=>' ','keep-element-content'=>TRUE,'style'=>['background-image'=>'url('.$url.')'],'function'=>'Home','nonce'=>'{{nonce}}']);
                    $this->backgroundMediaInfo=$entry['Params']['Address']['display_name']??$entry['Content']['Location/Destination']['display_name']??$entry['Name']??'';
                    $arr['toReplace']['{{bgMedia}}']=$mediaHtml;
                    break;
                }
            }
            $arr['toReplace']['{{content}}']=$this->homeAppWidgets();
            if (!$this->hasHomeWidgetApp){
                $arr['toReplace']['{{bgMedia}}']='';
            }
            return $arr;
        }
    }

    public function configureHomeWidgetsHtml(array $arr):array
    {
        $widgets=$infoMatrix=[];
        foreach($this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\HomeApp') as $widgetClass){
            $widget=explode('\\',$widgetClass);
            $widget=array_pop($widget);
            $widgets[$widgetClass]=$widget;
            // create info table
            $info=$this->oc[$widgetClass]->getHomeAppInfo();
            $info='<p style="font-size:1.2rem;padding:5px 0;">'.$info.'</p>';
            $widget='<p style="font-size:1.2rem;padding:5px 0;">'.$widget.'</p>';
            $infoMatrix[$widget]=['Widget'=>$widget,'Info'=>$info,'Class'=>$widgetClass];
        }
        $contentStructure=[
            'Widget'=>['method'=>'select','excontainer'=>TRUE,'value'=>key($widgets),'options'=>$widgets],
            'Name'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'component','excontainer'=>TRUE],
            'Access'=>['method'=>'select','excontainer'=>TRUE,'value'=>'ALL_MEMBER_R','options'=>$this->oc['SourcePot\Datapool\Foundation\Access']->getAccessOptionsStrings()],
            'Wrapper style'=>['method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'height:20vh;','excontainer'=>TRUE],
            ];
        // get selctor
        $arr['selector']=self::WIDGET_SETTINGS_SELECTOR;
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],['Source','Group','Folder','Name'],0,'',FALSE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        $elementId=key($formData['val']);
        if (isset($formData['cmd'][$elementId])){
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        }
        // get HTML
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Home Page Widgets';
        $arr['noBtns']=TRUE;
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h1','element-content'=>'Start web (Home) page widgets: widgets will be presented ascending based on "Key"']);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$infoMatrix,'caption'=>'Widget info for all available widgets','keep-element-content'=>TRUE,'hideKeys'=>TRUE,'hideHeader'=>FALSE]);
        return $arr;
    }

    private function homeAppWidgets():string
    {
        $html='';
        $widgetTemplate=['tag'=>'div','class'=>'widget','element-content'=>'Widget did not provide content...','keep-element-content'=>TRUE];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator(self::WIDGET_SETTINGS_SELECTOR,TRUE,'Read','EntryId',TRUE) as $widgetSetting){
            if (!$this->oc['SourcePot\Datapool\Foundation\Access']->hasAccess(FALSE,intval($widgetSetting['Content']['Access']))){
                continue;
            }
            $widgetHtml='';
            $widgetClass=$widgetSetting['Content']["Widget"];
            if ($widgetClass===__CLASS__){
                $this->hasHomeWidgetApp=TRUE;
            }
            if (empty($this->oc[$widgetClass])){
                $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()": widget class "{widgetClass}" does not exist. Please check Admin&rarr;Settings&rarr;Quick link "Start page".',['class'=>__CLASS__,'function'=>__FUNCTION__,'widgetClass'=>$widgetClass]);
                continue;
            }
            $caption=$this->oc[$widgetClass]->getHomeAppInfo();
            if (!empty($caption)){
                $widgetHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h2','class'=>'widget','element-content'=>$caption,'keep-element-content'=>TRUE]);
            }
            $widgetWrapperStyle=$this->oc['SourcePot\Datapool\Tools\MiscTools']->style2arr($widgetSetting['Content']['Wrapper style']);
            $widget=$this->oc[$widgetClass]->getHomeAppWidget($widgetSetting['Content']['Name']);
            $widget=array_replace_recursive($widgetTemplate,$widget,['style'=>$widgetWrapperStyle]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($widget);
        }
        return $html;
    }

    public function getHomeAppWidget(string $name):array
    {
        $element=['element-content'=>'Content missing, no images available or "home.mp4" missing...'];
        if (empty($this->pageSettings['homePageContent'])){
            // do nothing
            $element['element-content']='';
        } else if (strcmp($this->pageSettings['homePageContent'],'imageShuffle')===0){
            // show image shuffle
            $info=strip_tags($this->backgroundMediaInfo);
            if (empty($info)){
                $element['element-content']='';
            } else {
                $element['element-content']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','class'=>'bg-media','element-content'=>$info,'style'=>['display'=>'none']]);
            }
            $element['class']='transparent';
        } else if (strcmp($this->pageSettings['homePageContent'],'video')===0){
            // show intro video
            $element['element-content']=' ';
            $element['class']='transparent';
        }
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget presents an <b>image shuffle view</b> or <b>background video ./assets/home.mp4</b>.<br/>Image shuffle or video need to be selected in category "Admin" &larr; "Admin"';
        return $info;
    }   
}
?>