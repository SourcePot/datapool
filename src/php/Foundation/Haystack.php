<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Haystack implements \SourcePot\Datapool\Interfaces\HomeApp{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=[];
    
    public function __construct(array $oc)
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
    
    public function getQueryHtml(array $arr):array
    {
        $queryEntry=['Source'=>$this->entryTable,'Group'=>'Queries','Folder'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()];
        // process data
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['search']) || isset($formData['cmd']['reloadBtnArr'])){
            if (empty($formData['val']['Query'])){
                //
            } else {
                $serachResult=$this->getSerachResultHtml($formData['val']['Query']);
                $queryEntry['Name']=substr($formData['val']['Query'],0,20);
                $queryEntry['Content']=['Query'=>$serachResult['Query'],'Names'=>$serachResult['Names']];
                $queryEntry['Params']=[__CLASS__=>['Hits'=>$serachResult['Hits']]];
                $queryEntry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','P10D',\SourcePot\Datapool\Root::DB_TIMEZONE);
                $queryEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($queryEntry);
            }
        } else {
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($queryEntry,FALSE,'Read','Date',FALSE) as $queryEntry){
                $serachResult['Query']=$queryEntry['Content']['Query'];
                break;
            }
        }
        $serachResult['Query']=$serachResult['Query']??'';
        $serachResult['html']=$serachResult['html']??'';
        // compile html - add query div
        $arr['html']=(empty($arr['html']))?'':$arr['html'];
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','value'=>$serachResult['Query'],'placeholder'=>'Enter your query here...','key'=>['Query'],'excontainer'=>FALSE,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'style'=>[]]);
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'Search','key'=>['search'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'style'=>[]]);
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'style'=>['float'=>'none','width'=>'max-content','margin'=>'0 auto']]);
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'style'=>['float'=>'left','clear'=>'both','padding'=>'1rem 0','width'=>'inherit','background-color'=>'#ddd']]);
        // compile html - add result div
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$serachResult['html'],'keep-element-content'=>TRUE,'style'=>['float'=>'left','clear'=>'both','max-height'=>'40vh','overflow-y'=>'auto','width'=>'inherit']]);
        return $arr;
    }

    private function getSerachResultHtml(string $query):array
    {
        $entryCount=0;
        // if calendar entry add Start requirement
        $nowDateTime=new \DateTime('now');
        $nowDateTime->setTimezone(new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
        $calendarStartDateTime=$nowDateTime->format('Y-m-d H:i:s');
        // create selectors
        $selectors=[['Source'=>$this->oc['SourcePot\Datapool\GenericApps\Multimedia']->getEntryTable(),'Content'=>'%'.$query.'%','orderBy'=>'Date','isAsc'=>FALSE,'limit'=>100],
                    ['Source'=>$this->oc['SourcePot\Datapool\GenericApps\Forum']->getEntryTable(),'Content'=>'%'.$query.'%','orderBy'=>'Date','isAsc'=>FALSE,'limit'=>100],
                    ['Source'=>$this->oc['SourcePot\Datapool\GenericApps\Feeds']->getEntryTable(),'Content'=>'%'.$query.'%','orderBy'=>'Date','isAsc'=>FALSE,'limit'=>100],
                    ['Source'=>$this->oc['SourcePot\Datapool\GenericApps\Calendar']->getEntryTable(),'Content'=>'%'.$query.'%','Start>'=>$calendarStartDateTime,'orderBy'=>'Start','isAsc'=>TRUE,'limit'=>4],
                    ];
        //
        $arr['Query']=$query;
        $arr['html']='';
        $arr['Names']=[];
        $arr['Hits']=[];
        foreach($selectors as $selector){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read',$selector['orderBy'],$selector['isAsc'],$selector['limit'],0) as $entry){
                $arr['Names'][$entry['EntryId']]=$entry['Name'];
                $arr['Hits'][$entry['EntryId']]=$entry['Source'];
                $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>'<br/>','keep-element-content'=>TRUE,'function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'home','style'=>['clear'=>'none']]);
            }
        }
        if (empty($arr['html'])){
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h2','element-content'=>'Nothing found...']);    
        }
        return $arr;
    }

    public function getHomeAppWidget(string $name):array
    {
        $element=[];
        $element['element-content']=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Query','generic',[],['method'=>'getQueryHtml','classWithNamespace'=>'SourcePot\Datapool\Foundation\Haystack'],['style'=>['padding'=>'0px']]);
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget provides a <b>query text field</b>. Queries can be entered and will be used to search certain database tables.<br/>The results will be presented below the query field.';
        return $info;
    }
    
}
?>