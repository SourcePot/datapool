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
    
    private $oc;
        
    private $settings=['pageTitle'=>'Datapool',
                    'metaViewport'=>'width=device-width, initial-scale=1',
                    'metaDescription'=>'Light weight web application',
                    'metaRobots'=>'index',
                    'pageTimeZone'=>'Europe/Berlin',
                    'loginForm'=>0,
                    'iconFile'=>'main.ico',
                    'logoFile'=>'logo.jpg',
                    'homePageContent'=>'video',
                    'charset'=>'utf-8',
                    'cssFiles'=>['jquery-ui/jquery-ui.min.css','jquery-ui/jquery-ui.structure.min.css','jquery-ui/jquery-ui.theme.min.css','light.css','ep.css'],
                    'jsFiles'=>['jquery/jquery-3.6.1.min.js','jquery-ui/jquery-ui.min.js','main.js','container.js','plots.js','fileupload.js'],
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
        $arr['toReplace']=['{{head}}'=>'',
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
        $headerFiles=['iconFile'=>'<link rel="shortcut icon" href="{{iconFile}}">',
                    'cssFiles'=>'<link type="text/css" rel="stylesheet" href="{{cssFiles}}">',
                    'jsFiles'=>'<script src="{{jsFiles}}"></script>',
                     ];
        foreach($headerFiles as $settingsKey=>$template){
            if (!empty($headerFiles=$this->settings[$settingsKey])){
                if (!is_array($headerFiles)){$headerFiles=[$headerFiles];}
                foreach($headerFiles as $fileName){
                    $href=(mb_strpos($fileName,'://')===FALSE)?$this->mediaFile2href($fileName):$fileName;
                    if ($href){
                        $arr['toReplace']['{{head}}'].=str_replace('{{'.$settingsKey.'}}',$href,$template).PHP_EOL;
                    }
                }
            }
        }
        return $arr;
    }

    public function addHtmlPageBody(array $arr):array
    {
        $arr['toReplace']['{{bgMedia}}']='{{bgMedia}}';
        $arr['toReplace']['{{body}}'].='{{firstMenuBar}}'.PHP_EOL;
        $arr['toReplace']['{{body}}'].='{{secondMenuBar}}'.PHP_EOL;
        // main
        $main='<div id="top-filler"></div>'.PHP_EOL.'{{explorer}}'.PHP_EOL.'{{content}}'.PHP_EOL;
        $arr['toReplace']['{{body}}'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'main','element-content'=>$main,'keep-element-content'=>TRUE)).PHP_EOL;
        // end of page        
        $arr['toReplace']['{{body}}'].=$this->oc['SourcePot\Datapool\Foundation\Logger']->getMyLogs().PHP_EOL;
        $arr['toReplace']['{{body}}'].='<div id="overlay" style="display:none;"></div>'.PHP_EOL;
        $arr['toReplace']['{{body}}'].='<script>jQuery("article").hide();</script>'.PHP_EOL;
        $arr=$this->oc['SourcePot\Datapool\Foundation\Menu']->menu($arr);
        return $arr;
    }
    
    public function finalizePage(array $arr):array
    {
        foreach($arr['toReplace'] as $needle=>$replacement){
            $arr['page html']=strtr($arr['page html'],array($needle=>$replacement));
        }
        $arr['page html']=preg_replace('/{{[a-zA-Z]+}}/','',$arr['page html']);
        return $arr;
    }
    
    public function mediaFile2href(string $mediaFile):string|bool
    {
        $mediaFileAbs=$GLOBALS['dirs']['media'].$mediaFile;
        if (empty($mediaFile)){
            return FALSE;
        } else if (is_file($mediaFileAbs)){
            return $GLOBALS['relDirs']['media'].'/'.$mediaFile;
        } else {
            $this->oc['logger']->log('error','Function "{class} &rarr; {function}()" failed to open media file "{mediaFile}"',array('class'=>__CLASS__,'function'=>__FUNCTION__,'mediaFile'=>$mediaFile));         
            return FALSE;
        }
    }
}
?>