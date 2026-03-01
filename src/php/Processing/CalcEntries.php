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

class CalcEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    private $ruleOptions=[];

    private const INFO_MATRIX=[
        '"&infin;" (infinite): '=>['Comment'=>'Within entries <b style="font-size:1.5rem;">&infin;</b> is represented as string "INF" or "-INF". Only for comarisons or operations it is mapped to INF or -INF.'],
        'Division by zero: '=>['Comment'=>'To avoid division by zero errors and to allow comparisions, x/0 will return INF or -INF, if x<0'],
        'Not a number (NAN) and NULL: '=>['Comment'=>'As with INF, NAN and NULL are stored in Entries as string "NAN", "NULL" and only mapped to PHP constants during comparisons and computations.'],
    ];

    private const CONTENT_STRUCTURE_PARAMS=[
        'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>[0=>'No, move entries',1=>'Yes, copy entries']],
        'Target on success'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
        'Target on failure'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
        'System timezone (is used unless otherwise specified)'=>['method'=>'element','tag'=>'p','element-content'=>\SourcePot\Datapool\Root::DB_TIMEZONE,'excontainer'=>TRUE],
    ];
        
    private const CONTENT_STRUCTURE_RULES=[
        '"A" selected by...'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>[]],
        'Default value "A"'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Operation'=>['method'=>'select','excontainer'=>TRUE,'value'=>'+','options'=>[],'keep-element-content'=>TRUE],
        '"B" selected by...'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>[]],
        'Default value "B"'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        ''=>['method'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE],
        'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE],
        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
    ];

    private const CONTENT_STRUCTURE_FAILURE_RULES=[
        'Value'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'','addSourceValueColumn'=>FALSE,'addColumns'=>[]],
        'Failure if Result...'=>['method'=>'select','excontainer'=>TRUE,'value'=>'strpos','keep-element-content'=>TRUE,'options'=>\SourcePot\Datapool\Foundation\Computations::CONDITION_TYPES],
        'Compare value'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
    ];
        
    private const CONTENT_STRUCTURE_CONDITIONAL_RULES=[
        'Condition'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'','addSourceValueColumn'=>FALSE,'addColumns'=>[]],
        'Use value if...'=>['method'=>'select','excontainer'=>TRUE,'value'=>'==0','keep-element-content'=>TRUE,'options'=>\SourcePot\Datapool\Foundation\Computations::COMPARE_TYPES_CONST],
        ''=>['method'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE],
        'Use'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>[]],
        'Value'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE],
        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
    ];
    
    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];
    
    public function __construct($oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    public function loadOc(array $oc):void
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
     public function dataProcessor(array $callingElementSelector=[],string $action='info')
     {
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->runCalcEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runCalcEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getCalcEntriesWidget($callingElement),
                'settings'=>$this->getCalcEntriesSettings($callingElement),
                'info'=>$this->getCalcEntriesInfo($callingElement),
            };
        }
    }

    private function getCalcEntriesWidget($callingElement)
    {
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Calculate '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'getCalcEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }

    private function getCalcEntriesInfo($callingElement):string
    {
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>self::INFO_MATRIX,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'!']);
        return $html;
    }

    public function getCalcEntriesWidgetHtml($arr):array
    {
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runCalcEntries($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runCalcEntries($arr['selector'],TRUE);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Calculate widget']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getCalcEntriesSettings($callingElement):string
    {
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Calculate entries settings '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'getCalcEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getCalcEntriesSettingsHtml(array $arr):array
    {
        // initialize rule options
        $entriesSelector=['Source'=>$this->entryTable,'Name'=>$arr['selector']['EntryId']];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            if (mb_strpos($entry['Group'],'Rules')===FALSE){continue;}
            $rulePrefix=str_replace('Rules',' rule',$entry['Group']);
            $ruleIndex=$this->ruleId2ruleIndex($entry['EntryId'],ucfirst($rulePrefix));
            $this->ruleOptions[strtolower($entry['Group'])][$ruleIndex]=$ruleIndex;
        }
        // get html
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->calculationParams($arr['selector']);
        $arr['html'].=$this->calculationRules($arr['selector']);
        $arr['html'].=$this->conditionalValueRules($arr['selector']);
        $arr['html'].=$this->failureRules($arr['selector']);
        return $arr;
    }

    private function calculationParams($callingElement)
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Calculation control';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }
    
    private function calculationRules(array $callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_RULES;
        $addKeys=(isset($this->ruleOptions[mb_strtolower(__FUNCTION__)]))?$this->ruleOptions[mb_strtolower(__FUNCTION__)]:[];
        $operations=\SourcePot\Datapool\Foundation\Computations::CONDITION_TYPES+\SourcePot\Datapool\Foundation\Computations::OPERATIONS;
        $contentStructure['"A" selected by...']['addColumns']=$addKeys;
        $contentStructure['Operation']['options']=$operations;
        $contentStructure['"B" selected by...']['addColumns']=$addKeys;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Calculation rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function failureRules(array $callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_FAILURE_RULES;
        $addKeys=(isset($this->ruleOptions['calculationrules']))?$this->ruleOptions['calculationrules']:[];
        $contentStructure['Value']['value']=current($addKeys);
        $contentStructure['Value']['addColumns']=$addKeys;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Failure rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function conditionalValueRules(array $callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_CONDITIONAL_RULES;
        $addKeys=(isset($this->ruleOptions['calculationrules']))?$this->ruleOptions['calculationrules']:[];
        $contentStructure['Condition']['value']=current($addKeys);
        $contentStructure['Condition']['addColumns']=$addKeys;
        $contentStructure['Use']['addColumns']=$addKeys;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Conditional value rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function runCalcEntries(array $callingElement,bool $testRun=FALSE):array
    {
        $base=['calculationparams'=>[],'calculationrules'=>[],'conditionalvaluerules'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->initProcessorResult(__CLASS__,$testRun,current($base['calculationparams'])['Content']['Keep source entries']??FALSE);
        // loop through entries
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->updateProcessorResult($result,$sourceEntry);
            if ($result['cntr']['timeLimitReached']){
                break;
            } else if (!$result['cntr']['isSkipRow']){
                $result=$this->calcEntry($base,$sourceEntry,$result,$testRun);
            }
        }
        return $this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeProcessorResult($result);
    }
    
    private function calcEntry(array $base,array $sourceEntry,array $result,bool $testRun)
    {
        $debugArr=[];
        $log='';
        $params=current($base['calculationparams']);
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        // loop through calculation rules
        $ruleResults=[];
        foreach($base['calculationrules']??[] as $ruleEntryId=>$rule){
            $calculationRuleIndex=$this->ruleId2ruleIndex($ruleEntryId,'Calculation rule');
            // get A and B
            $result['Calculation rules'][$calculationRuleIndex]=['A'=>0,'Operation'=>'','B'=>0,'Result'=>''];
            foreach(['A','B'] as $index){
                $key=$rule['Content']['"'.$index.'" selected by...']??'';
                $debugArr[]=['ruleEntryId'=>$calculationRuleIndex,'key'=>$key];
                if (strcmp($key,'useValue')===0){
                    $value[$index]=$rule['Content']['Default value "'.$index.'"'];
                } else if (isset($ruleResults[$key])){
                    $value[$index]=$ruleResults[$key];
                } else if (isset($flatSourceEntry[$key])){
                    $value[$index]=$flatSourceEntry[$key];
                } else {
                    $value[$index]=$rule['Content']['Default value "'.$index.'"'];
                }
                $result['Calculation rules'][$calculationRuleIndex][$index]=$value[$index];
            }
            $ruleResults[$calculationRuleIndex]=$this->oc['SourcePot\Datapool\Foundation\Computations']->operation($value['A'],$value['B'],$rule['Content']['Operation']);
            $sourceEntry=$this->addValue2flatEntry($sourceEntry,$rule['Content']['Target column'],$rule['Content']['Target key'],$ruleResults[$calculationRuleIndex],$rule['Content']['Target data type']);
            $result['Calculation rules'][$calculationRuleIndex]['Operation']=$rule['Content']['Operation'];
            $result['Calculation rules'][$calculationRuleIndex]['Result']=$ruleResults[$calculationRuleIndex];
        }
        // loop through conditional value rules
        foreach($base['conditionalvaluerules']??[] as $ruleEntryId=>$rule){
            $value='NaN';
            $conditionalvalueRuleIndex=$this->ruleId2ruleIndex($ruleEntryId,'Conditionalvalue rule');
            if (isset($ruleResults[$rule['Content']['Condition']])){
                $value=$ruleResults[$rule['Content']['Condition']];
            } else if (isset($flatSourceEntry[$rule['Content']['Condition']])){
                $value=$flatSourceEntry[$rule['Content']['Condition']];
            } else {
                $ruleResults[$conditionalvalueRuleIndex]=FALSE;
            }
            if (!isset($ruleResults[$conditionalvalueRuleIndex])){
                $ruleResults[$conditionalvalueRuleIndex]=$this->oc['SourcePot\Datapool\Foundation\Computations']->isTrueConst($value,$rule['Content']['Use value if...']);
            }
            $log.='|'.$conditionalvalueRuleIndex.' = '.intval($ruleResults[$conditionalvalueRuleIndex]);
            if ($ruleResults[$conditionalvalueRuleIndex]){
                if (strlen($rule['Content']['Value'])>0){
                    $useValue=$rule['Content']['Value'];
                } else if (isset($flatSourceEntry[$rule['Content']['Use']])){
                    $useValue=$flatSourceEntry[$rule['Content']['Use']];
                }
                $sourceEntry=$this->addValue2flatEntry($sourceEntry,$rule['Content']['Target column'],$rule['Content']['Target key'],$useValue,$rule['Content']['Target data type']);
            }
            $result['Conditional value rules'][$conditionalvalueRuleIndex]=[
                'Condition'=>$value,
                'Use value if'=>\SourcePot\Datapool\Foundation\Computations::COMPARE_TYPES_CONST[$rule['Content']['Use value if...']],
                'Condition met'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleResults[$conditionalvalueRuleIndex]),
            ];
        }
        // loop through failure rules
        $isFailure=FALSE;
        foreach($base['failurerules']??[] as $ruleEntryId=>$rule){
            $failureRuleIndex=$this->ruleId2ruleIndex($ruleEntryId,'Failure rule');
            if (isset($ruleResults[$rule['Content']['Value']])){
                $value=$ruleResults[$rule['Content']['Value']];
            } else if (isset($flatSourceEntry[$rule['Content']['Value']])){
                $value=$flatSourceEntry[$rule['Content']['Value']];
            } else {
                $ruleResults[$failureRuleIndex]=FALSE;
            }
            if (!isset($ruleResults[$failureRuleIndex])){
                $ruleResults[$failureRuleIndex]=$this->oc['SourcePot\Datapool\Foundation\Computations']->isTrue($value,$rule['Content']['Compare value'],$rule['Content']['Failure if Result...']);
            }
            $log.='|'.$failureRuleIndex.' = '.intval($ruleResults[$failureRuleIndex]);
            if ($ruleResults[$failureRuleIndex]){
                $isFailure=TRUE;
            }
            $result['Failure rules'][$failureRuleIndex]=[
                'Value'=>$value,
                'Failure if Result'=>\SourcePot\Datapool\Foundation\Computations::CONDITION_TYPES[$rule['Content']['Failure if Result...']],
                'Compare value'=>$rule['Content']['Compare value'],
                'Condition met'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleResults[$failureRuleIndex]),
            ];
        }
        // wrapping up
        foreach($sourceEntry as $key=>$value){
            if (mb_strpos($key,'Content')===0 || mb_strpos($key,'Params')===0){continue;}
            if (!is_array($value)){continue;}
            foreach($value as $subKey=>$subValue){
                $subValue=$this->oc['SourcePot\Datapool\Foundation\Computations']->arr2value($subValue);
                if (is_array($subValue)){$subValue=implode('|',$subValue);}
                $value[$subKey]=$subValue;
            }
            // set order of array values
            ksort($value);
            $sourceEntry[$key]=implode('|',$value);
        }
        if ($isFailure){
            $result['Statistics']['Entries moved (failure)']['Value']++;
            $sourceEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Content']['Target on failure']],TRUE,$testRun,$params['Content']['Keep source entries']??FALSE);
            if (!isset($result['Sample result (failure)']) || mt_rand(0,100)>90){
                $result['Sample result (failure)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($sourceEntry);
            }    
        } else {
            $result['Statistics']['Entries moved (success)']['Value']++;
            $sourceEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Content']['Target on success']],TRUE,$testRun,$params['Content']['Keep source entries']??FALSE);
            if (!isset($result['Sample result (success)']) || mt_rand(0,100)>90){
                $result['Sample result (success)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($sourceEntry);
            }        
        }
        return $result;
    }
    
    private function addValue2flatEntry($entry,$baseKey,$key,$value,$dataType):array
    {
        if (!isset($entry[$baseKey])){$entry[$baseKey]=[];}
        if (!is_array($entry[$baseKey]) && empty($key)){$entry[$baseKey]=[];}
        $newValue=[$key=>$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($value,$dataType)];
        if (is_array($entry[$baseKey])){
            $entry[$baseKey]=array_replace_recursive($entry[$baseKey],$newValue);
        } else {
            $entry[$baseKey]=$newValue;
        }
        return $entry;
    }
    
    private function ruleId2ruleIndex($ruleId,$ruleType='Calculation rules')
    {
        $ruleIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleId);
        $ruleIndex=$ruleType.' '.$ruleIndex;
        return $ruleIndex;
    }

}
?>