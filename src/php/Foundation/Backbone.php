<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Backbone{
	
	private $arr;
	
	private $elementAttrWhitelist=array('tag'=>TRUE,'input'=>TRUE,'type'=>TRUE,'class'=>TRUE,'style'=>TRUE,'id'=>TRUE,'name'=>TRUE,'title'=>TRUE,'function'=>TRUE,
										'method'=>TRUE,'enctype'=>TRUE,'xmlns'=>TRUE,'lang'=>TRUE,'href'=>TRUE,'src'=>TRUE,'value'=>TRUE,'width'=>TRUE,'height'=>TRUE,
										'rows'=>TRUE,'cols'=>TRUE,'target'=>TRUE,
										'min'=>TRUE,'max'=>TRUE,'for'=>TRUE,'multiple'=>TRUE,'disabled'=>TRUE,'selected'=>TRUE,'checked'=>TRUE,'controls'=>TRUE,'trigger-id'=>TRUE,
										'container-id'=>TRUE,'excontainer'=>TRUE,'container'=>TRUE,'cell'=>TRUE,'row'=>TRUE,'source'=>TRUE,'entry-id'=>TRUE,'source'=>TRUE,'index'=>TRUE,
										'js-status'=>TRUE,'default-min-width'=>TRUE,'default-min-height'=>TRUE,'default-max-width'=>TRUE,'default-max-height'=>TRUE,
										);
    private $needsNameAttr=array('input'=>TRUE,'select'=>TRUE,'textarea'=>TRUE,'button'=>TRUE,'fieldset'=>TRUE,'legend'=>TRUE,'output'=>TRUE,'optgroup'=>TRUE);
	
	private $settings=array('pageTitle'=>'Datapool',
							'pageTimeZone'=>'Europe/Berlin',
							'mainBackgroundImageFile'=>FALSE,
							'loginBackgroundImageFile'=>'main-login.jpg',
							'iconFile'=>'main.ico',
							'charset'=>'utf-8',
							'cssFiles'=>array('jquery-ui/jquery-ui.min.css','jquery-ui/jquery-ui.structure.min.css','jquery-ui/jquery-ui.theme.min.css'),
							'jsFiles'=>array('jquery/jquery-3.6.1.min.js','jquery-ui/jquery-ui.min.js','main.js'),
							'emailWebmaster'=>'admin@datapool.info');
	
	public function __construct($arr){
		$this->arr=$arr;
	}
	
	public function init($arr){
		$this->arr=$arr;
		// Initialize page settings
		$settings=array('Class'=>__CLASS__,'SettingName'=>__FUNCTION__);
		$settings['Content']=$this->settings;
		$settings=$this->arr['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($settings,TRUE);
		$this->settings=$settings['Content'];
		$this->settings['cssFiles'][]=$_SESSION['page state']['cssFile'];
		return $this->arr;
	}
	
	public function getSettings(){
		return $this->settings;
	}
	
	public function addHtmlPageBackbone($arr){
		$arr['page html'].="<!DOCTYPE html>".PHP_EOL;
		$arr['page html'].='<html xmlns="http://www.w3.org/1999/xhtml" lang="'.$_SESSION['page state']['lngCode'].'">'.PHP_EOL;
		$arr['page html'].="{{head}}".PHP_EOL;
		$arr['page html'].="{{body}}".PHP_EOL;
		$arr['page html'].="</html>";
		return $arr;
	}

	public function addHtmlPageHeader($arr){
		$icoFileInclude='';
		$cssFileInclude='';
		$jsFileInclude='';
		if (is_dir($GLOBALS['dirs']['media'])){
			// add ico file
			$icoFileInclude='';
			if (!empty($this->settings['iconFile'])){
				$href=$this->mediaFile2href($this->settings['iconFile']);
				if ($href){$icoFileInclude.='<link rel="shortcut icon" href="'.$href.'">'.PHP_EOL;}
			}
			// css-files
			$cssFileInclude='';
			if (!empty($this->settings['cssFiles'])){
				foreach($this->settings['cssFiles'] as $fileName){
					if (strpos($fileName,'://')===FALSE){
						$href=$this->mediaFile2href($fileName);
					} else {
						$href=$fileName;
					}
					if ($href){$cssFileInclude.='<link type="text/css" rel="stylesheet" href="'.$href.'" />'.PHP_EOL;}
				}
			}
			// js-files
			$jsFileInclude='';
			if (!empty($this->settings['jsFiles'])){
				foreach($this->settings['jsFiles'] as $fileName){
					if (strpos($fileName,'://')===FALSE){	
						$href=$this->mediaFile2href($fileName);
					} else {
						$href=$fileName;
					}
					if ($href){$jsFileInclude.='<script src="'.$href.'"></script>'.PHP_EOL;}
				}
			}
		} else {
			throw new \ErrorException('Function '.__FUNCTION__.': Media dir "'.$GLOBALS['dirs']['media'].'" is missing.',0,E_ERROR,__FILE__,__LINE__);
		}
		$head='';
		$head.='<head>'.PHP_EOL;
		$head.='<meta charset="'.$this->settings['charset'].'">'.PHP_EOL;
		$head.='<meta name="viewport" content="width=device-width, initial-scale=0.8">'.PHP_EOL;
		$head.='<title>'.$this->settings['pageTitle'].'</title>'.PHP_EOL;
		$head.=$icoFileInclude;
		$head.=$jsFileInclude;
		$head.=$cssFileInclude;
		$head.='</head>'.PHP_EOL;
		$arr['page html']=str_replace('{{head}}',$head,$arr['page html']);
		return $arr;
	}

	public function addHtmlPageBody($arr){
		$mainTagArr=array('tag'=>'main','element-content'=>"{{explorer}}".PHP_EOL."{{content}}".PHP_EOL,'keep-element-content'=>TRUE);
		if (strcmp($_SESSION['page state']['app']['Category'],'Login')===0){
			$imageFile=$this->settings['loginBackgroundImageFile'];
		} else {
			$imageFile=$this->settings['mainBackgroundImageFile'];
		}
		$src=$this->mediaFile2href($imageFile);
		if ($src){$mainTagArr['style']=array('background-size'=>'cover','background-image'=>'url("'.$src.'")');}
		$body=$this->arr['SourcePot\Datapool\Foundation\Menu']->menu().PHP_EOL;
		$body.='<div class="filler" id="top-filler"></div>'.PHP_EOL;
		$body.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($mainTagArr);
		$body.='<div class="filler" id="bottom-filler"></div>'.PHP_EOL;
		$body.=$this->arr['SourcePot\Datapool\Foundation\Toolbox']->getToolbox().PHP_EOL;
		$body.='<div id="overlay" style="display:none;"></div>'.PHP_EOL;
		$body.='<script>jQuery("article").hide();</script>'.PHP_EOL;
		$name=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getRandomString(30);;
		$body='<form name="'.$name.'" method="post" enctype="multipart/form-data">'.PHP_EOL.$body.'</form>'.PHP_EOL;
		$body='<body>'.PHP_EOL.$body.'</body>'.PHP_EOL;
		$arr['page html']=str_replace('{{body}}',$body,$arr['page html']);
		return $arr;
	}
	
	public function finalizePage($arr){
		// This method is called last
		if (!isset($arr['toReplace']['{{firstMenuBar}}'])){$arr['toReplace']['{{firstMenuBar}}']='';}
		if (!isset($arr['toReplace']['{{explorer}}'])){$arr['toReplace']['{{explorer}}']='';}
		if (!isset($arr['toReplace']['{{content}}'])){$arr['toReplace']['{{content}}']='Page content is missing...';}
		foreach($arr['toReplace'] as $needle=>$replacement){$arr['page html']=str_replace($needle,$replacement,$arr['page html']);}
		return $arr;
	}
	
	private function mediaFile2href($mediaFile,$throwException=FALSE){
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