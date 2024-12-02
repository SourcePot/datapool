<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Haystack{
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array();
    
    public function __construct(array $oc)
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
    
    public function getQueryHtml(array $arr):array
    {
        $queryEntry=array('Source'=>$this->entryTable,'Group'=>'Queries','Folder'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId());
        // process data
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['search'])){
            if (empty($formData['val']['Query'])){
            } else {
                $serachResult=$this->getSerachResultHtml($formData['val']['Query']);
                $queryEntry['Name']=substr($formData['val']['Query'],0,20);
                $queryEntry['Content']=array('Query'=>$serachResult['Query'],'Names'=>$serachResult['Names']);
                $queryEntry['Params']=array(__CLASS__=>array('Hits'=>$serachResult['Hits']));
                $queryEntry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','P10D',\SourcePot\Datapool\Root::DB_TIMEZONE);
                $queryEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($queryEntry);
            }
        } else {
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($queryEntry,FALSE,'Read','Date',FALSE) as $queryEntry){
                $serachResult['Query']=$queryEntry['Content']['Query'];
                break;
            }
        }
        $serachResult['Query']=$serachResult['Query']??'';
        $serachResult['html']=$serachResult['html']??'';
        // compile html
        $arr['html']=(empty($arr['html']))?'':$arr['html'];
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'text','value'=>$serachResult['Query'],'placeholder'=>'Enter your query here...','key'=>array('Query'),'excontainer'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'style'=>array()));
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Search','key'=>array('search'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'style'=>array()));
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'style'=>array('float'=>'none','width'=>'max-content','margin'=>'5em auto')));
        $arr['html'].=$serachResult['html'];
        return $arr;
    }

    private function getSerachResultHtml(string $query):array
    {
        $entryCount=0;
        $selectors=array(array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Multimedia']->getEntryTable(),'Content'=>'%'.$query.'%'),
                         array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Forum']->getEntryTable(),'Content'=>'%'.$query.'%'),
                         array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Feeds']->getEntryTable(),'Content'=>'%'.$query.'%'),
                         array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Calendar']->getEntryTable(),'Content'=>'%'.$query.'%'),
                    );
        $arr['Query']=$query;
        $arr['html']='';
        $arr['Names']=array();
        $arr['Hits']=array();
        foreach($selectors as $selector){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Date',FALSE,100,0) as $entry){
                $arr['Names'][$entry['EntryId']]=$entry['Name'];
                $arr['Hits'][$entry['EntryId']]=$entry['Source'];
                $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>'.','function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'home','style'=>array()));
            }
        }
        return $arr;
    }

    public function processSQLquery(array $stmtArr)
    {
        
    }
}
?>