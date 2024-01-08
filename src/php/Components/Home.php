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
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
                                 );
    
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }

    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
    }

    public function getEntryTable(){
        return $this->entryTable;
    }

    public function getEntryTemplate(){
        return $this->entryTemplate;
    }

    public function unifyEntry($entry){
        $entry['Read']=intval($entry['Content']['Read access']);
        return $entry;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Home','Emoji'=>'&#9750;','Label'=>'Home','Read'=>'ALL_R','Class'=>__CLASS__);
        } else {
            $pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
            $html='';
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin() || $this->oc['SourcePot\Datapool\Foundation\Access']->isPublic()){
                // markdown logo
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$this->getDocumentHtml('Top'),'keep-element-content'=>TRUE,'style'=>array('min-height'=>'20vh','overflow'=>'unset')));
                if (empty($pageSettings['homePageContent'])){
                    // do nothing
                } else if (strcmp($pageSettings['homePageContent'],'imageShuffle')===0){
                    $width=320;
                    $height=320;
                    $wrapperSetting=array('style'=>array('float'=>'none','padding'=>'10px','border'=>'none','width'=>$width+40,'margin'=>'10px auto','border'=>'1px dotted #999'));
                    $setting=array('hideReloadBtn'=>TRUE,'style'=>array('width'=>$width,'height'=>$height),'autoShuffle'=>TRUE,'getImageShuffle'=>'home');
                    $selector=array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Multimedia']->getEntryTable());
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry shuffle','getImageShuffle',$selector,$setting,$wrapperSetting);                            
                } else if (strcmp($pageSettings['homePageContent'],'video')===0){
                    if (empty($pageSettings['homePageContentSource'])){
                        $this->oc['SourcePot\Datapool\Foundation\Logger']->log('error','The pageSetting "homePageContent" == "video" but "homePageContentSource" is empty',array());    
                    } else {
                        $homePageContentSource=$pageSettings['homePageContentSource'];
                        if (stripos($homePageContentSource,'://')===FALSE){
                            $homePageContentSource='./assets/'.$homePageContentSource;
                        }
                        $videoHtml='<iframe class="video-container" src="'.$homePageContentSource.'" title="Intro" frameborder="0" allow="autoplay; accelerometer; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>';
                        $arr['toReplace']['{{background}}']='<div class="video-container">'.$videoHtml.'</div>';
                        // spacer for background video
                        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>' ','keep-element-content'=>TRUE,'style'=>array('height'=>'200px','overflow'=>'unset','background'=>'none')));
                    }
                }
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$this->getDocumentHtml('Bottom'),'keep-element-content'=>TRUE,'style'=>array('min-height'=>'70vh','overflow'=>'unset')));
            } else {
                $html.=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getQuicklinksHtml();
            }
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    private function getDocumentHtml(string $name='Content'):string
    {
        $entry=array('Source'=>$this->entryTable,'Group'=>__CLASS__,'Folder'=>$_SESSION['page state']['lngCode'],'Name'=>$name);
        $entry['Params']['File']=array('UploaderId'=>'SYSTEM','UploaderName'=>'System','Name'=>'Home.md','Date (created)'=>time(),'MIME-Type'=>'text/plain','Extension'=>'md');
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name'),'0','',FALSE);
        $fileName=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
        if (!is_file($fileName)){
            $fileContent="[//]: # (This a Markdown document!)\n\n";
            if ($name=='Top'){
                $fileContent.='<div class="center"><img src="./assets/logo.jpg" alt="Logo" style="width:20vw;margin-left:40vw;"/></div>';
            } else {
                $fileContent.='# Home ('.$_SESSION['page state']['lngCode'].')';
            }
            $entry['Params']['File']['Uploaded']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now',FALSE,FALSE);
            file_put_contents($fileName,$fileContent);
        }
        $arr=array('settings'=>array('style'=>array('width'=>'100vw')));
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE,TRUE,TRUE,'');
        $arr=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getPreview($arr);
        return $arr['html'];
    }
    
}
?>