<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Components;

class Home implements \SourcePot\Datapool\Interfaces\App{
	
	private $oc;
	
private $entryTable;
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
								 );
    
	public function __construct($oc){
		$this->oc=$oc;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}

	public function init(array $oc){
		$this->oc=$oc;
		$this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
	}

	public function getEntryTable(){
		return $this->entryTable;
	}

	public function getEntryTemplate(){
		return $this->entryTemplate;
	}

	public function unifyEntry($entry){
		$entry['Read']=intval($entry['Content']['Read access']);
		return $entry;
	}

	public function run(array|bool $arr=TRUE):array{
		if ($arr===TRUE){
			return array('Category'=>'Home','Emoji'=>'&#9750;','Label'=>'Home','Read'=>'ALL_R','Class'=>__CLASS__);
		} else {
			$html='';
			// Add content
			$selector=array('Source'=>$this->entryTable,'Group'=>'Homepage','Folder'=>$_SESSION['page state']['lngCode']);
			foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','EntryId',TRUE) as $section){
				$settings=array('method'=>'presentEntry','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder','presentEntry'=>__CLASS__.'::'.__FUNCTION__);
				$html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Section '.$section['EntryId'],'generic',$section,$settings,array('style'=>array('margin'=>'0')));
			}
			if (empty($section['rowCount'])){
				$width=360;
				$height=400;
				$html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->getLogo($width);
				$wrapperSetting=array('style'=>array('float'=>'none','padding'=>'10px','border'=>'none','width'=>$width,'margin'=>'10px auto','border'=>'1px dotted #999;'));
				$setting=array('hideReloadBtn'=>TRUE,'style'=>array('width'=>$width,'height'=>$height),'autoShuffle'=>TRUE,'getImageShuffle'=>'home');
				$selector=array('Source'=>$this->oc['SourcePot\Datapool\GenericApps\Multimedia']->getEntryTable());
				$html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Entry shuffle','getImageShuffle',$selector,$setting,$wrapperSetting);
			}
			// Add admin section
			if ($this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin()){
				$html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Section administration','generic',$selector,array('method'=>'adminHtml','classWithNamespace'=>__CLASS__),array());
			}
			$arr['toReplace']['{{content}}']=$html;
			return $arr;
		}
	}
	
	public function adminHtml($arr){
		$html='';
		// section control
		$sectionArr['selector']=array('Source'=>$this->entryTable,'Group'=>'Homepage','Folder'=>$_SESSION['page state']['lngCode'],'Name'=>'Page content');
		$sectionArr['selector']=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($sectionArr['selector'],'ALL_R','ADMIN_R');
		$contentStructure=array('Section title'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'Section content'=>array('method'=>'element','tag'=>'textarea','element-content'=>'','keep-element-content'=>TRUE,'excontainer'=>TRUE),
								'Section footer'=>array('method'=>'element','tag'=>'textarea','element-content'=>'','keep-element-content'=>TRUE,'excontainer'=>TRUE),
								'Section attachment'=>array('method'=>'element','tag'=>'input','type'=>'file','excontainer'=>TRUE),
								'Read access'=>array('method'=>'select','selected'=>65535,'options'=>array_flip($this->oc['SourcePot\Datapool\Foundation\Access']->access),'keep-element-content'=>TRUE,'excontainer'=>TRUE),
								);
		$sectionArr['canvasCallingClass']=$arr['callingFunction'];
		$sectionArr['contentStructure']=$contentStructure;
		$sectionArr['caption']='Web page administration: Each entry/row will be compiled into a section.';
		$sectionArr['callingClass']=__CLASS__;
		$sectionArr['callingFunction']=__FUNCTION__;
		$html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($sectionArr);
		$arr['html']=$html;
		return $arr;
	}
	
}
?>