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

namespace Datapool\Foundation;

class Menu{
	
	private $arr;
	
	private $categories=array('Home'=>array('Emoji'=>'&#9750;','Label'=>'Home','Class'=>'Datapool\Components\Home'),
							  'Login'=>array('Emoji'=>'&#8688;','Label'=>'Login','Class'=>'Datapool\Components\Login'),
							  'Logout'=>array('Emoji'=>'&#10006;','Label'=>'Logout','Class'=>'Datapool\Components\Logout'),
							  'Admin'=>array('Emoji'=>'&#9874;','Label'=>'Admin','Class'=>'Datapool\AdminApps\Account'),
							  'Apps'=>array('Emoji'=>'&#10070;','Label'=>'Apps','Class'=>'Datapool\GenericApps\Multimedia'),
							  'Data'=>array('Emoji'=>'&#9783;','Label'=>'Data','Class'=>'Datapool\DataApps\Lists'),
							 );
							 
	private $available=array('Categories'=>array(),'Apps'=>array());
	
	private $requested=array('Category'=>'Home');
	
	public function __construct($arr){
		$this->arr=$arr;
	}
		
	public function init($arr){
		// get category from input
		$this->requested['Category']=filter_input(INPUT_GET,'category',FILTER_UNSAFE_RAW);
		if (!isset($this->categories[$this->requested['Category']])){
			$this->requested['Category']='Home';
		}
		if (isset($_SESSION[__CLASS__][__FUNCTION__]['selectedApp'][$this->requested['Category']])){
			$this->requested['App']=$_SESSION[__CLASS__][__FUNCTION__]['selectedApp'][$this->requested['Category']];
		} else {
			$this->requested['App']=$this->categories[$this->requested['Category']]['Class'];
		}
		// get app from form
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,'firstMenuBar');
		if (!empty($formData['val']['Class'])){
			$app=$formData['val']['Class'];
			if (method_exists($app,'run')){
				$this->requested['App']=$app;
				$_SESSION[__CLASS__][__FUNCTION__]['selectedApp'][$this->requested['Category']]=$this->requested['App'];
			}
		}
		// reset $_SESSION['page state']['app']
		$homeApp=$this->categories['Home']['Class'];
		$_SESSION['page state']['app']=$arr['registered methods']['run'][$homeApp];
		// get available and selected categories and apps
		if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
		foreach($arr['registered methods']['run'] as $classWithNamespace=>$menuDef){
			// check access rights
			if (empty($this->categories[$menuDef['Category']])){
				throw new \ErrorException('Function '.__FUNCTION__.': Menu category {'.$menuDef['Category'].'} set in {'.$classWithNamespace.'} has no definition in this class {'.__CLASS__.'}',0,E_ERROR,__FILE__,__LINE__);
			}
			$menuDef=$this->arr['Datapool\Foundation\Access']->replaceRightConstant($menuDef);
			if (empty($this->arr['Datapool\Foundation\Access']->access($menuDef,'Read',$user,FALSE))){continue;}
			// get categories
			$this->available['Categories'][$menuDef['Category']]=$this->categories[$menuDef['Category']];
			if (strcmp($menuDef['Category'],$this->requested['Category'])===0){
				$this->available['Categories'][$menuDef['Category']]['isSelected']=TRUE;
				// get apps
				$this->available['Apps'][$menuDef['Class']]=$menuDef;
				if (strcmp($menuDef['Class'],$this->requested['App'])===0){
					$this->available['Apps'][$menuDef['Class']]['isSelected']=TRUE;
					$_SESSION['page state']['app']=$menuDef;
				}
			}
		}
		return $arr;
	}
		
	public function menu(){
		$html='';
		$html.=$this->firstMenuBar();
		$html.=$this->secondMenuBar();
		return $html;
	}
	
	private function firstMenuBar(){
		$options=Array();
		$selected=FALSE;
		foreach($this->available['Apps'] as $class=>$def){
			$options[$class]=$def['Label'];
			if (!empty($def['isSelected'])){$selected=$class;}
		}
		$categoryEmoji=$this->categories[$this->requested['Category']]['Emoji'];
		$categoryTitle=$this->categories[$this->requested['Category']]['Label'];
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'a','element-content'=>$categoryEmoji,'href'=>'#','title'=>$categoryTitle,'class'=>'first-menu','keep-element-content'=>TRUE));
		if (!empty($options)){
			$html.=$this->arr['Datapool\Tools\HTMLbuilder']->select(array('options'=>$options,'selected'=>$selected,'key'=>array('Class'),'hasSelectBtn'=>TRUE,'title'=>'Select application','class'=>'menu','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
		$html.=$this->arr['Datapool\Foundation\Dictionary']->lngSelector(__CLASS__,__FUNCTION__);
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'first-menu'));
		return $html;
	}
	
	private function secondMenuBar(){
		$html='';
		foreach($this->available['Categories'] as $category=>$def){
			$def['Category']=$category;
			$html.=$this->def2div($def);	
		}		
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'ul','element-content'=>$html,'class'=>'menu','keep-element-content'=>TRUE));
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'second-menu','style'=>'height:0;'));
		return $html;
	}

	private function def2div($def){
		$href='?'.http_build_query(array('category'=>$def['Category']));
		if (empty($def['isSelected'])){
			$style='border-bottom:4px solid #400;';
		} else {
			$style='border-bottom:4px solid #a00;';
		}
		$html='';
		$html.=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$def['Emoji'],'class'=>'menu-item-emoji','keep-element-content'=>TRUE));
		$html.=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$def['Label'],'class'=>'menu-item-label','keep-element-content'=>TRUE));
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'a','element-content'=>$html,'href'=>$href,'class'=>'menu','keep-element-content'=>TRUE));
		$html=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'li','element-content'=>$html,'style'=>$style,'class'=>'menu','keep-element-content'=>TRUE));
		
		return $html;
	}
	
}
?>