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
    
    public const CATEGORIES=[
        'Home'=>['Category'=>'Home','Emoji'=>'&#9750;','Label'=>'Home','Class'=>'SourcePot\Datapool\Components\Home','Name'=>'Home app'],
        'Cookies'=>['Category'=>'Cookies','Emoji'=>'&#9737;','Label'=>'Cookies','Class'=>'SourcePot\Datapool\Cookies\Cookies','Name'=>'Cookies app'],
        'Login'=>['Category'=>'Login','Emoji'=>'&#8614;','Label'=>'Login','Class'=>'SourcePot\Datapool\Components\Login','Name'=>'Login app'],
        'Logout'=>['Category'=>'Logout','Emoji'=>'&#10006;','Label'=>'Logout','Class'=>'SourcePot\Datapool\Components\Logout','Name'=>'Logout app'],
        'Admin'=>['Category'=>'Admin','Emoji'=>'&#9786;','Label'=>'Admin','Class'=>'SourcePot\Datapool\AdminApps\Account','Name'=>'Account app'],
        'Calendar'=>['Category'=>'Calendar','Emoji'=>'&#9992;','Label'=>'Calendar','Class'=>'SourcePot\Datapool\Calendar\Calendar','Name'=>'Calendar app'],
        'Forum'=>['Category'=>'Forum','Emoji'=>'&#9993;','Label'=>'Forum','Class'=>'SourcePot\Datapool\Forum\Forum','Name'=>'Forum app'],
        'Apps'=>['Category'=>'Apps','Emoji'=>'&#10070;','Label'=>'Apps','Class'=>'SourcePot\Datapool\GenericApps\Multimedia','Name'=>'Multimedia app'],
        'Data'=>['Category'=>'Data','Emoji'=>'&#9783;','Label'=>'Data','Class'=>'SourcePot\Datapool\DataApps\Misc','Name'=>'Misc app'],
    ];
                             
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
        // get Category
        $requestedCategory=$_SESSION['page state']['selectedCategory']??self::CATEGORIES['Home']['Category'];
        $requestedCategory=filter_input(INPUT_GET,'category',FILTER_SANITIZE_ENCODED);
        if (isset(self::CATEGORIES[$requestedCategory])){
            $requestedCategory=$requestedCategory;
            $requestedAppClass=$_SESSION['page state']['selectedApp'][$requestedCategory]['Class']??self::CATEGORIES[$requestedCategory]['Class'];
        } else {
            $requestedCategory='Home';
        }
        // get app from form
        $requestedAppClass=$_SESSION['page state']['selectedApp'][$requestedCategory]['Class']??self::CATEGORIES[$requestedCategory]['Class'];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'firstMenuBar',FALSE);
        if (!empty($formData['val']['Class'])){
            if (method_exists($formData['val']['Class'],'run')){
                $requestedAppClass=$formData['val']['Class'];
            }
        }
        if (!empty($requestedAppClass)){
            $this->selectedApp($requestedAppClass);
        }
    }

    public function selectedApp(string $selectAppClass=''):array
    {
        if (empty($selectAppClass)){
            // get currently selected app class and Category
            $selectedCategory=$_SESSION['page state']['selectedCategory']??self::CATEGORIES['Home']['Category'];
            $selectAppClass=$_SESSION['page state']['selectedApp'][$selectedCategory]['Class']??self::CATEGORIES['Home']['Class'];
        }
        // has user access rights for requested app
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $selectAppClass=(empty($this->oc[$selectAppClass]))?self::CATEGORIES['Home']['Class']:$selectAppClass;
        $appDef=$this->oc[$selectAppClass]->run(TRUE);
        $appDef=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($appDef);
        if (empty($this->oc['SourcePot\Datapool\Foundation\Access']->access($appDef,'Read',$user,FALSE))){
            // access denied -> Hone app
            $_SESSION['page state']['selectedCategory']=self::CATEGORIES['Home']['Category'];
            $_SESSION['page state']['selectedApp'][self::CATEGORIES['Home']['Category']]=$this->oc[self::CATEGORIES['Home']['Class']]->run(TRUE);
        } else {
            // access granted
            $_SESSION['page state']['selectedCategory']=$appDef['Category'];
            $_SESSION['page state']['selectedApp'][$appDef['Category']]=$appDef;
        }
        return $_SESSION['page state']['selectedApp'][$_SESSION['page state']['selectedCategory']];
    }
    
    public function class2category(string $class):array|bool
    {
        foreach(self::CATEGORIES as $key=>$category){
            if (mb_strpos($category['Class'],$class)===FALSE){continue;}
            $category['Category']=$key;
            return $category;
        }
        return FALSE;
    }

    public function menu(array $arr):array
    {
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        // get available and selected categories and apps
        $implementedApps=$this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\App');
        foreach($implementedApps as $classWithNamespace){
            $menuDef=$this->oc[$classWithNamespace]->run(TRUE);
            $menuDef=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($menuDef);
            if (empty($this->oc['SourcePot\Datapool\Foundation\Access']->access($menuDef,'Read',$user,FALSE))){
                // skip app if access rights are not sufficient
            } else {
                $availableApps[$classWithNamespace]=$menuDef;
                $availableCategories[$menuDef['Category']]=self::CATEGORIES[$menuDef['Category']];
            }
        }
        $arr=$this->firstMenuBar($arr,$availableApps??[]);
        $arr=$this->secondMenuBar($arr,$availableCategories??[]);
        return $arr;
    }
    
    private function firstMenuBar(array $arr,array $availableApps):array
    {
        $options=[];
        $lngSelector=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lngSelector(__CLASS__,__FUNCTION__);
        $selectedApp=$this->selectedApp();
        // get apps selector
        foreach($availableApps as $class=>$appDef){
            if ($selectedApp['Category']===$appDef['Category']){
                $options[$class]=$appDef['Label'];
            }
        }
        $categoryDef=self::CATEGORIES[$selectedApp['Category']];
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'a','element-content'=>$categoryDef['Emoji'],'href'=>'#','title'=>$categoryDef['Label'],'class'=>'first-menu','keep-element-content'=>TRUE]);
        if (!empty($options)){
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['options'=>$options,'selected'=>$selectedApp['Class'],'key'=>['Class'],'hasSelectBtn'=>TRUE,'title'=>'Select application','class'=>'menu','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
        }
        // compile html
        // $html.=$lngHtml;
        $html.='{{firstMenuBarExt}}';
        $html.=$lngSelector;
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'first-menu','id'=>'nav']);
        $arr['toReplace']['{{firstMenuBar}}']=$html;
        return $arr;
    }
    
    private function secondMenuBar(array $arr, array $availableCategories):array
    {
        $html='';
        foreach($availableCategories as $category=>$categoryDef){
            if (empty($categoryDef)){
                $this->oc['logger']->log('error','Definition for menu item category "{category}" missing',['category'=>$category]);    
                continue;
            }
            $html.=$this->def2div($categoryDef);
        }
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'ul','element-content'=>$html,'class'=>'menu','keep-element-content'=>TRUE]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'class'=>'second-menu','style'=>'height:0;']);
        $arr['toReplace']['{{secondMenuBar}}']=$html;
        return $arr;
    }

    private function def2div(array $categoryDef):string
    {
        $selectedApp=$this->selectedApp();
        $href='index.php?'.http_build_query(['category'=>$categoryDef['Category']]);
        $style=($selectedApp['Category']===$categoryDef['Category'])?'border-bottom:3px solid #a00;':'';
        $def['Label']=$arr['element-content']=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng($categoryDef['Label']);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$categoryDef['Emoji'],'class'=>'menu-item-emoji','keep-element-content'=>TRUE]);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$categoryDef['Label'],'class'=>'menu-item-label','keep-element-content'=>TRUE]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'a','element-content'=>$html,'href'=>$href,'class'=>'menu','keep-element-content'=>TRUE]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'li','element-content'=>$html,'style'=>$style,'class'=>'menu','keep-element-content'=>TRUE]);
        return $html;
    }
}
?>