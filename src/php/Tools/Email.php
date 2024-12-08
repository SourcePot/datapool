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

class Email implements \SourcePot\Datapool\Interfaces\Transmitter,\SourcePot\Datapool\Interfaces\Receiver{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );

    public $receiverDef=array('Type'=>array('@tag'=>'p','@default'=>'settings receiver','@Read'=>'NO_R'),
                              'Content'=>array('EntryId'=>array('@tag'=>'p','@default'=>'','@excontainer'=>TRUE),
                                               'Mailbox'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                               'User'=>array('@tag'=>'input','@type'=>'text','@default'=>'John','@excontainer'=>TRUE),
                                               'Password'=>array('@tag'=>'input','@type'=>'password','@default'=>'','@excontainer'=>TRUE),
                                               'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'),
                                            ),
                            );

    public $transmitterDef=array('Type'=>array('@tag'=>'p','@default'=>'settings transmitter','@Read'=>'NO_R'),
                              'Content'=>array('Recipient e-mail address'=>array('@tag'=>'input','@type'=>'email','@default'=>'','@excontainer'=>TRUE),
                                               'Subject prefix'=>array('@tag'=>'input','@type'=>'text','@default'=>'John','@excontainer'=>TRUE),
                                               'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'),
                                              ),
                            );

    private $receiverMeta=array();
    
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

    public function getEntryTable(){
        return $this->entryTable;
    }
    
    public function getEntryTemplate(){
        return $this->entryTemplate;
    }
    
    public function job($vars){
        if (empty($vars['Inboxes'])){
            $selector=array('Class'=>__CLASS__.'-rec');
            $vars['Inboxes']=array();
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
        $setting=$this->getReceiverSetting($arr['selector']['EntryId']);
        $html=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($setting,FALSE);
        $meta=$this->getReceiverMeta($arr['selector']['EntryId']);
        $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($meta);
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Meta'));   
        return $html;
    }
    
    public function receiverSelector(string $id):array
    {
        $Group='INBOX|'.preg_replace('/\W/','_',$id);
        return array('Source'=>$this->entryTable,'Group'=>$Group);
    }

    private function id2entrySelector($id):array
    {
        $canvasElement=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$id);
        $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElement,TRUE);
        if (isset($canvasElement['Content']['Selector'])){
            return $this->oc['SourcePot\Datapool\Tools\MiscTools']->arrRemoveEmpty($canvasElement['Content']['Selector']);
        } else {
            return array();
        }
    }
    
    private function getReceiverSetting($id){
        $id=preg_replace('/\W/','_','INBOX-'.$id);
        $setting=array('Class'=>__CLASS__.'-rec','EntryId'=>$id);
        $setting['Content']=array('EntryId'=>$id,
                                  'Mailbox'=>'{}',
                                  'User'=>'',
                                  'Password'=>'');
        return $this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($setting,TRUE);
    }
    
    private function getReceiverMeta($id){
        $meta=array();
        $setting=$this->getReceiverSetting($id);
        $mbox=@imap_open(strval($setting['Content']['Mailbox']),$setting['Content']['User'],$setting['Content']['Password']);
        imap_errors();
        imap_alerts();
        if (empty($mbox)){
            $meta['Error']=imap_last_error();
        } else {
            $status=imap_status($mbox,$setting['Content']['Mailbox'],SA_ALL);
            $meta=array('messages'=>$status->messages,
                        'Recent'=>$status->recent,
                        'Unseen'=>$status->unseen,
                        'UIDnext'=>$status->uidnext,
                        'UIDvalidity'=>$status->uidvalidity
                        ); 
            imap_close($mbox);
        }
        $this->receiverMeta=$meta;
        return $meta;
    }

    private function todaysEmails($id){
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__,'messages'=>0,'emailsAdded'=>0,'emailsSkipped'=>0,'alerts'=>'','errors'=>'');
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
        if (!empty($errors)){
            $context['alerts']=(string)$alerts;
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
    public function send(string $recipient,array $entry):int{
        $sentEntriesCount=0;
        if (empty($entry['Content']['Subject'])){$entry['Content']['Subject']=$entry['Name'];}
        $userEntryTable=$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable();
        $recipient=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(array('Source'=>$userEntryTable,'EntryId'=>$recipient),TRUE);
        $flatRecipient=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($recipient);
        $sender=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(array('Source'=>$userEntryTable,'EntryId'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId()),TRUE);
        $flatSender=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($sender);
        $flatUserContentKey=$this->getRelevantFlatUserContentKey();
        if (empty($flatRecipient[$flatUserContentKey])){
            $this->oc['logger']->log('notice','Failed to send email: recipient email address is empty',array());    
        } else {
            $entry['Content']['To']=$flatRecipient[$flatUserContentKey];
            if (empty($flatSender[$flatUserContentKey])){
                $entry['Content']['From']=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('emailWebmaster');
            } else {
                $entry['Content']['From']=$flatSender[$flatUserContentKey];
            }
            $mail=array('selector'=>$entry);
            $sentEntriesCount+=intval($this->entry2mail($mail));
        }
        return $sentEntriesCount;
    }
    
    public function transmitterPluginHtml(array $arr):string{
        $arr['html']=(isset($arr['html']))?$arr['html']:'';
        $arr['html'].='I am the email plugin...';
        return $arr['html'];
    }
    
    public function getRelevantFlatUserContentKey():string{
        $S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
        $flatUserContentKey='Content'.$S.'Contact details'.$S.'Email';
        return $flatUserContentKey;
    }

    /**
    * This method converts the argument mail to an email and tries to send the email.
    * The argument mail is an array which must contain an entry: arr['selector']=entry 
    * @return boolean
    */
    public function entry2mail($mail,$isDebugging=FALSE){
        // This method converts an entry to an email, the $mail-keys are:
        // 'selector' ... selects the entry
        // 'To' ... is the recipients emal address, use array for multiple addressees
        $header=array();
        $emailWebmaster=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('emailWebmaster');
        $mailKeyTypes=array('mail'=>array('To'=>'','Subject'=>$mail['selector']['Name']),
                            'header'=>array('From'=>$emailWebmaster,'Cc'=>FALSE,'Bcc'=>FALSE,'Reply-To'=>FALSE)
                            );
        $success=FALSE;
        if (empty($mail['selector'])){
            $this->oc['logger']->log('notice','No email sent. Could not find the selected entry or no read access for the selected entry',array());    
        } else {
            // copy email settings from mail[selector][Content] to mail and unset these settings
            foreach($mailKeyTypes as $keyType=>$mailKeys){
                foreach($mailKeys as $mailKey=>$initValue){
                    if (empty($mail[$mailKey])){
                        if (empty($mail['selector']['Content'][$mailKey])){
                            if ($initValue!==FALSE){$$keyType[$mailKey]=$initValue;}
                        } else {
                            $$keyType[$mailKey]=$mail['selector']['Content'][$mailKey];
                        }
                    }
                    if (isset($mail['selector']['Content'][$mailKey])){unset($mail['selector']['Content'][$mailKey]);}
                }
            }
            // get message parts
            $flatContent=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($mail['selector']['Content']);
            $msgTextPlain='';
            $msgTextHtml='';
            foreach($flatContent as $flatContentKey=>$flatContentValue){
                $flatContentKey=strval($flatContentKey);
                $flatContentValue=strval($flatContentValue);
                $flatContentValue=trim($flatContentValue);
                if (mb_strpos($flatContentValue,'{{')===0){
                    continue;
                } else if (mb_strpos($flatContentValue,'<')!==0){
                    $flatContentValue='<p>'.$flatContentValue.'</p>';
                }
                $msgTextPlain=strip_tags($flatContentValue)."\r\n";
                $msgTextHtml.=$flatContentValue;
            }
            // create text part of the message
            $textBoundery='text-'.md5($mail['selector']['EntryId']);
            $message='';
            $msgPrefix="Content-Type: multipart/alternative; boundary=\"".$textBoundery."\"\r\n";
            $message.="\r\n\r\n--".$textBoundery."\r\n";
            $message.="Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            //$message.=chunk_split($msgTextPlain);
            $message.=$msgTextPlain;
            $message.="\r\n--".$textBoundery."\r\n";
            $message.="Content-Type: text/html; charset=UTF-8\n";
            $message.="Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            //$message.=chunk_split($msgTextHtml);
            $message.=$msgTextHtml;
            $message.="\r\n\r\n--".$textBoundery."--\r\n";
            // get attched file            
            $mixedBoundery='multipart-'.md5($mail['selector']['EntryId']);
            $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($mail['selector']);
            if (is_file($file)){
                $msgPrefix='--'.$mixedBoundery."\r\n".$msgPrefix;
                // get file content
                $msgFile=file_get_contents($file);
                $msgFile=base64_encode($msgFile);
                // attach to message
                $message.="\r\n\r\n--".$mixedBoundery."\r\n";
                $message.="Content-Type: ".mime_content_type($file)."; name=\"".$mail['selector']['Params']['File']['Name']."\"\n";
                $message.="Content-Transfer-Encoding: base64\n";
                $message.="Content-Disposition: attachment; filename=\"".$mail['selector']['Params']['File']['Name']."\"\r\n\r\n";
                $message.=chunk_split($msgFile);
                $message.="\r\n\r\n--".$mixedBoundery."--\r\n";
                $message=$msgPrefix.$message;
                $header['Content-Type']="multipart/mixed; boundary=\"".$mixedBoundery."\"";
            } else {
                $header['Content-Type']="multipart/alternative; boundary=\"".$textBoundery."\"";
            }
            $mail['message']=$message;
            // add headers
            $header['MIMI-Version']='1.0';
            $mail['To']=addcslashes(mb_encode_mimeheader($mail['To'],"UTF-8"),'"');
            $mail['Subject']=addcslashes(mb_encode_mimeheader($mail['Subject'],"UTF-8"),'"');
            $header['From']=addcslashes(mb_encode_mimeheader($header['From'],"UTF-8"),'"');
            $success=@mail($mail['To'],$mail['Subject'],$mail['message'],$header);
            if ($success){
                $this->oc['logger']->log('info','Email sent to {to}',array('to'=>$mail['To']));    
            } else {
                $this->oc['logger']->log('warning','Sending email to {to} failed.',array('to'=>$mail['To']));    
            }
            // save message
            $entry=array('Source'=>$this->entryTable,'Group'=>'OUTBOX','Folder'=>$mail['To'],'Name'=>$mail['Subject'],'Date'=>'{{nowDateUTC}}');
            $entry['Group'].=($success)?' success':' failure';
            $entry['Content']=array('Sending'=>($success)?'success':'failed','Html'=>$msgTextHtml,'Plain'=>$msgTextPlain);
            $entry=$this->email2file($entry,array('header'=>$header,'To'=>$mail['To'],'Subject'=>$mail['Subject'],'message'=>$message));
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
        }
        if ($isDebugging){
            unset($mail['selector']);
            $debugArr=array('header'=>$header,'mail'=>$mail);
            if (isset($entry)){$debugArr['entry']=$entry;}
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        return $success;
    }
    
    private function email2file($entry,$mailArr){
        $partOrder=array('header'=>TRUE,'From'=>TRUE,'To'=>TRUE,'Date'=>date('r'),'Subject'=>TRUE,'message'=>TRUE);
        $fileContent='';
        foreach($partOrder as $part=>$initValue){
            if (!isset($mailArr[$part])){$mailArr[$part]=$initValue;}
            if (is_array($mailArr[$part])){
                foreach($mailArr[$part] as $key=>$value){
                    $fileContent.=$key.': '.$value."\r\n";
                }
            } else {
                if (strcmp($part,'message')===0){
                    $fileContent.="\r\n".$mailArr[$part];
                } else {
                    $fileContent.=$part.': '.$mailArr[$part]."\r\n";
                }
            }
        }
        $entry['Name']=$this->iconvMimeDecode($mailArr['Subject']);
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name'),0);
        $fileName=date('Y-m-d').' '.preg_replace('/\W/','_',$entry['Name']).'.eml';
        $pathArr=pathinfo($fileName);
        $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
        file_put_contents($file,$fileContent);
        $entry['Params']['File']['MIME-Type']='application/octet-stream';
        $entry['Params']['File']['Size']=filesize($file);
        $entry['Params']['File']['Name']=$pathArr['basename'];
        $entry['Params']['File']['Extension']=$pathArr['extension'];
        $entry['Params']['File']['Date (created)']=date('Y-m-d');
        $entry['Expires']=date('Y-m-d',time()+31536000);
        return $entry;
    }
    
    private function iconvMimeDecode($str,$mode=0,$encoding=null){
        if (empty($str)){
            return '';
        } else {
            $result=iconv_mime_decode($str,$mode,$encoding);
            if ($result===FALSE){
                return '?';
            } else {
                return $result;
            }
        }
    }

}
?>