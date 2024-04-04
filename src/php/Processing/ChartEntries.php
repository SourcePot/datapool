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
        return $arr;
    }

    private function chartParams($callingElement){
        $contentStructure=array('width'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'700','excontainer'=>TRUE),
                                'height'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'300','excontainer'=>TRUE),
                                'x-range'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'','title'=>'e.g.: min|max','excontainer'=>TRUE),
                                'y-range'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'','title'=>'e.g.: min|max','excontainer'=>TRUE),
                                'Save'=>array('method'=>'element','tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'value'=>'string','excontainer'=>FALSE),
                                );
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
        $arr['caption']='Chart control';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        if (empty($arr['selector']['Content'])){$row['trStyle']=array('background-color'=>'#a00');}
        $matrix=array('Parameter'=>$row);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']));
    }
    
    private function chartRules($callingElement){
        $contentStructure=array('trace name'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'trace','excontainer'=>TRUE),
                                'x-selector'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Date','standardColumsOnly'=>FALSE),
                                'x-data type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'date','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE),
                                'y-selector'=>array('method'=>'keySelect','excontainer'=>TRUE,'value'=>'Group','standardColumsOnly'=>FALSE),
                                'y-data type'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'float','options'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDataTypes(),'keep-element-content'=>TRUE),
                                );
        $contentStructure['x-selector']+=$callingElement['Content']['Selector'];
        $contentStructure['y-selector']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Parser rules: Parse selected entry and copy result to target entry';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function runChartEntries($callingElement,$testRun=FALSE){
        $base=array('chartrules'=>array());
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        $chartparams=current($base['chartparams']);
        $prop=array();
        $prop['plotProp']['height']=(isset($chartparams['Content']['height']))?$chartparams['Content']['height']:300;
        $prop['plotProp']['width']=(isset($chartparams['Content']['width']))?$chartparams['Content']['width']:500;
        foreach($base['chartrules'] as $ruleId=>$rule){
            $trace=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->getTraceTemplate();
            $trace['selector']=$callingElement['Content']['Selector'];
            $trace['x']['key']=$rule['Content']['x-selector'];
            $trace['x']['Type']=$rule['Content']['x-data type'];
            $trace['y']['key']=$rule['Content']['y-selector'];
            $trace['y']['Type']=$rule['Content']['y-data type'];
            $trace['Name']=$rule['Content']['trace name'];
            $prop['traces'][$trace['Name']]=$trace;
        }
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=array();
        $result['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->xyTraces2plot($prop);
        $result['Statistics']['Script time']=array('Value'=>date('Y-m-d H:i:s'));
        $result['Statistics']['Time consumption [msec]']=array('Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000));
        return $result;
    }

}
?>