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

class OPSEnrichEntries implements \SourcePot\Datapool\Interfaces\Processor{

    private const DESCRIPTION='This processor enriches entries with data from the EPO Open Patent Service.';
    private $oc;

    private $entryTable='';
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            ];
    
    private const METHOD_OPTIONS=['SourcePot\OPS\Biblio|legal'=>'Biblio / Legal'];
    
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
                'run'=>$this->runEnrichEntries($callingElement,$testRunOnly=FALSE),
                'test'=>$this->runEnrichEntries($callingElement,$testRunOnly=TRUE),
                'widget'=>$this->getEnrichEntriesWidget($callingElement),
                'settings'=>$this->getEnrichEntriesSettings($callingElement),
                'info'=>$this->getEnrichEntriesInfo($callingElement),
            };
        }
    }

    private function getEnrichEntriesWidget($callingElement){
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container('Selecting','generic',$callingElement,['method'=>'getEnrichEntriesWidgetHtml','classWithNamespace'=>__CLASS__],[]);
    }
    
     private function getEnrichEntriesInfo($callingElement){
        $matrix=[];
        $matrix['Description']=['<p style="width:40em;">'.self::DESCRIPTION.'</p>'];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Info']);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$html,'icon'=>'Info']);
        return $html;
    }
    
    public function getEnrichEntriesWidgetHtml($arr){
        if (!isset($arr['html'])){$arr['html']='';}
        // command processing
        $result=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['run'])){
            $result=$this->runEnrichEntries($arr['selector'],0);
        } else if (isset($formData['cmd']['test'])){
            $result=$this->runEnrichEntries($arr['selector'],1);
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
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Enriching']);
        foreach($result as $caption=>$matrix){
            $appArr=['html'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption])];
            $appArr['icon']=$caption;
            if ($caption==='Enriched'){$appArr['open']=TRUE;}
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app($appArr);
        }
        $arr['wrapperSettings']=['style'=>['width'=>'fit-content']];
        return $arr;
    }

    private function getEnrichEntriesSettings($callingElement){
        $html='';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Enriching entries params','generic',$callingElement,['method'=>'getEnrichEntriesParamsHtml','classWithNamespace'=>__CLASS__],[]);
        }
        return $html;
    }
    
    public function getEnrichEntriesParamsHtml($arr){
        $callingElement=$arr['selector'];
        $contentStructure=[
                        'Source data'=>['method'=>'keySelect','excontainer'=>TRUE,'value'=>'useValue','standardColumsOnly'=>FALSE,'addSourceValueColumn'=>TRUE],
                        'OPS method'=>['method'=>'select','excontainer'=>TRUE,'value'=>'+','options'=>self::METHOD_OPTIONS,'keep-element-content'=>TRUE],
                        'Map result key to'=>['method'=>'select','excontainer'=>TRUE,'value'=>'Name','options'=>['Group'=>'Group','Folder'=>'Folder','Name'=>'Name'],],
                        'Map result values to'=>['method'=>'select','excontainer'=>TRUE,'value'=>'Name','options'=>['Group'=>'Group','Folder'=>'Folder','Name'=>'Name'],],
                        'Target'=>['method'=>'canvasElementSelect','excontainer'=>TRUE],
                        'Keep source entries'=>['method'=>'select','excontainer'=>TRUE,'value'=>1,'options'=>[0=>'No, move entries',1=>'Yes, copy entries']],
                        ];
        $contentStructure['Source data']+=$callingElement['Content']['Selector'];
        // get selctor
        $callingElementArr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $callingElementArr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($callingElementArr['selector'],TRUE);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        $elementId=key($formData['val']);
        if (isset($formData['cmd'][$elementId])){
            $callingElementArr['selector']['Content']=$formData['val'][$elementId]['Content'];
            $callingElementArr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($callingElementArr['selector'],TRUE);
        }
        // get HTML
        $callingElementArr['canvasCallingClass']=$callingElement['Folder'];
        $callingElementArr['contentStructure']=$contentStructure;
        $callingElementArr['caption']='Merginging control: Select target for enrichd entries';
        $callingElementArr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($callingElementArr);
        if (empty($callingElementArr['selector']['Content'])){$row['trStyle']=['background-color'=>'#a00'];}
        $matrix=['Parameter'=>$row];
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$callingElementArr['caption']]);
        return $arr;
    }
    
    public function runEnrichEntries($callingElement,$testRun=1){
        $base=['mergingparams'=>[],'mergingrules'=>[],'processId'=>$callingElement['EntryId']];
        $base=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2settings(__CLASS__,__FUNCTION__,$callingElement,$base);
        // loop through source entries and parse these entries
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $result=['Enrichd'=>[],];
        // loop through entries
        $selector=$callingElement['Content']['Selector'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE) as $sourceEntry){
            $result=$this->enrichEntries($base,$sourceEntry,$result,$testRun);
        }
        $result['Statistics']=array_merge($this->oc['SourcePot\Datapool\Foundation\Database']->statistic2matrix(),$result['Statistics']??[]);
        $result['Statistics']['Script time']=['Value'=>date('Y-m-d H:i:s')];
        $result['Statistics']['Time consumption [msec]']=['Value'=>round((hrtime(TRUE)-$base['Script start timestamp'])/1000000)];
        return $result;
    }
    
    public function enrichEntries($base,$sourceEntry,$result,$testRun){
        $params=current($base['getenrichentriesparamshtml'])['Content'];
        
        $targetEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($sourceEntry,$base['entryTemplates'][$params['Target']],TRUE,$testRun,$params['Keep source entries']??FALSE);
        
        if (!isset($result['Sample result']) || mt_rand(0,100)>90){
            $result['Sample result']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($targetEntry);
        }
        return $result;
    }
}
?>