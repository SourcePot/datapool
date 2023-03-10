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

namespace SourcePot\Datapool\Tools;

class LoginForms{
	
	private $arr;

	public $formType=0;
	
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
						  array('key'=>'t','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>71,'description'=>''),
						  array('key'=>'u','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>53,'description'=>'5'),
						  array('key'=>'v','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>41,'description'=>'Black arrow pointing right'),
						  array('key'=>'w','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>74,'description'=>'10'),
						  array('key'=>'x','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>65,'description'=>'1'),
						  array('key'=>'9','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>53,'description'=>'Cloud'),
						);
	
	public function __construct($arr){
		$this->arr=$arr;
	}
	
	public function init($arr){
		$this->arr=$arr;
		return $this->arr;
	}
	
	private function formData(){
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,'loginForm');
		$result=$formData['val'];
		$result['cmd']=empty($formData['cmd'])?'':key($formData['cmd']);
		if ($this->formType===1 && !empty($result['Passphrase'])){
			// symbol login
			$hashSymbolArr=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'hashSymbolArr');
			$symbolIds=explode('|',$result['Passphrase']);
			$result['Passphrase']='';
			foreach($symbolIds as $index=>$symbolId){
				if (!isset($hashSymbolArr[$symbolId])){continue;}
				$result['Passphrase'].=$hashSymbolArr[$symbolId]['key'];
			}
		}
		$result['Recovery']=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->getPageStateByKey(__CLASS__,'recovery');
		if ($this->isLoggedIn() && empty($result['Email'])){
			$result['Email']=$_SESSION['currentUser']['Params']['User registration']['Email'];
		}
		return $result;
	}
	
	public function getLoginForm($arr=array()){
		$arr['result']=$this->formData();
		//
		$email=array('tag'=>'input','type'=>'email','key'=>array('Email'),'filter'=>FILTER_SANITIZE_EMAIL,'required'=>TRUE,'pattern'=>"[\w-\.]+@([\w-]+\.)+[\w-]{2,6}",'callingClass'=>__CLASS__,'callingFunction'=>'loginForm');
		$updateBtn=array('tag'=>'input','type'=>'submit','key'=>array('Update'),'value'=>'Update','callingClass'=>__CLASS__,'callingFunction'=>'loginForm');
		$loginBtn=array('tag'=>'input','type'=>'submit','key'=>array('Login'),'value'=>'Login','callingClass'=>__CLASS__,'callingFunction'=>'loginForm');
		$registerBtn=array('tag'=>'input','type'=>'submit','key'=>array('Register'),'value'=>'Register','callingClass'=>__CLASS__,'callingFunction'=>'loginForm','style'=>array('float'=>'right'));
		$loginLinkBtn=array('tag'=>'input','type'=>'submit','key'=>array('pswRequest'),'value'=>'Get one time psw','callingClass'=>__CLASS__,'callingFunction'=>'loginForm');
		if ($this->formType===1){
			$passphrase=$this->getSymbolKeypad($arr);
		} else {
			$passphrase=$this->getStandard($arr);
		}
		$matrix=array();
		if ($this->isLoggedIn()){
			$matrix['']=array('Value'=>$passphrase);
			$matrix[' ']=array('Value'=>$updateBtn);
		} else {
			$matrix['Email']=array('Value'=>$email);
			$matrix['Passphrase']=array('Value'=>$passphrase);
			$btns=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($loginBtn).$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($registerBtn);
			$matrix['']=array('Value'=>$btns);
			$matrix['Recover']=array('Value'=>$loginLinkBtn);
		}
		$formHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Login'));
		$formHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'article','element-content'=>$formHtml,'keep-element-content'=>TRUE,'style'=>array('float'=>'none','margin'=>'2em auto','width'=>'fit-content','padding'=>'1em')));
		if (isset($arr['html'])){$arr['html'].=$formHtml;} else {$arr['html']=$formHtml;}
		return $arr;
	}
	
	private function getStandard($arr=array()){
		$recovery=array('Passphrase'=>$this->getHash(20));
		$recovery['Passphrase for user']='"'.$recovery['Passphrase'].'"';
		$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'recovery',$recovery);
		$passphraseArr=array('tag'=>'input','type'=>'password','key'=>array('Passphrase'),'required'=>TRUE,'minlength'=>'8','callingClass'=>__CLASS__,'callingFunction'=>'loginForm','excontainer'=>TRUE);
		return $this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($passphraseArr);
	}
	
	private function getSymbolKeypad($arr=array()){
		$template=array('symbolSize'=>50,'html'=>'','symbolColumnCount'=>5);
		$arr=array_merge($template,$arr);
		$recovery=array('Passphrase'=>'','Passphrase for user'=>'');
		$hashSymbolArr=array();
		$aArr=array('tag'=>'a','href'=>'#','style'=>array('position'=>'absolute','top'=>'0','left'=>'0','padding'=>'0'),'class'=>'imgPassLink','keep-element-content'=>TRUE);
		$imgArr=array('tag'=>'img');
		$layersDivArr=array('tag'=>'div','keep-element-content'=>TRUE,'style'=>array('position'=>'relative','width'=>$arr['symbolSize'].'px','height'=>$arr['symbolSize'].'px','margin'=>'1px'));
		shuffle($this->digits);
		$html=$arr['html'];
		foreach ($this->digits as $digitIndex => $digitDef){
			if (strlen($recovery['Passphrase'])<6){
				if (!empty($recovery['Passphrase for user'])){$recovery['Passphrase for user'].=', ';}
				$recovery['Passphrase for user'].='"'.$digitDef['description'].'"';
				$recovery['Passphrase'].=$digitDef['key'];
			}
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
				$fontFile=$GLOBALS['font dir'].$digitDef['font'];
				imagettftext($image,$imgSize,$imgAngle,$imgX,$imgY,$fg,$fontFile,$symbol);
				ob_start();
				imagepng($image);
				$imagedata=ob_get_contents();
				ob_end_clean();
				$imgArr['src']='data:image/png;base64,'.base64_encode($imagedata);
				$aArr['id']=$imgTmpHash.'_loginSymbol';
				$aArr['title']='Login symbol';
				//$aArr['title']=$digitDef['description'];
				$aArr['element-content']=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($imgArr);
				$layersHtml.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($aArr);
				$hashSymbolArr[$imgTmpHash]=$digitDef;
			}
			$layersDivArr['element-content']=$layersHtml;
			if ($digitIndex%$arr['symbolColumnCount']===0 && $digitIndex>0){$layersDivArr['style']['clear']='both';} else {$layersDivArr['style']['clear']='none';}
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($layersDivArr);
		}
		// add hidden input and passphrase preview
		$previewArr=array('tag'=>'div','element-content'=>'','class'=>'phrase-preview');
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($previewArr);
		$phraseArr=array('tag'=>'input','type'=>'hidden','key'=>array('Passphrase'),'callingClass'=>__CLASS__,'callingFunction'=>'loginForm','element-content'=>'','class'=>'pass-phrase');
		$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($phraseArr);
		// save state
		$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'hashSymbolArr',$hashSymbolArr);
		$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'recovery',$recovery);
		return $html;
	}
	
	private function getHash($length){
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

	private function isLoggedIn(){
		return intval($_SESSION['currentUser']['Privileges'])>1;
	}

}
?>