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
    
    const DEFINITIONEN_TEMPLATE="\n\n##Definitionen\n\nAusschließlich Anagben in deutscher Sprache sind rechtlich verbindlich.<br/>\nSolo los datos en lengua alemana son legalmente vinculantes.<br/>\nOnly information provided in German is legally binding.\n\nWebseite, Webpage, Web page, Webapplikation werden als Synonym für alle Seiten dieses Webauftritts der Internetdomän verwendet.";
    const ATTRIBUTIONS="\n\n##Beiträge von Dritten (Attributions)\n\nDie Webseite nutzt interaktive Karten, Address- und Geodaten von OpenSteetMap, die Angaben zur Copyright und der Lizenz sind unter diesem <a href=\"https://www.openstreetmap.org/copyright\" class=\"textlink\" target=\"_blank\">OpenStreetMap Link</a> verfügbar.\n\nEin ggf. auf diese Webseite vorhandenes Introvideo ist von **Pressmaster** oder **Nino Souza** (im Falle einer Planetenanimation), verfügbar auf <a href=\"https://www.pexels.com\"  class=\"textlink\" target=\"_blank\">Pexels</a>";
    const LIABILITY_TEMPLATE="\n\n##Haftungsausschluss\n\n###Haftung für Inhalte\n\nDie Inhalte unserer Webseiten wurden mit größter Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen. Als Diensteanbieter sind wir gemäß § 7 Abs.1 DDG für eigene Inhalte auf diesen Webseiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 DDG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen. Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.\n\n###Haftung für Links\n\nUnser Angebot enthält Links zu Webseiten Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar. Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen.";
    const COPYRIGHT_TEMPLATE="\n\n##Urheberrecht\n\nDie durch den Webseitenbetreiber erstellten Inhalte und Werke auf dieser Webseite unterliegen dem deutschen Urheberrecht sofern nicht abweichend angegeben. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung urheberrechtlich geschützter Gegenstände bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Schöpfers. Downloads und Kopien dieser Webseite sind nur für den bestimmungsgemäßen Gebrauch dieser Webseite gestattet. Soweit die Inhalte auf dieser Webseite nicht vom Betreiber erstellt wurden, werden die Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.";
    const DATA_PROTECTION_TEMPLATE="\n\n##Datenschutz\n\nDie Nutzung dieser Webseite ohne Registrierung ist mit Ausnahme des Kontaktformulars ohne Angabe personenbezogener Daten möglich. Soweit auf dieser Webseite personenbezogene Daten (beispielsweise Name, Anschrift oder E-Mail-Adressen) erhoben werden, erfolgt dies, soweit möglich, auf freiwilliger Basis. Diese Daten werden ohne Ihre ausdrückliche Zustimmung nicht an Dritte weitergegeben. Generell kann die Datenübertragung in Netzwerken und insbesondere im Internet unter anderem auch die Kommunikation per E-Mail Sicherheitslücken aufweisen. Ein lückenloser Schutz der Daten vor dem Zugriff durch Dritte ist unmöglich. Der Nutzung von im Rahmen der Impressumspflicht veröffentlichten Kontaktdaten durch Dritte zur Übersendung von nicht ausdrücklich angeforderter Werbung und Informationsmaterialien wird hiermit ausdrücklich widersprochen. Die Betreiber dieser Webseite behält sich ausdrücklich rechtliche Schritte im Falle der unverlangten Zusendung von Werbeinformationen vor.";
    const CONTACT_TEMPLATE="<img src=\"./assets/helen.jpg\" class=\"icon\"/>\n\nVorname Nachname<br/>\nStraße Hausnummer<br/>\nPLZ Ort<br/>\nLand<br/><br/>\n\n##Vertreten durch:\n\nVorname Nachname\n\n##Umsatzsteuer-Identifikationsnummer gemäß §27a Umsatzsteuergesetz:\n\nGemäß § 19 Abs. 1 UStG nicht umsatzsteuerpflichtig.\n\n##Wirtschafts-ID:\n\n-\n\n##Aufsichtsbehörde:\n\n-";

    private $oc;
    
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

    public function legalForm(string $name):string
    {
        $selector=['Source'=>$this->getEntryTable(),'Group'=>'legal','Folder'=>'Public','Name'=>$name];
        $selector['md']="\n";
        if ($name==='legal'){
            $selector['md'].="#Angaben gemäß § 5 DDG";
            $selector['md'].=self::DEFINITIONEN_TEMPLATE;
            $selector['md'].=self::ATTRIBUTIONS;
            $selector['md'].=self::LIABILITY_TEMPLATE;
            $selector['md'].=self::COPYRIGHT_TEMPLATE;
            $selector['md'].=self::DATA_PROTECTION_TEMPLATE;
        } else if ($name==='contact'){
            $selector['md'].=self::CONTACT_TEMPLATE;
        } else if ($name==='logo'){
            $selector['md'].="<img src=\"./assets/logo.jpg\" title=\"logo.jpg\" style=\"width:300px;\"/>";
        } else {
            $selector['md'].="[//]: # (Enter your text in Markdown format here)\n\n";
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
        // legal
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Legal','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->legalForm('legal');
        // contact
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Contact','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->legalForm('contact');
        // admin email contact form
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Admin email contact','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'img','src'=>'./assets/email.png','element-content'=>'','keep-element-content'=>TRUE,'style'=>['float'=>'left','clear'=>'both','padding'=>'0.5rem']]);
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget provides a email creation form';
        return $info;
    }

}
?>