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

class OutboxEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    private $outboxClass='';
    
    private $recipientOptions=[];
    
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
        $this->recipientOptions=$this->oc['SourcePot\Datapool\Foundation\User']->getUserOptions();
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
 
    public function getEntryTemplate(){
        return $this->entryTemplate;
    }

   /**
     * This method is the interface of this data processing class
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method 
     * @param string $action Selects the requested process to be run  
     *
     * @return string|bool Return the html-string or TRUE callingElement does not exist
     */
    public function dataProcessor(array $callingElementSelector=[],string $action='info'){
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->runOutboxEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runOutboxEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getOutboxEntriesWidget($callingElement),
                'settings'=>$this->getOutboxEntriesSettings($callingElement),
                'info'=>$this->getOutboxEntriesInfo($callingElement),
            };
        }
    }

    private function getOutboxEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Outbox','generic',$callingElement,array('method'=>'getOutboxEntriesWidgetHtml','classWithNamespace'=>__CLASS__),[]);
    }
    
    private function getOutboxEntriesInfo($callingElement){
        $matrix=[];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'?'));
        return $html;
    }

    public function getOutboxEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runOutboxEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runOutboxEntries($arr['selector'],1);
        }
        // build html
        $btnArr=array('tag'=>'input','type'=>'submit','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $matrix=[];
        $btnArr['value']='Test';
        $btnArr['key']=array('test');
        $matrix['Commands']['Test']=$btnArr;
        $btnArr['value']='Run';
        $btnArr['key']=array('run');
        $matrix['Commands']['Run']=$btnArr;
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Outbox widget'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getOutboxEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Outbox entries settings','generic',$callingElement,array('method'=>'getOutboxEntriesSettingsHtml','classWithNamespace'=>__CLASS__),[]);
        }
        return $html;
    }
    
    public function getOutboxEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->outboxParams($arr['selector']);
        $arr['callingClass']=$arr['selector']['Folder'];
        if (isset($this->oc[$this->outboxClass])){$arr['html']=$this->oc[$this->outboxClass]->transmitterPluginHtml($arr);}
        $arr['html'].=$this->outboxRules($arr['selector']);
        return $arr;
    }
    
    private function outboxParams($callingElement){
        $return=array('html'=>'','Parameter'=>[],'result'=>[]);
        if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
        $contentStructure=array('Outbox class'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'options'=>$this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Transmitter')),
                                'Recipient'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'options'=>$this->recipientOptions),
                                'When done'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array('Keep entries','Delete sent entries')),
                                );
        // get selctorB
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']['Content']=array('Column to delay'=>'Name');
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($arr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        $elementId=key($formData['val']);
        if (isset($formData['cmd'][$elementId])){
            $arr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($arr['selector'],TRUE);
        }
        // load outbox class
        if (isset($arr['selector']['Content']['Outbox class'])){$this->outboxClass=$arr['selector']['Content']['Outbox class'];}
        // get HTML
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Forward entries from outbox';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }

    private function outboxRules($callingElement){
        $msgPlaceholder='e.g. Dear Sir or Madam, the import for the attached document failed. Please capture the docuument manually.';
        $contentStructure=array('Text'=>array('method'=>'element','tag'=>'textarea','element-content'=>'','keep-element-content'=>TRUE,'placeholder'=>$msgPlaceholder,'rows'=>4,'cols'=>20,'excontainer'=>TRUE),
                                ' '=>array('method'=>'element','tag'=>'p','keep-element-content'=>TRUE,'element-content'=>'OR'),
                                'use column'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE),
                                'Add to'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'Subject','options'=>array('Subject'=>'Subject','Message'=>'Message')),
                                );
        $contentStructure['use column']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Message creation rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    public function runOutboxEntries($callingElement,$testRun=1){
        $base=$this->getBaseArr($callingElement);
        $outboxParams=current($base['outboxparams']);
        $outboxParams=$outboxParams['Content'];
        if (isset($this->oc[$outboxParams['Outbox class']])){
            // loop through source entries and parse these entries
            $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
            $result=array('Outbox statistics'=>array('Emails sent'=>array('value'=>0),
                                                     'Entries removed'=>array('value'=>0),
                                                     'Emails failed'=>array('value'=>0),
                                                     'Entries processed'=>array('value'=>0),
                                                    )
                         );
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE,'Read','Date',FALSE) as $entry){
                $result=$this->processEntry($entry,$base,$callingElement,$result,$testRun);
            }
        } else {
            $result=array('Outbox statistics'=>array('Error'=>array('value'=>'Please select the outbox.')));
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function processEntry($entry,$base,$callingElement,$result,$testRun,$isDebugging=FALSE){
        $outboxParams=current($base['outboxparams']);
        $outboxParams=$outboxParams['Content'];
        $orgEntry=$entry;
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
        $entry['Content']=[];
        // process outbox rules
        if (empty($base['outboxrules'])){
            $result['Outbox statistics']['Error']['Value']='Outbox rules missing';
            return $result;
        }
        foreach($base['outboxrules'] as $ruleId=>$rule){
            $flatKeyNeedle=$rule['Content']['use column'];
            $emailPart=$rule['Content']['Add to'];
            if (!empty($rule['Content']['Text'])){
                $entry['Content'][$emailPart]=(isset($entry['Content'][$emailPart]))?$entry['Content'][$emailPart].' '.$rule['Content']['Text']:$rule['Content']['Text'];
            } else if (!empty($flatEntry[$flatKeyNeedle])){
                $entry['Content'][$emailPart]=(isset($entry['Content'][$emailPart]))?$entry['Content'][$emailPart].' '.$flatEntry[$flatKeyNeedle]:$flatEntry[$flatKeyNeedle];
            }
        }
        $entry['Content']['Subject']=(empty($entry['Content']['Subject']))?$entry['Name']:$entry['Content']['Subject'];
        // create email
        if ($testRun){
            $result['Outbox statistics']['Emails sent']['value']++;    
        } else if ($this->oc[$outboxParams['Outbox class']]->send($outboxParams['Recipient'],$entry)){
            if (empty(intval($outboxParams['When done']))){
                // forwarded => By email to user $this->recipientOptions[$outboxParams['Recipient']]
            } else {
                $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($orgEntry,TRUE);
                $result['Outbox statistics']['Entries removed']['value']++;
            }
            $result['Outbox statistics']['Emails sent']['value']++;    
        } else {
            $result['Outbox statistics']['Emails failed']['value']++;    
        }
        $result['Outbox statistics']['Entries processed']['value']++;
        $emailIndex=(isset($result['Sample emails']))?count($result['Sample emails'])+1:1;
        $emailCaption='Sample '.$emailIndex;
        if ($emailIndex<2 || mt_rand(0,100)>80){
            $result['Sample emails'][$emailCaption]=array('To'=>$this->recipientOptions[$outboxParams['Recipient']]);
            if (isset($entry['Content']['Subject'])){$result['Sample emails'][$emailCaption]['Subject']=$entry['Content']['Subject'];}
            if (isset($entry['Content']['Message'])){$result['Sample emails'][$emailCaption]['Message']=$entry['Content']['Message'];}
        }
        if ($isDebugging){
            $debugArr=array('base'=>$base,'entry'=>$orgEntry,'flatEntry'=>$flatEntry,'entry_out'=>$entry);
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        return $result;
    }

    private function getBaseArr($callingElement){
        $base=array('Script start timestamp'=>hrtime(TRUE));
        $entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId']);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            $key=strtolower($entry['Group']);
            $base[$key][$entry['EntryId']]=$entry;
            // entry template
            foreach($entry['Content'] as $contentKey=>$content){
                if (is_array($content)){continue;}
                if (mb_strpos($content,'EID')!==0 || mb_strpos($content,'eid')===FALSE){continue;}
                $template=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->entryId2selector($content);
                if ($template){$base['entryTemplates'][$content]=$template;}
            }
        }
        return $base;
    }

}
?>