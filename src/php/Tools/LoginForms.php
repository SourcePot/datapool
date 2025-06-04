<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Tools;

class LoginForms{

    private const USE_RECAPTCHA=TRUE;
    private const MIN_PSW_LENGTH=6;
    
    private $oc;

    private $formType=0;
    
    private $digits=[['key'=>'a','sizeScaler'=>1,'font'=>'OpenSansLight.ttf','symbol'=>'?','description'=>'Question mark'],
                    ['key'=>'b','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>74,'description'=>'Spiral'],
                    ['key'=>'c','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>69,'description'=>'Mouse'],
                    ['key'=>'d','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>77,'description'=>'Mug'],
                    ['key'=>'e','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>78,'description'=>'Hand'],
                    ['key'=>'f','sizeScaler'=>1,'font'=>'Digits.ttf','symbol'=>60,'description'=>'White arrow pointing right'],
                    ['key'=>'g','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>80,'description'=>'Person'],
                    ['key'=>'h','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>70,'description'=>'Cheese'],
                    ['key'=>'i','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>75,'description'=>'Magnifying glass'],
                    ['key'=>'j','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>124,'description'=>'T-shirt'],
                    ['key'=>'k','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>163,'description'=>'Note'],
                    ['key'=>'l','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>235,'description'=>'Umbrella'],
                    ['key'=>'m','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>237,'description'=>'Moon'],
                    ['key'=>'n','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>35,'description'=>'Airplane'],
                    ['key'=>'o','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>41,'description'=>'Pie chart'],
                    ['key'=>'p','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>74,'description'=>'Bell'],
                    ['key'=>'q','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>79,'description'=>'Black chair'],
                    ['key'=>'r','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>120,'description'=>'White chair'],
                    ['key'=>'s','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>82,'description'=>'Light bulb'],
                    ['key'=>'t','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>71,'description'=>'Cow'],
                    ['key'=>'u','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>53,'description'=>'5'],
                    ['key'=>'v','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>41,'description'=>'Black arrow pointing right'],
                    ['key'=>'w','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>74,'description'=>'10'],
                    ['key'=>'x','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>65,'description'=>'1'],
                    ['key'=>'9','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>53,'description'=>'Cloud'],
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
        $this->formType=intval($this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('loginForm'));
    }
    
    public function getOneTimePswArr():array
    {
        $maxDigitsIndex=count($this->digits)-1;
        $return=['string'=>'','phrase'=>[]];
        for($index=0;$index<self::MIN_PSW_LENGTH;$index++){
            $int=random_int(0,$maxDigitsIndex);
            $keyArr=$this->digits[$int];
            $return['string'].=$keyArr['key'];
            $return['phrase'][]=$keyArr['description'];
        }
        $return['phrase']=implode(' | ',$return['phrase']);
        return $return;
    }    

    private function formData():array
    {
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'loginForm');
        $result=$formData['val'];
        $result['cmd']=empty($formData['cmd'])?'':key($formData['cmd']);
        if ($this->formType===1 && !empty($result['Passphrase'])){
            // symbol login
            $hashSymbolArr=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'hashSymbolArr');
            $symbolIds=explode('|',$result['Passphrase']);
            $result['Passphrase']='';
            foreach($symbolIds as $index=>$symbolId){
                if (!isset($hashSymbolArr[$symbolId])){continue;}
                $result['Passphrase'].=$hashSymbolArr[$symbolId]['key'];
            }
        }
        if ($this->isLoggedIn() && empty($result['Email'])){
            // Email input field was missing - get email from user entry
            $class=$this->oc['SourcePot\Datapool\Root']->source2class('user');
            $user=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState($class);
            $user=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user);
            if (empty($user['EntryId'])){$user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();}
            if (isset($user['Params']['User registration']['Email'])){
                $result['Email']=$user['Params']['User registration']['Email'];
            }
        }
        return $result;
    }
    
    public function getLoginForm(array $arr=[]):array
    {
        $arr['result']=$this->formData();
        if (self::USE_RECAPTCHA){$styleClass='g-recaptcha';} else {$styleClass='std';}
        $emailLabel=['tag'=>'label','element-content'=>'Email','for'=>'login-email'];
        $email=['tag'=>'input','type'=>'email','key'=>['Email'],'id'=>'login-email','placeholder'=>'Email','style'=>['clear'=>'both','width'=>220],'filter'=>FILTER_SANITIZE_EMAIL,'required'=>TRUE,'pattern'=>"[\w-\.]+@([\w-]+\.)+[\w-]{2,6}",'callingClass'=>__CLASS__,'callingFunction'=>'loginForm'];
        $updateBtn=['tag'=>'input','type'=>'submit','key'=>['Update'],'value'=>'Update','callingClass'=>__CLASS__,'callingFunction'=>'loginForm'];
        $loginBtn=['tag'=>'input','type'=>'submit','key'=>['Login'],'value'=>'Login','class'=>$styleClass,'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','style'=>['position'=>'absolute','top'=>'0.2em','left'=>'0','width'=>'45%','margin'=>0,'border'=>'2px solid #4d0','font-weight'=>'bold']];
        $registerBtn=['tag'=>'input','type'=>'submit','key'=>['Register'],'value'=>'Register','class'=>$styleClass,'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','style'=>['position'=>'absolute','top'=>'0.2em','right'=>'0','width'=>'45%','margin'=>'0 4px 0 0']];
        $loginLinkBtn=['tag'=>'input','type'=>'submit','key'=>['pswRequest'],'value'=>'Get login token','class'=>$styleClass,'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','style'=>['margin'=>'1em 0']];
        if ($this->formType===1){
            $passphrase=$this->getSymbolKeypad($arr);
        } else {
            $passphrase=$this->getStandard($arr);
        }
        $matrix=[];
        if ($this->isLoggedIn()){
            $matrix['Passphrase']['Value']=$passphrase;
            $matrix['Btns']['Value']=$updateBtn;
        } else {
            //$matrix['Email']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($emailLabel);
            $matrix['Email']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($email);
            $matrix['Passphrase']=['Value'=>$passphrase];
            $matrix['Btns']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($loginBtn);
            $matrix['Btns']['Value'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($registerBtn);
            $matrix['Recover']=['Value'=>$loginLinkBtn];
        }
        $matrix['Btns']['trStyle']=['height'=>'3em'];
        $formHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Login '.($arr['result']['Email']??''),'id'=>'login-table']);
        $formHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$formHtml,'keep-element-content'=>TRUE,'id'=>'login-article']);
        if (isset($arr['html'])){$arr['html'].=$formHtml;} else {$arr['html']=$formHtml;}
        return $arr;
    }
    
    private function getStandard(array $arr=[]):string
    {
        //$passphraseLabel=['tag'=>'label','element-content'=>'Passphrase','for'=>'login-psw'];
        $passphrase=['tag'=>'input','type'=>'password','key'=>['Passphrase'],'id'=>'login-psw','placeholder'=>'Passphrase','required'=>TRUE,'minlength'=>'6','style'=>['clear'=>'both','width'=>220],'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','excontainer'=>TRUE];
        //$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($passphraseLabel);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($passphrase);
        return $html;
    }
    
    private function getSymbolKeypad(array $arr=[]):string
    {
        $template=['symbolSize'=>40,'html'=>'','symbolColumnCount'=>5];
        $arr=array_merge($template,$arr);
        $hashSymbolArr=[];
        $aArr=['tag'=>'a','href'=>'javascript:','class'=>'keypad','keep-element-content'=>TRUE,'excontainer'=>TRUE];
        $imgArr=['tag'=>'img'];
        $layersDivArr=['tag'=>'div','keep-element-content'=>TRUE,'style'=>['width'=>$arr['symbolSize'].'px','height'=>$arr['symbolSize'].'px'],'class'=>'keypad'];
        shuffle($this->digits);
        $html=$arr['html'];
        foreach ($this->digits as $digitIndex => $digitDef){
            $layersHtml='';
            for ($layer=0;$layer<10;$layer++){ 
                $imgTmpHash=$this->getHash(20);
                $image=imagecreate($arr['symbolSize'],$arr['symbolSize']);
                $bg=imagecolorallocatealpha($image,255,255,255,0);
                $fg=imagecolorallocate($image,0,0,0);
                imagefill($image,0,0,$bg);
                $imgSizeMax=intval(round($digitDef['sizeScaler']*0.7*$arr['symbolSize']));
                $imgSizeMin=intval(round($imgSizeMax*0.8));
                $imgSize=mt_rand($imgSizeMin,$imgSizeMax);
                $imgXMax=intval(round($digitDef['sizeScaler']*0.2*$arr['symbolSize']));
                $imgXMin=intval(round($imgXMax*0.2));
                $imgX=mt_rand($imgXMin,$imgXMax);
                $imgYMax=$arr['symbolSize'];
                $imgYMin=intval(round($imgYMax-0.3*$imgSize));
                $imgY=mt_rand($imgYMin,$imgYMax);
                $imgAngleMax=20;
                $imgAngleMin=-20;
                $imgAngle=mt_rand($imgAngleMin,$imgAngleMax);
                $symbol=(is_int($digitDef['symbol']))?chr($digitDef['symbol']):$digitDef['symbol'];
                $fontFile=$GLOBALS['dirs']['fonts'].'/'.$digitDef['font'];
                imagettftext($image,$imgSize,$imgAngle,$imgX,$imgY,$fg,$fontFile,$symbol);
                ob_start();
                imagepng($image);
                $imagedata=ob_get_contents();
                ob_end_clean();
                $imgArr['src']='data:image/png;base64,'.base64_encode($imagedata);
                $imgArr['class']='keypad';
                $aArr['id']=$imgTmpHash.'_loginSymbol';
                $aArr['title']='Login symbol';
                //$aArr['title']=$digitDef['description'];
                $aArr['element-content']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($imgArr);
                $layersHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($aArr);
                $hashSymbolArr[$imgTmpHash]=$digitDef;
            }
            $layersDivArr['element-content']=$layersHtml;
            if ($digitIndex%$arr['symbolColumnCount']===0 && $digitIndex>0){$layersDivArr['style']['clear']='both';} else {$layersDivArr['style']['clear']='none';}
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($layersDivArr);
        }
        // add hidden input and passphrase preview
        $previewArr=['tag'=>'div','element-content'=>'','class'=>'phrase-preview'];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($previewArr);
        $previewBtn=['tag'=>'a','element-content'=>'Clear','href'=>'#','class'=>'phrase-preview'];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($previewBtn);
        $phraseArr=['tag'=>'input','type'=>'hidden','key'=>['Passphrase'],'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','element-content'=>'','class'=>'pass-phrase'];
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($phraseArr);
        // save state
        $this->oc['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'hashSymbolArr',$hashSymbolArr);
        return $html;
    }
    
    private function getHash(int $length):string
    {
        $hash='';
        $byteStr=random_bytes($length);
        for ($i=0;$i<$length;$i++){
            $byte=ord($byteStr[$i]);
            if ($byte>180){
                $hash.=chr(97+($byte%26));
            } else if ($byte>75){
                $hash.=chr(65+($byte%26));
            } else {
                $hash.=chr(48+($byte%10));
            }
        }
        return $hash;
    }

    private function isLoggedIn():bool
    {
        $currentUser=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        return intval($currentUser['Privileges'])>1;
    }

}
?>