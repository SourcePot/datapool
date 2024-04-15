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
    private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );

    public $receiverDef=array('Type'=>array('@tag'=>'p','@default'=>'settings receiver','@Read'=>'NO_R'),
                              'Content'=>array('callingClass'=>array('@tag'=>'p','@default'=>'','@excontainer'=>TRUE),
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

    private $msgEntry=array('Expires'=>\SourcePot\Datapool\Root::NULL_DATE,'Owner'=>'SYSTEM');
    
    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=strtolower(trim($table,'\\'));
    }
    
    public function init($oc){
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
        $oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion('!'.__CLASS__.'-rec',$this->receiverDef);
        $oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion('!'.__CLASS__.'-tec',$this->transmitterDef);
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
            if (isset($inbox['Content']['callingClass'])){
                $vars['Result']=$this->todaysEmails($inbox['Content']['callingClass']);
            }                
            $vars['Inboxes to process']=count($vars['Inboxes']);
        }
        return $vars;
    }
    
    /******************************************************************************************************************************************
    * DATASOURCE: Email receiver
    *
    * 'callingClass' ... arr-property selects the inbox
    * 
    */
    
    public function receive(string $callingClass):array
    {
        $result=$this->todaysEmails($callingClass);
        return $result;
    }
    
    public function receiverPluginHtml(array $arr):string
    {
        $setting=$this->getReceiverSetting($arr['callingClass']);
        $html=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($setting,FALSE);
        $meta=$this->getReceiverMeta($arr['callingClass']);
        $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($meta);
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Meta'));   
        return $html;
    }
    
    public function receiverSelector(string $callingClass):array
    {
        $Group=preg_replace('/\W/','_','INBOX-'.$callingClass);
        return array('Source'=>$this->entryTable,'Group'=>$Group);
    }    
    
    private function getReceiverSetting($callingClass){
        $EntryId=preg_replace('/\W/','_','INBOX-'.$callingClass);
        $setting=array('Class'=>__CLASS__.'-rec','EntryId'=>$EntryId);
        $setting['Content']=array('callingClass'=>$callingClass,
                                  'Mailbox'=>'{}',
                                  'User'=>'',
                                  'Password'=>'');
        return $this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($setting,TRUE);
    }
    
    private function getReceiverMeta($callingClass){
        //$mbox=imap_open("{mail.wallenhauer.com}INBOX",'c@wallenhauer.com','Hu8wl3PyT62tVV1');
        $meta=array();
        $setting=$this->getReceiverSetting($callingClass);
        $mbox=@imap_open($setting['Content']['Mailbox'],$setting['Content']['User'],$setting['Content']['Password']);
        imap_errors();
        imap_alerts();
        if (empty($mbox)){
            $meta['Error']=imap_last_error();
        } else {
            /*$check=imap_mailboxmsginfo($mbox);
            $meta=array('Driver'=>$check->Driver,
                        'Mailbox'=>$check->Mailbox,
                        'Messages'=>$check->Nmsgs,
                        'Recent'=>$check->Recent,
                        'Unread'=>$check->Unread,
                        'Deleted'=>$check->Deleted,
                        'Size'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($check->Size),
                        );
            */
            $status=imap_status($mbox,$setting['Content']['Mailbox'],SA_ALL);
            $meta=array('messages'=>$status->messages,
                        'Recent'=>$status->recent,
                        'Unseen'=>$status->unseen,
                        'UIDnext'=>$status->uidnext,
                        'UIDvalidity'=>$status->uidvalidity
                        ); 
            imap_close($mbox);
        }
        return $meta;
    }

    private function todaysEmails($callingClass){
        $entrySelector=$this->receiverSelector($callingClass);
        $setting=$this->getReceiverSetting($callingClass);
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        if (empty($setting['Content']['Mailbox']) || empty($setting['Content']['User'])){
            $result=array('Error'=>'Setting "Mailbox" and/or "User" is empty.');
            return $result;
        }
        $result=array('Mailbox'=>$setting['Content']['Mailbox'],'Exceptions'=>'','Messages'=>0);
        $mbox=@imap_open($setting['Content']['Mailbox'],$setting['Content']['User'],$setting['Content']['Password']);
        // error handling and documentation
        $errors=imap_errors();
        $alerts=imap_alerts();
        if ($alerts){$result['Exceptions'].='Alerts: '.implode(', ',$alerts);}
        if ($errors){$result['Exceptions'].=' | Errors: '.implode(', ',$errors);}
        $result['Exceptions']=trim($result['Exceptions'],' |');
        if (!empty($result['Exceptions'])){
            $this->oc['logger']->log('error','Failed to connect to mailbox: {Exceptions}',array('Exceptions'=>$result['Exceptions']));    
        }
        // open mailbox
        if (!empty($mbox)){
            $messages=imap_search($mbox,'SINCE "'.date('d-M-Y').'"');
            if ($messages){
                foreach($messages as $mid){
                    $entry=$this->getMsg($mbox,$mid);
                    $entry=array_replace_recursive($entry,$entrySelector);
                    if (empty($entry['htmlmsg'])){
                        $entry['Content']=array('Plain'=>$entry['plainmsg']);
                    } else {
                        $entry['Content']=array('Html'=>$entry['htmlmsg']);
                    }
                    $contentHash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($entry['Content'],TRUE);
                    $entry['Name'].=' ['.$contentHash.']';
                    $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($entry,TRUE);
                    if (empty($entry['attachments'])){
                        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Date'),0);
                        $this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,TRUE);
                    } else {
                        $entryName=$entry['Name'];
                        foreach($entry['attachments'] as $attName=>$attContent){
                            $attName=imap_utf8($attName);
                            $entry['Name']=$entryName;
                            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Date'),0);
                            $entry['EntryId'].='_'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($attName,TRUE);
                            $entry['Name'].=' ('.$attName.')';
                            $entry['fileContent']=$attContent;
                            $entry['fileName']=$attName;
                            $this->oc['SourcePot\Datapool\Foundation\Filespace']->fileContent2entry($entry,TRUE,TRUE,FALSE);
                        } // loop through attachmentzs
                    }
                } // loop through messages
                $result['Messages']=count($messages);
                $this->oc['logger']->log('info','Messages found in mailbox: {count}',array('count'=>$result['Messages']));    
            }
            imap_close($mbox);
        }
        return $result;
    }

    private function getMsg($mbox,$mid){
        // input $mbox = IMAP stream, $mid = message id
        $headerProps=array('fromaddress'=>'fromaddress','from'=>'from','ccaddress'=>'ccaddress',
                           'cc'=>'cc','bccaddress'=>'bccaddress','bcc'=>'bcc','reply_toaddress'=>'reply_toaddress',
                           'reply_to'=>'reply_to','senderaddress'=>'senderaddress','sender'=>'sender',
                           'return_pathaddress'=>'return_pathaddress','return_path'=>'return_path','date'=>'date',
                           'subject'=>'subject','in_reply_to'=>'in_reply_to','message_id'=>'message_id',
                           'newsgroups'=>'newsgroups','followup_to'=>'followup_to','references'=>'references',
                           'Recent'=>'Recent','Unseen'=>'Unseen','Flagged'=>'Flagged','Answered'=>'Answered',
                           'Deleted'=>'Deleted','Draft'=>'Draft','Msgno'=>'Msgno','MailDate'=>'MailDate',
                           'Size'=>'Size','udate'=>'udate','fetchfrom'=>'fetchfrom','fetchsubject'=>'fetchsubject');
        // output all the following:
        $this->msgEntry['charset']='';
        $this->msgEntry['htmlmsg']='';
        $this->msgEntry['plainmsg']='';
        $this->msgEntry['attachments']=array();
        // HEADER
        $h=\imap_headerinfo($mbox,$mid);
        $this->msgEntry['Name']=$this->iconvMimeDecode($h->subject,0,'utf-8');
        $mailingDate=new \DateTime('@'.$h->udate);
        $this->msgEntry['Date']=$mailingDate->format('Y-m-d H:i:s');
        $this->msgEntry['Folder']=$this->iconvMimeDecode($h->senderaddress,0,'utf-8');
        foreach($headerProps as $key=>$prop){
            if (property_exists($h,$prop)===FALSE){
                $this->msgEntry['Params']['Email'][$key]['value']='?';
            } else if (is_array($h->$prop) || is_numeric($h->$prop)){
                $this->msgEntry['Params']['Email'][$key]['value']=$h->$prop;
            } else {
                $this->msgEntry['Params']['Email'][$key]['value']=$this->iconvMimeDecode($h->$prop,0,'utf-8');
            }
        }
        $s=\imap_fetchstructure($mbox,$mid);
        if (!isset($s->parts)){
            // simple
            $this->getPart($mbox,$mid,$s,0);  // pass 0 as part-number
        } else {  
            // multipart: cycle through each part
            foreach ($s->parts as $partno0=>$p){
                $this->getPart($mbox,$mid,$p,$partno0+1);
            }
        }
        return $this->msgEntry;
    }

    private function getPart($mbox,$mid,$p,$partno){
        // $partno='1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
        // DECODE DATA
        $data=($partno)?
            \imap_fetchbody($mbox,$mid,strval($partno)):  // multipart
            \imap_body($mbox,$mid);  // simple
        
        // Any part may be encoded, even plain text messages, so check everything.
        $data=$this->decodeEmailData($data,strval($p->encoding));
        // PARAMETERS: get all parameters, like charset, filenames of attachments, etc.
        $params=array();
        if ($p->parameters){
            foreach ($p->parameters as $x){
                $params[strtolower($x->attribute)]=$x->value;
            }
        }
        if (isset($p->dparameters)){
            foreach ($p->dparameters as $x){
                $params[strtolower($x->attribute)]=$x->value;
            }
        }
        // ATTACHMENT: any part with a filename is an attachment, so an attached text file (type 0) is not mistaken as the message.
        if (!empty($params['filename']) || !empty($params['name'])){
            // filename may be given as 'Filename' or 'Name' or both
            $filename=(isset($params['filename']))?$params['filename']:$params['name'];
            // filename may be encoded, so see imap_mime_header_decode()
            $this->msgEntry['attachments'][$filename]=$data;  // this is a problem if two files have same name
        }
        // TEXT
        if ($p->type==0 && $data){
            // Messages may be split in different parts because of inline attachments,
            // so append parts together with blank row.
            if (strtolower($p->subtype)=='plain'){
                $this->msgEntry['plainmsg'].=trim($data) ."\n\n";
            } else {
                $this->msgEntry['htmlmsg'].=$data ."<br><br>";
            }
            $this->msgEntry['charset']=$params['charset'];  // assume all parts are same charset
        } else if ($p->type==2 && $data){
            // EMBEDDED MESSAGE
            // Many bounce notifications embed the original message as type 2,
            // but AOL uses type 1 (multipart), which is not handled here.
            // There are no PHP functions to parse embedded messages,
            // so this just appends the raw source to the main message.
            $this->msgEntry['plainmsg'].=$data."\n\n";
        }

        // SUBPART RECURSION
        if (isset($p->parts)){
            foreach ($p->parts as $partno0=>$p2){
               $this-> getPart($mbox,$mid,$p2,$partno.'.'.($partno0+1));  // 1.2, 1.2.1, etc.
            }
        }
    }

    public function emailProperties2arr(string $email,array $template=array(),bool $lowerCaseKeys=TRUE):array
    {
        $email=preg_replace('/(^|\n|r)([^\n\r:]+: )/','|[]|$2',$email);
        $keyValueArr=explode('|[]|',trim($email,"|[]\n\r"));
        foreach($keyValueArr as $line){
            $line=imap_utf8($line);
            $valueStart=strpos($line,':');
            if ($valueStart===FALSE){continue;}
            $keyA=trim(substr($line,0,$valueStart));
            if ($lowerCaseKeys){$keyA=strtolower($keyA);}
            $template[$keyA]=array();
            $line=substr($line,$valueStart+2);
            preg_match_all('/([a-z]+)=([^;]+)/',$line,$matches);
            foreach($matches[0] as $matchIndex=>$match){
                $line=str_replace($match,'',$line);
                $keyB=$matches[1][$matchIndex];
                if ($lowerCaseKeys){$keyB=strtolower($keyB);}
                $template[$keyA][$keyB]=trim($matches[2][$matchIndex],"\"\t\n\r; ");
            }
            $line=trim($line,"\"\t\n\r; ");
            if (!empty($line)){
                $template[$keyA]['value']=$line;
            }
        }
        return $template;
    }
    
    public function decodeEmailData(string $content,string $encoding)
    {
        switch($encoding){
            case 'base64':
                $content=base64_decode($content);
                break;
            case '3':
                $content=base64_decode($content);
                break;
            case 'quoted-printable':
                $content=quoted_printable_decode($content);
                break;
            case '4':
                $content=quoted_printable_decode($content);
                break;
            default:
                $content=$content;
        }
        return $content;
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
        $sender=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById(array('Source'=>$userEntryTable,'EntryId'=>$_SESSION['currentUser']['EntryId']),TRUE);
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
        // This methode converts an entry to an emial address, the $mail-keys are:
        // 'selector' ... selects the entry
        // 'To' ... is the recipients emal address, use array for multiple addressees
        $header=array();
        $emailWebmaster=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('emailWebmaster');
        $mailKeyTypes=array('mail'=>array('To'=>'','Subject'=>$mail['selector']['Name']),
                            'header'=>array('From'=>$emailWebmaster,'Cc'=>FALSE,'Bcc'=>FALSE,'Reply-To'=>FALSE)
                            );
        $success=FALSE;
        if (empty($mail['selector'])){
            $logArr=array('msg'=>'No email sent. Could not find the selected entry or no read access for the selected entry','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
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
                if (strpos($flatContentValue,'{{')===0){
                    continue;
                } else if (strpos($flatContentValue,'<')!==0){
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
            $entry=array('Source'=>$this->entryTable,'Group'=>'OUTBOX','Folder'=>$mail['To'],'Name'=>$mail['Subject'],'Date'=>'{{NOW}}');
            $entry['Type']= $entry['Source'].' '.(($success)?'success':'failure');
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
        $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Type'),0);
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