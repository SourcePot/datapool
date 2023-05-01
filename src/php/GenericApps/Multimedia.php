<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\GenericApps;

class Multimedia{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );

	public $definition=array('EntryId'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@Write'=>0));

	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}

	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
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
		// This function makes class specific corrections before the entry is inserted or updated.
		return $entry;
	}

	public function run($arr=TRUE){
		if ($arr===TRUE){
			return array('Category'=>'Apps','Emoji'=>'&#10063;','Label'=>'Multimedia','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
		} else {
			$arr=$this->arr['SourcePot\Datapool\Foundation\Explorer']->getExplorer($arr,__CLASS__);
			$selector=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
			$settings['columns']=array(array('Column'=>'Date','Filter'=>''),array('Column'=>'Name','Filter'=>''),array('Column'=>'preview','Filter'=>''));
			$html=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Multimedia entries','selectedView',$selector,$settings,array());
			$arr['toReplace']['{{content}}']=$html;
			return $arr;
		}
	}
		
}
?>