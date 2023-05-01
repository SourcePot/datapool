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

class Email{
	
	private $arr;
	
	private $entryTable='';
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 );

	public $definition=array('Type'=>array('@tag'=>'p','@default'=>'settings','@Read'=>'NO_R'),
							 'Content'=>array('Mailbox'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
											  'User'=>array('@tag'=>'input','@type'=>'text','@default'=>'John','@excontainer'=>TRUE),
											  'Password'=>array('@tag'=>'input','@type'=>'password','@default'=>'','@excontainer'=>TRUE),
											  'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'),
											),
							);

	private $msgEntry=array();

	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		$arr['SourcePot\Datapool\Foundation\Definitions']->addDefintion('!'.__CLASS__,$this->definition);
		return $this->arr;
	}

	public function getEntryTable(){
		return $this->entryTable;
	}
	
	public function getEntryTemplate(){
		return $this->entryTemplate;
	}
	
	public function job($vars){
		if (empty($vars['Inboxes'])){
			$selector=array('Class'=>__CLASS__);
			$vars['Inboxes']=array();
			foreach($this->arr['SourcePot\Datapool\Foundation\Filespace']->entryIterator($selector,TRUE,'Read') as $entry){
				$vars['Inboxes'][$entry['EntryId']]=$entry;
			}
		}
		if (!empty($vars['Inboxes'])){
			$inbox=array_shift($vars['Inboxes']);
			$vars['Result']=$this->todaysEmails($inbox);	
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

	public function dataSource($arr,$action='settingsWidget'){
		if ($arr===TRUE){return 'Email inbox';}
		switch($action){
			case 'settingsWidget':
				return $this->getReceiverSettingsWidget($arr);
				break;
			case 'receiverWidget':
				return $this->getReceiverWidget($arr);
				break;
			case 'meta':
				return $this->getReceiverMeta($arr);
				break;
			case 'selector':
				return $this->getReceiverSelector($arr);
				break;
		}
		return $arr;
	}

	private function getReceiverSetting($callingClass){
		$EntryId=preg_replace('/\W/','_','INBOX-'.$callingClass);
		$setting=array('Class'=>__CLASS__,'EntryId'=>$EntryId);
		$setting['Content']=array('Mailbox'=>'{mail.wallenhauer.com:993/imap/ssl/novalidate-cert/user=c@wallenhauer.com}',
								  'User'=>'c@wallenhauer.com',
								  'Password'=>'');
		return $this->arr['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($setting,TRUE);
	}

	private function getReceiverSettingsWidget($arr){
		$arr['html']=(isset($arr['html']))?$arr['html']:'';
		$setting=$this->getReceiverSetting($arr['callingClass']);
		$arr['html'].=$this->arr['SourcePot\Datapool\Foundation\Definitions']->entry2form($setting,FALSE);
		return $arr;
	}
	
	private function getReceiverSelector($arr){
		$setting=$this->getReceiverSetting($arr['callingClass']);
		return array('Source'=>$this->entryTable,'Group'=>$setting['EntryId']);
	}
	
	private function getReceiverMeta($arr){
		//$mbox=imap_open("{mail.wallenhauer.com}INBOX",'c@wallenhauer.com','Hu8wl3PyT62tVV1');
		$meta=array();
		$setting=$this->getReceiverSetting($arr['callingClass']);
		$mbox=@imap_open($setting['Content']['Mailbox'],$setting['Content']['User'],$setting['Content']['Password']);
		imap_errors();
		imap_alerts();
		if (empty($mbox)){
			$meta['Error']=imap_last_error();
		} else {
			$check=imap_mailboxmsginfo($mbox);
			$meta=array('Driver'=>$check->Driver,
						'Mailbox'=>$check->Mailbox,
						'Messages'=>$check->Nmsgs,
						'Recent'=>$check->Recent,
						'Unread'=>$check->Unread,
						'Deleted'=>$check->Deleted,
						'Size'=>$this->arr['SourcePot\Datapool\Tools\MiscTools']->float2str($check->Size),
						);
			imap_close($mbox);
		}
		return $meta;
	}

	private function todaysEmails($setting){
		$this->arr['SourcePot\Datapool\Foundation\Database']->resetStatistic();
		$result=array('Loading'=>$setting['Content']['Mailbox']);
		$mbox=@imap_open($setting['Content']['Mailbox'],$setting['Content']['User'],$setting['Content']['Password']);
		imap_errors();
		imap_alerts();
		if (empty($mbox)){
			$result['Error']=imap_last_error();
		} else {
			$messages=imap_search($mbox,'SINCE "'.date('d-M-Y').'"');
			if ($messages){
				foreach($messages as $mid){
					$entry=$this->getMsg($mbox,$mid);
					$entry['Source']=$this->entryTable;
					$entry['Group']=$setting['EntryId'];
					if (empty($entry['htmlmsg'])){
						$entry['Content']=array('Plain'=>$entry['plainmsg']);
					} else {
						$entry['Content']=array('Html'=>$entry['htmlmsg']);
					}
					$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->unifyEntry($entry,TRUE);
					if (empty($entry['attachments'])){
						$entry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Date'),0);
						$this->arr['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry,TRUE);
					} else {
						foreach($entry['attachments'] as $attName=>$attContent){
							$entry['pathArr']=pathinfo($attName);
							$entry['Name'].=' ('.$attName.')';
							$entry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Group','Folder','Name','Date'),0);
							$entry['fileContent']=$attContent;
							$entry['fileName']=$attName;
							$this->arr['SourcePot\Datapool\Foundation\Filespace']->fileContent2entries($entry,TRUE,TRUE,FALSE);
						} // loop through attachmentzs
					}
				} // loop through messages
			}
			imap_close($mbox);
		}
		return $result;
	}

	private function getMsg($mbox,$mid){
		// input $mbox = IMAP stream, $mid = message id
		// output all the following:
		$this->msgEntry['charset']='';
		$this->msgEntry['htmlmsg']='';
		$this->msgEntry['plainmsg']='';
		$this->msgEntry['attachments']=array();
		// HEADER
		$h=\imap_headerinfo($mbox,$mid);
		$this->msgEntry['Name']=iconv_mime_decode($h->subject,0,'utf-8');
		$mailingDate=new \DateTime('@'.$h->udate);
		$this->msgEntry['Date']=$mailingDate->format('Y-m-d H:i:s');
		$this->msgEntry['Folder']=htmlspecialchars(iconv_mime_decode($h->senderaddress,0,'utf-8'));
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
		if ($p->encoding==4){
			$data=quoted_printable_decode($data);
		} else if ($p->encoding==3){
			$data=base64_decode($data);
		}
		// PARAMETERS
		// get all parameters, like charset, filenames of attachments, etc.
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
		// ATTACHMENT
		// Any part with a filename is an attachment,
		// so an attached text file (type 0) is not mistaken as the message.
		if (!empty($params['filename']) || !empty($params['name'])){
			// filename may be given as 'Filename' or 'Name' or both
			$filename=($params['filename'])?$params['filename']:$params['name'];
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
	
	/******************************************************************************************************************************************
	* DATASINK: Email transmitter 
	*
	* 'callingClass' ... arr-property selects the outbox
	* 
	*/
	
	public function dataSink($arr,$action='settingsWidget'){
		if ($arr===TRUE){return 'Email inbox';}
		switch($action){
			case 'settingsWidget':
				return $this->getTransmitterSettingsWidget($arr);
				break;
			case 'transmitterWidget':
				return $this->getTransmitterWidget($arr);
				break;
			case 'meta':
				return $this->getTransmitterMeta($arr);
				break;
			case 'selector':
				return $this->getTransmitterSelector($arr);
				break;
		}
		return $arr;
	}
	
	private function getTransmitterSetting($callingClass){
		$EntryId=preg_replace('/\W/','_','OUTBOX-'.$callingClass);
		$setting=array('Class'=>__CLASS__,'EntryId'=>$EntryId);
		$setting['Content']=array();
		return $this->arr['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($setting,TRUE);
	}

	private function getTransmitterSettingsWidget($arr){
		$arr['html']=(isset($arr['html']))?$arr['html']:'';
		$arr['html'].='No settings available';
		return $arr;
	}
	
	private function getTransmitterWidget($arr){
		$arr['html']=(isset($arr['html']))?$arr['html']:'';
		$form=array('To'=>array('tag'=>'input','type'=>'email','value'=>'','filter'=>FILTER_SANITIZE_EMAIL),
					'From'=>array('tag'=>'input','type'=>'email','value'=>$this->arr['SourcePot\Datapool\Foundation\User']->userAbtract(FALSE,7),'filter'=>FILTER_SANITIZE_EMAIL),
					'Subject'=>array('tag'=>'input','type'=>'text','value'=>''),
					'Send'=>array('tag'=>'button','value'=>'send','element-content'=>'Send'),
					);
		$callingClass=__CLASS__;
		$callingFunction=md5(__FUNCTION__.$arr['callingClass']);
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing($callingClass,$callingFunction);
		if (isset($formData['cmd']['Send'])){
			foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($arr['selector'],FALSE,'Read') as $EntryId=>$entry){
				$mail['selector']=$entry;
				$mail['selector']['Content']=array_merge($mail['selector']['Content'],$formData['val']);	
				$this->entry2mail($mail);
			}
		}
		$matrix=array();
		foreach($form as $key=>$element){
			$element['key']=$key;
			$element['callingClass']=$callingClass;
			$element['callingFunction']=$callingFunction;
			$matrix[$key]['value']=$element;
		}
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>'Email widget'));
		return $arr;
	}
	
	private function getTransmitterSelector($arr){
		$setting=$this->getTransmitterSetting($arr['callingClass']);
		$selector=array('Source'=>$this->entryTable,'Group'=>$setting['EntryId']);
		$selector['Folder']=(empty($_SESSION['currentUser']))?'ANONYM':$_SESSION['currentUser']['EntryId'];
		return $selector;
	}
	
	private function getTransmitterMeta($arr){
		$rowCountSelector=$this->getTransmitterSelector($arr);
		$meta=array();
		$meta['Sent emails']=$this->arr['SourcePot\Datapool\Foundation\Database']->getRowCount($rowCountSelector);
		return $meta;
	}

	private function entry2mail($mail,$isDebugging=FALSE){
		// This methode converts an entry to an emial address, the $mail-keys are:
		// 'selector' ... selects the entry
		// 'To' ... is the recipients emal address, use array for multiple addressees
		$header=array();
		$pageSettings=$this->arr['SourcePot\Datapool\Foundation\Backbone']->getSettings();
		$mailKeyTypes=array('mail'=>array('To'=>'','Subject'=>$mail['selector']['Name']),
							'header'=>array('From'=>$pageSettings['emailWebmaster'],'Cc'=>FALSE,'Bcc'=>FALSE,'Reply-To'=>FALSE)
							);
		$success=FALSE;
		if (empty($mail['selector'])){
			$logArr=array('msg'=>'No email sent. Could not find the selected entry or no read access for the selected entry','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
			$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog($logArr);	
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
			$flatContent=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($mail['selector']['Content']);
			$msgTextPlain='';
			$msgTextHtml='';
			foreach($flatContent as $flatContentKey=>$flatContentValue){
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
			$message.=chunk_split($msgTextPlain);
			$message.="\r\n--".$textBoundery."\r\n";
			$message.="Content-Type: text/html; charset=UTF-8\n";
			$message.="Content-Transfer-Encoding: quoted-printable\r\n\r\n";
			$message.=chunk_split($msgTextHtml);
			$message.="\r\n\r\n--".$textBoundery."--\r\n";
			// get attched file			
			$mixedBoundery='multipart-'.md5($mail['selector']['EntryId']);
			$file=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($mail['selector']);
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
			unset($mail['selector']);
			$mail['message']=$message;
			// add headers
			$header['MIMI-Version']='1.0';
			$mail['To']=addcslashes(mb_encode_mimeheader($mail['To'],"UTF-8"),'"');
			$mail['Subject']=addcslashes(mb_encode_mimeheader($mail['Subject'],"UTF-8"),'"');
			$header['From']=addcslashes(mb_encode_mimeheader($header['From'],"UTF-8"),'"');
			$success=@mail($mail['To'],$mail['Subject'],$mail['message'],$header);
			if ($success){
				$logArr=array('msg'=>'Email sent...','priority'=>40,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
				$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog($logArr);
			} else {
				$logArr=array('msg'=>'Sending email failed.','priority'=>42,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
				$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog($logArr);
			}
		}
		if ($isDebugging){
			$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file(array('header'=>$header,'mail'=>$mail));
		}
		return $success;
	}
	
	

}
?>