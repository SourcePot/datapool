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

class Queue
{
    
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

    /**
    * Adds temporary entries to the queue
    *
    * @param string    $callingClass    The calling method's class-name
    * @param string    $step            Selected processing step
    * @param string    $entry           The entry to be stored, The keys 'Source','Group' and 'EntryId' will be set by the method
    * @param string    $attachment      Optional file name with location for file to be attached
    * @return array    $entry           The entry stored
    */
    public function enqueueEntry(string $callingClass,int|string $step,array $entry):array
    {
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $hrTimeArr=hrtime(FALSE);
        $callingClass=explode('\\',$callingClass);
        $entryTemplate=array('Source'=>$this->entryTable);
        $entryTemplate['Group']=array_pop($callingClass).'||'.strval($step);
        $entry['EntryId']=str_pad(strval($hrTimeArr[0]),32,'0',STR_PAD_LEFT).'.'.$hrTimeArr[1].'||'.$entryTemplate['Group'];
        $entry=array_merge($entry,$entryTemplate);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE,FALSE,FALSE);
        $this->updateQueueMeta($entry);
        return $entry;
    }

    /**
    * Returns and removes temporary entries from the queue
    *
    * @param string    $callingClass    The calling method's class-name
    * @param string    $step            Selected processing step
    * @return array    $entry           The entry stored
    */
    public function dequeueEntry(string $callingClass,int $step):\Generator
    {
        $entryFound=FALSE;
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $callingClass=explode('\\',$callingClass);
        $class=array_pop($callingClass);
        $selector=array('Source'=>$this->entryTable,'Group'=>$class.'||'.$step);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read','EntryId',TRUE) as $entry){
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($entry,TRUE);
            $this->updateQueueMeta($entry);
            $entryFound=TRUE;
            yield $entry;
        }
        // update meta entry
        if ($entryFound===FALSE){
            $metaEntry=array('Source'=>$this->entryTable,'Group'=>'meta','Folder'=>$class,'Name'=>str_pad(strval($step),5,'0',STR_PAD_LEFT));
            $metaEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($metaEntry,array('Group','Folder','Name'),0);
            $metaEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($metaEntry,TRUE);
            if (!empty($metaEntry['Content']['Queue size'])){
                $metaEntry['Content']['Queue size']=0;
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($metaEntry,TRUE);
            }
            yield FALSE;
        }
    }

    /**
    * Update and return the meta entry for the queue selected by parameter entry
    *
    * @param array      $entry    Queue entry which was added or removed
    * @return array     The queue meta data related to the $entry
    */
    public function updateQueueMeta(array $entry):array
    {
        // get and reset statistics
        $statistic=$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        // get meta entry
        $entryPropArr=explode('||',$entry['Group']);
        $class=array_shift($entryPropArr);
        $name=str_pad(array_shift($entryPropArr),5,'0',STR_PAD_LEFT);
        $metaEntry=array('Source'=>$this->entryTable,'Group'=>'meta','Folder'=>$class,'Name'=>$name,'Content'=>array('Init timestamp'=>time(),'Update timestamp'=>time(),'Queue max'=>FALSE,'Queue size'=>0));
        $metaEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($metaEntry,array('Group','Folder','Name'),0);
        $metaEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($metaEntry,TRUE);        
        // updatee entry meta
        $metaEntry['Content']['Update timestamp']=time();
        $metaEntry['Content']['Queue size']+=$statistic['inserted'];
        $metaEntry['Content']['Queue size']-=$statistic['deleted'];
        if ($metaEntry['Content']['Queue max']===FALSE || $metaEntry['Content']['Queue max']<$metaEntry['Content']['Queue size']){
            $metaEntry['Content']['Queue max']=$metaEntry['Content']['Queue size'];
        }
        $metaEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($metaEntry,TRUE);
        return $metaEntry;
    }

    /**
    * Resets the selected queue
    *
    * @param string     $callingClass    The calling method's class-name
    */
    public function clearQueue(string $callingClass)
    {
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $callingClass=explode('\\',$callingClass);
        $class=array_pop($callingClass);
        $entriesSelector=array('Source'=>$this->entryTable,'Group'=>$class.'||%');
        $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($entriesSelector,TRUE);
        $metaEntriesSelector=array('Source'=>$this->entryTable,'Group'=>'meta','Folder'=>$class);
        $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($metaEntriesSelector,TRUE);
        return $this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
    }

    /**
    * Returns the selected queue meta data
    *
    * @param string     $callingClass    The calling method's class-name
    * @return array     The queue meta data array related to the $entry, with key 'Meter': html meter tags providing progrss; 'Current step': providing the current processing step and 'All done' is true if all steps are completed
    */
    public function getQueueMeta(string $callingClass,array $stepsDescription=array(0=>'Start',1=>'Processing',2=>'Finalizing')):array
    {
        ksort($stepsDescription);
        // get processed steps
        $callingClass=explode('\\',$callingClass);
        $metaEntriesSelector=array('Source'=>$this->entryTable,'Group'=>'meta','Folder'=>array_pop($callingClass));
        $metaArr=array('class'=>$metaEntriesSelector['Folder'],'Meta entries'=>array(),'Empty'=>TRUE,'All done'=>FALSE);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($metaEntriesSelector,TRUE,'Read','Name',TRUE) as $metaEntry){
            $metaArr['Empty']=FALSE;
            $initTimeStamp=$metaEntry['Content']['Init timestamp'];
            $metaArr['Meta entries'][ $initTimeStamp]=$metaEntry['Content'];
            $metaArr['Meta entries'][ $initTimeStamp]['step']=intval($metaEntry['Name']);
            foreach($stepsDescription as $stepIndex=>$description){
                if (intval($stepIndex)!==intval($metaEntry['Name'])){continue;}
                $metaArr['Meta entries'][ $initTimeStamp]['description']=$description;
                unset($stepsDescription[$stepIndex]);
                break;
            }
        }
        // add unprocessed steps
        $initTimeStamp=$initTimeStamp??time();
        foreach($stepsDescription as $stepIndex=>$description){
            $initTimeStamp++;
            $metaArr['Meta entries'][ $initTimeStamp]=array('Init timestamp'=>$initTimeStamp,'Update timestamp'=>time(),'Queue max'=>0,'Queue size'=>0);
            $metaArr['Meta entries'][ $initTimeStamp]['step']=intval($stepIndex);
            $metaArr['Meta entries'][ $initTimeStamp]['description']=$description;
        }
        // create result
        ksort($metaArr['Meta entries']);
        $queueMatrix=array();
        foreach($metaArr['Meta entries'] as $timestamp=>$stepArr){
            if ($stepArr['Queue size']===0 && $stepArr['Queue max']===0){
                $percentage=0;
            } else if ($stepArr['Queue size']===0 && $stepArr['Queue max']>0){
                $percentage=100;
            } else {
                $percentage=round(100*($stepArr['Queue max']-$stepArr['Queue size'])/$stepArr['Queue max'],2);
            }
            if (!isset($metaArr['Current step']) && $stepArr['Queue size']>0){$metaArr['Current step']=$stepArr['step'];}
            // create meta html meter
            $queueMatrix[$timestamp]['Step']=$stepArr['step'];
            $queueMatrix[$timestamp]['Description']=(isset($stepArr['description']))?$stepArr['description']:'';
            $queueMatrix[$timestamp]['Done']=($stepArr['Queue max']-$stepArr['Queue size']).' / '.$stepArr['Queue max'];
            $queueMatrix[$timestamp]['Meter']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'meter','min'=>0,'max'=>100,'value'=>$percentage,'title'=>$percentage.'%','element-content'=>''));
        }
        if (!isset($metaArr['Current step'])){
            // nothing left in the queue -> set 'Current step' to start
            if ($metaArr['Empty']===FALSE){$metaArr['All done']=TRUE;}
            $metaArr['Current step']=0;
        }
        $metaArr['Meter']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$queueMatrix,'hideHeader'=>False,'hideKeys'=>True,'keep-element-content'=>TRUE,'caption'=>'Processing queues'));
        return $metaArr;
    }

}
?>