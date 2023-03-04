<?php
declare(strict_types=1);

namespace Datapool\AdminApps;

class Admin{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Owner'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'SYSTEM','Description'=>'This is the Owner\'s ElementId or SYSTEM. The Owner has Read and Write access.')
								 );
    
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}

	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		return $this->arr;
	}

	public function job($vars){
		return $vars;
	}

	public function getEntryTable(){
		return $this->entryTable;
	}
	
	public function getEntryTemplate(){
		return $this->entryTemplate;
	}

	public function run($arr=TRUE){
		if ($arr===TRUE){
			return array('Category'=>'Admin','Emoji'=>'&#9781;','Label'=>'Admin','Read'=>'ADMIN_R','Class'=>__CLASS__);
		} else {
			$arr=$this->logsArticle($arr);
			$arr=$this->backupArticle($arr);
			$arr['page html']=str_replace('{{content}}',$arr['html'],$arr['page html']);
			return $arr;
		}
	}
	
	public function logsArticle($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$selector=array('Source'=>$this->arr['Datapool\Foundation\Logging']->getEntryTable());
		$arr['html']=$this->arr['Datapool\Foundation\Container']->container('Log entries','entryList',$selector,array(),array());
		return $arr;
	}
	
	public function backupArticle($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		// form processing
		$formData=$this->arr['Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$this->arr['Datapool\Foundation\Database']->resetStatistic();
		if (isset($formData['cmd']['export'])){
			$selectors=array($formData['val']);
			$dumpFile=$this->arr['Datapool\Tools\FileTools']->exportEntries($selectors,FALSE,$formData['val']['Size']);
			if (is_file($dumpFile)){
				header('Content-Type: application/zip');
				header('Content-Disposition: attachment; filename="'.date('Y-m-d').' '.$formData['val']['Source'].' dump.zip"');
				header('Content-Length: '.fileSize($dumpFile));
				readfile($dumpFile);
			}	
		} else if (isset($formData['cmd']['import'])){
			$tmpFile=$this->arr['Datapool\Tools\FileTools']->getTmpDir().'tmp.zip';
			if (!empty($formData['files']['import'])){
				$success=move_uploaded_file($formData['files']['import'][0]['tmp_name'],$tmpFile);
				if ($success){$this->arr['Datapool\Tools\FileTools']->importEntries($tmpFile);}
			} else {
				$this->arr['Datapool\Foundation\Logging']->addLog(array('msg'=>'Import file missing','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			}
		}
		// export html
		$matrix=array();
		$attachedFileSizeOptions=array(0=>'Skip attached files',
									   1000000=>'Skip files if >1 MB',
									   10000000=>'Skip files if >10 MB',
									   100000000=>'Skip files if >100 MB',
									   1000000000=>'Skip files if >1 GB',
									   10000000000=>'Skip files if >10 GB'
									   );
		$tables=array(''=>'none');
		$dbInfo=$this->arr['Datapool\Foundation\Database']->getDbInfo();
		foreach($dbInfo as $table=>$infoArr){
			$tables[$table]=ucfirst($table);
		}
		$btnArr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'style'=>array('float'=>'left','clear'=>'both'));
		$tableSelect=$btnArr;
		$tableSelect['key']=array('Source');
		$tableSelect['options']=$tables;
		$sizeSelect=$btnArr;
		$sizeSelect['key']=array('Size');
		$sizeSelect['selected']=10000000;
		$sizeSelect['options']=$attachedFileSizeOptions;
		$btnArr['cmd']='export';
		$matrix['Backup to file']=array('Input'=>$this->arr['Datapool\Tools\HTMLbuilder']->select($tableSelect).$this->arr['Datapool\Tools\HTMLbuilder']->select($sizeSelect),
								'Cmd'=>$this->arr['Datapool\Tools\HTMLbuilder']->btn($btnArr));
		// import html		
		$fileArr=$btnArr;
		$fileArr['tag']='input';
		$fileArr['type']='file';
		$fileArr['multiple']=TRUE;
		$fileArr['key']=array('import');
		$btnArr['cmd']='import';
		$btnArr['hasCover']=TRUE;
		$matrix['Recover from file']=array('Input'=>$this->arr['Datapool\Tools\HTMLbuilder']->element($fileArr),'Cmd'=>$this->arr['Datapool\Tools\HTMLbuilder']->btn($btnArr));
		$tableHtml=$this->arr['Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Backup / recover','hideKeys'=>FALSE,'hideHeader'=>TRUE));
		$arr['html'].=$this->arr['Datapool\Tools\HTMLbuilder']->element(array('tag'=>'article','element-content'=>$tableHtml,'keep-element-content'=>TRUE));
		return $arr;
	}
	
}
?>