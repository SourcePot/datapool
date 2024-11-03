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

class Docs implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    public $definition=array('EntryId'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@Write'=>0));

    private $assetWhitelist=array('email.png'=>TRUE,'home.mp4'=>TRUE,'logo.jpg'=>TRUE,'dateType_example.png'=>TRUE,'login.jpg'=>TRUE,'Example_data_flow.png'=>TRUE);

    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init(){
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
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
        return $entry;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Home','Emoji'=>'&#128366;','Label'=>'Docs','Read'=>'ALL_R','Class'=>__CLASS__);
        } else {
            // add explorer and set selector
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__,FALSE);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            $selector=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($selector,'ALL_R','ALL_CONTENTADMIN_R');
            // add content article
            $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Doc','mdContainer',$selector,array(),array('style'=>array()));
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
                $html.=$this->assetManager($selector);
                $this->copy2assetsDir();
            }
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }

    public function assetManager(array $selector):string
    {
        $selector['Source']=$this->oc['SourcePot\Datapool\Components\Home']->getEntryTable();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (!empty($formData['cmd'])){
            $selector['EntryId']=hrtime(TRUE).'_asset';
            $flatFile=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($formData['files']);
            $fileArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatArrLeaves($flatFile);
            if ($fileArr['error']==0){
                $selector['Content']=array();
                $pathInfo=pathinfo($fileArr['name']);
                $selector['Content']['src']=$this->entry2asset($selector,$pathInfo['extension'],TRUE);
                if ($pathInfo['extension']=='mp4' || $pathInfo['extension']=='webm'){
                    $selector['Content']['tag']='<video controls width="360"><source src="'.$selector['Content']['src'].'" type="video/'.$pathInfo['extension'].'" /></video>';
                } else {
                    $selector['Content']['tag']='<img src="'.$selector['Content']['src'].'" title="'.$fileArr['name'].'" style=""/>';
                }
                $selector['Content']['tag']=htmlentities($selector['Content']['tag']);
                $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->fileUpload2entry($fileArr,$selector);
            }
        }
        $html='';
        // file upload
        $fileUpload=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'file','element-content'=>'','key'=>array('add'),'excontainer'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
        $btn=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Add','key'=>array('add'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
        $matrix['']=array('New asset file'=>$fileUpload,'Cmd'=>$btn);
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Assets - public static web page content'));
        // asset manager
        $selector['Name']=FALSE;
        $selector['EntryId']='%_asset';
        $settings=array('orderBy'=>'Name','isAsc'=>FALSE,'limit'=>5,'hideUpload'=>TRUE);
        $settings['columns']=array(array('Column'=>'Name','Filter'=>''),array('Column'=>'Content'.\SourcePot\Datapool\Root::ONEDIMSEPARATOR.'tag','Filter'=>''));
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Assets','entryList',$selector,$settings,array());
        //
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'&#9887;'));
        return $html;
    }
    
    private function entry2asset(array $entry,string $extension,bool $relDir=TRUE):string
    {
        $fileName=$this->oc['SourcePot\Datapool\Components\Home']->getEntryTable().'-'.str_replace('_asset','',$entry['EntryId']).'.'.$extension;
        if ($relDir){
            return $GLOBALS['relDirs']['assets'].'/'.$fileName;
        } else {
            return $GLOBALS['dirs']['assets'].$fileName;
        }
    }

    private function copy2assetsDir()
    {
        // delete asset files not present in database
        $entriesPresentAsAssetFiles=array();
        $files=scandir($GLOBALS['relDirs']['assets']);
        foreach($files as $fileName){
            if (strlen($fileName)<3){continue;}
            if (isset($this->assetWhitelist[$fileName])){continue;}
            $fileNameComps=preg_split('/[-_\.]/',$fileName);
            if (!isset($GLOBALS['dbInfo'][$fileNameComps[0]])){continue;}
            $selector=array('Source'=>$fileNameComps[0],'EntryId'=>$fileNameComps[1].'_%');
            if ($entry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($selector,TRUE)){
                // match of asset file with database entry
                $entriesPresentAsAssetFiles[$entry['EntryId']]=$entry['Source'];
            } else {
                // no match of asset file with database entry
                unlink($GLOBALS['dirs']['assets'].$fileName);
            }
        }
        // add files present in database
        $selector=array('Source'=>$this->oc['SourcePot\Datapool\Components\Home']->getEntryTable(),'EntryId'=>'%_asset');
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $assetEntry){
            if (isset($entriesPresentAsAssetFiles[$assetEntry['EntryId']])){continue;}
            $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($assetEntry);
            $targetFile=$this->entry2asset($assetEntry,$assetEntry['Params']['File']['Extension'],FALSE);
            $this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($sourceFile,$targetFile,0774);
        }
    }

}
?>