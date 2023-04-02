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

namespace SourcePot\Datapool\DataApps;

class Unycom{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array('Folder'=>array('index'=>'FOLDER_IND','type'=>'VARCHAR(255)','value'=>'...','Description'=>'Second level ordering criterion'),
								 'Name'=>array('index'=>'NAME_IND','type'=>'VARCHAR(1024)','value'=>'...','Description'=>'Third level ordering criterion'),
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
		$vars=$this->arr['SourcePot\Datapool\Processing\CanvasProcessing']->runCanvasProcessingOnClass(__CLASS__,FALSE,$vars);
		return $vars;
	}

	public function getEntryTable(){
		return $this->entryTable;
	}
	
	public function getEntryTemplate(){
		return $this->entryTemplate;
	}

	public function run($arr=TRUE){
		$html='';
		if ($arr===TRUE){
			return array('Category'=>'Data','Emoji'=>'€','Label'=>'UNYCOM','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
		} else {
			$explorerArr=$this->arr['SourcePot\Datapool\Foundation\DataExplorer']->getDataExplorer(__CLASS__);
			$html.=$explorerArr['contentHtml'];
			if (isset($explorerArr['selector']['Source'])){
				if (isset($this->arr['source2class'][$explorerArr['selector']['Source']])){
					$classWithNamespace=$this->arr['source2class'][$explorerArr['selector']['Source']];
					$pageState=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageState($classWithNamespace);
					$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Entry or entries','selectedView',$pageState,array(),array());
				}
			}
			$arr['page html']=str_replace('{{explorer}}',$explorerArr['explorerHtml'],$arr['page html']);
			$arr['page html']=str_replace('{{content}}',$html,$arr['page html']);
			return $arr;
		}
	}
	
	
	
}
?>