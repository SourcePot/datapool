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

    private const CHART_OPTIONS=[
        'timeY'=>'Value over time chart',
        'XY'=>'XY-chart',
        'histogram'=>'Histogram',
    ];
    
    private const ORDER_BY_OPTIONS=[
        'Group'=>'Group',
        'Folder'=>'Folder',
        'Name'=>'Name',
        'Date'=>'Date'
    ];

    private const CHART_PARAMS_TEMPLATE=[
        'Width'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'700','excontainer'=>TRUE],
        'Height'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'300','excontainer'=>TRUE],
        'Type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'timeY','options'=>self::CHART_OPTIONS,'keep-element-content'=>TRUE],
        'OrderBy'=>['method'=>'select','excontainer'=>TRUE,'value'=>'Date','options'=>self::ORDER_BY_OPTIONS,'keep-element-content'=>TRUE],
        'Normalize'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>[''=>'-','x'=>'X','y'=>'Y'],'keep-element-content'=>TRUE],
    ];

    private const CHART_RULES_TEMPLATE=[
        'trace name'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'trace','excontainer'=>TRUE],
        'x-selector'=>['method'=>'keySelect','value'=>'Date','standardColumsOnly'=>FALSE,'excontainer'=>TRUE],
        'y-selector'=>['method'=>'keySelect','value'=>'Group','standardColumsOnly'=>FALSE,'excontainer'=>TRUE],                
        'yMin'=>['method'=>'element','tag'=>'input','type'=>'number','value'=>'','title'=>'Leave empty if unused','excontainer'=>TRUE],
        'yMax'=>['method'=>'element','tag'=>'input','type'=>'number','value'=>'','title'=>'Leave empty if unused','excontainer'=>TRUE],
        'label-selector'=>['method'=>'keySelect','value'=>'Group','standardColumsOnly'=>FALSE,'addColumn'=>[''=>'-'],'excontainer'=>TRUE],                
    ];

    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];

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
        $contentStructure=self::CHART_PARAMS_TEMPLATE;
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
        $contentStructure=self::CHART_RULES_TEMPLATE;
        $contentStructure['x-selector']+=$callingElement['Content']['Selector'];
        $contentStructure['y-selector']+=$callingElement['Content']['Selector'];
        $contentStructure['label-selector']+=$callingElement['Content']['Selector'];
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$callingElement['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Plot rules';
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $html;
    }

    private function runChartEntries($callingElement,$testRun=FALSE){
        $signals=[];
        $signalsIndex=[];
        $result=['html'=>''];
        $base=['chartrules'=>[]];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        $params=current($base['chartparams'])['Content'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($callingElement['Content']['Selector'],TRUE,'Read',$params['OrderBy'],TRUE) as $entry){
            $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
            foreach($base['chartrules'] as $ruleId=>$rule){
                // create signal
                $traceName=$rule['Content']['trace name'];
                $xSelector=$rule['Content']['x-selector'];
                $ySelector=$rule['Content']['y-selector'];
                $labelSelector=$rule['Content']['label-selector'];
                $index=$signalsIndex[$traceName]??0;
                $signals[$traceName]['Folder']=__CLASS__;
                $signals[$traceName]['Name']=$traceName;
                $signals[$traceName]['EntryId']=$ruleId;
                if ($params['Type']==='timeY'){
                    if ($flatEntry[$xSelector]===NULL){
                        continue;
                    } else if (is_object($flatEntry[$xSelector])){
                        $timeStamp=$flatEntry[$xSelector]->getTimestamp();
                    } else if (!is_numeric($flatEntry[$xSelector])){
                        $xDateTime=new \DateTime($flatEntry[$xSelector]);
                        $timeStamp=$xDateTime->getTimestamp();
                    } else {
                        $timeStamp=intval($flatEntry[$xSelector]);        
                    }
                    $signals[$traceName]['metaOverwrite']=$signals[$traceName]['metaOverwrite']??[];
                    if ($params['Normalize']==='y' && !empty($flatEntry[$ySelector])){
                        if (isset($signals[$traceName]['metaOverwrite']['normalizer']['timeStamp'])){
                            if ($signals[$traceName]['metaOverwrite']['normalizer']['timeStamp']>$timeStamp){
                                $signals[$traceName]['metaOverwrite']['normalizer']=['timeStamp'=>$timeStamp,'value'=>$flatEntry[$ySelector]];
                            }
                        } else {
                            $signals[$traceName]['metaOverwrite']['normalizer']=['timeStamp'=>$timeStamp,'value'=>$flatEntry[$ySelector]];
                        }
                    }
                    if (is_numeric($rule['Content']['yMin'])){
                        $signals[$traceName]['metaOverwrite']['yMin']=$rule['Content']['yMin'];
                    }
                    if (is_numeric($rule['Content']['yMax'])){
                        $signals[$traceName]['metaOverwrite']['yMax']=$rule['Content']['yMax'];
                    }
                    $signals[$traceName]['metaOverwrite']['style']['height']=$params['Height'];
                    $signals[$traceName]['metaOverwrite']['style']['width']=$params['Width'];
                    $signals[$traceName]['Content']['signal'][$index]=['timeStamp'=>$timeStamp,'value'=>$flatEntry[$ySelector],'label'=>$flatEntry[$labelSelector]??''];
                }
                $signalsIndex[$traceName]=$index+1;
            }
        }
        $index=0;
        foreach($signals as $traceName=>$signal){
            $style=($index===0)?['margin-top'=>0]:[];
            $elArr=['tag'=>'p','class'=>'signal-chart','keep-element-content'=>TRUE,'element-content'=>$signal['Folder'].' &rarr; '.$signal['Name'],'style'=>$style];
            $result['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr);
            $result['html'].=$this->oc['SourcePot\Datapool\Foundation\Signals']->signalPlot($signal,$signal['metaOverwrite']);
            $index++;
        }
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }

}
?>