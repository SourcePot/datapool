<?php
declare(strict_types=1);

namespace Datapool\Processing;

class DeleteEntries{
	
	private $arr;

	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );
	
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

	public function getEntryTable(){return $this->entryTable;}
	
	public function dataProcessor($action='info',$callingElementSelector=array()){
		// This method is the interface of this data processing class
		// The Argument $action selects the method to be invoked and
		// argument $callingElementSelector$ provides the entry which triggerd the action.
		// $callingElementSelector ... array('Source'=>'...', 'ElementId'=>'...', ...)
		// If the requested action does not exist the method returns FALSE and 
		// TRUE, a value or an array otherwise.
		$callingElement=$this->arr['Datapool\Foundation\Database']->entryByKey($callingElementSelector);
		switch($action){
			case 'run':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runDeleteEntries($callingElement,$testRunOnly=FALSE);
				}
				break;
			case 'test':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->runDeleteEntries($callingElement,$testRunOnly=TRUE);
				}
				break;
			case 'widget':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getDeleteEntriesWidget($callingElement);
				}
				break;
			case 'settings':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getDeleteEntriesSettings($callingElement);
				}
				break;
			case 'info':
				if (empty($callingElement)){
					return TRUE;
				} else {
					return $this->getDeleteEntriesInfo($callingElement);
				}
				break;
		}
		return FALSE;
	}

	private function getDeleteEntriesWidget($callingElement){
		return $this->arr['Datapool\Foundation\Container']->container('Delete entries','generic',$callingElement,array('method'=>'getDeleteEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
	}
	
	public function getDeleteEntriesWidgetHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// command processing
		$result=array();
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['run'])){
			$result=$this->runDeleteEntries($arr['selector'],FALSE);
		} else if (isset($formData['cmd']['test'])){
			$result=$this->runDeleteEntries($arr['selector'],TRUE);
		}
		// build html
		$btnArr=array('tag'=>'input','type'=>'submit','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$matrix=array();
		$btnArr['value']='Test';
		$btnArr['key']=array('test');
		$matrix['Commands']['Test']=$btnArr;
		$btnArr['value']='Run';
		$btnArr['key']=array('run');
		$matrix['Commands']['Run']=$btnArr;
		$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Deletion widget'));
		foreach($result as $caption=>$matrix){
			$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		}
		return $arr;
	}

	private function getDeleteEntriesSettings($callingElement){
		$html='';
		if ($this->arr['Datapool\Foundation\Access']->isContentAdmin()){
			$html.=$this->arr['Datapool\Foundation\Container']->container('Deleteing entries settings','generic',$callingElement,array('method'=>'getDeleteEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
		}
		return $html;
	}
	
	public function getDeleteEntriesSettingsHtml($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		return $arr;
	}
	
		
	public function runDeleteEntries($callingElement,$isTestRun=TRUE){
		$result=array('Statistics'=>array('Entry count'=>array('value'=>0),
										  'Deleted entries'=>array('value'=>0),
										  'Deleted files'=>array('value'=>0),
										  'Skipped entries'=>array('value'=>0),
										  )
					 );
		foreach($this->arr['Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $entry){
			$result['Statistics']['Entry count']['value']=$entry['rowCount'];
			break;
		}
		if ($isTestRun){
			foreach($this->arr['Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],FALSE,'Write') as $entry){
				$result['Statistics']['Deleted entries']['value']=$entry['rowCount'];
				break;
			}
			$result['Statistics']['Deleted files']['value']='?';
		} else {
			$this->arr['Datapool\Foundation\Database']->resetStatistic();
			$this->arr['Datapool\Foundation\Database']->deleteEntries($callingElement['Content']['Selector']);
			$statistic=$this->arr['Datapool\Foundation\Database']->getStatistic();
			$result['Statistics']['Deleted files']['value']=$statistic['removed'];
			$result['Statistics']['Deleted entries']['value']=$statistic['deleted'];
			$result['Statistics']['Skipped entries']['value']=$result['Statistics']['Entry count']['value']-$result['Statistics']['Deleted entries']['value'];
		}
		return $result;
	}
	

}
?>