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

class Dictionary{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=[];
    
    private $sourceLng='en';
    private $lngCodes=array('en'=>'English','de'=>'Deutsch','es'=>'Español');
    
    private $lngCache=[];
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $this->getLanguageCode();
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
        $this->initDictionaryIfEmpty();
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
            return array('Category'=>'Admin','Emoji'=>'&#482;','Label'=>'Dictionary','Read'=>'ADMIN_R','Class'=>__CLASS__);
        } else {
            // get page content
            $html=$this->dictToolbox();
            $settings=array('orderBy'=>'Name','isAsc'=>TRUE,'limit'=>20,'hideUpload'=>TRUE);
            $settings['columns']=array(array('Column'=>'Folder','Filter'=>''),array('Column'=>'Name','Filter'=>''),array('Column'=>'Content|[]|translation','Filter'=>''));
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container(__CLASS__.' dictionary','entryList',array('Source'=>'dictionary'),$settings,[]);    
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }

    public function getValidLng($lngCode, bool $getLngCode=TRUE):string
    {
        $lngCode=mb_substr(strval($lngCode),0,2);
        $lngCode=mb_strtolower($lngCode);
        if (isset($this->lngCodes[$lngCode])){
            return ($getLngCode)?$lngCode:$this->lngCodes[$lngCode];
        } else {
            return 'en';
        }
    }

    public function getLanguageCode():string
    {
        if (empty($_SESSION['page state']['lngCode'])){
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
                $_SESSION['page state']['lngCode']=$this->getValidLng($_SERVER['HTTP_ACCEPT_LANGUAGE'],TRUE);
            } else {
                $_SESSION['page state']['lngCode']='en';
            }
        }
        return $_SESSION['page state']['lngCode'];
    }

    private function initDictionaryIfEmpty()
    {
        $added=0;
        $hasEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(array('Source'=>$this->entryTable,'Group'=>'Translations from en'));
        if (empty($hasEntry)){
            $transl=array('de'=>array('Add'=>'Hinzufügen','Save'=>'Speichern','Update'=>'Aktualisieren','Login'=>'Anmelden','Logout'=>'Abmelden','Calendar'=>'Kalender','TRUE'=>'WAHR','FALSE'=>'FALSCH',
                                      'Register'=>'Registrieren','Send login link'=>'Login link anfordern','Password'=>'Passwort','...repeat'=>'...wiederholen','Delete'=>'Löschen',
                                      'Language'=>'Sprache','Home'=>'Start','Account'=>'Konto','Email'=>'E-Mail',
                                      'Dear'=>'Hallo','Please use your requested one-time link to log into'=>'Bitte benutze den angeforderten Einmal-Link zur Anmeldung bei',
                                      'Requested login link from'=>'Der angefprderte Link von','The link is valid for 24hrs'=>'Der Link ist gültig für 24h','Best regards'=>'Viele Grüße'
                                      ),
                          'es'=>array('Add'=>'Añadir','Save'=>'Guardar','Update'=>'Actualizar','Login'=>'Entrar','Logout'=>'Salir','Calendar'=>'Calendario','TRUE'=>'VERDADERO','FALSE'=>'FALSO',
                                      'Register'=>'Registrar','Send login link'=>'Enviar enlace de acceso','Password'=>'Contraseña','...repeat'=>'...repetir','Delete'=>'Borrar',
                                      'Language'=>'Lengua','Home'=>'Inicio','Account'=>'Cuenta','Email'=>'Correo electrónico',
                                      'Dear'=>'Querido','Please use your requested one-time link to log into'=>'Por favor, utilice el enlace solicitado para iniciar sesión',
                                      'Requested login link from'=>'Solicitado enlace de inicio de sesión de','The link is valid for 24hrs'=>'El enlace es válido durante 24 horas','Best regards'=>'Saludos cordiales'
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
    
    public function unifyEntry(array $entry):array
    {
        if (!isset($entry['phrase']) || !isset($entry['langCode'])){
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" called but required entry-key missing.',array('class'=>__CLASS__,'function'=>__FUNCTION__));         
            return $entry;
        } else {
            $entry['EntryId']=md5($entry['phrase'].'|'.$entry['langCode']);
            $entry['Group']='Translations from en';
            $entry['Folder']=$entry['langCode'];
            $entry['Name']=mb_substr($entry['phrase'],0,100);
            $entry['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
            $entry['Owner']='SYSTEM';
            $entry['Content']=array('translation'=>$entry['translation']);
            $entry['Read']='ALL_R';
            $entry['Write']='ADMIN_R';
        }
        return $entry;
    }
    
    public function lng($phrase,string $langCode='',string|bool$translation=FALSE)
    {
        $langCode=(empty($langCode))?($this->getLanguageCode()):mb_strtolower($langCode);
        if (!is_string($phrase) || strcmp($langCode,'en')===0){return $phrase;}
        if (strlen($phrase)!==strlen(strip_tags($phrase))){return $phrase;}
        $elementId=md5($phrase.'|'.$langCode);
        if ($translation===FALSE && isset($this->lngCache[$elementId])){
            // translation request answered by cache
            $phrase=$this->lngCache[$elementId];
        } else if ($translation===FALSE && !isset($this->lngCache[$elementId])){
            // translation request
            $selector=array('Source'=>$this->entryTable,'EntryId'=>$elementId);
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
            if (!empty($entry)){$phrase=$entry['Content']['translation'];}
            $this->lngCache[$elementId]=$phrase;
        } else {
            // update translation
            $phrase=strip_tags($phrase);
            $phrase=trim($phrase);
            $entry=array('Source'=>$this->entryTable,'phrase'=>$phrase,'translation'=>$translation,'langCode'=>$langCode);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
            return $translation;
        }
        return $phrase;
    }
    
    public function lngText(string $text='Dear {{First name}},',array $placeholder=array('First name'=>'John')):string
    {
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
    
    public function lngSelector():string
    {
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['select'])){
            $_SESSION['page state']['lngCode']=$formData['val']['lngCode'];
        }
        //
        $selectArr=array('options'=>$this->lngCodes,'value'=>$_SESSION['page state']['lngCode'],'key'=>array('lngCode'),'title'=>'select page language','hasSelectBtn'=>TRUE,'class'=>'menu','style'=>'float:right;','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        return $html;
    }
    
    public function dictWidget(array $arr=[]):array
    {
        $langCode=$_SESSION['page state']['lngCode'];
        if (strcmp($langCode,$this->sourceLng)===0){
            $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>'Please select a language different to '.$this->lngCodes[$this->sourceLng],'style'=>array('font-size'=>'1.2rem','padding'=>'10px','color'=>'#f00')));
            return $arr;
        }
        // form processing
        if (!isset($_SESSION[__CLASS__][__FUNCTION__]['translation'][$langCode])){
            $_SESSION[__CLASS__][__FUNCTION__]=array('phrase'=>array('en'=>''),'translation'=>array($langCode=>''));
        }
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['update']) && !empty($formData['val']['phrase']['en'])){
            $_SESSION[__CLASS__][__FUNCTION__]=$formData['val'];
            $translation=array('Source'=>$this->entryTable,'phrase'=>$_SESSION[__CLASS__][__FUNCTION__]['phrase']['en'],'translation'=>$_SESSION[__CLASS__][__FUNCTION__]['translation'][$langCode],'langCode'=>$langCode);
            $translation=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($translation);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($translation);    
        } else if (!empty($formData['val']['phrase']['en'])){
            $_SESSION[__CLASS__][__FUNCTION__]=$formData['val'];
            $elementId=md5($formData['val']['phrase']['en'].'|'.$langCode);
            $translation=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(array('Source'=>$this->entryTable,'EntryId'=>$elementId));
            if (empty($translation)){
                $_SESSION[__CLASS__][__FUNCTION__]['translation'][$langCode]='';
            } else {
                $_SESSION[__CLASS__][__FUNCTION__]['translation'][$langCode]=$translation['Content']['translation'];    
            }
        }
        // compile html
        $matrix=array('Translation'=>[]);
        $matrix['Translation']['Label phrase']=array('tag'=>'p','element-content'=>'EN');
        $matrix['Translation']['Phrase']=array('tag'=>'input','type'=>'text','value'=>$_SESSION[__CLASS__][__FUNCTION__]['phrase']['en'],'key'=>array('phrase','en'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $matrix['Translation']['Label translation']=array('tag'=>'p','element-content'=>strtoupper($langCode));
        $matrix['Translation']['Translation']=array('tag'=>'input','type'=>'text','value'=>$_SESSION[__CLASS__][__FUNCTION__]['translation'][$langCode],'key'=>array('translation',$langCode),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>TRUE);
        $matrix['Translation']['Cmd']=array('tag'=>'input','type'=>'submit','value'=>'Set','key'=>array('update'),'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Translation'));
        return array('html'=>$html,'wrapperSettings'=>[]);
    }
    
    public function dictToolbox(array $arr=[]):string
    {
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Dictionary','generic',array('Source'=>$this->entryTable),array('method'=>'dictWidget','classWithNamespace'=>__CLASS__),[]);
        return $html;
    }

}
?>