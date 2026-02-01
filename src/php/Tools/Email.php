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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use DirectoryTree\ImapEngine\Mailbox;
use DirectoryTree\ImapEngine\FileMessage;
use Carbon\Carbon;
use Hfig\MAPI\MapiMessageFactory;
use Hfig\MAPI\OLE\Pear\DocumentFactory;

class Email implements \SourcePot\Datapool\Interfaces\Job,\SourcePot\Datapool\Interfaces\Transmitter,\SourcePot\Datapool\Interfaces\Receiver,\SourcePot\Datapool\Interfaces\HomeApp{

    private const SMTP_PORTS=[
        25=>'25 (standard)',
        587=>'587 (secure submission with TLS)',
        465=>'465 (legacy secure SMTPS)',
        2525=>'2525 (alternative when others are blocked)'
    ];
    
    private const IMAP_PORTS=[
        143=>'143 (unencrypted or STARTTLS)',
        993=>'993 (OAuth, SSL)',
    ];

    private const AUTHENTIFICATION_ENCRYPTION=[
        'OAuth'=>'OAuth',
        'STARTTLS'=>'STARTTLS',
        'SSL'=>'SSL',
        'Unencrypted'=>'None',
    ];
    
    private const HTML_TEMPLATE='
        <!DOCTYPE html>
        <html>
        <head>
            <title>{{title}}</title>
            <style>
                *{font-family: -apple-system, system-ui, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\',\'Fira Sans\',Ubuntu,Oxygen,\'Oxygen Sans\',Cantarell,\'Droid Sans\',\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\',\'Lucida Grande\',Helvetica,Arial, sans-serif;}
                h1{font-size:1.255em;}
                h2{font-size:1.125em;}
            </style>
        </head>
        <body>{{html}}</body>
        </html>
    ';

    private $oc;
    
    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];

    private const RECEIVER_DEF=[
        'Type'=>['@tag'=>'p','@default'=>'settings receiver','@Read'=>'NO_R'],
        'Content'=>[
            'EntryId'=>['@tag'=>'p','@default'=>'','@excontainer'=>TRUE],
            'Enabled'=>['@function'=>'select','@options'=>['No','Yes'],'@value'=>0,'@excontainer'=>TRUE],
            'Email account folder'=>['@tag'=>'input','@type'=>'text','@default'=>'','placeholder'=>'e.g. Bills/Energy','title'=>'If empty, the inbox is selcted','@excontainer'=>TRUE],
            'User'=>['@tag'=>'input','@type'=>'text','@default'=>'john@doe.com','@excontainer'=>TRUE],
            'Password'=>['@tag'=>'input','@type'=>'password','@default'=>'','@excontainer'=>TRUE],
            'IMAP server'=>['@tag'=>'input','@type'=>'text','@default'=>'smtp.strato.de','@excontainer'=>TRUE],
            'Security'=>['@function'=>'select','@options'=>self::AUTHENTIFICATION_ENCRYPTION,'@value'=>'STARTTLS','@excontainer'=>TRUE],
            'Validate certificate'=>['@function'=>'select','@options'=>['No','Yes'],'@value'=>0,'@excontainer'=>TRUE],
            'Port'=>['@function'=>'select','@options'=>self::IMAP_PORTS,'@value'=>993,'@excontainer'=>TRUE],
            'Timeout'=>['@tag'=>'input','@type'=>'number','@default'=>30,'@excontainer'=>TRUE],
            'Proxy [array json-encoded]'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
            'Debug'=>['@function'=>'select','@options'=>['No','Yes'],'@value'=>0,'@excontainer'=>TRUE],
            'Save'=>['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'],
            ],
        ];

