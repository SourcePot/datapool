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
	
	private $oc;
	private $entryTable='';
	
	public function __construct($oc){
		$this->oc=$oc;
		$this->entryTable=$this->oc['SourcePot\Datapool\Foundation\Logging']->getEntryTable();
	}

	public function init($oc){
		$this->oc=$oc;
	}

	public function run($arr=TRUE){
		if ($arr===TRUE){
			return array('Category'=>'Admin','Emoji'=>'&#9781;','Label'=>'Admin','Read'=>'ADMIN_R','Class'=>__CLASS__);
		} else {
			$arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
			$html='';
			$html.=$this->tableViewer();
			$html.=$this->backupArticle();
			$html.=$this->adminChart();
			$arr['toReplace']['{{content}}']=$html;
			return $arr;
		}
	}
	
	public function tableViewer(){
		$selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
		$html='';
		if (empty($selector['Source'])){
			$element=array('tag'=>'p','element-content'=>'Nothing selected, so there is nothing to show here...');
			$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
		} else {
			$settings['columns']=array(array('Column'=>'Date','Filter'=>''),array('Column'=>'Name','Filter'=>''),array('Column'=>'preview','Filter'=>''));
			$settings['columns']=array(array('Column'=>'Date','Filter'=>''),array('Column'=>'Type','Filter'=>''),array('Column'=>'Name','Filter'=>''));
			$html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container(ucfirst($selector['Source']).' entries','selectedView',$selector,$settings,array());
		}
		return $this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE));
		return $html;
	}
	
	public function backupArticle(){
		// form processing
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
		$this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
		if (isset($formData['cmd']['export'])){
			$selectors=array($formData['val']);
			$pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
			$fileName=date('Y-m-d H_i_s').' '.$pageSettings['pageTitle'].' '.current($selectors)['Source'].' dump.zip';
			$this->oc['SourcePot\Datapool\Foundation\Filespace']->downloadExportedEntries($selectors,FALSE,$formData['val']['Size'],$fileName);	
		} else if (isset($formData['cmd']['import'])){
			$tmpFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir().'tmp.zip';
			if (!empty($formData['files']['import'])){
				$success=move_uploaded_file($formData['files']['import'][0]['tmp_name'],$tmpFile);
				if ($success){$this->oc['SourcePot\Datapool\Foundation\Filespace']->importEntries($tmpFile);}
			} else {
				$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Import file missing','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
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
		$entryTemplates=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate();
		foreach($entryTemplates as $table=>$entryTemplate){
			$tables[$table]=ucfirst($table);
		}
		$btnArr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'tag'=>'button','keep-element-content'=>TRUE,'style'=>array('float'=>'left','clear'=>'both'),'excontainer'=>TRUE);
		$tableSelect=$btnArr;
		$tableSelect['style']=array('margin'=>'0.4em 0.2em');
		$tableSelect['key']=array('Source');
		$tableSelect['options']=$tables;
		$sizeSelect=$btnArr;
		$sizeSelect['key']=array('Size');
		$sizeSelect['selected']=10000000;
		$sizeSelect['options']=$attachedFileSizeOptions;
		$btnArr['key']=array('export');
		$btnArr['element-content']='Export';
		$matrix['Backup to file']=array('Input'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($tableSelect).$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($sizeSelect),
										'Cmd'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr));
		// import html		
		$fileArr=$btnArr;
		unset($fileArr['element-content']);
		$fileArr['tag']='input';
		$fileArr['type']='file';
		$fileArr['multiple']=TRUE;
		$fileArr['key']=$btnArr['key']=array('import');
		$btnArr['element-content']='Import';
		$btnArr['hasCover']=TRUE;
		$matrix['Recover from file']=array('Input'=>$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileArr),
										   'Cmd'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr));
		$tableHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Backup / recover','hideKeys'=>FALSE,'hideHeader'=>TRUE));
		return $this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$tableHtml,'keep-element-content'=>TRUE));
	}
	
	private function adminChart(){
		$html=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Test cahrt','generic',array('refreshInterval'=>10),array('classWithNamespace'=>'SourcePot\Datapool\Foundation\LinearChart','method'=>'getTestChart'),array());	
		return $html;
	}
}
?>