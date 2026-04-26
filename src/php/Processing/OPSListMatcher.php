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

    private const INFO_MATRIX=[
        'Caption'=>['Comment'=>'<b>Open Patent Service ListMatacher Wrapper</b>'],
        'Description'=>['Comment'=>'This processor is a wrapper for the DBaur22/OPS-Reader ListMatcher class.<br/>You must have installed dependancies with composer-dbaur22.json successfully in order to use the DBaur22/OPS-Reader ListMatcher.<br/>The list entries are compared with the cases and identified as matches if they belong to the same patent family.<br/>The European Patent Office\'s definition of a patent family is used.'],
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
        'Target cases without OPS match'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
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

    private const MANUAL_FAMILY_MATCH_RULES=[
        // add content structure of rules here...
        'Case'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>[]],
        'Entry key for Family value'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Folder',],
        'ip number'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'','placeholder'=>'AT-E-620,003','excontainer'=>TRUE],
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
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>self::INFO_MATRIX,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Help']);
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
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Manual match rules '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'manualMatchRules','classWithNamespace'=>__CLASS__],[]);
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

    public function manualMatchRules($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->manualMatchRulesHtml($callingElement);
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

    private function manualMatchRulesHtml(array $callingElement):string
    {
        $casesCanvasElement=$this->casesCanvasElement($callingElement);
        // build content structure
        $contentStructure=self::MANUAL_FAMILY_MATCH_RULES;
        $casesSelector=$this->getCasesSelector($callingElement);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($casesSelector,TRUE) as $caseEntry){
            $contentStructure['Case']['options'][$caseEntry['EntryId']]=$caseEntry['Name'];
        }
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$casesCanvasElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Manual match (spelling, format of "ip number" must be exact)';
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
        $result=$this->manualMatch($base,$result,$testRun,$callingElement);
        if (class_exists('\ListMatcher\ListMatcher',TRUE)){
            $list=$this->getList($callingElement);
            $result['List']=$list['tmp'];
            unset($list['tmp']);
            $this->list=$list;
            $credentials=$this->getCredentials($callingElement);
            $this->listMatcherObj=new \ListMatcher\ListMatcher($list,$credentials['Content']??[]);
        } else {
            $result['ERROR OPS-Reader ListMatcher']['Error']=['value'=>'Class "\ListMatcher\ListMatcher" missing. You need to install/update with "composer-dbaur22.json"?'];
        }
        if (empty($result['ERROR OPS-Reader ListMatcher']['Error'])){
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
            // get remaining list entries
            foreach($this->listMatcherObj->getRemainingRoyaltyList()??[] as $listEntryId=>$listEntryValue){
                // manual match
                foreach($base['manualmatchruleshtml']??[] as $ruleEntryId=>$rule){
                    if (stripos($listEntryValue,$rule['Content']['List entry']??'__MISSING__')===FALSE){continue;}
                    $manualMatch=$rule['Content']['Family ref.'];
                    break;
                }
                $result['Remaining list entries'][$listEntryId]=['List EntryId'=>$listEntryId,'List entry value'=>$listEntryValue];   
            }
        }
        return $this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeProcessorResult($result);
    }

    private function manualMatch(array $base,array $result,bool $testRun,array $callingElement):array
    {
        $overflow=FALSE;
        $list=$this->getList($callingElement);
        unset($list['tmp']);
        $processorParams=current($base['processorparamshtml'])['Content'];
        $targetSelectorSuccess=$base['entryTemplates'][$processorParams['Target matched list entries']]??[];
        $caseSelector=$this->getCasesSelector($callingElement);
        $index=0;
        foreach($list as $listEntryId=>$listEntryValue){
            foreach($base['manualmatchruleshtml']??[] as $ruleEntryId=>$rule){
                $index++;
                // match successful, get case
                $caseSelector['EntryId']=$rule['Content']['Case'];
                $case=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($caseSelector,TRUE);
                $flatCase=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($case);
                $family=$flatCase[$rule['Content']['Entry key for Family value']];
                $result['Manual match'][$index]=['Case'=>$case['Name'],'Familiy'=>$family,'Provided ip number'=>$rule['Content']['ip number'],'=?= List ip number'=>$listEntryValue,'Familiy'=>$family,'Match'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(FALSE)];
                // try to match
                if (stripos($listEntryValue,$rule['Content']['ip number']??'__MISSING__')===FALSE){
                    if (count($result['Manual match'])>15){
                        unset($result['Manual match'][$index]);
                        $overflow=TRUE;
                    }
                    continue;
                }
                $result['Manual match'][$index]['Match']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(TRUE);
                // update and move matched list entry
                $listEntry=$this->getListEntry($callingElement,$listEntryId);
                $listEntry['Content']['Match']=['Family'=>$family];
                $listEntry['Content']['Matched case']=$case['Content'];
                $listEntry['Content']['Match type']='OPS match';
                // move successfully matched list entry
                $this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($listEntry,$targetSelectorSuccess,TRUE,$testRun,FALSE);
                $result['Statistics']['Entries moved (success)']['Value']++;
            }
        }
        if ($overflow){
            $result['Manual match'][$index]=['Case'=>'...','Familiy'=>'...','Provided ip number'=>'...','=?= List ip number'=>'...','Familiy'=>'...','Match'=>'...'];
        }
        return $result;
    }

    private function processCase(array $base,array $caseEntry,array $result,bool $testRun,array $callingElement):array
    {
        // recover basic context data
        $processorParams=current($base['processorparamshtml'])['Content'];
        // recover the entry selector defined by the selected Canves element Selector
        $targetSelectorSuccess=$base['entryTemplates'][$processorParams['Target matched list entries']]??[];
        $targetSelectorFailure=$base['entryTemplates'][$processorParams['Target cases without OPS match']]??[];
        // get case keys based casesRules for list match
        $case=['EntryId'=>$caseEntry['EntryId'],'Family'=>'','Countrycode'=>'','Applicationnumber'=>'','Publicationnumber'=>'','Issuenumber'=>'',];
        $result['List matcher']=$result['List matcher']??[];
        $index=count($result['List matcher']);
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($caseEntry);
        foreach($base['casesruleshtml'] as $ruleEntryId=>$rule){
            $key=$rule['Content']['Key'];
            $ruleValueIn=$flatSourceEntry[$rule['Content']['Entry key']]??'';
            $result['List matcher'][$index][$key.' &rarr; ']=$ruleValueIn;
            preg_match('/'.($rule['Content']['RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/',$ruleValueIn,$match);
            $ruleValueOut=preg_replace('/'.($rule['Content']['Delete by RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/','',$match[$rule['Content']['RegExp match index']]??'').($rule['Content']['Glue']??'');
            $result['List matcher'][$index][$key]=$ruleValueOut;
            $case[$key]=$ruleValueOut;
        }
        $result['List matcher'][$index]['trStyle']=['background-color'=>'var(--bgColorA)'];
        $matchSuccess=FALSE;
        // OPS List Matcher
        $ListMatcherInput=new \ListMatcher\Data\ListMatcherInput($case['EntryId'],$case['Family'],$case['Countrycode'],$case['Applicationnumber'],$case['Publicationnumber'],$case['Issuenumber']);
        $matches=$this->listMatcherObj->matchUnycomEntry($ListMatcherInput,$format='docdb');
        foreach($matches??[] as $listMatch){
            if ($listMatch->opsFailure){break;}
            if (!$listMatch->matchSuccess){continue;}
            // match successful, compile documentation
            $matchSuccess=TRUE;
            $errorsMatrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($listMatch->errors??[]);
            $result['List matcher'][$index]['Match']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(TRUE);
            $result['List matcher'][$index]['List number']=$this->list[$listMatch->entryIdRoyaltyEntry];
            $result['List matcher'][$index]['Errors']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$errorsMatrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'style'=>['border'=>'none']]);
            // match successful, move list entry
            $listEntry=$this->getListEntry($callingElement,$listMatch->entryIdRoyaltyEntry);
            $listEntry['Folder']=$caseEntry['Folder'];
            $listEntry['Content']['Match']=$listMatch->to_array();
            $listEntry['Content']['Matched case']=$caseEntry['Content'];
            $listEntry['Content']['Match type']='OPS match';
            $this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($listEntry,$targetSelectorSuccess,TRUE,$testRun,FALSE);
            $result['Statistics']['Entries moved (success)']['Value']++;
            // look for further matches, increase index & reset documentation
            $index++;
            $result['List matcher'][$index]=$result['List matcher'][$index-1];
            $result['List matcher'][$index]['Match']=$result['List matcher'][$index]['List number']=$result['List matcher'][$index]['Errors']=NULL;
            $result['List matcher'][$index]['trStyle']=NULL;
        }
        // move completely processed entries
        if (!$matchSuccess && !$listMatch->opsFailure??FALSE){
            $result['List matcher'][$index]['Match']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(FALSE);
            $result['List matcher'][$index]['List number']='';
            $result['List matcher'][$index]['Errors']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$errorsMatrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE]);
            // move failed case entry
            $this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($caseEntry,$targetSelectorFailure,TRUE,$testRun,!empty($processorParams['Keep failed cases']));
            $result['Statistics']['Entries moved (failure)']['Value']++;
        } else {
            unset($result['List matcher'][$index]);
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
        $list=['tmp'=>[]];
        $listRules=$this->getListRules($callingElement);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $listEntry){
            $flatListEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($listEntry);
            $listValue='';
            $list['tmp']=$list['tmp']??[];
            $index=count($list['tmp']);
            foreach($listRules as $ruleId=>$rule){
                $ruleKey=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($ruleId)[0];
                $ruleValueIn=$flatListEntry[$rule['Content']['Entry key']]??'';
                $list['tmp'][$index][$ruleKey]=$ruleValueIn;
                preg_match('/'.($rule['Content']['RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/',$ruleValueIn,$match);
                $ruleValueOut=preg_replace('/'.($rule['Content']['Delete by RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/','',$match[$rule['Content']['RegExp match index']]??'').($rule['Content']['Glue']??'');
                $list['tmp'][$index][$ruleKey].=' &rarr; '.$ruleValueOut;
                $listValue.=$ruleValueOut;
            }
            $listValue=trim($listValue,$rule['Content']['Glue']??'');
            $list[$flatListEntry['EntryId']]=$list['tmp'][$index]['Result']=$listValue;
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

    private function getListEntry($callingElement,$listEntryId):array
    {
        $listSelector=$callingElement['Content']['Selector'];
        $listSelector['EntryId']=$listEntryId;
        return $this->oc['SourcePot\Datapool\Foundation\Database']->entryById($listSelector,TRUE);
    }
}
?>