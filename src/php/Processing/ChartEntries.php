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

class ChartEntries implements \SourcePot\Datapool\Interfaces\Processor{
    
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
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
    }
    
    public function getEntryTable():string{return $this->entryTable;}

    public function dataProcessor(array $callingElementSelector=array(),string $action='info'){
        // This method is the interface of this data processing class
        // The Argument $action selects the method to be invoked and
        // argument $callingElementSelector$ provides the entry which triggerd the action.
        // $callingElementSelector ... array('Source'=>'...', 'EntryId'=>'...', ...)
        // If the requested action does not exist the method returns FALSE and 
        // TRUE, a value or an array otherwise.
        $callingElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($callingElementSelector,TRUE);
        switch($action){
            case 'run':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                return $this->runChartEntries($callingElement,$testRunOnly=FALSE);
                }
                break;
            case 'test':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->runChartEntries($callingElement,$testRunOnly=TRUE);
                }
                break;
            case 'widget':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getChartEntriesWidget($callingElement);
                }
                break;
            case 'settings':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getChartEntriesSettings($callingElement);
                }
                break;
            case 'info':
                if (empty($callingElement)){
                    return TRUE;
                } else {
                    return $this->getChartEntriesInfo($callingElement);
                }
                break;
        }
        return FALSE;
    }

    private function getChartEntriesWidget($callingElement){
        $html='';
        $this->getChartEntriesSettings($callingElement);
        $result=$this->runChartEntries($callingElement,FALSE);
        foreach($result as $caption=>$matrixOrHtml){
            if (is_array($matrixOrHtml)){
                $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrixOrHtml,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption,'class'=>''));
            } else {
                $html.=$matrixOrHtml;
            }
        }
        return $html;
    }
    
    private function getChartEntriesInfo($callingElement){
        $matrix=array();
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info','class'=>'max-content'));
        return $html;
    }

    private function getChartEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Chart settings','generic',$callingElement,array('method'=>'getChartEntriesSettingsHtml','classWithNamespace'=>__CLASS__),array());
        }
        return $html;
    }
    
    public function getChartEntriesSettingsHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        $arr['html'].=$this->chartParams($arr['selector']);
        $arr['html'].=$this->chartRules($arr['selector']);
        //$selectorMatrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($callingElement['Content']['Selector']);
        //$arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$selectorMatrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Selector used for parsing'));
        return $arr;
    }

    private function chartParams($callingElement){
        $contentStructure=array('caption'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'Chart','excontainer'=>TRUE),
                                'width'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'700','excontainer'=>TRUE),
                                'height'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'300','excontainer'=>TRUE),
                                'x-range'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'','title'=>'e.g.: min|max','excontainer'=>TRUE),
                                'y-range'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'','title'=>'e.g.: min|max','excontainer'=>TRUE),
                                'separate by'=>array('method'=>'select','value'=>'','options'=>array(''=>"don't",'Group'=>'Group','Folder'=>'Folder'),'keep-element-content'=>TRUE,'excontainer'=>TRUE),
                                'orderBy'=>array('method'=>'keySelect','value'=>'Date','standardColumsOnly'=>TRUE,'excontainer'=>TRUE),
                                'Save'=>array('method'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string','excontainer'=>FALSE),
                                );
        $contentStructure['separate by']+=$callingElement['Content']['Selector'];
        $contentStructure['orderBy']+=$callingElement['Content']['Selector'];
        // get selector
        $arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement);
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
        $arr['caption']='Chart control';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['setRowStyle']='background-color:#a00;';}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }
    
    private function chartRules($callingElement){
        $processingOptions=array(''=>'none','group day'=>'Group by day','group month'=>'Group by month','group year'=>'Group by year','avr'=>'Average','sum'=>'Sum','min'=>'Minimum','max'=>'Maximum','count'=>'Count');
        // complete section selector
        $contentStructure=array('trace name'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'trace','excontainer'=>TRUE),
                                'filter'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Date','standardColumsOnly'=>FALSE),
                                'needle'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'2022','excontainer'=>TRUE),
                                '&#10073;'=>array('method'=>'element','tag'=>'p','element-content'=>'X:','excontainer'=>TRUE),
                                'x-selector'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Date','standardColumsOnly'=>FALSE),
                                'x-processing'=>array('method'=>'select','value'=>'','options'=>$processingOptions,'keep-element-content'=>TRUE,'excontainer'=>TRUE),
                                '&#10074;'=>array('method'=>'element','tag'=>'p','element-content'=>'Y:','excontainer'=>TRUE),
                                'y-selector'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Group','standardColumsOnly'=>FALSE),
                                'y-processing'=>array('method'=>'select','value'=>'','options'=>$processingOptions,'keep-element-content'=>TRUE,'excontainer'=>TRUE),
                                );
        $contentStructure['filter']+=$callingElement['Content']['Selector'];
        $contentStructure['x-selector']+=$callingElement['Content']['Selector'];
        $contentStructure['y-selector']+=$callingElement['Content']['Selector'];
        $arr=$this->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Parser rules: Parse selected entry and copy result to target entry';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function runChartEntries($callingElement,$testRun=FALSE){
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($callingElement['Content']['Selector']);
        $traceDefArr=array();
        $props=array('Script start timestamp'=>hrtime(TRUE));
        $entriesSelector=array('Source'=>$this->entryTable,'Name'=>$callingElement['EntryId']);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($entriesSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            if (strpos($entry['Group'],'Params')!==FALSE){
                $props=array_merge($props,$entry['Content']);
            } else if (strpos($entry['Group'],'Rule')!==FALSE){
                $traceDefArr[$entry['EntryId']]=$entry['Content'];
            }
        }
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array();
        $result['html']=$this->oc['SourcePot\Datapool\Foundation\Container']->selector2xyChartHtml($selector,$traceDefArr,$props);
        $result['Statistics']=$this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix();
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$props['Script start timestamp'])/1000000));
        return $result;
    }
    
    public function callingElement2arr($callingClass,$callingFunction,$callingElement){
        if (!isset($callingElement['Folder']) || !isset($callingElement['EntryId'])){return array();}
        $type=$this->oc['SourcePot\Datapool\Root']->class2source(__CLASS__);
        $type.='|'.$callingFunction;
        $entry=array('Source'=>$this->entryTable,'Group'=>$callingFunction,'Folder'=>$callingElement['Folder'],'Name'=>$callingElement['EntryId'],'Type'=>strtolower($type));
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Type'),0);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ALL_CONTENTADMIN_R');
        $entry['Content']=array();
        $arr=array('callingClass'=>$callingClass,'callingFunction'=>$callingFunction,'selector'=>$entry);
        return $arr;
    }

}
?>