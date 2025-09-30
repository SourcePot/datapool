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

class Feeds implements \SourcePot\Datapool\Interfaces\Job,\SourcePot\Datapool\Interfaces\App,\SourcePot\Datapool\Interfaces\Receiver,\SourcePot\Datapool\Interfaces\HomeApp{
    
    private const APP_ACCESS='ALL_MEMBER_R';

    private const SECTIONS=[
        // German
        'DE - Aktuelles'=>'DE - Aktuelles',
        'DE - Politik'=>'DE - Politik',
        'DE - Wirtschaft'=>'DE - Wirtschaft',
        'DE - Technologie'=>'DE - Technologie',
        'DE - Computer'=>'DE - Computer',
        'DE - Computersicherheit'=>'DE - Computersicherheit',
        'DE - Wissenschaft'=>'DE - Wissenschaft',
        'DE - Wetter'=>'DE - Wetter',
        'DE - Tourismus'=>'DE - Tourismus',
        'DE - Filme, Musik'=>'DE - Filme, Musik',
        'DE - Kunst'=>'DE - Kunst',
        'DE - Natur'=>'DE - Natur',
        // English
        'EN - News'=>'EN - News',
        'EN - Politics'=>'EN - Politics',
        'EN - Economy'=>'EN - Economy',
        'EN - Technology'=>'EN - Technology',
        'EN - Computer'=>'EN - Computer',
        'EN - Cyber Security'=>'EN - Cyber Security',
        'EN - Science'=>'EN - Science',
        'EN - Weather'=>'EN - Weather',
        'EN - Tourism'=>'EN - Tourism',
        'EN - Movies Music, Musik'=>'EN - Movies Music',
        'EN - Art'=>'EN - Art',
        'EN - Nature'=>'EN - Nature',
        // Spanish
        'ES - Noticias'=>'ES - Noticias',
        'ES - Política'=>'ES - Política',
        'ES - Economía'=>'ES - Economía',
        'ES - Tecnología'=>'ES - Tecnología',
        'ES - Informática'=>'ES - Informática',
        'ES - Ciberseguridad'=>'ES - Ciberseguridad',
        'ES - Ciencia'=>'ES - Ciencia',
        'ES - Tiempo'=>'ES - Tiempo',
        'ES - Turismo'=>'ES - Turismo',
        'ES - Películas Música'=>'ES - Películas Música',
        'ES - Arte'=>'ES - Arte',
        'ES - Naturaleza'=>'ES - Naturaleza',
    ];
    
    private const CHANNEL_MAPPING=[
        'lastBuildDate'=>'date','pubDate'=>'date',
        'title'=>'title',
        'link'=>'link',
        'src'=>'src','url'=>'src',
        'description'=>'description',
        'language'=>'language',
    ];
    
    private const ITEM_MAPPING=[
        'published'=>'date','pubDate'=>'date','updated'=>'date',
        'link'=>'link','href'=>'link',
        'src'=>'src','url'=>'src',
        'title'=>'Subject',
        'description'=>'Message','summary'=>'Message',
        'guid'=>'id','id'=>'id',
        'credit'=>'credit','author'=>'credit',
    ];

