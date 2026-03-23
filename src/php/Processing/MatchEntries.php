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

class MatchEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;

    private const MAX_TABLE_LENGTH=50;
    
    private const CONTENT_STRUCTURE_PARAMS=[
        'Column to match'=>['method'=>'keySelect','value'=>'Name','standardColumsOnly'=>FALSE,'excontainer'=>TRUE],
        'Match with'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
        'Match with column'=>['method'=>'keySelect','value'=>'Name','standardColumsOnly'=>FALSE,'excontainer'=>TRUE],
        'Match type'=>['method'=>'select','value'=>'unycom','options'=>[],'excontainer'=>TRUE],
        'Match probability'=>['method'=>'select','value'=>80,'options'=>[99=>'=100',90=>'>90',80=>'>80',70=>'>70',60=>'>60',50=>'>50',45=>'>45',40=>'>40',30=>'>30',25=>'>25'],'excontainer'=>TRUE],
        'Match failure'=>['method'=>'canvasElementSelect','addColumns'=>[''=>'...'],'excontainer'=>TRUE],
        'Match success'=>['method'=>'canvasElementSelect','addColumns'=>[''=>'...'],'excontainer'=>TRUE],
        'Combine content'=>['method'=>'select','value'=>1,'excontainer'=>TRUE,'options'=>['No','Yes']],
        'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>[0=>'No, move entries',1=>'Yes, copy entries']],
    ];

    private const CONTENT_STRUCTURE_RULES=[
        'On...'=>['method'=>'select','value'=>0,'options'=>['no match','match'],'excontainer'=>TRUE],
        'Map value or...'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        '...value selected by'=>['method'=>'keySelect','value'=>'useValue','addSourceValueColumn'=>TRUE,'excontainer'=>TRUE],
        'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Content','standardColumsOnly'=>TRUE],
        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
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
     * @return string|bool Return the html-string or TRUE callingElement does not exist
     */
    public function dataProcessor(array $callingElementSelector=[],string $action='info'){
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->runMatchEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runMatchEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getMatchEntriesWidget($callingElement),
                'settings'=>$this->getMatchEntriesSettings($callingElement),
                'info'=>$this->getMatchEntriesInfo($callingElement),
            };
        }
    }

    private function getMatchEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Matching','generic',$callingElement,['method'=>'getMatchEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }
    
    private function getMatchEntriesInfo($callingElement){
        $matrix=[];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?']);
        return $html;
    }

    public function getMatchEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runMatchEntries($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runMatchEntries($arr['selector'],TRUE);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Match entries']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            if ($caption==='Matching'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getMatchEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Matching entries settings','generic',$callingElement,['method'=>'getMatchEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Conditional mapping settings','generic',$callingElement,['method'=>'getConditionalMappingRulesHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getMatchEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->matchingParams($arr['selector']);
        return $arr;
    }
    
    private function matchingParams($callingElement){
        $return=['html'=>'','Parameter'=>[],'result'=>[]];
        if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure['Match type']['options']=$this->oc['SourcePot\Datapool\Foundation\Computations']->getMatchTypes();
        // add to match element to content structur
        $settings=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,[]);
        $params=current($settings[strtolower(__FUNCTION__)]??[]);
        $matchElement=['Source'=>$callingElement['Source'],'EntryId'=>$params['Content']['Match with']??''];
        $matchElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($matchElement,TRUE);
        $contentStructure['Match with column']+=$matchElement['Content']['Selector']??[];
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Select column to match and the success/failure targets';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }

    public function getConditionalMappingRulesHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->conditionalMappingRules($arr['selector']);
        return $arr;
    }
    
    private function conditionalMappingRules($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Map on match rules';
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
    }
    
    public function runMatchEntries($callingElement,$testRun=TRUE){
        $base=['matchingparams'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->initProcessorResult(__CLASS__,$testRun,current($base['matchingparams'])['Content']['Keep source entries']??FALSE);
        $result['Matches']=[];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $entry){
            $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->updateProcessorResult($result,$entry);
            if ($result['cntr']['timeLimitReached']){
                break;
            } else if (!$result['cntr']['isSkipRow']){
                $result=$this->matchEntry($base,$entry,$result,$testRun);
            }
        }
        return $this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeProcessorResult($result);
    }
    
    private function matchEntry($base,$entry,$result,$testRun){
        $params=current($base['matchingparams']);
        if (!isset($base['entryTemplates'][$params['Content']['Match success']]) || !isset($base['entryTemplates'][$params['Content']['Match failure']])){
            $result['Errors']['Function '.__FUNCTION__]=['Value'=>'Params <i>Match success</i> or <i>Match failure</i> not set, match skipped.'];
            $result['Statistics']['errors']['Value']++;
            return $result;
        }
        $bestMatchCanvasElement=current($this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements(__CLASS__,$params['Content']['Match with']));
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
        // get best match
        $needle=$flatEntry[$params['Content']['Column to match']]??'__NO_VALUE__';
        $bestMatch=$this->oc['SourcePot\Datapool\Foundation\Computations']->matchEntry($needle,$base['entryTemplates'][$params['Content']['Match with']],$params['Content']['Match with column'],$params['Content']['Match type'],TRUE);
        // process best match
        $probability=round(100*$bestMatch['Content']['match']['probability']);
        $entry['Params']['Processed'][__CLASS__]=$probability;
        $bestMatchKey='Best match';
        if ($bestMatchCanvasElement){
            $flatMatchKey=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($params['Content']['Match with column']??'');
            $bestMatchKey.='<br/>'.$bestMatchCanvasElement['Content']['Style']['Text'].'['.$flatMatchKey.']';
        }
        if (intval($params['Content']['Match probability'])<$probability){
            // successful match
            $takeSample=(mt_rand(0,100)>70) || empty($result['On match mapping (sample)']);
            if (!empty($params['Content']['Combine content'])){
                $entry['Content']=array_merge($entry['Content'],$bestMatch['Content']);
            }
            $entry=$this->mapArr($base,$entry,TRUE);
            if ($takeSample){
                $result['On match mapping (sample)']=$entry['__MAP_ARR__'];
                unset($entry['__MAP_ARR__']);
            }
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$base['entryTemplates'][$params['Content']['Match success']],TRUE,$testRun,$params['Content']['Keep source entries']);
            if ($takeSample){
                $matchEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2entry($entry??[]);
                $result['Sample result <b>Match</b>']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($matchEntry);
            }
            $result['Statistics']['Entries moved (success)']['Value']++;
            if (count($result['Matches'])<self::MAX_TABLE_LENGTH){
                $flatBestMatch=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($bestMatch);
                if (isset($flatBestMatch[$params['Content']['Match with column']])){
                    $result['Matches'][$needle]=[$bestMatchKey=>$flatBestMatch[$params['Content']['Match with column']],'Match [%]'=>$probability,'Match'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(TRUE)];
                }
            }
        } else {
            // failed match
            $takeSample=(mt_rand(0,100)>70) || empty($result['No match mapping (sample)']);
            $entry=$this->mapArr($base,$entry,FALSE);
            if ($takeSample){
                $result['No match mapping (sample)']=$entry['__MAP_ARR__'];
                unset($entry['__MAP_ARR__']);
            }
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$base['entryTemplates'][$params['Content']['Match failure']],TRUE,$testRun,$params['Content']['Keep source entries']);
            if ($takeSample){
                $noMatchEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2entry($entry??[]);
                $result['Sample result <b>No match</b>']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($noMatchEntry);
            }
            $result['Statistics']['Entries moved (failure)']['Value']++;
            if (count($result['Matches'])<self::MAX_TABLE_LENGTH){
                $flatBestMatch=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($bestMatch);
                if (isset($flatBestMatch[$params['Content']['Match with column']])){
                    $result['Matches'][$needle]=[$bestMatchKey=>$flatBestMatch[$params['Content']['Match with column']],'Match [%]'=>$probability,'Match'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(FALSE)];
                } else {
                    $result['Matches'][$needle]=[$bestMatchKey=>'','Match [%]'=>0,'Match'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(FALSE)];
                }
            }
        }
        return $result;
    }

    private function mapArr($base,$entry,$isMatch):array
    {
        $mapResult=[];
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
        $mappingRulse=$base['conditionalmappingrules']??[];
        foreach($mappingRulse as $ruleId=>$rule){
            $rowKey=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($ruleId)[0];
            $mapResult[$rowKey]=['Source key'=>'','Value'=>'','Data type'=>'','Target key'=>'','Result'=>''];
            if (boolval($rule['Content']['On...']) xor $isMatch){continue;}
            if (empty($rule['Content']['Map value or...'])){
                $sourceKey=$rule['Content']['...value selected by'];
                $value=$flatEntry[$rule['Content']['...value selected by']]??'';
            } else {
                $sourceKey='CONST';
                $value=$rule['Content']['Map value or...'];
            }
            $convertedValue=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($value,$rule['Content']['Target data type']);
            $flatEntry[$rule['Content']['Target column']][$rule['Content']['Target key']]=$convertedValue;
            if (is_array($convertedValue)){
                $resultValue=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($convertedValue);
                $resultValue=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$resultValue,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE]);
            } else {
                $resultValue=$convertedValue;
            }
            $mapResult[$rowKey]=['Source key'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($sourceKey??''),'Value'=>$value,'Data type'=>$rule['Content']['Target data type'],'Target key'=>$rule['Content']['Target column'].' &rarr; '.$rule['Content']['Target key'],'Result'=>$resultValue];
        }
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntry);
        $entry['__MAP_ARR__']=$mapResult;
        return $entry;
    }

}
?>