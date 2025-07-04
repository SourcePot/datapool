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
    
    public $categories=[
        'Home'=>['Emoji'=>'&#9750;','Label'=>'Home','Class'=>'SourcePot\Datapool\Components\Home','Name'=>'Home app'],
        'Login'=>['Emoji'=>'&#8614;','Label'=>'Login','Class'=>'SourcePot\Datapool\Components\Login','Name'=>'Login app'],
        'Logout'=>['Emoji'=>'&#10006;','Label'=>'Logout','Class'=>'SourcePot\Datapool\Components\Logout','Name'=>'Logout app'],
        'Admin'=>['Emoji'=>'&#9786;','Label'=>'Admin','Class'=>'SourcePot\Datapool\AdminApps\Account','Name'=>'Account app'],
        'Calendar'=>['Emoji'=>'&#9992;','Label'=>'Calendar','Class'=>'SourcePot\Datapool\Calendar\Calendar','Name'=>'Calendar app'],
        'Forum'=>['Emoji'=>'&#9993;','Label'=>'Forum','Class'=>'SourcePot\Datapool\Forum\Forum','Name'=>'Forum app'],
        'Apps'=>['Emoji'=>'&#10070;','Label'=>'Apps','Class'=>'SourcePot\Datapool\GenericApps\Multimedia','Name'=>'Multimedia app'],
        'Data'=>['Emoji'=>'&#9783;','Label'=>'Data','Class'=>'SourcePot\Datapool\DataApps\Misc','Name'=>'Misc app'],
        ];
                             
    private $available=['Categories'=>[],'Apps'=>[]];
    
    private $requested=['Category'=>'Home'];
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }
    
    public function init()
    {
        $implementedApps=$this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\App');
        // get category from input
        $this->requested['Category']=filter_input(INPUT_GET,'category',FILTER_SANITIZE_ENCODED);
        if (!isset($this->categories[$this->requested['Category']])){
            $this->requested['Category']='Home';
        }
        if (isset($_SESSION[__CLASS__][__FUNCTION__]['selectedApp'][$this->requested['Category']])){
            $this->requested['App']=$_SESSION[__CLASS__][__FUNCTION__]['selectedApp'][$this->requested['Category']];
        } else {
            $this->requested['App']=$this->categories[$this->requested['Category']]['Class'];
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
        $_SESSION['page state']['app']=$implementedApps[$this->categories['Home']['Class']];    // fallback
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        foreach($implementedApps as $classWithNamespace){
            $menuDef=$this->oc[$classWithNamespace]->run(TRUE);
            // check access rights
            if (empty($this->categories[$menuDef['Category']])){
                throw new \ErrorException('Function '.__FUNCTION__.': Menu category {'.$menuDef['Category'].'} set in {'.$classWithNamespace.'} has no definition in this class {'.__CLASS__.'}',0,E_ERROR,__FILE__,__LINE__);
            }
            $menuDef=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($menuDef);
            if (empty($this->oc['SourcePot\Datapool\Foundation\Access']->access($menuDef,'Read',$user,FALSE))){
                // skip app if access rights are not sufficient
                continue;
            }
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

    public function getCategories(bool $optionsOnly=FALSE):array
    {
        if ($optionsOnly){
            $options=[];
            foreach($this->categories as $key=>$category){
                $options[$key]=$category['Label'];
            }
            return $options;
        } else {
            return $this->categories;
        }
    }
    
    public function class2category(string $class):array|bool
    {
        foreach($this->categories as $key=>$category){
            if (mb_strpos($category['Class'],$class)===FALSE){continue;}
            $category['Category']=$key;
            return $category;
        }
        return FALSE;
    }

    public function menu(array $arr):array
    {
        $arr=$this->firstMenuBar($arr);
        $arr=$this->secondMenuBar($arr);
        return $arr;
    }
    
    private function firstMenuBar(array $arr):array
    {
        $options=[];
        $selected=FALSE;
        $lngSelector=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lngSelector(__CLASS__,__FUNCTION__);
        // get apps selector
        foreach($this->available['Apps'] as $class=>$def){
            $options[$class]=$def['Label'];
            if (!empty($def['isSelected'])){$selected=$class;}
        }
        $categoryEmoji=$this->categories[$this->requested['Category']]['Emoji'];
        $categoryTitle=$this->categories[$this->requested['Category']]['Label'];
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'a','element-content'=>$categoryEmoji,'href'=>'#','title'=>$categoryTitle,'class'=>'first-menu','keep-element-content'=>TRUE]);
        if (!empty($options)){
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['options'=>$options,'selected'=>$selected,'key'=>['Class'],'hasSelectBtn'=>TRUE,'title'=>'Select application','class'=>'menu','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
        }
        // compile html
        // $html.=$lngHtml;
        $html.='{{firstMenuBarExt}}';
        $html.=$lngSelector;
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'first-menu','id'=>'nav']);
        $arr['toReplace']['{{firstMenuBar}}']=$html;
        return $arr;
    }
    
    private function secondMenuBar(array $arr):array
    {
        $html='';
        foreach($this->available['Categories'] as $category=>$def){
            $def['Category']=$category;
            $html.=$this->def2div($def);    
        }        
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'ul','element-content'=>$html,'class'=>'menu','keep-element-content'=>TRUE]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'second-menu','style'=>'height:0;']);
        $arr['toReplace']['{{secondMenuBar}}']=$html;
        return $arr;
    }

    private function def2div(array $def):string
    {
        $href='index.php?'.http_build_query(['category'=>$def['Category']]);
        $style='';
        if (!empty($def['isSelected'])){$style='border-bottom:3px solid #a00;';}
        $def['Label']=$arr['element-content']=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng($def['Label']);
        $html='';
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$def['Emoji'],'class'=>'menu-item-emoji','keep-element-content'=>TRUE]);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$def['Label'],'class'=>'menu-item-label','keep-element-content'=>TRUE]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'a','element-content'=>$html,'href'=>$href,'class'=>'menu','keep-element-content'=>TRUE]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'li','element-content'=>$html,'style'=>$style,'class'=>'menu','keep-element-content'=>TRUE]);
        
        return $html;
    }
}
?>