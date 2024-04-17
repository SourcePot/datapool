<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\AdminApps;

class Settings implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Owner'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'{{Owner}}','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.')
                                 );
    
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
        return $this->oc;
    }

    public function getEntryTable(){
        return $this->entryTable;
    }
    
    public function getEntryTemplate(){
        return $this->entryTemplate;
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Admin','Emoji'=>'&#9783;','Label'=>'Settings','Read'=>'ADMIN_R','Class'=>__CLASS__);
        } else {
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            $html='';
            if (empty($selector['Group'])){
                $settings=array('hideUpload'=>TRUE,'orderBy'=>'Date','isAsc'=>FALSE,'columns'=>array(array('Column'=>'Group','Filter'=>''),array('Column'=>'Folder','Filter'=>''),array('Column'=>'Name','Filter'=>'')));
                $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container(__CLASS__.' settings','entryList',$selector,$settings,array());    
            } else {
                if ($selector['Group']==='Presentation' && !empty($selector['Folder'])){
                    $settings=array('method'=>'getPresentationSettingHtml','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder');
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry presentation','generic',$selector,$settings,array());
                } else if (!empty($selector['EntryId'])){
                    $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
                    if (isset($entry['Content']) && isset($entry['Params'])){
                        $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix(array('Content'=>$entry['Content'],'Params'=>$entry['Params']));
                        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE));
                    } else {
                        $html.='No entry found...';
                    }
                } else {
                    $settings=array('hideUpload'=>TRUE,'columns'=>array(array('Column'=>'Group','Filter'=>''),array('Column'=>'Folder','Filter'=>''),array('Column'=>'Name','Filter'=>'')));
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container(__CLASS__.' settings','entryList',$selector,$settings,array());
                }
                if ($selector['Group']==='Job processing'){
                    $settings=array('classWithNamespace'=>'SourcePot\Datapool\Foundation\Job','method'=>'getJobOverview');
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Job overview','generic',$selector,$settings,array());    
                }
            }
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }

    public function setSetting($callingClass,$callingFunction,$setting,$name='System',$isSystemCall=FALSE){
        $entry=array('Source'=>$this->entryTable,'Group'=>$callingClass,'Folder'=>$callingFunction,'Name'=>$name,'Type'=>$this->entryTable);
        $entry['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
        if ($isSystemCall){$entry['Owner']='SYSTEM';}
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name','Type'),0,'',FALSE);
        $entry['Content']=$setting;
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,$isSystemCall);
        $this->oc['logger']->log('info','Setting "{name}" updated',array('name'=>$name));    
        if (isset($entry['Content'])){return $entry['Content'];} else {return array();}
    }
    
    public function getSetting($callingClass,$callingFunction,$initSetting=array(),$name='System',$isSystemCall=FALSE){
        $entry=array('Source'=>$this->entryTable,'Group'=>$callingClass,'Folder'=>$callingFunction,'Name'=>$name,'Type'=>$this->entryTable);
        if ($isSystemCall){$entry['Owner']='SYSTEM';}
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name','Type'),0,'',FALSE);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_MEMBER_R','ALL_MEMBER_R');
        $entry['Content']=$initSetting;
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,$isSystemCall);
        if (isset($entry['Content'])){return $entry['Content'];} else {return array();}
    }
    
    public function getVars($class,$initVars=array(),$isSystemCall=FALSE){
        return $this->getSetting('Job processing','Var space',$initVars,$class,$isSystemCall);
    }

    public function setVars($class,$vars=array(),$isSystemCall=FALSE){
        return $this->setSetting('Job processing','Var space',$vars,$class,$isSystemCall);
    }
    
}
?>