    private $oc;
    
    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'All members can read forum entries'],
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
        $this->urlSelector=['Source'=>$this->oc['SourcePot\Datapool\AdminApps\Settings']->getEntryTable(),'Group'=>'Feeds','Folder'=>'Settings','Name'=>'URL'];
        $this->oc['SourcePot\Datapool\Foundation\Explorer']->getGuideEntry($this->urlSelector);
    }

    /**
    * Housekeeping method periodically executed by job.php (this script should be called once per minute through a CRON-job)
    * @param    string $vars Initial persistent data space
    * @return   array  Array Updateed persistent data space
    */
    public function job(array $vars):array
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
            $vars['html']='<h3>Processed: '.$urlsEntry['Content']['URL'].'</h3>';
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
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__,['addEntry'=>FALSE,'editEntry'=>FALSE,'settingsEntry'=>FALSE,'setRightsEntry'=>FALSE]);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            $html='';
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Date',FALSE) as $entry){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','keep-element-content'=>TRUE,'element-content'=>'.','function'=>'loadEntry','source'=>$entry['Source'],'entry-id'=>$entry['EntryId'],'class'=>'feeds','style'=>['clear'=>'both']]);
            }
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    public function feedsUrlsWidget(array $arr):array
    {
        $arr['selector']=$this->urlSelector;
        $accessOptions=$this->oc['SourcePot\Datapool\Foundation\Access']->getAccessOptionsStrings();
        $contentStructure=[
            'URL'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'https://malpedia.caad.fkie.fraunhofer.de/feeds/rss/latest','excontainer'=>TRUE],
            'Section'=>['method'=>'select','excontainer'=>TRUE,'value'=>'EN - News','options'=>self::SECTIONS,'keep-element-content'=>TRUE],
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
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'url'=>$this->currentUrlEntryContent['URL'],'itemCount'=>0];
        // get feed items
        if (isset($feed['channel']['item'])){
            $hasChannelKey=TRUE;
            $items=$feed['channel']['item'];
        } else if (isset($feed['channel']['entry'])){
            $hasChannelKey=TRUE;
            $items=$feed['channel']['entry'];
        } else if (isset($feed['entry'])){
            $hasChannelKey=FALSE;
            $items=$feed['entry'];
        } else if (isset($feed['item'])){
            $hasChannelKey=FALSE;
            $items=$feed['item'];
        } else {
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" feed processing failed, key "channel → item/entry" missing for "{url}".',$context);
            return $context['itemCount'];
        }
        // get feed data
        if ($hasChannelKey){
            $feed=$feed['channel'];
        }
        if (isset($feed['entry'])){unset($feed['entry']);}
        if (isset($feed['item'])){unset($feed['item']);}
        $feed=$this->mapArray($feed,self::CHANNEL_MAPPING);
        // create entryTemplate
        $entryTemplate=[
            'Source'=>$this->entryTable,
            'Group'=>$this->currentUrlEntryContent['Section']??'EN - News',
            'Folder'=>$feed['title']??'Title missing',
            'Read'=>$this->currentUrlEntryContent['Visibility'],
            'Write'=>'ALL_R',
            'Owner'=>'SYSTEM',
            'Content'=>[],
            'Params'=>[
                'Feed'=>[
                    'Language'=>$feed['language']??'en',
                    'Date'=>$this->getFeedDate($feed['date']??'now')['DB_TIMEZONE'],
                    'URL'=>$context['url'],
                    'Description'=>$feed['description']??$feed['title']??'Description missing',
                    'Feed link'=>$feed['link'],
                    ],
                'Feed item'=>[],
                ],
            'Expires'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','PT2H'),
        ];
        // create entries from items
        $entries=[];
        $tmpDir=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir();
        foreach($items as $item){
            $item=$this->mapArray($item,self::ITEM_MAPPING);
            if (isset($item['link'])){
                $link=$item['link'];
                unset($item['link']);
            }
            if (isset($item['src'])){
                $src=$item['src'];
                unset($item['src']);
            }
            $entry=$entryTemplate;
            $entry['Content']=$item;
            $entry['Name']=$item['Subject'];
            if (isset($link)){
                $entry['Params']['Feed item']['Item link']=['tag'=>'a','href'=>$link,'element-content'=>'Open','title'=>$entry['Content']['Subject'],'target'=>'_blank','class'=>'btn'];
            }
            $tmpFile='';
            if (isset($src)){
                // create file name from url
                $urlComps=parse_url($src);
                parse_str($urlComps['query']??'',$queryArr);
                $fileNameComps=pathinfo($urlComps['path']);
                $entry['Params']['Feed item']['Item media query']=$queryArr;
                $fileName=preg_replace('/[^A-Za-z0-9]/','_',$entry['Name']);
                // store media file
                $tmpFile=$tmpDir.$fileName.'.'.$fileNameComps['extension'];
                $fileContent=file_get_contents($src);
                if (!empty($fileContent)){
                    file_put_contents($tmpFile,$fileContent);
                }
            }
            // finalize entry
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],'0','',FALSE);
            if (is_file($tmpFile)){
                $this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entry($tmpFile,$entry,FALSE,TRUE);
            } else {
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,TRUE);
            }
            $context['itemCount']++;
        }
        return $context['itemCount'];
    }

    private function mapArray(array $in, array $mapping):array
    {
        $flatIn=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($in);
        $leafesIn=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatArrLeaves($flatIn);
        $out=[];
        foreach($leafesIn as $key=>$value){
            if (empty($value)){continue;}
            preg_match('/\ssrc="([^"]+)"/',$value,$match);
            if (!empty($match[1])){
                $key='url';
                $value=$match[1];
            }
            foreach($mapping as $fromKey=>$toKey){
                if (strpos($key,$fromKey)===FALSE){continue;}
                $out[$toKey]=strip_tags((string)$value);
                break;
            }
        }
        return $out;
    }

    private function getFeedDate(string $dateTimeString):array
    {
        $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        $dateTimeArr=['string'=>$dateTimeString];
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
        return ['Feed URL processed'=>$arr['jobVars']['Feed URL processed'],'Feed items loaded'=>$context['Feed items loaded'],'Already processed an skipped'=>$context['Already processed an skipped']];
    }
    
    public function receiverPluginHtml(array $arr):string
    {
        $html='';
        return $html;
    }

    public function receiverSelector(string $id):array
    {
        return ['Source'=>$this->entryTable];
    }    

    private function id2canvasElement($id):array
    {
        $canvasElement=['Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$id];
        return $this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElement,TRUE);
    }

    /******************************************************************************************************************************************
    * HomeApp Interface Implementation
    * 
    */
    
    public function getHomeAppWidget(string $name):array
    {
        // get container
        $elector=['Source'=>$this->entryTable,'refreshInterval'=>5];
        $element=['element-content'=>'','style'=>[]];
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'News','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Container']->container('News item '.__FUNCTION__,'generic',$elector,['method'=>'userItemHtml','classWithNamespace'=>__CLASS__],['style'=>['border'=>'none']]);
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget presents the news.';
        return $info;
    }

    private function updateUserSpecificItems()
    {
        $_SESSION[__CLASS__]['userItems']=[];
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $selector=['Source'=>$this->entryTable];
        $selectors[]=$selector+['Name'=>'%'.$user['Content']['Address']['Town'].'%'];
        $tags=preg_split('/[,;|\f\t\n\v\r]+/',$user['Content']['Contact details']['My tags']??'');
        foreach($tags as $tag){
            $tag=trim($tag);
            if (mb_strlen($tag)<3){continue;}
            $selectors[]=$selector+['Content'=>'%'.$tag.'%'];
        }
        foreach($selectors as $id=>$selector){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Date',FALSE,10,0) as $entry){
                $_SESSION[__CLASS__]['userItems'][$entry['EntryId']]=['Source'=>$this->getEntryTable(),'EntryId'=>$entry['EntryId'],'SelectorId'=>$id];
            }
        }
    }

    private function getUserSpecificItem():array
    {
        // try to get feed item
        if (empty($_SESSION[__CLASS__]['userItems'])){
            $this->updateUserSpecificItems();
        }
        $feedItemEntrySelector=array_pop($_SESSION[__CLASS__]['userItems']);
        $feedItem=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($feedItemEntrySelector??[]);
        if (empty($feedItem)){
            // final try to get feed item
            $this->updateUserSpecificItems();    
            $feedItemEntrySelector=array_pop($_SESSION[__CLASS__]['userItems']);
            $feedItem=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($feedItemEntrySelector??[]);
        }
        return $feedItem;
    }

    public function userItemHtml(array $arr):array
    {
        $presentArr=['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'selector'=>$this->getUserSpecificItem()];
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->presentEntry($presentArr);
        return $arr;
    }

}
?>