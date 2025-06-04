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
    
    private $entryTable='';
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            ];

    private const OPERATIONS=['+'=>'+','-'=>'-','*'=>'*','/'=>'/','<'=>'<','=='=>'==','!='=>'!=','>'=>'>','&'=>'&','|'=>'|','contains'=>'contains','!contains'=>'!contains'];
    private const FAILURE_CONDITIONS=['stripos'=>'&#8839;','stripos!'=>"&#8837;",'lt'=>'&#60;','le'=>'&#8804;','eq'=>'&#61;','ne'=>'&#8800;','gt'=>'&#62;','ge'=>'&#8805;'];
    private const CONDITIONAL_VALUE=['lt'=>'&#60; 0','gt'=>"&#62; 0",'eq'=>'&#61; 0','ne'=>'&#8800; 0'];
        
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
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Calculate','generic',$callingElement,['method'=>'getCalcEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }

    private function getCalcEntriesInfo($callingElement):string
    {
        $matrix=[];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?']);
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
            if ($caption==='Calculate statistics'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getCalcEntriesSettings($callingElement):string
    {
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Calculate entries settings','generic',$callingElement,['method'=>'getCalcEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
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
        $contentStructure=['Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>[0=>'No, move entries',1=>'Yes, copy entries']],
                        'Target on success'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
                        'Target on failure'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
                        ];
        // get selctor
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']['Content']=[];
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
        $arr['caption']='Calculation control';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        if (empty($arr['selector']['Content'])){$row['trStyle']=['background-color'=>'#a00'];}
        $matrix=['Parameter'=>$row];
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }
    
    private function calculationRules(array $callingElement):string
    {
        $addKeys=(isset($this->ruleOptions[mb_strtolower(__FUNCTION__)]))?$this->ruleOptions[mb_strtolower(__FUNCTION__)]:[];
        $contentStructure=['"A" selected by...'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>$addKeys],
                        'Default value "A"'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
                        'Operation'=>['method'=>'select','excontainer'=>TRUE,'value'=>'+','options'=>self::OPERATIONS],
                        '"B" selected by...'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>$addKeys],
                        'Default value "B"'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
                        ''=>['method'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE],
                        'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE],
                        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE],
                        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
                        ];
        $contentStructure['"A" selected by...']+=$callingElement['Content']['Selector'];
        $contentStructure['"B" selected by...']+=$callingElement['Content']['Selector'];
        $contentStructure['Target column']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Calculation rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function failureRules(array $callingElement):string
    {
        $addKeys=(isset($this->ruleOptions['calculationrules']))?$this->ruleOptions['calculationrules']:[];
        $contentStructure=['Value'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>current($addKeys),'addSourceValueColumn'=>FALSE,'addColumns'=>$addKeys],
                            'Failure if Result...'=>['method'=>'select','excontainer'=>TRUE,'value'=>'stripos','keep-element-content'=>TRUE,'options'=>self::FAILURE_CONDITIONS],
                            'Compare value'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
                            ];
        $contentStructure['Value']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Failure rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function conditionalValueRules(array $callingElement):string
    {
        $addKeys=(isset($this->ruleOptions['calculationrules']))?$this->ruleOptions['calculationrules']:[];
        $contentStructure=['Condition'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>current($addKeys),'addSourceValueColumn'=>FALSE,'addColumns'=>$addKeys],
                        'Use value if...'=>['method'=>'select','excontainer'=>TRUE,'value'=>'eq','keep-element-content'=>TRUE,'options'=>self::CONDITIONAL_VALUE],
                        ''=>['method'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'style'=>'font-size:20px;','excontainer'=>TRUE],
                        'Use'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>$addKeys],
                        'Value'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
                        'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE],
                        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE],
                        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
                        ];
        $contentStructure['Condition']+=$callingElement['Content']['Selector'];
        $contentStructure['Use']+=$callingElement['Content']['Selector'];
        $contentStructure['Target column']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
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
        $result=['Calculate statistics'=>['Entries'=>['value'=>0],
                                        'Failure'=>['value'=>0],
                                        'Success'=>['value'=>0],
                                        ]
                    ];
        // loop through entries
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            if ($sourceEntry['isSkipRow']){
                $result['Calculate statistics']['Skip rows']['value']++;
                continue;
            }
            $result=$this->calcEntry($base,$sourceEntry,$result,$testRun);
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }
    
    private function calcEntry(array $base,array $sourceEntry,array $result,bool $testRun)
    {
        $debugArr=[];
        $log='';
        $params=current($base['calculationparams']);
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        // loop through calculation rules
        $ruleResults=[];
        if (!empty($base['calculationrules'])){
            foreach($base['calculationrules'] as $ruleEntryId=>$rule){
                $calculationRuleIndex=$this->ruleId2ruleIndex($ruleEntryId,'Calculation rule');
                $result['Calc rule'][$calculationRuleIndex]=['A'=>0,'Operation'=>'','B'=>0,'Result'=>''];
                foreach(['A','B'] as $index){
                    $key=$rule['Content']['"'.$index.'" selected by...'];
                    $debugArr[]=['ruleEntryId'=>$calculationRuleIndex,'key'=>$key];
                    if (strcmp($key,'useValue')===0){
                        $value[$index]=floatval($rule['Content']['Default value "'.$index.'"']);
                    } else if (isset($ruleResults[$key])){
                        $value[$index]=floatval($ruleResults[$key]);
                    } else if (isset($flatSourceEntry[$key])){
                        $value[$index]=floatval($flatSourceEntry[$key]);
                    } else {
                        $value[$index]=floatval($rule['Content']['Default value "'.$index.'"']);
                    }
                    $result['Calc rule'][$calculationRuleIndex][$index]=$value[$index];
                }
                $ruleResults[$calculationRuleIndex]=match($rule['Content']['Operation']){
                        '+'=>$value['A']+$value['B'],
                        '-'=>$value['A']-$value['B'],
                        '*'=>$value['A']*$value['B'],
                        '/'=>($value['B']==0)?FALSE:($value['A']/$value['B']),
                        '%'=>($value['B']==0)?FALSE:($value['A']%$value['B']),
                        '>'=>intval(boolval($value['A']>$value['B'])),
                        '=='=>intval(boolval($value['A']==$value['B'])),
                        '!='=>intval(boolval($value['A']!=$value['B'])),
                        '<'=>intval(boolval($value['A']<$value['B'])),
                        '&'=>intval($value['A']) & intval($value['B']),
                        '|'=>intval($value['A']) | intval($value['B']),
                        'contains'=>stripos(strval($value['A']),strval($value['B']))!==FALSE,
                        '!contains'=>stripos(strval($value['A']),strval($value['B']))===FALSE,
                        };
                $sourceEntry=$this->addValue2flatEntry($sourceEntry,$rule['Content']['Target column'],$rule['Content']['Target key'],$ruleResults[$calculationRuleIndex],$rule['Content']['Target data type']);
                $result['Calc rule'][$calculationRuleIndex]['Operation']=$rule['Content']['Operation'];
                $result['Calc rule'][$calculationRuleIndex]['Result']=$ruleResults[$calculationRuleIndex];
            }
        }
        // loop through conditional value rules
        if (!empty($base['conditionalvaluerules'])){
            foreach($base['conditionalvaluerules'] as $ruleEntryId=>$rule){
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
                    $ruleResults[$conditionalvalueRuleIndex]=match($rule['Content']['Use value if...']){
                        'lt'=>floatval($value)<0,
                        'gt'=>floatval($value)>0,
                        'eq'=>intval($value)==0,
                        'ne'=>intval($value)!=0,
                    };
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
                $result['Conditional value rules'][$conditionalvalueRuleIndex]=['Condition'=>$value,
                                                                       'Use value if'=>self::CONDITIONAL_VALUE[$rule['Content']['Use value if...']],
                                                                       'Condition met'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleResults[$conditionalvalueRuleIndex]),
                                                                       ];
            }
        }
        // loop through failurerules rules
        $isFailure=FALSE;
        if (!empty($base['failurerules'])){
            foreach($base['failurerules'] as $ruleEntryId=>$rule){
                $failureRuleIndex=$this->ruleId2ruleIndex($ruleEntryId,'Failure rule');
                if (isset($ruleResults[$rule['Content']['Value']])){
                    $value=$ruleResults[$rule['Content']['Value']];
                } else if (isset($flatSourceEntry[$rule['Content']['Value']])){
                    $value=$flatSourceEntry[$rule['Content']['Value']];
                } else {
                    $ruleResults[$failureRuleIndex]=FALSE;
                }
                if (!isset($ruleResults[$failureRuleIndex])){
                    $ruleResults[$failureRuleIndex]=match($rule['Content']['Failure if Result...']){
                        'stripos'=>stripos($value,$rule['Content']['Compare value'])!==FALSE,
                        'stripos!'=>stripos($value,$rule['Content']['Compare value'])===FALSE,
                        'lt'=>floatval($value)<floatval($rule['Content']['Compare value']),
                        'le'=>floatval($value)<=floatval($rule['Content']['Compare value']),
                        'gt'=>floatval($value)>floatval($rule['Content']['Compare value']),
                        'ge'=>floatval($value)>=floatval($rule['Content']['Compare value']),
                        'eq'=>floatval($value)==floatval($rule['Content']['Compare value']),
                        'ne'=>floatval($value)!=floatval($rule['Content']['Compare value']),
                    };
                }
                $log.='|'.$failureRuleIndex.' = '.intval($ruleResults[$failureRuleIndex]);
                if ($ruleResults[$failureRuleIndex]){$isFailure=TRUE;}
                $result['Failure rules'][$failureRuleIndex]=['Value'=>$value,
                                                           'Failure if Result'=>self::FAILURE_CONDITIONS[$rule['Content']['Failure if Result...']],
                                                           'Compare value'=>$rule['Content']['Compare value'],
                                                           'Condition met'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleResults[$failureRuleIndex]),
                                                           ];
            }
        }
        // wrapping up
        foreach($sourceEntry as $key=>$value){
            if (mb_strpos($key,'Content')===0 || mb_strpos($key,'Params')===0){continue;}
            if (!is_array($value)){continue;}
            foreach($value as $subKey=>$subValue){
                $subValue=$this->oc['SourcePot\Datapool\Tools\MiscTools']->valueArr2value($subValue);
                if (is_array($subValue)){$subValue=implode('|',$subValue);}
                $value[$subKey]=$subValue;
            }
            // set order of array values
            ksort($value);
            $sourceEntry[$key]=implode('|',$value);
        }
        $result['Calculate statistics']['Entries']['value']++;
        if ($isFailure){
            $result['Calculate statistics']['Failure']['value']++;
            $sourceEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Content']['Target on failure']],TRUE,$testRun,$params['Content']['Keep source entries']??FALSE);
            if (!isset($result['Sample result (failure)']) || mt_rand(0,100)>90){
                $result['Sample result (failure)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($sourceEntry);
            }    
        } else {
            $result['Calculate statistics']['Success']['value']++;
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
        $newValue=[$key=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($value,$dataType)];
        if (is_array($entry[$baseKey])){
            $entry[$baseKey]=array_replace_recursive($entry[$baseKey],$newValue);
        } else {
            $entry[$baseKey]=$newValue;
        }
        return $entry;
    }
    
    private function ruleId2ruleIndex($ruleId,$ruleType='Calc rule')
    {
        $ruleIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleId);
        $ruleIndex=$ruleType.' '.$ruleIndex;
        return $ruleIndex;
    }

}
?>