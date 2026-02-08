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

class Filespace implements \SourcePot\Datapool\Interfaces\Job{

    private $oc;
    
    const ENV_FILE='env.json';
    private const MAX_AGE=[
        'dirs'=>[
            '__IMMEDIATELY__'=>60,
            '__PUBLIC__'=>60,
            'tmp'=>10800,
            'privat tmp'=>60
        ]
    ];

    private $entryTemplate=[
        'Class'=>['type'=>'string','value'=>TRUE,'Description'=>'Class selects the folder within the setup dir space'],
        'EntryId'=>['type'=>'string','value'=>TRUE,'Description'=>'This is the unique id'],
        'Type'=>['type'=>'string','value'=>'{{Class}}','Description'=>'This is the data-type of Content'],
        'Date'=>['type'=>'datetime','value'=>'{{nowDateUTC}}','Description'=>'This is the entry date and time'],
        'Content'=>['type'=>'json','value'=>[],'Description'=>'This is the entry Content, the structure of depends on the MIME-type.'],
        'Read'=>['type'=>'int','value'=>'ADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'int','value'=>'ADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'],
        'Owner'=>['type'=>'string','value'=>'SYSTEM','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.']
    ];

    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $this->resetStatistic();
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->removeTmpDirs();
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
        if (isset($vars['Error'])){
            unset($vars['Error']);
        }
        if (empty($vars['Dirs to process'])){
            $dirs=scandir($GLOBALS['dirs']['filespace']);
            foreach($dirs as $dir){
                if (strcmp($dir,'.')===0 || strcmp($dir,'..')===0){continue;}
                $vars['Dirs to process'][$dir]=['dir'=>$GLOBALS['dirs']['filespace'].$dir,'table'=>$dir];
            }
        }
        $vars['Last deleted files']=[];
        $vars['Last failed deletions']=[];
        $dir2process=array_shift($vars['Dirs to process']);
        if (empty($dir2process['dir'])){
            $this->oc['logger']->log('error','Failed "{class} &rarr; {function}()" due to array key in "dir2process[dir]" missing or empty array',['class'=>__CLASS__,'function'=>__FUNCTION__]);    
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
                $context=['table'=>$dir2process['table'],'deleted'=>count($vars['Last deleted files']),'failed'=>count($vars['Last failed deletions'])];
                $this->oc['logger']->log('error','Files without corresponding entries found in "{table}", deleted="{deleted}" failed="{failed}"',$context);         
                $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'Deleted unlinked files',$context['deleted'],'int'); 
            }
            $vars['Last processed dir']=$dir2process['dir'];
        }
        return $vars;
    }

    public function resetStatistic():array
    {
        $_SESSION[__CLASS__]['Statistic']=['matched files'=>0,'updated files'=>0,'deleted files'=>0,'deleted dirs'=>0,'inserted files'=>0,'added dirs'=>0,'uploaded file'=>0];
        return $_SESSION[__CLASS__]['Statistic'];
    }
    
    public function addStatistic($key,$amount):array
    {
        if (!isset($_SESSION[__CLASS__]['Statistic'])){$this->resetStatistic();}
        $_SESSION[__CLASS__]['Statistic'][$key]+=$amount;
        return $_SESSION[__CLASS__]['Statistic'];
    }
    
    public function getStatistic($key=FALSE):array|int
    {
        if (isset($_SESSION[__CLASS__]['Statistic'][$key])){
            return $_SESSION[__CLASS__]['Statistic'][$key];
        } else {
            return $_SESSION[__CLASS__]['Statistic'];
        }
    }

    private function class2dir(string $class,bool $mkDirIfMissing=FALSE):string
    {
        $classComps=explode('\\',$class);
        $class=array_pop($classComps);
        $dir=$GLOBALS['dirs']['setup'].$class.'/';
        if (!is_dir($dir) && $mkDirIfMissing){
            mkdir($dir,0770,TRUE);
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
            mkdir($dir,0770,TRUE);
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
        }
        return ($dir??'').($file??'');
    }

    public function unifyEntry(array $entry):array 
    {
        if (!isset($entry['Type'])){
            $classComps=explode('\\',$entry['Class']);
            $entry['Type']=array_pop($classComps);
        }
        // add defaults at missing keys
        foreach($this->entryTemplate as $key=>$defArr){
            if (!isset($entry[$key])){$entry[$key]=$defArr['value'];}
        }
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ADMIN_R','ADMIN_R');
        $entry=$this->oc['SourcePot\Datapool\Root']->substituteWithPlaceholder($entry);
        return $entry;
    }
    
    public function entryIterator(array $selector,bool $isSystemCall=FALSE,string $rightType='Read'):\Generator
    {
        $dir=$this->class2dir($selector['Class']);
        if (!is_dir($dir)){
            if (isset($this->oc['logger'])){
                $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'dir'=>$dir];
                $this->oc['logger']->log('notice','Function {class} &rarr; {function}() failed: directory "{dir}" missing',$context);
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

    /**
    * The method returns the first entry that matches the selector or false, if no match is found.
    *
    * @param array $selector Is the selector.  
    * @param boolean $isSystemCall The value is provided to access control. 
    * @param boolean $returnMetaOnNoMatch If true and EntryId is provided, meta data is return on no match instead of false. 
    *
    * @return array|boolean The entry, an empty array or false if no entry was found.
    */
    public function hasEntry(array $selector,bool $isSystemCall=TRUE,string $rightType='Read',bool $removeGuideEntries=TRUE):array|bool
    {
        if (empty($selector['Class'])){return FALSE;}
        if (empty($selector['EntryId'])){
            foreach($this->entryIterator($selector,$isSystemCall,$rightType) as $entry){
                return $entry;
            }
        } else {
            return $this->entryById($selector,$isSystemCall,$rightType);
        }
        return FALSE;
    }

    public function entryById(array $selector,bool $isSystemCall=FALSE,string $rightType='Read',bool $returnMetaOnNoMatch=FALSE):array
    {
        // This method returns the entry from a setup-file selected by the selector arguments.
        // The selector argument is an array which must contain at least the array-keys 'Class' and 'EntryId'.
        //
        $entry=['rowCount'=>0,'rowIndex'=>0,'access'=>'NO ACCESS RESTRICTION'];
        $selector['EntryId']=trim($selector['EntryId']?:'','%');
        $entry['file']=$this->selector2file($selector);
        $arr=$this->oc['SourcePot\Datapool\Root']->file2arr($entry['file']);
        $entry['rowCount']=(empty($arr))?0:1;
        if (empty($arr)){
            // no entry found
            if (!$returnMetaOnNoMatch){$entry=[];}
        } else {
            // entry found
            $entry['rowCount']=1;
            $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
            $entry['access']=$this->oc['SourcePot\Datapool\Foundation\Access']->access($arr,$rightType,$user,$isSystemCall);
            if ($entry['access']){
                $entry=array_replace_recursive($selector,$entry,$arr);
            } else if (!$returnMetaOnNoMatch){
                $entry=[];
            }
        }
        return $entry;
    }
    
    public function insertEntry(array $entry):array
    {
        return $this->updateEntry($entry);
    }

    public function updateEntry(array $entry,bool $isSystemCall=FALSE,bool $noUpdateCreateIfMissing=FALSE,bool $recursiveReplace=TRUE):array
    {
        // This method updates and returns the entry from the setup-directory.
        // The selector argument is an array which must contain at least the array-keys 'Class' and 'EntryId'.
        //
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $existingEntry=$this->entryById($entry,TRUE,'Read',TRUE);
        if (empty($existingEntry['rowCount'])){
            // insert entry
            $entry=$this->unifyEntry($entry);
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($entry,$existingEntry['file']);
            $this->addStatistic('inserted files',1);
        } else if (empty($noUpdateCreateIfMissing)){
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($existingEntry,'Write',$user,$isSystemCall)){
                // has access to update entry
                if ($recursiveReplace){
                    $entry=array_replace_recursive($existingEntry,$entry);
                } else {
                    $entry=array_merge($existingEntry,$entry);
                }
                //$entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->mergeArr($existingEntry,$entry);
                $entry=$this->oc['SourcePot\Datapool\Root']->substituteWithPlaceholder($entry);
                $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($entry,$existingEntry['file']);
                $this->addStatistic('updated files',1);                    
            } else {
                // failed access to update entry
                $entry=['rowCount'=>1,'rowIndex'=>0,'access'=>FALSE];
                $this->addStatistic('matched files',1);
            }
        } else {
            // existing entry was not updated
            if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($existingEntry,'Read',$user,$isSystemCall)){
                $entry=$existingEntry;
                $this->addStatistic('matched files',1);
            }
        }
        return $entry;
    }
    
    public function entryByIdCreateIfMissing(array $entry,bool $isSystemCall=FALSE):array
    {
        return $this->updateEntry($entry,$isSystemCall,TRUE);
    }

    /**
    * This method deletes the selected entries including linked files 
    * and returns the count of deleted entries or false on error.
    *
    * @param array $selector Is the selector to select the entries to be deleted  
    * @return int|boolean The count of deleted entries or false on failure
    */
    public function deleteEntries(array $selector,bool $isSystemCall=FALSE):array
    {
        foreach($this->entryIterator($selector,$isSystemCall) as $EntryId=>$entry){
            if (empty($entry['file'])){continue;}
            $this->addStatistic('deleted files',intval(unlink($entry['file'])));
        }
        return $this->getStatistic();
    }

    /**
    * This method deletes the selected entries including linked files 
    * and returns the count of deleted entries or false on error.
    *
    * @param array $selector Is the selector to select the entries to be deleted  
    * @return int|boolean The count of deleted entries or false on failure
    */
    public function deleteEntry(array $selector,bool $isSystemCall=FALSE):array
    {
        foreach($this->entryIterator($selector,$isSystemCall) as $EntryId=>$entry){
            if (empty($entry['file'])){continue;}
            return $this->addStatistic('deleted files',intval(unlink($entry['file'])));
        }
        return $this->addStatistic('deleted files',0);
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
                $zip->addFile($file,str_replace('_', '-',basename($entry['Params']['File']['Name']??'filename_unset_'.hrtime(TRUE))));
            }
            $zip->close();
            $entry=['Params'=>['File'=>['Extension'=>'zip','Name'=>$zipName]]];
            $fileForDownload=$zipFile;
        } else {
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($entry);
            $fileForDownload=$this->selector2file($entry);
        }
        if (is_file($fileForDownload)){
            $fileNamePathInfo=pathinfo($entry['Params']['File']['Name']??$entry['Name']);
            if (!isset($fileNamePathInfo['extension'])){
                $entry['Params']['File']['Name'].='.'.$entry['Params']['File']['Extension'];
                $this->oc['logger']->log('notice','"{function}" file extension added to filename {fileName}',['function'=>__FUNCTION__,'fileName'=>$entry['Params']['File']['Name']]);
            }
            header('Content-Type: application/'.$entry['Params']['File']['Extension']);
            header('Content-Disposition: attachment; filename="'.$entry['Params']['File']['Name'].'"');
            header('Content-Length: '.fileSize($fileForDownload));
            readfile($fileForDownload);
            exit;
        } else {
            $this->oc['logger']->log('notice','No file found to download.',[]);    
        }
    }

    public function getPrivatTmpDir($salt=''):string
    {
        $privDir=$this->oc['SourcePot\Datapool\Root']->getIP($hashOnly=TRUE,$salt);
        $tmpDir=$GLOBALS['dirs']['privat tmp'].$privDir.'/';
        if (!is_dir($tmpDir)){
            $this->addStatistic('added dirs',intval(mkdir($tmpDir,0770,TRUE)));
        }
        return $tmpDir;
    }
    
    public function getTmpDir():string
    {
        if (!isset($_SESSION[__CLASS__]['tmpDir'])){
            $isPublic=str_contains($this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),'ANONYM_');
            $sessionIdHash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash(session_id(),TRUE);
            $_SESSION[__CLASS__]['tmpDir']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(20);
            $_SESSION[__CLASS__]['tmpDir'].='_'.$sessionIdHash;
            if ($isPublic){
                $_SESSION[__CLASS__]['tmpDir'].='_PUBLIC';
            }
            $_SESSION[__CLASS__]['tmpDir'].='/';
        }
        $tmpDir=$GLOBALS['dirs']['tmp'].$_SESSION[__CLASS__]['tmpDir'];
        if (!is_dir($tmpDir)){
            $this->addStatistic('added dirs',intval(mkdir($tmpDir,0775,TRUE)));
        }
        return $tmpDir;
    }
    
    public function removeTmpDirs(string $sessionId='__NOT_SET__'):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        $sessionIdHash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($sessionId,TRUE);
        foreach($GLOBALS['dirs'] as $dirKey=>$tmpDir){
            if (!isset(self::MAX_AGE['dirs'][$dirKey])){continue;}
            $context['dirKey']=$dirKey;
            if (is_dir($tmpDir)){
                $allDirs=scandir($tmpDir);
                foreach($allDirs as $dir){
                    $context['dir']=$dir;
                    $fullDir=$tmpDir.$dir;
                    if (!is_dir($fullDir) || strlen($dir)<4){continue;}
                    // remove tmp dir, if expired
                    if (str_contains($dir,$sessionIdHash)){
                        // tmp expires immediately on session Logout
                        $context['maxAgeKey']='__IMMEDIATELY__';
                    } else if (str_contains($dir,'_PUBLIC')){
                        // if public, MAX_AGE is shorter
                        $context['maxAgeKey']='__PUBLIC__';
                    } else {
                        // active session MAX_AGE
                        $context['maxAgeKey']=$dirKey;
                    }
                    $maxAge=self::MAX_AGE['dirs'][$context['maxAgeKey']];
                    // check age and delete expired dir
                    $age=time()-filemtime($fullDir);
                    if ($age>$maxAge){
                        $this->delDir($fullDir);
                        //$this->oc['logger']->log('info','Function "{class} &rarr; {function}()" expired dir "{dirKey} &rarr; {dir}" removed, MAX_AGE key "{maxAgeKey}"',$context);         
                    }
                }
            }
        }
        return $this->getStatistic();
    }

    public function delDir(string $dir):array
    {
        gc_collect_cycles();
        if (is_dir($dir)){
            $files2delete=scandir($dir);
            foreach($files2delete as $fileIndex=>$file){
                $file=$dir.'/'.$file;
                if (is_file($file)){
                    $this->addStatistic('deleted files',intval(unlink($file)));
                }    
            }
            $this->addStatistic('deleted dirs',intval(rmdir($dir)));
        }
        return $this->getStatistic();
    }
    
    public function dirSize(string $dir):int
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
            $this->addStatistic('inserted files',intval(copy($source,$targetFile)));
            if ($rights){
                chmod($targetFile,$rights);
            }
        } catch(\Exception $e){
            // Exception handling
        }
        return $this->getStatistic();
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

