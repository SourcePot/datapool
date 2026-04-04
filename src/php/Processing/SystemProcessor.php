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

class SystemProcessor implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;
    
    private const INFO_MATRIX=[
        'Caption'=>['Comment'=>'External program execution'],
        'Description'=>['Comment'=>'This prozessors allows for the execution of exernal programms on the system.'],
    ];

    private const SYSTEM_INFO=[
        'CYGWIN_NT-5.1'=>[

        ],
        'Darwin'=>[

        ],
        'FreeBSD'=>[

        ],
        'HP-UX'=>[

        ],
        'IRIX64'=>[

        ],
        'Linux'=>[
            'uname -a'=>'System',
            'whoami'=>'Current User',
            'hostname'=>'Hostname',
            'pwd'=>'Current directory',
        ],
        'NetBSD'=>[

        ],
        'OpenBSD'=>[

        ],
        'SunOS'=>[

        ],
        'Unix'=>[
            'uname -a'=>'System',
            'whoami'=>'Current User',
            'hostname'=>'Hostname',
            'pwd'=>'Current directory',
        ],
        'WIN32'=>[
            'systeminfo'=>'System',
            'whoami'=>'Current User',
            'cd'=>'Current directory',
        ],
        'WINNT'=>[
            'systeminfo'=>'System',
            'whoami'=>'Current User',
            'hostname'=>'Hostname',
            'cd'=>'Current directory',
        ],
        'Windows'=>[
            'systeminfo'=>'System',
            'whoami'=>'Current User',
            'hostname'=>'Hostname',
            'cd'=>'Current directory',
        ],
    ];

    private const CONTENT_STRUCTURE_PARAMS=[
        'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['No','Yes']],
        'Target on success'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],    
        'Target on failure'=>['method'=>'canvasElementSelect','addBlackHole'=>TRUE,'excontainer'=>TRUE],    
    ];
    
    private const CONTENT_STRUCTURE_PLACEHOLDER=[
        'Map entry key or ...'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','addSourceValueColumn'=>TRUE,'addColumns'=>[]],
        '... value'=>['method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'Enter your new value here','excontainer'=>TRUE],
        '|'=>['method'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'excontainer'=>TRUE],
        'Placeholder'=>['method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'{FILE}','excontainer'=>TRUE],
    ];
    
    private const CONTENT_STRUCTURE_COMMANDS=[
        'Command (ypu can use placeholders)'=>['method'=>'element','tag'=>'textarea','element-content'=>'ps -aux | less','keep-element-content'=>TRUE,'excontainer'=>TRUE],
        '|'=>['method'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'excontainer'=>TRUE],
        'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Content','standardColumsOnly'=>TRUE,'addColumns'=>['Write to file'=>'Write to file']],
        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Combine'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS,'title'=>"Controls the resulting value, fIf the target already exsists."],
    ];
    
    private const CONTENT_STRUCTURE_MAPPING=[
        'Value (ypu can use placeholders)'=>['method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'Enter your new value here','excontainer'=>TRUE],
        '|'=>['method'=>'element','tag'=>'p','element-content'=>'&rarr;','keep-element-content'=>TRUE,'excontainer'=>TRUE],
        'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Content','standardColumsOnly'=>TRUE,'addColumns'=>['Write to file'=>'Write to file']],
        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Combine'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS,'title'=>"Controls the resulting value, fIf the target already exsists."],
    ];
    
    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];

    private $placeholder=[];
    
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

    private function getInitPlaceholder():array
    {
        $matrix=[];
        foreach(self::SYSTEM_INFO[PHP_OS]??[] as $cmd=>$key){
            $placeholder='{'.$key.'}';
            $output=NULL;
            exec($cmd,$output);
            $this->add2placeholder($placeholder,current($output));
            $matrix[$placeholder]=['Command'=>$cmd,'Placeholder'=>$placeholder,'Value'=>'= "'.implode("\n",$output).'"'];
        }
        $this->add2placeholder('{FTP directory}',$GLOBALS['dirs']['ftp']);
        $this->add2placeholder('{TMP directory}',$GLOBALS['dirs']['privat tmp']);
        return $matrix;
    }

    private function getWidget($callingElement)
    {
        // check system
        $matrix=$this->getInitPlaceholder();
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'System','style'=>['width'=>'95vw','color'=>'#eee','background-color'=>'#000',]]);
        // provide widget
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('System processor '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'getWidgetHtml','classWithNamespace'=>__CLASS__],[]);
        return $html;
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'System Processor']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            //$appArr['open']=$caption==='Statistics';
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getSettings($callingElement):string
    {
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Processor parameters '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'processorParams','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Processor placeholder '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'processorPlaceholder','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Processor commands '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'processorCommands','classWithNamespace'=>__CLASS__],[]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Processor mapping '.($callingElement['EntryId']??''),'generic',$callingElement,['method'=>'processorMapping','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }

    public function processorParams($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->processorParamsHtml($callingElement);
        return $arr;
    }
    
    public function processorPlaceholder($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->processorPlaceholderHtml($callingElement);
        return $arr;
    }
    
    public function processorCommands($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->processorCommandsHtml($callingElement);
        return $arr;
    }
    
    public function processorMapping($arr)
    {
        $callingElement=$arr['selector'];
        $arr['html']=$this->processorMappingHtml($callingElement);
        return $arr;
    }
    
    private function processorParamsHtml($callingElement)
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Parameter';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }
    
    private function processorPlaceholderHtml(array $callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PLACEHOLDER;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Placeholder';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
    
    private function processorCommandsHtml(array $callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_COMMANDS;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Commands';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function processorMappingHtml(array $callingElement):string
    {
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_MAPPING;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Mapping';
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
        // init placeholder results
        $this->getInitPlaceholder();
        // loop through entries
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->updateProcessorResult($result,$sourceEntry);
            if ($result['cntr']['timeLimitReached']){
                break;
            } else if (!$result['cntr']['isSkipRow']){
                $result=$this->processEntry($base,$sourceEntry,$result,$testRun);
            }
        }
        return $this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeProcessorResult($result);
    }

    private function processEntry(array $base,array $sourceEntry,array $result,bool $testRun):array
    {
        $success=TRUE;
        $result['Placeholder']=[];
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        // process placeholder rules
        foreach($base['processorplaceholderhtml']??[] as $placeholderRuleId=>$placeholderRule){
            $ruleKey=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($placeholderRuleId)[0];
            $strValue=strval($placeholderRule['Content']['... value']??'');
            $placeholderKey=$placeholderRule['Content']['Placeholder']??'{MISSING_PLACEHOLDER}';
            if (empty($strValue)){
                $this->add2placeholder($placeholderKey,$flatSourceEntry[$placeholderRule['Content']['Map entry key or ...']??'']??'');
            } else {
                $this->add2placeholder($placeholderKey,$strValue);
            }
        }
        // process entry, execute commands
        foreach($base['processorcommandshtml']??[] as $ruleEntryId=>$rule){
            $ruleKey=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($ruleEntryId)[0];
            $command=$rule['Content']['Command (ypu can use placeholders)']??'';
            $command=strtr($command,$this->placeholder);
            if ($testRun){
                $targetValue=['TEST RUN - Command not executed.'];
            } else {
                $targetValue=$result_code=NULL;
                exec($command,$targetValue,$result_code);
                if ($result_code!==0){$success=FALSE;}
            }
            if ($rule['Content']['Target data type']!=='keep'){
                $targetValue=implode("\n",$targetValue);
            }
            $targetValue=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($targetValue,$rule['Content']['Target data type']);
            $result['Commands'][$ruleKey]=['User'=>$this->placeholder['{Current User}'].'@'.$this->placeholder['{Hostname}'].':','Command'=>$command,'Result'=>(is_array($targetValue)?implode("\n",$targetValue):$targetValue),'Failed'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($result_code??0)];
            $this->add2placeholder('{COMMAND_RESULT_'.$ruleKey.'}',$result['Commands'][$ruleKey]['Result']);
            if (($rule['Content']['Target column']??'')==='Write to file'){
                $fileContent=is_array($targetValue)?implode("\n",$targetValue):$targetValue;
            } else {
                $this->oc['SourcePot\Datapool\Foundation\Computations']->add2combineCache($rule['Content']['Combine'],$rule['Content']['Target column'],$rule['Content']['Target key'],$targetValue);
            }
        }
        // process mapping
        foreach($base['processormappinghtml']??[] as $ruleEntryId=>$mappingRule){
            $ruleKey=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($ruleEntryId)[0];
            $mappingValue=strval($mappingRule['Content']['Value (ypu can use placeholders)']??'');
            $mappingValue=strtr($mappingValue,$this->placeholder);
            $targetValue=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($mappingValue,$mappingRule['Content']['Target data type']);
            $result['Mapping'][$ruleKey]=['Key'=>$mappingRule['Content']['Target column'].' &rarr; '.$mappingRule['Content']['Target key'],'Mapping value'=>(is_array($targetValue)?implode("\n",$targetValue):$targetValue)];
            $this->oc['SourcePot\Datapool\Foundation\Computations']->add2combineCache($mappingRule['Content']['Combine'],$mappingRule['Content']['Target column'],$mappingRule['Content']['Target key'],$targetValue);
        }
        // move processed entries
        $processorParams=current($base['processorparamshtml'])['Content'];
        $targetSelectorSuccess=$base['entryTemplates'][$processorParams['Target on success']]??[];
        $targetSelectorFailure=$base['entryTemplates'][$processorParams['Target on failure']]??[];
        if ($success){
            $sourceEntry=$this->oc['SourcePot\Datapool\Foundation\Computations']->combineAll($sourceEntry);
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetSelectorSuccess,TRUE,$testRun,!empty($processorParams['Keep source entries']));
            if (!empty($fileContent) && !$testRun){
                $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($targetEntry);
                file_put_contents($file,$fileContent);
                $targetEntry['Params']['File']['Name']=preg_replace('/[^a-zA-Z0-9.]/','_',$targetEntry['Name']).'.txt';
                $targetEntry['Params']['File']['Size']=filesize($file);
                $targetEntry['Params']['File']['Extension']='txt';
                $targetEntry['Params']['File']['MIME-Type']='text/plain';
                $targetEntry['Params']['File']['Date (created)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($targetEntry,TRUE);
            }
            $result['Statistics']['Entries moved (success)']['Value']++;
            if (!isset($result['Sample result <b>success</b>']) || mt_rand(1,100)>70){
                $targetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2entry($targetEntry??[]);
                $result['Sample result <b>success</b>']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
            }
        } else {
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetSelectorFailure,TRUE,$testRun,!empty($processorParams['Keep source entries']));
            $result['Statistics']['Entries moved (failure)']['Value']++;
            if (!isset($result['Sample result <b>failed</b>']) || mt_rand(1,100)>70){
                $targetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2entry($targetEntry??[]);
                $result['Sample result <b>failed</b>']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
            }
        }
        // get placholder
        foreach($this->placeholder as $placeholderKey=>$placeholderValue){
            $result['Placeholder'][$placeholderKey]=['value'=>$placeholderValue];
        }
        return $result;
    }

    private function add2placeholder(string $key,string $value):void
    {
        $this->placeholder[$key]=escapeshellcmd($value);
    }
}
?>