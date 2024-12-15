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

    private $entryTable='';
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
        
    private $sections=array('FULL'=>'Complete text','ALL'=>'All non-multpile sections','LAST'=>'Text after last section');
    
    private $paramsTemplate=array('Source column'=>'useValue','Target on success'=>'','Target on failure'=>'','Array→string glue'=>' ');
    
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
    
    public function getEntryTable():string{return $this->entryTable;}

    /**
     * This method is the interface of this data processing class
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method 
     * @param string $action Selects the requested process to be run  
     *
     * @return string|bool Return the html-string or TRUE callingElement does not exist
     */
    public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
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
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Parsing','generic',$callingElement,array('method'=>'getParseEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
    private function getParseEntriesInfo($callingElement){
        //$regExpTester=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->copy2clipboard(htmlentities('https://regexr.com/'));
        $matrix=array();
        $matrix['Parser control: Select parser target and type']['Description']='This control panel sets the fundermental parameters.<br/>"Source column" should contain the text to be parsed.<br/>"Target on success" is the Select-Element an entry should be moved to on success and<br/>"Target on failure" the Select-element used for entries that fail.<br/>If a value is an array but the target column can\'t hold arrays, the array will be imploded using "Array→string glue" as glue.';
        $matrix['Provide rules to divide the text into sections']['Description']='This control panel sets rules to divide the text into sections.<br/>The first section is called "START", all following sections are named by "Section name".<br/>The text section boundery is controlled by the "Regular expression".';
        $matrix['Parser rules: Parse selected entry and copy result to target entry']['Description']='This control panel sets the parser rules. The rules are processed as an ordered list.<br/>Rules are applied to the text section selected by "Rule relevant on section, the "Regular expression" is used to extract releavnt values.<br/>You can use brackets to ecxtract the full match "Match index"=0 or parts of match selected by "Match index">0.<br/>Alternatively to the regular expression you can proviede "Constant or..." as value.<br/>Use "Target data type" to convert the extracted value and "Target column", "Target key" to map the value to the entry.<br/>"Allow multiple hits" will create an array, "Combine on update" combines multiple hits in a new value.';
        $matrix['Mapper rules: map directly to the target entry']['Description']='This control panel allows for the definition of direct mapping from the source entry to the target entry.';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'','class'=>'max-content'));
        $html.='<h2 class="std">Setting-up the parser, first steps:</h2><ol>';
        $html.='<li>Add two Select-elements to the canvas, one for successfiully parsed entries and another for failed entries</li>';
        $html.='<li>Add the two Select-elements to "Parser control: Select parser target and type"</li>';
        $html.='<li>Upload a valid test document, this will enable the selection of the relevant "Source column"</li>';
        $html.='<li>Set the "Source column", for text from a pdf-file the source column is "Content → File content"</li>';
        $html.='<li>Check the content of the source column, e.g. "Content → File content", copy & paste the text into a regular expression tester such as <a href="https://regexr.com/" target="_blank" class="textlink">'.htmlentities('https://regexr.com/').'</a></li>';
        $html.='<li>Find regular expression for all the values you need to extract. If a value type exsists multiple times such as dates, divide the txet into sections</li>';
        $html.='<li>After the value extraction works fine with the test document, run a test with multiple real world documents. Improve the configuration if needed.</li>';
        $html.='</ol>';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'?'));
        return $html;
    }

    public function getParseEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runParseEntries($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runParseEntries($arr['selector'],TRUE);
        }
        // build html
        $btnArr=array('tag'=>'input','type'=>'submit','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $matrix=array();
        $btnArr['value']='Test';
        $btnArr['key']=array('test');
        $matrix['Commands']['Test']=$btnArr;
        $btnArr['value']='Run';
        $btnArr['key']=array('run');
        $matrix['Commands']['Run']=$btnArr;
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Parsing widget'));
        foreach($result as $caption=>$matrix){
            $appArr=array('html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption)));
            $appArr['icon']=$caption;
            if ($caption==='Parser statistics'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getParseEntriesSettings($callingElement){
        // compile html
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Parsing entries settings','generic',$callingElement,array('method'=>'getParseEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getParseEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->parserParams($arr['selector']);
        $arr['html'].=$this->parserSectionRules($arr['selector']);
        $arr['html'].=$this->parserRules($arr['selector']);
        $arr['html'].=$this->mapperRules($arr['selector']);
        return $arr;
    }

    private function parserParams($callingElement){
        $contentStructure=array('Source column'=>array('method'=>'keySelect','value'=>$this->paramsTemplate['Source column'],'excontainer'=>TRUE,'addSourceValueColumn'=>TRUE),
                                'Target on success'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                'Target on failure'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                                'Array→string glue'=>array('method'=>'select','excontainer'=>TRUE,'value'=>$this->paramsTemplate['Array→string glue'],'options'=>array('|'=>'|',' '=>'Space',''=>'None','_'=>'Underscore')),
                                'No match placeholder'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'','placeholder'=>'e.g. {missing}','excontainer'=>TRUE),
                                );
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
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['trStyle']=array('background-color'=>'#a00');}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }
    
    private function parserSectionRules($callingElement){
        $contentStructure=array('Regular expression'=>array('method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'e.g. I\s{0,1}n\s{0,1}v\s{0,1}o\s{0,1}i\s{0,1}c\s{0,1}e\s{0,1}','excontainer'=>TRUE),
                                'Section type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'singleEntry','options'=>array('singleEntry'=>'Single entry','multipleEntries'=>'Multiple entries'),'title'=>''),
                                'Section name'=>array('method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'e.g. Invoice start','excontainer'=>TRUE),
                                );
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Provide rules to divide the text into sections.';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function parserRules($callingElement){
        // complete section selector
        $entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId'],'Group'=>'parserSectionRules');
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            if (!isset($entry['Content']['Section name'])){continue;}
            $this->sections[$entry['EntryId']]=$entry['Content']['Section name'];
        }
        $contentStructure=array('Rule relevant on section'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>$this->sections),
                                'Constant or...'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                'regular expression'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE,'title'=>"Add your regular expression to search for matches within the 'Source column' content here. You can check your regular expressions on different web pages. Use brackets to define sub matches. 'Match index'=0 wil return the whole match,\n'Match index'=1 the first sub match defined by the first set if brakets,..."),
                                'Match index'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array(0,1,2,3,4,5,6,7,8,9,10)),
                                'Target data type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE),
                                'Target column'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
                                'Target key'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                'Allow multiple hits'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array('No','Yes'),'title'=>'If "Yes", an additional numerical index will be added to the "Target key"'),
                                'Match required'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array('No','Yes')),
                                );
        $contentStructure['Target column']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Parser rules: Parse selected entry and copy result to target entry';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function mapperRules($callingElement){
        $contentStructure=array('Source column'=>array('method'=>'keySelect','value'=>$this->paramsTemplate['Source column'],'excontainer'=>TRUE,'addSourceValueColumn'=>TRUE),
                                '...or constant'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                'Target data type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE),
                                'Target column'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Folder','standardColumsOnly'=>TRUE),
                                'Target key'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                'Source value'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array('any','must be set'),'keep-element-content'=>TRUE),
                                );
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
        $entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId'],'Group'=>'parserSectionRules');
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            if (!isset($entry['Content']['Section name'])){continue;}
            $this->sections[$entry['EntryId']]=$entry['Content']['Section name'];
        }
        $base=array('parserparams'=>array(),'parsersectionrules'=>array(),'parserrules'=>array(),'mapperrules'=>array());
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Parser statistics'=>array('Entries'=>array('value'=>0),
                                                 'Success'=>array('value'=>0),
                                                 'Failed'=>array('value'=>0),
                                                 'Skip rows'=>array('value'=>0))
                                                );
        $result['Mutliple entries → one target']=array();
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $sourceEntry){
            if ($sourceEntry['isSkipRow']){
                $result['Parser statistics']['Skip rows']['value']++;
                continue;
            }
            $result['Parser statistics']['Entries']['value']++;
            $result=$this->parseEntry($base,$sourceEntry,$result,$testRun);
        }
        $statistics=array('Statistics'=>$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix());
        $statistics['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $statistics['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
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
        $fullText=(isset($flatSourceEntry[$params['Source column']]))?$flatSourceEntry[$params['Source column']]:'';
        if (empty($fullText)){
            // Parser failed, no content to parse
            $result['Parser statistics']['Failed']['value']++;
            $failedEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Target on failure']],TRUE,$testRun);
            if (!isset($result['Sample result (failure)']) || mt_rand(1,100)>80){
                $result['Sample result (failure)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($failedEntry);
            }         
            return $result;
        } else {
            unset($flatSourceEntry[$params['Source column']]);
            $sourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flat2arr($flatSourceEntry);
        }
        // direct mapping to entry
        $targetEntry=$this->processMapping($base,$flatSourceEntry);
        $result['Mapping']=$targetEntry['processMapping']['result'];
        if ($targetEntry['processMapping']['failed']){
            // mapper failed
            $result['Parser statistics']['Failed']['value']++;
            $failedEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Target on failure']],TRUE,$testRun);            
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
                $result['Sections singleEntry'][$this->sections[$sectionId]]=array('value'=>$section);
            }
            foreach($sections['multipleEntries'] as $sectionId=>$sectionArr){
                foreach($sectionArr as $sectionIndex=>$section)
                $result['Sections multipleEntries "'.$this->sections[$sectionId].'"'][$sectionIndex]=array('value'=>$section);
            }
        }
        // parse single entry sections
        $parserFailed=FALSE;
        $resultArr=array();
        foreach($sections['singleEntry'] as $sectionId=>$section){
            if ($section===FALSE){continue;}
            $targetEntry=$this->processParsing($base,$targetEntry,$sectionId,$section);
            if (isset($targetEntry['processParsing']['result'][$sectionId])){
                $sectionName=$this->sections[$sectionId];
                $resultArr=array_replace_recursive($resultArr,$targetEntry['processParsing']['result'][$sectionId]);
            }
            $parserFailed=($targetEntry['processParsing']['failed'])?TRUE:$parserFailed;
        }
        ksort($resultArr);
        if ($parserFailed){
            if (!isset($result['Parser singleEntry sections <b>failed</b>']) || mt_rand(0,100)>70){$result['Parser singleEntry sections <b>failed</b>']=$resultArr;}
            // parser failed
            $result['Parser statistics']['Failed']['value']++;
            $failedEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Target on failure']],TRUE,$testRun);   
            return $result;
        } else {
            if (!isset($result['Parser singleEntry sections <b>success</b>']) || mt_rand(0,100)>70){$result['Parser singleEntry sections <b>success</b>']=$resultArr;}
        }
        // parse multi entries sections
        if (empty($sections['multipleEntries'])){
            $goodEntry=$this->finalizeEntry($base,$sourceEntry,$targetEntry,$result,$testRun);
            $result['Parser statistics']['Success']['value']++;
        } else {
            foreach($sections['multipleEntries'] as $sectionId=>$sectionArr){
                foreach($sectionArr as $entryIndex=>$section){
                    $targetEntryTmp=$this->processParsing($base,$targetEntry,$sectionId,$section);
                    if ($targetEntryTmp['processParsing']['failed']){
                        // parser failed
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
                    $goodEntry=$this->finalizeEntry($base,$sourceEntry,$targetEntryTmp,$result,$testRun);
                }
            }
        }
        if (!isset($result['Sample result <b>success</b>']) || mt_rand(1,100)>70){
            $goodEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2entry($goodEntry);
            $result['Sample result <b>success</b>']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($goodEntry);
        }
        return $result;
    }

    private function processParsing(array $base,array $entry,string $sectionId,string $section):array
    {
        $params=current($base['parserparams']);
        $params=$params['Content'];
        $result=array();
        $failed=FALSE;
        foreach($base['parserrules'] as $ruleEntryId=>$rule){
            $ruleFailed=FALSE;
            // check if rule is relevant
            if (!isset($rule['Content']['Target data type'])){continue;}
            if ($rule['Content']['Rule relevant on section']!==$sectionId){continue;}
            $rowKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleEntryId);
            $result[$rowKey]['Text']=$section;
            $result[$rowKey]['Key']=$rule['Content']['Target column'];
            $result[$rowKey]['Match text']='';
            // section text or constant to matchText
            if (empty($rule['Content']['Constant or...'])){
                $ruleMatchIndex=$rule['Content']['Match index'];
                preg_match_all('/'.$rule['Content']['regular expression'].'/u',$section,$matches);
                if (!empty($rule['Content']['Match required']) && !isset($matches[$ruleMatchIndex][0])){
                    $ruleFailed=TRUE;
                }
                $matches[$ruleMatchIndex][0]=$matches[$ruleMatchIndex][0]??$params['No match placeholder']??'';
                foreach($matches[$ruleMatchIndex] as $hitIndex=>$matchText){
                    if (empty($rule['Content']['Allow multiple hits'])){
                        $targetKey=$rule['Content']['Target key'];
                    } else {
                        $targetKey=$rule['Content']['Target key'].' '.$hitIndex;
                    }
                    $result[$rowKey]['Key'].=' | '.$targetKey;
                    $entry=$this->addValue2flatEntry($entry,$rule['Content']['Target column'],$targetKey,$matchText,$rule['Content']['Target data type']);
                    $result[$rowKey]['Match text'].=empty($result[$rowKey]['Match text'])?$matchText:(' | '.$matchText);
                }
            } else {
                $result[$rowKey]['Key'].=' | '.$rule['Content']['Target key'];
                $entry=$this->addValue2flatEntry($entry,$rule['Content']['Target column'],$rule['Content']['Target key'],$rule['Content']['Constant or...'],$rule['Content']['Target data type']);
                $result[$rowKey]['Match text'].=$rule['Content']['Constant or...'];
            }
            $result[$rowKey]['Match required']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($rule['Content']['Match required']);
            $result[$rowKey]['Rule Failed']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($ruleFailed);
            $failed=($ruleFailed)?TRUE:$failed;
        }
        $entry[__FUNCTION__]['result'][$sectionId]=$result;
        $entry[__FUNCTION__]['failed']=$failed;
        return $entry;
    }

    private function processMapping($base,$entry):array
    {
        $result=array();
        $failed=FALSE;
        $isset=FALSE;
        foreach($base['mapperrules'] as $ruleEntryId=>$rule){
            if (!isset($rule['Content']['Source column']) || !isset($rule['Content']['...or constant'])){continue;}
            $rowKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleEntryId);
            // get text and add to entry
            if (empty($rule['Content']['...or constant'])){
                $isset=isset($entry[$rule['Content']['Source column']]);
                $matchText=(isset($entry[$rule['Content']['Source column']]))?$entry[$rule['Content']['Source column']]:'';
            } else {
                $isset=TRUE;
                $matchText=$rule['Content']['...or constant'];
            }
            // check for error
            $failed=!empty($rule['Content']['Source value']) && !$isset;
            $result[$rowKey]=array('Source column or constant'=>$matchText,
                                   'Must be set'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($rule['Content']['Source value']),
                                   'Rule failed'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($failed),
                                    );
            // add entry
            if (!$failed){
                $entry=$this->addValue2flatEntry($entry,$rule['Content']['Target column'],$rule['Content']['Target key'],$matchText,$rule['Content']['Target data type']);
            }
        }
        $entry[__FUNCTION__]['result']=$result;
        $entry[__FUNCTION__]['failed']=$failed;
        return $entry;
    }

    private function sections(array $base,string $fullText):array
    {
        $text=$fullText;
        $sections=array('singleEntry'=>array('FULL'=>''),'multipleEntries'=>array());
        foreach($base['parsersectionrules'] as $ruleKey=>$rule){
            $sectionId=$rule['EntryId'];
            if ($rule['Content']['Section type']==='multipleEntries'){
                $sections['multipleEntries'][$sectionId]=array();
                do{
                    $textComps=$this->preg_single_split('/'.$rule['Content']['Regular expression'].'/',$text);
                    $text=$textComps[0];
                    if (isset($textComps[1])){
                        $sections['multipleEntries'][$sectionId][]=$textComps[1];
                    }
                }while(isset($textComps[1]));
            } else {
                $textComps=$this->preg_single_split('/'.$rule['Content']['Regular expression'].'/',$text);
                $text=$textComps[0];
                $sections['singleEntry'][$sectionId]=$textComps[1]??FALSE;
            }
        }
        $sections['singleEntry']['LAST']=$text;
        $sections['singleEntry']['ALL']=implode(' ',$sections['singleEntry']);
        $sections['singleEntry']['FULL']=$fullText;
        return $sections;
    }

    private function preg_single_split(string $regex,string $str):array
    {
        $result=array();
        preg_match($regex,$str,$match,PREG_OFFSET_CAPTURE);
        if (isset($match[0][1])){
            $splitpos=$match[0][1]+strlen($match[0][0]);
            $result[1]=substr($str,0,$splitpos);
            $result[0]=substr($str,$splitpos);
        } else {
            $result[0]=$str;
        }
        return $result;
    }

    private function addValue2flatEntry($entry,$baseKey,$key,$value,$dataType)
    {
        // value datatype conversions
        $newValue=array($key=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($value,$dataType));
        // add new value to entry
        if (!isset($entry[$baseKey])){
            $entry[$baseKey]=$newValue;
        } else if (!is_array($entry[$baseKey])){
            $entry[$baseKey]=$newValue;
        } else {
            $entry[$baseKey]=array_replace_recursive($entry[$baseKey],$newValue);
        }
        return $entry;
    }

    private function finalizeEntry(array $base,array $sourceEntry,array $targetEntry,array $result,bool $testRun):array
    {
        $params=current($base['parserparams']);
        $params=$params['Content'];
        $entryTemplate=$GLOBALS['dbInfo'][$base['entryTemplates'][$params['Target on success']]['Source']];
        foreach($targetEntry as $key=>$value){
            if (!is_array($value)){continue;}
            if (!isset($entryTemplate[$key])){continue;}
            if (is_array($entryTemplate[$key]['value'])){continue;}
            foreach($value as $subKey=>$subValue){
                $value[$subKey]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->valueArr2value($subValue);
            }
            // set order of array values
            ksort($value);
            $targetEntry[$key]=implode($params['Array→string glue'],$value);
        }
        $targetEntry=array_replace_recursive($targetEntry,$base['entryTemplates'][$params['Target on success']]);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetEntry,TRUE,$testRun);
        return $entry;
    }

}
?>