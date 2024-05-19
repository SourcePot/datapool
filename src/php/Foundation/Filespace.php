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

class Filespace{

    private $oc;
    
    private $statistics=array();
    private $toReplace=array();
    
    const ENV_FILE='env.json';

    private $entryTemplate=array('Class'=>array('type'=>'string','value'=>TRUE,'Description'=>'Class selects the folder within the setup dir space'),
                                 'EntryId'=>array('type'=>'string','value'=>TRUE,'Description'=>'This is the unique id'),
                                 'Type'=>array('type'=>'string','value'=>'{{Class}}','Description'=>'This is the data-type of Content'),
                                 'Date'=>array('type'=>'datetime','value'=>'{{NOW}}','Description'=>'This is the entry date and time'),
                                 'Content'=>array('type'=>'json','value'=>array(),'Description'=>'This is the entry Content, the structure of depends on the MIME-type.'),
                                 'Read'=>array('type'=>'int','value'=>FALSE,'Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'int','value'=>FALSE,'Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
                                 'Owner'=>array('type'=>'string','value'=>'SYSTEM','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.')
                                 );

    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $this->resetStatistic();
    }
    
    public function init(array $oc):void
    {
        $this->oc=$oc;
        $this->removeTmpDirs();
        
    }
        
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
    
    public function job(array $vars):array
    {
        if (isset($vars['Error'])){unset($vars['Error']);}
        if (empty($vars['Dirs to process'])){
            $dirs=scandir($GLOBALS['dirs']['filespace']);
            foreach($dirs as $dir){
                if (strcmp($dir,'.')===0 || strcmp($dir,'..')===0){continue;}
                $vars['Dirs to process'][$dir]=array('dir'=>$GLOBALS['dirs']['filespace'].$dir,'table'=>$dir);
            }
        }
        $vars['Last deleted files']=array();
        $vars['Last failed deletions']=array();
        $dir2process=array_shift($vars['Dirs to process']);
        if (empty($dir2process['dir'])){
            $this->oc['logger']->log('error','Failed "{class}->{function}()" due to array key in "dir2process[dir]" missing or empty array',array('class'=>__CLASS__,'function'=>__FUNCTION__));    
            $vars['Error']='Key missing or empty: dir2process[dir]';
        } else {
            $files=scandir($dir2process['dir']);
            foreach($files as $fileName){
                $file=$dir2process['dir'].'/'.$fileName;
                $extensionPos=mb_strpos($fileName,'.file');
                if (empty($extensionPos)){continue;}
                $entryId=mb_substr($fileName,0,$extensionPos);
                $sql="SELECT ".$dir2process['table'].".EntryId FROM `".$dir2process['table']."` WHERE `EntryId` LIKE '".$entryId."';";
                $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql);
                if (empty($stmt->fetchAll())){
                    if (is_file($file)){
                        if (unlink($file)){
                            $this->oc['SourcePot\Datapool\Foundation\Database']->addStatistic('removed',1);
                            $vars['Last deleted files'][]=$file;
                        } else {
                            $this->oc['SourcePot\Datapool\Foundation\Database']->addStatistic('failed',1);
                            $vars['Last failed deletions'][]=$file;
                        }
                    }
                    $this->oc['SourcePot\Datapool\Foundation\Database']->addStatistic('matches',1);
                }
            }
            if (!empty($vars['Last deleted files']) || !empty($vars['Last failed deletions'])){
                $context=array('table'=>$dir2process['table'],'deleted'=>count($vars['Last deleted files']),'failed'=>count($vars['Last failed deletions']));
                $this->oc['logger']->log('error','Files without corresponding entries found in "{table}", deleted="{deleted}" failed="{failed}"',$context);         
                $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'Deleted unlinked files',$context['deleted'],'int'); 
            }
            $vars['Last processed dir']=$dir2process['dir'];
        }
        return $vars;
    }

    public function resetStatistic():array
    {
        $this->statistics=array('matched files'=>0,'updated files'=>0,'deleted files'=>0,'deleted dirs'=>0,'inserted files'=>0,'added dirs'=>0);
        return $this->statistics;
    }
    
    public function getStatistic():array
    {
        return $this->statistics;
    }
    
    private function class2dir(string $class,bool $mkDirIfMissing=FALSE):string
    {
        $classComps=explode('\\',$class);
        $class=array_pop($classComps);
        $dir=$GLOBALS['dirs']['setup'].$class.'/';
        if (!is_dir($dir) && $mkDirIfMissing){
            mkdir($dir,0750,TRUE);
        }
        return $dir;    
    }

    private function source2dir(string $sourceFile,bool $mkDirIfMissing=TRUE):string
    {
        // This function returns the filespace directory based on the tablename provided.
        $sourceFile=explode('\\',$sourceFile);
        $sourceFile=array_pop($sourceFile);
        $dir=$GLOBALS['dirs']['filespace'].$sourceFile.'/';
        if (!is_dir($dir) && $mkDirIfMissing){
            mkdir($dir,0750,TRUE);
        }
        return $dir;    
    }
    
    public function selector2file(array $selector,bool $mkDirIfMissing=TRUE):string
    {
        if (!empty($selector['Source']) && !empty($selector['EntryId'])){
            $dir=$this->source2dir($selector['Source'],$mkDirIfMissing);    
            $file=$selector['EntryId'].'.file';
        } else if (!empty($selector['Class']) && !empty($selector['EntryId'])){
            $dir=$this->class2dir($selector['Class'],$mkDirIfMissing);    
            $file=$selector['EntryId'].'.json';
        } else {
            $this->oc['logger']->log('warning','Mandatory keys missing in selector argument',$selector);   
            $dir='';
            $file='';
        }
        return $fileName=$dir.$file;
    }
    
    private function stdReplacements($str='')
    {
        if (!is_string($str)){return $str;}
        if (isset($this->oc['SourcePot\Datapool\Foundation\Database'])){
            $this->toReplace=$this->oc['SourcePot\Datapool\Foundation\Database']->enrichToReplace($this->toReplace);
        }
        foreach($this->toReplace as $needle=>$replacement){$str=str_replace($needle,$replacement,$str);}
        return $str;
    }
    
    public function unifyEntry(array $entry):array 
    {
        if (!isset($entry['Type'])){
            $classComps=explode('\\',$entry['Class']);
            $entry['Type']=array_pop($classComps);
        }
        // remove all keys from entry, not provided by entryTemplate
        foreach($entry as $key=>$value){
            $toReplaceKey='{{'.$key.'}}';
            if (is_string($value)){
                $this->toReplace[$toReplaceKey]=$value;
            } else {
                $this->toReplace[$toReplaceKey]='';
            }
            if (!isset($this->entryTemplate[$key])){unset($entry[$key]);}
        }
        // add defaults at missing keys
        foreach($this->entryTemplate as $key=>$defArr){
            if (!isset($entry[$key])){$entry[$key]=$defArr['value'];}
            $entry[$key]=$this->stdReplacements($entry[$key]);
        }
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ADMIN_R','ADMIN_R');
        return $entry;
    }
    
    public function entryIterator(array $selector,bool $isSystemCall=FALSE,string $rightType='Read'):\Generator
    {
        $dir=$this->class2dir($selector['Class']);
        if (!is_dir($dir)){
            if (isset($this->oc['logger'])){
                $context=array('class'=>__CLASS__,'function'=>__FUNCTION__,'dir'=>$dir);
                $this->oc['logger']->log('error','Function {class}::{function} failed: directory "{dir}" missing',$context);
            }
            return FALSE;
        }
        $files=scandir($dir);
        $selector['rowCount']=count($files)-2;
        foreach($files as $file){
            $suffixPos=strrpos($file,'.json');
            if (empty($suffixPos)){continue;}
            $selector['EntryId']=mb_substr( $file,0,$suffixPos);
            $entry=$this->entryById($selector,$isSystemCall,$rightType);
            yield array_replace_recursive($selector,$entry);
        }
    }

    public function entryById(array $selector,bool $isSystemCall=FALSE,string $rightType='Read',bool $returnMetaOnNoMatch=FALSE):array
    {
        // This method returns the entry from a setup-file selected by the selector arguments.
        // The selector argument is an array which must contain at least the array-keys 'Class' and 'EntryId'.
        //
        $entry=array('rowCount'=>0,'rowIndex'=>0,'access'=>'NO ACCESS RESTRICTION');
        $entry['file']=$this->selector2file($selector);
        $arr=$this->oc['SourcePot\Datapool\Root']->file2arr($entry['file']);
        $entry['rowCount']=intval($arr);
        if (empty($arr)){
            // no entry found
            if (!$returnMetaOnNoMatch){$entry=array();}
        } else {
            // entry found
            $entry['rowCount']=1;
            if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
            $entry['access']=$this->oc['SourcePot\Datapool\Foundation\Access']->access($arr,$rightType,$user,$isSystemCall);
            if ($entry['access']){
                $entry=array_replace_recursive($selector,$entry,$arr);
            } else if (!$returnMetaOnNoMatch){
                $entry=array();
            }
        }
        return $entry;
    }
    
    private function insertEntry(array $entry):array
    {
        if (empty($entry['Class']) || empty($entry['EntryId'])){
            throw new \ErrorException('Function '.__FUNCTION__.': Mandatory keys missing in entry argument, i.e. Class and EntryId',0,E_ERROR,__FILE__,__LINE__);        
        }
        $existingEntry=$this->entryById($entry,TRUE,'Read',TRUE);
        if (empty($existingEntry['rowCount'])){
            // insert entry
            $dir=$this->class2dir($entry['Class'],TRUE);    
            $entry=$this->unifyEntry($entry);
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($entry,$existingEntry['file']);
            $this->statistics['inserted files']++;
            return $entry;
        } else {
            // do nothing if entry exsits
            $this->statistics['matched files']++;
            return array();
        }
    }
    
    public function updateEntry(array $entry,bool $isSystemCall=FALSE,bool $noUpdateCreateIfMissing=FALSE):array
    {
        // This method updates and returns the entry of setup-file selected by the entry arguments.
        // The selector argument is an array which must contain at least the array-keys 'Class' and 'EntryId'.
        // 
        $existingEntry=$this->entryById($entry,TRUE,'Read',TRUE);
        if (empty($existingEntry['rowCount'])){
            // insert entry
            $entry=$this->unifyEntry($entry);
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($entry,$existingEntry['file']);
            $this->statistics['inserted files']++;
        } else if (empty($noUpdateCreateIfMissing)){
            if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($existingEntry,'Write',$user,$isSystemCall)){
                // has access to update entry
                $entry=array_replace_recursive($existingEntry,$entry);
                $entry=$this->unifyEntry($entry);
                $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($entry,$existingEntry['file']);
                $this->statistics['updated files']++;                    
            } else {
                // failed access to update entry
                $entry=array('rowCount'=>1,'rowIndex'=>0,'access'=>FALSE);
                $this->statistics['matched files']++;
            }
        } else {
            // existing entry was not updated
            $entry=$existingEntry;
            $this->statistics['matched files']++;
        }
        return $entry;
    }
    
    public function entryByIdCreateIfMissing(array $entry,bool $isSystemCall=FALSE):array
    {
        return $this->updateEntry($entry,$isSystemCall,TRUE);
    }

    public function entry2fileDownload(array $entry):void
    {
        if (empty($entry['EntryId'])){
            $zipName=date('Y-m-d His').' bulk download.zip';
            $zipFile=$this->getTmpDir().$zipName;
            $zip = new \ZipArchive;
            $zip->open($zipFile,\ZipArchive::CREATE);
            $selector=$entry;
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector) as $entry){
                $file=$this->selector2file($entry);
                if (!is_file($file)){continue;}
                $zip->addFile($file,str_replace('_', '-',basename($entry['Params']['File']['Name'])));
            }
            $zip->close();
            $entry=array('Params'=>array('File'=>array('Extension'=>'zip','Name'=>$zipName)));
            $fileForDownload=$zipFile;
        } else {
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($entry);
            $fileForDownload=$this->selector2file($entry);
        }
        if (is_file($fileForDownload)){
            $fileNamePathInfo=pathinfo($entry['Params']['File']['Name']);
            if (!isset($fileNamePathInfo['extension'])){
                $entry['Params']['File']['Name'].='.'.$entry['Params']['File']['Extension'];
                $this->oc['logger']->log('notice','"{function}" file extension added to filename {fileName}',array('function'=>__FUNCTION__,'fileName'=>$entry['Params']['File']['Name']));
            }
            header('Content-Type: application/'.$entry['Params']['File']['Extension']);
            header('Content-Disposition: attachment; filename="'.$entry['Params']['File']['Name'].'"');
            header('Content-Length: '.fileSize($fileForDownload));
            readfile($fileForDownload);
            exit;
        } else {
            $this->oc['logger']->log('notice','No file found to download.',array());    
        }
    }

    public function getPrivatTmpDir():string
    {
        $ip=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getIP($hashOnly=TRUE);
        $tmpDir=$GLOBALS['dirs']['privat tmp'].$ip.'/';
        if (!is_dir($tmpDir)){
            $this->statistics['added dirs']+=intval(mkdir($tmpDir,0770,TRUE));
        }
        return $tmpDir;
    }
    
    public function getTmpDir():string
    {
        if (!isset($_SESSION[__CLASS__]['tmpDir'])){
            $_SESSION[__CLASS__]['tmpDir']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(20);
            $_SESSION[__CLASS__]['tmpDir'].='/';
        }
        $tmpDir=$GLOBALS['dirs']['tmp'].$_SESSION[__CLASS__]['tmpDir'];
        if (!is_dir($tmpDir)){
            $this->statistics['added dirs']+=intval(mkdir($tmpDir,0775,TRUE));
        }
        return $tmpDir;
    }
    
    public function removeTmpDirs():array
    {
        $tmpDirs=array($GLOBALS['dirs']['tmp']=>28600,$GLOBALS['dirs']['privat tmp']=>30);
        foreach($tmpDirs as $tmpDir=>$maxAge){
            if (is_dir($tmpDir)){
                $allDirs=scandir($tmpDir);
                foreach($allDirs as $dirIndex=>$dir){
                    $fullDir=$tmpDir.$dir;
                    if (!is_dir($fullDir) || strlen($dir)<4){continue;}
                    $age=time()-filemtime($fullDir);
                    if ($age>$maxAge){
                        $this->delDir($fullDir);
                    }
                }
            }
        }
        return $this->statistics;
    }
    
    public function delDir(string $dir):array
    {
        gc_collect_cycles();
        if (is_dir($dir)){
            $files2delete=scandir($dir);
            foreach($files2delete as $fileIndex=>$file){
                $file=$dir.'/'.$file;
                if (is_file($file)){
                    $this->statistics['deleted files']+=intval(unlink($file));
                }    
            }
            $this->statistics['deleted dirs']+=intval(rmdir($dir));
        }
        return $this->statistics;
    }
    
    public function dirSize(array $dir):int
    {
        $size=0;
        if (is_dir($dir)){
            foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS)) as $file){
                $size+=$file->getSize();
            }
        }
        return $size;
    }
    
    public function tryCopy(string $source,string $targetFile,int|bool $rights=FALSE):array
    {
        try{
            $this->statistics['inserted files']+=intval(copy($source,$targetFile));
            if ($rights){chmod($targetFile,$rights);}
        } catch(\Exception $e){
            // Exception handling
        }
        return $this->statistics;
    }

    public function abs2rel(string $file):string
    {
        $rel=str_replace($GLOBALS['dirs']['public'],'./',$file);
        return $rel;
    }

    public function rel2abs(string $file):string
    {
        $abs=str_replace('./',$GLOBALS['dirs']['public'],$file);
        return $abs;
    }

    /**
    * The following methods add files to entries.
    * Method fileUpload2entry() processes a form file upload, fileContent2entry() adds a file to an entry based on the provided file content and
    * method file2entry() just adds a file to an entry. Methods fileUpload2entry() and fileContent2entry() are wrapper methods for file2entry().
    */
    public function fileUpload2entry(array $fileArr,array $entry,bool $createOnlyIfMissing=FALSE,bool $isSystemCall=FALSE,bool $isDebugging=FALSE):array
    {
        $this->resetStatistic();
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__);
        if (empty($fileArr['tmp_name'])){
            $this->oc['logger']->log('warning','Function "{class}::{function}" called with empty fileArr["tmp_name"], skipped this entry',$context);         
            return $entry;
        }
        if (empty($fileArr['name'])){
            $this->oc['logger']->log('warning','Function "{class}::{function}" called with empty fileArr["name"], skipped this entry',$context);         
            return $entry;
        }
        // move uploaded file to private tmp dir
        $entry['Params']['File']['Source']=$fileArr['tmp_name'];
        $tmpDir=$this->getPrivatTmpDir();
        $pathinfo=pathinfo($fileArr['name']);
        $file=$context['file']=$tmpDir.$pathinfo['basename'];
        $success=move_uploaded_file($fileArr['tmp_name'],$file);
        if ($success){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entry,'Processing log',array('msg'=>'File "'.$fileArr['name'].'" upload'),FALSE);
            $entry=$this->file2entry($file,$entry,$createOnlyIfMissing,$isSystemCall);
        } else {
            $this->oc['logger']->log('warning','Function "{class}::{function}" moving uploaded file "{file}" failed, skipped this entry',$context);         
        }
        return $entry;    
    }
    
    public function fileContent2entry(array $entry,bool $createOnlyIfMissing=FALSE,bool $isSystemCall=FALSE,bool $isDebugging=FALSE):array
    {
        $this->resetStatistic();
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__);
        if (empty($entry['fileContent'])){
            $this->oc['logger']->log('warning','Function "{class}::{function}" failed entry["fileContent"] is empty, skipped this entry',$context);             
        } else if (empty($entry['fileName'])){
            $this->oc['logger']->log('warning','Function "{class}::{function}" failed entry["fileName"] is empty, skipped this entry',$context);             
        } else {
            // save file content to tmp dir, e.g. from email
            $tmpDir=$this->getPrivatTmpDir();
            $entry['Params']['File']['Source']=$tmpDir.$entry['fileName'];
            $bytes=file_put_contents($entry['Params']['File']['Source'],$entry['fileContent']);
            if ($bytes===FALSE){
                $context['filename']=$entry['Params']['File']['Source'];
                $this->oc['logger']->log('error','Function "{class}::{function}" failed to create temporary file "{filename}", skipped this entry',$context);                 
            } else {
                $this->file2entry($entry['Params']['File']['Source'],$entry,$createOnlyIfMissing,$isSystemCall);
            }
        }
        return $entry;
    }
    
    public function file2entry(string $file,array $entry,bool $createOnlyIfMissing=FALSE,bool $isSystemCall=FALSE,bool $isDebugging=FALSE):array
    {
        $debugArr=array('file'=>$file,'entry_in'=>$entry,'debugFileName'=>__FUNCTION__.' ');
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__);
        if (empty($entry['Source'])){
            $this->oc['logger']->log('warning','Function "{class}::{function}" called with empty entry["Source"], skipped this entry',$context);         
            return $entry;
        }
        if (!is_file($file)){
            $this->oc['logger']->log('warning','Function "{class}::{function}" called with invalid file handle "{file}", skipped this entry',$context);         
            return $entry;
        }
        $entry=$this->addFileProps($entry,$file);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addEntryDefaults($entry);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($entry);
        if ($this->specialFileHandling($file,$entry,$createOnlyIfMissing,$isSystemCall)){
            // file attachment to entries is done by method specialFileHandling()
            $debugArr['debugFileName'].='specialFileHandling';
        } else {
            $entry=$this->addFile2entry($entry,$file);
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,FALSE,$createOnlyIfMissing);
            $debugArr['debugFileName'].=$entry['Params']['File']['Name'];
        }
        // debugging
        if ($isDebugging){
            $debugArr['entry_out']=$entry;
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr,$debugArr['debugFileName']);
        }
        return $entry;
    }

    private function addFileProps(array $entry,string $file):array
    {
        $currentUser=(empty($_SESSION['currentUser']))?array('EntryId'=>'ANONYM','Name'=>'ANONYM'):$_SESSION['currentUser'];
        $entry['Owner']=(empty($entry['Owner']))?$currentUser['EntryId']:$entry['Owner'];
        if (empty($entry['Params']['File']['UploaderId'])){
            $entry['Params']['File']['UploaderId']=$currentUser['EntryId'];
            $entry['Params']['File']['UploaderName']=$currentUser['Name'];
            $entry['Params']['File']['Uploaded']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
        }
        $pathinfo=pathinfo($file);
        $entry['Params']['File']['Name']=$pathinfo['basename'];
        $entry['Params']['File']['Extension']=$pathinfo['extension'];
        $entry['Name']=(empty($entry['Name']))?$pathinfo['basename']:$entry['Name'];
        $entry['Params']['File']['Size']=filesize($file);
        $entry['Params']['File']['Date (created)']=filectime($file);
        $entry['Params']['File']['MIME-Type']=mime_content_type($file);
        $entry['Params']['File']['Style class']='';
        return $entry;
    }

    private function specialFileHandling(string $file,array $entry,bool $createOnlyIfMissing=FALSE,bool $isSystemCall=FALSE):array|bool
    {
        $debugArr=array('file'=>$file,'cntr'=>array('extractEmails'=>TRUE,'extractArchives'=>TRUE));
        $entry=array_merge($debugArr['cntr'],$entry);
        if ($entry['extractEmails'] && stripos($entry['Params']['File']['MIME-Type'],'/vnd.ms-outlook')!==FALSE){
            $statistic=$this->msg2files($file,$entry,$createOnlyIfMissing,$isSystemCall);
            if (empty($statistic['errors'])){return TRUE;}            
        } else if ($entry['extractEmails'] && stripos($entry['Params']['File']['MIME-Type'],'/rfc822')!==FALSE){
            $this->email2files($file,$entry,$createOnlyIfMissing,$isSystemCall);
            return TRUE;
        } else if ($entry['extractArchives'] && stripos($entry['Params']['File']['MIME-Type'],'/zip')!==FALSE){
            $this->archive2files($file,$entry,$createOnlyIfMissing,$isSystemCall);
            return TRUE;
        }
        return FALSE;
    }
    
    private function msg2files(string $file,array $entry,bool $createOnlyIfMissing,bool $isSystemCall,bool $isDebugging=FALSE):array
    {
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__);
        $emailStatistic=array('errors'=>array(),'parts'=>array(),'files'=>0);
        if (class_exists('\Hfig\MAPI\MapiMessageFactory') && class_exists('\Hfig\MAPI\OLE\Pear\DocumentFactory')){
            // message parsing and file IO are kept separate
            $messageFactory= new \Hfig\MAPI\MapiMessageFactory();
            $documentFactory= new \Hfig\MAPI\OLE\Pear\DocumentFactory(); 
            $ole= $documentFactory->createFromFile($file);
            $message= $messageFactory->parseMessage($ole);
            // set timezone
            $dateTimeObj=$message->getSendTime();
            $timeZoneObj=new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE);
            $dateTimeObj->setTimezone($timeZoneObj);
            // unify entry
            $properties=$message->properties();
            $subject=$context['Name']=$properties->subject;
            $idHash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($message->getInternetMessageId());
            $entry['EntryId']=$idHash;
            $entry['Date']=$dateTimeObj->format('Y-m-d H:i:s');
            $entry['Content']=array();
            try{
                $entry['Content']['Plain']=trim($message->getBody());
            } catch (\Exception $e){
                $context['msg']=$e->getMessage();
                $this->oc['logger']->log('notice','Processing message "{Name}" Plain-part failed with: "{msg}"',$context);    
            }
            try{
                $entry['Content']['Html']=$message->getBodyHtml();
            } catch (\Exception $e){
                $context['msg']=$e->getMessage();
                $this->oc['logger']->log('notice','Processing message "{Name}" Html-part failed with: "{msg}"',$context);    
            }
            try{
                $entry['Content']['RTF']=$message->getBodyRTF();
            } catch (\Exception $e){
                $context['msg']=$e->getMessage();
                $this->oc['logger']->log('notice','Processing message "{Name}" RTF-part failed with: "{msg}"',$context);    
            }
            $entry['Params']['Email']=array('subject'=>array('value'=>$subject),
                                            'fromaddress'=>array('value'=>$message->getSender()),
                                            'from'=>array('value'=>$this->oc['SourcePot\Datapool\Tools\Email']->emailAddressString2arr($message->getSender())),
                                            'delivery-date'=>array('value'=>$dateTimeObj->format(DATE_RFC822)),
                                            'message-id'=>array('value'=>$message->getInternetMessageId()),
                                            );
            foreach($message->getRecipients() as $recipient){
                $entry['Params']['Email'][strtolower($recipient->getType())]['value']=$this->oc['SourcePot\Datapool\Tools\Email']->emailAddressString2arr((string)$recipient);
            }
            if (empty($message->getAttachments())){
                // no attachements: email content -> entry
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entry,'Processing log',array('msg'=>'Email content copied to entry["Content"]["Html"]'),FALSE);
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,FALSE);
            } else {
                // create one entry per attachement and add email content to entry 
                foreach($message->getAttachments() as $index=>$attachment){
                    if ($attachment->getFilename()=='smime.p7m'){
                        $this->oc['logger']->log('notice','Failed to process content of message "{Name}" due to encryption',$context);    
                    }
                    $entry['fileName']=(empty($attachment->getFilename()))?$entry['Name']:$attachment->getFilename();
                    $emailStatistic['files']++;
                    $entry['fileContent']=$attachment->getData();
                    $entry['EntryId']=$idHash.'-'.$index;
                    $entry['Params']['File']=array('Name'=>$entry['fileName'],'Size'=>strlen($entry['fileContent']),'Date (created)'=>date('Y-m-d H:i:s'),'MIME-Type'=>$attachment->getMimeType());
                    $newEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entry,'Processing log',array('msg'=>'Entry created from email attachment "'.$entry['fileName'].'"'),FALSE);
                    $this->fileContent2entry($newEntry);
                }
            }
        } else {
            $context['msg']='\Hfig\MAPI... classes not loaded';
            $emailStatistic['errors'][]=$context['msg'];
            $this->oc['logger']->log('notice','Problem uploading msg-file: "{msg}"',$context);    
        }
        return array('errors'=>$emailStatistic['errors'],'files'=>$emailStatistic['files']);
    }    

    private function email2files(string $file,array $entry,bool $createOnlyIfMissing,bool $isSystemCall,bool $isDebugging=FALSE):array
    {
        $emailStatistic=array('errors'=>array(),'parts'=>array(),'files'=>0);
        $fileContent=@file_get_contents($file);
        if (empty($fileContent)){
            $emailStatistic['errors']='File "'.$entry['Params']['File']['Name'].'" not found or empty';
        } else {
            // get and add email header
            $emailHeader=mb_substr($fileContent,0,mb_strpos($fileContent,"\r\n\r\n"));
            $emailContent=mb_substr($fileContent,mb_strpos($fileContent,"\r\n\r\n"));
            $emailContent=trim($emailContent,"\r\n ");
            $entry['Params']['Email']=$this->oc['SourcePot\Datapool\Tools\Email']->emailProperties2arr($emailHeader,array());
            $entry['EntryId']=md5($fileContent);
            $entry['Name']=$entry['Params']['Email']['subject']['value'].' ['.$entry['EntryId'].']';
            // init zip file
            $zipFile=$this->getPrivatTmpDir().'attachments.zip';
            // scan email parts
            preg_match_all('/boundary="([^"]+)"/',$fileContent,$bounderies);
            if (empty($bounderies[1])){
                if (isset($entry['Params']['Email']['content-transfer-encoding']['value'])){
                    $emailContent=$this->oc['SourcePot\Datapool\Tools\Email']->decodeEmailData($emailContent,$entry['Params']['Email']['content-transfer-encoding']['value']);
                }
                if (!isset($entry['Params']['Email']['content-type']['value'])){
                    $entry['Params']['Email']['content-type']['value']='text';
                }
                $contentType=ucfirst(mb_substr($entry['Params']['Email']['content-type']['value'],mb_strpos($entry['Params']['Email']['content-type']['value'],'/')+1));
                $entry['Content'][$contentType]=$emailContent;
                $emailStatistic['emailContent']=$emailContent;                
            } else {
                foreach($bounderies[1] as $boundery){
                    $boundery='--'.$boundery;
                    $endBoundery=$boundery.'--';
                    $parts=explode($endBoundery,$fileContent);
                    $part=array_shift($parts);
                    $parts=explode($boundery,$part);
                    array_shift($parts);
                    foreach($parts as $partIndex=>$part){
                        $partContentStart=mb_strpos($part,"\r\n\r\n");
                        $partHeader=mb_substr($part,0,$partContentStart);
                        $partContent=mb_substr($part,$partContentStart);
                        $partContent=trim($partContent,"\r\n ");
                        // get header
                        $headerArr=array('content-disposition'=>array('value'=>''),'content-transfer-encoding'=>array('value'=>''));
                        $headerArr=$this->oc['SourcePot\Datapool\Tools\Email']->emailProperties2arr($partHeader,$headerArr);
                        // decode content
                        $partContent=$this->oc['SourcePot\Datapool\Tools\Email']->decodeEmailData($partContent,$headerArr['content-transfer-encoding']['value']);
                        if (strpos($headerArr['content-disposition']['value'],'attachment')===FALSE){
                            $contentType=ucfirst(mb_substr($headerArr['content-type']['value'],mb_strpos($headerArr['content-type']['value'],'/')+1));
                            $entry['Content'][$contentType]=$partContent;
                        } else {
                            if (!isset($zip)){
                                $zip=new \ZipArchive;
                                $zip->open($zipFile,\ZipArchive::CREATE);
                            }
                            $zip->addFromString($headerArr['content-disposition']['filename'],$partContent);
                            $emailStatistic['files']++;
                        }
                        $emailStatistic['parts'][$boundery.'|'.$partIndex]=array('headerArr'=>$headerArr,'partContent'=>$partContent);
                    }
                    $entry['Content']['RTF']='';
                }
            }
            $emailStatistic['emailHeader']=$entry['Params']['Email'];
            unset($entry['Params']['File']);
            if (isset($zip)){
                $zip->close();
                $this->file2entry($zipFile,$entry,$createOnlyIfMissing,$isSystemCall);
            } else {
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entry,'Processing log',array('msg'=>'Email content copied to entry["Content"]["html"]'),FALSE);
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,FALSE,$createOnlyIfMissing);
            }
            $emailStatistic['entry_out']=$entry;
        }
        if ($isDebugging){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($emailStatistic,__FUNCTION__.'-'.hrtime(TRUE));
        }
        if ($emailStatistic['errors']){
            $this->oc['logger']->log('warning',implode('|',$emailStatistic['errors']));    
        }
        return array('errors'=>$emailStatistic['errors'],'files'=>$emailStatistic['files']);
    }
    
    private function archive2files(string $file,array $entry,bool $createOnlyIfMissing,bool $isSystemCall,bool $isDebugging=FALSE):array
    {
        $zipStatistic=array('errors'=>array(),'files'=>array());
        // extract zip archive to a temporary dir
        $zip=new \ZipArchive;
        if ($zip->open($file)===TRUE){
            $i=0;
            $this->oc['logger']->log('info','Processing zip-archive with "{numFiles}" files',array('numFiles'=>$zip->count()));    
            while($fileName=$zip->getNameIndex($i)){
                $entry['fileName']=preg_replace('/[^a-zäüößA-ZÄÜÖ0-9\.]+/','_',$fileName);
                $entry['fileContent']=$zip->getFromIndex($i);
                if (!empty($entry['fileContent'])){
                    $zipStatistic['files'][$i]=$entry['fileName'];
                    $entry['Name']=$entry['fileName'];
                    $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder','Name'),'0','',FALSE);
                    $newEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entry,'Processing log',array('msg'=>'Extracted file "'.$fileName.'" from zip-archive (file '.($i+1).' of '.$zip->count().')'),FALSE);
                    $this->fileContent2entry($newEntry);
                }
                $i++;
            }
            $zip->close();
        } else {
            $zipStatistic['errors'][]='Failed to open zip archive';
        }
        if ($isDebugging){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($zipStatistic);
        }
        return $zipStatistic;
    }
    
    /**
     * Adds meta data to to an entry derived from the file provided.
     *
     * @param array $entry is the entry which meta data is added to
     * @param array $file is file from which the meta data is derived
     *
     * @return $entry is the enriched entry
     */
    public function addFile2entry(array $entry,string $file,bool $isDebugging=FALSE):array
    {
        $debugArr=array('entry_in'=>$entry,'file'=>$file);
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__);
        // process file
        $entry=$this->oc['SourcePot\Datapool\Tools\ExifTools']->addExif2entry($entry,$file);
        $entry=$this->oc['SourcePot\Datapool\Tools\GeoTools']->location2address($entry);
        // if pdf parse content
        if (empty($entry['Params']['File'])){
            // file info is missing
        } else if (stripos($entry['Params']['File']['MIME-Type'],'pdf')!==FALSE){
            if (empty($entry['pdfParser'])){
                $this->oc['logger']->log('notice','File upload, pdf parsing failed: no parser selected',array());    
            } else {
                $parserMethod=$entry['pdfParser'];
                try{
                    $entry=$this->oc['SourcePot\Datapool\Tools\PdfTools']->$parserMethod($file,$entry);
                    $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entry,'Processing log',array('parser applied'=>$parserMethod),FALSE);
                } catch (\Exception $e){
                    $context['msg']=$e->getMessage();
                    $this->oc['logger']->log('notice','Function "{class}::{function}" parser failed: {msg}',$context);
                }
            }
            try{
                $entry=$this->oc['SourcePot\Datapool\Tools\PdfTools']->attachments2arrSmalot($file,$entry);
            } catch (\Exception $e){
                $context['msg']=$e->getMessage();
                $this->oc['logger']->log('notice','Function "{class}::{function}" failed to scan for pdf-attachments: {msg}',$context);
            }    
        } else if (stripos($entry['Params']['File']['Extension'],'csv')!==FALSE){
                $entry['Params']['File']['Spreadsheet']=$this->oc['SourcePot\Datapool\Tools\CSVtools']->csvIterator($file,$entry['Params']['File']['Extension'])->current();
                $entry['Params']['File']['SpreadsheetIteratorClass']='SourcePot\Datapool\Tools\CSVtools';
                $entry['Params']['File']['SpreadsheetIteratorMethod']='csvIterator';
        } else if (stripos($entry['Params']['File']['Extension'],'xls')!==FALSE){
                $entry['Params']['File']['Spreadsheet']=$this->oc['SourcePot\Datapool\Tools\XLStools']->iterator($file,$entry['Params']['File']['Extension'])->current();
                $entry['Params']['File']['SpreadsheetIteratorClass']='SourcePot\Datapool\Tools\XLStools';
                $entry['Params']['File']['SpreadsheetIteratorMethod']='iterator';
        }
        // add file to entry
        $targetFile=$this->selector2file($entry,TRUE);
        if (copy($file,$targetFile)){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entry,'Attachment log',array('File source'=>$file,'File attached'=>$targetFile),FALSE);    
        }
        if ($isDebugging){
            $debugArr['entry_out']=$entry;
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
        return $entry;
    }

    public function exportEntries(array $selectors,bool $isSystemCall=FALSE,int $maxAttachedFilesize=10000000000):string
    {
        $statistics=array('added entries'=>0,'added files'=>0,'Attached filesize'=>0,'tables'=>array(),'Errors'=>array());
        if (isset($selectors['Source'])){$selectors=array($selectors);}
        $pageTitle=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');
        $fileName=preg_replace('/\W+/','_',$pageTitle).' dump.zip';
        $dir=$this->getTmpDir();
        $dumpFile=$dir.$fileName;
        if (is_file($dumpFile)){unlink($dumpFile);}
        $attachedFiles=array();
        $zip = new \ZipArchive;
        $zip->open($dumpFile,\ZipArchive::CREATE);
        foreach($selectors as $index=>$selector){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$isSystemCall,'Read',FALSE,TRUE,FALSE,FALSE,array(),FALSE,FALSE) as $entry){
                $attachedFileName=$entry['Source'].'~'.$entry['EntryId'].'.file';
                $attachedFile=$this->selector2file($entry);
                if (is_file($attachedFile)){
                    $statistics['Attached filesize']+=filesize($attachedFile);
                    $attachedFiles[]=array('attachedFile'=>$attachedFile,'attachedFileName'=>$attachedFileName);
                }
                $jsonFileContent=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($entry);
                $jsonFileName=$entry['Source'].'~'.$entry['EntryId'].'.json';
                $zip->addFromString($jsonFileName,$jsonFileContent);
                $statistics['added entries']++;
                $statistics['tables'][$entry['Source']]=$entry['Source'];
            }
        }
        if ($statistics['Attached filesize']<$maxAttachedFilesize){
            foreach($attachedFiles as $fileIndex=>$fileArr){
                $statistics['added files']++;
                $zip->addFile($fileArr['attachedFile'],$fileArr['attachedFileName']);
            }
        } else {
            $statistics['Errors'][]='Attached files were skipped do to their size. Use FTP to back them up!';
        }
        $zip->close();
        $statistics['Attached filesize']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($statistics['Attached filesize'],2,1024);
        $msg='Export resulted in '.$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics);
        $this->oc['logger']->log('info',$msg,array());    
        return $dumpFile;
    }
    
    public function downloadExportedEntries(array $selectors,string $fileName='',bool $isSystemCall=FALSE,int $maxAttachedFilesize=10000000000):void
    {
        $dumpFile=$this->exportEntries($selectors,$isSystemCall,$maxAttachedFilesize);
        if (is_file($dumpFile)){
            $pathArr=pathinfo($dumpFile);
            if (empty($fileName)){$fileName=$pathArr['filename'];}
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'.$fileName.'"');
            header('Content-Length: '.fileSize($dumpFile));
            readfile($dumpFile);
        }
    }
    
    public function importEntries(string $dumpFile,$orgFileName='',bool $isSystemCall=FALSE):array
    {
        $statistics=array('zip errors'=>0,'json decode errors'=>0,'entries updated'=>0,'attached files added'=>0);
        $dir=$this->getPrivatTmpDir();
        $zip = new \ZipArchive;
        if ($zip->open($dumpFile)===TRUE){
            $zip->extractTo($dir);
            $zip->close();
            $files=scandir($dir);
            foreach($files as $fileName){
                // get entry and linked file
                $file=$dir.$fileName;
                if (!is_file($file)){continue;}
                if (mb_strpos($fileName,'.json')===FALSE){continue;}
                $fileContent=file_get_contents($file);
                $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr($fileContent);
                if (!$entry){
                    $statistics['json decode errors']++;
                    continue;
                }
                // copy file
                $sourceFile=$dir.$entry['Source'].'~'.$entry['EntryId'].'.file';
                $targetFile=$this->selector2file($entry);
                if (is_file($sourceFile)){
                    $statistics['attached files added']++;
                    $this->tryCopy($sourceFile,$targetFile,0750);
                    $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entry,'Attachment log',array('msg'=>'Entry attachment imported','Expires'=>date('Y-m-d H:i:s',time()+604800)),FALSE);
                }
                // update insert entry
                $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addLog2entry($entry,'Processing log',array('msg'=>'Entry imported','Expires'=>date('Y-m-d H:i:s',time()+604800)),FALSE);
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,$isSystemCall);
                $statistics['entries updated']++;
            }
        } else {
            $statistics['zip errors']++;
        }
        $context=array('statistics'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics),'orgFileName'=>$orgFileName);
        $this->oc['logger']->log('notice','Import of "{orgFileName}" resulted in "{statistics}"',$context);    
        return $statistics;
    }
    
    public function loggerFilesWidget()
    {
        $clearAll=FALSE;
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['download'])){
            $file=$GLOBALS['dirs']['logging'].key($formData['cmd']['download']);
            $pathinfo=pathinfo($file);
            header('Content-Type: text/'.$pathinfo['extension']);
            header('Content-Disposition: attachment; filename="'.$pathinfo['basename'].'"');
            header('Content-Length: '.fileSize($file));
            readfile($file);
            exit;
        } else if (isset($formData['cmd']['clear']['all'])){
            $clearAll=TRUE;
        } else if (isset($formData['cmd']['remove'])){
            $file=key($formData['cmd']['remove']);
            unlink($GLOBALS['dirs']['logging'].$file);
        }
        $btnArr=array('tag'=>'button','keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,);
        $matrix=array();
        $files=scandir($GLOBALS['dirs']['logging']);
        foreach($files as $fileName){
            if (strlen($fileName)<3){continue;}
            $file=$GLOBALS['dirs']['logging'].$fileName;
            if ($clearAll){
                unlink($file);
                continue;
            }
            $matrix[$fileName]=pathinfo($file);
            $matrix[$fileName]['Size']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str(filesize($file),3,1024).'B';
            $matrix[$fileName]['Date']=date('Y-m-d H:i:s',filemtime($file));
            $btnArr['key']=array('download',$fileName);
            $btnArr['element-content']='&#8892;';
            $btnArr['hasCover']=FALSE;
            $matrix[$fileName]['Download']=$btnArr;
            $btnArr['key']=array('remove',$fileName);
            $btnArr['hasCover']=TRUE;
            $btnArr['element-content']='&xcup;';
            $matrix[$fileName]['Delete']=$btnArr;
        }
        if ($matrix){
            foreach(current($matrix) as $column=>$value){
                if ($column==='dirname'){
                    $matrix['lastRow'][$column]='Remove all logging files';
                } else if ($column==='Delete'){
                    $btnArr['key']=array('clear','all');
                    $matrix['lastRow'][$column]=$btnArr;
                } else {
                    $matrix['lastRow'][$column]='';
                }
            }
            ksort($matrix);
            $tableHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Logging files','keep-element-content'=>TRUE,'hideKeys'=>TRUE,'hideHeader'=>FALSE));
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$tableHtml,'keep-element-content'=>TRUE));
        } else {
            return '';
        }
    }
    
}
?>