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
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );

    public $definition=array('EntryId'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@Write'=>0));

    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }

    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
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
            return array('Category'=>'Home','Emoji'=>'&#128366;','Label'=>'Docs','Read'=>'ALL_R','Class'=>__CLASS__);
        } else {
            // add explorer
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
                $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
            } else {
                $style=array('width'=>'300px','border'=>'none','overflow'=>'unset');
                $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getTocHtml(__CLASS__,$style);
            }
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            // add content article
            $html='';
            if (!empty($selector['EntryId'])){
                $presentArr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
                $presentArr['settings']=array('style'=>array('width'=>'98%','border'=>'none'),'presentEntry'=>__CLASS__.'::'.__FUNCTION__);
                $presentArr['selector']=$selector;
                $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->presentEntry($presentArr);
                $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>array('width'=>'84%')));
                //
                $settings=array('method'=>'manageAssets','classWithNamespace'=>__CLASS__);
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Manage assets','generic',array('Source'=>$this->entryTable),$settings,array('style'=>array()));
            }
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    public function manageAssets($arr){
        $arr['html']=(isset($arr['html']))?$arr['html']:'';
        if (!$this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin()){
            return $arr;
        }
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['remove'])){
            $completeFileName=$GLOBALS['dirs']['assets'].key($formData['cmd']['remove']);
            unlink($completeFileName);
        } else if (isset($formData['cmd']['add'])){
            $fileHandle=current($formData['files']['add']);
            if (empty($fileHandle['error'])){
                $success=move_uploaded_file($fileHandle['tmp_name'],$GLOBALS['dirs']['assets'].$fileHandle['name']);
                if ($success){
                    $this->oc['SourcePot\Datapool\Foundation\Logger']->log('notice','Moved uploaded file "{file}" to dir "{dir}"',array('file'=>$fileHandle['name'],'dir'=>$GLOBALS['dirs']['assets']));         
                } else {
                    $this->oc['SourcePot\Datapool\Foundation\Logger']->log('error','Moving uploaded file "{file}" to dir "{dir}" failed.',array('file'=>$fileHandle['name'],'dir'=>$GLOBALS['dirs']['assets']));             
                }
            } else {
                $this->oc['SourcePot\Datapool\Foundation\Logger']->log('warning','Failed to upload "{file}" with error code "{code}" failed.',array('file'=>$fileHandle['name'],'code'=>$fileHandle['error']));             
            }
        }
        $matrix=array();
        $assets=scandir($GLOBALS['dirs']['assets']);
        foreach($assets as $file){
            $completeFileName=$GLOBALS['dirs']['assets'].$file;
            if (!is_file($completeFileName)){continue;}
            $btn=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Delete','key'=>array('remove',$file),'hasCover'=>TRUE,'title'=>'delete file','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
            $relFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->abs2rel($completeFileName);
            $relFile=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->copy2clipboard($relFile);
            $matrix[$file]=array('Relative file path'=>$relFile,'Cmd'=>$btn);
        }
        $fileUpload=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'file','element-content'=>'','key'=>array('add'),'excontainer'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
        $btn=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Add','key'=>array('add'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
        $matrix['']=array('Relative file path'=>$fileUpload,'Cmd'=>$btn);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Documentation assets'));
        return $arr;
    }
}
?>