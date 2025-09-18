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

class Dictionary implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=[];
    
    private const SOURCE_LNG='en';
    private const LANGUAGE_CODES=['en'=>'English','de'=>'Deutsch','es'=>'Español'];

    private const INIT_DICTIONARY=[
        'de'=>[
            '...repeat'=>'...wiederholen',
            'Account'=>'Konto',
            'Address'=>'Adresse',
            'Add'=>'Hinzufügen',
            'Add comment'=>'Kommentar hinzufügen',
            'Add Folder'=>'Ordner hinzufügen',
            'Add Group'=>'Gruppe hinzufügen',
            'Admin email contact'=>'E-Mail-Kontakt des Administrators',
            'Attachment'=>'Anhang',
            'Attachments'=>'Anhänge',
            'Best regards'=>'Viele Grüße',
            'Calendar'=>'Kalender',
            'Check'=>'Eingaben prüfen',
            'Company'=>'Unternehmen',	
            'Contact'=>'Adresse',
            'Contact details'=>'Kontaktdaten',
            'Content'=>'Inhalt',
            'Cookies and permissions'=>'Cookies und Berechtigungen',
            'Country'=>'Land',
            'Country code'=>'Ländercode',
            'Dear'=>'Hallo',
            'Delete'=>'Löschen',
            'Delete entry'=>'Eintrag löschen',
            'Department'=>'Abteilung',
            'Description'=>'Beschreibung',
            'Edit'=>'Ändern',
            'Edit Folder'=>'Ordner ändern',
            'Edit Group'=>'Gruppe ändern',
            'Email'=>'E-Mail',
            'Enter your message here...'=>'Geben Sie Ihre Nachricht hier ein...',
            'Essential cookies'=>'Unerlässliche Cookies',
            'Explorer'=>'Explorer',
            'FALSE'=>'FALSCH',
            'Family name'=>'Familienname',
            'Fax'=>'Fax',
            'filter'=>'filtern',
            'First name'=>'Vorname',
            'Folder'=>'Ordner',
            'Gender'=>'Geschlecht',
            'Get in touch...'=>'Sprechen Sie uns an...',
            'Get login token'=>'Login anfordern',
            'Group'=>'Gruppe',
            'Home'=>'Start',
            'House number'=>'Hausnummer',
            'If you permit this, this web page may send location or address data embedded in files or entered by you to OpenStreetMap for processing, e.g. to display a location on a map or to specify an address in relation to a location.'=>'Sofern zugelassen, kann die Webanwendung Standort- oder Adressdaten, die in Dateien eingebettet sind oder von Ihnen eingegeben wurden, zur Verarbeitung an OpenStreetMap senden, z. B. um einen Standort auf einer Karte anzuzeigen oder eine Adresse in Bezug auf einen Standort anzugeben.',
            'If you permit this, this web page can collect, temporarily store and process location information provided by your web browser.'=>'Wenn Sie dies zulassen, kann diese Webseite Standortinformationen, die von Ihrem Webbrowser bereitgestellt werden, erfassen, vorübergehend speichern und verarbeiten.',
            'Language'=>'Sprache',
            'Legal'=>'Impressum',
            'Location'=>'Ort',
            'Login'=>'Anmelden',
            'Logout'=>'Abmelden',
            'key'=>'Schlüssel',
            'Key'=>'Schlüssel',
            'Map'=>'Karte',
            'Message*'=>'Nachricht',
            'Misc tools'=>'Werkzeuge',
            'Middle name'=>'Zweiter Vorname',
            'Mobile'=>'Mobilnummer',
            'My reference'=>'Mein Zeichen',
            'My rols'=>'Meine Rollen',
            'My tags'=>'Meine Tags',
            'My user rols'=>'Meine Rollen',
            'Name'=>'Name',
            'New comment'=>'Neuer Kommentar',
            'No'=>'Nein',
            'Off'=>'Aus',
            'On'=>'Ein',
            'Password'=>'Passwort',
            'Permissions'=>'Berechtigungen',
            'Permitted'=>'Erlaubt',
            'Please use your requested one-time link to log into'=>'Bitte benutze den angeforderten Einmal-Link zur Anmeldung bei',
            'Phone'	=>'Telefon',
            'TRUE'=>'WAHR',
            'Register'=>'Registrieren',
            'Requested login link from'=>'Der angefprderte Link von',
            'Save'=>'Speichern',
            'Select entry'=>'Eintrag auswählen',
            'Send'=>'Abschicken',
            'Send login link'=>'Login link anfordern',
            'Settings'=>'Einstellungen',
            'Source'=>'Laufwerk',
            'State'=>'Bundesland',
            'Street'=>'Straße',
            'Subject'=>'Betreff',
            'Switch'=>'Umschalten',
            'The link is valid for 24hrs'=>'Der Link ist gültig für 24h',
            'The "session cookie" and the "dataprotection cookie" are essential for the correct functioning of this web page. The "session cookie" stores your login status based on a session id. The "dataprotection cookie" stores your settings.'=>'Das „Session-Cookie“ und das „Datenschutz-Cookie“ sind für das korrekte Funktionieren dieser Webseite unerlässlich. Das „Session-Cookie“ speichert Ihren Anmeldestatus anhand einer Sitzungs-ID. Das „Datenschutz-Cookie“ speichert Ihre Einstellungen.',
            'Title'=>'Anrede',
            'Town'=>'Stadt',
            'Type'=>'Typ',
            'Update'=>'Aktualisieren',
            'Upload settings'=>'Upload Einstellungen',
            'value'=>'Wert',
            'Value'=>'Wert',
            'Yes'=>'Ja',
            'Your email address*'=>'Ihre E-Mail-Adresse*',
            'Your location data'=>'Ihre Standortdaten',
            'Your phone number'=>'Ihre Telefonnummer',
            'Zip'=>'Postleitzahl',
            ],
        'es'=>[
            '...repeat'=>'...repetir',
            'Address'=>'Dirección',
            'Account'=>'Cuenta',
            'Add'=>'Añadir',
            'Add comment'=>'Añadir comentario',
            'Add Folder'=>'Añadir carpeta',
            'Add Group'=>'Añadir grupo',
            'Admin email contact'=>'E-Mail-Kontakt des Administrators',
            'Attachment'=>'Anexo',
            'Attachments'=>'Anexos',
            'Best regards'=>'Saludos cordiales',
            'Calendar'=>'Calendario',
            'Check'=>'Comprobar entradas',
            'Company'=>'Empresa',
            'Contact'=>'Dirección',
            'Contact details'=>'Datos de contacto',
            'Content'=>'Contenido',
            'Cookies and permissions'=>'Cookies y permisos',
            'Country'=>'País',
            'Country code'=>'Código del país',
            'Dear'=>'Querido',
            'Delete'=>'Borrar',
            'Delete entry'=>'Eliminar entrada',
            'Department'=>'Departamento',	
            'Description'=>'Descripción',
            'Edit'=>'Cambiar',
            'Edit Folder'=>'Cambiar carpeta',
            'Edit Group'=>'Cambiar grupo',
            'Email'=>'Correo electrónico',
            'Enter your message here...'=>'Escriba aquí su mensaje...',
            'Essential cookies'=>'Cookies esenciales',
            'Explorer'=>'Explorador de archivos',
            'FALSE'=>'FALSO',
            'Family name'=>'Apellido',
            'Fax'=>'Fax',
            'filter'=>'filtrar',
            'First name'=>'Nombre',
            'Folder'=>'Carpeta',
            'Gender'=>'Género',
            'Get in touch...'=>'Póngase en contacto con nosotros...',
            'Get login token'=>'Solicitar inicio de sesión',
            'Group'=>'Grupo',
            'Home'=>'Inicio',
            'House number'=>'Número de casa',
            'If you permit this, this web page may send location or address data embedded in files or entered by you to OpenStreetMap for processing, e.g. to display a location on a map or to specify an address in relation to a location.'=>'Si lo ha permitido, la aplicación web puede enviar datos de ubicación o dirección incrustados en archivos o introducidos por usted a OpenStreetMap para su procesamiento, por ejemplo, para mostrar una ubicación en un mapa o para especificar una dirección en relación con una ubicación.',
            'If you permit this, this web page can collect, temporarily store and process location information provided by your web browser.'=>'Si lo permite, esta página web puede recopilar, almacenar temporalmente y procesar la información de ubicación proporcionada por su navegador web.',
            'key'=>'Llave',
            'Key'=>'Llave',
            'Language'=>'Lengua',
            'Legal'=>'Aviso legal',
            'Location'=>'Localización',
            'Login'=>'Entrar',
            'Logout'=>'Salir',
            'Map'=>'Mapa',
            'Message*'=>'Mensaje',
            'Middle name'=>'Segundo nombre',
            'Misc tools'=>'Herramientas',
            'Mobile'=>'Móvil',
            'My reference'=>'Mi referencia',
            'My rols'=>'Mis roles de usuario',
            'My tags'=>'Mis etiquetas',
            'My user rols'=>'Mis roles de usuario',
            'Name'=>'Nombre',
            'New comment'=>'Nuevo comentario',
            'No'=>'No',
            'Off'=>'Apagado',
            'On'=>'Encendido',
            'Password'=>'Contraseña',
            'Permissions'=>'Autorizaciones',
            'Permitted'=>'Permitido',
            'Phone'	=>'Teléfono',
            'Please use your requested one-time link to log into'=>'Por favor, utilice el enlace solicitado para iniciar sesión',
            'Register'=>'Registrar',
            'Requested login link from'=>'Solicitado enlace de inicio de sesión de',
            'Save'=>'Guardar',
            'Select entry'=>'Seleccionar entrada',
            'Send'=>'Enviar',
            'Send login link'=>'Enviar enlace de acceso',
            'Settings'=>'Configuración',
            'Source'=>'Drive',
            'State'=>'Provincia',
            'Street'=>'Calle',	
            'Subject'=>'Asunto',
            'Switch'=>'Cambiar',
            'Town'=>'Población',
            'TRUE'=>'VERDADERO',
            'The link is valid for 24hrs'=>'El enlace es válido durante 24 horas',
            'The "session cookie" and the "dataprotection cookie" are essential for the correct functioning of this web page. The "session cookie" stores your login status based on a session id. The "dataprotection cookie" stores your settings.'=>'La «cookie de sesión» y la «cookie de protección de datos» son esenciales para el correcto funcionamiento de esta página web. La «cookie de sesión» almacena su estado de inicio de sesión basándose en un identificador de sesión. La «cookie de protección de datos» almacena su configuración.',
            'Title'=>'Título',
            'Type'=>'Tipo',
            'Update'=>'Actualizar',
            'Upload settings'=>'Configuración de carga',
            'value'=>'Valor',
            'Value'=>'Valor',
            'Yes'=>'Sí',
            'Your email address*'=>'Su dirección de correo electrónico*',
            'Your location data'=>'Tus datos de ubicación',
            'Your phone number'=>'Su número de teléfono',
            'Zip'=>'Código postal',	
            ],
        ];
    
    private $lngCache=[];
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
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
            return ['Category'=>'Admin','Emoji'=>'&#482;','Label'=>'Dictionary','Read'=>'ADMIN_R','Class'=>__CLASS__];
        } else {
            // get page content
            $html=$this->dictToolbox();
            $settings=['orderBy'=>'Name','isAsc'=>TRUE,'limit'=>20,'hideUpload'=>TRUE];
            $settings['columns']=[['Column'=>'Folder','Filter'=>''],['Column'=>'Name','Filter'=>''],['Column'=>'Content|[]|translation','Filter'=>'']];
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container(__CLASS__.' dictionary','entryList',['Source'=>'dictionary'],$settings,[]);    
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }

    public function getValidLng($lngCode, bool $getLngCode=TRUE):string
    {
        $lngCode=mb_substr(strval($lngCode),0,2);
        $lngCode=mb_strtolower($lngCode);
        if (isset(self::LANGUAGE_CODES[$lngCode])){
            return ($getLngCode)?$lngCode:self::LANGUAGE_CODES[$lngCode];
        } else {
            return 'en';
        }
    }

    public function getLanguageCode():string
    {
        $browserLngCode=substr(trim($_SERVER['HTTP_ACCEPT_LANGUAGE']??'','\'",-.='),0,2);
        $browserLngCode=(isset(self::LANGUAGE_CODES[$browserLngCode]))?$browserLngCode:self::SOURCE_LNG;
        return $this->oc['SourcePot\Datapool\Cookies\Cookies']->getSettingsCookieValue('Interface language')??$browserLngCode;
    }

    private function initDictionaryIfEmpty()
    {
        $added=0;
        $hasEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(['Source'=>$this->entryTable,'Group'=>'Translations from en']);
        if (empty($hasEntry)){
            foreach(self::INIT_DICTIONARY as $lngCode=>$phrases){
                foreach($phrases as $phrase=>$translation){
                    $added++;
                    $this->lng($phrase,$lngCode,$translation);
                }
            }
            $this->oc['logger']->log('notice','Function "{class} &rarr; {function}()" called, init set of "{added}" translations added',['class'=>__CLASS__,'function'=>__FUNCTION__,'added'=>$added]);         
        }
        return $added;
    }
    
    public function lng($phrase,string $lngCode='',string|bool $translation=FALSE,bool $isSystemCall=FALSE)
    {
        $lngCode=(empty($lngCode))?($this->getLanguageCode()):mb_strtolower($lngCode);
        if (!is_string($phrase) || strcmp($lngCode,'en')===0){return $phrase;}
        $phrase=trim($phrase);
        if (strlen($phrase)!==strlen(strip_tags($phrase))){return $phrase;}
        $elementId=md5($phrase.'|'.$lngCode);
        if ($translation===FALSE && isset($this->lngCache[$elementId])){
            // translation request answered by cache
            $phrase=$this->lngCache[$elementId];
        } else if ($translation===FALSE && !isset($this->lngCache[$elementId])){
            // translation request
            $selector=['Source'=>$this->entryTable,'EntryId'=>$elementId];
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($selector);
            if (!empty($entry)){
                $phrase=$entry['Content']['translation'];
            }
            $this->lngCache[$elementId]=$phrase;
        } else {
            // update translation
            $entry=['Source'=>$this->entryTable,'Group'=>'Translations from en','Folder'=>$lngCode,'Name'=>mb_substr($phrase,0,100),'EntryId'=>$elementId,'Read'=>'ALL_R','Write'=>'ADMIN_R','Owner'=>'SYSTEM'];
            $entry['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
            $entry['Content']=['translation'=>$translation];
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,$isSystemCall);
            return $translation;
        }
        return $phrase;
    }
    
    public function lngText(string $text='Dear {{First name}},',array $placeholder=['First name'=>'John']):string
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
            $this->oc['SourcePot\Datapool\Cookies\Cookies']->setSettingsCookie('Interface language',$formData['val']['lngCode']);
        }
        //
        $lngCode=$this->getLanguageCode();
        $selectArr=['options'=>self::LANGUAGE_CODES,'value'=>$lngCode,'key'=>['lngCode'],'title'=>'select page language','hasSelectBtn'=>TRUE,'class'=>'menu','style'=>'float:right;','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        return $html;
    }
    
    public function dictWidget(array $arr=[]):array
    {
        $lngCode=$this->getLanguageCode();
        if (strcmp($lngCode,self::SOURCE_LNG)===0){
            $arr['html']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>'Please select a language different to '.self::LANGUAGE_CODES[self::SOURCE_LNG],'style'=>['font-size'=>'1.2rem','padding'=>'10px','color'=>'#f00']]);
            return $arr;
        }
        // form processing
        if (!isset($_SESSION[__CLASS__][__FUNCTION__]['translation'][$lngCode])){
            $_SESSION[__CLASS__][__FUNCTION__]=['phrase'=>['en'=>''],'translation'=>[$lngCode=>'']];
        }
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['update']) && !empty($formData['val']['phrase']['en'])){
            $_SESSION[__CLASS__][__FUNCTION__]=$formData['val'];
            $translation=$this->lng($_SESSION[__CLASS__][__FUNCTION__]['phrase']['en'],$lngCode,$_SESSION[__CLASS__][__FUNCTION__]['translation'][$lngCode],FALSE);
        } else if (!empty($formData['val']['phrase']['en'])){
            $_SESSION[__CLASS__][__FUNCTION__]=$formData['val'];
            $translation=$this->lng($formData['val']['phrase']['en'],$lngCode);
            if (empty($translation)){
                $_SESSION[__CLASS__][__FUNCTION__]['translation'][$lngCode]='';
            } else {
                $_SESSION[__CLASS__][__FUNCTION__]['translation'][$lngCode]=$translation;    
            }
        }
        // compile html
        $matrix=['Translation'=>[]];
        $matrix['Translation']['Label phrase']=['tag'=>'p','element-content'=>'EN'];
        $matrix['Translation']['Phrase']=['tag'=>'input','type'=>'text','value'=>$_SESSION[__CLASS__][__FUNCTION__]['phrase']['en'],'key'=>['phrase','en'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $matrix['Translation']['Label translation']=['tag'=>'p','element-content'=>strtoupper($lngCode)];
        $matrix['Translation']['Translation']=['tag'=>'input','type'=>'text','value'=>$_SESSION[__CLASS__][__FUNCTION__]['translation'][$lngCode],'key'=>['translation',$lngCode],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>TRUE];
        $matrix['Translation']['Cmd']=['tag'=>'input','type'=>'submit','value'=>'Set','key'=>['update'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Translation']);
        return ['html'=>$html,'wrapperSettings'=>[]];
    }
    
    public function dictToolbox(array $arr=[]):string
    {
        $html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Dictionary','generic',['Source'=>$this->entryTable],['method'=>'dictWidget','classWithNamespace'=>__CLASS__],[]);
        return $html;
    }

}
?>