<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Processing;

class SelectEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }
    
    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
    }

    public function getEntryTable():string{return $this->entryTable;}
    
    /**
     * This method is the interface of this data processing class
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method 
     * @param string $action Selects the requested process to be run  
     *
     * @return bool TRUE the requested action exists or FALSE if not
     */
    public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        switch($action){
            case 'run':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runSelectEntries($callingElement,$testRunOnly=0);
                }
                break;
            case 'test':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runSelectEntries($callingElement,$testRunOnly=1);
                }
                break;
            case 'widget':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getSelectEntriesWidget($callingElement);
                }
                break;
            case 'settings':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getSelectEntriesSettings($callingElement);
                }
                break;
            case 'info':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getSelectEntriesInfo($callingElement);
                }
                break;
        }
        return FALSE;
    }

    private function getSelectEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Selecting','generic',$callingElement,array('method'=>'getSelectEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
     private function getSelectEntriesInfo($callingElement){
        $matrix=array();
        $matrix['Description']=array('<p style="width:40em;">A database table selector is created based on the "Entry pool" selector and enriched with features derived from the entries selected by the current canvas element and based on the rules provided. All entries selected by the created selector are copied to the "Target".</p>');
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        return $html;
    }
    
    public function getSelectEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runSelectEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runSelectEntries($arr['selector'],1);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selecting widget'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getSelectEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Selecting entries settings','generic',$callingElement,array('method'=>'getSelectEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getSelectEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->selectingParams($arr['selector']);
        $arr['html'].=$this->selectingRules($arr['selector']);
        //$selectorMatrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
        //$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for Selecting'));
        return $arr;
    }
    
    private function selectingParams($callingElement){
        $return=array('html'=>'','Parameter'=>array(),'result'=>array());
        if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
        $contentStructure=array('Selector pool'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>$callingElement['Content']['Style']['Text'],'disabled'=>TRUE,'excontainer'=>TRUE),
                                'Entry pool'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                'Target'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                'Mode'=>array('method'=>'select','value'=>0,'options'=>array('Run when triggered','Run only on empty Target'),'excontainer'=>TRUE),
                                'Save'=>array('method'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE),
                            );
        // get selctorB
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        $elementId=key($formData['val']);
        if (isset($formData['cmd'][$elementId])){
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        }
        // get HTML
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Copy selected entries from Entry pool to Target.';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }

    private function selectingRules($callingElement){
        $triggerOptions=$this->oc['SourcePot\Datapool\Foundation\Signals']->getTriggerOptions();
        $contentStructure=array('Needle value'=>array('method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'e.g. US','excontainer'=>TRUE),
                                '... or needle column'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','standardColumsOnly'=>FALSE,'addSourceValueColumn'=>TRUE),
                                'Entry pool column'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Content','standardColumsOnly'=>TRUE,'addColumns'=>array()),
                                'Wrap needle'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'...','options'=>array('...'=>'...','%...'=>'%...','...%'=>'...%','%...%'=>'%...%')),
                                'Skip entry, if'=>array('method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>array('Never','empty needle')),
                                );
        $contentStructure['... or needle column']+=$callingElement['Content']['Selector'];
        $contentStructure['Entry pool column']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Select rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
        
    public function runSelectEntries($callingElement,$testRun=1){
        $base=array('Script start timestamp'=>hrtime(TRUE));
        $entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId']);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            $key=explode('|',$entry['Type']);
            $key=array_pop($key);
            $base[$key][$entry['EntryId']]=$entry;
            // entry template
            foreach($entry['Content'] as $contentKey=>$content){
                if (is_array($content)){continue;}
                if (strpos($content,'EID')!==0 || strpos($content,'eid')===FALSE){continue;}
                $template=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->entryId2selector($content);
                if ($template){$base['entryTemplates'][$content]=$template;}
            }
        }
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Selecting statistics'=>array('Entries scanned'=>array('value'=>0),
                                                    'Entries added/updated'=>array('value'=>0),
                                                    'Empty needle entries skiped'=>array('value'=>0),
                                                    'Skip rows'=>array('value'=>0),
                                                    )
                     );
        // check if run condition is met
        $params=current($base['selectingparams']);
        $runOnEmptyTargetOnly=intval($params['Content']['Mode']);
        $targetSelector=$base['entryTemplates'][$params['Content']['Target']];
        $targetEntryCount=$this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($targetSelector,TRUE);
        if ($runOnEmptyTargetOnly===0 || $targetEntryCount===0){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
                $result['Selecting statistics']['Entries scanned']['value']++;
                if ($entry['isSkipRow']){
                    $result['Selecting statistics']['Skip rows']['value']++;
                    continue;
                }
                $result=$this->selectEntry($base,$sourceEntry,$result,$testRun);
            }
        } else {
            $result['Selecting statistics']['Message']=array('value'=>'Target contains '.$targetEntryCount.' entries, finished without running selection.');
        }   
        // finalize statistic
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function selectEntry($base,$sourceEntry,$result,$testRun,$isDebugging=TRUE){
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        $params=current($base['selectingparams']);
        $entryPoolSelector=$base['entryTemplates'][$params['Content']['Entry pool']];
        $targetSelector=$base['entryTemplates'][$params['Content']['Target']];
        $debugArr=array('base'=>$base,'testRun'=>$testRun);
        $skipThisEntry=FALSE;
        foreach($base['selectingrules'] as $ruleId=>$rule){
            $key=$rule['Content']['... or needle column'];
            if (isset($flatSourceEntry[$key])){
                $needle=$flatSourceEntry[$key];
            } else {
                $needle=$rule['Content']['Needle value'];
            }
            if (empty($needle) && empty($rule['Content']['Skip entry, if'])){$skipThisEntry=TRUE;}
            $needle=match($rule['Content']['Wrap needle']){
                            '...'=>$needle=strval($needle),
                            '%...'=>$needle='%'.strval($needle),
                            '...%'=>$needle=strval($needle).'%',
                            '%...%'=>$needle='%'.strval($needle).'%',
                            };
            $newSelectorColumn=$rule['Content']['Entry pool column'].$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator().$ruleId;
            $entryPoolSelector[$newSelectorColumn]=$needle;
        }
        if (!isset($result['Created selector samples'])){$result['Sample selector']=array();}
        $takeSample=(mt_rand(0,100)>50 && count($result['Sample selector'])<5);
        if ($skipThisEntry){
            $result['Selecting statistics']['Empty needle entries skiped']['value']++;
        } else if (empty($testRun)){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entryPoolSelector,TRUE) as $targetEntry){
                $targetEntry=array_merge($targetEntry,$targetSelector);
                $targetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($targetEntry,array('Source','Group','Folder','Name'),'0','',FALSE);
                $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($targetEntry,TRUE);
                $result['Selecting statistics']['Entries added/updated']['value']++;
            }
        } else {
            if ($takeSample){$result['Created selector samples'][]=$entryPoolSelector;}
            $result['Selecting statistics']['Entries added/updated']['value']='?';
        }
        // finalize documentation
        if ($isDebugging && $takeSample){
            $debugArr['result']=$result;
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        return $result;
    }

}
?>