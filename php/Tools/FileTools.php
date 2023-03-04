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

namespace Datapool\Tools;

class FileTools{

	private $arr;
	
	const ENV_FILE='env.json';

	private $entryTemplate=array('Type'=>array('type'=>'string','value'=>'array','Description'=>'This is the data-type of Content'),
								 'Date'=>array('type'=>'datetime','value'=>'{{NOW}}','Description'=>'This is the entry date and time'),
								 'Content'=>array('type'=>'json','value'=>array(),'Description'=>'This is the entry Content, the structure of depends on the MIME-type.'),
								 'Read'=>array('type'=>'int','value'=>FALSE,'Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('type'=>'int','value'=>FALSE,'Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
								 'Owner'=>array('type'=>'string','value'=>'SYSTEM','Description'=>'This is the Owner\'s ElementId or SYSTEM. The Owner has Read and Write access.')
								 );

    
	private $statistics=array();
	
	public function __construct($arr){
		$this->arr=$arr;
		$this->resetStatistics();
	}
	
	public function init($arr){
		$this->arr=$arr;
		return $this->arr;
	}
		
	public function getEntryTemplate(){
		return $this->entryTemplate;
	}
	
	public function resetStatistics(){
		$this->statistics=array('matched files'=>0,'updateed files'=>0,'deleted files'=>0,'deleted dirs'=>0,'inserted files'=>0,'added dirs'=>0);
		return $this->statistics;
	}
	
	public function getStatistics(){
		return $this->statistics;
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

	public function class2className($class){
		$comps=explode('\\',$class);
		return array_pop($comps);
	}

	private function class2dir($class,$mkDirIfMissing=FALSE){
		$class=$this->class2className($class);
		$dir=$GLOBALS['setup dir'].$class.'/';
		if (!file_exists($dir) && $mkDirIfMissing){
			$mkDir=trim($dir,'/');
			mkdir($mkDir,0750);
		}
		return $dir;	
	}

	private function source2dir($source,$mkDirIfMissing=TRUE){
		// This function returns the filespace directory based on the tablename provided.
		$source=explode('\\',$source);
		$source=array_pop($source);
		// select dir and create the directory if neccessary
		$dir=$GLOBALS['filespace dir'];
		if (!file_exists($dir) && $mkDirIfMissing){
			$mkDir=rtrim($dir,'/');
			mkdir($mkDir,0750);
		}
		$dir.=$source.'/';
		if (!file_exists($dir) && $mkDirIfMissing){
			$mkDir=rtrim($dir,'/');
			mkdir($mkDir,0750);
		}
		return $dir;
	}
	
	public function selector2file($selector,$mkDirIfMissing=TRUE){
		if (!empty($selector['Source']) && !empty($selector['ElementId'])){
			$dir=$this->source2dir($selector['Source'],$mkDirIfMissing);	
			$file=$selector['ElementId'].'.file';
		} else if (!empty($selector['Class']) && !empty($selector['SettingName'])){
			$dir=$this->class2dir($selector['Class'],$mkDirIfMissing);	
			$file=$selector['SettingName'].'.json';
		} else {
			throw new \ErrorException('Function '.__FUNCTION__.': Mandatory keys missing in selector argument, either Source, ElementId  or Class, SettingName',0,E_ERROR,__FILE__,__LINE__);	
		}
		return $fileName=$dir.$file;
	}
	
	private function unifyEntry($entry){
		// remove all keys from entry, not provided by entryTemplate
		foreach($entry as $key=>$value){
			if (!isset($this->entryTemplate[$key])){unset($entry[$key]);}
		}
		// add defaults at missing keys
		foreach($this->entryTemplate as $key=>$defArr){
			if (!isset($entry[$key])){$entry[$key]=$defArr['value'];}
			$entry[$key]=$this->arr['Datapool\Tools\StrTools']->stdReplacements($entry[$key]);
		}
		$entry=$this->arr['Datapool\Foundation\Access']->addRights($entry,'ADMIN_R','ADMIN_R');
		return $entry;
	}
	
	public function entryByKey($selector,$isSystemCall=FALSE,$rightType='Read',$returnMetaOnNoMatch=FALSE){
		// This method returns the entry from a setup-file selected by the selector arguments.
		// The selector argument is an array which must contain at least the array-keys 'Class' and 'SettingName'.
		//
		$entry=array('rowCount'=>0,'rowIndex'=>0,'access'=>'NO ACCESS RESTRICTION');
		$entry['file']=$this->selector2file($selector);
		$arr=$this->file2arr($entry['file']);
		$entry['rowCount']=intval($arr);
		if (!empty($arr)){
			$entry['rowCount']=1;
			if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
			$entry['access']=$this->arr['Datapool\Foundation\Access']->access($arr,$rightType,$user,$isSystemCall);
			if ($entry['access']){
				$entry=array_merge($entry,$arr);
				return $entry;
			}
		}
		if ($returnMetaOnNoMatch){return $entry;} else {return FALSE;}
	}
	
	public function insertEntry($entry){
		if (empty($entry['Class']) || empty($entry['SettingName'])){
			throw new \ErrorException('Function '.__FUNCTION__.': Mandatory keys missing in entry argument, i.e. Class and SettingName',0,E_ERROR,__FILE__,__LINE__);		
		}
		$existingEntry=$this->entryByKey($entry,TRUE,'Read',TRUE);
		if (empty($existingEntry['rowCount'])){
			// insert entry
			$dir=$this->class2dir($entry['Class'],TRUE);	
			$entry=$this->unifyEntry($entry);
			$this->arr['Datapool\Tools\ArrTools']->arr2file($entry,$existingEntry['file']);
			$this->statistics['inserted files']++;
			return $entry;
		} else {
			// do nothing if entry exsits
			$this->statistics['matched files']++;
			return FALSE;
		}
	}
	
	public function updateEntry($entry,$isSystemCall=FALSE){
		// This method updates and returns the entry of setup-file selected by the entry arguments.
		// The selector argument is an array which must contain at least the array-keys 'Class' and 'SettingName'.
		// 
		$insertedEntry=$this->insertEntry($entry);
		if ($insertedEntry){
			return $insertedEntry;
		} else {
			$existingEntry=$this->entryByKey($entry,TRUE,'Read',TRUE);
			if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
			if ($this->arr['Datapool\Foundation\Access']->access($existingEntry,'Write',$user,$isSystemCall)){
				// write access update exsisting entry
				$entry=array_merge($existingEntry,$entry);
				$dir=$this->class2dir($entry['Class'],TRUE);	
				$entry=$this->unifyEntry($entry);
				$this->arr['Datapool\Tools\ArrTools']->arr2file($entry,$existingEntry['file']);
				$this->statistics['inserted files']++;
				return $entry;
			} else if ($this->arr['Datapool\Foundation\Access']->access($existingEntry,'Read',$user,$isSystemCall)){
				// read access only
				return $existingEntry;
			} else {
				// no access
				return array('rowCount'=>1,'rowIndex'=>0,'access'=>FALSE);
			}
		}
	}
	
	public function entryByKeyCreateIfMissing($entry,$isSystemCall=FALSE){
		$insertedEntry=$this->insertEntry($entry);
		if ($insertedEntry){
			return $insertedEntry;
		} else {
			return $this->entryByKey($entry,$isSystemCall,'Read',TRUE);
		}
	}

	public function fileErrorCode2str($code){
		$codeArr=array(0=>'There is no error, the file uploaded with success',
					   1=>'The uploaded file exceeds the upload_max_filesize directive in php.ini',
					   2=>'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
					   3=>'The uploaded file was only partially uploaded',
					   4=>'No file was uploaded',
					   6=>'Missing a temporary folder',
					   7=>'Failed to write file to disk.',
					   8=>'A PHP extension stopped the file upload.',
					   );
		$code=intval($code);
		if (isset($codeArr[$code])){return $codeArr[$code];} else {return '';}
	}
	
	public function fileUpload2entry($entry,$skipMoveUpload=FALSE){
		if (empty($entry['Name'])){$entry['Name']=$entry['file']['name'];}
		if (empty($entry['ElementId'])){
			$entry=$this->arr['Datapool\Tools\StrTools']->addElementId($entry,array('Source','Group','Folder','Name','Type','Date','Owner'));
		}
		$file=$this->selector2file($entry,TRUE);
		$fileArr=$entry['file'];
		if (empty($skipMoveUpload)){$success=move_uploaded_file($fileArr['tmp_name'],$file);} else {$success='Skiped upload';}
		if ($success && is_file($file)){
			$this->statistics['inserted files']++;
			$entry['Params']['File']=array('Name'=>$fileArr['name'],
										   'Size'=>$fileArr['size'],
										   'Extension'=>pathinfo($fileArr['name'],PATHINFO_EXTENSION),
										   'Download'=>$this->arr['Datapool\Tools\StrTools']->getDateTime(),
										   'DownloaderId'=>$_SESSION['currentUser']['ElementId'],
										   'DownloaderName'=>$_SESSION['currentUser']['Name'],
										   'MIME-Type'=>mime_content_type($file),
										   'Style class'=>'',
										   );
			$entry=$this->arr['Datapool\Tools\ExifTools']->addExif2entry($entry,$file);
			$entry=$this->arr['Datapool\Tools\GeoTools']->location2address($entry);
			$mimeType=str_replace('/',' ',$entry['Params']['File']['MIME-Type']);
			if (empty($entry['Type'])){$entry['Type']=$entry['Source'];}
			$entry['Type'].=' '.$mimeType;
		}
		return $entry;
	}
	
	public function entry2fileDownload($entry){
		if (empty($entry['ElementId'])){
			$zipName=date('Y-m-d His').' bulk download.zip';
			$zipFile=$this->getTmpDir().$zipName;
			$zip = new \ZipArchive;
			$zip->open($zipFile,\ZipArchive::CREATE);
			$selector=$entry;
			foreach($this->arr['Datapool\Foundation\Database']->entryIterator($selector) as $entry){
				$file=$this->selector2file($entry);
				if (!is_file($file)){continue;}
				$zip->addFile($file,str_replace('_', '-',basename($entry['Params']['File']['Name'])));
			}
			$zip->close();
			$entry=array('Params'=>array('File'=>array('Extension'=>'zip','Name'=>$zipName)));
			$fileForDownload=$zipFile;
		} else {
			$entry=$this->arr['Datapool\Foundation\Database']->entryByKey($entry);
			$fileForDownload=$this->selector2file($entry);
		}
		if (is_file($fileForDownload)){
			header('Content-Type: application/'.$entry['Params']['File']['Extension']);
			header('Content-Disposition: attachment; filename="'.$entry['Params']['File']['Name'].'"');
			header('Content-Length: '.fileSize($fileForDownload));
			readfile($fileForDownload);
			exit;
		} else {
			$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'No file found to download.','priority'=>12,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
	}
	
	public function getTmpDir(){
		if (!is_dir($GLOBALS['tmp dir'])){$this->statistics['added dirs']+=intval(mkdir($GLOBALS['tmp dir'],0775));}
		if (!isset($_SESSION[__CLASS__]['tmpDir'])){
			$_SESSION[__CLASS__]['tmpDir']=$this->arr['Datapool\Tools\StrTools']->getRandomString(20);
			$_SESSION[__CLASS__]['tmpDir'].='/';
		}
		$tmpDir=$GLOBALS['tmp dir'].$_SESSION[__CLASS__]['tmpDir'];
		if (!is_dir($tmpDir)){$this->statistics['added dirs']+=intval(mkdir($tmpDir,0775));}
		return $tmpDir;
	}
	
	public function removeTmpDir(){
		$maxAge=86400;
		$allDirs=scandir($GLOBALS['tmp dir']);
		foreach($allDirs as $dirIndex=>$dir){
			$fullDir=$GLOBALS['tmp dir'].$dir;
			if (!is_dir($fullDir) || strlen($dir)<4){continue;}
			$age=time()-filemtime($fullDir);
			if ($age>$maxAge){
				$this->delDir($fullDir);
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
		$rel=str_replace($GLOBALS['base dir'],'../',$file);
		if (strpos($GLOBALS['base dir'],'xampp')===FALSE){
			// productive environment -> dir entrypoint should be /wwww/ directory
			$rel=str_replace('www/','',$rel);
		} else {
			// development environment
		}
		return $rel;
	}

	public function rel2abs($file){
		$abs=str_replace('../',$GLOBALS['base dir'],$file);
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
	
	public function exportEntries($selectors,$isSystemCall=FALSE,$maxAttachedFilesize=10000000000){
		$statistics=array('added entries'=>0,'added files'=>0,'Attached filesize'=>0,'tables'=>array(),'Errors'=>array());
		if (isset($selectors['Source'])){$selectors=array($selectors);}
		$pageSettings=$this->arr['Datapool\Tools\HTMLbuilder']->getSettings();
		$fileName=preg_replace('/\W+/','_',$pageSettings['pageTitle']).' dump.zip';
		$dir=$this->getTmpDir();
		$dumpFile=$dir.$fileName;
		if (is_file($dumpFile)){unlink($dumpFile);}
		$attachedFiles=array();
		$zip = new \ZipArchive;
		$zip->open($dumpFile,\ZipArchive::CREATE);
		foreach($selectors as $index=>$selector){
			foreach($this->arr['Datapool\Foundation\Database']->entryIterator($selector,$isSystemCall) as $entry){
				$attachedFileName=$entry['Source'].'~'.$entry['ElementId'].'.file';
				$attachedFile=$this->selector2file($entry);
				if (is_file($attachedFile)){
					$statistics['Attached filesize']+=filesize($attachedFile);
					$attachedFiles[]=array('attachedFile'=>$attachedFile,'attachedFileName'=>$attachedFileName);
				}
				$jsonFileContent=$this->arr['Datapool\Tools\ArrTools']->arr2json($entry);
				$jsonFileName=$entry['Source'].'~'.$entry['ElementId'].'.json';
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
		$statistics['Attached filesize']=$this->arr['Datapool\Tools\StrTools']->float2str($statistics['Attached filesize'],2,1024);
		$msg='Export resulted in '.$this->arr['Datapool\Tools\ArrTools']->statistic2str($statistics);
		$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>$msg,'priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		return $dumpFile;
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
				$entry=$this->arr['Datapool\Tools\ArrTools']->json2arr($fileContent);
				if (!$entry){
					$statistics['json decode errors']++;
					continue;
				}
				$statistics['entries updated']++;
				$this->arr['Datapool\Foundation\Database']->updateEntry($entry,$isSystemCall);
				$source=$dir.$entry['Source'].'~'.$entry['ElementId'].'.file';
				$target=$this->selector2file($entry);
				if (is_file($source)){
					$statistics['attached files added']++;
					$this->tryCopy($source,$target,0750);
				}
			}
		} else {
			$statistics['zip errors']++;
		}
		$msg='Import resulted in '.$this->arr['Datapool\Tools\ArrTools']->statistic2str($statistics);
		$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>$msg,'priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		return $statistics;
	}
	
}
?>
