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

class OPSEnrichEntries implements \SourcePot\Datapool\Interfaces\Processor{

    private $oc;
    private $biblio;

    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        ];
    
    private const MAX_REQUEST_COUNT_PER_RUN=3;
    private const ENRICHMENT_KEY='ops';
    private const DESCRIPTION='This processor enriches entries with data from the EPO Open Patent Service.';
    private const METHOD_OPTIONS=['SourcePot\OPS\Biblio|legal'=>'Biblio / Legal'];
    private const CREDENTIALS_DEF=[
        'Type'=>['@tag'=>'p','@default'=>'settings receiver','@Read'=>'NO_R'],
        'Content'=>[
            'appName'=>['@tag'=>'input','@type'=>'text','@default'=>'','placeholder'=>'Datapool','@excontainer'=>TRUE],
            'consumerKey'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
            'consumerSecretKey'=>['@tag'=>'input','@type'=>'password','@default'=>'','@excontainer'=>TRUE],
            'Save'=>['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'],
            'Test credentials'=>['@tag'=>'button','@value'=>'save','@element-content'=>'Test credentials','@default'=>'testCredentials'],
            ],
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
        //
        $credentials=$this->getCredentialsSetting()['Content'];
        $this->biblio=new \SourcePot\OPS\Biblio($credentials['appName'],$credentials['consumerKey'],$credentials['consumerSecretKey']);
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
                'run'=>$this->runEnrichEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runEnrichEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getEnrichEntriesWidget($callingElement),
                'settings'=>$this->getEnrichEntriesSettings($callingElement),
                'info'=>$this->getEnrichEntriesInfo($callingElement),
            };
        }
    }

    private function getEnrichEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Get enrich entries widget','generic',$callingElement,['method'=>'getEnrichEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }
    
     private function getEnrichEntriesInfo($callingElement){
        $matrix=[];
        $matrix['Description']=['<p style="width:40em;">'.self::DESCRIPTION.'</p>'];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'Info']);
        return $html;
    }

    private function getCredentialsSetting()
    {
        $setting=['Class'=>__CLASS__,'EntryId'=>'credentials'];
        $setting['Content']=['appName'=>'Datapool','consumerKey'=>'','consumerSecretKey'=>''];
        return $this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($setting,TRUE);
    }

    public function getCredentialsHtml($arr):string
    {
        $open=FALSE;
        $setting=$this->getCredentialsSetting();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing('SourcePot\Datapool\Foundation\Definitions','entry2form');
        if (isset($formData['cmd']['Content']['Test credentials'])){
            $accessToken=$this->renewAccessToken($setting['Content']);
            $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($accessToken);
            $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'OPS reply: access token']);
            $open=TRUE;
        } else {
            $html=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($setting,FALSE);
        }
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'Credentials','open'=>$open]);
        return $html;
    }

    public function getEnrichEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runEnrichEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runEnrichEntries($arr['selector'],1);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Enriching']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            if ($caption==='Enriched'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['html'].=$this->getCredentialsHtml($arr);
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getEnrichEntriesSettings($callingElement){
        $idStoreAppArr=['html'=>$this->oc['SourcePot\Datapool\Foundation\Queue']->idStoreWidget($callingElement['EntryId']),'icon'=>'Already processed'];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($idStoreAppArr);
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Enriching entries params','generic',$callingElement,['method'=>'getEnrichEntriesParamsHtml','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Enriching entries rules','generic',$callingElement,['method'=>'getEnrichEntriesRulesHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getEnrichEntriesParamsHtml($arr){
        $callingElement=$arr['selector'];
        $contentStructure=[
            'OPS method'=>['method'=>'select','excontainer'=>TRUE,'value'=>'SourcePot\OPS\Biblio|legal','options'=>self::METHOD_OPTIONS,'keep-element-content'=>TRUE],
            'Map result key to'=>['method'=>'select','excontainer'=>TRUE,'value'=>'Name','options'=>['Group'=>'Group','Folder'=>'Folder','Name'=>'Name','EntryId'=>'EntryId',],'keep-element-content'=>TRUE],
            'Map result values to'=>['method'=>'select','excontainer'=>TRUE,'value'=>'Content','options'=>['Content'=>'Content','Params'=>'Params',],'keep-element-content'=>TRUE],
            'Target'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
            'Target on failure'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
            'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>[0=>'No, move entries',1=>'Yes, copy entries']],
            ];
        $contentStructure['Map result key to']+=$callingElement['Content']['Selector'];
        $contentStructure['Map result values to']+=$callingElement['Content']['Selector'];
        // get selctor
        $callingElementArr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $callingElementArr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($callingElementArr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        $elementId=key($formData['val']);
        if (isset($formData['cmd'][$elementId])){
            $callingElementArr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $callingElementArr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($callingElementArr['selector'],TRUE);
        }
        // get HTML
        $callingElementArr['canvasCallingClass']=$callingElement['Folder'];
        $callingElementArr['contentStructure']=$contentStructure;
        $callingElementArr['caption']='Merginging control: Select target for enrichd entries';
        $callingElementArr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($callingElementArr);
        if (empty($callingElementArr['selector']['Content'])){$row['trStyle']=['background-color'=>'#a00'];}
        $matrix=['Parameter'=>$row];
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$callingElementArr['caption']]);
        return $arr;
    }

    public function getEnrichEntriesRulesHtml($arr){
        $callingElement=$arr['selector'];
        $contentStructure=[
            'Value source'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>FALSE],
            'Regex match selector'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            'Regex match index'=>['method'=>'element','tag'=>'input','type'=>'number','value'=>1,'excontainer'=>TRUE],
            ];
        $contentStructure['Value source']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Create application/documentation number rules';
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $arr;
    }
    
    public function runEnrichEntries($callingElement,$testRun=1){
        $base=['mergingparams'=>[],'mergingrules'=>[],'processId'=>$callingElement['EntryId']];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=['Statistics'=>['Itmes already processed and skipped'=>['value'=>0]]];
        // loop through entries
        $requestCounter=0;
        $selector=$callingElement['Content']['Selector'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $sourceEntry){
            if (empty($sourceEntry['Params']['File']['SpreadsheetIteratorClass'])){
                if ($this->oc['SourcePot\Datapool\Foundation\Queue']->idStoreIsNew($callingElement['EntryId'],$sourceEntry['EntryId'])){
                    $result['Statistics']['Itmes already processed and skipped']['value']++;
                    continue;
                }
                $result=$this->enrichEntries($base,$sourceEntry,$result,$testRun);
                if ($testRun==0){
                    $this->oc['SourcePot\Datapool\Foundation\Queue']->idStoreAdd($callingElement['EntryId'],$sourceEntry['EntryId']);
                }    
                $requestCounter++;
                if ($requestCounter>=self::MAX_REQUEST_COUNT_PER_RUN){break;}
                sleep(1);
            } else {
                $iteratorClass=$sourceEntry['Params']['File']['SpreadsheetIteratorClass'];
                $iteratorMethod=$sourceEntry['Params']['File']['SpreadsheetIteratorMethod'];
                foreach($this->oc[$iteratorClass]->$iteratorMethod($sourceEntry,$sourceEntry['Params']['File']['Extension']) as $rowIndex=>$rowArr){
                    $idStoreId=$sourceEntry['EntryId'].'_'.$rowIndex;
                    if ($this->oc['SourcePot\Datapool\Foundation\Queue']->idStoreIsNew($callingElement['EntryId'],$idStoreId)){
                        $result['Statistics']['Itmes already processed and skipped']['value']++;
                        continue;
                    }
                    $sourceEntry['Params']['File']['Spreadsheet']=$rowArr;
                    $result=$this->enrichEntries($base,$sourceEntry,$result,$testRun);
                    if ($testRun==0){
                        $this->oc['SourcePot\Datapool\Foundation\Queue']->idStoreAdd($callingElement['EntryId'],$idStoreId);
                    }
                    $requestCounter++;
                    if ($requestCounter>=self::MAX_REQUEST_COUNT_PER_RUN){break 2;}
                    sleep(1);
                }
            }
        }
        $result['Statistics']=array_merge($this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix(),$result['Statistics']??[]);
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }
    
    public function enrichEntries($base,$sourceEntry,$result,$testRun){
        $params=current($base['getenrichentriesparamshtml'])['Content'];
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        // get application/publication number
        $applicationPublication='';
        foreach($base['getenrichentriesruleshtml'] as $ruleId=>$rule){
            $rule=$rule['Content'];
            $value=$flatSourceEntry[$rule['Value source']]??'';
            preg_match('/'.$rule['Regex match selector'].'/',$value,$match);
            if (isset($match[$rule['Regex match index']])){
                $applicationPublication.=$match[intval($rule['Regex match index'])];
            }
        }
        // number clean-up
        $applicationPublication=explode('.',$applicationPublication);
        $applicationPublication=array_shift($applicationPublication);
        $applicationPublication=preg_replace('/[\-\s]+/','',$applicationPublication);
        $applicationPublication=str_replace('PI','',$applicationPublication);
        $applicationPublication=str_replace('WE','EP',$applicationPublication);
        // process application/publication
        if (empty($applicationPublication)){
            // empty application
            $result['Applications/publications'][$applicationPublication]=['OK'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(FALSE)];
        } else {
            // application
            $documents=$this->biblio->legal($applicationPublication);
            if (isset($documents['error'])){
                $targetSelector=$base['entryTemplates'][$params['Target on failure']];
                $sourceEntry[$params['Map result values to']][self::ENRICHMENT_KEY]=$documents;
                $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetSelector,TRUE,$testRun,$params['Keep source entries']??FALSE);
                // result creation
                if (!isset($result['Sample result (failure)']) || mt_rand(0,100)>90){
                    $result['Sample result (failure)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
                }
                $result['Applications/publications'][$applicationPublication]=['OK'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(empty($documents['error']))];
            } else {
                $targetSelector=$base['entryTemplates'][$params['Target']];        
                foreach($documents as $key=>$legalArr){
                    $targetSelector[$params['Map result key to']]=$key;
                    $sourceEntry[$params['Map result values to']][self::ENRICHMENT_KEY]=$legalArr;
                    $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetSelector,TRUE,$testRun,$params['Keep source entries']??FALSE);
                    // result creation
                    if (!isset($result['Sample result (success)']) || mt_rand(0,100)>90){
                        $result['Sample result (success)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
                    }
                    $result['Applications/publications'][$applicationPublication]['OK']=(isset($result['Applications/publications'][$applicationPublication]['OK']))?($result['Applications/publications'][$applicationPublication]['OK'].' | '.$key):$key;
                }
            }
        }
        return $result;
    }

    private function renewAccessToken($credentials):array
    {
        $ops=new \SourcePot\OPS\ops($credentials['appName'],$credentials['consumerKey'],$credentials['consumerSecretKey']);
        return $ops->renewAccessToken();
    }
}
?>