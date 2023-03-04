<?php
declare(strict_types=1);

namespace Datapool\DataApps;

class Invoices{
	
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
		$this->entryTemplate=$arr['Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		return $this->arr;
	}

	public function job($vars){
		$this->arr['Datapool\Processing\CanvasProcessing']->runCanvasProcessingOnClass(__CLASS__);
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
			return array('Category'=>'Data','Emoji'=>'€','Label'=>'Invoices','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
		} else {
			$explorerArr=$this->arr['Datapool\Foundation\DataExplorer']->getDataExplorer(__CLASS__);
			$selector=$this->arr['Datapool\Tools\NetworkTools']->getSelectorFromPageState(__CLASS__);
			if (empty($selector)){
				$entryHtml='';
			} else {
				$entryHtml=$this->arr['Datapool\Foundation\Container']->container('Entry or entries','selectedView',$selector,array(),array());
			}
			$arr['page html']=str_replace('{{explorer}}',$explorerArr['explorerHtml'],$arr['page html']);
			$arr['page html']=str_replace('{{content}}',$explorerArr['contentHtml'].$entryHtml,$arr['page html']);
			return $arr;
		}
	}
	
	
	
}
?>