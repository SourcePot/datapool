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
		
	private $settings=array('pageTitle'=>'Datapool',
							'pageTimeZone'=>'Europe/Berlin',
							'mainBackgroundImageFile'=>FALSE,
							'loginBackgroundImageFile'=>'main-login.jpg',
							'iconFile'=>'main.ico',
							'logoFile'=>'logo.svg',
							'charset'=>'utf-8',
							'cssFiles'=>array('jquery-ui/jquery-ui.min.css','jquery-ui/jquery-ui.structure.min.css','jquery-ui/jquery-ui.theme.min.css','entry-presentation.css'),
							'jsFiles'=>array('jquery/jquery-3.6.1.min.js','jquery-ui/jquery-ui.min.js','main.js'),
							'emailWebmaster'=>'admin@datapool.info');
	
	public function __construct($oc){
		$this->oc=$oc;
	}
	
	public function init($oc){
		$this->oc=$oc;
		// Initialize page settings
		$settings=array('Class'=>__CLASS__,'EntryId'=>__FUNCTION__);
		$settings['Content']=$this->settings;
		$settings=$oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($settings,TRUE);
		$this->settings=$settings['Content'];
		$this->settings['cssFiles'][]=$_SESSION['page state']['cssFile'];
		return $this->oc;
	}
	
	public function getSettings(){
		return $this->settings;
	}
	
	public function addHtmlPageBackbone($arr){
		$arr['formName']=md5($this->settings['pageTitle']);
		$arr['toReplace']=array('{{head}}'=>'',
								'{{body}}'=>'',
								'{{content}}'=>'Page content is missing...',
								'{{firstMenuBar}}'=>'',
								'{{firstMenuBarExt}}'=>'',
								'{{secondMenuBar}}'=>'',
								'{{explorer}}'=>'',
								);
		$arr['page html']='';
		$arr['page html'].="<!DOCTYPE html>".PHP_EOL;
		$arr['page html'].='<html xmlns="http://www.w3.org/1999/xhtml" lang="'.$_SESSION['page state']['lngCode'].'">'.PHP_EOL;
		// page header
		$arr['page html'].='<head>'.PHP_EOL;
		$arr['page html'].='<meta charset="'.$this->settings['charset'].'">'.PHP_EOL;
		$arr['page html'].='<meta name="viewport" content="width=device-width, initial-scale=0.8">'.PHP_EOL;
		$arr['page html'].='<title>'.$this->settings['pageTitle'].'</title>'.PHP_EOL;
		$arr['page html'].='{{head}}'.PHP_EOL;
		$arr['page html'].='</head>'.PHP_EOL;
		// page body
		$arr['page html'].='<body>'.PHP_EOL;
		$arr['page html'].='<form name="'.$arr['formName'].'" method="post" enctype="multipart/form-data">'.PHP_EOL;
		$arr['page html'].='{{body}}'.PHP_EOL;
		$arr['page html'].='</form>'.PHP_EOL;
		$arr['page html'].='</body>'.PHP_EOL;
		$arr['page html'].="</html>";
		return $arr;
	}
	
	public function addHtmlPageHeader($arr){
		$headerFiles=array('iconFile'=>'<link rel="shortcut icon" href="{{iconFile}}">',
						   'cssFiles'=>'<link type="text/css" rel="stylesheet" href="{{cssFiles}}">',
						   'jsFiles'=>'<script src="{{jsFiles}}"></script>',
						   );
		foreach($headerFiles as $settingsKey=>$template){
			if (!empty($headerFiles=$this->settings[$settingsKey])){
				if (!is_array($headerFiles)){$headerFiles=array($headerFiles);}
				foreach($headerFiles as $fileName){
					$href=(strpos($fileName,'://')===FALSE)?$this->mediaFile2href($fileName):$fileName;
					if ($href){
						$arr['toReplace']['{{head}}'].=str_replace('{{'.$settingsKey.'}}',$href,$template).PHP_EOL;
					}
				}
			}
		}
		return $arr;
	}

	public function addHtmlPageBody($arr){
		$imageFile=(strcmp($_SESSION['page state']['app']['Category'],'Login')===0)?$this->settings['loginBackgroundImageFile']:$this->settings['mainBackgroundImageFile'];
		if ($src=$this->mediaFile2href($imageFile)){
			$mainStyle=array('background-size'=>'cover','background-image'=>'url('.$src.')');
		} else {
			$mainStyle=array();
		}
		$arr['toReplace']['{{body}}'].='{{firstMenuBar}}'.PHP_EOL;
		$arr['toReplace']['{{body}}'].='{{secondMenuBar}}'.PHP_EOL;
		$arr['toReplace']['{{body}}'].='<div class="filler" id="top-filler"></div>'.PHP_EOL;
		// main
		$arr['toReplace']['{{body}}'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'main','style'=>$mainStyle)).PHP_EOL;
		$arr['toReplace']['{{body}}'].='{{explorer}}'.PHP_EOL;
		$arr['toReplace']['{{body}}'].='{{content}}'.PHP_EOL;
		$arr['toReplace']['{{body}}'].='</main>'.PHP_EOL;
		// en of page		
		$arr['toReplace']['{{body}}'].='<div class="filler" id="bottom-filler"></div>'.PHP_EOL;
		$arr['toReplace']['{{body}}'].='{{toolbox}}'.PHP_EOL;
		$arr['toReplace']['{{body}}'].='<div id="overlay" style="display:none;"></div>'.PHP_EOL;
		$arr['toReplace']['{{body}}'].='<script>jQuery("article").hide();</script>'.PHP_EOL;
		$arr=$this->oc['SourcePot\Datapool\Foundation\Menu']->menu($arr);
		$arr=$this->oc['SourcePot\Datapool\Foundation\Toolbox']->getToolbox($arr);
		return $arr;
	}
	
	public function finalizePage($arr){
		foreach($arr['toReplace'] as $needle=>$replacement){
			$arr['page html']=strtr($arr['page html'],array($needle=>$replacement));
		}
		return $arr;
	}
	
	public function mediaFile2href($mediaFile,$throwException=FALSE){
		$mediaFileAbs=$GLOBALS['dirs']['media'].'/'.$mediaFile;
		if (is_file($mediaFileAbs)){
			return $GLOBALS['relDirs']['media'].$mediaFile;
		} else {
			if ($throwException){
				throw new \ErrorException('Function '.__FUNCTION__.': Could not open file '.$mediaFileAbs,0,E_ERROR,__FILE__,__LINE__);
			}
			return FALSE;
		}
	}
}
?>