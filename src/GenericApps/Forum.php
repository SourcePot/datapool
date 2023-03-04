<?php
declare(strict_types=1);

namespace Datapool\GenericApps;

class Forum{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'All members can read forum entries'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'All admins can edit forum entries'),
								 );
	
	public $definition=array('Content'=>array('Message'=>array('@tag'=>'textarea','@rows'=>'10','@cols'=>'50','@cols'=>'50','@minlength'=>'1','@default'=>'','@filter'=>FILTER_DEFAULT,'@id'=>'newforumentry','@style'=>array('font-size'=>'1.3em')),
											 ),
							 'Attachment'=>array('@tag'=>'input','@type'=>'file','@default'=>''),
							 'Preview'=>array('@function'=>'preview','@Write'=>'ADMIN_R'),
							);
	
	private $menuDef=array('Category'=>'Apps','Emoji'=>'&#9993;','Label'=>'Forum','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);

	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}

	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		// complete defintion
		$this->definition['Send']=array('@tag'=>'button','@key'=>'save','@element-content'=>'Send');
		$arr['Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
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
			return $this->menuDef;
		} else {
			$entryHtml=$this->newEntryHtml();
			$entryHtml.=$this->loadForumEntries();
			$arr['page html']=str_replace('{{content}}',$entryHtml,$arr['page html']);
			return $arr;
		}
	}
	
	private function newEntryHtml(){
		$draftSelector=array('Source'=>$this->entryTable,
						  'Folder'=>'Draft',
						  'Type'=>$this->entryTable.' entry',
						  'Owner'=>$_SESSION['currentUser']['ElementId'],
						  );
		foreach($this->arr['Datapool\Foundation\Database']->entryIterator($draftSelector) as $entry){
			if ($entry['isSkipRow']){continue;}
			$forumEntry=$entry;
		}
		if (empty($forumEntry)){
			$forumEntry=$this->arr['Datapool\Foundation\Database']->addEntryDefaults($draftSelector);
		} 
		$definition=$this->arr['Datapool\Foundation\Definitions']->getDefinition($forumEntry);
		$definition['hideKeys']=TRUE;
		$html=$this->arr['Datapool\Foundation\Definitions']->definition2form($definition,$forumEntry);
		$html.=$this->arr['Datapool\Foundation\Container']->container('Emojis for '.__FUNCTION__,'generic',$draftSelector,array('method'=>'emojis','classWithNamespace'=>'Datapool\Tools\HTMLbuilder','target'=>'newforumentry'),array('style'=>array('margin-top'=>'50px;')));
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'&#9993;','style'=>array('background-color'=>'#888','min-width'=>'100%','margin'=>'0')));
		return $html;
	}
	
	private function loadForumEntries(){
		$html='';
		$forumSelector=array('Source'=>$this->entryTable,'Folder'=>'Sent');
		foreach($this->arr['Datapool\Foundation\Database']->entryIterator($forumSelector,FALSE,'Read','Date',FALSE) as $entry){
			$html.=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$entry['Date'],'function'=>'loadEntry','source'=>$entry['Source'],'element-id'=>$entry['ElementId'],'class'=>'forum'));
		}
		return $html;
	}
		
	public function unifyEntry($forumEntry){
		$forumEntry['Group']=$_SESSION['currentUser']['Privileges'];
		$forumEntry['Folder']='Sent';
		$forumEntry['Date']=$this->arr['Datapool\Tools\StrTools']->getDateTime();
		$forumEntry['Name']=substr($forumEntry['Content']['Message'],0,30);
		return $forumEntry;
	}
	
}
?>