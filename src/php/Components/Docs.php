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
    
    private const APP_ACCESS='ALL_R';
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],];
    
    public $definition=['EntryId'=>['@tag'=>'input','@type'=>'text','@default'=>'','@Write'=>0]];

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
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
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
        return $entry;
    }

    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return array('Category'=>'Home','Emoji'=>'&#128366;','Label'=>'Docs','Read'=>self::APP_ACCESS,'Class'=>__CLASS__);
        } else {
            // add explorer and set selector
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__,['EntryId'=>FALSE]);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            $selector=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($selector,'ALL_R','ALL_CONTENTADMIN_R');
            // add content article
            $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Doc','mdContainer',$selector,[],['style'=>[]]);
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
                $selector['Content']=[];
                $pathInfo=pathinfo($fileArr['name']);
                $selector['Content']['src']=$this->entry2asset($selector,$pathInfo['extension'],TRUE);
                if ($pathInfo['extension']=='pdf'){
                    $selector['Content']['tag']='<object data="'.$selector['Content']['src'].'" type="application/pdf" title="" style="width:95vw;height:70vh;"/>';
                } else if ($pathInfo['extension']=='mp4' || $pathInfo['extension']=='webm'){
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
        $fileUpload=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'file','element-content'=>'','key'=>['add'],'excontainer'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
        $btn=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'Add','key'=>['add'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
        $matrix['']=array('New asset file'=>$fileUpload,'Cmd'=>$btn);
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Assets - public static web page content']);
        // asset manager
        $selector['Name']=FALSE;
        $selector['EntryId']='%_asset';
        $settings=array('orderBy'=>'Name','isAsc'=>FALSE,'limit'=>5,'hideUpload'=>TRUE);
        $settings['columns']=[['Column'=>'Name','Filter'=>''],['Column'=>'Content'.\SourcePot\Datapool\Root::ONEDIMSEPARATOR.'tag','Filter'=>'']];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Assets','entryList',$selector,$settings,[]);
        //
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'&#9887;']);
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
        $entriesPresentAsAssetFiles=[];
        $files=scandir($GLOBALS['relDirs']['assets']);
        foreach($files as $fileName){
            if (strlen($fileName)<3){continue;}
            if (isset(\SourcePot\Datapool\Root::ASSETS_WHITELIST[$fileName])){continue;}
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
        $selector=['Source'=>$this->oc['SourcePot\Datapool\Components\Home']->getEntryTable(),'EntryId'=>'%_asset'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $assetEntry){
            if (isset($entriesPresentAsAssetFiles[$assetEntry['EntryId']])){continue;}
            $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($assetEntry);
            $targetFile=$this->entry2asset($assetEntry,$assetEntry['Params']['File']['Extension'],FALSE);
            $this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($sourceFile,$targetFile,0774);
        }
    }

}
?>