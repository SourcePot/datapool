<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Cookies;

class Cookies implements \SourcePot\Datapool\Interfaces\App{
    
    private const APP_ACCESS='ALL_R';
    
    private $oc;
    
    const COOKIE_LIFETIME=2592000;

    const PERMISSIONS_COOKIE=[
        'Essential cookies'=>['disabled'=>TRUE,'initialSetting'=>TRUE,'description'=>'The "session cookie" and the "dataprotection cookie" are essential for the correct functioning of this web page. The "session cookie" stores your login status based on a session id. The "dataprotection cookie" stores your settings.'],
        'OpenStreetMap'=>['disabled'=>FALSE,'initialSetting'=>TRUE,'description'=>'If you permit this, this web page may send location or address data embedded in files or entered by you to OpenStreetMap for processing, e.g. to display a location on a map or to specify an address in relation to a location.'],
        'Your location data'=>['disabled'=>FALSE,'initialSetting'=>FALSE,'description'=>'If you give permission, the website will process and store the location information provided by your web browser.'],
    ];

    private $permissionsCookie=[];
    private $settingsCookie=[];
  
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
        $this->permissionsCookie=$this->refreshPermissionsCookie();
        $this->settingsCookie=$this->refreshSettingsCookie();
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

    
    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return ['Category'=>'Cookies','Emoji'=>'&#9737;','Label'=>'Cookies','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            $arr['toReplace']['{{content}}']=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Cookies form','generic',['Source'=>$this->getEntryTable()],['method'=>'permissionsCookieForm','classWithNamespace'=>__CLASS__,],['style'=>['border'=>'none']]);
            $arr['toReplace']['{{content}}'].=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Settings form','generic',['Source'=>$this->getEntryTable()],['method'=>'settingsCookieForm','classWithNamespace'=>__CLASS__,],['style'=>['border'=>'none']]);
            return $arr;
        }
    }

    /*  DATAPROTECTION COOKIE - PERMISSIONS
     * 
     */

    public function permitted(string $key):bool|array
    {
        if (empty($key)){
            return $this->permissionsCookie;
        }
        return $this->permissionsCookie[$key]??FALSE;
    }

    private function getPermissionsCookieInitialValues():array
    {
        $values=[];
        foreach(self::PERMISSIONS_COOKIE as $name=>$definition){
            $values[$name]=$definition['initialSetting'];
        }
        return $values;    
    }

    private function getPermissionsCookie()
    {
        return json_decode($_COOKIE["dataprotection"]??'',TRUE)?:[];
    }
    
    private function refreshPermissionsCookie():array
    {
        $values=json_decode($_COOKIE["dataprotection"]??'',TRUE)?:$this->getPermissionsCookieInitialValues();
        return $this->setPermissionsCookie($values);
    }
    
    private function setPermissionsCookie(array $values=[]):array
    {
        $values=$values?:$this->getPermissionsCookieInitialValues();
        $cookieValue=json_encode($values);
        $cookieOptions=[
            'expires'=>time()+self::COOKIE_LIFETIME, 
            'path'=>'/', 
            'domain'=>'',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly'=>TRUE,
            'samesite'=>'Strict', // None || Lax  || Strict
        ];
        if (setcookie('dataprotection',$cookieValue,$cookieOptions)){
            return $values;
        } else {
            return [];
        }
    }

    public function permissionsCookieForm(array $arr):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        $values=$this->getPermissionsCookie();
        // process form
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (!empty($formData['cmd'])){
            $name=key($formData['cmd']);
            $values[$name]=boolval(key($formData['cmd'][$name]));
            if (headers_sent()){
                $this->oc['logger']->log('notice','{class}&rarr;{function}() failed to set cookie. Header was sent already',$context);
            } else {
                $values=$this->setPermissionsCookie($values);
                if (empty($values)){
                    $this->oc['logger']->log('notice','{class}&rarr;{function}() permissions cookie update failed.',$context);
                } else {
                    $this->oc['logger']->log('info','{class}&rarr;{function}() permissions cookie updated.',$context);
                }
            }
        }
        // compile html
        $matrix=[];
        foreach(self::PERMISSIONS_COOKIE as $name=>$definition){
            $value=$values[$name]??$definition['initialSetting'];
            $btn=['tag'=>'input','type'=>'submit','key'=>[$name,(boolval($value)?0:1)],'value'=>(boolval($value)?'TRUE':'FALSE'),'style'=>['line-height'=>'2rem'],'class'=>(boolval($value)?'status-on':'status-off'),'excontainer'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'disabled'=>self::PERMISSIONS_COOKIE[$name]['disabled']];
            $description=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'p','element-content'=>$name,'style'=>['clear'=>'both','font-weight'=>'bold','padding-top'=>'1rem ']]);
            $description.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'p','element-content'=>self::PERMISSIONS_COOKIE[$name]['description'],'keep-element-content'=>TRUE,'style'=>['padding-bottom'=>'1rem ']]);
            $matrix[$name]=['Permitted'=>$btn,'Description'=>$description];
        }
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Cookies and permissions','id'=>'cookies','keep-element-content'=>TRUE]);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'hideKeys'=>TRUE,'hideHeader'=>FALSE,'style'=>['border'=>'none']]);
        return $arr;
    }

    /*  SETTINGS COOKIE
     * 
     */

    private function setSettingsCookieValue(string $cookieValue):bool
    {
        $cookieOptions=[
            'expires'=>time()+self::COOKIE_LIFETIME, 
            'path'=>'/', 
            'domain'=>'',
            'secure'=>isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly'=>TRUE,
            'samesite'=>'Strict', // None || Lax  || Strict
        ];
        return setcookie('settings',$cookieValue,$cookieOptions);
    }

    private function refreshSettingsCookie():array
    {
        $cookieValue=$_COOKIE["settings"]??'[]';
        $this->setSettingsCookieValue($cookieValue);
        return json_decode($cookieValue,TRUE);
    }

    public function setSettingsCookie(string $key,$value):array
    {
        $settings=json_decode(($_COOKIE["settings"]??''),TRUE);
        $settings[$key]=$value;
        $cookieValue=json_encode($settings);
        $this->setSettingsCookieValue($cookieValue);
        $this->settingsCookie=$settings;
        return $settings;
    }

    public function getSettingsCookieValue(string|bool $key)
    {
        if ($key===FALSE){
            return $this->settingsCookie;
        } else {
            return $this->settingsCookie[$key]??NULL;
        }
    }

    public function settingsCookieForm(array $arr):array
    {
        $settings=$this->getSettingsCookieValue(FALSE);
        // compile html
        $matrix=[];
        foreach($settings as $name=>$value){
            $matrix['<b>'.$name.'</b>']=['Value'=>$value];
        }
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Your settings-cookie','id'=>'settings-cookies','keep-element-content'=>TRUE]);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'hideKeys'=>FALSE,'hideHeader'=>FALSE,'style'=>['border'=>'none']]);
        return $arr;
    }

}
?>