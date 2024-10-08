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
    private $entryTable;
    
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
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

    public function getEntryTable(){
        return $this->entryTable;
    }

    public function getEntryTemplate(){
        return $this->entryTemplate;
    }

    public function unifyEntry($entry){
        $entry['Read']=intval($entry['Content']['Read access']??$this->oc['SourcePot\Datapool\Foundation\Access']->accessString2int($this->entryTemplate['Read']['value']));
        return $entry;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Home','Emoji'=>'&#9750;','Label'=>'Home','Read'=>'ALL_R','Class'=>__CLASS__);
        } else {
            $pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
            $html='';
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin() || $this->oc['SourcePot\Datapool\Foundation\Access']->isPublic()){
                // top web page section
                $selector=array('Source'=>$this->entryTable,'Group'=>'Home','Folder'=>'Public','Name'=>'Top paragraph');
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container($selector['Name'],'mdContainer',$selector,array(),array('style'=>array()));
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
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container($selector['Name'],'mdContainer',$selector,array(),array('style'=>array()));
            } else {
                $html.=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getQuicklinksHtml();
            }
            $selector=array('Source'=>$this->entryTable,'Group'=>'Home','Folder'=>'Public','Name'=>'Legal paragraph');
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container($selector['Name'],'mdContainer',$selector,array(),array('style'=>array()));
            // finalize
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
}
?>