// ================================ The following methods add files to entries. Methods fileUpload2entry() and fileContent2entry() are wrapper methods for file2entry().
    
    /**
    * This method moves the uploaded file to the private tmp directory and calls the file2entry method.
    * @param array $fileArr Is the content of $_FILE
    * @param array $entry Is the entry to be linked with the uploaded file
    * @param boolean $createOnlyIfMissing If TRUE, an exsisting entry will not be touched but the file will be added
    * @param boolean $isSystemCall Is the access control setting
    *
    * @return array Returns the final entry
    */
    public function fileUpload2entry(array $fileArr,array $entry,bool $createOnlyIfMissing=FALSE,bool $isSystemCall=FALSE):array
    {
        $this->resetStatistic();
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        if (empty($fileArr['tmp_name'])){
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" called with empty fileArr["tmp_name"], skipped this entry',$context);         
            return $entry;
        }
        if (empty($fileArr['name'])){
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" called with empty fileArr["name"], skipped this entry',$context);         
            return $entry;
        }
        // move uploaded file to private tmp dir
        $entry['Params']['File']['Source']=$fileArr['tmp_name'];
        $tmpDir=$this->getPrivatTmpDir();
        $pathinfo=pathinfo($fileArr['name']);
        $file=$context['file']=$tmpDir.$pathinfo['basename'];
        $success=move_uploaded_file($fileArr['tmp_name'],$file);
        if ($success){
            $entry=$this->file2entry($file,$entry,$createOnlyIfMissing,$isSystemCall);
        } else {
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" moving uploaded file "{file}" failed, skipped this entry',$context);         
        }
        return $entry;
    }
    
    /**
    * This method saves bytes stored at entry['fileContent'] to the private tmp directory, the file name is set from entry['fileCName'].
    * @param array $entry Is the entry providing the file content and file name
    * @param boolean $createOnlyIfMissing If TRUE, an exsisting entry will not be touched but the file will be added
    * @param boolean $isSystemCall Is the access control setting
    *
    * @return array Returns the final entry
    */
    public function fileContent2entry(array $entry,bool $noUpdateButCreateIfMissing=FALSE,bool $isSystemCall=FALSE):array
    {
        $this->resetStatistic();
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        if (empty($entry['fileContent'])){
            // key "fileContent" not set
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" failed entry["fileContent"] is empty, skipped this entry',$context);             
        } else if (empty($entry['fileName'])){
            // key "fileName" not set
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" failed entry["fileName"] is empty, skipped this entry',$context);             
        } else {
            // save entry['fileContent'] to private tmp dir, e.g. from email
            $tmpDir=$this->getPrivatTmpDir();
            $entry['Params']['File']['Source']=$context['filename']=$tmpDir.$entry['fileName'];
            $context['bytes']=file_put_contents($entry['Params']['File']['Source'],$entry['fileContent']);
            if ($context['bytes']===FALSE){
                $this->oc['logger']->log('error','Function "{class} &rarr; {function}()" failed to create temporary file "{filename}", skipped this entry',$context);                 
            } else {
                $entry=$this->file2entry($entry['Params']['File']['Source'],$entry,$noUpdateButCreateIfMissing,$isSystemCall);
                $this->oc['logger']->log('info','Function "{class} &rarr; {function}()" created file "{filename}" with "{bytes}" Bytes.',$context);                 
            }
        }
        return $entry;
    }
    
    /**
    * This method adds/links a file to an entry.
    * @param string $file Is the full file name of the file to be linked to the entry
    * @param array $entry Is the entry to be linked with the file
    * @param boolean $noUpdateButCreateIfMissing If TRUE, an exsisting entry will not be touched but the file will be added
    * @param boolean $isSystemCall Is the access control setting
    *
    * @return array Returns the final entry
    */
    public function file2entry(string $file,array $entry,bool $noUpdateButCreateIfMissing=FALSE,bool $isSystemCall=FALSE):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'specialFileHandling'=>FALSE,'noUpdateButCreateIfMissing'=>$noUpdateButCreateIfMissing,'isSystemCall'=>$isSystemCall];
        if (empty($entry['Source'])){
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" called with empty entry["Source"], skipped this entry',$context);         
            return $entry;
        }
        if (!is_file($file)){
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" called with invalid file handle "{file}", skipped this entry',$context);         
            return $entry;
        }
        $entry=$this->addFileProps($entry,$file);
        $entry=$this->oc['SourcePot\Datapool\Tools\ExifTools']->addExif2entry($entry,$file);
        if ($this->specialFileHandling($file,$entry,$noUpdateButCreateIfMissing,$isSystemCall)){
            // Files are processed by specialFileHandling()
            // If a file is an archive or an email, this method will be called again with each of the separated files
            $context['specialFileHandling']=TRUE;
        } else {
            // single file handling
            $entry=$this->addFile2entry($entry,$file);
            // analyse pdf if any parser is selected
            if (!empty($entry['parserMethod'])){
                $entry=$this->oc['SourcePot\Datapool\Tools\PdfTools']->attachments2arrSmalot($file,$entry);
            }
            // update entry
            $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,$isSystemCall,$noUpdateButCreateIfMissing);
        }
        $entry[__FUNCTION__]=$context;
        return $entry;
    }

    public function addFileProps(array $entry,string $file):array
    {
        // set Params → File uploader properties
        $currentUser=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $entry['Owner']=(empty($entry['Owner']))?$currentUser['EntryId']:$entry['Owner'];
        $entry['Params']['File']=[];
        if (empty($entry['Params']['File']['UploaderId'])){
            $entry['Params']['File']['UploaderId']=$currentUser['EntryId'];
            $entry['Params']['File']['UploaderName']=$currentUser['Name'];
            $entry['Params']['File']['Uploaded']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
        }
        // get file name
        $pathinfo=pathinfo($file);
        if (!empty($entry['fileName'])){
            $fileName=pathinfo($entry['fileName'])['filename'];
        } else if (!empty($entry['Name'])){
            $fileName=pathinfo($entry['Name'])['filename'];
        } else {
            $fileName=$pathinfo['filename'];
            $entry['Name']=$fileName.'.'.$pathinfo['extension'];
        }
        $fileName=trim(preg_replace('/[^A-Za-z0-9\-]/','_',$fileName),'_ ');
        // set Params → File properties
        $entry['Params']['File']['Name']=$fileName.'.'.$pathinfo['extension'];
        $entry['Params']['File']['Extension']=$pathinfo['extension'];
        $entry['Params']['File']['Size']=filesize($file);
        $entry['Params']['File']['Date (created)']=filectime($file);
        $entry['Params']['File']['MIME-Type']=mime_content_type($file);
        $entry['Params']['File']['Style class']='';
        return $entry;
    }

    private function specialFileHandling(string $file,array $entry,bool $createOnlyIfMissing=FALSE,bool $isSystemCall=FALSE):array|bool
    {
        if ($this->oc['SourcePot\Datapool\Foundation\Explorer']->selector2setting($entry,'File upload extract email parts') && (stripos($entry['Params']['File']['MIME-Type'],'/vnd.ms-outlook')!==FALSE || $entry['Params']['File']['Extension']=='msg')){
            // Outlook email
            $email=file_get_contents($file);
            $this->oc['SourcePot\Datapool\Tools\Email']->ole2entries($entry,$email,'',[]);
            return TRUE;
        } else if ($this->oc['SourcePot\Datapool\Foundation\Explorer']->selector2setting($entry,'File upload extract email parts') && $entry['Params']['File']['Extension']=='p7m'){
            // signed email
            $email=file_get_contents($file);
            $this->oc['SourcePot\Datapool\Tools\Email']->msg2entries($entry,$email,'',[]);
            return TRUE;
        } else if ($this->oc['SourcePot\Datapool\Foundation\Explorer']->selector2setting($entry,'File upload extract email parts') && (stripos($entry['Params']['File']['MIME-Type'],'/rfc822')!==FALSE || $entry['Params']['File']['Extension']=='eml')){
            // standard email
            $email=file_get_contents($file);
            $this->oc['SourcePot\Datapool\Tools\Email']->msg2entries($entry,$email,'',[]);
            return TRUE;
        } else if ($this->oc['SourcePot\Datapool\Foundation\Explorer']->selector2setting($entry,'File upload extract archive') && stripos($entry['Params']['File']['MIME-Type'],'/zip')!==FALSE){
            // zip-archive
            $this->archive2files($file,$entry,$createOnlyIfMissing,$isSystemCall);
            return TRUE;
        }
        return FALSE;
    }
   
    private function archive2files(string $file,array $entry,bool $createOnlyIfMissing,bool $isSystemCall):array
    {
        $zipStatistic=['errors'=>[],'files'=>[]];
        // extract zip archive to a temporary dir
        $zip=new \ZipArchive;
        if ($zip->open($file)===TRUE){
            $i=0;
            $this->oc['logger']->log('info','Processing zip-archive with "{numFiles}" files',['numFiles'=>$zip->count()]);    
            while($fileName=$zip->getNameIndex($i)){
                $entry['fileName']=preg_replace('/[^a-zäüößA-ZÄÜÖ0-9\.]+/','_',$fileName);
                $entry['fileContent']=$zip->getFromIndex($i);
                if (!empty($entry['fileContent'])){
                    $zipStatistic['files'][$i]=$entry['fileName'];
                    $entry['Name']=$entry['fileName'];
                    $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder','Name'],'0','',FALSE);
                    $newEntry=$entry;
                    $this->fileContent2entry($newEntry);
                }
                $i++;
            }
            $zip->close();
        } else {
            $zipStatistic['errors'][]='Failed to open zip archive';
        }
        return $zipStatistic;
    }
    
    /**
     * Adds meta data to to an entry derived from the file provided.
     * @param array $entry is the entry which meta data is added to
     * @param string $file is file from which the meta data is derived
     *
     * @return $entry is the enriched entry
     */
    public function addFile2entry(array $entry,string $file):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'steps'=>''];
        // if pdf parse content
        if (stripos($entry['Params']['File']['MIME-Type'],'pdf')!==FALSE){
            // pdf-content to text
            $parserMethod=$entry['parserMethod']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->selector2setting($entry,'pdf-file parser');
            if (empty($parserMethod)){
                $this->oc['logger']->log('notice','File upload, pdf parsing failed: no parser selected',[]);    
            } else {
                try{
                    $entry=$this->oc['SourcePot\Datapool\Tools\PdfTools']->$parserMethod($file,$entry);
                } catch (\Exception $e){
                    $context['msg']=$e->getMessage();
                    $this->oc['logger']->log('notice','Function "{class} &rarr; {function}()" parser failed: {msg}',$context);
                }
            }
            // extract attachments
            try{
                $entry=$this->oc['SourcePot\Datapool\Tools\PdfTools']->attachments2arrSmalot($file,$entry);
            } catch (\Exception $e){
                $context['msg']=$e->getMessage();
                $this->oc['logger']->log('notice','Function "{class} &rarr; {function}()" failed to scan for pdf-attachments: {msg}',$context);
            }    
        } else if (stripos(strval($entry['Params']['File']['Extension']),'csv')!==FALSE){
            $entry['Params']['File']['Spreadsheet']=$this->oc['SourcePot\Datapool\Tools\CSVtools']->csvIterator($file,$entry['Params']['File']['Extension'])->current();
            $entry['Params']['File']['SpreadsheetIteratorClass']='SourcePot\Datapool\Tools\CSVtools';
            $entry['Params']['File']['SpreadsheetIteratorMethod']='csvIterator';
        } else if (stripos(strval($entry['Params']['File']['Extension']),'xls')!==FALSE){
            $entry['Params']['File']['Spreadsheet']=$this->oc['SourcePot\Datapool\Tools\XLStools']->iterator($file,$entry['Params']['File']['Extension'])->current();
            $entry['Params']['File']['SpreadsheetIteratorClass']='SourcePot\Datapool\Tools\XLStools';
            $entry['Params']['File']['SpreadsheetIteratorMethod']='iterator';
        } else if (stripos(strval($entry['Params']['File']['Extension']),'txt')!==FALSE){
            $entry['Content']['File content']=file_get_contents($file);
        } else if (stripos(strval($entry['Params']['File']['Extension']),'json')!==FALSE){
            $fileContent=file_get_contents($file)?:'{}';
            $entry['Content']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr($fileContent);
        }
        // add file to entry
        if (empty($entry['EntryId'])){
            $entry['EntryId']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getEntryId();
        }
        $context['file']=$file;
        $context['targetFile']=$this->selector2file($entry,TRUE);
        if ($this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($file,$context['targetFile'])){
            // success
            $context['steps']='File copied to entry|';
        } else {
            $context['steps']='Failed to copy file to entry|';
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" failed to copy "{file}" to "{targetFile}"',$context);
        }
        $entry[__FUNCTION__]=$context;
        return $entry;
    }

