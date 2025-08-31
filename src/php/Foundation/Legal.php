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

class Legal implements \SourcePot\Datapool\Interfaces\HomeApp{

    const COOKIE_LIFETIME=2592000;
    const COOKIES=[
        'Essential cookies'=>['disabled'=>TRUE,'initialSetting'=>TRUE,'description'=>'The session cookie is essential for the correct functioning of this website.<br/>A second cookie "dataprotection" is essential to store to store the your following settings:'],
        'OpenStreetMap'=>['disabled'=>FALSE,'initialSetting'=>TRUE,'description'=>'If permitted, the web application may send location or address data embedded in files to OpenStreetMap for processing, e.g. to display a location on a map or to specify an address in relation to a location.'],
        'Your location data'=>['disabled'=>FALSE,'initialSetting'=>FALSE,'description'=>'If this is permitted, the web application can collect, store and process location information provided by the web browser.'],
    ];

    private $oc;
    private $cookie=[];

    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];

    public function __construct($oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
        //
        $this->cookie=$this->refreshCookie();
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }

    /******************************************************************************************************************************************
    * COOKIE handling
    * 
    */
    
    public function permitted(string $key):bool|array
    {
        if (empty($key)){
            return $this->cookie;
        }
        return $this->cookie[$key]??FALSE;
    }

    private function getCookieIntialValues():array
    {
        foreach(self::COOKIES as $name=>$definition){
            $values[$name]=$definition['initialSetting'];
        }
        return $values;    
    }

    private function getCookie()
    {
        return json_decode($_COOKIE["dataprotection"]??'',TRUE)?:[];
    }
    
    private function refreshCookie():array
    {
        $values=json_decode($_COOKIE["dataprotection"]??'',TRUE)?:$this->getCookieIntialValues();
        return $this->setCookie($values);
    }
    
    private function setCookie(array $values=[]):array
    {
        $values=$values?:$this->getCookieIntialValues();
        $cookieValue=json_encode($values);
        $domain=($_SERVER['HTTP_HOST']=='localhost')?'':$_SERVER['HTTP_HOST'];
        if (setcookie('dataprotection',$cookieValue,time()+self::COOKIE_LIFETIME,'/',$domain,FALSE,TRUE)){
            return $values;
        } else {
            return [];
        }
    }

    public function cookieForm(array $arr):array
    {
        $values=$this->getCookie();
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (!empty($formData['cmd'])){
            $name=key($formData['cmd']);
            $values[$name]=boolval(key($formData['cmd'][$name]));
            $values=$this->setCookie($values);
        }
        // compile html
        $matrix=[];
        foreach($values as $name=>$value){
            $permitted=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($value);
            if (self::COOKIES[$name]['disabled']){
                $setBtn='';
            } else {
                $setBtn=['tag'=>'input','type'=>'submit','key'=>[$name,(boolval($value)?0:1)],'value'=>(boolval($value)?'Off':'On'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
            }
            $description=['tag'=>'p','element-content'=>self::COOKIES[$name]['description'],'keep-element-content'=>TRUE];
            $matrix[$name]=['Permitted'=>$permitted,'Set'=>$setBtn,'Description'=>$description];
        }
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'caption'=>'Permissions','keep-element-content'=>TRUE,'hideKeys'=>FALSE,'hideHeader'=>FALSE,'style'=>['border'=>'none']]);
        return $arr;
    }

    public function legalForm(string $name):string
    {
        $selector=['Source'=>$this->getEntryTable(),'Group'=>'legal','Folder'=>'Public','Name'=>$name];
        $selector['md']="\n";
        if ($name==='legal'){
            $selector['md'].="Dies ist eine private Webseite. Diese Webseite nutzt Cookies zur Session-Verwaltung und Speicherung der Datenschutzeinstellungen.\n";
            $selector['md'].="Die Webpage nutzt externe Kartendaten und Geo-Daten, die von OpenStreetMaps zur Verfügung gestellt werden. Nutzungsrechte der Kartendaten liegen bei OpenStreetMaps (<a href=\"https://www.openstreetmap.org/copyright\" target=\"_blank\" class=\"textlink\">The OpenStreetMap License</a>).\n";
            $selector['md'].="Sofern nicht abweichend angegeben, liegen die Nutzungsrechte zu Bildern und Texten beim Webseitenbetreiber. Ein ggf. vorhandenes Video auf der Startseite ist von Pressmaster und verfügbar auf www.pexels.com.\n";
            $selector['md'].="Die Haftung für verlinkte Inhalte ist im Umfang des gesetzlich Zulässigen ausgeschlossen.\n";
            $selector['md'].="\n";
            $selector['md'].="This is a private website. This website uses cookies for session management and to store privacy settings.\n";
            $selector['md'].="The webpage uses external map data and geo-data provided by OpenStreetMaps. The rights of use for the map data are held by OpenStreetMaps (see <a href=\"https://www.openstreetmap.org/copyright\" target=\"_blank\" class=\"textlink\">The OpenStreetMap License</a>).\n";
            $selector['md'].="Unless otherwise stated, the rights of use for images and texts are held by the website operator. Any videos on the home page are from Pressmaster and available at www.pexels.com.\n";
            $selector['md'].="Liability for linked content is excluded to the extent permitted by law.\n";
            $selector['md'].="\n";
            $selector['md'].="Esta es una página web privada. Esta página web utiliza cookies para gestionar sesiones y almacenar la configuración de privacidad.\n";
            $selector['md'].="La página web utiliza datos cartográficos y geográficos externos proporcionados por OpenStreetMaps. Los derechos de uso de los datos cartográficos pertenecen a OpenStreetMaps (<a href=\"https://www.openstreetmap.org/copyright\" target=\"_blank\" class=\"textlink\">The OpenStreetMap License</a>).\n";
            $selector['md'].="Salvo que se indique lo contrario, los derechos de uso de las imágenes y los textos pertenecen al operador del sitio web. Cualquier vídeo que pueda aparecer en la página de inicio es de Pressmaster y está disponible en www.pexels.com.\n";
            $selector['md'].="Se excluye la responsabilidad por los contenidos enlazados en la medida en que lo permita la ley.\n";
        } else if ($name==='contact'){
            $selector['md'].="[//]: # (Enter your text in Markdown fomat here)\n\n";
        } else if ($name==='logo'){
            $selector['md'].="<img src=\"./assets/logo.jpg\" title=\"logo.jpg\" style=\"width:300px;\"/>";
        } else {
            $selector['md'].="[//]: # (Enter your text in Markdown fomat here)\n\n";
        }
        return $this->oc['SourcePot\Datapool\Foundation\Container']->container($selector['Name'],'mdContainer',$selector,[],['style'=>[]]);
    }

    /******************************************************************************************************************************************
    * HomeApp Interface Implementation
    * 
    */
    
    public function getHomeAppWidget(string $name):array
    {
        // reset page setting
        $element=['element-content'=>'','style'=>[]];
        // cookie form
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Cookies and permissions','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Legal '.__FUNCTION__,'generic',['Source'=>$this->getEntryTable()],['method'=>'cookieForm','classWithNamespace'=>__CLASS__,],['style'=>['border'=>'none']]);
        // legal
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Legal','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->legalForm('legal');
        // contact
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Contact','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->legalForm('contact');
        // admin email contact form
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Admin email contact','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'img','src'=>'./assets/email.png','element-content'=>'','keep-element-content'=>TRUE,'style'=>['float'=>'left','clear'=>'both','padding'=>'0.5rem']]);
        // logo
        $element['element-content'].=$this->legalForm('logo');
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget provides a email creation form';
        return $info;
    }

}
?>