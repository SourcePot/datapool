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

namespace SourcePot\Datapool\Foundation;

class Dictionary{
	
	private $arr;

	private $entryTable='';
	private $entryTemplate=array();
	
	private $sourceLng='en';
	private $lngCodes=array('en'=>'English','de'=>'Deutsch','es'=>'Español','pl'=>'Polski');
	
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}

	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		$this->initDictionaryIfEmpty();
		$this->registerToolbox();
		return $this->arr;
	}
	
	private function initDictionaryIfEmpty(){
		$added=0;
		$hasEntry=$this->arr['SourcePot\Datapool\Foundation\Database']->hasEntry(array('Source'=>$this->entryTable,'Group'=>'Translations from en'));
		if (empty($hasEntry)){
			$transl=array('de'=>array('Add'=>'Hinzufügen','Save'=>'Speichern','Update'=>'Aktualisieren','Login'=>'Anmelden','Logout'=>'Abmelden',
									  'Register'=>'Registrieren','Send login link'=>'Login link anfordern','Password'=>'Passwort','...repeat'=>'...wiederholen','Delete'=>'Löschen',
									  'Language'=>'Sprache','Home'=>'Start','Account'=>'Konto','Email'=>'E-Mail',
									  'Dear'=>'Hallo','Please use your requested one-time link to log into'=>'Bitte benutze den angeforderten Einmal-Link zur Anmeldung bei',
									  'Requested login link from'=>'Der angefprderte Link von','The link is valid for 24hrs'=>'Der Link ist gültig für 24h','Best regards'=>'Viele Grüße'
									  ),
						  'es'=>array('Add'=>'Añadir','Save'=>'Guardar','Update'=>'Actualizar','Login'=>'Entrar','Logout'=>'Salir',
									  'Register'=>'Registrar','Send login link'=>'Enviar enlace de acceso','Password'=>'Contraseña','...repeat'=>'...repetir','Delete'=>'Borrar',
									  'Language'=>'Lengua','Home'=>'Inicio','Account'=>'Cuenta','Email'=>'Correo electrónico',
									  'Dear'=>'Querido','Please use your requested one-time link to log into'=>'Por favor, utilice el enlace solicitado para iniciar sesión',
									  'Requested login link from'=>'Solicitado enlace de inicio de sesión de','The link is valid for 24hrs'=>'El enlace es válido durante 24 horas','Best regards'=>'Saludos cordiales'
									  ),
						  'pl'=>array('Add'=>'Dodaj','Save'=>'Zapisz','Update'=>'Aktualizuj','Login'=>'Zaloguj','Logout'=>'Wyloguj',
									  'Register'=>'Zarejestruj','Send login link'=>'Wyślij link do logowania','Password'=>'Hasło','...repeat'=>'...powtarzaj','Delete'=>'Usuń',
									  'Language'=>'Język','Home'=>'Start','Account'=>'Konto','Email'=>'Email',
									  'Dear'=>'Drogi','Please use your requested one-time link to log into'=>'Proszę użyć żądanego jednorazowego linku, aby zalogować się do',
									  'Requested login link from'=>'Poproszono o link do logowania z','The link is valid for 24hrs'=>'Link jest ważny przez 24 godziny','Best regards'=>'Z wyrazami szacunku'
									  ),
						  );
			foreach($transl as $langCode=>$phrases){
				foreach($phrases as $phrase=>$translation){
					$this->lng($phrase,$langCode,$translation);
				}
			}
		}
		return $added;
	}
	
	public function unifyEntry($entry){
		if (empty($entry['EntryId'])){$entry['EntryId']=md5($entry['phrase'].'|'.$entry['langCode']);}
		if (empty($entry['Group'])){$entry['Group']='Translations from en';}
		if (empty($entry['Folder'])){$entry['Folder']=$entry['langCode'];}
		if (empty($entry['Name'])){$entry['Name']=substr($entry['phrase'],0,100);}
		if (empty($entry['Type'])){$entry['Type']='dictionary';}
		if (empty($entry['Date'])){$entry['Date']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getDateTime();}
		if (empty($entry['Owner'])){$entry['Owner']='SYSTEM';}
		if (empty($entry['Content'])){$entry['Content']=array('translation'=>$entry['translation']);}
		$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ALL_R','ADMIN_R');
		return $entry;
	}
	
	public function lng($phrase,$langCode=FALSE,$translation=FALSE){
		// This method provides the translation of the phrase argument or updates the translation if translation argument is provided.
		if (empty($langCode)){$langCode=$_SESSION['page state']['lngCode'];}
		$langCode=strtolower($langCode);
		if (!is_string($phrase)){return $phrase;}
		$phrase=strip_tags($phrase);
		$phrase=trim($phrase);
		if (strcmp($langCode,'en')===0 || empty($phrase)){return $phrase;}
		$elementId=md5($phrase.'|'.$langCode);
		if ($translation===FALSE){
			// translation request
			$selector=array('Source'=>$this->entryTable,'EntryId'=>$elementId);
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($selector);
			if (empty($entry)){
				return $phrase;
			} else {
				return $entry['Content']['translation'];
			}
		} else {
			// update translation
			$entry=array('Source'=>$this->entryTable,'phrase'=>$phrase,'translation'=>$translation,'langCode'=>$langCode);
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->unifyEntry($entry);
			$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
			return $translation;
		}
		return $phrase;
	}
	
	public function lngText($text='Dear {{First name}},',$placeholder=array('First name'=>'John')){
		$regexp='/(\s*\{{2}[\w\s]+\}{2}\s*)|([\r\n.,]+)/';
		$phrases=preg_split($regexp,$text);
		foreach($phrases as $index=>$from){
			if (empty($from)){continue;}
			$to=$this->lng($from);
			$text=str_replace($from,$to,$text);
		}
		foreach($placeholder as $from=>$to){
			$text=str_replace('{{'.$from.'}}',$to,$text);
		}
		return $text;
	}
	
	public function lngSelector(){
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['select'])){
			$_SESSION['page state']['lngCode']=$formData['val']['lngCode'];
		}
		//
		$selectArr=array('options'=>$this->lngCodes,'value'=>$_SESSION['page state']['lngCode'],'key'=>array('lngCode'),'hasSelectBtn'=>TRUE,'class'=>'menu','style'=>'float:right;','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
		return $html;
	}
	
	public function dictWidget($arr=array()){
		$langCode=$_SESSION['page state']['lngCode'];
		if (strcmp($langCode,$this->sourceLng)===0){
			$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>'Please select a language different to '.$this->lngCodes[$this->sourceLng],'style'=>'fonct-size:2em;padding:10px;'));
			return $arr;
		}
		// form processing
		if (!isset($_SESSION[__CLASS__][__FUNCTION__]['translation'][$langCode])){
			$_SESSION[__CLASS__][__FUNCTION__]=array('phrase'=>array('en'=>''),'translation'=>array($langCode=>''));
		}
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['update']) && !empty($formData['val']['phrase']['en'])){
			$_SESSION[__CLASS__][__FUNCTION__]=$formData['val'];
			$translation=array('Source'=>$this->entryTable,'phrase'=>$_SESSION[__CLASS__][__FUNCTION__]['phrase']['en'],'translation'=>$_SESSION[__CLASS__][__FUNCTION__]['translation'][$langCode],'langCode'=>$langCode);
			$translation=$this->arr['SourcePot\Datapool\Foundation\Database']->unifyEntry($translation);
			$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($translation);	
		} else if (!empty($formData['val']['phrase']['en'])){
			$_SESSION[__CLASS__][__FUNCTION__]=$formData['val'];
			$elementId=md5($formData['val']['phrase']['en'].'|'.$langCode);
			$translation=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById(array('Source'=>$this->entryTable,'EntryId'=>$elementId));
			if (empty($translation)){
				$_SESSION[__CLASS__][__FUNCTION__]['translation'][$langCode]='';
			} else {
				$_SESSION[__CLASS__][__FUNCTION__]['translation'][$langCode]=$translation['Content']['translation'];	
			}
		}
		// compile html
		$matrix=array('Translation'=>array());
		$matrix['Translation']['Label phrase']=array('tag'=>'p','element-content'=>'EN');
		$matrix['Translation']['Phrase']=array('tag'=>'input','type'=>'text','value'=>$_SESSION[__CLASS__][__FUNCTION__]['phrase']['en'],'key'=>array('phrase','en'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$matrix['Translation']['Label translation']=array('tag'=>'p','element-content'=>strtoupper($langCode));
		$matrix['Translation']['Translation']=array('tag'=>'input','type'=>'text','value'=>$_SESSION[__CLASS__][__FUNCTION__]['translation'][$langCode],'key'=>array('translation',$langCode),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>TRUE);
		$matrix['Translation']['Cmd']=array('tag'=>'input','type'=>'submit','value'=>'Set','key'=>array('update'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'','style'=>'margin:0;'));
		return array('html'=>$html,'wrapperSettings'=>array('class'=>'toolbox'));
	}
	
	public function dictToolbox($arr=array()){
		$html=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'h2','class'=>'toolbox','element-content'=>'Dictionary'));
		$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Dictionary','generic',array('Source'=>$this->entryTable),array('method'=>'dictWidget','classWithNamespace'=>__CLASS__),array('style'=>'float:left;clear:both;padding:0 10px;margin:5px;'));
		return $html;
	}
	
	public function registerToolbox(){
		$toolbox=array('Name'=>'Dictionary',
					   'Content'=>array('class'=>__CLASS__,'method'=>'dictToolbox','args'=>array(),'settings'=>array())
					   );
		$toolbox=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights($toolbox,'ALL_CONTENTADMIN_R','ADMIN_R');
		$toolbox=$this->arr['SourcePot\Datapool\Foundation\Toolbox']->registerToolbox(__CLASS__,$toolbox);
		if (empty($_SESSION['page state']['toolbox']) && !empty($toolbox['EntryId'])){$_SESSION['page state']['toolbox']=$toolbox['EntryId'];}
		return $toolbox;
	}

}
?>