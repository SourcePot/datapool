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
        // process data
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['search'])){
            $serachResult=$this->getSerachResukltHtml($formData['val']['query']);
        } else {
            $serachResult='';
        }
        // compile html
        $arr['html']=(empty($arr['html']))?'':$arr['html'];
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'input','type'=>'text','value'=>($formData['val']['query']??''),'placeholder'=>'Enter your query here...','key'=>array('query'),'excontainer'=>TRUE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'style'=>array()));
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'Search','key'=>array('search'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'style'=>array()));
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'style'=>array('float'=>'none','width'=>'max-content','margin'=>'5em auto')));
        $arr['html'].=$serachResult;
        return $arr;
    }

    private function getSerachResukltHtml(string $query):string{
        $entryCount=0;
        $selectors=array(array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Multimedia']->getEntryTable(),'Content'=>'%'.$query.'%'),
                         array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Forum']->getEntryTable(),'Content'=>'%'.$query.'%'),
                         array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Calendar']->getEntryTable(),'Content'=>'%'.$query.'%'),
                    );
        $html='';
        foreach($selectors as $selector){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Date',TRUE) as $entry){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>'.','function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'home','style'=>array()));
            }
        }
        return $html;
    }

    public function processSQLquery(array $stmtArr)
    {
        
    }
}
?>