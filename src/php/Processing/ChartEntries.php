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
    
    private const MAX_LIMIT=1000000;

    private $oc;

    private $entryTable='';
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            ];

    private $chartTypeOptions=['timeY'=>'DateTime-Y-chart','XY'=>'XY-chart',];
    private $orderByOptions=['Group'=>'Group','Folder'=>'Folder','Name'=>'Name','Date'=>'Date'];


    
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
                'run'=>$this->runChartEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runChartEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getChartEntriesWidget($callingElement),
                'settings'=>$this->getChartEntriesSettings($callingElement),
                'info'=>$this->getChartEntriesInfo($callingElement),
            };
        }
    }

    private function getChartEntriesWidget($callingElement){
        $html='';
        $this->getChartEntriesSettings($callingElement);
        $result=$this->runChartEntries($callingElement,FALSE);
        foreach($result as $caption=>$matrixOrHtml){
            if (is_array($matrixOrHtml)){
                //$html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrixOrHtml,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption,'class'=>''));
            } else {
                $html.=$matrixOrHtml;
            }
        }
        return $html;
    }
    
    private function getChartEntriesInfo($callingElement){
        $matrix=[];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Info','class'=>'max-content']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'?']);
        return $html;
    }

    private function getChartEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Chart settings','generic',$callingElement,['method'=>'getChartEntriesSettingsHtml','classWithNamespace'=>__CLASS__],[]);
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
        $contentStructure=['Width'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'700','excontainer'=>TRUE],
                        'Height'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'300','excontainer'=>TRUE],
                        'offset'=>['method'=>'element','tag'=>'input','type'=>'number','value'=>0,'min'=>0,'excontainer'=>TRUE],
                        'limit'=>['method'=>'element','tag'=>'input','type'=>'number','value'=>-1,'min'=>-1,'excontainer'=>TRUE],
                        'Type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'timeY','options'=>$this->chartTypeOptions,'keep-element-content'=>TRUE],
                        'OrderBy'=>['method'=>'select','excontainer'=>TRUE,'value'=>'Date','options'=>$this->orderByOptions,'keep-element-content'=>TRUE],
                        'Normalize'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>[''=>'-','x'=>'X','y'=>'Y'],'keep-element-content'=>TRUE],
                        ];
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
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        if (empty($arr['selector']['Content'])){$row['trStyle']=['background-color'=>'#a00'];}
        $matrix=['Parameter'=>$row];
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
    }
    
    private function chartRules($callingElement){
        $contentStructure=['trace name'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'trace','excontainer'=>TRUE],
                        'x-selector'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Date','standardColumsOnly'=>FALSE],
                        'y-selector'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'Group','standardColumsOnly'=>FALSE],
                        ];
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
        $base=['chartrules'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        $plotSelector=$callingElement['Content']['Selector'];
        $plotSelector['property']=current($base['chartparams'])['Content'];
        $plotSelector['property']['Title']=$callingElement['Content']['Style']['Text'];
        foreach($base['chartrules'] as $ruleId=>$rule){
            $plotSelector['rule'][$ruleId]=$rule['Content'];
        }
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic($plotSelector);
        $result=['html'=>$this->getChartProcessorPlot($plotSelector)];
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }

    public function getChartProcessorPlot(array $selector=[]):string|array
    {
        if (empty($selector['function'])){
            // draw plot pane request
            $selector['callingClass']=__CLASS__;
            $selector['callingFunction']=__FUNCTION__;
            $selector['id']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($selector,TRUE);
            $_SESSION['plots'][$selector['id']]=$selector;
            $elArr=['tag'=>'h1','keep-element-content'=>TRUE,'element-content'=>$selector['property']['Title']];
            $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $elArr=['tag'=>'div','class'=>'plot','keep-element-content'=>TRUE,'element-content'=>'Plot "'.$selector['id'].'" placeholder','id'=>$selector['id']];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $elArr=['tag'=>'a','class'=>'plot','keep-element-content'=>TRUE,'element-content'=>'SVG','id'=>'svg-'.$selector['id']];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $elArr=['tag'=>'div','class'=>'plot-wrapper','style','keep-element-content'=>TRUE,'element-content'=>$html];
            $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            return $html;
        } else {
            // return plot data request
            $index=0;
            $plotData=$selector;
            $plotData['meta']['id']=$selector['id'];
            if ($plotData['property']['Type']==='timeY'){
                $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
                $plotData['property']['Title'].=' | timezone='.$pageTimeZone;
            }
            $plotData['use']=$plotData['property']['Type'].'plot';
            $plotData['traces']=[];
            if ($plotData['property']['offset']<1){
                $plotData['property']['offset']=FALSE;
            }
            if ($plotData['property']['limit']<2){
                if ($plotData['property']['offset']===FALSE){
                    $plotData['property']['limit']=FALSE;       
                } else {
                    $plotData['property']['limit']=\SourcePot\Datapool\Processing\ChartEntries::MAX_LIMIT;
                }
            }
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($plotData,TRUE,'Read',$plotData['property']['OrderBy'],TRUE,$plotData['property']['limit'],$plotData['property']['offset'],) as $entry){
                $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
                foreach($plotData['rule'] as $ruleId=>$rule){
                    if (empty($rule)){continue;}
                    if (!isset($flatEntry[$rule['x-selector']]) || !isset($flatEntry[$rule['y-selector']])){continue;}
                    $x=str_replace(\SourcePot\Datapool\Root::ONEDIMSEPARATOR,'→',$rule['x-selector']);
                    $y=str_replace(\SourcePot\Datapool\Root::ONEDIMSEPARATOR,'→',$rule['y-selector']);
                    if ($plotData['property']['Type']==='timeY'){
                        if (is_numeric($flatEntry[$rule['x-selector']])){
                            $flatEntry[$rule['x-selector']]=$this->oc['SourcePot\Datapool\Calendar\Calendar']->getTimezoneDate('@'.$flatEntry[$rule['x-selector']],\SourcePot\Datapool\Root::DB_TIMEZONE,$pageTimeZone);
                        } else {
                            $flatEntry[$rule['x-selector']]=$this->oc['SourcePot\Datapool\Calendar\Calendar']->getTimezoneDate($flatEntry[$rule['x-selector']],\SourcePot\Datapool\Root::DB_TIMEZONE,$pageTimeZone);
                        }
                    }
                    $plotData['traces'][$rule['trace name']][$index][$x]=$flatEntry[$rule['x-selector']];
                    $plotData['traces'][$rule['trace name']][$index][$y]=$flatEntry[$rule['y-selector']];
                    $index++;
                }
            }
            return $plotData;
        }
    }

}
?>