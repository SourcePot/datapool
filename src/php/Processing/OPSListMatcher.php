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
        'Royalty list key'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','addSourceValueColumn'=>FALSE,'addColumns'=>[]],
        'Target (success)'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
        'Target (failure)'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],
    ];

    private const CONTENT_STRUCTURE_RULES=[
        // add content structure of rules here...
        'Entry key'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name',],
        'RegExp'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'.+','excontainer'=>TRUE],
        'RegExp match index'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>[0,1,2,3,4,5,6,7,8,9],'keep-element-content'=>TRUE],
        'Glue'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'','excontainer'=>TRUE],
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
        $arr['html']='<h1 style="width:100vw;">JUST FOR TESTING - THIS PROZESSOR IS UNDER CONSTRUCTION</h1>';
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

    private function processorRulesHtml(array $callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Processor rules';
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
        foreach(['ListMatcher.php','OpsInterface.php','OpsReader.php'] as $class){
            require_once(self::OPS_READER_CORE_PATH.$class);
        }
        try{
            $royalty_list=$this->getRoyaltyList($callingElement);
            foreach(array_rand($royalty_list,10) as $key){
                $result['Royalty list sample'][$key]=['value'=>$royalty_list[$key]];
            }
            $credentials=$this->getCredentials($callingElement);
            //$this->listMatcherObj=new \Core\ListMatcher($royalty_list,#[\SensitiveParameter] array $credentials['Content']);
            $result['OPS-Reader ListMatcher']['errors']=['value'=>'UNDER CONSTRUCTION'];
        } catch(\Exception $e){
            $result['OPS-Reader ListMatcher']['errors']=['value'=>$e->getMessage()];
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
        $opsMatchValue='';
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        foreach($processorRules as $ruleEntryId=>$rule){
            $ruleKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleEntryId);
            preg_match('/'.$rule['Content']['RegExp'].'/',$flatSourceEntry[$processorParams['Entry key']],$match);
            $opsMatchValue.=$match[$processorParams['RegExp match index']].$processorParams['Glue'];
        }
        $processedSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatSourceEntry);

        // move processed entries
        $success=(mt_rand(0,1)>0)?TRUE:FALSE;
        if ($success){
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($processedSourceEntry,$targetSelectorSuccess,TRUE,$testRun,!empty($processorParams['Keep source entries']));
            $result['Statistics']['Entries moved (success)']['Value']++;
        } else {
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($processedSourceEntry,$targetSelectorFailure,TRUE,$testRun,!empty($processorParams['Keep source entries']));
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
        $params=current($base['processorparamshtml'])['Content'];
        $oyaltyList=[];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($base['entryTemplates'][$params['Royalty list']],TRUE) as $royaltyEntry){
            $flatRoyaltyEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($royaltyEntry);
            $oyaltyList[$royaltyEntry['EntryId']]=$flatRoyaltyEntry[$params['Royalty list key']]??('Key "'.$params['Royalty list key'].'" not present');
        }
        return $oyaltyList??[];
    }

}
?>