    private const TRANSMITTER_DEF=[
        'Type'=>['@tag'=>'p','@default'=>'settings transmitter','@Read'=>'NO_R'],
        'Content'=>[
            'Enabled'=>['@function'=>'select','@options'=>['No','Yes'],'@value'=>0,'@excontainer'=>TRUE],
            'Originator'=>['@tag'=>'input','@type'=>'text','@default'=>'Datapool','@excontainer'=>TRUE],
            'SMTP server'=>['@tag'=>'input','@type'=>'text','@default'=>'smtp.strato.de','@excontainer'=>TRUE],
            'Port'=>['@function'=>'select','@options'=>self::SMTP_PORTS,'@value'=>465,'@excontainer'=>TRUE],
            'Authentication method'=>['@function'=>'select','@options'=>['no_authentication'=>'No authentication','normal_password'=>'Normal password','encrypted_password'=>'Entcrypted password','keberos'=>'Keberos/GSSAPI','ntlm'=>'NTLM'],'@excontainer'=>TRUE],
            'User'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
            'Password'=>['@tag'=>'input','@type'=>'password','@default'=>'','@excontainer'=>TRUE],
            'From'=>['@tag'=>'input','@type'=>'email','@title'=>'This field sets the email header "from" address. The mail provider might request this address to be regisztered with the user account.','@default'=>'john@doe.com','@excontainer'=>TRUE],
            'Save'=>['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'],
            ],
        ];

