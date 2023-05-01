<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\AdminApps;

class Admin{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Owner'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'SYSTEM','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.')
								 );
    
	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}

	public function init($arr){
		$this->arr=$arr;
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		return $this->arr;
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
			$html=$this->logsArticle();
			$html.=$this->backupArticle();
			$arr['toReplace']['{{content}}']=$html;
			return $arr;
		}
	}
	
	public function logsArticle(){
		$selector=array('Source'=>$this->arr['SourcePot\Datapool\Foundation\Logging']->getEntryTable());
		$settings=array();
		$settings['columns']=array(array('Column'=>'Date','Filter'=>''),array('Column'=>'Type','Filter'=>'log'),array('Column'=>'Content|[]|Message','Filter'=>''));
		return $this->arr['SourcePot\Datapool\Foundation\Container']->container('Log entries','entryList',$selector,$settings,array());
	}
	
	public function backupArticle(){
		// form processing
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,__FUNCTION__);
		$this->arr['SourcePot\Datapool\Foundation\Database']->resetStatistic();
		if (isset($formData['cmd']['export'])){
			$selectors=array($formData['val']);
			$dumpFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->exportEntries($selectors,FALSE,$formData['val']['Size']);
			if (is_file($dumpFile)){
				header('Content-Type: application/zip');
				header('Content-Disposition: attachment; filename="'.date('Y-m-d').' '.$formData['val']['Source'].' dump.zip"');
				header('Content-Length: '.fileSize($dumpFile));
				readfile($dumpFile);
			}	
		} else if (isset($formData['cmd']['import'])){
			$tmpFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->getTmpDir().'tmp.zip';
			if (!empty($formData['files']['import'])){
				$success=move_uploaded_file($formData['files']['import'][0]['tmp_name'],$tmpFile);
				if ($success){$this->arr['SourcePot\Datapool\Foundation\Filespace']->importEntries($tmpFile);}
			} else {
				$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Import file missing','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
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
		$entryTemplates=$this->arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplate();
		foreach($entryTemplates as $table=>$entryTemplate){
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
		$matrix['Backup to file']=array('Input'=>$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($tableSelect).$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($sizeSelect),
								'Cmd'=>$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr));
		// import html		
		$fileArr=$btnArr;
		$fileArr['tag']='input';
		$fileArr['type']='file';
		$fileArr['multiple']=TRUE;
		$fileArr['key']=array('import');
		$btnArr['cmd']='import';
		$btnArr['hasCover']=TRUE;
		$matrix['Recover from file']=array('Input'=>$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($fileArr),'Cmd'=>$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr));
		$tableHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Backup / recover','hideKeys'=>FALSE,'hideHeader'=>TRUE));
		return $this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'article','element-content'=>$tableHtml,'keep-element-content'=>TRUE));
	}
	
}
?>