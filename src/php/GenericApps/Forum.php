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

class Forum implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'All members can read forum entries'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'All admins can edit forum entries'),
                                 );
    
    public $definition=array('Content'=>array('Message'=>array('@tag'=>'textarea','@placeholder'=>'e.g. This was a great day 😁','@rows'=>'10','@cols'=>'50','@cols'=>'50','@minlength'=>'1','@default'=>'','@filter'=>FILTER_DEFAULT,'@id'=>'newforumentry','@style'=>array('font-size'=>'1.2rem')),
                                              '@hideCaption'=>FALSE
                                             ),
                             'Attachment'=>array('@tag'=>'input','@type'=>'file','@default'=>'','@hideKeys'=>TRUE),
                             'Preview'=>array('@function'=>'preview','@Write'=>'ADMIN_R','@hideKeys'=>TRUE),
                             '@hideHeader'=>TRUE,
                             );
                            
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
        // complete defintion
        $this->definition['Send']=array('@tag'=>'button','@key'=>array('save'),'@element-content'=>'Send','@hideKeys'=>TRUE);
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
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

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Apps','Emoji'=>'&#9993;','Label'=>'Forum','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
        } else {
            $arr=$this->addYearSelector2menu($arr);
            $entryHtml=$this->newEntryHtml();
            $entryHtml.=$this->loadForumEntries();
            $arr['toReplace']['{{content}}']=$entryHtml;
            return $arr;
        }
    }
    
    private function addYearSelector2menu($arr){
        $selectedYear=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'Year','');
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['select'])){
            $selectedYear=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'Year',$formData['val']['Year']);
        }
        // get selector
        $options=array(''=>'All');
        $startYear=intval(date('Y'));
        for($year=$startYear;$year>$startYear-10;$year--){
            $options[$year]='Year '.$year;
        }
        $arr['toReplace']['{{firstMenuBarExt}}']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$options,'selected'=>$selectedYear,'key'=>array('Year'),'hasSelectBtn'=>TRUE,'class'=>'menu','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
        return $arr;
    }
    
    private function newEntryHtml(){
        $draftSelector=array('Source'=>$this->entryTable,
                          'Folder'=>'Draft',
                          'Owner'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),
                          );
        $draftSelector=$this->oc['SourcePot\Datapool\Foundation\Database']->addType2entry($draftSelector);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($draftSelector) as $entry){
            if ($entry['isSkipRow']){continue;}
            $forumEntry=$entry;
        }
        if (empty($forumEntry)){
            $forumEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($draftSelector,TRUE);
        } 
        $html=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($forumEntry,FALSE);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Emojis for '.__FUNCTION__,'generic',$draftSelector,array('method'=>'emojis','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder','target'=>'newforumentry'),array('style'=>array('margin'=>'0','border'=>'none')));
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'&#9993;','class'=>'forum'));
        return $html;
    }
    
    private function loadForumEntries(){
        $forumSelector=array('Source'=>$this->entryTable,'Folder'=>'Sent');
        $selectedYear=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'Year','');
        if (!empty($selectedYear)){$forumSelector['Date']=$selectedYear.'-%';}
        $html='';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($forumSelector,FALSE,'Read','Date',FALSE) as $entry){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>'<br/>','keep-element-content'=>TRUE,'function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'forum','style'=>array('clear'=>'none')));
        }
        return $html;
    }
    
    public function unifyEntry($forumEntry){
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $forumEntry['Group']=$user['Privileges'];
        $forumEntry['Folder']='Sent';
        $forumEntry['Date']=(empty($forumEntry['Date']))?($this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime()):$forumEntry['Date'];
        $forumEntry['Name']=(empty($forumEntry['Content']['Message']))?'':mb_substr($forumEntry['Content']['Message'],0,30);
        return $forumEntry;
    }
    
}
?>