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
        'Response.php',
        'enums/FailedFamilySearchKeys.php',
    ];

    private const INFO_MATRIX=[
        'Caption'=>['Comment'=>'Open Patent Service ListMatacher Wrapper'],
        'Description'=>['Comment'=>'This processor is a wrapper for the DBaur22/OPS-Reader ListMatcher class. The canvas-element must contain well formated '],
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
        'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['No','Yes']],
        'Royalty list'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
        'Royalty list ip number &rarr; target entry Name'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['No','Yes']],
        'Target (success)'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
        'Target (failure)'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
    ];

    private const CONTENT_ROYALTYLIST_RULES=[
        // add content structure of rules here...
        'Entry key'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name',],
        'RegExp match'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'.+','excontainer'=>TRUE],
        'RegExp match index'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>[0,1,2,3,4,5,6,7,8,9],'keep-element-content'=>TRUE],
        'Delete by RegExp match'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'','excontainer'=>TRUE],
        'Glue'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'','excontainer'=>TRUE],
    ];

    private const CONTENT_STRUCTURE_RULES=[
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

    private $royaltyList=[];

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
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Royalty list rules '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'royaltyListRules','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Processor rules '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'processorRules','classWithNamespace'=>__CLASS__],[]);
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

    public function royaltyListRules($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->royaltyListRulesHtml($callingElement);
        return $arr;
    }

    public function processorRules($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->processorRulesHtml($callingElement);
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

    private function royaltyListRulesHtml(array $callingElement):string
    {
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,[]);
        $royaltyListCanvasElement=['EntryId'=>current($base['processorparamshtml'])['Content']['Royalty list']??'','Source'=>'dataexplorer'];
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($royaltyListCanvasElement,TRUE);
        if (empty($callingElement)){
            return '';
        }
        // build content structure
        $contentStructure=self::CONTENT_ROYALTYLIST_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Royalty list rules: creates an ip number used for the match';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
    
    private function processorRulesHtml(array $callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
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
        $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->initProcessorResult(__CLASS__,$testRun,current($base['processorparamshtml'])['Content']['Keep source entries']??FALSE);
        // load and create ListMatcher
        $royalty_list=[];
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
                $royalty_list=$this->getRoyaltyList($callingElement);
                $result['Royalty list']=$royalty_list['debug'];
                unset($royalty_list['debug']);
                $this->royaltyList=$royalty_list;
                $credentials=$this->getCredentials($callingElement);
                $this->listMatcherObj=new \Core\ListMatcher($royalty_list,$credentials['Content']);
            } catch(\Exception $e){
                $result['OPS-Reader ListMatcher']['Error']=['value'=>$e->getMessage()];
            }
            if (empty($result['OPS-Reader ListMatcher error'])){
                // loop through entries
                foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
                    $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->updateProcessorResult($result,$sourceEntry);
                    if ($result['cntr']['timeLimitReached']){
                        break;
                    } else if (!$result['cntr']['isSkipRow']){
                        $result=$this->processEntry($base,$sourceEntry,$result,$testRun);
                    }
                }
            }
        }
        return $this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeProcessorResult($result);
    }

    private function processEntry(array $base,array $sourceEntry,array $result,bool $testRun):array
    {
        // recover basic context data
        $processorParams=current($base['processorparamshtml'])['Content'];
        $processorRules=$base['processorruleshtml'];
        
        // recover the entry selector defined by the selected Canves element Selector
        $targetSelectorSuccess=$base['entryTemplates'][$processorParams['Target (success)']]??[];
        $targetSelectorFailure=$base['entryTemplates'][$processorParams['Target (failure)']]??[];

        // process entry based on processorRules and e.g. processorParams
        $ip_number=['EntryId'=>$sourceEntry['EntryId']];
        $result['Patent/Application no.']=$result['Patent/Application no.']??[];
        $count=count($result['Patent/Application no.']);
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        foreach($processorRules as $ruleEntryId=>$rule){
            $key=$rule['Content']['Key'];
            $ruleValueIn=$flatSourceEntry[$rule['Content']['Entry key']]??'';
            $result['Patent/Application no.'][$count][$key.' &rarr; ']=$ruleValueIn;
            preg_match('/'.($rule['Content']['RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/',$ruleValueIn,$match);
            $ruleValueOut=preg_replace('/'.($rule['Content']['Delete by RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/','',$match[$rule['Content']['RegExp match index']]??'').($rule['Content']['Glue']??'');
            $result['Patent/Application no.'][$count][$key]=$ruleValueOut;
            $ip_number[$key]=$ruleValueOut;
        }
        // match
        $contentKey=array_pop(explode('\\',__CLASS__));
        $matchSuccess=FALSE;
        $royaltyListSelector=$base['entryTemplates'][$processorParams['Royalty list']];
        $ListMatcherInput=new \Core\data\ListMatcher\ListMatcherInput($ip_number['EntryId']??'',$ip_number['Family']??'',$ip_number['Countrycode']??'',$ip_number['Applicationnumber']??'',$ip_number['Publicationnumber']??'',$ip_number['Issuenumber']??'');
        $matches=$this->listMatcherObj->matchUnycomEntry($ListMatcherInput,$format='docdb');
        foreach($matches??[] as $royaltyListMatch){
            $result['Patent/Application no.'][$count]['Match']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($royaltyListMatch->matchSuccess);
            $royaltyListSelector['EntryId']=$royaltyListMatch->entryIdRoyaltyEntry;
            $matchedRoyaltyListEntryContent=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($royaltyListSelector,TRUE)['Content'];
            $matchedRoyaltyListEntryContent['Match']=$royaltyListMatch->to_array();
            if ($royaltyListMatch->matchSuccess){
                $matchSuccess=TRUE;
                if (empty($processorParams['Royalty list ip number &rarr; target entry Name'])){
                    $sourceEntry['Content'][$contentKey][]=$matchedRoyaltyListEntryContent;
                } else {
                    $sourceEntry['Name']=$this->royaltyList[$royaltyListSelector['EntryId']];
                    $sourceEntry['Content'][$contentKey]=$matchedRoyaltyListEntryContent;
                }
                $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetSelectorSuccess,TRUE,$testRun,TRUE);
                $result['Statistics']['Entries moved (success)']['Value']++;
            }
        }
        // move processed entries
        if ($matchSuccess){
            if (empty($testRun)){
                $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries(['Source'=>$sourceEntry['Source'],'EntryId'=>$sourceEntry['EntryId']],TRUE);
            }
        } else {
            $sourceEntry['Content'][$contentKey][0]=FALSE;
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetSelectorFailure,TRUE,$testRun,!empty($processorParams['Keep source entries']));
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
    
    private function getRoyaltyList($callingElement):array
    {
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,[]);
        $params=current($base['processorparamshtml'])['Content']??[];
        $royaltyListCanvasElement=['EntryId'=>current($base['processorparamshtml'])['Content']['Royalty list']??'','Source'=>'dataexplorer'];
        $royaltyListBase=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$royaltyListCanvasElement,[]);
        $processorRules=$royaltyListBase['royaltylistruleshtml']??[];
        $royaltyList=['debug'=>[]];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($base['entryTemplates'][$params['Royalty list']],TRUE) as $royaltyEntry){
            $flatRoyaltyEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($royaltyEntry);
            $royaltyListValue='';
            $royaltyList['debug']=$royaltyList['debug']??[];
            $count=count($royaltyList['debug']);
            foreach($processorRules as $ruleEntryId=>$rule){
                $ruleKey=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($ruleEntryId)[0];
                $ruleValueIn=$flatRoyaltyEntry[$rule['Content']['Entry key']]??'';
                $royaltyList['debug'][$count][$ruleKey]=$ruleValueIn;
                preg_match('/'.($rule['Content']['RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/',$ruleValueIn,$match);
                $ruleValueOut=preg_replace('/'.($rule['Content']['Delete by RegExp match']??\SourcePot\Datapool\Root::NULL_STRING).'/','',$match[$rule['Content']['RegExp match index']]??'').($rule['Content']['Glue']??'');
                $royaltyList['debug'][$count][$ruleKey].=' &rarr; '.$ruleValueOut;
                $royaltyListValue.=$ruleValueOut;
            }
            $royaltyListValue=trim($royaltyListValue,$rule['Content']['Glue']??'');
            $royaltyList[$royaltyEntry['EntryId']]=$royaltyList['debug'][$count]['Result']=$royaltyListValue;
        }
        return $royaltyList;
    }

}
?>