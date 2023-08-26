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

class OptOutEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    use \SourcePot\Datapool\Traits\Conversions;
    
    private $oc;

    private $entryTable='';
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
        
    private $dataTypes=array('string'=>'String','stringNoWhitespaces'=>'String without whitespaces','splitString'=>'Split string','int'=>'Integer','float'=>'Float','bool'=>'Boolean','money'=>'Money','date'=>'Date','codepfad'=>'Codepfad','unycom'=>'UNYCOM file number');
    private $sections=array(0=>'all sections');
    
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

    public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
        // This method is the interface of this data processing class
        // The Argument $action selects the method to be invoked and
        // argument $callingElementSelector$ provides the entry which triggerd the action.
        // $callingElementSelector ... array('Source'=>'...', 'EntryId'=>'...', ...)
        // If the requested action does not exist the method returns FALSE and 
        // TRUE, a value or an array otherwise.
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        switch($action){
            case 'run':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                return $this->runOptOutEntries($callingElement,$testRunOnly=FALSE);
                }
                break;
            case 'test':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runOptOutEntries($callingElement,$testRunOnly=TRUE);
                }
                break;
            case 'widget':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getOptOutEntriesWidget($callingElement);
                }
                break;
            case 'settings':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getOptOutEntriesSettings($callingElement);
                }
                break;
            case 'info':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getOptOutEntriesInfo($callingElement);
                }
                break;
        }
        return FALSE;
    }

    private function getOptOutEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Opt-out entries','generic',$callingElement,array('method'=>'getOptOutEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
    public function getOptOutEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runOptOutEntries($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runOptOutEntries($arr['selector'],TRUE);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Opt-out widget'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        return $arr;
    }
    
    private function getOptOutEntriesInfo($callingElement){
        $matrix=array();
        $matrix['Mode of operation']['value']=__CLASS__.' takes the opt-out receipt and extracts the EP-patent number as well as the opt-out-date and -number.';
        $matrix['Source UNYCOM']['value']='Please select the UNYCOM cases. These entries must contain the UNYCOM case reference In <b>"Folder"</b> and <b>"Content  &rarr; Erteilungsaktenzeichen"</b>';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        return $html;
    }

    private function getOptOutEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Opt-out entries settings','generic',$callingElement,array('method'=>'getOptOutEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getOptOutEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->optOutParams($arr['selector']);
        return $arr;
    }

    private function optOutParams($callingElement){
        $contentStructure=array('Source column'=>array('method'=>'keySelect','value'=>'useValue','excontainer'=>TRUE,'addSourceValueColumn'=>TRUE),
                                'Source UNYCOM'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                'Target on success'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                'Target on failure'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                'Save'=>array('method'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
                                );
        $contentStructure['Source column']+=$callingElement['Content']['Selector'];
        // get selector
        $arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement);
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
        $arr['caption']='Opt-out control: Select opt-out target and type';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
        $matrix=array('Parameter'=>$row);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
        return $html;
    }

    private function runOptOutEntries($callingElement,$testRun=FALSE){
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
        $result=array('Opt-out statistics'=>array('Entries'=>array('value'=>0),'Success'=>array('value'=>0),'Failed'=>array('value'=>0),'No text, skipped'=>array('value'=>0),'Skip rows'=>array('value'=>0)));
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            if ($entry['isSkipRow']){
                $result['Opt-out statistics']['Skip rows']['value']++;
                continue;
            }
            $result['Opt-out statistics']['Entries']['value']++;
            $result=$this->optOutEntry($base,$sourceEntry,$result,$testRun);
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function optOutEntry($base,$sourceEntry,$result,$testRun){
        $optOutParams=current($base['optoutparams']);
        $optOutParams=$optOutParams['Content'];
        $optOutSelectors=$base['entryTemplates'];
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        //
        if (isset($flatSourceEntry[$optOutParams['Source column']])){
            // extract EP-Patentnumbers and create UNYCOM case selector
            $haystack=$flatSourceEntry[$optOutParams['Source column']];
            preg_match_all('/EP[0-9 ]{7,9}/u',$haystack,$matches);
            if (empty($matches[0][0])){
                $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$optOutSelectors[$optOutParams['Target on failure']],TRUE,$testRun);
                $result['Opt-out statistics']['Failed']['value']++;
                $result['Sample result (Failed)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
            } else {
                $caseSelectors=array();
                foreach(current($matches) as $matchIndex=>$match){
                    $match=preg_replace('/\s+/','',$match);
                    if (strlen($match)<9){continue;}
                    $caseSelectors[$match]=$optOutSelectors[$optOutParams['Source UNYCOM']];
                    $caseSelectors[$match]['Content']='%'.$match[0].$match[1].'%'.$match[2].'%'.$match[3].$match[4].$match[5].'%'.$match[6].$match[7].$match[8].'%';
                }
            }
            $sourceEntry['Content']=array();
            $sourceEntry=$this->addContentFromOptOutReceipts($sourceEntry,$haystack);
            // get UNYCOM case match
            foreach($caseSelectors as $patentNumber=>$caseSelector){
                $sourceEntry['Name']=$patentNumber;
                $success=FALSE;
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($caseSelector,TRUE) as $case){
                    if ($case['isSkipRow']){continue;}
                    // skip if Patent number does not match
                    if (empty($case['Content']['Erteilungsaktenzeichen'])){continue;}
                    $erteilungsaktenzeichen=preg_replace('/\s+/','',$case['Content']['Erteilungsaktenzeichen']);
                    if (strpos($erteilungsaktenzeichen,trim($patentNumber,'EP'))===FALSE){continue;}
                    // skip if EP-Validation case instead of EP-case
                    if (strlen(trim($case['Folder'],'0123456789PFX '))>2){continue;}
                    // matching EP-case found
                    $success=TRUE;
                    $sourceEntry['Content']=array_merge($sourceEntry['Content'],$case['Content']);
                    break;
                }
                if ($success){
                    $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$optOutSelectors[$optOutParams['Target on success']],TRUE,$testRun,TRUE);
                    $result['Opt-out statistics']['Success']['value']++;
                    $result['Sample result (success)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);    
                } else {
                    $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$optOutSelectors[$optOutParams['Target on failure']],TRUE,$testRun,TRUE);
                    $result['Opt-out statistics']['Failed']['value']++;
                    $result['Sample result (failed)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
                }
            }
        } else {
            // Source column not found
            $result['Opt-out statistics']['No text, skipped']['value']++;
        }
        return $result;
    }
    
    private function addContentFromOptOutReceipts($entry,$optOutReceiptString){
        $regexArr=array('[0-9]{6,7}\/20[2-9][0-9]'=>array('key'=>'Case Number','dataType'=>'string','matchIndex'=>0),
                        '(Date\s+of\s+lodging\s+)([0-9]{2}\/[0-9]{2}\/[0-9]{4})'=>array('key'=>'Date of lodging','dataType'=>'date','matchIndex'=>2),
                        );
        $entry['Content']=array();
        foreach($regexArr as $regex=>$def){
            preg_match('/'.$regex.'/',$optOutReceiptString,$match);
            if (empty($match[$def['matchIndex']])){continue;}
            $dataTypeMethod='convert2'.$def['dataType'];
            $entry['Content'][$def['key']]=$this->$dataTypeMethod($match[$def['matchIndex']]);   
        }
        return $entry;
    }

    public function callingElement2arr($callingClass,$callingFunction,$callingElement){
        if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
        $type=$this->oc['SourcePot\Datapool\Root']->class2source(__CLASS__);
        $type.='|'.$callingFunction;
        $entry=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Type'),0);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ALL_CONTENTADMIN_R');
        $entry['Content']=array();
        $arr=array('callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'selector'=>$entry);
        return $arr;
    }

}
?>