    public function __construct($oc){
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
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion('!'.__CLASS__.'-rec',self::RECEIVER_DEF);
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion('!'.__CLASS__.'-tec',self::TRANSMITTER_DEF);
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
    
    /**
    * Housekeeping method periodically executed by job.php (this script should be called once per minute through a CRON-job)
    * @param    string $vars Initial persistent data space
    * @return   array  Array Updateed persistent data space
    */
    public function job(array $vars):array
    {
        if (empty($vars['Inboxes'])){
            $selector=['Class'=>__CLASS__.'-rec'];
            $vars['Inboxes']=[];
            foreach($this->oc['SourcePot\Datapool\Foundation\Filespace']->entryIterator($selector,TRUE,'Read') as $entry){
                $vars['Inboxes'][$entry['EntryId']]=$entry;
            }
        }
        if (!empty($vars['Inboxes'])){
            $inbox=array_shift($vars['Inboxes']);
            if (isset($inbox['Content']['EntryId'])){
                $vars['Result']=$this->todaysEmails($inbox['Content']['EntryId']);
            }                
            $vars['Inboxes to process']=count($vars['Inboxes']);
        }
        return $vars;
    }
    
    /******************************************************************************************************************************************
    * DATASOURCE: Email receiver
    *
    * 'EntryId' ... arr-property selects the inbox
    * 
    */
    
    public function receive(string $id):array
    {
        $result=$this->todaysEmails($id);
        return $result;
    }
    
    public function receiverPluginHtml(array $arr):string
    {
        $html='';
        // add settings form
        $setting=$this->getReceiverSetting($arr['selector']['EntryId']);
        $settingsHtml=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($setting,FALSE);
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$settingsHtml,'icon'=>'Settings']);
        // add meta data info
        $metaHtml=$this->getReceiverMeta($arr['selector']['EntryId']);
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$metaHtml,'icon'=>'Meta']);
        return $html;
    }
    
    public function receiverSelector(string $id):array
    {
        $Group='INBOX|'.preg_replace('/\W/','_',$id);
        return ['Source'=>$this->entryTable,'Group'=>$Group];
    }

    private function id2entrySelector($id,array $templateSelector=[]):array
    {
        $canvasElement=['Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$id];
        $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElement,TRUE);
        if (isset($canvasElement['Content']['Selector'])){
            $canvasElement['Content']['Selector']['Group']=$canvasElement['Content']['Selector']['Group']?:$templateSelector['Group'];
            $canvasElement['Content']['Selector']['Folder']=$canvasElement['Content']['Selector']['Folder']?:$templateSelector['Folder'];
            $canvasElement['Content']['Selector']['Name']=$canvasElement['Content']['Selector']['Name']?:$templateSelector['Name'];
            return $this->oc['SourcePot\Datapool\Tools\MiscTools']->arrRemoveEmpty($canvasElement['Content']['Selector']);
        } else {
            return [];
        }
    }
    
    private function getReceiverSetting($id)
    {
        $id=preg_replace('/\W/','_',$id);
        $setting=['Class'=>__CLASS__.'-rec','EntryId'=>$id];
        $setting['Content']=[
            'EntryId'=>$id,
            'Enabled'=>0,
            'User'=>'',
            'Password'=>'',
            'IMAP server'=>'imap.strato.de',
            'Security'=>'STARTTLS',
            'Port'=>143,
        ];
        return $this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($setting,TRUE);
    }

    private function receiverSetting2mailboxArr($id):array
    {
        $setting=$this->getReceiverSetting($id);
        $encryptionAuthentication= match($setting['Content']['Security']){
            'OAuth'=>['encryption'=>'ssl','authentication'=>'oauth'],
            'STARTTLS'=>['encryption'=>'starttls','authentication'=>'plain'],
            'SSL'=>['encryption'=>'ssl','authentication'=>'plain'],
            'Unencrypted'=>['encryption'=>NULL,'authentication'=>'plain'],
        };
        $mailboxArr=[
            'Enabled'=>$setting['Content']['Enabled']??0,
            'port'=>$setting['Content']['Port'],
            'host'=>$setting['Content']['IMAP server'],
            'timeout'=>intval($setting['Content']['Timeout']??3),
            'debug'=>boolval($setting['Content']['Debug']??FALSE),
            'username'=>$setting['Content']['User']??'',
            'password'=>$setting['Content']['Password']??'',
            'encryption'=>$encryptionAuthentication['encryption']??'',
            'validate_cert'=>boolval($setting['Content']['Validate certificate'])??TRUE,
            'authentication'=>$encryptionAuthentication['authentication'],
            'proxy'=>json_decode($setting['Content']['Proxy [array json-encoded]']??'[]'),
            'Folder'=>$setting['Content']['Email account folder']??'',
        ];
        return $mailboxArr;
    }
    
    private function getReceiverMeta($id):string
    {
        $mailboxArr=$this->receiverSetting2mailboxArr($id);
        $folderName=$mailboxArr['Folder']?:'INBOX';
        if (empty($mailboxArr['Enabled'])){
            $matrix=['Notice'=>['Value'=>'Check settings, this mailbox is currently disabled']];
        } else {
            $mailbox=new Mailbox($mailboxArr);
            $matrix=[];
            try{
                $folders=$mailbox->folders()->get();
                foreach($folders as $folder){
                    foreach($folder->status() as $key=>$value){
                        $matrix[$folder->name()][$key]=$value;
                    }
                    if (stripos($folder->name(),$folderName)!==FALSE){
                        $matrix[$folder->name()]['trStyle']=['background-color'=>'#ccc'];
                    }
                }
            } catch(\Exception $e){
                $matrix['Error'][]=$e->getMessage();
            }
        }
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Account folder']);
    }

    private function todaysEmails($id)
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'messages'=>0,'messageEntries'=>0,'alerts'=>'','errors'=>''];
        // get the mailbox
        $mailboxArr=$this->receiverSetting2mailboxArr($id);
        if (empty($mailboxArr["Enabled"])){
            $context['notice']='Mailbox is not enabled, please check settings.';
            return $context;
        }
        $mailbox=new Mailbox($mailboxArr);
        if (empty($mailboxArr['Folder'])){
            $folder=$mailbox->inbox();
        } else {
            $folder=$mailbox->folders()->find($mailboxArr['Folder']);
        }
        //create entry template
        $entry=$this->id2entrySelector($id,['Folder'=>$mailboxArr['Folder']?:'INBOX']);
        $entry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','P10D');
        foreach($folder->messages()->since(Carbon::now()->subDays(7))->withHeaders()->withFlags()->withBody()->get() as $message){
            $id=$mailboxArr['host'].$mailboxArr['username'].$message->uid();
            $context=$this->messageObj2entries($entry,$message,$id,$context);
        }
        return $context;
    }

    public function msg2entries(array $entry,string $msg,string $id,array $context):array
    {
        $message=new FileMessage($msg);
        return $this->messageObj2entries($entry,$message,$id,$context);
    }

    public function ole2entries(array $entry,string $oleMsg,string $id,array $context):array
    {
        if (empty($oleMsg)){return $context;}
        $context['messages']++;
        // ole-content -> message object
        $messageFactory = new MapiMessageFactory();
        $documentFactory = new DocumentFactory(); 
        $stream=fopen('data://text/plain;base64,'.base64_encode($oleMsg),'r');
        $ole=$documentFactory->createFromStream($stream);
        $message=$messageFactory->parseMessage($ole);
        // entry base data
        $entry=$this->header2entry($entry,$message->properties()->transport_message_headers??'');
        $entry['Content']['Subject']=$message->properties['subject']??'{Missing subject}';
        $entry['Content']['File content']=$message->getBody();
        $entry['Content']['Message']=strip_tags($entry['Content']['File content']);
        $nameBase=mb_substr($entry['Content']['Subject'],0,200).'... ('.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($id,TRUE);
        // html message
        $context['messageEntries']++;
        $htmlContent=$message->getBodyHTML()??'';
        if (empty($htmlContent)){
            $entry['Name']=$nameBase.') [text/plain]';
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],'0','',FALSE);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
        } else {
            $entry['Name']=$nameBase.') [text/html]';
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],'0','',FALSE);
            $entry['fileName']='message.html';
            $entry['fileContent']=$htmlContent;
            $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileContent2entry($entry);
        }
        // message attachments
        foreach($message->getAttachments() as $attachment){
            $contentIdHash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($attachment->getContentId(),TRUE);
            $entry['Name']=$nameBase.'|'.$contentIdHash.') ['.$attachment->getMimeType().']';
            $entry['Name']=str_replace('{Missing subject}',$entry['fileName'],$entry['Name']);
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],'0','',FALSE);
            $entry['fileName']=$attachment->getFilename()?:($contentIdHash.'.file');
            $entry['fileContent']=$attachment->getData();
            $entry['Params']['File']['MIME-Type']=$attachment->getMimeType();
            $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileContent2entry($entry);
            $context['messageEntries']++;
        }
        return $context;
    }

    private function messageObj2entries(array $entry,$message,$id,$context):array
    {
        if (empty($message)){
            return $context;
        }
        $context['messages']++;
        // html and/or text message
        $rawEmail=$message->__toString();
        $entry=$this->header2entry($entry,substr($rawEmail,0,strpos($rawEmail,"\r\n\r\n")));
        $entry['Content']['Subject']=$message->subject()??'{Missing subject}';
        $entry['Content']['Message']=$message->text()?:strip_tags($htmlContent??'');
        $entry['Content']['File content']=$message->text();
        $nameBase=mb_substr($entry['Content']['Subject'],0,200).'... ('.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($id,TRUE);
        // html entry
        $context['messageEntries']++;
        $htmlContent=$message->html();
        if (empty($htmlContent)){
            $entry['Name']=$nameBase.') [text/plain]';
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],'0','',FALSE);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
        } else {
            $entry['Name']=$nameBase.') [text/html]';
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],'0','',FALSE);
            $entry['fileName']='message.html';
            $entry['fileContent']=$htmlContent;
            $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileContent2entry($entry);
            $context['messageEntries']++;
        }
        // attachment entries
        foreach($message->attachments() as $attachment){
            $contentIdHash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($attachment->contentId(),TRUE);
            $entry['fileName']=$attachment->filename()?:($contentIdHash.'.file');
            $entry['fileContent']=$attachment->contents();
            $entry['Name']=$nameBase.'|'.$contentIdHash.') ['.$attachment->contentType().']';
            $entry['Name']=str_replace('{Missing subject}',$entry['fileName'],$entry['Name']);
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],'0','',FALSE);
            $entry['Params']['File']['MIME-Type']=$attachment->contentType();
            $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileContent2entry($entry);
            $context['messageEntries']++;
        }      
        return $context;
    }

    private function header2entry(array $entry, string $headerStr):array
    {
        $entry['Params']['Email']=[];
        $headerStr=preg_replace('/\r\n([A-Za-z])/','__SPLIT__$1',$headerStr);
        $headerChunks=explode('__SPLIT__',$headerStr);
        foreach($headerChunks as $headerChunk){
            $keyValueDivPos=strpos($headerChunk,': ');
            if ($keyValueDivPos===FALSE){continue;}
            $key=strtolower(substr($headerChunk,0,$keyValueDivPos));
            $value=substr($headerChunk,$keyValueDivPos+2);
            $value=preg_replace('/\r\n\s+/','',$value);
            $values=explode(';',$value);
            foreach($values as $value){
                $value=mb_decode_mimeheader($value);
                // get sub arrays
                preg_match('/([a-zA-Z]+)\=([^?].*)/',$value,$match);
                $subKey=$match[1]??'root';
                $value=$match[2]??$value;
                $entry['Params']['Email'][$key][$subKey]=$value;

            }            
        }
        // add date
        $emailDate=$entry['Params']['Email']['date']['root']??$entry['Params']['Email']['received']['root']??('@'.time());
        $entry['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime($emailDate,'',\SourcePot\Datapool\Root::DB_TIMEZONE);
        return $entry;
    }

    /******************************************************************************************************************************************
    * TRANSMITTER: Email transmitter 
    */
    public function send(string $recipient,array $entry):int
    {
        $sentEntriesCount=0;
        $userEntryTable=$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable();
        // get recipient user entry 
        $recipient=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(['Source'=>$userEntryTable,'EntryId'=>$recipient],TRUE);
        $flatRecipient=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($recipient);
        // get sender user entry 
        $sender=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(['Source'=>$userEntryTable,'EntryId'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()],TRUE);
        $flatSender=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sender);
        //
        $flatUserContentKey=$this->getRelevantFlatUserContentKey();
        if (empty($flatRecipient[$flatUserContentKey])){
            $this->oc['logger']->log('notice','Failed to send email: recipient email address is empty',[]);    
        } else {
            $smtpSettings=$this->getTransmitterSetting(__CLASS__)['Content'];
            if (empty($smtpSettings['Enabled'])){
                $this->oc['logger']->log('notice','Failed to send email: SMTP transmitter is disabled',[]);    
                return $sentEntriesCount;
            }
            $entry['Content']['Subject']=$entry['Content']['Subject']??$entry['Name']??'Entry Name missing...';
            $entry['Content']['To']=$flatRecipient[$flatUserContentKey];
            $entry['Content']['ToName']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($recipient,1);
            if (empty($flatSender[$flatUserContentKey])){
                $entry['Content']['From']=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('emailWebmaster');
                $entry['Content']['FromName']=$smtpSettings['Originator'];
            } else {
                $entry['Content']['From']=$flatSender[$flatUserContentKey];
                $entry['Content']['FromName']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($sender,1);
            }
            $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
            if (is_file($file)){
                $fileContent=file_get_contents($file);
                $entry['file']=($this->oc['SourcePot\Datapool\Foundation\Filespace']->getPrivatTmpDir()).$entry['Params']['File']['Name'];
                file_put_contents($entry['file'],$fileContent);
            }
            // send entry
            $mail = new PHPMailer();
            $mail->isSMTP();
            //Enable SMTP debugging
            $mail->SMTPDebug=SMTP::DEBUG_OFF;       // for production use
            //$mail->SMTPDebug=SMTP::DEBUG_CLIENT;   // client messages;
            //$mail->SMTPDebug=SMTP::DEBUG_SERVER;   // client and server messages;
            
            //Set the hostname of the mail server
            $mail->Host=$smtpSettings['SMTP server'];
            //Use `$mail->Host = gethostbyname('smtp.gmail.com');`
            //if your network does not support SMTP over IPv6,
            //though this may cause issues with TLS

            //Set the SMTP port number:
            // - 465 for SMTP with implicit TLS, a.k.a. RFC8314 SMTPS or
            // - 587 for SMTP+STARTTLS
            $mail->Port=$smtpSettings['Port'];

            //Set the encryption mechanism to use:
            if ($smtpSettings['Port']==465){
                // - SMTPS (implicit TLS on port 465) or
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else if ($smtpSettings['Port']==587){
                // - STARTTLS (explicit TLS on port 587)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {

            }
            $mail->SMTPAuth=TRUE;
            $mail->Username=$smtpSettings['User'];
            $mail->Password=$smtpSettings['Password'];
            // create header and body
            $mail->CharSet='UTF-8';
            $mail->setFrom($smtpSettings['From']??$entry['Content']['From']??$smtpSettings['User'],$entry['Content']['FromName']);
            //$mail->addReplyTo('replyto@example.com', 'First Last');
            $mail->addAddress($entry['Content']['To'],$entry['Content']['ToName']??'');
            $mail->Subject=$entry['Content']['Subject'];
            $html=$text='';
            $flatContent=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry['Content']);
            $skipSections=['From'=>TRUE,'FromName'=>TRUE,'To'=>TRUE,'ToName'=>TRUE,'Subject'=>TRUE,];
            $hideSectionsName=['Message'=>TRUE];
            foreach($flatContent as $section=>$content){
                if ($skipSections[$section]??FALSE){continue;}
                if (empty($hideSectionsName[$section])){
                    $section=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2label($section);
                    $text=htmlspecialchars_decode($section)."\n\r";
                    $html.='<h1>'.$section.'</h1>';
                }
                $text=strip_tags(htmlspecialchars_decode($content))."\n\r";
                $html.='<div class="content">'.$content.'<div>';
            }
            $html=str_replace('{{html}}',$html,self::HTML_TEMPLATE);
            $html=str_replace('{{title}}','',$html);
            $mail->msgHTML($html);
            $mail->AltBody=strip_tags($text);
            // add attachment
            if (isset($entry['file'])){
                $mail->addAttachment($entry['file']);
            }
            if ($mail->send()){
                $sentEntriesCount++;
                $this->oc['logger']->log('info','Message "{Subject}" sent to "{To}"',$entry['Content']); 
            } else {
                $entry['Content']['User']=$smtpSettings['User'];
                $this->oc['logger']->log('notice','Failed to send message "{Subject}" to "{To}" via account "{User}" from "{From}"',$entry['Content']); 
            }
        }
        return $sentEntriesCount;
    }
    
    public function transmitterPluginHtml(array $arr):string
    {
        $html='';
        $availableRecipients=$this->oc['SourcePot\Datapool\Foundation\User']->getUserOptions([],$this->getRelevantFlatUserContentKey());
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $settingsHtml=$this->getTransmitterSettingsWidgetHtml(['callingClass'=>__CLASS__]);
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['icon'=>'Email Settings','html'=>$settingsHtml]);
        }
        // Send message
        $entry=['recipient'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),'Source'=>$this->getEntryTable(),'Group'=>'Test','Folder'=>'Test','Name'=>'Testmail','Content'=>['Subject'=>'Testmail','Message'=>'Ich bin ein Test']];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['send'])){
            $entry=array_replace_recursive($entry,$formData['val']);
            if (isset($availableRecipients[$formData['val']['recipient']])){
                $this->send($formData['val']['recipient'],$entry);
            }
        }
        $selectArr=['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'options'=>$availableRecipients,'key'=>['recipient'],'selected'=>$entry['recipient']];
        $emailMatrix=[];
        $emailMatrix['Recepient']['Value']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $emailMatrix['Subject']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','value'=>$entry['Content']['Subject'],'key'=>['Content','Subject'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
        $emailMatrix['Message']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'textarea','element-content'=>$entry['Content']['Message'],'key'=>['Content','Message'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
        $emailMatrix['']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','type'=>'submit','element-content'=>'Send','key'=>['send'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
        $emailHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$emailMatrix,'caption'=>'Email test','keep-element-content'=>TRUE,'hideHeader'=>TRUE]);
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['icon'=>'Create Email','html'=>$emailHtml]);
        return $html;
    }
    
    public function getRelevantFlatUserContentKey():string
    {
        $flatUserContentKey='Content'.(\SourcePot\Datapool\Root::ONEDIMSEPARATOR).'Contact details'.(\SourcePot\Datapool\Root::ONEDIMSEPARATOR).'Email';
        return $flatUserContentKey;
    }

    private function getTransmitterSetting($callingClass){
        $EntryId=preg_replace('/\W/','_',$callingClass);
        $setting=['Class'=>'!'.__CLASS__.'-tec','EntryId'=>$EntryId];
        $setting['Content']=[];
        $settings=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($setting,TRUE);
        $settings['Content']['Port']=intval($settings['Content']['Port']??465);
        return $settings;
    }

    private function getTransmitterSettingsWidgetHtml($arr):string
    {
        $setting=$this->getTransmitterSetting($arr['callingClass']);
        $html=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($setting,FALSE);
        return $html;
    }
    /******************************************************************************************************************************************
    * HomeApp Interface Implementation
    * 
    */
    
    public function contactForm(array $arr):array
    {
        // get settings
        $currentUser=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $flatCurrentUser=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($currentUser);
        $template=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],['Source','Group','Folder','Name'],'0','',FALSE);
        $template=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($template,TRUE);
        // process form
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['send']) && empty($this->isInvalidForm($formData['val']))){
            $pageTitle=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');
            $email=htmlentities(mb_substr($formData['val']['Email'],0,255));
            $phone=htmlentities(mb_substr($formData['val']['Phone'],0,30));
            $sentBy=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($currentUser,4);
            $message=htmlentities(mb_substr($formData['val']['Message'],0,1000));
            $message=str_replace("\n",'<br/>',$message);
            $content=[];
            $content['Subject']=htmlentities($pageTitle).': '.$this->getSubjectOptions($template['Content'],intval($formData['val']['Subject']));
            $content['Message']=$message;
            $content['Provided email'].=$email;
            $content['Provided phone number'].=$phone;
            $content['Sent by'].=$sentBy;
            $this->send($template['Content']['To'],['Content'=>$content]);
            $this->oc['logger']->log('info','Message sent by "{Email}" via contact form.',$formData['val']);
            $sentMatix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($content);
            $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$sentMatix,'caption'=>'Thank you for your message','hideKeys'=>TRUE,'hideHeader'=>TRUE,'keep-element-content'=>TRUE,'class'=>'contact-form']);
            $refreshBtn=['tag'=>'input','type'=>'submit','value'=>'OK','class'=>'contact-form','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['refresh']];
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($refreshBtn);
            return $arr;
        } else if (!empty($formData['val'])){
            $values=$formData['val'];
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
                $template['Content']=$formData['val'];
                $template=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($template,TRUE);
            }
        }
        // compile html
        $arr['html']=$arr['html']??'';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $availableRecipients=$this->oc['SourcePot\Datapool\Foundation\User']->getUserOptions([],$this->getRelevantFlatUserContentKey());
            $toEl=['options'=>$availableRecipients,'selected'=>$template['Content']['To'],'key'=>['To'],'class'=>'contact-form','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'excontainer'=>TRUE];
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h2','element-content'=>'Recepient (admin setting to be genarally used by this form)','keep-element-content'=>TRUE]);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($toEl);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h2','element-content'=>'Subject options (Please seperate options by a line break)','keep-element-content'=>TRUE]);
            $subjectOptionsEl=['tag'=>'textarea','minlength'=>10,'element-content'=>$template['Content']['subjectOptions']??"Option 1\nOption 2\nOption 3\nOption 4\n",'placeholder'=>'Option 1<br/>Option 2<br/>Option 3<br/>','class'=>'contact-form','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['subjectOptions'],'excontainer'=>TRUE];
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($subjectOptionsEl);
            $saveBtn=['tag'=>'input','type'=>'submit','value'=>'Save','class'=>'contact-form','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['save']];
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($saveBtn);
        }
        if (empty($template['Content']['To'])){
            return $arr;
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h2','element-content'=>'Subject','keep-element-content'=>TRUE]);
        $subjectEl=['options'=>$this->getSubjectOptions($template['Content'],FALSE),'selected'=>intval($values['Subject']),'key'=>['Subject'],'class'=>'contact-form','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'excontainer'=>TRUE];
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($subjectEl);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h2','element-content'=>'Message*','keep-element-content'=>TRUE]);
        $messageEl=['tag'=>'textarea','minlength'=>10,'element-content'=>$values['Message']??'','placeholder'=>'Enter your message here...','class'=>'contact-form','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['Message'],'excontainer'=>TRUE];
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($messageEl);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h2','element-content'=>'Your email address*','keep-element-content'=>TRUE]);
        $currentUserEmail=$values['Email']?:$flatCurrentUser[$this->getRelevantFlatUserContentKey()]??'';
        $emailEl=['tag'=>'input','type'=>'email','minlength'=>6,'value'=>$currentUserEmail,'class'=>'contact-form','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['Email'],'excontainer'=>TRUE];
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($emailEl);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h2','element-content'=>'Your phone number','keep-element-content'=>TRUE]);
        $phoneEl=['tag'=>'input','type'=>'tel','value'=>$values['Phone']??'','placeholder'=>'+49...','class'=>'contact-form','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['Phone'],'excontainer'=>TRUE];
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($phoneEl);
        // button div
        $problemStr=$this->isInvalidForm($values);
        $checkEL=['tag'=>'p','element-content'=>$problemStr,'class'=>'contact-form'];
        $btnDivHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($checkEL);
        if (empty($problemStr)){
            $btnEL=['tag'=>'input','type'=>'submit','value'=>'Send','class'=>'contact-form','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['send']];
        } else {
            $btnEL=['tag'=>'input','type'=>'submit','value'=>'Check','class'=>'contact-form','callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'key'=>['check']];
        }
        $btnDivHtml.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnEL);
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'div','element-content'=>$btnDivHtml,'keep-element-content'=>TRUE,'class'=>'contact-form']);
        return $arr;
    }

    private function isInvalidForm($values):string
    {
        $isInvalid=[];
        if (mb_strlen($values['Message']??'')<10){
            $isInvalid[]=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng('Message is too short');
        }
        $atSignPos=intval(strpos($values['Email']??'','@'));
        $dotPos=intval(strrpos($values['Email']??'','.'));
        if ($atSignPos<1 || $atSignPos>$dotPos-2 || mb_strlen($values['Email'])<$dotPos+3){
            $isInvalid[]=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng('Please provide a valid email address');
        }
        return implode("\n",$isInvalid);
    }

    private function getSubjectOptions($values,$getThisOption=FALSE):array|string
    {
        $options=[];
        $subjectOptions=explode("\n",$values['subjectOptions']?:'Miscellaneous');
        foreach($subjectOptions as $index=>$subjectOption){
            if ($getThisOption!==FALSE && $getThisOption===$index){
                return $subjectOption;
            }
            if (empty($subjectOption)){continue;}
            $options[]=$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng($subjectOption);
        }
        return $options;
    }

    public function getHomeAppWidget(string $name):array
    {
        $element=['element-content'=>'','style'=>[]];
        // contact form
        $selector=['Source'=>$this->getEntryTable(),'Group'=>'Forms','Name'=>__FUNCTION__,'disableAutoRefresh'=>TRUE];
        $element['element-content'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element(['tag'=>'h1','element-content'=>'Get in touch...','keep-element-content'=>TRUE]);
        $element['element-content'].=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Web contact form '.__FUNCTION__,'generic',$selector,['method'=>'contactForm','classWithNamespace'=>__CLASS__,],['style'=>['border'=>'none']]);
        return $element;
    }
    
    public function getHomeAppInfo():string
    {
        $info='This widget provides an email contact form';
        return $info;
    }
}
?>