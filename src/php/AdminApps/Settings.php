<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\AdminApps;

class Settings{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Owner'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'{{Owner}}','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.')
								 );
    
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}

	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		return $this->arr;
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

	public function run($arr=TRUE){
		if ($arr===TRUE){
			return array('Category'=>'Admin','Emoji'=>'&#9783;','Label'=>'Settings','Read'=>'ADMIN_R','Class'=>__CLASS__);
		} else {
			$arr=$this->arr['SourcePot\Datapool\Foundation\Explorer']->getExplorer($arr,__CLASS__);
			$selector=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
			$html=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Entry or entries','selectedView',$selector,array(),array());
			$arr['page html']=str_replace('{{content}}',$html,$arr['page html']);
			return $arr;
		}
	}

	public function setSetting($callingClass,$callingFunction,$setting,$name='System',$isSystemCall=FALSE){
		$entry=array('Source'=>$this->entryTable,'Group'=>$callingClass,'Folder'=>$callingFunction,'Name'=>$name,'Type'=>$this->entryTable);
		if ($isSystemCall){$entry['Owner']='SYSTEM';}
		$entry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name','Type'),0,'',FALSE);
		$entry['Content']=$setting;
		$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,$isSystemCall);
		if (isset($entry['Content'])){return $entry['Content'];} else {return array();}
	}
	
	public function getSetting($callingClass,$callingFunction,$initSetting=array(),$name='System',$isSystemCall=FALSE){
		$entry=array('Source'=>$this->entryTable,'Group'=>$callingClass,'Folder'=>$callingFunction,'Name'=>$name,'Type'=>$this->entryTable);
		if ($isSystemCall){$entry['Owner']='SYSTEM';}
		$entry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name','Type'),0,'',FALSE);
		$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_MEMBER_R','ALL_MEMBER_R');
		$entry['Content']=$initSetting;
		$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,$isSystemCall);
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