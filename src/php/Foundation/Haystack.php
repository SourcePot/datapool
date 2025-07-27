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
    
    private const SAMPLE_LENGTH=30;

    private const MAX_HEIGHT_RESULTS='60vh';
    
    private const QUERY_SELECTORS=[
        ['app'=>'SourcePot\Datapool\GenericApps\Feeds','Source'=>'feeds','Content'=>'%{{query}}%','orderBy'=>'Date','isAsc'=>FALSE,'limit'=>10],
        ['app'=>'SourcePot\Datapool\GenericApps\Multimedia','Source'=>'multimedia','Content'=>'%{{query}}%','orderBy'=>'Date','isAsc'=>FALSE,'limit'=>10],
        ['app'=>'SourcePot\Datapool\GenericApps\Multimedia','Source'=>'multimedia','Name'=>'%{{query}}%','orderBy'=>'Date','isAsc'=>FALSE,'limit'=>10],
        ['app'=>'SourcePot\Datapool\GenericApps\Multimedia','Source'=>'multimedia','Folder'=>'%{{query}}%','orderBy'=>'Date','isAsc'=>FALSE,'limit'=>5],
        ['app'=>'SourcePot\Datapool\GenericApps\Multimedia','Source'=>'multimedia','Params'=>'%{{query}}%','orderBy'=>'Date','isAsc'=>FALSE,'limit'=>5],
        ['app'=>'ourcePot\Datapool\GenericApps\Documents','Source'=>'documents','Folder'=>'%{{query}}%','orderBy'=>'Date','isAsc'=>FALSE,'limit'=>5],
        ['app'=>'SourcePot\Datapool\Forum\Forum','Source'=>'forum','Content'=>'%{{query}}%','orderBy'=>'Date','isAsc'=>FALSE,'limit'=>10],
        ['app'=>'SourcePot\Datapool\Calendar\Calendar','Source'=>'calendar','Content'=>'%{{query}}%','Start>'=>'{{calendarStartDateTime}}','orderBy'=>'Start','isAsc'=>TRUE,'limit'=>4],
    ];
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
        if ((isset($formData['cmd']['search']) || isset($formData['cmd']['reloadBtnArr']) && !empty($formData['val']['Query']))){
            $serachResult=$this->getSerachResultHtml($formData['val']['Query']);
            $queryEntry['Name']=substr($formData['val']['Query'],0,20);
            $queryEntry['Content']=['Query'=>$serachResult['Query'],'Names'=>$serachResult['Names']];
            $queryEntry['Params']=[__CLASS__=>['Hits'=>$serachResult['Hits']]];
            $queryEntry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','P10D',\SourcePot\Datapool\Root::DB_TIMEZONE);
            $queryEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($queryEntry);
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
        $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'style'=>['float'=>'left','clear'=>'both','padding'=>'1rem 0','width'=>'inherit','background-color'=>'#eee']]);
        // compile html - add result div
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$serachResult['html'],'keep-element-content'=>TRUE,'style'=>['float'=>'left','clear'=>'both','max-height'=>self::MAX_HEIGHT_RESULTS,'overflow-y'=>'auto','width'=>'99vw','border-right'=>'1px dotted #000']]);
        return $arr;
    }

    private function getSerachResultHtml(string $query):array
    {
        // if calendar entry add Start requirement
        $nowDateTime=new \DateTime('now');
        $nowDateTime->setTimezone(new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
        $calendarStartDateTime=$nowDateTime->format('Y-m-d H:i:s');
        $arr=['Query'=>$query,'html'=>'','Names'=>[],'Hits'=>[]];
        if (empty($query)){
            return $arr;
        }
        // loop through query selectors
        $query=preg_replace('/\s+/','%',trim($query));
        $selectors=$this->oc['SourcePot\Datapool\Tools\MiscTools']->generic_strtr(self::QUERY_SELECTORS,['{{query}}'=>$query,'{{calendarStartDateTime}}'=>$calendarStartDateTime]);
        foreach($selectors as $selector){
            $queryColumn=$this->selector2queryColumn($selector,$query);
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read',$selector['orderBy'],$selector['isAsc'],$selector['limit'],0) as $entry){
                if (isset($arr['Hits'][$entry['EntryId']])){
                    if ($arr['Hits'][$entry['EntryId']]===$entry['Source']){continue;}
                }
                $headline=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->selector2string($entry).': '.$this->getQuerySampleText($entry,$queryColumn,$query);
                $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h2','element-content'=>$headline,'keep-element-content'=>TRUE,]);  
                $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','keep-element-content'=>TRUE,'element-content'=>'&#10227;','function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'home','style'=>['clear'=>'both','width'=>'99vw']]);
                $arr['Names'][$entry['EntryId']]=$entry['Name'];
                $arr['Hits'][$entry['EntryId']]=$entry['Source'];
            }
        }
        if (empty($arr['html'])){
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h2','element-content'=>'Nothing found...']);
        } else {
            $hitCountHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>'# '.count($arr['Hits']),'style'=>['position'=>'absolute','top'=>0,'left'=>'5px','background'=>'none'],'keep-element-content'=>FALSE,]);
            $arr['html']=$hitCountHtml.$arr['html'];    
        }
        return $arr;
    }

    private function selector2queryColumn(array $selector,string $query):string|bool
    {
        $queryColumn=FALSE;
        foreach($selector as $column=>$value){
            if (mb_strpos($value,$query)!==FALSE){
                $queryColumn=$column;
                break;
            }
        }
        return $queryColumn;
    }

    private function getQuerySampleText(array $entry,string|bool $column,string $query):string
    {
        if (empty($column)){return '';}
        $halfSmapleLength=intval(self::SAMPLE_LENGTH/2);
        $text=(is_array($entry[$column]))?($this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($entry[$column])):$entry[$column];
        $queryPos=intval(mb_strpos($text,$query));
        $startPos=($queryPos>$halfSmapleLength)?($queryPos-$halfSmapleLength):0;
        $sampleText=mb_substr($text,$startPos,self::SAMPLE_LENGTH);
        $sampleText=str_replace($query,'<span style="background-color:yellow;">'.$query.'</span>',$sampleText);
        $sampleText=($startPos>0)?('...'.$sampleText):$sampleText;
        $sampleText=(mb_strlen($text)>=self::SAMPLE_LENGTH)?($sampleText.'...'):$sampleText;
        $sampleText='<i>'.$sampleText.'</i>';
        return $sampleText;
    }

    public function getHomeAppWidget(string $name):array
    {
        $element=['element-content'=>''];
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Search','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Query','generic',[],['method'=>'getQueryHtml','classWithNamespace'=>'SourcePot\Datapool\Foundation\Haystack'],['style'=>['padding'=>'0px']]);
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget provides a <b>query text field</b>. Queries can be entered and will be used to search certain database tables.<br/>The results will be presented below the query field.';
        return $info;
    }
    
}
?>