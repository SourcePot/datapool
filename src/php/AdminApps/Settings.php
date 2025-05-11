<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\AdminApps;

class Settings implements \SourcePot\Datapool\Interfaces\App{
    
    private const APP_ACCESS='ADMIN_R';
    
    private $oc;
    
    public  const SELECTORS=['Logger errors'=>['selector'=>['app'=>__CLASS__,'Source'=>'logger','Group'=>'error'],
                                               'containerType'=>'entryList',
                                               'settings'=>['hideUpload'=>TRUE,'orderBy'=>'Date','isAsc'=>FALSE,'columns'=>[['Column'=>'Date','Filter'=>''],['Column'=>'Group','Filter'=>''],['Column'=>'Folder','Filter'=>''],['Column'=>'Content'.\SourcePot\Datapool\Root::ONEDIMSEPARATOR.'msg','Filter'=>'']]],
                                               'description'=>'Error logs can be found here.'],
                             'Logger'=>['selector'=>['app'=>__CLASS__,'Source'=>'logger'],
                                        'containerType'=>'entryList',
                                        'settings'=>['hideUpload'=>TRUE,'orderBy'=>'Date','isAsc'=>FALSE,'columns'=>[['Column'=>'Date','Filter'=>''],['Column'=>'Group','Filter'=>''],['Column'=>'Folder','Filter'=>''],['Column'=>'Content'.\SourcePot\Datapool\Root::ONEDIMSEPARATOR.'msg','Filter'=>'']]],
                                        'description'=>'Here you will find all the logs.'],
                             'Start page'=>['selector'=>\SourcePot\Datapool\Components\Home::WIDGET_SETTINGS_SELECTOR,
                                                    'containerType'=>'generic',
                                                    'settings'=>['method'=>'configureHomeWidgetsHtml','classWithNamespace'=>'SourcePot\Datapool\Components\Home'],
                                                    'description'=>'Configure widget to be shown on the start page'],
                             'Job processing timimg'=>['selector'=>['app'=>__CLASS__,'Source'=>'settings','Group'=>'Job processing','Folder'=>'All jobs','Name'=>'Timing'],
                                                    'text'=>'Use &#9998; to edit the selected Entry...',
                                                    'containerType'=>'',
                                                    'settings'=>[],
                                                    'description'=>'Here you can access the timing of the job processing. Use "&#9998;" (Edit) &rarr; Content to change the timing of a specific job'],
                             'Job processing'=>['selector'=>['app'=>__CLASS__,'Source'=>'settings','Group'=>'Job processing','Folder'=>'All jobs'],
                                                    'containerType'=>'generic',
                                                    'settings'=>['classWithNamespace'=>'SourcePot\Datapool\Foundation\Job','method'=>'getJobOverview'],
                                                    'description'=>'Here you can access the timing of the job processing. Use "&#9998;" (Edit) &rarr; Content to change the timing of a specific job'],
                             'Entry presentation'=>['selector'=>['app'=>__CLASS__,'Source'=>'settings','Group'=>'Presentation'],
                                                    'containerType'=>'generic',
                                                    'settings'=>['method'=>'getPresentationSettingHtml','classWithNamespace'=>'SourcePot\Datapool\Tools\HTMLbuilder'],
                                                    'description'=>'Here you can adjust the entry presentation which is based on the Class and Method used to present the entry. The method presemnting an entry is typically run() or for javascript calls presentEntry().'],
                             'Definitions'=>['selector'=>['app'=>__CLASS__,'Source'=>'definitions','Group'=>'Templates'],
                                                    'containerType'=>'entryList',
                                                    'settings'=>['hideUpload'=>TRUE,'columns'=>[['Column'=>'Folder','Filter'=>''],['Column'=>'Content','Filter'=>''],]],
                                                    'description'=>'Here you can adjust the entry definitions.'],
                             'Feeds'=>['selector'=>['app'=>__CLASS__,'Source'=>'settings','Group'=>'Feeds'],
                                                    'containerType'=>'generic',
                                                    'settings'=>['method'=>'feedsUrlsWidget','classWithNamespace'=>'SourcePot\Datapool\GenericApps\Feeds'],
                                                    'description'=>'Here you can add and remove Feeds.'],
                             'Remote client definitions'=>['selector'=>['app'=>__CLASS__,'Source'=>'remoteclient','EntryId'=>'%_definition'],
                                                    'containerType'=>'entryList',
                                                    'settings'=>['hideUpload'=>TRUE,'columns'=>[['Column'=>'EntryId','Filter'=>''],['Column'=>'Group','Filter'=>''],['Column'=>'Folder','Filter'=>''],['Column'=>'Name','Filter'=>''],]],
                                                    'description'=>'Here you can delete the remote client definitions. It will be renewed when the client is connected']
                            ];
    

