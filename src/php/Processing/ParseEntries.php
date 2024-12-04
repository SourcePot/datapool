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
        
    private $sections=array(0=>'Complete text','START'=>'START');
    
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
        //
        $contentStructure=array('Rule relevant on section'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>$this->sections),
                                'Constant or...'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                'regular expression'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE,'title'=>"Add your regular expression to search for matches within the 'Source column' content here. You can check your regular expressions on different web pages. Use brackets to define sub matches. 'Match index'=0 wil return the whole match,\n'Match index'=1 the first sub match defined by the first set if brakets,..."),
                                'Match index'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array(0,1,2,3,4,5,6,7,8,9,10)),
                                'Target data type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'string','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE),
                                'Target column'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Name','standardColumsOnly'=>TRUE),
                                'Target key'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                'Allow multiple hits'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array('No','Yes','Multiple entries'),'title'=>"Allow multiple hits\nYES:\twill create array\nNO: \tlast match will override previous match\nMULTIPLE ENTRIES:\twill create a new entry per match (make sure selector is unique)"),
                                'Remove match'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array('No','Yes')),
                                'Match required'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array('No','Yes')),
                                'Combine on update'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getCombineOptions(),'title'=>"Controls the resulting value, fIf the target already exsists."),
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
                                'Required'=>array('method'=>'select','excontainer'=>TRUE,'value'=>0,'options'=>array('-','Must not be empty'),'keep-element-content'=>TRUE),
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
        $base=array('parserparams'=>array(),'parsersectionrules'=>array(),'parserrules'=>array(),'mapperrules'=>array());
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Parser statistics'=>array('Entries'=>array('value'=>0),
                                                 'Success'=>array('value'=>0),
                                                 'Failed'=>array('value'=>0),
                                                 'No text, skipped'=>array('value'=>0),
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
        foreach($result['Mutliple entries → one target'] as $EntryId=>$hitsName){
            if ($hitsName['hits']<2){unset($result['Mutliple entries → one target'][$EntryId]);}
        }
        $statistics=array('Statistics'=>$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix());
        $statistics['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $statistics['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $statistics+$result;
    }
    
    private function parseEntry($base,$sourceEntry,$result,$testRun){
        $params=current($base['parserparams']);
        $params=$params['Content'];
        // get source text
        $flatSourceEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sourceEntry);
        $fullText=(isset($flatSourceEntry[$params['Source column']]))?$flatSourceEntry[$params['Source column']]:'';
        $parserFailed='';
        if (empty($fullText)){
            // source column missing or empty
            $parserFailed='No text to parse';
            $result['Parser statistics']['No text, skipped']['value']++;
        } else {
            $fullText=preg_replace('/<\/[a-z]+>/','$0 ',$fullText);
            $fullText=strip_tags($fullText);
            $fullText=preg_replace('/\s+/',' ',$fullText);
            $textSections=array(0=>$fullText);
            if ($testRun){$result['Parser text sections']['all sections']=array('value'=>$textSections[0]);}
            $base['parsersectionrules'][0]=array('Content'=>array('Regular expression'=>'_____','Section name'=>'All sections'));
            $lastSection='START';
            $base['parsersectionrules'][$lastSection]=array('Content'=>array('Regular expression'=>'_____','Section name'=>'START'));
            $textSections[$lastSection]=$fullText;
            // create text sections
            foreach($base['parsersectionrules'] as $entryId=>$sectionRule){
                $tmpText=$textSections[$lastSection];
                $regexp='/'.$sectionRule['Content']['Regular expression'].'/u';
                preg_match($regexp,$tmpText,$matches,PREG_OFFSET_CAPTURE);
                if (isset($matches[0][0])){
                    $keywordPos=$matches[0][1]+strlen($matches[0][0]);
                    $textSections[$lastSection]=substr($tmpText,0,$keywordPos);
                    if ($testRun){$result['Parser text sections'][$base['parsersectionrules'][$lastSection]['Content']['Section name']]=array('value'=>$textSections[$lastSection]);}
                    $lastSection=$entryId;
                    $textSections[$lastSection]=substr($tmpText,$keywordPos);
                    if ($testRun){$result['Parser text sections'][$base['parsersectionrules'][$lastSection]['Content']['Section name']]=array('value'=>$textSections[$lastSection]);}
                }
            }
            // parse sections
            $multipleHits2multipleEntriesColumn=FALSE;
            $textSections2show=$textSections;
            $targetEntry=array();
            // loop through parser rules
            foreach($base['parserrules'] as $ruleEntryId=>$rule){
                if (!empty($rule['Content']['Combine on update'])){
                    // this setting will be used/process by the unifyEntry-method
                    $key=$rule['Content']['Target column'];
                    if ($key==='Content' || $key==='Params'){$key.=\SourcePot\Datapool\Root::ONEDIMSEPARATOR.$rule['Content']['Target key'];}
                    $targetEntry['Params']['Combine on update'][$key]=$rule['Content']['Combine on update'];
                }
                $ruleFailed='';
                // get relevant text section
                $relevantText='';
                $relevantText2show='';
                if (empty($rule['Content']['Rule relevant on section'])){
                    $relevantText=$fullText;
                } else if (isset($textSections[$rule['Content']['Rule relevant on section']])){
                    $relevantText=$textSections[$rule['Content']['Rule relevant on section']];
                } else {
                    continue;
                }
                $matchRemoved='<p>No</p>';
                //$rowKey=mb_substr($ruleEntryId,0,mb_strpos($ruleEntryId,'_'));
                $rowKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleEntryId);
                if (isset($base['parsersectionrules'][$rule['Content']['Rule relevant on section']]['Content']['Section name'])){
                    $sectionName=$base['parsersectionrules'][$rule['Content']['Rule relevant on section']]['Content']['Section name'];
                } else {
                    $sectionName='Section missing, check rules!';
                }
                if (!empty($rule['Content']['Constant or...'])){
                    // use constant
                    $matchText=$rule['Content']['Constant or...'];
                    $targetEntry=$this->addValue2flatEntry($targetEntry,$rule['Content']['Target column'],$rule['Content']['Target key'],$matchText,$rule['Content']['Target data type']);
                } else {
                    // match rule with text section
                    $ruleMatchIndex=$rule['Content']['Match index'];
                    preg_match_all('/'.$rule['Content']['regular expression'].'/u',$relevantText,$matches);
                    if (!isset($matches[0][0])){
                        if (strcmp($rule['Content']['Target data type'],'bool')===0){
                            $matchText=FALSE;
                            $targetEntry=$this->addValue2flatEntry($targetEntry,$rule['Content']['Target column'],$rule['Content']['Target key'],$matchText,$rule['Content']['Target data type']);
                        } else {
                            $matchText='No match.';
                            $ruleFailed.='|'.$matchText.' rule '.$ruleEntryId;
                        }
                    } else if (isset($matches[$ruleMatchIndex])){
                        foreach($matches[$ruleMatchIndex] as $hitIndex=>$matchText){
                            $hits=count($matches[$ruleMatchIndex]);
                            if ($hits>1 && $rule['Content']['Allow multiple hits']){
                                $targetKey=$rule['Content']['Target key'].' '.$hitIndex;
                            } else if ($rule['Content']['Allow multiple hits']){
                                $targetKey=$rule['Content']['Target key'].' 0';
                            } else {
                                $targetKey=$rule['Content']['Target key'];
                            }
                            $targetEntry=$this->addValue2flatEntry($targetEntry,$rule['Content']['Target column'],$targetKey,$matchText,$rule['Content']['Target data type']);
                        }
                        if ($rule['Content']['Allow multiple hits']>1){$multipleHits2multipleEntriesColumn=$rule['Content']['Target column'];}    
                    } else {
                        $matchText='Match, but Match index '.$ruleMatchIndex.' is not set.';
                        $ruleFailed.='|'.$matchText.' rule '.$ruleEntryId;
                    }
                    if (!empty($matches[0][0]) && !empty($rule['Content']['Remove match'])){
                        $matchRemoved='<p style="color:#fd0;">True</p>';
                        $textSections[$rule['Content']['Rule relevant on section']]=str_replace($matches[0][0],'',$textSections[$rule['Content']['Rule relevant on section']]);
                        if (isset($textSections2show[$rule['Content']['Rule relevant on section']])){                        
                            $textSections2show[$rule['Content']['Rule relevant on section']]=str_replace($matches[0][0],'<span title="Rule: '.$rowKey.'" style="text-decoration:line-through;">'.$matches[0][0].'</span>',$textSections2show[$rule['Content']['Rule relevant on section']]);
                            $sectionName=$base['parsersectionrules'][$rule['Content']['Rule relevant on section']]['Content']['Section name'];
                            if ($testRun){$result['Parser text sections'][$sectionName]=array('value'=>$textSections2show[$rule['Content']['Rule relevant on section']]);}
                        }
                    }
                }
                $matchRequired=(isset($rule['Content']['Match required']))?boolval($rule['Content']['Match required']):FALSE;
                $parserFailed.=(!empty($ruleFailed) && $matchRequired)?'|Required match failed':'';
                if ($testRun){
                    $result['Parser rule matches'][$rowKey]=array('Regular expression or constant used'=>$rule['Content']['Constant or...'].$rule['Content']['regular expression'],
                                                                  'Section name'=>$sectionName,
                                                                  'Match'=>$matchText,
                                                                  'Removed match'=>$matchRemoved,
                                                                  'Match required'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($matchRequired),
                                                                  'Rule failed'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($parserFailed),
                                                                  );
                }
                if ($parserFailed){break;}
            } 
            // loop through mapper rules
            $mapperRuleFailed='';
            foreach($base['mapperrules'] as $ruleEntryId=>$rule){
                $rowKey=$this->oc['SourcePot\Datapool\Foundation\Database']->getOrderedListIndexFromEntryId($ruleEntryId);
                $matchText='';
                if (empty($rule['Content']['...or constant'])){
                    $matchText=(isset($flatSourceEntry[$rule['Content']['Source column']]))?$flatSourceEntry[$rule['Content']['Source column']]:'';
                    if (empty($matchText)){
                        $mapperRuleFailed.='|'.$rule['Content']['Source column'].' not set or empty';
                    }
                } else {
                    $matchText=$rule['Content']['...or constant'];
                }
                $targetEntry=$this->addValue2flatEntry($targetEntry,$rule['Content']['Target column'],$rule['Content']['Target key'],$matchText,$rule['Content']['Target data type']);
                //
                $mustNotBeEmpty=(isset($rule['Content']['Required']))?boolval($rule['Content']['Required']):FALSE;
                $parserFailed.=(!empty($mapperRuleFailed) && $mustNotBeEmpty)?'|Mapper rule failed':'';
                $result['Mapper rule matches'][$rowKey]=array('Source column or constant'=>(($rule['Content']['Source column']==='useValue')?$rule['Content']['...or constant']:$matchText),
                                                              'Must not be empty'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($mustNotBeEmpty),
                                                              'Rule failed'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($parserFailed),
                                                             );
                if ($parserFailed){break;}
            }
        }
        // process result
        if (empty($parserFailed)){
            // wrapping up
            $multipleEntriesValueArr=array();
            $targetEntry=array_replace_recursive($base['entryTemplates'][$params['Target on success']],$targetEntry);
            foreach($targetEntry as $key=>$value){
                if (mb_strpos($key,'Content')===0 || mb_strpos($key,'Params')===0){continue;}
                if (!is_array($value)){continue;}
                foreach($value as $subKey=>$subValue){
                    $value[$subKey]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->valueArr2value($subValue);
                }
                if ($multipleHits2multipleEntriesColumn){
                    if (strcmp($multipleHits2multipleEntriesColumn,$key)===0){
                        $multipleEntriesValueArr=$value;
                    }
                }
                // set order of array values
                ksort($value);
                $targetEntry[$key]=implode($params['Array→string glue'],$value);
            }
            // static content mapping
            $sourceEntry['Content']=array();
            if (isset($sourceEntry['UNYCOM'])){$sourceEntry['Content']['UNYCOM']=$sourceEntry['UNYCOM'];}
            if (isset($sourceEntry['UNYCOM list'])){$sourceEntry['Content']['UNYCOM list']=$sourceEntry['UNYCOM list'];}
            if (isset($sourceEntry['Costs'])){$sourceEntry['Content']['Costs']=$sourceEntry['Costs'];}
            if (isset($sourceEntry['Costs description'])){$sourceEntry['Content']['Costs description']=$sourceEntry['Costs description'];}
            // move to target or targets
            if ($multipleHits2multipleEntriesColumn){
                foreach($multipleEntriesValueArr as $subKey=>$value){
                    $targetEntry[$multipleHits2multipleEntriesColumn]=$value;
                    $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetEntry,TRUE,$testRun,TRUE);
                    $result['Parser statistics']['Success']['value']++;
                }
            } else {
                $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$targetEntry,TRUE,$testRun);
                $result['Parser statistics']['Success']['value']++;
            }
            // multiple hit per target statistic
            if (isset($result['Mutliple entries → one target'][$targetEntry['EntryId']])){
                $result['Mutliple entries → one target'][$targetEntry['EntryId']]['hits']++;
            } else {
                $result['Mutliple entries → one target'][$targetEntry['EntryId']]['hits']=1;
                $result['Mutliple entries → one target'][$targetEntry['EntryId']]['name']=$targetEntry['Name'];
            }
            // get sample
            if (!isset($result['Sample result (success)']) || mt_rand(1,100)>80){
                $result['Sample result (success)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
            }
        } else {
            // Parser failed
            $result['Parser statistics']['Failed']['value']++;
            $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Target on failure']],TRUE,$testRun);
            // get sample
            if (!isset($result['Sample result (failure)']) || mt_rand(1,100)>80){
                $result['Sample result (failure)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
            }
        }
        return $result;
    }
        
    private function addValue2flatEntry($entry,$baseKey,$key,$value,$dataType)
    {
        // value datatype conversions
        $newValue=array($key=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->convert($value,$dataType));
        // add new value to entry
        if (!isset($entry[$baseKey])){$entry[$baseKey]=array();}
        if (!is_array($entry[$baseKey]) && empty($key)){$entry[$baseKey]=array();}
        if (is_array($entry[$baseKey])){
            $entry[$baseKey]=array_replace_recursive($entry[$baseKey],$newValue);
        } else {
            $entry[$baseKey]=$newValue;
        }
        return $entry;
    }


}
?>