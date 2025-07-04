<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\DataApps;

class Misc implements \SourcePot\Datapool\Interfaces\Job,\SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=[
        'Folder'=>['type'=>'VARCHAR(255)','value'=>'...','Description'=>'Second level ordering criterion'],
        'Name'=>['skipContainerMonitor'=>TRUE,'type'=>'VARCHAR(1024)','value'=>'New','Description'=>'Third level ordering criterion'],
    ];

    public function __construct($oc)
    {
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

    /**
    * Housekeeping method periodically executed by job.php (this script should be called once per minute through a CRON-job)
    * @param    string $vars Initial persistent data space
    * @return   array  Array Updateed persistent data space
    */
    public function job(array $vars):array
    {
        $vars=$this->oc['SourcePot\Datapool\Processing\CanvasProcessing']->runCanvasProcessingOnClass(__CLASS__,FALSE);
        return $vars;
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    public function run(array|bool $arr=TRUE):array
    {
        $html='';
        if ($arr===TRUE){
            return ['Category'=>'Data','Emoji'=>'⋆','Label'=>'Misc','Read'=>'ALL_DATA_R','Class'=>__CLASS__];
        } else {
            $explorerArr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getDataExplorer(__CLASS__);
            $html.=$explorerArr['contentHtml'];
            if (isset($explorerArr['canvasElement']['Content']['Selector']['Source']) && empty($explorerArr['isEditMode'])){
                $explorerSelector=$explorerArr['canvasElement']['Content']['Selector'];
                $classWithNamespace=$this->oc['SourcePot\Datapool\Root']->source2class((string)$explorerSelector['Source']);
                $pageStateSelector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($classWithNamespace);
                $arr['selector']=array_merge($explorerSelector,$pageStateSelector);
                if (!empty($arr['selector']['EntryId'])){
                    $presentArr=['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
                    $presentArr['selector']=$arr['selector'];
                    $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->presentEntry($presentArr);
                } else if (!empty($arr['selector']['Group'])){
                    $settings=['orderBy'=>'Name','isAsc'=>FALSE,'limit'=>5,'hideUpload'=>TRUE];
                    $settings['columns']=[['Column'=>'Name','Filter'=>''],['Column'=>'Folder','Filter'=>'']];
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container(__CLASS__.' entries table','entryList',$arr['selector'],$settings,[]);
                }
            }
            $arr['toReplace']['{{explorer}}']=$explorerArr['explorerHtml'];
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }

}
?>