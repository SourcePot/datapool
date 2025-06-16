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

class Email implements \SourcePot\Datapool\Interfaces\Job,\SourcePot\Datapool\Interfaces\Transmitter,\SourcePot\Datapool\Interfaces\Receiver{
    
    private const SMTP_PORTS=[25=>'25 (standard)',587=>'587 (secure submission with TLS)',465=>'465 (legacy secure SMTPS)',2525=>'2525 (alternative when others are blocked)'];
    
    private const HTML_TEMPLATE='<!DOCTYPE html>
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
    private $entryTemplate=['Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                            ];

    public $receiverDef=['Type'=>['@tag'=>'p','@default'=>'settings receiver','@Read'=>'NO_R'],
                        'Content'=>['EntryId'=>['@tag'=>'p','@default'=>'','@excontainer'=>TRUE],
                                    'Mailbox'=>['@tag'=>'input','@type'=>'text','@default'=>'','placeholder'=>'{imap.gmail.com:993/imap/ssl/novalidate-cert/user=...}','@excontainer'=>TRUE],
                                    'User'=>['@tag'=>'input','@type'=>'text','@default'=>'john@doe.com','@excontainer'=>TRUE],
                                    'Password'=>['@tag'=>'input','@type'=>'password','@default'=>'','@excontainer'=>TRUE],
                                    'Save'=>['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'],
                                    ],
                        ];

    public $transmitterDef=['Type'=>['@tag'=>'p','@default'=>'settings transmitter','@Read'=>'NO_R'],
                            'Content'=>['Originator'=>['@tag'=>'input','@type'=>'text','@default'=>'Datapool','@excontainer'=>TRUE],
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
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion('!'.__CLASS__.'-rec',$this->receiverDef);
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion('!'.__CLASS__.'-tec',$this->transmitterDef);
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
        $meta=$this->getReceiverMeta($arr['selector']['EntryId']);
        $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($meta);
        $metaHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Meta']);   
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['html'=>$metaHtml,'icon'=>'Meta']);
        return $html;
    }
    
    public function receiverSelector(string $id):array
    {
        $Group='INBOX|'.preg_replace('/\W/','_',$id);
        return ['Source'=>$this->entryTable,'Group'=>$Group];
    }

    private function id2entrySelector($id):array
    {
        $canvasElement=['Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$id];
        $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElement,TRUE);
        if (isset($canvasElement['Content']['Selector'])){
            return $this->oc['SourcePot\Datapool\Tools\MiscTools']->arrRemoveEmpty($canvasElement['Content']['Selector']);
        } else {
            return [];
        }
    }
    
    private function getReceiverSetting($id)
    {
        $id=preg_replace('/\W/','_','INBOX-'.$id);
        $setting=['Class'=>__CLASS__.'-rec','EntryId'=>$id];
        $setting['Content']=['EntryId'=>$id,
                            'Mailbox'=>'{imap.gmail.com:993/imap/ssl/novalidate-cert/user=...}',
                            'User'=>'',
                            'Password'=>''];
        return $this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($setting,TRUE);
    }
    
    private function getReceiverMeta($id)
    {
        $meta=[];
        $setting=$this->getReceiverSetting($id);
        $mbox=@imap_open(strval($setting['Content']['Mailbox']),$setting['Content']['User'],$setting['Content']['Password']);
        imap_errors();
        imap_alerts();
        if (empty($mbox)){
            $meta['Error']=imap_last_error();
        } else {
            $status=imap_status($mbox,$setting['Content']['Mailbox'],SA_ALL);
            $meta=['messages'=>$status->messages,
                    'Recent'=>$status->recent,
                    'Unseen'=>$status->unseen,
                    'UIDnext'=>$status->uidnext,
                    'UIDvalidity'=>$status->uidvalidity
                    ]; 
            imap_close($mbox);
        }
        return $meta;
    }

    private function todaysEmails($id)
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'messages'=>0,'emailsAdded'=>0,'alerts'=>'','errors'=>''];
        $entrySelector=$this->id2entrySelector($id);
        $setting=$this->getReceiverSetting($id);
        $context['Mailbox']=$setting['Content']['Mailbox'];
        // initialize mailbox
        if (empty($setting['Content']['Mailbox']) || empty($setting['Content']['User'])){
            $context['Error']='Setting "Mailbox" and/or "User" is empty.';
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" added "{emailsAdded}" Mailbox settings are empty.',$context);    
            return $context;
        }
        $mbox=@imap_open($setting['Content']['Mailbox'],$setting['Content']['User'],$setting['Content']['Password']);
        // error handling and documentation
        $errors=imap_errors();
        if (!empty($errors)){
            $context['errors']=implode(', ',$errors);
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" failed with errors: {errors}',$context);    
        }
        $alerts=imap_alerts();
        if (!empty($alerts)){
            $context['alerts']=implode(', ',$alerts);
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" failed with alerts: {alerts}',$context);    
        }
        // open mailbox
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        if (!empty($mbox)){
            $status=imap_status($mbox,$setting['Content']['Mailbox'],SA_ALL);
            $context['messages']=$status->messages;
            $entry=$entrySelector;
            $entry['Params']['File']['MIME-Type']='message/rfc822';
            $entry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','P10D');
            $messages=imap_search($mbox,'SINCE "'.date('d-M-Y').'"');
            if ($messages){
                foreach($messages as $mid){
                    $msg=\imap_fetchbody($mbox,$mid,"");
                    $statistic=$this->oc['SourcePot\Datapool\Foundation\Filespace']->email2files($msg,$entry);
                    $context['emailsAdded']++;
                }
            }
            imap_close($mbox);
        }
        return $context;
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
        $arr['html']=(isset($arr['html']))?$arr['html']:'';
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->isContentAdmin()){
            $settingsHtml=$this->getTransmitterSettingsWidgetHtml(['callingClass'=>__CLASS__]);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['icon'=>'Email Settings','html'=>$settingsHtml]);
        }
        // Send message
        $entry=['recipient'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),'Source'=>$this->getEntryTable(),'Group'=>'Test','Folder'=>'Test','Name'=>'Testmail','Content'=>['Subject'=>'Testmail','Message'=>'Ich bin ein Test']];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['send'])){
            $entry=array_replace_recursive($entry,$formData['val']);
            $this->send($formData['val']['recipient'],$entry);
        }
        $availableRecipients=$this->oc['SourcePot\Datapool\Foundation\User']->getUserOptions([],$this->getRelevantFlatUserContentKey());
        $selectArr=['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'options'=>$availableRecipients,'key'=>['recipient'],'selected'=>$entry['recipient']];
        $emailMatrix=[];
        $emailMatrix['Recepient']['Value']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $emailMatrix['Subject']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','value'=>$entry['Content']['Subject'],'key'=>['Content','Subject'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
        $emailMatrix['Message']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'textarea','element-content'=>$entry['Content']['Message'],'key'=>['Content','Message'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
        $emailMatrix['']['Value']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','type'=>'submit','element-content'=>'Send','key'=>['send'],'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
        $emailHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$emailMatrix,'caption'=>'Email test','keep-element-content'=>TRUE,'hideHeader'=>TRUE]);
        //
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(['icon'=>'Create Email','html'=>$emailHtml]);
        return $arr['html'];
    }
    
    public function getRelevantFlatUserContentKey():string
    {
        $flatUserContentKey='Content'.(\SourcePot\Datapool\Root::ONEDIMSEPARATOR).'Contact details'.(\SourcePot\Datapool\Root::ONEDIMSEPARATOR).'Email';
        return $flatUserContentKey;
    }

    private function getTransmitterSetting($callingClass){
        $EntryId=preg_replace('/\W/','_','OUTBOX-'.$callingClass);
        $setting=['Class'=>__CLASS__.'-tec','EntryId'=>$EntryId];
        $setting['Content']=['EntryId'=>$EntryId,
                            'Mailbox'=>'{imap.gmail.com:993/imap/ssl/novalidate-cert/user=...}',
                            'User'=>'',
                            'From'=>'',
                            'Password'=>''];
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

}
?>