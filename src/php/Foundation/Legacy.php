<?php
/*
* This file is part of the Datapool CMS package.
* ANY METHODS DEALING WITH IMPORTING ENBTRIES AND WITH LEGACY ISSUES SHOULD BE PLACED HERE.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Legacy{
    private $oc;
    
    private const TIMELIMIT_SEC=550;
    private const FILESPACE2IMPORT='/var/www/vhosts/wallenhauer.com/httpdocs/wallenhauer/src/_filespace';    // directory location of the filespace to import
    private $sourceDB=FALSE;    // The sourceDb setting are stored at ./setup/Legacy/sourceDb.json
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        
    }

    public function importPage(array $arr):array
    {
        // connect to database and get available tables
        $tables=[];
        $this->sourceDB=$this->oc['SourcePot\Datapool\Foundation\Database']->connect(__CLASS__,'sourceDb'); 
        foreach ($this->sourceDB->query('SHOW TABLES;') as $row){
            $tables[$row[0]]=FALSE;
        }
        // load settings
        $setting=array('Class'=>__CLASS__,'EntryId'=>__FUNCTION__,'Read'=>65535,'Content'=>array('entries'=>[],'tables'=>[],'entryCount'=>0,'Dir'=>__DIR__));
        $setting=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($setting,TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['save'])){
            // table selector
            $setting['Content']=array('entries'=>[],'tables'=>[],'entryCount'=>0);
            foreach($tables as $table=>$selected){
                $setting['Content']['tables'][$table]=isset($formData['val']['tables'][$table]);
            }
            $setting=$this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($setting,TRUE,FALSE,FALSE);
        } else if (isset($formData['cmd']['load'])){
            $setting['Content']['entries']=[];
            foreach($setting['Content']['tables'] as $table=>$selected){
                if (!$selected){continue;}
                foreach ($this->sourceDB->query('SELECT `ElementId` FROM `'.$table.'`;') as $entryId){
                    $setting['Content']['entries'][]=array($table,$entryId[0]);
                }
            }
            $setting['Content']['entryCount']=count($setting['Content']['entries']);
            $setting=$this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($setting,TRUE,FALSE,FALSE);
        } else if (isset($formData['cmd']['process'])){
            $start=time();
            while(time()-$start<self::TIMELIMIT_SEC){
                $entryComps=array_pop($setting['Content']['entries']);
                if (empty($entryComps)){
                    $setting['Content']['entries']=[];
                    break;
                }
                $this->processEntry(array('Source'=>$entryComps[0],'ElementId'=>$entryComps[1]));
            }
            $setting=$this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($setting,TRUE,FALSE,FALSE);
        }
        // tables matrix
        $tablesStr='';
        $tablesMatrix=[];
        foreach($tables as $table=>$selected){
            if (empty($setting['Content']['tables'][$table])){
                $tablesMatrix[$table]=array('Import'=>array('tag'=>'input','type'=>'checkbox','checked'=>FALSE,'key'=>array('tables',$table),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
            } else {
                $tablesStr.=$table.', ';
                $tablesMatrix[$table]=array('Import'=>array('tag'=>'input','type'=>'checkbox','checked'=>TRUE,'key'=>array('tables',$table),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
            }
        }
        $tablesMatrix['']=array('Import'=>array('tag'=>'button','element-content'=>'Save','key'=>array('save'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
        // to do matrix
        $toDoMatrix=[];
        $toDoMatrix['Tables']=array('Processing'=>array('tag'=>'p','element-content'=>trim($tablesStr,', ')));
        $toDoMatrix['Progress']=array('Processing'=>array('tag'=>'p','element-content'=>strval($setting['Content']['entryCount']-count($setting['Content']['entries'])).' of '.strval($setting['Content']['entryCount']).' done'));
        $toDoMatrix[' ']=array('Processing'=>array('tag'=>'meter','min'=>0,'max'=>$setting['Content']['entryCount'],'value'=>$setting['Content']['entryCount']-count($setting['Content']['entries'])));
        $cmd=($setting['Content']['entryCount']==0)?'load':'process';
        $toDoMatrix['']=array('Processing'=>array('tag'=>'button','element-content'=>ucfirst($cmd),'key'=>array($cmd),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
        // page html
        $html='<h1 style="color:#f00;">Import page</h1>';
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$tablesMatrix,'keep-element-content'=>TRUE,'caption'=>'Tables to import','hideKeys'=>FALSE,'hideHeader'=>TRUE));
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$toDoMatrix,'keep-element-content'=>TRUE,'caption'=>'Entries to process','hideKeys'=>FALSE,'hideHeader'=>TRUE));
        $arr['toReplace']['{{content}}']=$html;
        return $arr;
    }

    private function processEntry(array $selector)
    {
        $sourceEntry=[];
        foreach ($this->sourceDB->query('SELECT * FROM `'.$selector['Source'].'` WHERE `ElementId`=\''.$selector['ElementId'].'\';') as $entry){
            foreach($entry as $column=>$value){
                if (is_numeric($column)){continue;}
                if ($column=='Content' || $column=='Params'){
                    $sourceEntry[$column]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr((string)$value);;
                } else {
                    $sourceEntry[$column]=$value;
                }
            }
            $file=self::FILESPACE2IMPORT.'\\'.$selector['Source'].'\\'.$selector['ElementId'].'.file';
        }
        if (empty($sourceEntry)){return FALSE;}
        if ($selector['Source']==='user'){
            // *****************  Target Table "user" *************************
            $paramsFile=$sourceEntry['Params']['File']??[];
            $paramsGeo=$sourceEntry['Params']['Geo']??[];
            $sourceEntry['Params']=array('File'=>$paramsFile,'Geo'=>$paramsGeo);
            $sourceEntry['Source']='user';
            $sourceEntry['Name']=$sourceEntry['Content']['Contact details']['Family name'].', '.$sourceEntry['Content']['Contact details']['First name'];
            $sourceEntry['Email']=$sourceEntry['Folder']=$sourceEntry['Content']['Contact details']['Email'];
            $sourceEntry['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($sourceEntry['Email']);
            $sourceEntry['Owner']=$sourceEntry['EntryId'];
            if (is_file($file)){
                $sourceEntry['fileContent']=file_get_contents($file);
                $sourceEntry['fileName']=$paramsFile['Name'];
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileContent2entry($sourceEntry,TRUE,TRUE);
            } else {
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($sourceEntry,TRUE,TRUE);
            }
        } else if ($selector['Source']==='forum'){
            // *****************  Target Table "forum" *************************
            $sourceEntry['Source']='forum';
            $sourceEntry['EntryId']=$sourceEntry['ElementId'];
            $sourceEntry['Owner']=$sourceEntry['Creator'];
            $paramsFile=(isset($sourceEntry['Params']['File']))?$sourceEntry['Params']['File']:[];
            $paramsAddress=(isset($sourceEntry['Params']['reverseGeoLoc']['address']))?$sourceEntry['Params']['reverseGeoLoc']['address']:[];
            if (isset($sourceEntry['Params']['reverseGeoLoc']['display_name'])){
                $paramsAddress['display_name']=$sourceEntry['Params']['reverseGeoLoc']['display_name'];
            }
            $paramsGeo=(isset($sourceEntry['Params']['Geo']))?$sourceEntry['Params']['Geo']:[];
            $sourceEntry['Params']=array('File'=>$paramsFile,'Address'=>$paramsAddress,'Geo'=>$paramsGeo);
            if (is_file($file)){
                $targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($file,$targetFile);
            }
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($sourceEntry,TRUE,FALSE);
        } else if ($selector['Source']==='calendar'){
            // *****************  Target Table "calendar" *************************
            if (strpos($sourceEntry['Name'],'BDay')!==FALSE || strpos($sourceEntry['Type'],'bankholiday')!==FALSE || !isset($sourceEntry['Content']['Entry']['Visibility']) || strpos($sourceEntry['Name'],'Name missing')!==FALSE){return FALSE;}
            $sourceEntry['Source']='calendar';
            $sourceEntry['Group']='Events';
            $sourceEntry['Name']=mb_substr($sourceEntry['Content']['Entry']['Description'],0,120);
            $sourceEntry['EntryId']=$sourceEntry['ElementId'];
            $sourceEntry['Owner']=$sourceEntry['Creator'];
            $sourceEntry['Read']=intval(trim($sourceEntry['Content']['Entry']['Visibility'],'-'));
            $contentEvent=array('Description'=>$sourceEntry['Content']['Entry']['Description'],
                                'Type'=>$sourceEntry['Content']['Entry']['Type'],
                                'Start'=>((is_array($sourceEntry['Content']['Entry']['Start']))?(implode(' ',$sourceEntry['Content']['Entry']['Start'])):$sourceEntry['Content']['Entry']['Start']),
                                'Start timezone'=>$sourceEntry['Content']['Entry']['Start timezone'],
                                'End'=>((is_array($sourceEntry['Content']['Entry']['End']))?(implode(' ',$sourceEntry['Content']['Entry']['End'])):$sourceEntry['Content']['Entry']['End']),
                                'End timezone'=>$sourceEntry['Content']['Entry']['End timezone'],
                            );
            $sourceEntry['Content']=array('Event'=>$contentEvent,'Location/Destination'=>(isset($sourceEntry['Content']['Location/Destination']))?$sourceEntry['Content']['Location/Destination']:[]);
            if (is_file($file)){
                $sourceEntry['Params']=array('File'=>$sourceEntry['Params']['File']);
                $targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($file,$targetFile);
            } else {
                $sourceEntry['Params']=[];
            }
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($sourceEntry,TRUE,TRUE);
        } else if ($selector['Source']==='multimedia'){
            // *****************  Target Table "multimedia" *************************
            if (strpos($sourceEntry['Type'],'__')===0){return FALSE;}
            $sourceEntry['Source']='multimedia';
            $sourceEntry['EntryId']=$sourceEntry['ElementId'];
            $sourceEntry['Owner']=$sourceEntry['Creator'];
            if (is_file($file)){
                $paramsFile=(isset($sourceEntry['Params']['File']))?$sourceEntry['Params']['File']:[];
                $paramsAddress=(isset($sourceEntry['Params']['reverseGeoLoc']['address']))?$sourceEntry['Params']['reverseGeoLoc']['address']:[];
                $paramsAddress['display_name']=(isset($sourceEntry['Params']['reverseGeoLoc']['display_name']))?$sourceEntry['Params']['reverseGeoLoc']['display_name']:'';
                $paramsGeo=(isset($sourceEntry['Params']['Geo']))?$sourceEntry['Params']['Geo']:[];
                $sourceEntry['Params']=array('File'=>$paramsFile,'Address'=>$paramsAddress,'Geo'=>$paramsGeo);
                $targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($file,$targetFile);
            } else {
                $sourceEntry['Params']=[];
            }
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($sourceEntry,TRUE,TRUE);
        } else if ($selector['Source']==='security'){
            // *****************  Target Table "documents" SECURITY *************************
            if (strpos($sourceEntry['Type'],'__')===0){return FALSE;}
            $sourceEntry['Source']='documents';
            $sourceEntry['EntryId']=$sourceEntry['ElementId'];
            $sourceEntry['Owner']=$sourceEntry['Creator'];
            $sourceEntry['Group']='Security';
            $sourceEntry['Name'].=' ('.$sourceEntry['Type'].')';
            if (is_file($file)){
                $sourceEntry['Params']=array('File'=>$sourceEntry['Params']['File']);
                $targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($file,$targetFile);
            } else {
                $sourceEntry['Params']=[];
            }
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($sourceEntry,TRUE,TRUE);
        } else if ($selector['Source']==='inventory' || $selector['Source']==='invoicing' || $selector['Source']==='finance'){
            // *****************  Target Table "documents" *************************
            if (strpos($sourceEntry['Type'],'__')===0){return FALSE;}
            $sourceEntry['Source']='documents';
            $sourceEntry['Group']=($selector['Source']==='cloud')?$sourceEntry['Group']:($selector['Source'].' '.$sourceEntry['Group']);
            $sourceEntry['EntryId']=$sourceEntry['ElementId'];
            $sourceEntry['Owner']=$sourceEntry['Creator'];
            if (is_file($file)){
                $paramsFile=(isset($sourceEntry['Params']['File']))?$sourceEntry['Params']['File']:[];
                $paramsAddress=(isset($sourceEntry['Params']['reverseGeoLoc']['address']))?$sourceEntry['Params']['reverseGeoLoc']['address']:[];
                $paramsAddress['display_name']=(isset($sourceEntry['Params']['reverseGeoLoc']['display_name']))?$sourceEntry['Params']['reverseGeoLoc']['display_name']:'';
                $paramsGeo=(isset($sourceEntry['Params']['Geo']))?$sourceEntry['Params']['Geo']:[];
                $sourceEntry['Params']=array('File'=>$paramsFile,'Address'=>$paramsAddress,'Geo'=>$paramsGeo);
                $targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($file,$targetFile);
            } else {
                $sourceEntry['Params']=[];
            }
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($sourceEntry,TRUE,TRUE);
        } else {
            // undefined source table
            $context=array('class'=>__CLASS__,'function'=>__FUNCTION__,'Source'=>$selector['Source']);
            $this->oc['logger']->log('notice','{class} &rarr; {function}() definition missing for source table {Source}',$context);
        }
    }

    public function updateEntryListEditorEntries($arr)
    {
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__);
        if (!empty($arr['selector']['Source'])){
            $currentListSelector=array('Source'=>$arr['selector']['Source']);
        } else if (!empty($arr['selector']['Class'])){
            $currentListSelector=array('Class'=>$arr['selector']['Class']);
        } else {
            return FALSE;
        }
        // get current ordered list entry example from selector
        $currentListSelector['Group']=(empty($arr['selector']['Group']))?FALSE:$arr['selector']['Group'];
        $currentListSelector['Folder']=(empty($arr['selector']['Folder']))?FALSE:$arr['selector']['Folder'];
        $currentListSelector['Name']=(empty($arr['selector']['Name']))?FALSE:$arr['selector']['Name'];
        $currentListEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($currentListSelector,TRUE);
        // check for key mismatch
        if (empty($currentListEntry['EntryId']) || empty($arr['selector']['EntryId'])){return $arr;}
        $oldKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListKeyFromEntryId($currentListEntry['EntryId']);
        $newKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListKeyFromEntryId($arr['selector']['EntryId']);
        if ($oldKey===$newKey){return $arr;}
        // correct key mismatch
        $rebuildSelector=array('EntryId'=>$oldKey);
        if (!empty($currentListEntry['Source'])){
            $rebuildSelector['Source']=$currentListEntry['Source'];
        } else if (!empty($currentListEntry['Class'])){
            $rebuildSelector['Class']=$currentListEntry['Class'];
        } else {
            return FALSE;
        }
        $this->oc['SourcePot\Datapool\Foundation\Database']->rebuildOrderedList($rebuildSelector,array('newOlKey'=>$newKey));
        $context['oldKey']=$oldKey;
        $context['newKey']=$newKey;
        $this->oc['logger']->log('notice','{class} &rarr; {function}() changed ordered list keys: "{oldKey} &rarr; {newKey}"',$context);
    
        return TRUE;
    }
    
}
?>