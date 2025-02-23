<?php
/*
* This file is part of the Datapool CMS package.
* This class provides basic persistency based on the SimpleCache interface.
* It provides a simple infrastructure to stores data in the persistency-table of the database.
* Entry Read and Write access is not restricted, i.e. this class should not be used to store access restricted data! 
*
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Persistency implements \Psr\SimpleCache\CacheInterface{
    
    public const DEFAULT_TTL='P10Y';    // <-------- adjust for long term persistency
    
    private $oc;

    private $tmpCallingClassFuntion=[];

    private $entryTable='';
    private $entryTemplate=['Expires'=>['type'=>'DATETIME','value'=>\SourcePot\Datapool\Root::NULL_DATE,'Description'=>'If the current date is later than the Expires-date the entry will be deleted. On insert-entry the init-value is used only if the Owner is not anonymous, set to 10mins otherwise.'],
                            'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'],
                            'Owner'=>['type'=>'VARCHAR(100)','value'=>'SYSTEM','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.']
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
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    /** @inheritdoc */
    public function get(string $key, mixed $default = null):mixed
    {
        $callingClassFunction=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $callingClassFunction=($callingClassFunction['class']===__CLASS__)?$this->tmpCallingClassFuntion:$callingClassFunction;
        $entryId=sha1($callingClassFunction['class'].'|'.$key);
        $selector=['Source'=>$this->getEntryTable(),'EntryId'=>$entryId];
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector,$isSystemCall=FALSE,$rightType='Read',$returnMetaOnNoMatch=FALSE);
        if ($entry){
            return unserialize($entry['Content']['!serialized!']);
        } else {
            return $default;
        }
    }

    /** @inheritdoc */
    public function set($key, $value, $ttl=new \DateInterval(self::DEFAULT_TTL)):bool
    {
        $expires=new \DateTime('now');
        $expires->add($ttl);
        $callingClassFunction=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $callingClassFunction=($callingClassFunction['class']===__CLASS__)?$this->tmpCallingClassFuntion:$callingClassFunction;
        $entryId=sha1($callingClassFunction['class'].'|'.$key);
        $entry=['Source'=>$this->getEntryTable(),'Name'=>$key,'EntryId'=>$entryId];
        $entry['Group']='data';
        $entry['Folder']=str_replace('\\','_',$callingClassFunction['class']);
        $entry['Expires']=$expires->format('Y-m-d H:i:s');
        $entry['Content']=['!serialized!'=>serialize($value)];
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,$isSystemCall=FALSE,$noUpdateButCreateIfMissing=FALSE,$addLog=TRUE);
        return $entry!==FALSE;
    }

    /** @inheritdoc */
    public function delete($key):bool
    {
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $callingClassFunction=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $callingClassFunction=($callingClassFunction['class']===__CLASS__)?$this->tmpCallingClassFuntion:$callingClassFunction;
        $entryId=sha1($callingClassFunction['class'].'|'.$key);
        $selector=['Source'=>$this->getEntryTable(),'EntryId'=>$entryId];
        $statistics=$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector,$isSystemCall=FALSE);
        return boolval($statistics['deleted']);
    }

    /** @inheritdoc */
    public function clear():bool
    {
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $callingClassFunction=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $selector=['Source'=>$this->getEntryTable(),'Group'=>'data','Folder'=>str_replace('\\','_',$callingClassFunction['class'])];
        $statistics=$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector,$isSystemCall=FALSE);
        return boolval($statistics['deleted']);
    }

    /** @inheritdoc */
    public function getMultiple($keys, $default = null):iterable
    {
        $this->tmpCallingClassFuntion=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        foreach($keys as $key){    
            yield $this->get($key,$default);
        }
    }

    /** @inheritdoc */
    public function setMultiple($values, $ttl = null):bool
    {
        $success=TRUE;
        $this->tmpCallingClassFuntion=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        foreach($values as $key=>$value){    
            $success=($this->set($key,$value))?$success:FALSE;
        }
        return $success;
    }

    /** @inheritdoc */
    public function deleteMultiple($keys):bool
    {
        $success=TRUE;
        $this->tmpCallingClassFuntion=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        foreach($keys as $key){
            $success=($this->delete($key))?$success:FALSE;
        }
        return $success;
    }

    /** @inheritdoc */
    public function has($key):bool
    {
        $callingClassFunction=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $entryId=sha1($callingClassFunction['class'].'|'.$key);
        $selector=['Source'=>$this->getEntryTable(),'EntryId'=>$entryId];
        $return=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($selector,$isSystemCall=TRUE,$rightType='Read',$removeGuideEntries=TRUE);
        return !empty($return);
    }

}
?>