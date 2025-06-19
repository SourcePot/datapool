<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Forum;

class Forum implements \SourcePot\Datapool\Interfaces\App{
    
    private const APP_ACCESS='ALL_MEMBER_R';
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'All members can read forum entries'],
                            'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'All admins can edit forum entries'],
                            ];
    
    public $definition=['Content'=>['Message'=>['@tag'=>'textarea','@placeholder'=>'e.g. This was a great day 😁','@rows'=>'10','@cols'=>'50','@cols'=>'50','@minlength'=>'1','@default'=>'','@filter'=>FILTER_DEFAULT,'@id'=>'newforumentry','@hideKeys'=>TRUE],
                                              '@hideCaption'=>FALSE,
                                ],
                        'Attachment'=>['@tag'=>'input','@type'=>'file','@default'=>'','@hideKeys'=>TRUE],
                        'Preview'=>['@function'=>'preview','@Write'=>'ADMIN_R','@hideKeys'=>TRUE],
                        '@hideHeader'=>TRUE,
                        ];
                            
    public function __construct($oc)
    {
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
        $this->definition['Send']=['@tag'=>'button','@key'=>['save'],'@element-content'=>'Send','@hideKeys'=>TRUE];
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return ['Category'=>'Forum','Emoji'=>'&#9993;','Label'=>'Forum','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            $arr=$this->addYearSelector2menu($arr);
            $entryHtml=$this->newEntryHtml();
            $entryHtml.=$this->loadForumEntries();
            $arr['toReplace']['{{content}}']=$entryHtml;
            return $arr;
        }
    }
    
    private function addYearSelector2menu($arr)
    {
        $selectedYear=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'Year','');
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['select'])){
            $selectedYear=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'Year',$formData['val']['Year']);
        }
        // get selector
        $options=[''=>'All'];
        $startYear=intval(date('Y'));
        for($year=$startYear;$year>$startYear-10;$year--){
            $options[$year]='Year '.$year;
        }
        $arr['toReplace']['{{firstMenuBarExt}}']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['options'=>$options,'selected'=>$selectedYear,'key'=>['Year'],'hasSelectBtn'=>TRUE,'class'=>'menu','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
        return $arr;
    }
    
    private function newEntryHtml()
    {
        $draftSelector=['Source'=>$this->entryTable,'Folder'=>'Draft','Owner'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),];
        $draftSelector=$this->oc['SourcePot\Datapool\Foundation\Database']->addType2entry($draftSelector);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($draftSelector) as $entry){
            if ($entry['isSkipRow']){continue;}
            $forumEntry=$entry;
        }
        if (empty($forumEntry)){
            $forumEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($draftSelector,TRUE);
        }
        $forumEntry['File upload extract archive']=FALSE;
        $forumEntry['File upload extract email parts']=FALSE;
        $html=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($forumEntry,FALSE);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Emojis for '.__FUNCTION__,'generic',$draftSelector,['method'=>'emojis','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder','target'=>'newforumentry'],['style'=>['clear'=>'both','margin'=>'1rem','width'=>'auto']]);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'&#9993;','class'=>'forum']);
        return $html;
    }
    
    private function loadForumEntries()
    {
        $forumSelector=['Source'=>$this->entryTable,'Folder'=>'Sent'];
        $selectedYear=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'Year','');
        if (!empty($selectedYear)){$forumSelector['Date']=$selectedYear.'-%';}
        $html='';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($forumSelector,FALSE,'Read','Date',FALSE) as $entry){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>'@','keep-element-content'=>TRUE,'function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'forum','style'=>['clear'=>'none']]);
        }
        return $html;
    }
    
    public function unifyEntry($forumEntry)
    {
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $forumEntry['Group']=$user['Privileges'];
        $forumEntry['Folder']='Sent';
        $forumEntry['Date']=(empty($forumEntry['Date']))?($this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime()):$forumEntry['Date'];
        $forumEntry['Name']=(empty($forumEntry['Content']['Message']))?'':mb_substr($forumEntry['Content']['Message'],0,30);
        return $forumEntry;
    }
    
}
?>