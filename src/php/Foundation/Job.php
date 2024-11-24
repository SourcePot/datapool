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

class Job{
    
    private $oc;
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    /**
    * @return array The method runs the most overdue job, updates the job setting, adds generated webpage refrenced by the key "page html" to the provided array and returns the completed array.
    */
    public function trigger(array $arr):array
    {
        $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        // all jobs settings - remove non-existing job methods and add new job methods
        $arr['run']=(isset($arr['run']))?$arr['run']:'';
        $jobs=array('due'=>array(),'undue'=>array());
        $allJobsSettingInitContent=array('Last run'=>time(),'Min time in sec between each run'=>600,'Last run time consumption [ms]'=>0);
        $allJobsSettingInitContent['Last run date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','',$pageTimeZone);
        $allJobsSetting=array('Source'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Job processing','Folder'=>'All jobs','Name'=>'Timing','Owner'=>'SYSTEM');
        $allJobsSetting=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($allJobsSetting,array('Source','Group','Folder','Name'),0);
        $allJobsSetting=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($allJobsSetting,'ALL_R','ADMIN_R');
        $allJobsSetting=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($allJobsSetting,TRUE);
        $allJobsSettingContent=$allJobsSetting['Content'];
        $allJobsSetting['Content']=array();
        foreach($this->oc['SourcePot\Datapool\Root']->getRegisteredMethods('job') as $class=>$initContent){
            $initContent=array_merge($allJobsSettingInitContent,$initContent);
            if (isset($allJobsSettingContent[$class])){
                $allJobsSetting['Content'][$class]=$allJobsSettingContent[$class];
            } else {
                $allJobsSetting['Content'][$class]=$initContent;
            }
            ksort($allJobsSetting['Content'][$class]);
            if (empty($arr['run'])){
                // get job based on which is overdue
                $dueTime=time()-($allJobsSetting['Content'][$class]['Last run']+$allJobsSetting['Content'][$class]['Min time in sec between each run']);
                if ($dueTime>0){
                    $jobs['due'][$class]=$dueTime;
                } else {
                    $jobs['undue'][$class]=$dueTime;
                }
            } else {
                // specific job requested
                if ($class==$arr['run']){
                    $jobs['due'][$class]=10;
                } else {
                    $jobs['undue'][$class]=0;
                }
            }
        }
        // get most overdue job
        $arr['page html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'h1','element-content'=>'Job processing triggered'));
        if (empty($jobs['due'])){
            $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($jobs);
            $arr['page html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Jobs','keep-element-content'=>TRUE,'hideKeys'=>TRUE));
            $arr['jobVars']=array(); 
        } else {
            arsort($jobs['due']);
            reset($jobs['due']);
            $dueJob=key($jobs['due']);
            $dueMethod=$allJobsSetting['Content'][$dueJob]['method'];
            // job var space and run job
            $jobVars=array('Source'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),
                           'Group'=>'Job processing','Folder'=>'Var space',
                           'Name'=>$dueJob,
                           'Type'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),
                           'Content'=>array(),
                           );
            $jobVars=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($jobVars,array('Source','Group','Folder','Name'),'0','',FALSE);
            $jobVars=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($jobVars,'ADMIN_R','ADMIN_R');
            $jobVars=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($jobVars,TRUE);
            $jobStartTime=hrtime(TRUE);
            $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
            $jobVars['Content']=$this->oc[$dueJob]->$dueMethod($jobVars['Content']);
            $jobStatistic=$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
            $allJobsSetting['Content'][$dueJob]['Last run']=time();
            $allJobsSetting['Content'][$dueJob]['Last run date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','',$pageTimeZone);
            $allJobsSetting['Content'][$dueJob]['Last run time consumption [ms]']=round((hrtime(TRUE)-$jobStartTime)/1000000);
            // update job vars
            $jobVars=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($jobVars,TRUE);
            // show results
            $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($allJobsSetting['Content'][$dueJob]);
            $arr['page html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Job done','keep-element-content'=>TRUE,'hideKeys'=>TRUE));
            $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($jobStatistic);
            $arr['page html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Job statistic','keep-element-content'=>TRUE,'hideKeys'=>TRUE));
            ksort($allJobsSetting['Content'][$dueJob]);
            $arr['jobVars']=$jobVars['Content'];
        }
        $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($allJobsSetting,TRUE);
        return $arr;
    }
    
    public function getJobOverview(array $arr):array
    {
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['selector']['Folder']='All jobs';
        $arr['selector']['Name']='Timing';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($arr['selector'],TRUE,'Read') as $jobEntry){
            if ($jobEntry['isSkipRow']){continue;}
            break;
        }
        if (!empty($jobEntry)){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$jobEntry['Content'],'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Overview jobs','class'=>'max-content'));
        }
        return $arr;
    }

}
?>