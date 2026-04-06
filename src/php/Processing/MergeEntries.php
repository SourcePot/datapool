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

class MergeEntries implements \SourcePot\Datapool\Interfaces\Processor{

    private $oc;

    private const INFO_MATRIX=[
        'Headline'=>['Value'=>'<b>This processor merges entries into one or multiple target entries.</b>'],
        'Description'=>['Value'=>'The target entry count depends on the entry[Name] count, i.e. the value "Assign this to the target entry[Name]" should be selected accordingly.<br/>All results mapped to "Target Column &rarr; Target key" and calculated based on the "Intra entry merging rules" will only be present in the final target entry,<br/>if "Target Column &rarr; Target key" is mapped to "Inter entry merging rules" &#8680; "Target Column &rarr; Target key".'],
    ];
    
    private const CONTENT_STRUCTURE_PARAMS=[
        'Assign this to the target entry[Name]'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','addParentKeys'=>FALSE,'addColumns'=>[]],
        'Target'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
        'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>[0=>'No, move entries',1=>'Yes, copy entries']],
    ];
        
    private const CONTENT_STRUCTURE_INTRA_ENTRY_RULES=[
        'Select value by key to be merged'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','addParentKeys'=>TRUE,'addColumns'=>[]],
        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Content','standardColumsOnly'=>TRUE,'addColumns'=>['Write to file'=>'Write to file']],
        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Combine'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS,'title'=>"Controls the resulting value, fIf the target already exsists."],
    ];
    
    private const CONTENT_STRUCTURE_INTER_ENTRY_RULES=[
        'Select value by key to be merged'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','addParentKeys'=>FALSE,'addColumns'=>[]],
        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Content','standardColumsOnly'=>TRUE,'addColumns'=>['Write to file'=>'Write to file']],
        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Combine'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS,'title'=>"Controls the resulting value, fIf the target already exsists."],
    ];
        
