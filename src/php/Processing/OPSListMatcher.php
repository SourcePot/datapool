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

class OPSListMatcher implements \SourcePot\Datapool\Interfaces\Processor{

    private $oc;

    private const OPS_READER_CORE_PATH='/var/www/www-workspace/wallenhauer/OPS-Reader-dev_datapool/Core/';
    private const OPS_READER_CORE_REQUIRED_FILES=[
        'ListMatcher.php',
        'OpsInterface.php',
        'OpsReader.php',
        'data/ListMatcher/ListMatcherInput.php',
        'data/ListMatcher/ListMatcherOutput.php',
        'data/Ops/OpsNumberSearchOutput.php',
        'data/Ops/OpsFamilySearchOutput.php',
        'Response.php',
        'enums/FailedFamilySearchKeys.php',
    ];

    private const INFO_MATRIX=[
        'Caption'=>['Comment'=>'Open Patent Service ListMatacher Wrapper'],
        'Description'=>['Comment'=>'This processor is a wrapper for the DBaur22/OPS-Reader ListMatcher class. The list entries are matched with patent cases if they belong to the same patent family. The Open Patent Service patent family definition is employed.'],
    ];

    private const CREDENTIALS_DEF=[
        'Content'=>[
            'app_name'=>['@tag'=>'input','@type'=>'text','@default'=>'Datapool','@excontainer'=>TRUE],
            'consumer_key'=>['@tag'=>'input','@type'=>'text','@default'=>'...','@excontainer'=>TRUE],
            'consumer_secret_key'=>['@tag'=>'input','@type'=>'password','@default'=>'','@excontainer'=>TRUE],
            ''=>['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'],
        ],
    ];

