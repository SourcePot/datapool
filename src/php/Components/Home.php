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

namespace SourcePot\Datapool\Components;

class Home{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array();
    
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

	public function unifyEntry($entry){
		return $entry;
	}

	public function run($arr=TRUE){
		if ($arr===TRUE){
			return array('Category'=>'Home','Emoji'=>'&#9750;','Label'=>'Home','Read'=>'ALL_R','Class'=>__CLASS__);
		} else {
			$entry=array('Source'=>$this->entryTable,'Group'=>'Homepage','Folder'=>$_SESSION['page state']['lngCode'],'Name'=>'Page content','Type'=>$this->entryTable.' html');
			$entry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name'),'0','',FALSE);
			$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ADMIN_R');
			$entry['Content']=array('Contact'=>'','Legal'=>'');
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,TRUE);
			if ($this->arr['SourcePot\Datapool\Foundation\Access']->isAdmin()){
				$editorHtml=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Entry editor','entryEditor',$entry,array(),array());
			} else {
				$editorHtml='';
			}
			$viewerHtml=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Entry or entries','selectedView',$entry,array(),array());
			$arr['page html']=str_replace('{{content}}',$viewerHtml.$editorHtml,$arr['page html']);
			return $arr;
		}
	}
		
}
?>