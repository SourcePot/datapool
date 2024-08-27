<?php
/*
* This file is part of the Datapool CMS package.
* ANY METHODS DEALING WITH LEGACY ISSUES SHOULD BE PLACED HERE.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Legacy{
    private $oc;
        
    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
    
    }
    
    public function updateEntryListEditorEntries($arr)
    {
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__);
        if (!empty($arr['selector']['Source'])){
            $currentListSelector=array('Source'=>$arr['selector']['Source']);
        } else if (!empty($arr['selector']['Class'])){
            $currentListSelector=array('Class'=>$arr['selector']['Class']);
        } else {
            return FALSE;
        }
        // get current ordered list entry example from selector
        $currentListSelector['Group']=(empty($arr['selector']['Group']))?FALSE:$arr['selector']['Group'];
        $currentListSelector['Folder']=(empty($arr['selector']['Folder']))?FALSE:$arr['selector']['Folder'];
        $currentListSelector['Name']=(empty($arr['selector']['Name']))?FALSE:$arr['selector']['Name'];
        $currentListEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($currentListSelector,TRUE);
        // check for key mismatch
        if (empty($currentListEntry['EntryId']) || empty($arr['selector']['EntryId'])){return $arr;}
        $oldKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListKeyFromEntryId($currentListEntry['EntryId']);
        $newKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListKeyFromEntryId($arr['selector']['EntryId']);
        if ($oldKey===$newKey){return $arr;}
        // correct key mismatch
        $rebuildSelector=array('EntryId'=>$oldKey);
        if (!empty($currentListEntry['Source'])){
            $rebuildSelector['Source']=$currentListEntry['Source'];
        } else if (!empty($currentListEntry['Class'])){
            $rebuildSelector['Class']=$currentListEntry['Class'];
        } else {
            return FALSE;
        }
        $this->oc['SourcePot\Datapool\Foundation\Database']->rebuildOrderedList($rebuildSelector,$newKey);
        $context['oldKey']=$oldKey;
        $context['newKey']=$newKey;
        $this->oc['logger']->log('notice','{class}&rarr;{function} changed ordered list keys: "{oldKey} &rarr; {newKey}"',$context);
    
        return TRUE;
    }
    
}
?>