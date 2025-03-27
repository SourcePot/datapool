<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Chat implements \SourcePot\Datapool\Interfaces\HomeApp{
    
    private $oc;

    private $entryTable='';
    private $entryTemplate=[];

    private $currentUser=[];
    
    public function __construct(array $oc)
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
        $this->currentUser=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
    }
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
    
    public function newEntryHtml(array $arr):array
    {
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        // create new chat entry
        $chatEntry=['Source'=>$this->entryTable,'Owner'=>'SYSTEM','Group'=>$this->currentUser['EntryId'],'Name'=>str_pad(strval(time()),20,'0',STR_PAD_LEFT)];
        $chatEntry['Folder']=($formData['val']['selectedUser']??'').'|'.$this->currentUser['EntryId'];
        $chatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($chatEntry);
        if (isset($formData['cmd']['send']) && !empty($formData['val']['new'])){
            $chatEntry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','P10D');
            $chatEntry['Content']=['Message'=>$formData['val']['new'],];
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($chatEntry,TRUE);
            $formData['val']['selectedUser']='';
        }
        $arr['html']=$arr['html']??'';
        // user selector
        $selectArr=['options'=>[''=>''],'selected'=>$formData['val']['selectedUser']??'','key'=>['selectedUser'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
        $userSelctor=['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId!'=>'online_%','EntryId!!'=>'%-oneTimeLink'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($userSelctor,TRUE,'Read','Name',FALSE,FALSE,FALSE) as $user){
            if ($user['EntryId']===$this->currentUser['EntryId']){continue;}
            if ((intval($user['Privileges']) & intval($this->currentUser['Privileges']))==0){continue;}
            $selectArr['options'][$user['EntryId']]=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($user,6);
        }
        $matrix=[];
        $matrix['To']['value']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        // New chat entry
        if (!empty($formData['val']['selectedUser'])){
            $matrix['Msg']['value']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'textarea','element-content'=>$formData['val']['new']??'','placeholder'=>'e.g. This was a great day 😁','keep-element-content'=>TRUE,'id'=>$chatEntry['EntryId'],'key'=>['new'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']]);
            $matrix['Emoji']['value']=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Emojis for new entry '.$chatEntry['EntryId'],'generic',$chatEntry,['method'=>'emojis','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder','target'=>$chatEntry['EntryId']]);
            $matrix['']['value']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'button','element-content'=>'Send','key'=>['send'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']]);
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'']);
        return $arr;
    }

    public function getChat(array $arr):array
    {
        $arr['html']=$arr['html']??'';
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($arr['selector'],TRUE,'Read','Name',FALSE,FALSE,FALSE) as $chat){
            $timeDiff=$this->oc['SourcePot\Datapool\GenericApps\Calendar']->getTimeDiff('@'.time(),'@'.strval(intval($chat['Name'])));
            $chatAuthor=['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$chat['Group']];
            $chatAuthor=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($chatAuthor,TRUE);
            $chatAuthorName=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($chatAuthor,1);
            if ($chat['Group']===$this->currentUser['EntryId']){
                $pair=explode('|',$chat['Folder']);
                $to=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$pair[0]],TRUE);
                $toName=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($to,1);
                $chatAuthorName=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng('You');
                $chatAuthorName.=' &rarr; '.$toName;
            }
            if (!empty($timeDiff)){
                $chatAuthorName.=' (-'.$timeDiff.')';
            }
            $msg=$this->oc['SourcePot\Datapool\Tools\MiscTools']->wrapUTF8($chat['Content']['Message']);
            $entryHtml=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getIcon(['maxDim'=>30,'selector'=>$chatAuthor,'returnHtmlOnly'=>TRUE]);
            $entryContentHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'p','element-content'=>$chatAuthorName,'keep-element-content'=>TRUE,'class'=>'widget-entry-header']);
            $entryContentHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'p','element-content'=>$msg,'keep-element-content'=>TRUE,'class'=>'widget-entry-content']);
            $entryHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'div','element-content'=>$entryContentHtml,'keep-element-content'=>TRUE,'class'=>'widget-entry-content-wrapper']);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'div','element-content'=>$entryHtml,'keep-element-content'=>TRUE,'class'=>'widget-entry-wrapper']);
        }
        if (!isset($chat)){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'p','element-content'=>'Nothing here yet...']);
        }
        return $arr;
    }

    public function getHomeAppWidget():string
    {
        $html='';
        $newEntryHtml=$this->oc['SourcePot\Datapool\Foundation\Container']->container('New entry'.__CLASS__.__FUNCTION__,'generic',['Source'=>$this->entryTable],['method'=>'newEntryHtml','classWithNamespace'=>__CLASS__,'target'=>'newforumentry'],['style'=>['margin'=>'0','border'=>'none']]);
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$newEntryHtml,'icon'=>'&#9993; Chat','class'=>'forum']);
        $selector=['Source'=>$this->entryTable,'Folder'=>'%'.$this->currentUser['EntryId'].'%','refreshInterval'=>5];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Chat '.__CLASS__.__FUNCTION__,'generic',$selector,['method'=>'getChat','classWithNamespace'=>__CLASS__,'target'=>'newforumentry'],['style'=>['margin'=>'0','border'=>'none']]);
        return $html;
    }
    
    public function getHomeAppCaption():string
    {
        return '';
    }
    
    public function getHomeAppPriority():int
    {
        return 3;
    }

}
?>