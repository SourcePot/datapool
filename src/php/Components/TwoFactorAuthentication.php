<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Components;

class TwoFactorAuthentication implements \SourcePot\Datapool\Interfaces\App{

    private const APP_ACCESS='PUBLIC_R';
    private const MAX_LOGIN_AGE=60;
    private const TRANSMITTER_BLACKLIST=[
        'SourcePot\Datapool\Tools\Files'=>'Files',
    ];

    private $oc;

    public function __construct($oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        // switch to Login page if there is no valkid login
        if (!$this->validUserLogin()){
            $_SESSION['page state']['selectedApp']['Login']['Class']='SourcePot\Datapool\Components\Login';
        }
    }

    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return ['Category'=>'Login','Emoji'=>'&#8614;','Label'=>'2FA','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            $bgStyle=['background-image'=>'url(\''.$GLOBALS['relDirs']['assets'].'/login.jpg\')'];
            $arr['toReplace']['{{bottomArticle}}']='';
            $arr['toReplace']['{{bgMedia}}']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','class'=>'bg-media','style'=>$bgStyle,'element-content'=>' ']).PHP_EOL;
            $arr['toReplace']['{{content}}']=$this->getTwoFactorAuthenticationHtml();
            return $arr;
        }
    }

    public function isTwoFactorAuthenticationRequired($user):bool
    {
        $twoFactorAuthenticationBitMask=intval($this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('Two-factor authentication'));
        return $this->oc['SourcePot\Datapool\Foundation\Access']->hasAccess($user,$twoFactorAuthenticationBitMask);
    }

    /******************************************************************************************************************************************
    *   User login status
    */
    
    private function successUserLoginKey($user):string
    {
        return 'login_'.$user['EntryId'];
    }

    public function successfulUserLogin($user)
    {
        $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,$this->successUserLoginKey($user),time());
    }

    public function resetUserLogin($user)
    {
        $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,$this->successUserLoginKey($user),0);
    }

    private function validUserLogin():bool
    {
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $loginTimestamp=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,$this->successUserLoginKey($user));
        return (time()-$loginTimestamp?:0)<self::MAX_LOGIN_AGE;
    }

    /******************************************************************************************************************************************
    *   Generate login token: code
    */
    
    private function getUserCodeKey($user):string
    {
        return 'code_'.$user['EntryId'];
    }

    private function sendTwoFactorAuthenticationRequest($user,string $transmitter):int
    {
        $code=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,$this->getUserCodeKey($user),mt_rand(100000,999999));
        $subject='Login Authentication PIN';
        $message='Your authentication PIN is: '.$code;
        return $this->oc[$transmitter]->send($user['EntryId'],['Content'=>['Subject'=>$subject,'Message'=>$message,]]);
    }

    /******************************************************************************************************************************************
    *   HTML-Form creation
    */
    
    private function availableTransmitter():array
    {
        $availableTransmitter=[];
        $transmitter=$this->oc['SourcePot\Datapool\Root']->getImplementedInterfaces('SourcePot\Datapool\Interfaces\Transmitter');
        foreach($transmitter as $transmitterClass=>$transmitterName){
            if (isset(self::TRANSMITTER_BLACKLIST[$transmitterClass])){continue;}
            $availableTransmitter[$transmitterClass]=$transmitterName;
        }
        return $availableTransmitter;
    }
    
    private function getTwoFactorAuthenticationHtml():string
    {
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $settings=['method'=>'getTwoFactorAuthenticationForm','classWithNamespace'=>__CLASS__];
        $selector=['Source'=>$user['Source'],'EntryId'=>$user['EntryId'],];
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('2FA form','generic',$selector,$settings,['style'=>['border'=>'none','width'=>'auto']]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE,'id'=>'login-article']);
        return $html;
    }

    public function getTwoFactorAuthenticationForm(array $arr):array
    {
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $transmitter=$this->availableTransmitter();
        $settings=['transmitter'=>$transmitter['SourcePot\Datapool\Tools\Email']??current($transmitter),'code'=>''];
        // processing from
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (!empty($formData['val'])){$settings=$formData['val'];}
        if (isset($formData['cmd']['Login'])){
            $code=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,$this->getUserCodeKey($user));
            if (intval($settings['code'])===intval($code??0) && $this->validUserLogin()){
                // user login and correct code provided
                $userToLogin=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
                $this->oc['SourcePot\Datapool\Foundation\User']->loginUser($userToLogin);
                $this->oc['logger']->log('info','2FA login for "{email}" at "{dateTime}" was successful.',['lifetime'=>'P30D','email'=>$user['Params']['User registration']['Email'],'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')]);    
                header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(['category'=>'Home']));
                exit;
            } else {
                // code failed - reset login
                $this->resetUserLogin($user);
                $this->oc['logger']->log('notice','Failed 2FA login for "{email}" at "{dateTime}".',['lifetime'=>'P30D','email'=>$user['Params']['User registration']['Email'],'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')]);    
                header("Location: ".$this->oc['SourcePot\Datapool\Tools\NetworkTools']->href(['category'=>'Logout']));
                exit;
            }
        } else if (isset($formData['cmd']['Request'])){
            // request code
            $success=$this->sendTwoFactorAuthenticationRequest($user,$settings['transmitter']);
            $codeSent=boolval($success);
            $transmitterFailed=!boolval($success);;
        }
        // compile html
        $selectorArr=['options'=>$transmitter,'key'=>['transmitter'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'value'=>$settings['transmitter'],'excontainer'=>TRUE];
        $matrix=[];
        $matrix['Transmitter']=['Value'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectorArr)];
        if (!empty($transmitterFailed)){
            $matrix['Code']=['Value'=>['tag'=>'h3','element-content'=>'Transmitter failed']];
            $matrix['Request']=['Value'=>['tag'=>'button','element-content'=>'Request code','key'=>['Request'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>TRUE]];
        } else if (!empty($codeSent)){
            $btnId=md5(strval(hrtime(TRUE)));
            $matrix['Code']=['Value'=>['tag'=>'input','type'=>'text','key'=>['code'],'placeholder'=>'123456','trigger-id'=>$btnId,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>TRUE]];
            $matrix['Login']=['Value'=>['tag'=>'button','element-content'=>'Login','key'=>['Login'],'id'=>$btnId,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>TRUE]];
        } else {
            $matrix['Request']=['Value'=>['tag'=>'button','element-content'=>'Request code','key'=>['Request'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>TRUE]];
        }
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'style'=>'clear:left;','hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Two-Factor Authentication']);
        return $arr;
    }
}
?>