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

class InboxEntries implements \SourcePot\Datapool\Interfaces\Processor{

    private $oc;
    
    private const CONTENT_STRUCTURE_PARAMS=[
        'Inbox source'=>['method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>0,'options'=>[]],   
    ];

    private const INFO_MATRIX=[
        'Info'=>['Commemt'=>'This processor receives entries from a data source. You need to select the receiver through "Inbox source" first. This will load the respective receiver widget.<br/>In addition, this processor forwards received or uploaded entries to different destinations/targets based on conditions.<br/>If there are multiple rules for a forwarding destination, all rules must be met for the entry to be forwarded. Rules are linked by logical "AND" or “OR” (column "..."), whereby the operation for the first rule of each destination is ignored.'],
    ];
    private $inboxClass='__NOTSET__';
    
    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];

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
                'run'=>$this->runForwardEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runForwardEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getInboxEntriesWidget($callingElement),
                'settings'=>$this->getInboxEntriesSettings($callingElement),
                'info'=>$this->getInboxEntriesInfo($callingElement),
            };
        }
    }

    private function getInboxEntriesWidget($callingElement){
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Inbox','generic',$callingElement,['method'=>'getInboxEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
        return $html;
    }
    
     private function getInboxEntriesInfo($callingElement){
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>self::INFO_MATRIX,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?']);
        return $html;
    }
    
    public function getInboxEntriesWidgetHtml($arr){
        $arr['html']=$arr['html']??'';
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runForwardEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runForwardEntries($arr['selector'],1);
        } else if (isset($formData['cmd']['receive'])){
            $result=$this->runForwardEntries($arr['selector'],2);
        }
        // build html
        $btnArr=['tag'=>'input','type'=>'submit','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $matrix=[];
        $btnArr['value']='Test';
        $btnArr['key']=['test'];
        $matrix['Commands']['Test']=$btnArr;
        $btnArr['value']='Run';
        $btnArr['key']=['run'];
        $matrix['Commands']['Run']=$btnArr;
        $btnArr['value']='Receive';
        $btnArr['key']=['receive'];
        $matrix['Commands']['Receive']=$btnArr;
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Inbox widget','style'=>['clear'=>'none']]);
        $idStoreAppArr=['html'=>$this->oc['SourcePot\Datapool\Foundation\Queue']->idStoreWidget($arr['selector']['EntryId']),'icon'=>'Already processed'];
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($idStoreAppArr);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            if ($caption==='Forwarded' || $caption==='Processing statistics'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getInboxEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Inbox entries settings','generic',$callingElement,['method'=>'getInboxEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getInboxEntriesSettingsHtml($arr){
        $arr['html']=$arr['html']??'';
        $arr['html'].=$this->inboxParams($arr['selector']);
        $arr['callingClass']=$arr['selector']['Folder'];
        // load inbox class
        $paramsArr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,'inboxParams',$arr['selector'],TRUE);
        $paramsArr['selector']['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Database']->addOrderedListIndexToEntryId($paramsArr['selector']['EntryId'],1);
        $paramsEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($paramsArr['selector']);
        $this->inboxClass=$paramsEntry['Content']['Inbox source']?:'__NOTSET__';
        if (isset($this->oc[$this->inboxClass])){
            $arr['html'].=$this->oc[$this->inboxClass]->receiverPluginHtml($arr);
        }
        $arr['html'].=$this->forwardingRules($arr['selector']);
        return $arr;
    }
    
    private function inboxParams($callingElement){
        $return=['html'=>'','Parameter'=>[],'result'=>[]];
        if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure['Inbox source']['options']=$this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Receiver');
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Forward entries from inbox';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }

    private function forwardingRules($callingElement){
        // build content structure
        $contentStructure=\SourcePot\Datapool\Processing\ForwardEntries::CONTENT_STRUCTURE_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Forwarding rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
   
    public function runForwardEntries($callingElement,$testRun=1){
        $base=['forwardingparams'=>[],'forwardingrules'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        $base['canvasElements']=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements($callingElement['Folder']);
        // get targets template
        $base['targets']=[];
        foreach($base['forwardingrules'] as $ruleId=>$rule){
            if (!isset($rule['Content']['Forward on success'])){continue;}
            foreach($base['canvasElements'] as $targetName=>$target){
                if ($target['EntryId']==$rule['Content']['Forward on success']){
                    $base['targets'][$targetName]=$target['EntryId'];
                }
            }
        }
        $result=[
            'Processing statistics'=>[
                'Entries'=>['value'=>0],
                'Itmes already processed and skipped'=>['value'=>0],
                'Itmes forwarded'=>['value'=>0],
                'Itmes not forwarded'=>['value'=>0],
                ],
            'Forwarded'=>[],
        ];
        // receive entries
        if ($testRun==2 || $testRun==0){
            $inboxParams=current($base['inboxparams'])['Content'];
            if (isset($this->oc[$inboxParams['Inbox source']])){
                $inboxResult=$this->oc[$inboxParams['Inbox source']]->receive($callingElement['EntryId']);
            } else {
                $this->oc['logger']->log('warning','Function {class} &rarr; {function}() failed. Inbox "{inboxSource}" was not initiated.',['class'=>__CLASS__,'function'=>__FUNCTION__,'inboxSource'=>$inboxParams['Inbox source']]);         
                $result['Inbox statistics']['error']['value']='Inbox source not set';
            }
            foreach($inboxResult as $key=>$value){
                //if (empty($value)){continue;}
                $result['Processing statistics'][$key]['value']=$value;
            }
        }
        // process entry forwarding
        if ($testRun<2){
            // loop through source entries and parse these entries
            $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
            // loop through entries
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
                if ($this->oc['SourcePot\Datapool\Foundation\Queue']->idStoreIsNew($callingElement['EntryId'],$sourceEntry['EntryId'])){
                    $result['Processing statistics']['Itmes already processed and skipped']['value']++;
                    continue;
                }
                $result['Processing statistics']['Entries']['value']++;
                $result=$this->oc['SourcePot\Datapool\Processing\ForwardEntries']->forwardEntry($base,$sourceEntry,$result,$testRun);
                if ($testRun==0){
                    $this->oc['SourcePot\Datapool\Foundation\Queue']->idStoreAdd($callingElement['EntryId'],$sourceEntry['EntryId']);
                }
            }
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }
}
?>