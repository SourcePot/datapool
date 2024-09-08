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
    
    private $oc;

    private $formType=0;
    private $pageSettings=array();
    
    private $digits=array(array('key'=>'a','sizeScaler'=>1,'font'=>'OpenSansLight.ttf','symbol'=>'?','description'=>'Question mark'),
                          array('key'=>'b','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>74,'description'=>'Spiral'),
                          array('key'=>'c','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>69,'description'=>'Mouse'),
                          array('key'=>'d','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>77,'description'=>'Mug'),
                          array('key'=>'e','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>78,'description'=>'Hand'),
                          array('key'=>'f','sizeScaler'=>1,'font'=>'Digits.ttf','symbol'=>60,'description'=>'White arrow pointing right'),
                          array('key'=>'g','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>80,'description'=>'Person'),
                          array('key'=>'h','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>70,'description'=>'Cheese'),
                          array('key'=>'i','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>75,'description'=>'Magnifying glass'),
                          array('key'=>'j','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>124,'description'=>'T-shirt'),
                          array('key'=>'k','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>163,'description'=>'Note'),
                          array('key'=>'l','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>235,'description'=>'Umbrella'),
                          array('key'=>'m','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>237,'description'=>'Moon'),
                          array('key'=>'n','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>35,'description'=>'Airplane'),
                          array('key'=>'o','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>41,'description'=>'Pie chart'),
                          array('key'=>'p','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>74,'description'=>'Bell'),
                          array('key'=>'q','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>79,'description'=>'Black chair'),
                          array('key'=>'r','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>120,'description'=>'White chair'),
                          array('key'=>'s','sizeScaler'=>1,'font'=>'icon-works-webfont.ttf','symbol'=>82,'description'=>'Light bulb'),
                          array('key'=>'t','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>71,'description'=>'Cow'),
                          array('key'=>'u','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>53,'description'=>'5'),
                          array('key'=>'v','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>41,'description'=>'Black arrow pointing right'),
                          array('key'=>'w','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>74,'description'=>'10'),
                          array('key'=>'x','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>65,'description'=>'1'),
                          array('key'=>'9','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>53,'description'=>'Cloud'),
                        );
    
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
    
    public function oneTimeLoginEntry(array $arr,$user):array
    {
        $maxDigitsIndex=count($this->digits)-1;
        $loginEntry=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),
                          'Group'=>$this->pageSettings['pageTitle'],
                          'Folder'=>'Login links',
                          'Name'=>'',
                          'EntryId'=>$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($arr['Email']).'-oneTimeLink',
                          'Expires'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','PT10M'),
                          'Content'=>array('Message'=>''),
                          );
        $loginEntry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($loginEntry,'ADMIN_R','ADMIN_R');
        $passphraseIcons='';
        for($index=0;$index<6;$index++){
            $keyArr=$this->digits[random_int(0,$maxDigitsIndex)];
            $loginEntry['Name'].=$keyArr['key'];
            $loginEntry['Content']['Message'].=$keyArr['description'].' | ';
        }
        $loginEntry['Content']['Message']=trim($loginEntry['Content']['Message'],'| ');
        return $loginEntry;
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
            if (empty($user['EntryId'])){$user=$_SESSION['currentUser'];}
            if (isset($user['Params']['User registration']['Email'])){
                $result['Email']=$user['Params']['User registration']['Email'];
            }
        }
        return $result;
    }
    
    public function getLoginForm(array $arr=array()):array
    {
        $arr['result']=$this->formData();
        if (self::USE_RECAPTCHA){$styleClass='g-recaptcha';} else {$styleClass='std';}
        $emailLabel=array('tag'=>'label','element-content'=>'Email','for'=>'login-email');
        $email=array('tag'=>'input','type'=>'email','key'=>array('Email'),'id'=>'login-email','style'=>array('clear'=>'both','width'=>220),'filter'=>FILTER_SANITIZE_EMAIL,'required'=>TRUE,'pattern'=>"[\w-\.]+@([\w-]+\.)+[\w-]{2,6}",'callingClass'=>__CLASS__,'callingFunction'=>'loginForm');
        $updateBtn=array('tag'=>'input','type'=>'submit','key'=>array('Update'),'value'=>'Update','callingClass'=>__CLASS__,'callingFunction'=>'loginForm');
        $loginBtn=array('tag'=>'input','type'=>'submit','key'=>array('Login'),'value'=>'Login','class'=>$styleClass,'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','style'=>array('position'=>'absolute','top'=>'0.2em','left'=>'0','width'=>'45%','margin'=>0,'border'=>'2px solid #4d0','font-weight'=>'bold'));
        $registerBtn=array('tag'=>'input','type'=>'submit','key'=>array('Register'),'value'=>'Register','class'=>$styleClass,'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','style'=>array('position'=>'absolute','top'=>'0.2em','right'=>'0','width'=>'45%','margin'=>'0 4px 0 0'));
        $loginLinkBtn=array('tag'=>'input','type'=>'submit','key'=>array('pswRequest'),'value'=>'Get login token','class'=>$styleClass,'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','style'=>array('margin'=>'1em 0'));
        if ($this->formType===1){
            $passphrase=$this->getSymbolKeypad($arr);
        } else {
            $passphrase=$this->getStandard($arr);
        }
        $matrix=array();
        if ($this->isLoggedIn()){
            $matrix['Passphrase']['Value']=$passphrase;
            $matrix['Btns']['Value']=$updateBtn;
        } else {
            $matrix['Email']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($emailLabel);
            $matrix['Email']['Value'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($email);
            $matrix['Passphrase']=array('Value'=>$passphrase);
            $matrix['Btns']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element($loginBtn);
            $matrix['Btns']['Value'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($registerBtn);
            $matrix['Recover']=array('Value'=>$loginLinkBtn);
        }
        $matrix['Btns']['trStyle']=array('height'=>'3em');
        $formHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Login','id'=>'login-table'));
        $formHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$formHtml,'keep-element-content'=>TRUE,'id'=>'login-article'));
        if (isset($arr['html'])){$arr['html'].=$formHtml;} else {$arr['html']=$formHtml;}
        return $arr;
    }
    
    private function getStandard(array $arr=array()):string
    {
        $passphraseLabel=array('tag'=>'label','element-content'=>'Passphrase','for'=>'login-psw');
        $passphrase=array('tag'=>'input','type'=>'password','key'=>array('Passphrase'),'id'=>'login-psw','required'=>TRUE,'minlength'=>'6','style'=>array('clear'=>'both','width'=>220),'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','excontainer'=>TRUE);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element($passphraseLabel);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($passphrase);
        return $html;
    }
    
    private function getSymbolKeypad(array $arr=array()):string
    {
        $template=array('symbolSize'=>40,'html'=>'','symbolColumnCount'=>5);
        $arr=array_merge($template,$arr);
        $hashSymbolArr=array();
        $aArr=array('tag'=>'a','href'=>'javascript:','class'=>'keypad','keep-element-content'=>TRUE,'excontainer'=>TRUE);
        $imgArr=array('tag'=>'img');
        $layersDivArr=array('tag'=>'div','keep-element-content'=>TRUE,'style'=>array('width'=>$arr['symbolSize'].'px','height'=>$arr['symbolSize'].'px'),'class'=>'keypad');
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
        $previewArr=array('tag'=>'div','element-content'=>'','class'=>'phrase-preview');
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($previewArr);
        $previewBtn=array('tag'=>'a','element-content'=>'Clear','href'=>'#','class'=>'phrase-preview');
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($previewBtn);
        $phraseArr=array('tag'=>'input','type'=>'hidden','key'=>array('Passphrase'),'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','element-content'=>'','class'=>'pass-phrase');
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
        return intval($_SESSION['currentUser']['Privileges'])>1;
    }

}
?>