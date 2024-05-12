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

class Unycom implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array('Folder'=>array('type'=>'VARCHAR(255)','value'=>'...','Description'=>'Second level ordering criterion'),
                                 'Name'=>array('skipContainerMonitor'=>TRUE,'type'=>'VARCHAR(1024)','value'=>'New','Description'=>'Third level ordering criterion'),
                                 );

    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    public function init(array $oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }

    public function job($vars){
        $vars=$this->oc['SourcePot\Datapool\Processing\CanvasProcessing']->runCanvasProcessingOnClass(__CLASS__,FALSE);
        return $vars;
    }

    public function getEntryTable(){
        return $this->entryTable;
    }
    
    public function getEntryTemplate(){
        return $this->entryTemplate;
    }

    public function run(array|bool $arr=TRUE):array{
        $html='';
        if ($arr===TRUE){
            return array('Category'=>'Data','Emoji'=>'§','Label'=>'UNYCOM','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
        } else {
            $explorerArr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getDataExplorer(__CLASS__);
            $html.=$explorerArr['contentHtml'];
            if (isset($explorerArr['canvasElement']['Content']['Selector']['Source'])){
                $explorerSelector=$explorerArr['canvasElement']['Content']['Selector'];
                $classWithNamespace=$this->oc['SourcePot\Datapool\Root']->source2class((string)$explorerSelector['Source']);
                $pageStateSelector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($classWithNamespace);
                $arr['selector']=array_merge($explorerSelector,$pageStateSelector);
                if (!empty($arr['selector']['EntryId'])){
                    $presentArr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
                    $presentArr['settings']=array('presentEntry'=>__CLASS__.'::'.__FUNCTION__);
                    $presentArr['selector']=$arr['selector'];
                    $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->presentEntry($presentArr);
                } else if (!empty($arr['selector']['Group'])){
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container(__CLASS__.' entries','entryList',$arr['selector'],array('hideUpload'=>TRUE),array());
                }
            }
            $arr['toReplace']['{{explorer}}']=$explorerArr['explorerHtml'];
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    
    
}
?>