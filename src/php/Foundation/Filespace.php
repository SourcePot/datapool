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

	public function __construct($oc){
		$this->oc=$oc;
		$this->resetStatistic();
	}
	
	public function init($oc){
		$this->oc=$oc;
		$this->removeTmpDirs();
	}
		
	public function getEntryTemplate(){
		return $this->entryTemplate;
	}
	
	public function job($vars){
		if (empty($vars['Dirs to process'])){
			$dirs=scandir($GLOBALS['dirs']['filespace']);
			foreach($dirs as $dir){
				if (strcmp($dir,'.')===0 || strcmp($dir,'..')===0){continue;}
				$vars['Dirs to process'][$dir]=array('absDir'=>$GLOBALS['dirs']['filespace'].$dir,'table'=>$dir);
			}
		}
		$dir2process=array_shift($vars['Dirs to process']);
		$files=scandir($GLOBALS['dirs']['filespace']);
		foreach(new \DirectoryIterator($dir2process['absDir']) as $fileInfo){
			$file=$dir2process['absDir'].'/'.$fileInfo->getFilename();
			$extensionPos=strpos($fileInfo->getFilename(),'.file');
			if (empty($extensionPos)){continue;}
			$entryId=substr($fileInfo->getFilename(),0,$extensionPos);
			$sql="SELECT ".$dir2process['table'].".EntryId FROM `".$dir2process['table']."` WHERE `EntryId` LIKE '".$entryId."';";
			$stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql);
			if (empty($stmt->fetchAll())){
				if (is_file($file)){
					if (unlink($file)){
						$this->oc['SourcePot\Datapool\Foundation\Database']->addStatistic('removed',1);
					} else {
						$this->oc['SourcePot\Datapool\Foundation\Database']->addStatistic('failed',1);
					}
				}
				$this->oc['SourcePot\Datapool\Foundation\Database']->addStatistic('matches',1);
			}
		}
		$vars['Last processed dir']=$dir2process['absDir'];
		return $vars;
	}

	public function resetStatistic(){
		$this->statistics=array('matched files'=>0,'updated files'=>0,'deleted files'=>0,'deleted dirs'=>0,'inserted files'=>0,'added dirs'=>0);
		return $this->statistics;
	}
	
	public function getStatistic(){
		return $this->statistics;
	}
	
	private function class2dir($class,$mkDirIfMissing=FALSE){
		$classComps=explode('\\',$class);
		$class=array_pop($classComps);
		$dir=$GLOBALS['dirs']['setup'].$class.'/';
		if (!is_dir($dir) && $mkDirIfMissing){
			mkdir($dir,0750,TRUE);
		}
		return $dir;	
	}	

	private function source2dir($source,$mkDirIfMissing=TRUE){
		// This function returns the filespace directory based on the tablename provided.
		$source=explode('\\',$source);
		$source=array_pop($source);
		$dir=$GLOBALS['dirs']['filespace'].$source.'/';
		if (!is_dir($dir) && $mkDirIfMissing){
			mkdir($dir,0750,TRUE);
		}
		return $dir;	
	}
	
	public function selector2file($selector,$mkDirIfMissing=TRUE){
		if (!empty($selector['Source']) && !empty($selector['EntryId'])){
			$dir=$this->source2dir($selector['Source'],$mkDirIfMissing);	
			$file=$selector['EntryId'].'.file';
		} else if (!empty($selector['Class']) && !empty($selector['EntryId'])){
			$dir=$this->class2dir($selector['Class'],$mkDirIfMissing);	
			$file=$selector['EntryId'].'.json';
		} else {
			$source=(empty($selector['Source']))?'EMPTY':'OK';
			$entryId=(empty($selector['EntryId']))?'EMPTY':'OK';
			$class=(empty($selector['Class']))?'EMPTY':'OK';
			throw new \ErrorException('Function '.__FUNCTION__.': Mandatory keys missing in selector argument, either Source='.$source.', EntryId='.$entryId.'  or Class='.$class.', EntryId='.$entryId,0,E_ERROR,__FILE__,__LINE__);	
		}
		return $fileName=$dir.$file;
	}
	
	private function stdReplacements($str=''){
		if (!is_string($str)){return $str;}
		if (isset($this->oc['SourcePot\Datapool\Foundation\Database'])){
			$this->toReplace=$this->oc['SourcePot\Datapool\Foundation\Database']->enrichToReplace($this->toReplace);
		}
		foreach($this->toReplace as $needle=>$replacement){$str=str_replace($needle,$replacement,$str);}
		return $str;
	}
	
	public function unifyEntry($entry){
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
	
	public function file2arr($fileName){
		$arr=FALSE;
		if (is_file($fileName)){
			$content=$this->file_get_contents_utf8($fileName);
			if (!empty($content)){
				$arr=json_decode($content,TRUE,512,JSON_INVALID_UTF8_IGNORE);
				if (empty($arr)){$arr=json_decode(stripslashes($content),TRUE,512,JSON_INVALID_UTF8_IGNORE);}
			}
		}
		return $arr;
	}

	public function entryIterator($selector,$isSystemCall=FALSE,$rightType='Read'){
		$dir=$this->class2dir($selector['Class']);
		$files=scandir($dir);
		$selector['rowCount']=count($files)-2;
		foreach($files as $file){
			$suffixPos=strrpos($file,'.json');
			if (empty($suffixPos)){continue;}
			$selector['EntryId']=substr( $file,0,$suffixPos);
			$entry=$this->entryById($selector,$isSystemCall,$rightType);
			yield array_replace_recursive($selector,$entry);
		}
		return $entry['rowCount'];
	}

	public function entryById($selector,$isSystemCall=FALSE,$rightType='Read',$returnMetaOnNoMatch=FALSE){
		// This method returns the entry from a setup-file selected by the selector arguments.
		// The selector argument is an array which must contain at least the array-keys 'Class' and 'EntryId'.
		//
		$entry=array('rowCount'=>0,'rowIndex'=>0,'access'=>'NO ACCESS RESTRICTION');
		$entry['file']=$this->selector2file($selector);
		$arr=$this->file2arr($entry['file']);
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
	
	private function insertEntry($entry){
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
			return FALSE;
		}
	}
	
	public function updateEntry($entry,$isSystemCall=FALSE,$noUpdateCreateIfMissing=FALSE){
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
	
	public function entryByIdCreateIfMissing($entry,$isSystemCall=FALSE){
		return $this->updateEntry($entry,$isSystemCall,TRUE);
	}

	public function entry2fileDownload($entry){
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
			header('Content-Type: application/'.$entry['Params']['File']['Extension']);
			header('Content-Disposition: attachment; filename="'.$entry['Params']['File']['Name'].'"');
			header('Content-Length: '.fileSize($fileForDownload));
			readfile($fileForDownload);
			exit;
		} else {
			$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'No file found to download.','priority'=>12,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
	}

	public function getPrivatTmpDir(){
        $ip=$this->oc['SourcePot\Datapool\Foundation\Logging']->getIP($hashOnly=TRUE);
        
		$tmpDir=$GLOBALS['dirs']['privat tmp'].$ip.'/';
		if (!is_dir($tmpDir)){
			$this->statistics['added dirs']+=intval(mkdir($tmpDir,0770,TRUE));
        }
		return $tmpDir;
	}
	
	public function getTmpDir(){
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
	
	public function removeTmpDirs(){
        $tmpDirs=array($GLOBALS['dirs']['tmp']=>86400,$GLOBALS['dirs']['privat tmp']=>30);
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
	
	public function delDir($dir){
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
	
	public function dirSize($dir){
		$size=0;
		if (is_dir($dir)){
			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS)) as $file){
				$size+=$file->getSize();
			}
        }
		return $size;
	}
	
	public function tryCopy($source,$target,$rights=FALSE){
		try{
			$this->statistics['inserted files']+=intval(copy($source,$target));
			if ($rights){chmod($target,$rights);}
		} catch(Exception $e){
			// Exception handling
		}
	}

	public function abs2rel($file){
		$rel=str_replace($GLOBALS['dirs']['public'],'./',$file);
		return $rel;
	}

	public function rel2abs($file){
		$abs=str_replace('./',$GLOBALS['dirs']['public'],$file);
		return $abs;
	}
	
	public function file_get_contents_utf8($fn){
		$content=@file_get_contents($fn);
		$content=mb_convert_encoding($content,'UTF-8',mb_detect_encoding($content,'UTF-16,UTF-8,ISO-8859-1',TRUE));
		// clean up - remove BOM
		$bom=pack('H*','EFBBBF');
    	$content=preg_replace("/^$bom/",'',$content);
      	return $content;
	}
	
	/**
	* This is the file upload facility. I handels a wide range of possible file sources, e.g. form upload, incomming files via FTP directory,...
	*/
	public function file2entries($fileHandle,$entry,$createOnlyIfMissing=FALSE,$isSystemCall=FALSE,$isDebugging=FALSE){
		$debugArr=array('fileHandle'=>$fileHandle,'entry'=>$entry);
		if (isset($fileHandle['name']) && isset($fileHandle['tmp_name'])){
			// uploaded file via html form
			$entry['Params']['File']['Source']=$fileHandle['tmp_name'];
			$entry['pathArr']=pathinfo($fileHandle['name']);
			$entry['mimeType']=mime_content_type($fileHandle['tmp_name']);
			if (empty($mimeType) && !empty($fileHandle['type'])){$mimeType=$fileHandle['type'];}
			// move uploaded file to tmp dir
			$tmpDir=$this->getPrivatTmpDir();
			$newSourceFile=$tmpDir.$entry['pathArr']['basename'];
			$success=move_uploaded_file($fileHandle['tmp_name'],$newSourceFile);
			if (!$success){return FALSE;}
			$entry['Params']['File']['Source']=$newSourceFile;
			$entry=$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog2entry($entry,'Attachment log',array('File source old'=>$fileHandle['tmp_name'],'File source new'=>$entry['Params']['File']['Source']),FALSE);
		} else if (is_file($fileHandle)){
			// valid file name with path
			$entry['Params']['File']['Source']=$fileHandle;
			$entry['pathArr']=pathinfo($fileHandle);
			$entry['mimeType']=mime_content_type($fileHandle);
			$entry=$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog2entry($entry,'Attachment log',array('File source new'=>$entry['Params']['File']['Source']),FALSE);
		} else {
			if ($isDebugging){$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);}
			return FALSE;
		}
		return $this->fileContent2entries($entry,$createOnlyIfMissing,$isSystemCall,$isDebugging);
	}
	
	public function fileContent2entries($entry,$createOnlyIfMissing=FALSE,$isSystemCall=FALSE,$isDebugging=FALSE){
		$debugArr=array('entry'=>$entry,'createOnlyIfMissing'=>$createOnlyIfMissing);
		if (!empty($entry['fileContent']) && !empty($entry['fileName'])){
			// save file content to tmp dir
			$tmpDir=$this->getPrivatTmpDir();
			$entry['Params']['File']['Source']=$tmpDir.$entry['fileName'];
			$bytes=file_put_contents($entry['Params']['File']['Source'],$entry['fileContent']);
			if ($bytes===FALSE){return FALSE;}
			$entry['mimeType']=mime_content_type($entry['Params']['File']['Source']);
			$entry['pathArr']=pathinfo($entry['Params']['File']['Source']);
			$entry=$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog2entry($entry,'Attachment log',array('File source new'=>$entry['Params']['File']['Source']),FALSE);
		}
		$entry['currentUser']=(empty($_SESSION['currentUser']))?array('EntryId'=>'ANONYM','Name'=>'ANONYM'):$_SESSION['currentUser'];
		$entry['Owner']=(empty($entry['Owner']))?$entry['currentUser']['EntryId']:$entry['Owner'];
		$entry['Params']['File']['UploaderId']=$entry['currentUser']['EntryId'];
		$entry['Params']['File']['UploaderName']=$entry['currentUser']['Name'];
		$entry['Params']['File']['Uploaded']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
		$entry['Params']['File']['Size']=filesize($entry['Params']['File']['Source']);
		$entry['Params']['File']['Name']=$entry['pathArr']['basename'];
		$entry['Params']['File']['Extension']=$entry['pathArr']['extension'];
		$entry['Params']['File']['Date (created)']=filectime($entry['Params']['File']['Source']);
		if (empty($entry['Name'])){$entry['Name']=$entry['pathArr']['basename'];}
		if (isset($entry['mimeType'])){$entry['Params']['File']['MIME-Type']=$entry['mimeType'];}
		$entry=$entry;
		if (stripos($entry['Params']['File']['MIME-Type'],'zip')!==FALSE){
			// if file is zip-archive, extract all file and create entries seperately 
			$entry=$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog2entry($entry,'Processing log',array('msg'=>'Extracted from zip-archive "'.$entry['Params']['File']['Name'].'"'),FALSE);
			$debugArr['archive2files return']=$this->archive2files($entry,$createOnlyIfMissing,$isSystemCall,$isDebugging);
		} else {
			// save file
			$this->statistics['inserted files']++;
			$entry['Params']['File']['Style class']='';
			$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->addEntryDefaults($entry);
			$entry=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($entry);
			if (!empty($entry['Params']['File']['MIME-Type'])){	
				$entry['Type'].=' '.preg_replace('/[^a-zA-Z]/',' ',$entry['Params']['File']['MIME-Type']);
			}
			$targetFile=$this->selector2file($entry,TRUE);
			copy($entry['Params']['File']['Source'],$targetFile);
			$entry=$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog2entry($entry,'Attachment log',array('File source old'=>$entry['Params']['File']['Source'],'File source new'=>$targetFile),FALSE);
			$entry=$this->fileUploadPostProcessing($entry);
			if ($createOnlyIfMissing){
				$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($entry);
			} else {
				$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
			}
			$debugArr['entry updated']=$entry;
		}
		if ($isDebugging){
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr,__FUNCTION__.'-'.intval($isDebugging));
		}
		return $entry;
	}
	
	private function archive2files($entry,$createOnlyIfMissing,$isSystemCall,$isDebugging){
		$zipStatistic=array('errors'=>array(),'files'=>array());
		// extract zip archive to a temporary dir
		if (is_file($entry['Params']['File']['Source'])){
			$zipDir=$GLOBALS['dirs']['tmp'].$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(20).'/';
			$this->statistics['added dirs']+=intval(mkdir($zipDir,0775,TRUE));
			$zip=new \ZipArchive;
			if ($zip->open($entry['Params']['File']['Source'])===TRUE){
				$zip->extractTo($zipDir);
				$zip->close();
			} else {
				$zipStatistic['errors'][]='Failed to open zip archive';
			}
		} else {
			$zipStatistic['errors'][]='Zip archive is not a file';
		}
		$files=scandir($zipDir);
		foreach($files as $file){
			$file=$zipDir.$file;
			if (is_dir($file)){continue;}
			$zipStatistic['files'][]=$file;
			$entry['EntryId']='{{EntryId}}';
			$entry['Name']='';
			$this->file2entries($file,$entry,$createOnlyIfMissing,$isSystemCall,$isDebugging);
		}
		$this->delDir($zipDir);
		return $zipStatistic;
	}
	
	private function fileUploadPostProcessing($entry){
		$file=$this->selector2file($entry,TRUE);
		$entry=$this->oc['SourcePot\Datapool\Tools\ExifTools']->addExif2entry($entry,$file);
		$entry=$this->oc['SourcePot\Datapool\Tools\GeoTools']->location2address($entry);
		// if pdf parse content
		if (stripos($entry['Params']['File']['MIME-Type'],'pdf')!==FALSE){
			$pdfFileContent=$this->parsePdfFile($file);
			if (!empty($pdfFileContent)){
				$entry['Content']['File content']=$pdfFileContent;
			}
		}			
		return $entry;
	}

	public function parsePdfFile($file){
		$text=FALSE;
		if (class_exists('\Smalot\PdfParser\Config') &&  class_exists('\Smalot\PdfParser\Parser')){
			$fileContent=file_get_contents($file);
			$fileContent=$this->oc['SourcePot\Datapool\Tools\MiscTools']->base64decodeIfEncoded($fileContent);
			if ($this->pdfOK($fileContent)){				
				// parser configuration
				$config=new \Smalot\PdfParser\Config();
				$config->setHorizontalOffset('');
				$config->setRetainImageContent(FALSE);
				// check for encryption etc.
				$parser=new \Smalot\PdfParser\RawData\RawDataParser([],$config);
				list($xref,$data)=$parser->parseData($fileContent);
				if (!empty($data)){
					// parse content
					$parser=new \Smalot\PdfParser\Parser([],$config);
					$pdf=$parser->parseContent($fileContent);
					$text=$pdf->getText();
					// clean-up
					$text=preg_replace('/[\t ]+/',' ',$text);
					$text=preg_replace('/(\n )+|(\n )+/',"\n",$text);
					$text=preg_replace('/(\n)+/',"\n",$text);				
				}
			}
		}
		return $text;
	}

	private function pdfOK($pdfContent){
		if (FALSE===($trimpos=strpos($pdfContent,'%PDF-'))){return FALSE;}
		if (empty(preg_match_all('/[\r\n]startxref[\s]*[\r\n]+([0-9]+)[\s]*[\r\n]+%%EOF/i',$pdfContent,$matches,\PREG_SET_ORDER,0))){return FALSE;}
        return TRUE;
	}

	public function exportEntries($selectors,$isSystemCall=FALSE,$maxAttachedFilesize=10000000000){
		$statistics=array('added entries'=>0,'added files'=>0,'Attached filesize'=>0,'tables'=>array(),'Errors'=>array());
		if (isset($selectors['Source'])){$selectors=array($selectors);}
		$pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
		$fileName=preg_replace('/\W+/','_',$pageSettings['pageTitle']).' dump.zip';
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
		$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>$msg,'priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		return $dumpFile;
	}
	
	public function downloadExportedEntries($selectors,$isSystemCall=FALSE,$maxAttachedFilesize=10000000000,$fileName=FALSE){
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
	
	public function importEntries($dumpFile,$isSystemCall=FALSE){
		$statistics=array('zip errors'=>0,'json decode errors'=>0,'entries updated'=>0,'attached files added'=>0);
		$dir=$this->getTmpDir();
		$zip = new \ZipArchive;
		if ($zip->open($dumpFile)===TRUE){
			$zip->extractTo($dir);
			$zip->close();
			$files=scandir($dir);
			foreach($files as $fileName){
				$file=$dir.$fileName;
				if (!is_file($file)){continue;}
				if (strpos($fileName,'.json')===FALSE){continue;}
				$fileContent=file_get_contents($file);
				$entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr($fileContent);
				if (!$entry){
					$statistics['json decode errors']++;
					continue;
				}
				$statistics['entries updated']++;
				$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry,$isSystemCall);
				$source=$dir.$entry['Source'].'~'.$entry['EntryId'].'.file';
				$target=$this->selector2file($entry);
				if (is_file($source)){
					$statistics['attached files added']++;
					$this->tryCopy($source,$target,0750);
				}
			}
		} else {
			$statistics['zip errors']++;
		}
		$msg='Import resulted in '.$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str($statistics);
		$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>$msg,'priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		return $statistics;
	}
	
}
?>