    private $entryTable='';
    private $entryTemplate=[];
    private $caches=[];
    
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
                'run'=>$this->runMergeEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runMergeEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getMergeEntriesWidget($callingElement),
                'settings'=>$this->getMergeEntriesSettings($callingElement),
                'info'=>$this->getMergeEntriesInfo($callingElement),
            };
        }
    }

    private function getMergeEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Get merge entries widget','generic',$callingElement,['method'=>'getMergeEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }
    
     private function getMergeEntriesInfo($callingElement){
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>self::INFO_MATRIX,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'Info']);
        return $html;
    }
    
    public function getMergeEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runMergeEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runMergeEntries($arr['selector'],1);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Mergeing']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            if ($caption==='Mergeed'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getMergeEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Merging params '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'getMergeEntriesParamsHtml','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Merging intra entry'.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'getMergeIntraEntryRulesHtml','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Merging inter entries'.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'getMergeInterEntriesRulesHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getMergeEntriesParamsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->mergingParams($arr['selector']);
        return $arr;
    }
    
    public function getMergeIntraEntryRulesHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->mergingIntraEntryRules($arr['selector']);
        return $arr;
    }
    
    public function getMergeInterEntriesRulesHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->mergingInterEntryRules($arr['selector']);
        return $arr;
    }
    
    public function mergingParams($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Merge settings: Select a destination for merged entries';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }

    private function mergingIntraEntryRules($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_INTRA_ENTRY_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Rules for merging values within each entry';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
    
    private function mergingInterEntryRules($callingElement){
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,[]);
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_INTER_ENTRY_RULES;
        foreach($base['mergingintraentryrules'] as $rule){
            $flatKey=$rule['Content']['Target column'].\SourcePot\Datapool\Root::ONEDIMSEPARATOR.$rule['Content']['Target key'];
            $contentStructure['Select value by key to be merged']['addColumns'][$flatKey]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($flatKey);
        }
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Rules for merging values between entries';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
        
    public function runMergeEntries($callingElement,$testRun=1){
        $base=['mergingparams'=>[],'mergingrules'=>[],'processId'=>$callingElement['EntryId']];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->initProcessorResult(__CLASS__,$testRun,current($base['mergingparams'])['Content']['Keep source entries']??FALSE);
        // loop through entries
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->updateProcessorResult($result,$sourceEntry);
            if ($result['cntr']['timeLimitReached']){
                break;
            } else if (!$result['cntr']['isSkipRow']){
                $result=$this->mergeEntries($base,$sourceEntry,$result,$testRun);
            }
        }
        // finalize computations, save target entry and present result
        $params=current($base['mergingparams'])['Content'];
        $targetSelector=$base['entryTemplates'][$params['Target']]??[];
        foreach($this->caches as $entryName=>$cacheRules){
            $flatSourceEntry=current($cacheRules)['Flat target entry'];
            foreach($cacheRules as $cache){    
                $flatSourceEntry=$this->oc['SourcePot\Datapool\Foundation\Computations']->combine($flatSourceEntry,$cache);
            }
            $sourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatSourceEntry);
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetSelector,TRUE,$testRun,!empty($params['Keep source entries']));
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->removeFileFromEntry($targetEntry);
            $result['Statistics']['Entries moved (success)']['Value']++;
            if (count($result)<20){
                $result['Target entry '.$entryName]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
            }
        }
        return $this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeProcessorResult($result);
    }
    
    public function mergeEntries($base,$sourceEntry,$result,$testRun){
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        // enrich source entry, process intra entry merge rules
        $takeSample=mt_rand(1,100)>70;
        foreach($base['mergingintraentryrules']??[] as $intraEntryRuleId=>$intraEntryRule){
            $ruleKey=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($intraEntryRuleId)[0];
            $keyNeedle=$intraEntryRule['Content']['Select value by key to be merged']??'__MISSING_KEY__';
            $combineOperation=$intraEntryRule['Content']['Combine']??key(\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS);
            $cacheArr=['__COLUMN__'=>$intraEntryRule['Content']['Target column'],'__OPERATION__'=>$combineOperation,'__VALUES__'=>[]];
            foreach($flatSourceEntry as $key=>$value){
                if (strpos($key,$keyNeedle)===FALSE){continue;}
                $cacheArr['__VALUES__'][$intraEntryRule['Content']['Target key']][]=$this->oc['SourcePot\Datapool\Foundation\Computations']->adjustDatatypeBasedOnOperation($value,$combineOperation);
            }
            $flatSourceEntry=$this->oc['SourcePot\Datapool\Foundation\Computations']->combine($flatSourceEntry,$cacheArr);
            $mergedValue=$flatSourceEntry[$intraEntryRule['Content']['Target column']][$intraEntryRule['Content']['Target key']]??'?';
            if (!isset($result['Intra entry merge sample']) || $takeSample){
                $result['Intra entry merge sample'][$ruleKey]=['Key'=>$intraEntryRule['Content']['Target column'].' &rarr; '.$intraEntryRule['Content']['Target key'],'Value'=>$mergedValue];
            }
        }
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($flatSourceEntry);
        // create target entry, process inter entries merge rules
        $params=current($base['mergingparams'])['Content'];
        $targetEntry=$sourceEntry;
        $targetEntry['Name']=$flatSourceEntry[$params['Assign this to the target entry[Name]']];
        $targetEntry['Content']=$targetEntry['Params']=[];
        $flatTargetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($targetEntry);    
        foreach($base['merginginterentryrules']??[] as $interEntryRuleId=>$interEntryRule){
            $ruleKey=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($interEntryRuleId)[0];
            $combineOperation=$interEntryRule['Content']['Combine']??key(\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS);
            $column=$interEntryRule['Content']['Target column'];
            $key=$interEntryRule['Content']['Target key'];
            $value=$flatSourceEntry[$interEntryRule['Content']['Select value by key to be merged']];
            if (!isset($this->caches[$flatTargetEntry['Name']][$ruleKey])){
                $this->caches[$flatTargetEntry['Name']][$ruleKey]=['__COLUMN__'=>$column,'__OPERATION__'=>$combineOperation,'__VALUES__'=>[],'Flat target entry'=>$flatTargetEntry];
            }
            $this->caches[$flatTargetEntry['Name']][$ruleKey]['__VALUES__'][$key][]=$this->oc['SourcePot\Datapool\Foundation\Computations']->adjustDatatypeBasedOnOperation($value,$combineOperation);
        }
        return $result;
    }
}
?>