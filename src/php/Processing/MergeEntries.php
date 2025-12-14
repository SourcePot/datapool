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

    private const INFO_MATRIX=[
        ''=>['Value'=>'This processor merges entries into one or multiple target entries.'],
        'Description'=>['Value'=>'The target entry count depends on the amount of different "Map to"-values.<br/>Make sure that there are no entries left in the target canvas-element from any previous run, hen you trigger the processor.<br/>Otherwise a new run will be taking pre-existing values as a starting point.'],
    ];
    private $oc;

    private const OPERATIONS=[
        'number(A+B)'=>'number(A+B)','number(A-B)'=>'number(A-B)','number(A*B)'=>'number(A*B)','number(A/B)'=>'number(A/B)','number(A%B)'=>'number(A%B)',
        'money(A+B)'=>'money(A+B)','money(A-B)'=>'money(A-B)',
        'string(A B)'=>'string(A B)','string(A | B)'=>'string(A | B)','string(A, B)'=>'string(A, B)','string(A; B)'=>'string(A; B)',
        'byte(A&B)'=>'byte(A&B)','byte(A|B)'=>'byte(A|B)','byte(A^B)'=>'byte(A^B)',
    ];
    
    private const CONTENT_STRUCTURE_PARAMS=[
        'Map from'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','standardColumsOnly'=>FALSE,'addSourceValueColumn'=>TRUE],
        'Map to'=>['method'=>'select','excontainer'=>TRUE,'value'=>'Name','options'=>['Group'=>'Group','Folder'=>'Folder','Name'=>'Name'],],
        'Target'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
        'Target on failure'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
        'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>[0=>'No, move entries',1=>'Yes, copy entries']],
    ];
        
    private const CONTENT_STRUCTURE_RULES=[
        'New key: Content &rarr; ...'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Init value'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Operation'=>['method'=>'select','excontainer'=>TRUE,'value'=>'+','options'=>self::OPERATIONS,'keep-element-content'=>TRUE],
        'Key'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','standardColumsOnly'=>FALSE,'addSourceValueColumn'=>TRUE],
        'or const value'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
    ];
        
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
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>self::INFO_MATRIX,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info']);
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
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Merging settings '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'getMergeEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getMergeEntriesParamsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->mergingParams($arr['selector']);
        return $arr;
    }
    
    public function mergingParams($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Merging control: Select target for merged entries';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$callingElementArr['caption']]);
    }

    public function getMergeEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->mergingRules($arr['selector']);
        return $arr;
    }
    
    private function mergingRules($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Select rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
        
    public function runMergeEntries($callingElement,$testRun=1){
        $base=['mergingparams'=>[],'mergingrules'=>[],'processId'=>$callingElement['EntryId']];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=['Merged'=>[],];
        // loop through entries
        $selector=$callingElement['Content']['Selector'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $sourceEntry){
            $result=$this->mergeEntries($base,$sourceEntry,$result,$testRun);
        }
        $result['Statistics']=array_merge($this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix(),$result['Statistics']??[]);
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }
    
    public function mergeEntries($base,$sourceEntry,$result,$testRun){
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        $params=current($base['mergingparams'])['Content'];
        $failure=FALSE;
        // get tgarget selector and existing entry
        if (isset($flatSourceEntry[$params['Map from']])){
            $value=$flatSourceEntry[$params['Map from']];
            $targetSelector=array_merge(['Group'=>$sourceEntry['Group'],'Folder'=>$sourceEntry['Folder'],'Name'=>$sourceEntry['Name'],],$base['entryTemplates'][$params['Target']]);
            $targetSelector[$params['Map to']]=$value;
        } else {
            $failure=TRUE;
            $value='Failed';
            $targetSelector=array_merge(['Group'=>$sourceEntry['Group'],'Folder'=>$sourceEntry['Folder'],'Name'=>$sourceEntry['Name'],],$base['entryTemplates'][$params['Target on failure']]);
        }
        $result['Statistics'][$value]['Value']=($result['Statistics'][$value]['Value']??0)+1;
        $targetSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($targetSelector,['Source','Group','Folder','Name'],'0','',FALSE);
        $existingEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($targetSelector,TRUE,'Write',TRUE);
        $flatExistingEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($existingEntry??[]);
        // create target entry, apply rules
        $calcDebug=[];
        foreach($base['mergingrules'] as $ruleId=>$rule){
            $ruleIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($ruleId)[0];
            // get operand A
            $flatTargetKey='Content'.\SourcePot\Datapool\Root::ONEDIMSEPARATOR.$rule['Content']['New key: Content &rarr; ...'];
            $operandA=$flatExistingEntry[$flatTargetKey]??$rule['Content']['Init value']??'';
            // get operand B
            if ($rule['Content']['Key']=='useValue'){
                $operandB=$rule['Content']['or const value'];
            } else {
                $operandB=$flatSourceEntry[$rule['Content']['Key']];
            }
            $debugFlatKey='New key: Content &rarr; '.$rule['Content']['New key: Content &rarr; ...'];
            $calcDebug[$debugFlatKey]=['Previous value'=>$operandA,'Entry value'=>$operandB];
            // calculations
            $newValue='';
            $operation=$calcDebug[$debugFlatKey]['Operaton']=trim($rule['Content']['Operation'],'abcdefghijklmnopqrstuvwxyzAB()');
            if (strpos($rule['Content']['Operation'],'number')===0){
                $newValue=$this->oc['SourcePot\Datapool\Foundation\Computations']->operation($operandA,$operandB,$operation);
            } else if (strpos($rule['Content']['Operation'],'money')===0){
                $asset=new \SourcePot\Asset\Asset();
                $asset->setFromString(strval($operandA));
                $assetArrB=$asset->guessAssetFromString((string)$operandB);
                $newValue=match($operation){
                    '+'=>$asset->addAssetString($assetArrB['value string'],$assetArrB['unit'],$assetArrB['dateTime']),
                    '-'=>$asset->subAssetString($assetArrB['value string'],$assetArrB['unit'],$assetArrB['dateTime']),
                };
            } else if (strpos($rule['Content']['Operation'],'string')===0){
                $newValue=($result['Statistics'][$value]['Value']>1)?($operandA.$operation.$operandB):$operandB;
            } else if (strpos($rule['Content']['Operation'],'byte')===0){
                $newValue=match($operation){
                    '&'=>intval($operandA) & intval($operandB),
                    '|'=>intval($operandA) | intval($operandB),
                    '^'=>intval($operandA) ^ intval($operandB)
                };
            } else {
                $calcDebug[$debugFlatKey]['error']='Operation "'.$rule['Content']['Operation'].'" undefined';
            }
            $calcDebug[$debugFlatKey]['Result']=$rule['Content']['Data type'].'('.$newValue.')';
            $flatSourceEntry[$flatTargetKey]=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($newValue,$rule['Content']['Data type']);
            if (is_array($flatSourceEntry[$flatTargetKey])){
                $calcDebug[$debugFlatKey]['Result final']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($flatSourceEntry[$flatTargetKey]);
            } else {
                $calcDebug[$debugFlatKey]['Result final']=$flatSourceEntry[$flatTargetKey];
            }
        }
        $sourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatSourceEntry);
        $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetSelector,TRUE,$testRun,$params['Keep source entries']??FALSE);
        if ($failure){
            if (!isset($result['Sample result calculation '.$value]) || mt_rand(0,100)>90){
                $result['Sample result calculation '.$value]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
            }
        } else {
            $result['Sample result calculation '.$value]=$calcDebug;
            if (!isset($result['Sample result (success)']) || mt_rand(0,100)>90){
                $result['Sample result (success)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
            }
        }
        return $result;
    }
}
?>