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
	    
	public function __construct($oc){
		$this->oc=$oc;
	}

	public function init($oc){
		$this->oc=$oc;
	}

	/**
	* @return array The method runs the most overdue job, updates the job setting, adds generated webpage refrenced by the key "page html" to the provided array and returns the completed array.
	*/
	public function trigger($arr){
		// all jobs settings - remove non-existing job methods and add new job methods
		$jobs=array('due'=>array(),'undue'=>array());
		$allJobsSettingInitContent=array('Last run'=>time(),'Last run date'=>date('Y-m-d H:i:s'),'Min time in sec between each run'=>600,'Last run time consumption [ms]'=>0);
		$allJobsSetting=array('Source'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Job processing','Folder'=>'All jobs','Name'=>'Timing','Type'=>'array setting');
		$allJobsSetting=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($allJobsSetting,array('Source','Group','Folder','Name','Type'),0);
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
			$dueTime=time()-($allJobsSetting['Content'][$class]['Last run']+$allJobsSetting['Content'][$class]['Min time in sec between each run']);
			if ($dueTime>0){$jobs['due'][$class]=$dueTime;} else {$jobs['undue'][$class]=$dueTime;}
		}
		// get most overdue job
		$arr['page html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'h1','element-content'=>'Job processing triggered'));
		if (empty($jobs['due'])){
			$matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($jobs);
			$arr['page html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Jobs','keep-element-content'=>TRUE,'hideKeys'=>TRUE));	
		} else {
			arsort($jobs['due']);
			reset($jobs['due']);
			$dueJob=key($jobs['due']);
			$dueMethod=$allJobsSetting['Content'][$dueJob]['method'];
			// job var space and run job
			$jobVars=array('Source'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),
						   'Group'=>'Job processing','Folder'=>'Var space',
						   'Name'=>$dueJob,
						   'Type'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable()
						   );
			$jobVars=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($jobVars,array('Source','Group','Folder','Name','Type'),'0','',FALSE);
			$jobVars=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($jobVars,'ADMIN_R','ADMIN_R');
			$jobVars=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($jobVars,TRUE);
			$jobStartTime=hrtime(TRUE);
			$this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
			$jobVars['Content']=$this->oc[$dueJob]->$dueMethod($jobVars['Content']);
			$jobStatistic=$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
			$allJobsSetting['Content'][$dueJob]['Last run']=time();
			$allJobsSetting['Content'][$dueJob]['Last run date']=date('Y-m-d H:i:s');
			$allJobsSetting['Content'][$dueJob]['Last run time consumption [ms]']=round((hrtime(TRUE)-$jobStartTime)/1000000);
			// update job vars
			$jobVars=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($jobVars,TRUE);
			// show results
			$matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($allJobsSetting['Content'][$dueJob]);
			$arr['page html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Job done','keep-element-content'=>TRUE,'hideKeys'=>TRUE));
			$matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($jobStatistic);
			$arr['page html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Job statistic','keep-element-content'=>TRUE,'hideKeys'=>TRUE));
		}
		$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($allJobsSetting,TRUE);
		return $arr;
	}

}
?>