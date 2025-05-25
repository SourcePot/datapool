<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\GenericApps;

class Feeds implements \SourcePot\Datapool\Interfaces\App,\SourcePot\Datapool\Interfaces\Receiver{
    
    private const APP_ACCESS='ALL_MEMBER_R';
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'All members can read forum entries'],
                            'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'All admins can edit forum entries'],
                            ];
    
    private $urlSelector=[];
    private $currentUrlEntryContent=[];

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
        $this->urlSelector=array('Source'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Feeds','Folder'=>'Settings','Name'=>'URL');
        $this->oc['SourcePot\Datapool\Foundation\Explorer']->getGuideEntry($this->urlSelector);
    }

    public function job($vars):array
    {
        if (empty($vars['URLs2do'])){
            $vars['URLs2do']=[];
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($this->urlSelector,FALSE,'Read','Date',FALSE) as $urlEntry){
                $vars['URLs2do'][$urlEntry['EntryId']]=$urlEntry;
            }
        }
        foreach($vars['URLs2do'] as $key=>$urlsEntry){
            $this->currentUrlEntryContent=$urlsEntry['Content'];
            $this->loadFeed($urlsEntry);
            $vars['Feed URL processed']=$urlsEntry['Content']['URL'];
            // remove from to do list
            $vars['URLs2do'][$key]=NULL;
            break;
        }
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

    public function unifyEntry($feedEntry):array
    {
        return $feedEntry;
    }
    
    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return ['Category'=>'Apps','Emoji'=>'&#10057;','Label'=>'Feeds','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            $html='';
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Date',FALSE) as $entry){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>'.','function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'feeds','style'=>['clear'=>'none']]);
            }
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    public function feedsUrlsWidget(array $arr):array
    {
        $arr['selector']=$this->urlSelector;
        $accessOptions=$this->oc['SourcePot\Datapool\Foundation\Access']->getAccessOptionsStrings();
        $contentStructure=['URL'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'https://malpedia.caad.fkie.fraunhofer.de/feeds/rss/latest','excontainer'=>TRUE],
                            'Visibility'=>['method'=>'select','excontainer'=>TRUE,'value'=>'ALL_R','options'=>$accessOptions],
                            ];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']=$arr['selector']['Folder'];
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],['Source','Group','Folder','Name'],'0','',FALSE);
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $arr;
    
    }

    private function loadFeed($urlEntry)
    {
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__,'url'=>$urlEntry['Content']['URL']);
        if (empty($urlEntry['Content']['URL'])){
            $this->oc['logger']->log('notice','Function "{class} &rarr; {function}()" failed because the url is empty.',$context);
        } else {
            try{
                $client = new \GuzzleHttp\Client();
                $request = new \GuzzleHttp\Psr7\Request('GET',$urlEntry['Content']['URL']);
                // Send an asynchronous request.
                $promise = $client->sendAsync($request)->then(function($response){
                    $body=((string)$response->getBody());
                    $feed=$this->oc['SourcePot\Datapool\Tools\MiscTools']->xml2arr($body);
                    if ($feed){
                        $header=$response->getHeaders();
                        $this->storeFeed($feed,$header);
                    }
                });
                $promise->wait();
            } catch (\Exception $e){
                $context['msg']=$e->getMessage();
                $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" failed for "{url}" with "{msg}".',$context);
            }
        }
    }

    private function storeFeed(array $feed, array $header):int
    {
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__,'url'=>$this->currentUrlEntryContent['URL'],'itemCount'=>0);
        if (!isset($feed['channel'])){
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" feed processing failed, key "channel" missing for "{url}".',$context);
            return 0;
        }
        if (!isset($feed['channel']['title'])){
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" feed processing failed, key "channel → title" missing for "{url}".',$context);
            return 0;
        }
        $date=$feed['channel']['lastBuildDate']??$feed['channel']['published']??$feed['channel']['pubDate']??FALSE;
        if ($date===FALSE){
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" feed processing failed, key "channel → lastBuildDate/published/pubDate" missing for "{url}".',$context);
            return 0;
        }
        if (!isset($feed['channel']['item'])){
            if (isset($feed['channel']['entry'])){
                $feed['channel']['item']=$feed['channel']['entry'];
            } else {
                $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" feed processing failed, key "channel → item/entry" missing for "{url}".',$context);
                return 0;
            }
        }
        $tmpDir=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
        $dateTimeArr=$this->getFeedDate($date);
        $urlComps=parse_url($this->currentUrlEntryContent['URL']);
        $entryTemplate=array('Source'=>$this->entryTable,'Group'=>$urlComps['host'],'Folder'=>$feed['channel']['title'],'Read'=>$this->currentUrlEntryContent['Visibility'],'Date'=>$dateTimeArr['DB_TIMEZONE'],'Content'=>[]);
        $entryTemplate['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','P10D');
        foreach($feed['channel']['item'] as $item){
            $tmpFile='';
            $entry=$entryTemplate;
            $entry['Name']=$item['title'];
            foreach($item as $contentKey=>$contentValue){
                if (is_array($contentValue)){
                    $resource=(empty($contentValue['url']))?((empty($contentValue['src']))?((empty($contentValue['href']))?'':$contentValue['href']):$contentValue['src']):$contentValue['url'];        
                    $resourceComps=pathinfo($resource);
                    $fileName=preg_replace('/[^A-Za-z0-9]/','_',$entry['Name']);
                    $tmpFile=$tmpDir.$fileName.'.'.$resourceComps['extension'];
                    $fileContent=file_get_contents($resource);
                    if (!empty($fileContent)){file_put_contents($tmpFile,$fileContent);}
                } else {
                    switch ($contentKey) {
                        case 'link':
                            $element=array('tag'=>'a','href'=>$contentValue,'title'=>$contentValue,'element-content'=>'Open','target'=>'_blank','class'=>'btn');
                            $entry['Content']['Link']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
                            break;
                        case 'pubDate':
                            $dateTimeArr=$this->getFeedDate($contentValue);
                            $entry['Date']=$dateTimeArr['DB_TIMEZONE'];
                            $entry['Content']['Published']=$dateTimeArr['PAGE_TIMEZONE'];
                            break;
                        case 'published':
                            $dateTimeArr=$this->getFeedDate($contentValue);
                            $entry['Date']=$dateTimeArr['DB_TIMEZONE'];
                            $entry['Content']['Published']=$dateTimeArr['PAGE_TIMEZONE'];
                            break;
                        default:
                            $entry['Content'][ucfirst($contentKey)]=$contentValue;
                    }
                }
            }
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name'),'0','',FALSE);
            if (empty(is_file($tmpFile))){
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
            } else {
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entry($tmpFile,$entry,FALSE,TRUE);
            }
            $context['itemCount']++;
        }
        return $context['itemCount'];
    }

    private function getFeedDate(string $dateTimeString):array
    {
        $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        $dateTimeArr=array('string'=>$dateTimeString);
        $dateTime=new \DateTime($dateTimeString);
        $dateTime->setTimeZone(new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
        $dateTimeArr['DB_TIMEZONE']=$dateTime->format('Y-m-d H:i:s');
        $dateTime->setTimeZone(new \DateTimeZone($pageTimeZone));
        $dateTimeArr['PAGE_TIMEZONE']=$dateTime->format('Y-m-d H:i:s');
        return $dateTimeArr;
    }

    /******************************************************************************************************************************************
    * DATASOURCE: Feed receiver
    *
    * 'EntryId' ... arr-property selects the inbox
    * 
    */
    
    public function receive(string $id):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'Feed items loaded'=>0,'Already processed an skipped'=>0];
        // receive new items from current feed
        $arr=$this->oc['SourcePot\Datapool\Foundation\Job']->trigger(['run'=>__CLASS__]);
        // copy feed items to canvas element
        $canvasElement=$this->id2canvasElement($id);
        $sourceSelector=$this->receiverSelector($id);
        $targetSelector=$canvasElement['Content']['Selector'];
        $processId=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($targetSelector);
        $targetSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arrRemoveEmpty($targetSelector);
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($sourceSelector,TRUE,'Read','Date') as $feedItem){
            if ($this->oc['SourcePot\Datapool\Tools\MiscTools']->wasTouchedByClass($feedItem,$processId,FALSE)){
                // feed entry was already processed
                $context['Already processed an skipped']++;                    
            } else {
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->moveEntryOverwriteTarget($feedItem,$targetSelector,TRUE,FALSE,TRUE,TRUE);
                $context['Feed items loaded']++;
            }
        }
        return array('Feed URL processed'=>$arr['jobVars']['Feed URL processed'],'Feed items loaded'=>$context['Feed items loaded'],'Already processed an skipped'=>$context['Already processed an skipped']);
    }
    
    public function receiverPluginHtml(array $arr):string
    {
        $html='HALLO';
        return $html;
    }

    public function receiverSelector(string $id):array
    {
        return array('Source'=>$this->entryTable);
    }    

    private function id2canvasElement($id):array
    {
        $canvasElement=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$id);
        return $this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElement,TRUE);
    }

}
?>