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
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );
    
    private $maxResultTableLength=50;

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
     * @return string|bool Return the html-string or TRUE callingElement does not exist
     */
    public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        if (empty($callingElement)){
            return TRUE;
        } else {
            return match($action){
                'run'=>$this->runMatchEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runMatchEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getMatchEntriesWidget($callingElement),
                'settings'=>$this->getMatchEntriesSettings($callingElement),
                'info'=>$this->getMatchEntriesInfo($callingElement),
            };
        }
    }

    private function getMatchEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Matching','generic',$callingElement,array('method'=>'getMatchEntriesWidgetHtml','classWithNamespace'=>__CLASS__),array());
    }
    
    private function getMatchEntriesInfo($callingElement){
        $matrix=array();
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info'));
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$html,'icon'=>'?'));
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Match entries'));
        foreach($result as $caption=>$matrix){
            $appArr=array('html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption)));
            $appArr['icon']=$caption;
            if ($caption==='Matching'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
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
        return $arr;
    }
    
    private function matchingParams($callingElement){
        $return=array('html'=>'','Parameter'=>array(),'result'=>array());
        if (empty($callingElement['Content']['Selector']['Source'])){return $return;}
        $matchTypOptions=array('identical'=>'Identical','contains'=>'Contains','epPublication'=>'European patent publication');
        $contentStructure=array('Column to match'=>array('method'=>'keySelect','value'=>'Name','standardColumsOnly'=>TRUE,'excontainer'=>TRUE),
                              'Match with'=>array('method'=>'canvasElementSelect','excontainer'=>TRUE),
                              'Match with column'=>array('method'=>'keySelect','value'=>'Name','standardColumsOnly'=>TRUE,'excontainer'=>TRUE),
                              'Match type'=>array('method'=>'select','value'=>'unycom','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getMatchTypes(),'excontainer'=>TRUE),
                              'Match probability'=>array('method'=>'select','value'=>80,'options'=>array(100=>'=100',90=>'>90',80=>'>80',70=>'>70',60=>'>60',50=>'>50',45=>'>45',40=>'>40',30=>'>30',25=>'>25'),'excontainer'=>TRUE),
                              'Match failure'=>array('method'=>'canvasElementSelect','addColumns'=>array(''=>'...'),'excontainer'=>TRUE),
                              'Match success'=>array('method'=>'canvasElementSelect','addColumns'=>array(''=>'...'),'excontainer'=>TRUE),
                              'Combine content'=>array('method'=>'select','value'=>1,'excontainer'=>TRUE,'options'=>array('No','Yes')),
                              'Keep source entries'=>array('method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>array(0=>'No, move entries',1=>'Yes, copy entries')),
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

    public function runMatchEntries($callingElement,$testRun=TRUE){
        $base=array('matchingparams'=>array());
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array('Matching'=>array('Entries'=>array('value'=>0),
                                        'Matched'=>array('value'=>0),
                                        'Failed'=>array('value'=>0),
                                        'Skip rows'=>array('value'=>0),
                                        ),
                     'Matches'=>array(),
                     );
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE) as $entry){
            if ($entry['isSkipRow']){
                $result['Matching']['Skip rows']['value']++;
                continue;
            }
            if ($testRun && $result['Matching']['Entries']['value']>$this->maxResultTableLength){
                $result['Matching']['Entries']['value'].=' (testrun, incomplete)';
                $result['Matching']['Matched']['value'].=' (testrun, incomplete)';
                $result['Matching']['Failed']['value'].=' (testrun, incomplete)';
                $result['Matching']['Skip rows']['value'].=' (testrun, incomplete)';
                break;
            }
            $result['Matching']['Entries']['value']++;
            $result=$this->matchEntry($base,$entry,$result,$testRun);
        }
        if (count($result['Matches'])>=$this->maxResultTableLength){
            $currentValues=current($result['Matches']);
            foreach($currentValues as $key=>$value){$currentValues[$key]='...';}
            $result['Matches']['...']=$currentValues;
        }
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }
    
    private function matchEntry($base,$entry,$result,$testRun){
        $params=current($base['matchingparams']);
        $bestMatchCanvasElement=current($this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getCanvasElements(__CLASS__,$params['Content']['Match with']));
        // get best match
        $needle=$entry[$params['Content']['Column to match']];
        $bestMatch=$this->oc['SourcePot\Datapool\Tools\MiscTools']->matchEntry($needle,$base['entryTemplates'][$params['Content']['Match with']],$params['Content']['Match with column'],$params['Content']['Match type'],TRUE);
        // process best match
        $probability=round(100*$bestMatch['probability']);
        $entry['Params']['Processed'][__CLASS__]=$probability;
        $bestMatchKey='Best match';
        if ($bestMatchCanvasElement){
            $bestMatchKey.='<br/>'.$bestMatchCanvasElement['Content']['Style']['Text'].'['.$params['Content']['Match with column'].']';
        }
        if (intval($params['Content']['Match probability'])<100*$bestMatch['probability']){
            // successful match
            if (!empty($params['Content']['Combine content'])){
                $entry['Content']=array_replace_recursive($entry['Content'],$bestMatch['Content']);
            }
            $result['Matching']['Matched']['value']++;
            if (isset($base['entryTemplates'][$params['Content']['Match success']])){
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$base['entryTemplates'][$params['Content']['Match success']],TRUE,$testRun,$params['Content']['Keep source entries']);
            } else {
                $result['Matching']['Kept entry']['value']++;
            }
            if (count($result['Matches'])<$this->maxResultTableLength && isset($bestMatch[$params['Content']['Match with column']])){        
                $result['Matches'][$needle]=array($bestMatchKey=>$bestMatch[$params['Content']['Match with column']],'Match [%]'=>$probability,'Match'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(TRUE));
            }
        } else {
            // failed match
            $result['Matching']['Failed']['value']++;
            if (isset($base['entryTemplates'][$params['Content']['Match failure']])){
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($entry,$base['entryTemplates'][$params['Content']['Match failure']],TRUE,$testRun,$params['Content']['Keep source entries']);
            } else {
                $result['Matching']['Kept entry']['value']++;
            }
            if (count($result['Matches'])<$this->maxResultTableLength && isset($bestMatch[$params['Content']['Match with column']])){
                $result['Matches'][$needle]=array($bestMatchKey=>$bestMatch[$params['Content']['Match with column']],'Match [%]'=>$probability,'Match'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element(FALSE));
            }
        }
        return $result;
    }

}
?>