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

class Access{
	
	private $arr;

	private $access=array('NO_R'=>0,'PUBLIC_R'=>1,'REGISTERED_R'=>2,'MEMBER_R'=>4,'CCTV_R'=>1024,'ADMIN_R'=>32768,'ALL_CONTENTADMIN_R'=>49152,'ALL_REGISTERED_R'=>65534,'ALL_MEMBER_R'=>65532,'ALL_R'=>65535);
	
	private $digits=array(array('key'=>'a','sizeScaler'=>1,'font'=>'OpenSansLight.ttf','symbol'=>'?','description'=>'Question mark'),
						  array('key'=>'b','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>74,'description'=>'Spiral'),
						  array('key'=>'c','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>69,'description'=>'Mouse'),
						  array('key'=>'d','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>77,'description'=>'Mug'),
						  array('key'=>'e','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>78,'description'=>'Hand'),
						  array('key'=>'f','sizeScaler'=>1,'font'=>'Digits.ttf','symbol'=>60,'description'=>'Arrow right'),
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
						  array('key'=>'u','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>53,'description'=>''),
						  array('key'=>'v','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>41,'description'=>''),
						  array('key'=>'w','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>74,'description'=>''),
						  array('key'=>'x','sizeScaler'=>1.2,'font'=>'Digits.ttf','symbol'=>65,'description'=>''),
						  array('key'=>'9','sizeScaler'=>1.2,'font'=>'GOODDB__.TTF','symbol'=>53,'description'=>'Cloud'),
						);
	
	private $entryTable;
	private $entryTemplate=array();
    
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		$access=array('Class'=>__CLASS__,'SettingName'=>__FUNCTION__,'Content'=>$this->access);
		$access=$this->arr['Datapool\Tools\FileTools']->entryByKeyCreateIfMissing($access,TRUE);
		$this->access=$access['Content'];
		return $this->arr;
	}
	
	public function getEntryTable(){return $this->entryTable;}

	public function getEntryTemplate(){return $this->entryTemplate;}
	
	public function accessString2int($string='ADMIN_R',$setNoRightsIfMissing=TRUE){
		if (isset($this->access[$string])){
			return $this->access[$string];
		} else if ($setNoRightsIfMissing){
			return $this->access['NO_R'];
		} else {
			return $string;
		}
	}

	public function addRights($entry,$Read=FALSE,$Write=FALSE,$Privileges=FALSE){
		//	This function adds named rights to $entry based on the rights constants names or, if this fails, 
		//  it changes existing right of entry Read and/or Write key if these contain a named right.
		//
		$rigthTypes=array('Read'=>'MEMBER_R','Write'=>'MEMBER_R','Privileges'=>'PUBLIC_R');
		foreach($rigthTypes as $type=>$default){
			// set to default value
			if (!isset($entry[$type])){$entry[$type]=$default;}
			// set to method argument
			if (isset($this->access[$$type])){$entry[$type]=$this->access[$$type];}
			// replace alias values
			$entry=$this->replaceRightConstant($entry,$type);
		}
		if (!isset($entry['Read']) || !isset($entry['Write'])){
			throw new \ErrorException('Function '.__FUNCTION__.': Unable to set valid entry right.',0,E_ERROR,__FILE__,__LINE__);	
		}
		if (isset($entry['Read'])){$entry['Read']=intval($entry['Read']);}
		if (isset($entry['Write'])){$entry['Write']=intval($entry['Write']);}
		return $entry;		
	}
	
	public function replaceRightConstant($entry,$type='Read'){
		if (!isset($entry[$type])){return $entry;}
		if (is_array($entry[$type])){return $entry;}
		if (isset($this->access[$entry[$type]])){$entry[$type]=$this->access[$entry[$type]];}
		return $entry;
	}

	public function access($entry,$type='Write',$user=FALSE,$isSystemCall=FALSE,$ignoreOwner=FALSE){
		if ($isSystemCall===TRUE){return 'SYSTEMCALL';}
		if (empty($entry)){return FALSE;}
		if ($user===FALSE){$user=$_SESSION['currentUser'];}
		if (empty($entry['Owner'])){$entry['Owner']='Entry Owner Missing';}
		if (empty($user['Owner'])){$user['Owner']='User ElementId Missing';}
		if (strcmp($user['Owner'],'SYSTEM')===0 || $ignoreOwner){$user['ElementId']='User id Invalid';}
		if (strcmp($entry['Owner'],$user['ElementId'])===0){
			return 'CREATOR MATCH';
		} else if (isset($entry[$type])){
			$accessLevel=intval($entry[$type]) & intval($user['Privileges']);
			if ($accessLevel>0){return $accessLevel;}
		} else {
			throw new \ErrorException('Function '.__FUNCTION__.': Type '.$type.' missing in argument entry.',0,E_ERROR,__FILE__,__LINE__);
		}
		return FALSE;
	}
	
	public function emailId($email){
		$emailId=md5($email."kjHD1W82IQ9iBS");
		return $emailId;
	}

	public function loginId($email,$password){
		$emailId=$this->emailId($email);
		$userPass=$password.$emailId;
		$loginId=password_hash($userPass,PASSWORD_DEFAULT);
		return $loginId;
	}
	
	public function verfiyPassword($email,$password,$loginId){
		$emailId=$this->emailId($email);
		$userPass=$password.$emailId;
		if (password_verify($userPass,$loginId)===TRUE){
			return TRUE;
		} else {
			return FALSE;
		}
	}

	public function isAdmin($user=FALSE){
		if (empty($user)){
			if (empty($_SESSION['currentUser'])){
				return FALSE;
			} else {
				$user=$_SESSION['currentUser'];
			}
		}
		if (($_SESSION['currentUser']['Privileges'] & $this->access['ADMIN_R'])>0){
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	public function isContentAdmin($user=FALSE){
		if (empty($user)){$user=$_SESSION['currentUser'];}
		if (($_SESSION['currentUser']['Privileges'] & $this->access['ALL_CONTENTADMIN_R'])>0){
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	public function getSymbolKeypad($arr){
		$template=array('symbolSize'=>50,'html'=>'','symbolColumnCount'=>5);
		$arr=array_merge($template,$arr);
		$hashSymbolArr=array();
		$aArr=array('tag'=>'a','href'=>'#','style'=>array('position'=>'absolute','top'=>'0','left'=>'0','padding'=>'0'),'class'=>'imgPassLink','keep-element-content'=>TRUE);
		$imgArr=array('tag'=>'img');
		$layersDivArr=array('tag'=>'div','keep-element-content'=>TRUE,'style'=>array('position'=>'relative','width'=>$arr['symbolSize'].'px','height'=>$arr['symbolSize'].'px','margin'=>'1px'));
		shuffle($this->digits);
		foreach ($this->digits as $digitIndex => $digitDef){
			$layersHtml='';
			for ($layer=0;$layer<10;$layer++){ 
				$imgTmpHash=$this->arr['Datapool\Tools\StrTools']->getRandomString(20);
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
				$fontFile=$this->arr['env']['font dir'].$digitDef['font'];
				imagettftext($image,$imgSize,$imgAngle,$imgX,$imgY,$fg,$fontFile,$symbol);
				ob_start();
				imagepng($image);
				$imagedata=ob_get_contents();
				ob_end_clean();
				$imgArr['src']='data:image/png;base64,'.base64_encode($imagedata);
				$aArr['id']=$imgTmpHash.'_loginSymbol';
				$aArr['title']='Login symbol';
				$aArr['element-content']=$this->arr['Datapool\Tools\HTMLbuilder']->element($imgArr);
				$layersHtml.=$this->arr['Datapool\Tools\HTMLbuilder']->element($aArr);
				$hashSymbolArr[$imgTmpHash]=array('digit'=>$digitDef['key']);
			}
			$layersDivArr['element-content']=$layersHtml;
			if ($digitIndex%$arr['symbolColumnCount']===0 && $digitIndex>0){$layersDivArr['style']['clear']='both';} else {$layersDivArr['style']['clear']='none';}
			$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->element($layersDivArr);
		}
		// add hidden input and passphrase preview
		$previewArr=array('tag'=>'div','element-content'=>'','class'=>'phrase-preview');
		$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->element($previewArr);
		$phraseArr=array('tag'=>'input','type'=>'hidden','key'=>array('Login','Password'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'element-content'=>'','class'=>'pass-phrase');
		$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->element($phraseArr);
		
		// save state
		$this->arr['Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'hashSymbolArr',$hashSymbolArr);
		echo $arr['html'];
		return $arr;
	}

}
?>