    private $entryTable='';
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            'Owner'=>['type'=>'VARCHAR(100)','value'=>'{{Owner}}','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.']
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
        if ($arr===TRUE){
            return array('Category'=>'Admin','Emoji'=>'&#9783;','Label'=>'Settings','Read'=>self::APP_ACCESS,'Class'=>__CLASS__);
        } else {
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            $html='';
            foreach(self::SELECTORS as $selectorName=>$containerDef){
                $match=TRUE;
                foreach($containerDef['selector'] as $column=>$value){
                    if ($column==='app'){continue;}
                    if (empty($selector[$column])){
                        $match=FALSE;
                    } else {
                        $match=strpos($selector[$column],trim($value,'%'))!==FALSE;
                    }
                    if ($match===FALSE){break;}
                }
                if ($match===TRUE){
                    if (empty($containerDef['containerType'])){
                        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h2','keep-element-content'=>TRUE,'element-content'=>$containerDef['text']??'']);
                    } else {
                        $containerDef['selector']=array_merge($containerDef['selector'],$selector);
                        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container($selectorName,$containerDef['containerType'],$containerDef['selector'],$containerDef['settings'],[]);
                    }
                    break;
                }
            }
            if (empty($match)){
                if (!empty($selector['EntryId'])){
                    $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
                    if (isset($entry['Content']) && isset($entry['Params'])){
                        $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix(['Content'=>$entry['Content'],'Params'=>$entry['Params']]);
                        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE]);
                    } 
                } else {
                    $settings=['hideUpload'=>TRUE,'columns'=>[['Column'=>'Group','Filter'=>''],['Column'=>'Folder','Filter'=>''],['Column'=>'Name','Filter'=>'']]];
                    $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container(__CLASS__.' settings','entryList',$selector,$settings,[]);
                }
            }
            if (empty($selector['Folder'])){$html.=$this->settingsOverviewHtml();}
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }

    private function settingsOverviewHtml():string
    {
        // create html
        $matrix=[];
        foreach(self::SELECTORS as $key=>$def){
            if (!empty($def['selector']['Name'])){
                $def['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($def['selector']);
            }
            $btnArr=['cmd'=>'select','selector'=>$def['selector'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
            $btnArr['selector']['Read']=65535;
            $btnArr['selector']['Write']=49152;
            $matrix[$key]['Description']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','style'=>['font-weight'=>'bold','padding'=>'1rem','max-width'=>'40rem'],'keep-element-content'=>TRUE,'element-content'=>$def['description']]);
            $matrix[$key]['Select']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
        }
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'caption'=>'Quick links','hideKeys'=>FALSE,'keep-element-content'=>TRUE]);
        return $html;
    }

    public function setSetting($callingClass,$callingFunction,$setting,$name='System',$isSystemCall=FALSE)
    {
        $entry=['Source'=>$this->entryTable,'Group'=>$callingClass,'Folder'=>$callingFunction,'Name'=>$name];
        $entry['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
        if ($isSystemCall){
            $entry['Owner']='SYSTEM';
        }
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],0,'',FALSE);
        $entry['Content']=$setting;
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,$isSystemCall);
        $this->oc['logger']->log('info','Setting "{name}" updated',['name'=>$name]);    
        return $entry['Content']??[];
    }
    
    public function getSetting($callingClass,$callingFunction,$initSetting=[],$name='System',$isSystemCall=FALSE)
    {
        $entry=['Source'=>$this->entryTable,'Group'=>$callingClass,'Folder'=>$callingFunction,'Name'=>$name];
        if ($isSystemCall){
            $entry['Owner']='SYSTEM';
        }
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],0,'',FALSE);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_MEMBER_R','ALL_MEMBER_R');
        $entry['Content']=$initSetting;
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,$isSystemCall);
        return $entry['Content']??[];
    }
    
    public function getVars($class,$initVars=[],$isSystemCall=FALSE)
    {
        return $this->getSetting('Job processing','Var space',$initVars,$class,$isSystemCall);
    }

    public function setVars($class,$vars=[],$isSystemCall=FALSE)
    {
        return $this->setSetting('Job processing','Var space',$vars,$class,$isSystemCall);
    }
    
}
?>