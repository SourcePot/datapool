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
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
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

	public function unifyEntry($entry){
		return $entry;
	}

	public function run($arr=TRUE){
		if ($arr===TRUE){
			return array('Category'=>'Home','Emoji'=>'&#9750;','Label'=>'Home','Read'=>'ALL_R','Class'=>__CLASS__);
		} else {
			$html='';
			// Add content
			$sectionSelector=array('Source'=>$this->entryTable,'Group'=>'Homepage','Folder'=>$_SESSION['page state']['lngCode']);
			foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($sectionSelector) as $section){
				$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Section '.$section['EntryId'],'selectedView',$section,array(),array());
			}
			// Add admin section
			if ($this->arr['SourcePot\Datapool\Foundation\Access']->isAdmin()){
				$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Section administration','generic',$sectionSelector,array('method'=>'adminHtml','classWithNamespace'=>__CLASS__),array());
			}
			$arr['page html']=str_replace('{{content}}',$html,$arr['page html']);
			return $arr;
		}
	}
	
	public function adminHtml($arr){
		$html='';
		// section control
		$sectionArr=array('Source'=>$this->entryTable,'Group'=>'Homepage','Folder'=>$_SESSION['page state']['lngCode'],'Name'=>'Page content');
		$sectionArr=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($sectionArr,'ALL_R','ADMIN_R');
		$contentStructure=array('Section title'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Section content'=>array('htmlBuilderMethod'=>'element','tag'=>'textarea','element-content'=>'Add your section text here...','keep-element-content'=>TRUE,'excontainer'=>TRUE),
								'Section attachment'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'file','excontainer'=>TRUE),
								);
		$sectionArr['canvasCallingClass']=$arr['callingFunction'];
		$sectionArr['contentStructure']=$contentStructure;
		$sectionArr['caption']='Web page administration: Each entry/row will be compiled into a section.';
		$sectionArr['callingClass']=__CLASS__;
		$sectionArr['callingFunction']=__FUNCTION__;
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($sectionArr);
		$arr['html']=$html;
		return $arr;
	}
	
}
?>