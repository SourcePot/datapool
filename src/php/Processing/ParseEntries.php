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

    private $oc;

    private const SPLIT_MARKER='__SPLIT__';

    private const CONTENT_STRUCTURE_PARAMS=[
        'Source column'=>['method'=>'keySelect','value'=>'','excontainer'=>TRUE,'addSourceValueColumn'=>TRUE],
        'Pre-processing'=>['method'=>'select','excontainer'=>TRUE,'value'=>'stripTags','options'=>[''=>'-','stripTags'=>'Strip tags','whiteSpaceToSpace'=>'\s+ to "space"'],'title'=>''],
        'Target on success'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
        'Target on failure'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
        'No match placeholder'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'','placeholder'=>'e.g. {missing}','excontainer'=>TRUE],
    ];
    
    private const CONTENT_STRUCTURE_SECTION_RULES=[
        'Regular expression'=>['method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'e.g. I\s{0,1}n\s{0,1}v\s{0,1}o\s{0,1}i\s{0,1}c\s{0,1}e\s{0,1}','excontainer'=>TRUE],
        '...is section'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['end indicator','start indicator'],'title'=>''],
        'Section type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'singleEntry','options'=>['singleEntry'=>'Single entry','multipleEntries'=>'Multiple entries'],'title'=>''],
        'Section name'=>['method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'e.g. Invoice start','excontainer'=>TRUE],
    ];
    
    private const CONTENT_STRUCTURE_RULES=[
        'Rule relevant on section'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>[]],
        'Constant or...'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'regular expression'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE,'title'=>"Add your regular expression to search for matches within the 'Source column' content here. You can check your regular expressions on different web pages. Use brackets to define sub matches. 'Match index'=0 wil return the whole match,\n'Match index'=1 the first sub match defined by the first set if brakets,..."],
        'Match index'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>[0,1,2,3,4,5,6,7,8,9,10]],
        'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE],
        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Combine'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS,'title'=>"Controls the resulting value, fIf the target already exsists."],
        'Match required'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['No','Yes']],
    ];

    private const CONTENT_STRUCTURE_MAPPER_RULES=[
        'Source column'=>['method'=>'keySelect','value'=>'','excontainer'=>TRUE,'addSourceValueColumn'=>TRUE,'addColumns'=>[]],
        '...or constant'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Target data type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>\SourcePot\Datapool\Foundation\Computations::DATA_TYPES,'keep-element-content'=>TRUE],
        'Target column'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Folder','standardColumsOnly'=>TRUE],
        'Target key'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        'Source value'=>['method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>['any','must be set'],'keep-element-content'=>TRUE],
        'Combine'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>\SourcePot\Datapool\Foundation\Computations::COMBINE_OPTIONS,'title'=>"Controls the resulting value, fIf the target already exsists."],
    ];
    
    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];
        
    private $sectionNamesById=[];
    
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
        $html='<h2 class="std">Setting-up the parser - first steps:</h2><ol>';
        $html.='<li>Make sure you have two additional Canvas-Elements, one for successfiully parsed entries and another for failed entries</li>';
        $html.='<li>Upload a valid test document, this will enable the selection of the relevant <b>Source column</b></li>';
        $html.='<li>at <b>Parser control: Select parser target and type</b> select the <b>Source column</b> which contains the text to parse</li>';
        $html.='<li>Check the content of the source column, e.g. "Content → File content", copy & paste the text into a regular expression tester such as <a href="https://regexr.com/" target="_blank" class="textlink">'.htmlentities('https://regexr.com/').'</a></li>';
        $html.='<li>Find regular expression for all the values you need to extract. If a value type exsists multiple times such as dates, divide the txet into sections</li>';
        $html.='<li>After the value extraction works alright with the test document, run a test with multiple real world documents. Improve the configuration if needed.</li>';
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
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getParseEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Parsing entries settings','generic',$callingElement,['method'=>'getParseEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getParseEntriesSettingsHtml($arr){
        $this->sectionNamesById=$this->sectionNamesById($arr['selector']);
        $arr['html']=$arr['html']??'';
        $arr['html'].=$this->parserParams($arr['selector']);
        $arr['html'].=$this->mapperRules($arr['selector']);
        $arr['html'].=$this->parserSectionRules($arr['selector']);
        $arr['html'].=$this->parserRules($arr['selector']);
        return $arr;
    }

    private function parserParams($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $contentStructure['Source column']['value']=$this->paramsTemplate['Source column'];
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Parser control: Select parser target and type';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }
    
    private function parserSectionRules($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_SECTION_RULES;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Provide rules to divide the text into sections.';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function parserRules($callingElement){
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_RULES;
        $contentStructure['Rule relevant on section']['options']=$this->sectionNamesById;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Parser rules: Parse selected entry and copy result to target entry';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function mapperRules($callingElement){
        $contentStructure=self::CONTENT_STRUCTURE_MAPPER_RULES;
        $contentStructure['Source column']['value']=$this->paramsTemplate['Source column'];
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Mapper rules: map directly to the target entry';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function runParseEntries($callingElement,$testRun=FALSE){
        // complete section selector
        $this->sectionNamesById=$this->sectionNamesById($callingElement);
        $base=['parserparams'=>[],'parsersectionrules'=>[],'parserrules'=>[],'mapperrules'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->initProcessorResult(__CLASS__,$testRun,current($base['parserparams'])['Content']['Keep source entries']??FALSE);
        $result['Mutliple entries → one target']=[];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            $result=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->updateProcessorResult($result,$sourceEntry);
            if ($result['cntr']['timeLimitReached']){
                break;
            } else if (!$result['cntr']['isSkipRow']){
                $result=$this->parseEntry($base,$sourceEntry,$result,$testRun);
            }
        }
        // multiple hits statistics
        foreach($this->oc['SourcePot\Datapool\Tools\MiscTools']->getMultipleHitsStatistic() as $hitsArr){
            if ($hitsArr['Hits']<2){continue;}
            $result['Hits >1 with same EntryId'][$hitsArr['Name']]=['Hits'=>$hitsArr['Hits'],'Comment'=>$hitsArr['Comment']];    
        }
        return $this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeProcessorResult($result);
    }
    
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
            return $this->finalizeFailedEntry($result,$sourceEntry,$base,$params,$testRun);
        }
        // direct mapping to entry
        $mappingResult=$this->processMapping($base,$flatSourceEntry);
        $result['Mapping']=$mappingResult['result'];
        if ($mappingResult['failed']){
            // mapper failed
            $singleEntry=$this->oc['SourcePot\Datapool\Foundation\Computations']->combineAll([]);
            return $this->finalizeFailedEntry($result,$sourceEntry,$base,$params,$testRun);
        }
        // content found, get sections
        $sections=$this->sections($base,$fullText);
        if (!isset($result['Sections singleEntry']) || mt_rand(1,100)>95){
            foreach($sections['singleEntry'] as $sectionId=>$section){
                $result['Sections singleEntry'][$this->sectionNamesById[$sectionId]]=['value'=>htmlspecialchars($section??'')];
            }
            foreach($sections['multipleEntries'] as $newEntryIndex=>$newEntrySections){
                foreach($newEntrySections as $sectionId=>$section){
                    $result['Sections multipleEntries"'][$newEntryIndex.' | '.$this->sectionNamesById[$sectionId]]=['value'=>htmlspecialchars($section??'')];
                }
            }
        }
        // parse single entry sections
        $parserFailed=FALSE;
        $resultArr=[];
        foreach($sections['singleEntry']??[] as $sectionId=>$section){
            $parserResult=$this->processParsing($base,$sectionId,$section??'');
            if (isset($parserResult['result'][$sectionId])){
                $resultArr=array_replace_recursive($resultArr,$parserResult['result'][$sectionId]);
            }
            $parserFailed=($parserResult['failed'])?TRUE:$parserFailed;
        }
        ksort($resultArr);
        if ($parserFailed){
            // single section parser failed
            if (!isset($result['Parser singleEntry sections <b>failed</b>']) || mt_rand(0,100)>70){
                $result['Parser singleEntry sections <b>failed</b>']=$resultArr;
            }
            return $this->finalizeFailedEntry($result,$sourceEntry,$base,$params,$testRun);
        } else {
            // single section parser success
            if (!isset($result['Parser singleEntry sections <b>success</b>']) || mt_rand(0,100)>70){
                $result['Parser singleEntry sections <b>success</b>']=$resultArr;
            }
        }
        if (empty($sections['multipleEntries'])){
            // finalize single entry
            $singleEntry=$this->oc['SourcePot\Datapool\Foundation\Computations']->combineAll([]);
            $goodEntry=$this->finalizeEntry($base,$flatSourceEntry,[$singleEntry],$testRun);
            if (empty($testRun)){
                $this->oc['SourcePot\Datapool\Foundation\Database']->removeFileFromEntry($goodEntry);
            }        
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->add2hitStatistics($goodEntry,'success');
            $result['Statistics']['Entries moved (success)']['Value']++;
        } else {
            // parse multiple entries sections
            $combineCacheBackup=$this->oc['SourcePot\Datapool\Foundation\Computations']->getCombineCache();
            foreach($sections['multipleEntries'] as $entryIndex=>$newEntrySections){
                $parsingFailed=FALSE;
                $entryParserResult=[];
                $this->oc['SourcePot\Datapool\Foundation\Computations']->setCombineCache($combineCacheBackup);
                foreach($newEntrySections as $sectionId=>$section){
                    $parserResult=$this->processParsing($base,$sectionId,$section);
                    foreach($parserResult['result'][$sectionId] as $parserResultArr){
                        $key=$entryIndex.'-'.count($entryParserResult);
                        $entryParserResult[$key]=$parserResultArr;
                    }
                    // check if parser failed
                    if ($parserResult['failed']){
                        $result=$this->finalizeFailedEntry($result,$sourceEntry,$base,$params,$testRun);
                        $parsingFailed=TRUE;
                        break;
                    }
                }
                $subKey=$parsingFailed?'(failed)':'(success)';
                $result['Parser multipleEntries section '.$subKey]=array_merge($result['Parser multipleEntries section '.$subKey]??[],$entryParserResult);
                $multipleEntry=$this->oc['SourcePot\Datapool\Foundation\Computations']->combineAll([]); 
                if ($parsingFailed){continue;}
                // create result entry
                $isLastSection=(count($newEntrySections)-1)===$entryIndex;
                $goodEntry=$this->finalizeEntry($base,$flatSourceEntry,[$multipleEntry],$testRun,!$isLastSection);
                if (empty($testRun)){
                    $this->oc['SourcePot\Datapool\Foundation\Database']->removeFileFromEntry($goodEntry);
                }            
                $result['Statistics']['Entries moved (success)']['Value']++;
                $this->oc['SourcePot\Datapool\Tools\MiscTools']->add2hitStatistics($goodEntry,'success');
            }
        }
        // sample result
        if (!isset($result['Sample result <b>success</b>']) || mt_rand(1,100)>70){
            $goodEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2entry($goodEntry??[]);
            $result['Sample result <b>success</b>']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($goodEntry);
        }
        return $result;
    }

    private function finalizeFailedEntry($result,$sourceEntry,$base,$params,$testRun):array
    {
        $result['Statistics']['Entries moved (failure)']['Value']++;
        $failedEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Target on failure']],TRUE,$testRun);
        $this->oc['SourcePot\Datapool\Tools\MiscTools']->add2hitStatistics($failedEntry,'failed');
        if (!isset($result['Sample result (failure)']) || mt_rand(1,100)>80){
            $result['Sample result (failure)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($failedEntry);
        }
        return $result;
    }

    private function processParsing(array $base,string $sectionId,string $section):array
    {
        $result=[];
        $failed=FALSE;
        $params=current($base['parserparams']);
        $params=$params['Content'];
        foreach($base['parserrules'] as $ruleEntryId=>$rule){
            $ruleFailed=FALSE;
            // check if rule is relevant
            if (!isset($rule['Content']['Target data type'])){continue;}
            if ($rule['Content']['Rule relevant on section']!==$sectionId){continue;}
            $rowKey=$this->oc['SourcePot\Datapool\Foundation\Database']->orderedListComps($ruleEntryId)[0];
            $result[$rowKey]['Parser rule']=$rowKey;
            $result[$rowKey]['Text']='';
            $result[$rowKey]['Match']='';
            $result[$rowKey]['Key']=$rule['Content']['Target column'];
            $result[$rowKey]['Match text']='';
            // section text or constant to matchText
            if (strlen($rule['Content']['Constant or...'])===0){
                $result[$rowKey]['Text']=$section;
                $ruleMatchIndex=$rule['Content']['Match index'];
                if (empty($rule['Content']['regular expression'])){
                    $matches=[0=>[0=>$section]];
                } else {
                    preg_match_all('/'.$rule['Content']['regular expression'].'/u',$section,$matches);
                    if (!empty($rule['Content']['Match required']) && !isset($matches[$ruleMatchIndex][0])){
                        $ruleFailed=TRUE;
                    }
                }
                $result[$rowKey]['Match']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($matches[$ruleMatchIndex][0]??FALSE);
                $noMtachPlaceholder=(strpos($rule['Content']['Target data type'],'string')===FALSE)?'':($params['No match placeholder']??'');
                $matches[$ruleMatchIndex][0]=$matches[$ruleMatchIndex][0]??$noMtachPlaceholder;
                foreach($matches[$ruleMatchIndex] as $hitIndex=>$matchText){
                    $result[$rowKey]['Key']=$rule['Content']['Target column'].' | '.$rule['Content']['Target key'];
                    $result[$rowKey]['Match text'].=(empty($result[$rowKey]['Match text']))?$matchText:(' | '.$matchText);
                    $matchText=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($matchText,$rule['Content']['Target data type']);
                    $this->oc['SourcePot\Datapool\Foundation\Computations']->add2combineCache($rule['Content']['Combine'],$rule['Content']['Target column'],$rule['Content']['Target key'],$matchText);
                }
            } else if (!empty($section)){
                $result[$rowKey]['Text']='<b>const:</b> "'.$rule['Content']['Constant or...'].'"';
                $result[$rowKey]['Key']=$rule['Content']['Target column'].' | '.$rule['Content']['Target key'];
                $result[$rowKey]['Match text'].=$rule['Content']['Constant or...'];
                $constant=$this->oc['SourcePot\Datapool\Foundation\Computations']->convert($rule['Content']['Constant or...'],$rule['Content']['Target data type']);
                $this->oc['SourcePot\Datapool\Foundation\Computations']->add2combineCache($rule['Content']['Combine'],$rule['Content']['Target column'],$rule['Content']['Target key'],$constant);
            }
            $result[$rowKey]['Match required']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($rule['Content']['Match required']);
            $result[$rowKey]['Rule Failed']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleFailed);
            $result[$rowKey]['Match text']='"'.$result[$rowKey]['Match text'].'"';
            $failed=($ruleFailed)?TRUE:$failed;
        }
        return ['result'=>[$sectionId=>$result],'failed'=>$failed];
    }

    private function processMapping($base,$entry):array
    {
        $result=[];
        $mappingFailed=FALSE;
        foreach($base['mapperrules'] as $ruleEntryId=>$rule){
            if (!isset($rule['Content']['Source column']) || !isset($rule['Content']['...or constant'])){continue;}
            $rowKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleEntryId);
            // get text and add to entry
            if (empty($rule['Content']['...or constant'])){
                // direct mapping
                $isset=isset($entry[$rule['Content']['Source column']]);
                $matchText=$entry[$rule['Content']['Source column']]??'';
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
                $this->oc['SourcePot\Datapool\Foundation\Computations']->add2combineCache($rule['Content']['Combine']??'',$rule['Content']['Target column'],$rule['Content']['Target key'],$matchText);
            }
        }
        return ['result'=>$result,'failed'=>$mappingFailed];
    }

    private function sectionNamesById(array $callingElement):array
    {
        $sectionsById=[];
        $entriesSelector=['Source'=>$this->entryTable,'Name'=>$callingElement['EntryId'],'Group'=>'parserSectionRules'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            if (!isset($entry['Content']['Section name'])){continue;}
            $sectionsById[$entry['EntryId']]=$entry['Content']['Section name'];
        }
        $sectionsById['FULL']='Single entry full text';
        $sectionsById['NEW ENTRY FULL']='New entry full text';
        $sectionsById['NEW ENTRY INDEX']='New entry index';
        return $sectionsById;
    }

    private function sections(array $base,string $fullText):array
    {
        // get split params
        $sinleEntrySplitParams=$multipleEntrySplitParams=$multipleEntriesSignleEntrySplitParams=[];
        foreach($base['parsersectionrules'] as $ruleId=>$rule){
            if (!isset($rule['Content']['Section type']) || !isset($rule['Content']['Regular expression'])){
                continue;
            }
            if ($rule['Content']['Section type']==='singleEntry' && empty($multipleEntrySplitParams)){
                $sinleEntrySplitParams[$ruleId]=[
                    'ruleId'=>$ruleId,
                    'regEx'=>$rule['Content']['Regular expression'],
                    'isSectionStartIndicator'=>boolval($rule['Content']['...is section']),
                ];
            } else if ($rule['Content']['Section type']==='multipleEntries'){
                $multipleEntrySplitParams[$ruleId]=[
                    'ruleId'=>$ruleId,
                    'regEx'=>$rule['Content']['Regular expression'],
                    'isSectionStartIndicator'=>boolval($rule['Content']['...is section']),
                ];
            } else if ($rule['Content']['Section type']==='singleEntry' && !empty($multipleEntrySplitParams)){
                $multipleEntriesSignleEntrySplitParams[$ruleId]=[
                    'ruleId'=>$ruleId,
                    'regEx'=>$rule['Content']['Regular expression'],
                    'isSectionStartIndicator'=>boolval($rule['Content']['...is section']),
                ];
            }
        }
        // split into sections - full text single entry sections
        $sections=[];
        $sections['singleEntry']=$this->singleEntrySplit($sinleEntrySplitParams,$fullText);
        $sections['singleEntry']['FULL']=$fullText;
        // split into sections - full text multiple entry sections
        $multipleEntriesSections=$this->multiEntriesSplit($multipleEntrySplitParams,$fullText);
        foreach($multipleEntriesSections as $index=>$multipleEntriesSection){
            $sections['multipleEntries'][$index]['NEW ENTRY INDEX']=strval($index);
            $sections['multipleEntries'][$index]['NEW ENTRY FULL']=$multipleEntriesSection;
            $sections['multipleEntries'][$index]+=$this->singleEntrySplit($multipleEntriesSignleEntrySplitParams,$sections['multipleEntries'][$index]['NEW ENTRY FULL']);
        }
        return $sections;
    }
    
    private function singleEntrySplit(array $splitParams,string $text):array
    {
        $sections=[];
        foreach($splitParams as $ruleId=>$splitParam){
            $splitResult=$this->textSplit($text,$splitParam['regEx'],$splitParam['isSectionStartIndicator']);
            if ($splitParam['isSectionStartIndicator']){
                $sections[$ruleId]=$splitResult['textComps'][0]??'';
                //array_unshift($splitResult['textComps'],$splitResult['residue']);
            } else {
                $sections[$ruleId]=array_shift($splitResult['textComps'])??'';
                $splitResult['textComps'][]=$splitResult['residue'];
            }
            $text=implode('',$splitResult['textComps']);
        }
        return $sections;
    }

    private function multiEntriesSplit(array $splitParams,string $text):array
    {   
        $isFirst=TRUE;
        while($splitParam=array_shift($splitParams)){
            $ruleIndex=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($splitParam['ruleId']);
            if ($isFirst){
                $splitResult=$this->textSplit($text,$splitParam['regEx'],$splitParam['isSectionStartIndicator']);
                $firstLevelSections=$splitResult['textComps'];
                $isFirst=FALSE;    
            } else {
                $tmpSections=$sections??$firstLevelSections;
                $sections=[];
                foreach($tmpSections as $index=>$section){
                    $splitResult=$this->textSplit($section,$splitParam['regEx'],$splitParam['isSectionStartIndicator'],$splitResult['residue']??'');
                    if (empty($splitResult['textComps'])){
                        $sections[]=$tmpSections[$index];
                    } else {
                        foreach($splitResult['textComps'] as $textComp){
                            $textComp=($splitParam['isSectionStartIndicator'])?($splitResult['residue'].' |'.$ruleIndex.'| '.$textComp):$textComp;
                            $textComp=(!$splitParam['isSectionStartIndicator'])?($textComp.' |'.$ruleIndex.'| '.$splitResult['residue']):$textComp;
                            $sections[]=$textComp;
                        }
                    }
                }
            }
        }
        return $sections??[];
    }

    private function textSplit(string $text,string $regEx,bool $splitBeforeMatch=TRUE):array
    {
        $result=['textComps'=>[],'residue'=>''];
        $text=preg_replace('/('.$regEx.')/u',($splitBeforeMatch)?(self::SPLIT_MARKER.'${1}'):('${1}'.self::SPLIT_MARKER),$text);
        $result['textComps']=explode(self::SPLIT_MARKER,$text??'');
        if ($splitBeforeMatch){
            $result['residue']=array_shift($result['textComps']);
        } else {
            $result['residue']=array_pop($result['textComps']);
        }
        return $result;
    }

    private function finalizeEntry(array $base,array $flatSourceEntry,array $targetEntries,bool $testRun,bool $keepSource=FALSE):array
    {
        // combine target entries
        foreach($targetEntries as $targetEntry){
            $flatTargetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($targetEntry);
            $flatEntry=array_merge($flatEntry??$flatSourceEntry,$flatTargetEntry);
        }
        // move entry
        $params=current($base['parserparams']);
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatEntry);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$base['entryTemplates'][$params['Content']['Target on success']],TRUE,$testRun,$keepSource);
        return $entry;
    }

}
?>