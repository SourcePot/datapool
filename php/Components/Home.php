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

	public $definition=array('Content'=>array('Contact'=>array('@tag'=>'textarea','@default'=>'Contact','@style'=>'height:200px;min-width:320px;','@keep-element-content'=>TRUE,'@Write'=>'ADMIN_R','@Read'=>'ALL_R'),
											 'Legal'=>array('@tag'=>'textarea','@default'=>'Legal','@style'=>'height:200px;min-width:320px;','@keep-element-content'=>TRUE,'@Write'=>'ADMIN_R','@Read'=>'ALL_R'),
											 ),
							'hideKeys'=>TRUE,
							'skipEmptyRows'=>TRUE,
							'hideCaption'=>TRUE
							);
    
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}

	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		// check database user entry definition 
		$this->definition['save']=array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save','@Write'=>'ADMIN_R','@Read'=>'ADMIN_R');
		$arr['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
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
		$entry=$this->arr['SourcePot\Datapool\Foundation\Definitions']->definition2entry($this->definition,$entry);
		return $entry;
	}

	public function run($arr=TRUE){
		if ($arr===TRUE){
			return array('Category'=>'Home','Emoji'=>'&#9750;','Label'=>'Home','Read'=>'ALL_R','Class'=>__CLASS__);
		} else {
			$htmlContent='';
			$htmlContent.=$this->contactHtml();
			$arr['page html']=str_replace('{{content}}',$htmlContent,$arr['page html']);
			return $arr;
		}
	}
	
	private function contactHtml(){
		$entry=array('Source'=>$this->entryTable,'Group'=>'Imprint','Folder'=>$_SESSION['page state']['lngCode'],'Name'=>'Imprint text','Type'=>'home','Owner'=>'SYSTEM');
		$entry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addElementId($entry,array('Source','Group','Folder','Name'),0);
		$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ADMIN_R');
		$definition=$this->arr['SourcePot\Datapool\Foundation\Definitions']->getDefinition($entry);
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->isAdmin()){$definition['hideKeys']=FALSE;}
		$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryByKeyCreateIfMissing($entry,TRUE);
		$html=$this->arr['SourcePot\Datapool\Foundation\Definitions']->definition2form($definition,$entry);
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE));
		return $html;
	}
	
}
?>