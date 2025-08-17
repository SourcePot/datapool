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

class Backbone{
    
    private const HEADER_FILES_TEMPLATES=[
        'ico'=>['tag'=>'link','rel'=>'shortcut icon','srcAttr'=>'href'],
        'css'=>['tag'=>'link','type'=>'text/css','rel'=>'stylesheet','srcAttr'=>'href'],
        'js'=>['tag'=>'script','element-content'=>'','srcAttr'=>'src'],
    ];

    private const EXTERNAL_HEADER_ELEMENTS=[
        'leaflet css'=>['tag'=>'link','rel'=>'stylesheet','href'=>'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css','integrity'=>'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=','crossorigin'=>''],
        'leaflet js'=>['tag'=>'script','src'=>'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js','integrity'=>'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=','element-content'=>'','crossorigin'=>''],
    ];

    private $oc=NULL;
    
    private $settings=[
        'pageTitle'=>'Datapool',
        'metaViewport'=>'width=device-width, initial-scale=1',
        'metaDescription'=>'Light weight web application',
        'metaRobots'=>'index',
        'pageTimeZone'=>'Europe/Berlin',
        'loginForm'=>0,
        'logLevel'=>'monitoring',
        'iconFile'=>'main.ico',
        'logoFile'=>'logo.jpg',
        'homePageContent'=>'video',
        'charset'=>'utf-8',
        'emailWebmaster'=>'admin@datapool.info',
        'path to Xpdf pdftotext executable'=>'',
        ];
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        // get settings asap.
        $settingsFile=$GLOBALS['dirs']['setup'].'Backbone\init.json';
        $settings=$oc['SourcePot\Datapool\Root']->file2arr($settingsFile);
        if (isset($settings['Content'])){
            $this->settings=$settings['Content'];
        }
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
        // initialize page settings
        $settings=['Class'=>__CLASS__,'EntryId'=>'init'];
        $settings['Content']=$this->settings;
        $settings=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($settings,TRUE);
        $this->settings=$settings['Content'];
    }

    public function init()
    {
        // add placeholder
        $this->oc['SourcePot\Datapool\Root']->addPlaceholder('{{pageTitle}}',$this->settings['pageTitle']);
        $this->oc['SourcePot\Datapool\Root']->addPlaceholder('{{pageTimeZone}}',$this->settings['pageTimeZone']);
    }
    
    public function getSettings($key=FALSE)
    {
        if (empty($key)){
            return $this->settings;
        } else if (isset($this->settings[$key])){
            return $this->settings[$key];
        } else {
            return FALSE;
        }
    }

    public function addHtmlPageBackbone(array $arr):array
    {
        $formId=md5($this->settings['pageTitle']);
        $arr['toReplace']=[
            '{{head}}'=>'',
            '{{body}}'=>'',
            '{{bgMedia}}'=>'',
            '{{content}}'=>'Page content is missing...',
            '{{firstMenuBar}}'=>'',
            '{{firstMenuBarExt}}'=>'',
            '{{secondMenuBar}}'=>'',
            '{{explorer}}'=>'',
            ];
        $arr['page html']='';
        $arr['page html'].="<!DOCTYPE html>".PHP_EOL;
        $arr['page html'].='<html xmlns="http://www.w3.org/1999/xhtml" lang="'.$_SESSION['page state']['lngCode'].'">'.PHP_EOL;
        // page header
        $arr['page html'].='<head>'.PHP_EOL;
        $arr['page html'].='<meta charset="'.$this->settings['charset'].'">'.PHP_EOL;
        $arr['page html'].='<meta name="viewport" content="'.$this->settings['metaViewport'].'">'.PHP_EOL;
        $arr['page html'].='<meta name="description" content="'.$this->settings['metaDescription'].'">'.PHP_EOL;
        $arr['page html'].='<meta name="robots" content="'.$this->settings['metaRobots'].'">'.PHP_EOL;
        $arr['page html'].='<meta name="referrer" content="strict-origin" />'.PHP_EOL;
        $arr['page html'].='<title>'.$this->settings['pageTitle'].'</title>'.PHP_EOL;
        $arr['page html'].='{{head}}'.PHP_EOL;
        $arr['page html'].='</head>'.PHP_EOL;
        // page body
        $arr['page html'].='<body>'.PHP_EOL;
        $arr['page html'].='{{bgMedia}}'.PHP_EOL;
        $arr['page html'].='<form name="'.$formId.'" id="'.$formId.'" method="post" enctype="multipart/form-data">'.PHP_EOL;
        $arr['page html'].='<button id="page-refresh" style="display:none;">°</button>'.PHP_EOL;
        $arr['page html'].='<button id="js-refresh" style="display:none;">°</button>'.PHP_EOL;
        $arr['page html'].='{{body}}'.PHP_EOL;
        $arr['page html'].='</form>'.PHP_EOL;
        $arr['page html'].='</body>'.PHP_EOL;
        $arr['page html'].="</html>";
        return $arr;
    }

    public function addHtmlPageHeader(array $arr):array
    {
        $jQueryImport='';
        $jQueryUIImport='';
        $arr['toReplace']['{{head}}']=$arr['toReplace']['{{head}}']??'';
        $wwwMediaFiles=scandir($GLOBALS['relDirs']['media']);
        sort($wwwMediaFiles);
        foreach($wwwMediaFiles as $wwwMediaFile){
            $fileName=$GLOBALS['relDirs']['media'].'/'.$wwwMediaFile;
            $fileArr=pathinfo($fileName);
            if (!isset(self::HEADER_FILES_TEMPLATES[$fileArr['extension']])){
                continue;
            }
            $elArr=self::HEADER_FILES_TEMPLATES[$fileArr['extension']];
            $srcAttr=self::HEADER_FILES_TEMPLATES[$fileArr['extension']]['srcAttr'];
            $elArr[$srcAttr]=$fileName;
            if (strpos($fileName,'jquery-ui')!==FALSE){
                $jQueryUIImport.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr).PHP_EOL;
            } else if (strpos($fileName,'jquery')!==FALSE){
                $jQueryImport.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr).PHP_EOL;
            } else {
                $arr['toReplace']['{{head}}'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr).PHP_EOL;
            }
        }
        $arr['toReplace']['{{head}}']=$jQueryImport.$jQueryUIImport.$arr['toReplace']['{{head}}'];
        // Leavelet plugin
        foreach(self::EXTERNAL_HEADER_ELEMENTS as $label=>$element){
            $arr['toReplace']['{{head}}'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
        }
        return $arr;
    }

    public function addHtmlPageBody(array $arr):array
    {
        $arr['toReplace']['{{bgMedia}}']='{{bgMedia}}';
        $arr['toReplace']['{{body}}'].='{{firstMenuBar}}'.PHP_EOL;
        $arr['toReplace']['{{body}}'].='{{secondMenuBar}}'.PHP_EOL;
        // main
        $arr['toReplace']['{{bottomArticle}}']='<article style="width:100vw;height:100px;border:none;"></article>';
        $main='<div id="top-filler"></div>'.PHP_EOL.'{{explorer}}'.PHP_EOL.'{{content}}'.PHP_EOL.'{{bottomArticle}}'.PHP_EOL;
        $arr['toReplace']['{{body}}'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'main','element-content'=>$main,'keep-element-content'=>TRUE]).PHP_EOL;
        // end of page        
        $arr['toReplace']['{{body}}'].=$this->oc['SourcePot\Datapool\Foundation\Logger']->getMyLogs().PHP_EOL;
        $arr['toReplace']['{{body}}'].='<div id="overlay" style="display:none;"></div><div id="overlay-image-container" style="display:none;"></div>'.PHP_EOL;
        $elArr=['tag'=>'script','element-content'=>'jQuery("article").hide();','keep-element-content'=>TRUE];
        $arr['toReplace']['{{body}}'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elArr).PHP_EOL;
        $arr=$this->oc['SourcePot\Datapool\Foundation\Menu']->menu($arr);
        return $arr;
    }
    
    public function finalizePage(array $arr):array
    {
        // replace page sceleton placeholders
        foreach($arr['toReplace'] as $needle=>$replacement){
            $arr['page html']=strtr($arr['page html'],[$needle=>$replacement]);
        }
        $arr['page html']=preg_replace('/{{[a-zA-Z]+}}/','',$arr['page html']);
        return $arr;
    }

}
?>