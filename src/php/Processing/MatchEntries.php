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

class MatchEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
    private $oc;

    private $entryTable='';
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }
    
    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }

    public function getEntryTable():string{return $this->entryTable;}
    
    /**
     * This method is the interface of this data processing class
     *
     * @param array $callingElementSelector Is the selector for the canvas element which called the method 
     * @param string $action Selects the requested process to be run  
     *
     * @return bool TRUE the requested action exists or FALSE if not
     */
    public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        switch($action){
            case 'run':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runMatchEntries($callingElement,$testRunOnly=FALSE);
                }
                break;
            case 'test':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runMatchEntries($callingElement,$testRunOnly=TRUE);
                }
                break;
            case 'widget':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getMatchEntriesWidget($callingElement);
                }
                break;
            case 'settings':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getMatchEntriesSettings($callingElement);
                }
                break;
            case 'info':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getMatchEntriesInfo($callingElement);
                }
                break;
        }
        return FALSE;
    }

    private function getMatchEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Matching','generic',$callingElement,array('method'=>'getMatchEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
    private function getMatchEntriesInfo($callingElement){
        $matrix=array();
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        return $html;
    }

    public function getMatchEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=array();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runMatchEntries($arr['selector'],FALSE);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runMatchEntries($arr['selector'],TRUE);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Match entries widget'));
        foreach($result as $caption=>$matrix){
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
        }
        $arr['wrapperSettings']=array('style'=>array('width'=>'fit-content'));
        return $arr;
    }

    private function getMatchEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Matching entries settings','generic',$callingElement,array('method'=>'getMatchEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getMatchEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->matchingParams($arr['selector']);
        $arr['html'].=$this->matchingRules($arr['selector']);
        //$selectorMatrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
        //$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for Matching'));
        return $arr;
    }
    
    private function matchingParams($callingElement){
        $return=array('html'=>'','Parameter'=>array(),'result'=>array());
        if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
        $matchTypOptions=array('identical'=>'Identical','contains'=>'Contains','epPublication'=>'European patent publication');
        $contentStructure=array('Column to match'=>array('method'=>'keySelect','standardColumsOnly'=>TRUE,'excontainer'=>TRUE),
                              'Match with'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                              'Match with column'=>array('method'=>'keySelect','standardColumsOnly'=>TRUE,'excontainer'=>TRUE),
                              'Match type'=>array('method'=>'select','value'=>'identical','options'=>$matchTypOptions,'excontainer'=>TRUE),
                              'Match failure'=>array('method'=>'canvasElementSelect','addColumns'=>array(''=>'...'),'excontainer'=>TRUE),
                              'Match success'=>array('method'=>'canvasElementSelect','addColumns'=>array(''=>'...'),'excontainer'=>TRUE),
                              'Combine content'=>array('method'=>'select','value'=>1,'excontainer'=>TRUE,'options'=>array('No','Yes')),
                              'Save'=>array('method'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string'),
                            );
        $contentStructure['Column to match']+=$callingElement['Content']['Selector'];
        $contentStructure['Match with column']+=$callingElement['Content']['Selector'];
        // get selctorB
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['selector']['Content']=array('Column to match'=>'Name');
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
        $arr['caption']='Select column to match and the success/failure targets';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['trStyle']=array('background-color'=>'#a00');}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }

    private function matchingRules($callingElement){
        $contentStructure=array('Operation'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'skipIfFound','options'=>array('skipIfFound'=>'Skip entry if needle found','skipIfNotFound'=>'Skip entry if needle is not found')),
                                 'Entry'=>array('method'=>'select','excontainer'=>TRUE,'keep-element-content'=>TRUE,'value'=>'Entry A','options'=>array('Entry A'=>'Entry A','Entry B'=>'Entry B')),
                                 'Column'=>array('method'=>'keySelect','standardColumsOnly'=>TRUE,'excontainer'=>TRUE),
                                 'Needle'=>array('method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
                                );
        $contentStructure['Column']+=$callingElement['Content']['Selector'];
        if (empty($callingElement['Content']['Selector']['Source'])){return $html;}
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Filter-rules: Skip entries if one of the conditions is met';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }
        
    public function runMatchEntries($callingElement,$testRun=TRUE){
        $base=array('matchingparams'=>array(),'matchingrules'=>array());
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Matching statistics'=>array('Entries A'=>array('value'=>0),
                                                 'Entries B'=>array('value'=>0),
                                                 'Skipped entries A'=>array('value'=>0),
                                                 'Skipped entries B'=>array('value'=>0),
                                                 'Matched'=>array('value'=>0),
                                                 'Failed'=>array('value'=>0),
                                                 'Skip rows'=>array('value'=>0),
                                                 )
                     );
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $entryA){
            if ($entryA['isSkipRow']){
                $result['Matching statistics']['Skip rows']['value']++;
                continue;
            }
            if ($this->skipMatch($entryA,$base,'Entry A')){
                $result['Matching statistics']['Skipped entries A']['value']++;
                continue;
            }
            $result['Matching statistics']['Entries A']['value']++;
            $result=$this->matchEntry($base,$entryA,$result,$testRun);
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function matchEntry($base,$entryA,$result,$testRun){
        $success=FALSE;
        $params=current($base['matchingparams']);
        // match column and value
        $needle=$entryA[$params['Content']['Column to match']];
        $columnToMatch=$params['Content']['Match with column'];
        // create entry b selector
        $selectorB=$base['entryTemplates'][$params['Content']['Match with']];
        if (strcmp($params['Content']['Match type'],'identical')===0){
            // match type identical
            $selectorB[$columnToMatch]=$needle;
        } else if (strcmp($params['Content']['Match type'],'epPublication')===0){
            // match type European patent publication
            $needle=preg_replace('/[^0-9]+/','',$needle);
            if (strlen($needle)<7){$needle='XXXXXXX';}
            $selectorB[$columnToMatch]='%'.$needle[0].'%'.$needle[1].$needle[2].$needle[3].'%'.$needle[4].$needle[5].$needle[6].'%';
        } else {
            // other match types, e.g. contains
            $selectorB[$columnToMatch]='%'.$needle.'%';
        }
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selectorB,TRUE) as $entryB){
            // continue if skip condition is met
            if ($this->skipMatch($entryB,$base,'Entry B')){
                $result['Matching statistics']['Skipped entries B']['value']++;
                continue;
            }
            // filter from group of matches
            if (strcmp($params['Content']['Match type'],'epPublication')===0){
                $contentValue=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entryB['Content']);
                $contentValue=implode('|',$contentValue);
                preg_match('/([0-9]{4}[A-Z]{1,2}[0-9]{5})(WE|EP)(\s{2})/',$contentValue,$match);
                if (empty($match[0])){continue;}
                preg_match('/'.$needle[0].'\s{0,1}'.$needle[1].$needle[2].$needle[3].'\s{0,1}'.$needle[4].$needle[5].$needle[6].'/',$contentValue,$match);
                if (empty($match[0])){continue;}    
            }
            // match detected
            if (!empty($params['Content']['Combine content'])){$entryA['Content']=array_replace_recursive($entryB['Content'],$entryA['Content']);}
            $success=TRUE;
            break;
        }
        if ($success){
            $result['Matching statistics']['Matched']['value']++;
            $entryA=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entryA,'Processing log',array('success'=>'Match column "'.$columnToMatch.'" successful'),FALSE);
            if (isset($base['entryTemplates'][$params['Content']['Match success']])){
                $entryA=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entryA,$base['entryTemplates'][$params['Content']['Match success']],TRUE,$testRun);
            } else {
                $result['Matching statistics']['Kept entry']['value']++;
            }
            if (!isset($result['Sample result (match)']) || mt_rand(0,100)>90){
                $result['Sample result (match)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($entryA);
            }
        } else {
            $result['Matching statistics']['Failed']['value']++;
            $entryA=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entryA,'Processing log',array('failure'=>'Match column "'.$columnToMatch.'" failed'),FALSE);
            if (isset($base['entryTemplates'][$params['Content']['Match failure']])){
                $entryA=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entryA,$base['entryTemplates'][$params['Content']['Match failure']],TRUE,$testRun);
            } else {
                $result['Matching statistics']['Kept entry']['value']++;
            }
            if (!isset($result['Sample result (failed)']) || mt_rand(0,100)>90){
                $result['Sample result (failed)']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($entryA);
            }
        }
        return $result;
    }
    
    private function skipMatch($entry,$base,$entryType='Entry A'){
        $skip=FALSE;
        if (!isset($base['matchingrules'])){return $skip;}
        foreach($base['matchingrules'] as $entryId=>$rule){
            $rule=$rule['Content'];
            if (strcmp($rule['Entry'],$entryType)!==0){continue;}
            $haystack=$entry[$rule['Column']];
            $needle=$rule['Needle'];
            if (is_array($haystack)){$haystack=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($haystack);}
            if (strcmp($rule['Operation'],'skipIfNotFound')===0){
                if (mb_strpos($haystack,$needle)===FALSE){
                    return TRUE;
                }
            } else if (strcmp($rule['Operation'],'skipIfFound')===0){
                if (mb_strpos($haystack,$needle)!==FALSE){
                    return TRUE;
                }
            }
        }
        return $skip;
    }

}
?>