// ============ Methods providing entry import and export ======================================

    public function exportEntries(array $selectors,bool $isSystemCall=FALSE,int $maxAttachedFilesize=10000000000):string
    {
        $statistics=['added entries'=>0,'added files'=>0,'Attached filesize'=>0,'tables'=>[],'Errors'=>[]];
        if (isset($selectors['Source'])){$selectors=[$selectors];}
        $pageTitle=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');
        $fileName=preg_replace('/\W+/','_',$pageTitle).' dump.zip';
        $dir=$this->getTmpDir(__FUNCTION__);
        $dumpFile=$dir.$fileName;
        if (is_file($dumpFile)){unlink($dumpFile);}
        $attachedFiles=[];
        $zip = new \ZipArchive;
        $zip->open($dumpFile,\ZipArchive::CREATE);
        foreach($selectors as $index=>$selector){
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,$isSystemCall,'Read',FALSE,TRUE,FALSE,FALSE,[],FALSE,FALSE) as $entry){
                // get files to attach
                $attachedFileName=$entry['Source'].'~'.$entry['EntryId'].'.file';
                $attachedFile=$this->selector2file($entry);
                if (is_file($attachedFile)){
                    $statistics['Attached filesize']+=filesize($attachedFile);
                    $attachedFiles[]=['attachedFile'=>$attachedFile,'attachedFileName'=>$attachedFileName];
                }
                // add entry data
                $jsonFileContent=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($entry);
                if (empty($jsonFileContent)){
                    $statistics['Errors'][]='Entry Source='.$entry['Source'].', EntryId='.$entry['EntryId'].' skipped due to invalid json.';
                } else {
                    $jsonFileName=$entry['Source'].'~'.$entry['EntryId'].'.json';
                    $zip->addFromString($jsonFileName,$jsonFileContent);
                    $statistics['added entries']++;
                    $statistics['tables'][$entry['Source']]=$entry['Source'];
                }
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
        $this->oc['logger']->log('info',$msg,[]);    
        return $dumpFile;
    }
    
    public function downloadExportedEntries(array $selectors,string $fileName='',bool $isSystemCall=FALSE,int $maxAttachedFilesize=10000000000):void
    {
        $dumpFile=$this->exportEntries($selectors,$isSystemCall,$maxAttachedFilesize);
        if (is_file($dumpFile)){
            if (empty($fileName)){$fileName=basename($dumpFile);}
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'.$fileName.'"');
            header('Content-Length: '.filesize($dumpFile));
            readfile($dumpFile);
            exit;
        }
    }

    public function importEntries(string $dumpFile,$orgFileName='',bool $isSystemCall=FALSE):array
    {
        $statistics=['zip file count'=>0,'zip errors'=>0,'zip extraction error'=>0,'json decode errors'=>0,'entries updated'=>0,'attached files added'=>0];
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        $dir=$this->getPrivatTmpDir(__FUNCTION__);
        $zip = new \ZipArchive;
        if ($zip->open($dumpFile)===TRUE){
            $statistics['zip file count']=$zip->numFiles;
            $extracted=$zip->extractTo($dir);
            $zip->close();    
            $statistics['zip extraction error']=intval($extracted);
            $files=scandir($dir);
            foreach($files as $fileName){
                // get entry and linked file
                $file=$dir.$fileName;
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
                    $this->tryCopy($sourceFile,$targetFile,0770);
                }
                // update or insert entry
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,$isSystemCall);
                $statistics['entries updated']++;
            }
        } else {
            $statistics['zip errors']++;
        }
        $dbStatistics=$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
        $context=[
            'statistics'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics),
            'dbStatistics'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($dbStatistics),
            'orgFileName'=>$orgFileName
        ];
        $this->oc['logger']->log('info','Import of "{orgFileName}" resulted in "{statistics}", the database statistic is "{dbStatistics}"',$context);    
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
        $btnArr=['tag'=>'button','keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,];
        $matrix=[];
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
            $btnArr['key']=['download',$fileName];
            $btnArr['element-content']='&#8892;';
            $btnArr['hasCover']=FALSE;
            $matrix[$fileName]['Download']=$btnArr;
            $btnArr['key']=['remove',$fileName];
            $btnArr['hasCover']=TRUE;
            $btnArr['element-content']='&xcup;';
            $matrix[$fileName]['Delete']=$btnArr;
        }
        if ($matrix){
            foreach(current($matrix) as $column=>$value){
                if ($column==='dirname'){
                    $matrix['lastRow'][$column]='Remove all logging files';
                } else if ($column==='Delete'){
                    $btnArr['key']=['clear','all'];
                    $matrix['lastRow'][$column]=$btnArr;
                } else {
                    $matrix['lastRow'][$column]='';
                }
            }
            ksort($matrix);
            $tableHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'caption'=>'Logging files','keep-element-content'=>TRUE,'hideKeys'=>TRUE,'hideHeader'=>FALSE]);
            return $this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$tableHtml,'keep-element-content'=>TRUE]);
        } else {
            return '';
        }
    }
    
}