    private const CONTENT_STRUCTURE_PARAMS=[
        // add content structure of parameters here...
        'Keep failed cases'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['No','Yes']],
        'Cases to match'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
        'Target matched list entries'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
        'Target cases without match'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
    ];

    private const CONTENT_STRUCTURE_LIST_RULES=[
        // add content structure of rules here...
        'Entry key'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name',],
        'RegExp match'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'.+','excontainer'=>TRUE],
        'RegExp match index'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>[0,1,2,3,4,5,6,7,8,9],'keep-element-content'=>TRUE],
        'Delete by RegExp match'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'','excontainer'=>TRUE],
        'Glue'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'','excontainer'=>TRUE],
    ];

    private const CONTENT_STRUCTURE_CASE_RULES=[
        // add content structure of rules here...
        'Key'=>['method'=>'select','excontainer'=>TRUE,'value'=>'Countrycode','options'=>['Countrycode'=>'Countrycode','Family'=>'Family','Applicationnumber'=>'Applicationnumber','Issuenumber'=>'Issuenumber','Publicationnumber'=>'Publicationnumber',]],
        'Entry key'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name',],
        'RegExp match'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'.+','excontainer'=>TRUE],
        'RegExp match index'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>[0,1,2,3,4,5,6,7,8,9],'keep-element-content'=>TRUE],
        'Delete by RegExp match'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'','excontainer'=>TRUE],
    ];

    private $listMatcherObj=NULL;

    private $entryTable='';
    private $entryTemplate=[];

    private $list=[];

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
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion('!'.__CLASS__,self::CREDENTIALS_DEF);
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate(){
        return $this->entryTemplate;
    }

    /**
     * This method is links the processor with the canvaselement
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method
     * @param string $action Selects the requested process to be run
     * @return string|bool Return the html-string or TRUE callingElement does not exist
     */
     public function dataProcessor(array $callingElementSelector=[],string $action='info')
     {
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->run($callingElement,$testRunOnly=FALSE),
                'test'=>$this->run($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getWidget($callingElement),
                'settings'=>$this->getSettings($callingElement),
                'info'=>$this->getInfo($callingElement),
            };
        }
    }

    private function getWidget($callingElement)
    {
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('OPSListMatcher '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'getWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }

    private function getInfo($callingElement):string
    {
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>self::INFO_MATRIX,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Help']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?','open'=>FALSE]);
        return $html;
    }

    public function getWidgetHtml($arr):array
    {
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->run($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->run($arr['selector'],TRUE);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'OPS ListMatcher Processor']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            $appArr['open']=$caption==='Statistics';
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getSettings($callingElement):string
    {
        // credentials from
        $credentials=$this->getCredentials($callingElement);
        $html=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($credentials,FALSE);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'OPS Credentials','open'=>FALSE]);
        // paremeters and rules
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Processor parameters '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'processorParams','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('List rules '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'listRules','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Processor rules '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'casesRules','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }

    public function processorParams($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->processorParamsHtml($callingElement);
        // get credentials widget

        return $arr;
    }

    public function listRules($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->listRulesHtml($callingElement);
        return $arr;
    }

    public function casesRules($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->casesRulesHtml($callingElement);
        return $arr;
    }

    private function processorParamsHtml($callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Processor control';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }

    private function listRulesHtml(array $callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_LIST_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='List rules: creates an ip number used for the match';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
    
    private function casesRulesHtml(array $callingElement):string
    {
        $casesCanvasElement=$this->casesCanvasElement($callingElement);
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_CASE_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$casesCanvasElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Patent case rules: mapping patent case fields to ListMatcher fields';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    /******************************************************************************************************************************************
    *   Loop through entries and process entries based on processor parameters, rules
    *   The processor runs on all entries selected be the Canvas element (callingElement)
    */

    private function run(array $callingElement,bool $testRun=FALSE):array
    {
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,[]);
        // initialize statistic and result array
        $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->initProcessorResult(__CLASS__,$testRun,current($base['processorparamshtml'])['Content']['Keep failed cases']??FALSE);
        // load and create ListMatcher
        $list=[];
        $failedToLoadRequiredFiles=FALSE;
        foreach(self::OPS_READER_CORE_REQUIRED_FILES as $class){
            $required=self::OPS_READER_CORE_PATH.$class;
            if (!is_file($required)){
                $failedToLoadRequiredFiles=TRUE;
                break;
            }
            require_once($required);
        }
        if ($failedToLoadRequiredFiles){
            $result['OPS-Reader ListMatcher']['Error']=['value'=>'ListMatcher not found at "'.self::OPS_READER_CORE_PATH.'".</br>Please check the source code of class "'.__CLASS__.'".</br>Set the const OPS_READER_CORE_PATH to a valid path.'];
        } else {
            try{
                $list=$this->getList($callingElement);
                $result['List']=$list['debug'];
                unset($list['debug']);
                $this->list=$list;
                $credentials=$this->getCredentials($callingElement);
                $this->listMatcherObj=new \Core\ListMatcher($list,$credentials['Content']);
            } catch(\Exception $e){
                $result['OPS-Reader ListMatcher']['Error']=['value'=>$e->getMessage()];
            }
            if (empty($result['OPS-Reader ListMatcher error'])){
                // loop through entries
                $casesSelector=$this->getCasesSelector($callingElement);
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($casesSelector,TRUE) as $caseEntry){
                    $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->updateProcessorResult($result,$caseEntry);
                    if ($result['cntr']['timeLimitReached']){
                        break;
                    } else if (!$result['cntr']['isSkipRow']){
                        $result=$this->processCase($base,$caseEntry,$result,$testRun,$callingElement);
                    }
                }
            }
        }
        return $this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeProcessorResult($result);
    }

    private function processCase(array $base,array $caseEntry,array $result,bool $testRun,array $callingElement):array
    {
        // recover basic context data
        $processorParams=current($base['processorparamshtml'])['Content'];
        // recover the entry selector defined by the selected Canves element Selector
        $targetSelectorSuccess=$base['entryTemplates'][$processorParams['Target matched list entries']]??[];
        $targetSelectorFailure=$base['entryTemplates'][$processorParams['Target cases without match']]??[];
        // get case keys based casesRules for list match
        $case=['EntryId'=>$caseEntry['EntryId'],'Family'=>'','Countrycode'=>'','Applicationnumber'=>'','Publicationnumber'=>'','Issuenumber'=>'',];
        $result['List matcher']=$result['List matcher']??[];
        $count=count($result['List matcher']);
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($caseEntry);
        foreach($base['casesruleshtml'] as $ruleEntryId=>$rule){
            $key=$rule['Content']['Key'];
            $ruleValueIn=$flatSourceEntry[$rule['Content']['Entry key']]??'';
            $result['List matcher'][$count][$key.' &rarr; ']=$ruleValueIn;
            preg_match('/'.($rule['Content']['RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/',$ruleValueIn,$match);
            $ruleValueOut=preg_replace('/'.($rule['Content']['Delete by RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/','',$match[$rule['Content']['RegExp match index']]??'').($rule['Content']['Glue']??'');
            $result['List matcher'][$count][$key]=$ruleValueOut;
            $case[$key]=$ruleValueOut;
        }
        $matchSuccess=FALSE;
        // OPS List Matcher
        $listSelector=$callingElement['Content']['Selector'];
        $ListMatcherInput=new \Core\data\ListMatcher\ListMatcherInput($case['EntryId'],$case['Family'],$case['Countrycode'],$case['Applicationnumber'],$case['Publicationnumber'],$case['Issuenumber']);
        $matches=$this->listMatcherObj->matchUnycomEntry($ListMatcherInput,$format='docdb');
        foreach($matches??[] as $listMatch){
            if ($listMatch->opsFailure){break;}
            if (!$listMatch->matchSuccess){continue;}
            $matchSuccess=TRUE;
            $result['List matcher'][$count]['Match']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($listMatch->matchSuccess);
            $result['List matcher'][$count]['List number']=$this->list[$listMatch->entryIdRoyaltyEntry];
            $result['List matcher'][$count]['Errors']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(!empty($listMatch->errors),['element-content'=>implode('</br>',$listMatch->errors)?:'None']);
            $listSelector['EntryId']=$listMatch->entryIdRoyaltyEntry;
            $listEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($listSelector,TRUE);
            $listEntry['Content']['Match']=$listMatch->to_array();
            $listEntry['Content']['Matched case']=$caseEntry['Content'];
            $this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($listEntry,$targetSelectorSuccess,TRUE,$testRun,FALSE);
            $result['Statistics']['Entries moved (success)']['Value']++;
        }
        // move processed entries
        if (!$matchSuccess && !$listMatch->opsFailure??FALSE){
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($caseEntry,$targetSelectorFailure,TRUE,$testRun,!empty($processorParams['Keep failed cases']));
            $result['Statistics']['Entries moved (failure)']['Value']++;
        }
        return $result;
    }

    private function getCredentials($callingElement):array
    {
        $credentials=['Class'=>__CLASS__,'EntryId'=>$callingElement['EntryId'],'Name'=>'&#8688;'];
        $credentials['Content']=['app_name'=>'Datapool','consumer_key'=>'','consumer_secret_key'=>'',];
        $credentials=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($credentials,TRUE);
        return $credentials;
    }
    
    private function getList($callingElement):array
    {
        $list=['debug'=>[]];
        $listRules=$this->getListRules($callingElement);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $listEntry){
            $flatListEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($listEntry);
            $listValue='';
            $list['debug']=$list['debug']??[];
            $count=count($list['debug']);
            foreach($listRules as $ruleId=>$rule){
                $ruleKey=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($ruleId)[0];
                $ruleValueIn=$flatListEntry[$rule['Content']['Entry key']]??'';
                $list['debug'][$count][$ruleKey]=$ruleValueIn;
                preg_match('/'.($rule['Content']['RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/',$ruleValueIn,$match);
                $ruleValueOut=preg_replace('/'.($rule['Content']['Delete by RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/','',$match[$rule['Content']['RegExp match index']]??'').($rule['Content']['Glue']??'');
                $list['debug'][$count][$ruleKey].=' &rarr; '.$ruleValueOut;
                $listValue.=$ruleValueOut;
            }
            $listValue=trim($listValue,$rule['Content']['Glue']??'');
            $list[$flatListEntry['EntryId']]=$list['debug'][$count]['Result']=$listValue;
        }
        return $list;
    }

    private function getParams($callingElement):array
    {
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,[]);
        return current($base['processorparamshtml'])['Content']??[];
    }

    private function getListRules($callingElement):array
    {
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,[]);
        return $base['listruleshtml'];
    }

    private function casesCanvasElement($callingElement):array
    {
        $params=$this->getParams($callingElement);
        $casesCanvasElement=['EntryId'=>$params['Cases to match']??'','Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable()];
        return $this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($casesCanvasElement,TRUE)?:[];
    }

    private function getCasesSelector($callingElement):array
    {
        return $this->casesCanvasElement($callingElement)['Content']['Selector']??[];
    }
}
?>