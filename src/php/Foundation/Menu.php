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

class Menu{
    
    private $oc;
    
    private $categories=array('Home'=>array('Emoji'=>'&#9750;','Label'=>'Home','Class'=>'SourcePot\Datapool\Components\Home','Name'=>'Home app'),
                              'Login'=>array('Emoji'=>'&#128274;','Label'=>'Login','Class'=>'SourcePot\Datapool\Components\Login','Name'=>'Login app'),
                              'Logout'=>array('Emoji'=>'&#10006;','Label'=>'Logout','Class'=>'SourcePot\Datapool\Components\Logout','Name'=>'Logout app'),
                              'Admin'=>array('Emoji'=>'&#128208;','Label'=>'Admin','Class'=>'SourcePot\Datapool\AdminApps\Account','Name'=>'Account app'),
                              'Apps'=>array('Emoji'=>'&#10070;','Label'=>'Apps','Class'=>'SourcePot\Datapool\GenericApps\Multimedia','Name'=>'Multimedia app'),
                              'Data'=>array('Emoji'=>'&#9783;','Label'=>'Data','Class'=>'SourcePot\Datapool\DataApps\Invoices','Name'=>'Invoice app'),
                             );
                             
    private $available=array('Categories'=>array(),'Apps'=>array());
    
    private $requested=array('Category'=>'Home');
    
    public function __construct($oc){
        $this->oc=$oc;
    }
        
    public function init($oc){
        $registeredRunMethods=$this->oc['SourcePot\Datapool\Root']->getRegisteredMethods('run');
        // get category from input
        $linkid=filter_input(INPUT_GET,'linkid',FILTER_SANITIZE_ENCODED);
        $linkid??FALSE;
        if (isset($_SESSION['page state']['linkids'][$linkid])){
            $linkinfo=$_SESSION['page state']['linkids'][$linkid];
            $this->requested['App']=$linkinfo['Class'];
            $this->requested['Category']=$linkinfo['Category'];
            $selector=array();
            $selector['Source']=(isset($linkinfo['Source']))?$linkinfo['Source']:FALSE;
            $selector['Group']=(isset($linkinfo['Group']))?$linkinfo['Group']:FALSE;
            $selector['Folder']=(isset($linkinfo['Folder']))?$linkinfo['Folder']:FALSE;
            $selector['Name']=(isset($linkinfo['Name']))?$linkinfo['Name']:FALSE;
            $selector['EntryId']=(isset($linkinfo['EntryId']))?$linkinfo['EntryId']:FALSE;
            $_SESSION['page state']['selected'][$this->requested['App']]=$selector;
            $_SESSION['page state']['linkids']=array();
        } else {
            $this->requested['Category']=filter_input(INPUT_GET,'category',FILTER_SANITIZE_ENCODED);
            if (!isset($this->categories[$this->requested['Category']])){
                $this->requested['Category']='Home';
            }
            if (isset($_SESSION[__CLASS__][__FUNCTION__]['selectedApp'][$this->requested['Category']])){
                $this->requested['App']=$_SESSION[__CLASS__][__FUNCTION__]['selectedApp'][$this->requested['Category']];
            } else {
                $this->requested['App']=$this->categories[$this->requested['Category']]['Class'];
            }
            // reset $_SESSION['page state']['app']
            $homeApp=$this->categories['Home']['Class'];
            $_SESSION['page state']['app']=$registeredRunMethods[$homeApp];
        }
        // get app from form
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'firstMenuBar',FALSE);
        if (!empty($formData['val']['Class'])){
            $app=$formData['val']['Class'];
            if (method_exists($app,'run')){
                $this->requested['App']=$app;
                $_SESSION[__CLASS__][__FUNCTION__]['selectedApp'][$this->requested['Category']]=$this->requested['App'];
            }
        }
        // get available and selected categories and apps
        if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
        foreach($registeredRunMethods as $classWithNamespace=>$menuDef){
            // check access rights
            if (empty($this->categories[$menuDef['Category']])){
                throw new \ErrorException('Function '.__FUNCTION__.': Menu category {'.$menuDef['Category'].'} set in {'.$classWithNamespace.'} has no definition in this class {'.__CLASS__.'}',0,E_ERROR,__FILE__,__LINE__);
            }
            $menuDef=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($menuDef);
            if (empty($this->oc['SourcePot\Datapool\Foundation\Access']->access($menuDef,'Read',$user,FALSE))){continue;}
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
    }

    public function getCategories($optionsOnly=FALSE){
        if ($optionsOnly){
            $options=array();
            foreach($this->categories as $key=>$category){
                $options[$key]=$category['Label'];
            }
            return $options;
        } else {
            return $this->categories;
        }
    }
    
    public function class2category($class){
        foreach($this->categories as $key=>$category){
            if (strpos($category['Class'],$class)===FALSE){continue;}
            $category['Category']=$key;
            return $category;
        }
        return FALSE;
    }

    public function menu($arr){
        $arr=$this->firstMenuBar($arr);
        $arr=$this->secondMenuBar($arr);
        return $arr;
    }
    
    private function firstMenuBar($arr){
        $options=array();
        $selected=FALSE;
        foreach($this->available['Apps'] as $class=>$def){
            $options[$class]=$def['Label'];
            if (!empty($def['isSelected'])){$selected=$class;}
        }
        $categoryEmoji=$this->categories[$this->requested['Category']]['Emoji'];
        $categoryTitle=$this->categories[$this->requested['Category']]['Label'];
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','element-content'=>$categoryEmoji,'href'=>'#','title'=>$categoryTitle,'class'=>'first-menu','keep-element-content'=>TRUE));
        if (!empty($options)){
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(array('options'=>$options,'selected'=>$selected,'key'=>array('Class'),'hasSelectBtn'=>TRUE,'title'=>'Select application','class'=>'menu','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
        }
        $html.='{{firstMenuBarExt}}';
        $html.=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lngSelector(__CLASS__,__FUNCTION__);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'first-menu','id'=>'nav'));
        $arr['toReplace']['{{firstMenuBar}}']=$html;
        return $arr;
    }
    
    private function secondMenuBar($arr){
        $html='';
        foreach($this->available['Categories'] as $category=>$def){
            $def['Category']=$category;
            $html.=$this->def2div($def);    
        }        
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'ul','element-content'=>$html,'class'=>'menu','keep-element-content'=>TRUE));
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'second-menu','style'=>'height:0;'));
        $arr['toReplace']['{{secondMenuBar}}']=$html;
        return $arr;
    }

    private function def2div($def){
        $href='index.php?'.http_build_query(array('category'=>$def['Category']));
        $style='';
        if (!empty($def['isSelected'])){$style='border-bottom:4px solid #a00;';}
        $def['Label']=$arr['element-content']=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng($def['Label']);
        $html='';
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$def['Emoji'],'class'=>'menu-item-emoji','keep-element-content'=>TRUE));
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$def['Label'],'class'=>'menu-item-label','keep-element-content'=>TRUE));
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','element-content'=>$html,'href'=>$href,'class'=>'menu','keep-element-content'=>TRUE));
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'li','element-content'=>$html,'style'=>$style,'class'=>'menu','keep-element-content'=>TRUE));
        
        return $html;
    }
    
}
?>