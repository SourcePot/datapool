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

class ParseEntries implements \SourcePot\Datapool\Interfaces\Processor{

    private const INTERNAL_MAPPING=[
        'sectionIndex'=>'ParseEntries :: sectionIndex',
        'section'=>'ParseEntries :: section',
    ];

    private const SPLIT_MARKER='__SPLIT__';
    
    private $internalData=[];

    private $oc;

    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];
        
    private $sections=['FULL'=>'Complete text','LAST'=>'Text after last section'];
    
    private $paramsTemplate=['Source column'=>'useValue','Target on success'=>'','Target on failure'=>''];

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
                'run'=>$this->runParseEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runParseEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getParseEntriesWidget($callingElement),
                'settings'=>$this->getParseEntriesSettings($callingElement),
                'info'=>$this->getParseEntriesInfo($callingElement),
            };
        }
    }

    private function getParseEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Parsing','generic',$callingElement,['method'=>'getParseEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }
    
    private function getParseEntriesInfo($callingElement){
        //$regExpTester=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->copy2clipboard(htmlentities('https://regexr.com/'));
        $matrix=[];
        $matrix['Parser control: Select parser target and type']['Description']='This control panel sets the fundermental parameters.<br/>"Source column" should contain the text to be parsed.<br/>"Target on success" is the Select-Element an entry should be moved to on success and<br/>"Target on failure" the Select-element used for entries that fail.';
        $matrix['Provide rules to divide the text into sections']['Description']='This control panel sets rules to divide the text into sections.<br/>The first section is called "START", all following sections are named by "Section name".<br/>The text section boundery is controlled by the "Regular expression".';
        $matrix['Parser rules: Parse selected entry and copy result to target entry']['Description']='This control panel sets the parser rules. The rules are processed as an ordered list.<br/>Rules are applied to the text section selected by "Rule relevant on section, the "Regular expression" is used to extract releavnt values.<br/>You can use brackets to ecxtract the full match "Match index"=0 or parts of match selected by "Match index">0.<br/>Alternatively to the regular expression you can proviede "Constant or..." as value.<br/>Use "Target data type" to convert the extracted value and "Target column", "Target key" to map the value to the entry.<br/>"Allow multiple hits" will create an array, "Combine on update" combines multiple hits in a new value.';
        $matrix['Mapper rules: map directly to the target entry']['Description']='This control panel allows for the definition of direct mapping from the source entry to the target entry.';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'','class'=>'max-content']);
        $html.='<h2 class="std">Setting-up the parser, first steps:</h2><ol>';
        $html.='<li>Add two Select-elements to the canvas, one for successfiully parsed entries and another for failed entries</li>';
        $html.='<li>Add the two Select-elements to "Parser control: Select parser target and type"</li>';
        $html.='<li>Upload a valid test document, this will enable the selection of the relevant "Source column"</li>';
        $html.='<li>Set the "Source column", for text from a pdf-file the source column is "Content → File content"</li>';
        $html.='<li>Check the content of the source column, e.g. "Content → File content", copy & paste the text into a regular expression tester such as <a href="https://regexr.com/" target="_blank" class="textlink">'.htmlentities('https://regexr.com/').'</a></li>';
        $html.='<li>Find regular expression for all the values you need to extract. If a value type exsists multiple times such as dates, divide the txet into sections</li>';
        $html.='<li>After the value extraction works fine with the test document, run a test with multiple real world documents. Improve the configuration if needed.</li>';
        $html.='</ol>';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?']);
        return $html;
    }

    public function getParseEntriesWidgetHtml($arr){
        $arr['html']=$arr['html']??'';
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runParseEntries($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runParseEntries($arr['selector'],TRUE);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Parsing widget']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            if ($caption==='Parser statistics'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getParseEntriesSettings($callingElement){
        // compile html
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Parsing entries settings','generic',$callingElement,['method'=>'getParseEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getParseEntriesSettingsHtml($arr){
        $arr['html']=$arr['html']??'';
        $arr['html'].=$this->parserParams($arr['selector']);
        $arr['html'].=$this->parserSectionRules($arr['selector']);
        $arr['html'].=$this->parserRules($arr['selector']);
        $arr['html'].=$this->mapperRules($arr['selector']);
        return $arr;
    }

    private function parserParams($callingElement){
        $contentStructure=[
            'Source column'=>['method'=>'keySelect','value'=>$this->paramsTemplate['Source column'],'excontainer'=>TRUE,'addSourceValueColumn'=>TRUE],
            'Pre-processing'=>['method'=>'select','excontainer'=>TRUE,'value'=>'stripTags','options'=>[''=>'-','stripTags'=>'Strip tags','whiteSpaceToSpace'=>'\s+ to "space"'],'title'=>''],
            'Target on success'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
            'Target on failure'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
            'No match placeholder'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'','placeholder'=>'e.g. {missing}','excontainer'=>TRUE],
            ];
        $contentStructure['Source column']+=$callingElement['Content']['Selector'];
        // get selector
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
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
        $arr['caption']='Parser control: Select parser target and type';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        if (empty($arr['selector']['Content'])){
            $row['trStyle']=['background-color'=>'#a00'];
        }
        $matrix=['Parameter'=>$row];
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }
    
    private function parserSectionRules($callingElement){
        $contentStructure=[
            'Regular expression'=>['method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'e.g. I\s{0,1}n\s{0,1}v\s{0,1}o\s{0,1}i\s{0,1}c\s{0,1}e\s{0,1}','excontainer'=>TRUE],
            '...is section'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['end indicator','start indicator'],'title'=>''],
            'Section type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'singleEntry','options'=>['singleEntry'=>'Single entry','multipleEntries'=>'Multiple entries'],'title'=>''],
            'Section name'=>['method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'e.g. Invoice start','excontainer'=>TRUE],
            ];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Provide rules to divide the text into sections.';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function parserRules($callingElement){
        // complete section selector
        $entriesSelector=['Source'=>$this->entryTable,'Name'=>$callingElement['EntryId'],'Group'=>'parserSectionRules'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            if (!isset($entry['Content']['Section name'])){continue;}
            $this->sections[$entry['EntryId']]=$entry['Content']['Section name'];
        }
        $contentStructure=[
            'Rule relevant on section'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>$this->sections],
            'Constant or...'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            'regular expression'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE,'title'=>"Add your regular expression to search for matches within the 'Source column' content here. You can check your regular expressions on different web pages. Use brackets to define sub matches. 'Match index'=0 wil return the whole match,\n'Match index'=1 the first sub match defined by the first set if brakets,..."],
            'Match index'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>[0,1,2,3,4,5,6,7,8,9,10]],
            'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
            'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE],
            'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            'Combine'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS,'title'=>"Controls the resulting value, fIf the target already exsists."],
            'Match required'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['No','Yes']],
            ];
        $contentStructure['Target column']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Parser rules: Parse selected entry and copy result to target entry';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function mapperRules($callingElement){
        $contentStructure=[
            'Source column'=>['method'=>'keySelect','value'=>$this->paramsTemplate['Source column'],'excontainer'=>TRUE,'addSourceValueColumn'=>TRUE,'addColumns'=>self::INTERNAL_MAPPING],
            '...or constant'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
            'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Folder','standardColumsOnly'=>TRUE],
            'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            'Source value'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['any','must be set'],'keep-element-content'=>TRUE],
            'Combine'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS,'title'=>"Controls the resulting value, fIf the target already exsists."],
            ];
        //$contentStructure['Source column']['addColumns']
        $contentStructure['Source column']+=$callingElement['Content']['Selector'];
        $contentStructure['Target column']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Mapper rules: map directly to the target entry';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function runParseEntries($callingElement,$testRun=FALSE){
        // complete section selector
        $entriesSelector=['Source'=>$this->entryTable,'Name'=>$callingElement['EntryId'],'Group'=>'parserSectionRules'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            if (!isset($entry['Content']['Section name'])){continue;}
            $this->sections[$entry['EntryId']]=$entry['Content']['Section name'];
        }
        $base=['parserparams'=>[],'parsersectionrules'=>[],'parserrules'=>[],'mapperrules'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=[
            'Parser statistics'=>[
                'Entries'=>['value'=>0],
                'Success'=>['value'=>0],
                'Failed'=>['value'=>0],
                'Skip rows'=>['value'=>0]
                ]
            ];
        $result['Mutliple entries → one target']=[];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            if ($sourceEntry['isSkipRow']){
                $result['Parser statistics']['Skip rows']['value']++;
                continue;
            }
            $result['Parser statistics']['Entries']['value']++;
            $result=$this->parseEntry($base,$sourceEntry,$result,$testRun);
        }
        // multiple hits statistics
        foreach($this->oc['SourcePot\Datapool\Tools\MiscTools']->getMultipleHitsStatistic() as $hitsArr){
            if ($hitsArr['Hits']<2){continue;}
            $result['Hits >1 with same EntryId'][$hitsArr['Name']]=['Hits'=>$hitsArr['Hits'],'Comment'=>$hitsArr['Comment']];    
        }
        // add general statistics
        $statistics=['Statistics'=>$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix()];
        $statistics['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $statistics['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $statistics+$result;
    }
    
    /**
    * This method parses the provided entry
    *
    * @param    array $base Contains all rules and parameters
    * @param    array $sourceEntry The entry to be processed
    * @param    array $result Contains an array of matices with results to be presented to the user, this method will add own values to the result
    * @param    bool $testRun Processes the entry but does not move/update the entry if TRUE
    * @return   array  Contains an array of matices with results to be presented to the user
    */
    private function parseEntry($base,$sourceEntry,$result,$testRun):array
    {
        $params=current($base['parserparams']);
        $params=$params['Content'];
        // get source text
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        if (isset($flatSourceEntry[$params['Source column']])){
            $fullText=$flatSourceEntry[$params['Source column']];
            if (empty($params['Pre-processing'])){
                // no pre-processing    
            } else if ($params['Pre-processing']=='stripTags'){
                $fullText=str_replace('</','|</',$fullText);
                $fullText=strip_tags($fullText);
                $fullText=preg_replace('/\|+/','|',$fullText);
                $fullText=preg_replace('/\s+/',' ',$fullText);
            } else if ($params['Pre-processing']=='whiteSpaceToSpace'){
                $fullText=preg_replace('/\s+/',' ',$fullText);
            }
        } else {
            // Parser failed, content column not found
            $result['Parser statistics']['Failed']['value']++;
            $failedEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Target on failure']],TRUE,$testRun);
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->add2hitStatistics($failedEntry,'failed');
            if (!isset($result['Sample result (failure)']) || mt_rand(1,100)>80){
                $result['Sample result (failure)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($failedEntry);
            }
            return $result;
        }
        // direct mapping to entry
        $targetEntry=$this->processMapping($base,$flatSourceEntry);
        $result['Mapping']=$targetEntry['processMapping']['result'];
        if ($targetEntry['processMapping']['failed']){
            // mapper failed
            $result['Parser statistics']['Failed']['value']++;
            $failedEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Target on failure']],TRUE,$testRun);            
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->add2hitStatistics($failedEntry,'failed');
            if (!isset($result['Sample result (failure)']) || mt_rand(1,100)>80){
                $failedEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2entry($failedEntry);
                $result['Sample result (failure)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($failedEntry);
            }         
            return $result;
        }
        // content found, get sections
        $sections=$this->sections($base,$fullText);
        if (!isset($result['Sections singleEntry']) || mt_rand(1,100)>95){
            foreach($sections['singleEntry'] as $sectionId=>$section){
                $result['Sections singleEntry'][$this->sections[$sectionId]]=['value'=>htmlspecialchars($section??'')];
            }
            foreach($sections['multipleEntries'] as $sectionId=>$sectionArr){
                foreach($sectionArr as $sectionIndex=>$section)
                $result['Sections multipleEntries "'.$this->sections[$sectionId].'"'][$sectionIndex]=['value'=>htmlspecialchars($section??'')];
            }
        }
        // parse single entry sections
        $parserFailed=FALSE;
        $resultArr=[];
        foreach($sections['singleEntry']??[] as $sectionId=>$section){
            $this->internalData['{{sectionIndex}}']=0;
            $this->internalData['{{section}}']=$section;
            $targetEntryParsing=$this->processParsing($base,$flatSourceEntry,$sectionId,$section??'');
            if (isset($targetEntryParsing['processParsing']['result'][$sectionId])){
                $sectionName=$this->sections[$sectionId];
                $resultArr=array_replace_recursive($resultArr,$targetEntryParsing['processParsing']['result'][$sectionId]);
            }
            $parserFailed=($targetEntryParsing['processParsing']['failed'])?TRUE:$parserFailed;
            $targetEntry=array_replace_recursive($targetEntry,$targetEntryParsing);
        }
        ksort($resultArr);
        if ($parserFailed){
            if (!isset($result['Parser singleEntry sections <b>failed</b>']) || mt_rand(0,100)>70){$result['Parser singleEntry sections <b>failed</b>']=$resultArr;}
            // parser failed
            $result['Parser statistics']['Failed']['value']++;
            $failedEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Target on failure']],TRUE,$testRun);
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->add2hitStatistics($failedEntry,'failed');
            return $result;
        } else {
            if (!isset($result['Parser singleEntry sections <b>success</b>']) || mt_rand(0,100)>70){$result['Parser singleEntry sections <b>success</b>']=$resultArr;}
        }
        if (empty($sections['multipleEntries'])){
            // finalize single entry
            $goodEntry=$this->finalizeEntry($base,$sourceEntry,$targetEntry,$result,$testRun);
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->add2hitStatistics($goodEntry,'success');
            $result['Parser statistics']['Success']['value']++;
        } else {
            // parse multiple entries sections
            foreach($sections['multipleEntries'] as $sectionId=>$sectionArr){
                foreach($sectionArr as $sectionIndex=>$section){
                    $this->internalData['{{sectionIndex}}']=$sectionIndex;
                    $this->internalData['{{section}}']=$section;
                    $targetEntryTmp=$this->processParsing($base,$flatSourceEntry,$sectionId,$section);
                    // check if parser failed
                    if ($targetEntryTmp['processParsing']['failed']){
                        $result['Parser statistics']['Failed']['value']++;
                        $failedEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Target on failure']],TRUE,$testRun);            
                        if (!isset($result['Sample result (failure)']) || mt_rand(1,100)>80){
                            $failedEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2entry($failedEntry);
                            $result['Sample result (failure)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($failedEntry);
                        }         
                        continue;
                    }    
                    // create result entry
                    $sectionName=$this->sections[$sectionId];
                    $sectionKey='Parser multipleEntries section '.$sectionName;
                    if (isset($targetEntryTmp['processParsing']['result'][$sectionId]) && (!isset($result[$sectionKey]) || mt_rand(0,100)>70)){
                        $result[$sectionKey]=$targetEntryTmp['processParsing']['result'][$sectionId];
                    }
                    $result['Parser statistics']['Success']['value']++;
                    $targetEntry=array_replace_recursive($targetEntry,$targetEntryTmp);
                    $isLastSection=(count($sectionArr)-1)===$sectionIndex;
                    $goodEntry=$this->finalizeEntry($base,$sourceEntry,$targetEntry,$result,$testRun,!$isLastSection);
                    $this->oc['SourcePot\Datapool\Tools\MiscTools']->add2hitStatistics($goodEntry,'success');
                }
            }
        }
        // sample result
        if (!isset($result['Sample result <b>success</b>']) || mt_rand(1,100)>70){
            $goodEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2entry($goodEntry??[]);
            $result['Sample result <b>success</b>']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($goodEntry);
        }
        return $result;
    }

    private function processParsing(array $base,array $entry,string $sectionId,string $section):array
    {
        $params=current($base['parserparams']);
        $params=$params['Content'];
        $targetEntry=$result=[];
        $failed=FALSE;
        foreach($base['parserrules'] as $ruleEntryId=>$rule){
            $ruleFailed=FALSE;
            // check if rule is relevant
            if (!isset($rule['Content']['Target data type'])){continue;}
            if ($rule['Content']['Rule relevant on section']!==$sectionId){continue;}
            $rowKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleEntryId);
            $result[$rowKey]['Text']='';
            $result[$rowKey]['Match']='';
            $result[$rowKey]['Key']=$rule['Content']['Target column'];
            $result[$rowKey]['Match text']='';
            // section text or constant to matchText
            if (empty($rule['Content']['Constant or...'])){
                $result[$rowKey]['Text']=$section;
                $ruleMatchIndex=$rule['Content']['Match index'];
                preg_match_all('/'.$rule['Content']['regular expression'].'/u',$section,$matches);
                if (!empty($rule['Content']['Match required']) && !isset($matches[$ruleMatchIndex][0])){
                    $ruleFailed=TRUE;
                }
                $result[$rowKey]['Match']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($matches[$ruleMatchIndex][0]??FALSE);
                $noMtachPlaceholder=(strpos($rule['Content']['Target data type'],'string')===FALSE)?'':($params['No match placeholder']??'');
                $matches[$ruleMatchIndex][0]=$matches[$ruleMatchIndex][0]??$noMtachPlaceholder;
                foreach($matches[$ruleMatchIndex] as $hitIndex=>$matchText){
                    $result[$rowKey]['Key'].=' | '.$rule['Content']['Target key'];
                    $result[$rowKey]['Match text'].=(empty($result[$rowKey]['Match text']))?$matchText:(' | '.$matchText);
                    $matchText=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($matchText,$rule['Content']['Target data type']);
                    $this->oc['SourcePot\Datapool\Foundation\Computations']->add2combineCache($rule['Content']['Combine'],$rule['Content']['Target column'],$rule['Content']['Target key'],$matchText);
                }
            } else if (!empty($section)){
                $result[$rowKey]['Text']='<b>const:</b> "'.$rule['Content']['Constant or...'].'"';
                $result[$rowKey]['Key'].=' | '.$rule['Content']['Target key'];
                $result[$rowKey]['Match text'].=$rule['Content']['Constant or...'];
                $constant=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($rule['Content']['Constant or...'],$rule['Content']['Target data type']);
                $this->oc['SourcePot\Datapool\Foundation\Computations']->add2combineCache($rule['Content']['Combine'],$rule['Content']['Target column'],$rule['Content']['Target key'],$constant);
            }
            $result[$rowKey]['Match required']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($rule['Content']['Match required']);
            $result[$rowKey]['Rule Failed']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleFailed);
            $result[$rowKey]['Match text']='"'.$result[$rowKey]['Match text'].'"';
            $failed=($ruleFailed)?TRUE:$failed;
        }
        $targetEntry[__FUNCTION__]['result'][$sectionId]=$result;
        $targetEntry[__FUNCTION__]['failed']=$failed;
        return $targetEntry;
    }

    private function processMapping($base,$entry):array
    {
        $targetEntry=$result=[];
        $mappingFailed=FALSE;
        foreach($base['mapperrules'] as $ruleEntryId=>$rule){
            if (!isset($rule['Content']['Source column']) || !isset($rule['Content']['...or constant'])){continue;}
            $rowKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleEntryId);
            // get text and add to entry
            if (empty($rule['Content']['...or constant'])){
                // direct mapping
                $isset=isset($entry[$rule['Content']['Source column']]);
                $matchText=$entry[$rule['Content']['Source column']]??'';
                // internal mapping
                foreach(self::INTERNAL_MAPPING as $internalMappingKey=>$internalMappingValue){
                    if ($rule['Content']['Source column']!==$internalMappingKey){continue;}
                    $matchText='{{'.$internalMappingKey.'}}';
                    $rule['Content']['Target data type']='string';
                    $isset=TRUE;
                    break;    
                }
            } else {
                // map constant
                $isset=TRUE;
                $matchText=$rule['Content']['...or constant'];
            }
            // check for error
            $ruleFailed=!empty($rule['Content']['Source value']) && !$isset;
            $result[$rowKey]=[
                'Source column or constant'=>$matchText,
                'Must be set'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($rule['Content']['Source value']??FALSE),
                'Rule failed'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleFailed),
                ];
            // add entry
            if ($ruleFailed){
                $mappingFailed=TRUE;
            } else {
                $matchText=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($matchText,$rule['Content']['Target data type']);
                $this->oc['SourcePot\Datapool\Foundation\Computations']->add2combineCache($rule['Content']['Combine'],$rule['Content']['Target column'],$rule['Content']['Target key'],$matchText);
                $debugArr[]=['Target column'=>$rule['Content']['Target column'],'matchText'=>$matchText,'targetEntry'=>$targetEntry];
            }
        }
        $targetEntry[__FUNCTION__]['result']=$result;
        $targetEntry[__FUNCTION__]['failed']=$mappingFailed;
        return $targetEntry;
    }

    private function sections(array $base,string $fullText):array
    {
        $sections=['singleEntry'=>[],'multipleEntries'=>[]];
        $splitParams=[];
        foreach($base['parsersectionrules'] as $ruleId=>$rule){
            if (!isset($rule['Content']['Section type']) || !isset($rule['Content']['Regular expression'])){
                continue;
            }
            $splitParams[$rule['Content']['Section type']][$ruleId]=[
                'ruleId'=>$ruleId,
                'regEx'=>$rule['Content']['Regular expression'],
                'isSectionStartIndicator'=>boolval($rule['Content']['...is section']),
            ];
        }
        $sections['singleEntry']=$this->singleEntrySplit($splitParams['singleEntry']??[],$fullText);
        $sections['singleEntry']['FULL']=$fullText;
        $sections['multipleEntries']=$this->multiEntriesSplit($splitParams['multipleEntries']??[],$fullText);
        return $sections;
    }

    private function singleEntrySplit(array $splitParams,string $text):array
    {
        $sections=[];
        foreach($splitParams as $ruleId=>$splitParam){
            $textComps=$this->textSplit($text,$splitParam['regEx'],$splitParam['isSectionStartIndicator'],TRUE);
            if (count($textComps)<2){continue;}
            $sections[$ruleId]=array_shift($textComps);
            $text=implode('',$textComps);
        }
        return $sections;
    }

    private function multiEntriesSplit(array $splitParams,string $text):array
    {
        $multiEntryKeys=count($splitParams);
        // first level split
        $texts=[$text];
        if (count($splitParams)>1){
            $splitParam=array_shift($splitParams);
            $text=array_shift($texts);
            $texts=$this->textSplit($text,$splitParam['regEx'],$splitParam['isSectionStartIndicator'],FALSE);
        }
        // second level split
        $splitParam=array_shift($splitParams);
        foreach($texts as $text){
            $textComps=$this->textSplit($text,$splitParam['regEx'],$splitParam['isSectionStartIndicator'],TRUE);
            if ($splitParam['isSectionStartIndicator']){
                $startText=array_shift($textComps);
            } else {
                $endText=array_pop($textComps);
            }
            foreach($textComps as $textComp){
                $sections[$splitParam['ruleId']][]=trim(trim($startText??'').'|'.$textComp.'|'.trim($endText??''),'| ');
            }
        }
        if (array_shift($splitParams)){
            $this->oc['logger']->log('notice','A maximum of 2 "Multiple entries" rules is supported. You have "{multiEntryKeys}" rules defined.',['multiEntryKeys'=>$multiEntryKeys]);
        }
        return $sections??[];
    }

    private function textSplit(string $text,string $regEx,bool $splitBeforeMatch=TRUE,bool $returnAllComps=FALSE):array
    {
        $text=preg_replace('/('.$regEx.')/u',($splitBeforeMatch)?(self::SPLIT_MARKER.'${1}'):('${1}'.self::SPLIT_MARKER),$text);
        $textComps=explode(self::SPLIT_MARKER,$text);
        if (count($textComps)<2){return [$text];}
        if ($returnAllComps){return $textComps;}
        if ($splitBeforeMatch){
            array_shift($textComps);
        } else {
            array_pop($textComps);
        }
        return $textComps;
    }

    private function finalizeEntry(array $base,array $sourceEntry,array $targetEntry,array $result,bool $testRun,bool $keepSource=FALSE):array
    {
        $params=current($base['parserparams']);
        unset($targetEntry['processMapping']);
        unset($targetEntry['processParsing']);
        $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Computations']->combineAll($targetEntry);
        foreach($targetEntry as $flatKey=>$flatValue){
            if (!is_string($flatValue)){continue;}
            if (strpos($flatValue,'{{')===FALSE || strpos($flatValue,'}}')===FALSE){continue;}
            $targetEntry[$flatKey]=strtr($flatValue,$this->internalData);
        }
        $targetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($targetEntry);
        $entry=array_replace_recursive($sourceEntry,$targetEntry);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$base['entryTemplates'][$params['Content']['Target on success']],TRUE,$testRun,$keepSource);
        return $entry;
    }

}
?>