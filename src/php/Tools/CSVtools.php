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

class CSVtools{

	private $arr;
	
	private $csvTimestamp=FALSE;
	
	private $csvAlias=array(1=>array('chr'=>';','label'=>';','validSeparator'=>TRUE,'validEnclosure'=>FALSE,'validLineSeparator'=>FALSE,'validEscape'=>FALSE),
						    2=>array('chr'=>',','label'=>',','validSeparator'=>TRUE,'validEnclosure'=>FALSE,'validLineSeparator'=>FALSE,'validEscape'=>FALSE),
							3=>array('chr'=>"\t",'label'=>'Tabulator','validSeparator'=>TRUE,'validEnclosure'=>FALSE,'validLineSeparator'=>FALSE,'validEscape'=>FALSE),
							4=>array('chr'=>'"','label'=>'"','validSeparator'=>FALSE,'validEnclosure'=>TRUE,'validLineSeparator'=>FALSE,'validEscape'=>FALSE),
							5=>array('chr'=>"'",'label'=>"'",'validSeparator'=>FALSE,'validEnclosure'=>TRUE,'validLineSeparator'=>FALSE,'validEscape'=>FALSE),
							6=>array('chr'=>"\\",'label'=>"Backslash",'validSeparator'=>FALSE,'validEnclosure'=>FALSE,'validLineSeparator'=>FALSE,'validEscape'=>TRUE),
							7=>array('chr'=>"\n",'label'=>"Newline",'validSeparator'=>FALSE,'validEnclosure'=>FALSE,'validLineSeparator'=>TRUE,'validEscape'=>FALSE),
							8=>array('chr'=>"\r",'label'=>"Carriage return",'validSeparator'=>FALSE,'validEnclosure'=>FALSE,'validLineSeparator'=>TRUE,'validEscape'=>FALSE),
							);
	private $csvSettings=array('offset'=>0,'limit'=>5,'enclosure'=>4,'separator'=>1,'escape'=>6,'lineSeparator'=>7,'noEnclosureOutput'=>TRUE,'mode'=>0);
    
	public function __construct($arr){
		$this->arr=$arr;
		$this->csvTimestamp=time();
	}
	
	public function init($arr){
		$this->arr=$arr;
		$this->entry2csv();
		return $this->arr;
	}
	
	public function isCSV($selector){
		$file=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector);
		if (!is_file($file)){return FALSE;}
		if (strpos(mime_content_type($file),'text/')!==0){return FALSE;}
		foreach($this->csvIterator($selector) as $rowIndex=>$rowArr){
			if (count($rowArr)>1){
				//change file content encoding to utf-8 if encoding is different from utf-8
				$csvContent=file_get_contents($file);
				$sourceEncoding=mb_detect_encoding($csvContent,["ASCII","ISO-8859-1","JIS","ISO-2022-JP","UTF-7","UTF-8",],TRUE);
				if ($sourceEncoding!=='UTF-8'){
					$csvContent=mb_convert_encoding($csvContent,"UTF-8",$sourceEncoding);
					file_put_contents($file,$csvContent);
					$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Changed file content encoding from '.$sourceEncoding.' to UTF-8','priority'=>3,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));	
				}
				return TRUE;
			}
			break;
		}
		return FALSE;
	}
	
	public function alias($index=FALSE,$validate='separator'){
		// This method returns an alias based on the provided $index.
		// If $index is FALSE, an array of options is returned based on the labels in $csvAlias.
		// The options are filtered by the $validate argument.
		// If $index is >0 the alias char is returned if $validate is empty or label if $validate is not empty.
		if ($index===FALSE){
			$result=array();
			foreach($this->csvAlias as $index=>$aliasArr){
				$validatorKey='valid'.ucfirst($validate);
				if (empty($aliasArr[$validatorKey])){continue;}
				$result[$index]=$aliasArr['label'];
			}
			return $result;
		} else {
			if (empty($validate)){
				return $this->csvAlias[$index]['chr'];	
			} else {
				return $this->csvAlias[$index]['label'];
			}
		}
	}
	
	public function getSettings(){
		return $this->csvSettings;
	}
	
	public function setSetting($setting){
		$this->csvSettings=array_merge($this->csvSettings,$setting);
		return $this->csvSettings;
	}
	
	private function csvSetting(){
		$csvSettings=array();
		foreach($this->csvSettings as $settingKey=>$settingValueIndex){
			if (strcmp($settingKey,'offset')===0 || strcmp($settingKey,'limit')===0 || strcmp($settingKey,'mode')===0){continue;}
			$csvSettings[$settingKey]=$this->csvAlias[$settingValueIndex]['chr'];
		}
		return $csvSettings;
	}
	
	public function csvIterator($selector){
		$csvFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector);
		if (!is_file($csvFile)){yield array();}
		$csvSettings=$this->csvSetting();
		$csv=new \SplFileObject($csvFile);
		$csv->setCsvControl($csvSettings['separator'],$csvSettings['enclosure'],$csvSettings['escape']);
		$keys=array();
		$rowIndex=0;
		while($csv->valid()){
			$csvArr=$csv->fgetcsv();
			$result=array();
			foreach($csvArr as $columnIndex=>$cellValue){
				if (isset($keys[$columnIndex])){
					$result[$keys[$columnIndex]]=$cellValue;
				} else {
					$keys[$columnIndex]=$cellValue;
				}
			}
			if ($rowIndex!==0){yield $result;}
			$csv->next();
			$rowIndex++;
		}
	}
	
	public function entry2csv($entry=FALSE,$rowId=FALSE){
		// When called with an object this method adds the object to a session var space for later
		// csv-file creation. When the class is created the session var space will be written to respective csv-file-objects
		// csv-file name = $entry['Name'], if $entry['EntryId'] is not set it will be created from $entry['Name']
		//$_SESSION['csvVarSpace']=array();
		if (empty($entry) && isset($_SESSION['csvVarSpace'])){
			$statistics=array('csv entries'=>0,'row count'=>0);
			$csvSetting=$this->csvSetting();
			if (!empty($csvSetting['noEnclosureOutput'])){$csvSetting['enclosure']='';}
			$prodessedEntries=0;
			foreach($_SESSION['csvVarSpace'] as $EntryId=>$csvDefArr){
				$csvContent='';
				$entry=$csvDefArr['entry'];
				foreach($csvDefArr['rows'] as $rowIndex=>$valArr){
					$csvLineArr=$this->getCsvRow($valArr,$csvSetting);
					if ($rowIndex===0){$csvContent.=$csvLineArr['header'];}
					$csvContent.=$csvLineArr['line'];
					$statistics['row count']++;
				}
				// save csv content
				$targetFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
				file_put_contents($targetFile,$csvContent);
				if (empty($entry['Params']['File']['Name'])){$entry['Params']['File']['Name']=str_replace('.csv','',$entry['Name']).'.csv';}
				$entry['Params']['File']['Size']=filesize($targetFile);
				$entry['Params']['File']['Extension']='csv';
				$entry['Params']['File']['MIME-Type']='text/csv';
				$entry['Date']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getDateTime();
				$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
				// reset csv var-space
				unset($_SESSION['csvVarSpace'][$EntryId]);
				$statistics['csv entries']++;
				$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'CSV-entry created named "'.$entry['Name'].'" containing '.count($csvDefArr['rows']).' rows.','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
			}
			return $statistics;
		} else if (isset($entry['Content'])){
			$entry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,array('Source','Group','Folder'),$this->csvTimestamp,'',TRUE);
			$elementId=$entry['EntryId'];
			$flatContentArr=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry['Content']);
			if (!isset($_SESSION['csvVarSpace'][$elementId])){
				$_SESSION['csvVarSpace'][$elementId]=array('rows'=>array(),'entry'=>$entry,'first row'=>$flatContentArr);
			}
			$row=array();
			foreach($_SESSION['csvVarSpace'][$elementId]['first row'] as $column=>$firstRowValue){
				if (isset($flatContentArr[$column])){$row[$column]=$flatContentArr[$column];} else {$row[$column]='?';}
			}
			$_SESSION['csvVarSpace'][$elementId]['rows'][]=$row;
			return $entry;
		} else if (!isset($_SESSION['csvVarSpace'])){
			// nothing to do
			$_SESSION['csvVarSpace']=array();
		} else {
			$trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
			$this->arr['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Method "'.__FUNCTION__.'" called by "'.$trace[1]['function'].'" without content','priority'=>20,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
		return FALSE;
	}
	
	private function getCsvRow($row,$csvSetting){
		$result=array('header'=>'','line'=>'');
		$valueStr='';
		foreach($row as $column=>$value){
			$result['header'].=$csvSetting['enclosure'].$column.$csvSetting['enclosure'].$csvSetting['separator'];
			if (is_bool($value)){
				if ($value){$value='TRUE';} else {$value='FALSE';}
				$result['line'].=$csvSetting['enclosure'].$value.$csvSetting['enclosure'].$csvSetting['separator'];
			} else if (is_numeric($value)){
				$result['line'].=$csvSetting['enclosure'].$value.$csvSetting['enclosure'].$csvSetting['separator'];
			} else {
				$result['line'].=$csvSetting['enclosure'].$value.$csvSetting['enclosure'].$csvSetting['separator'];
			}
		}
		$result['header']=trim($result['header'],$csvSetting['separator']);
		$result['line']=trim($result['line'],$csvSetting['separator']);
		$result['header'].=$csvSetting['lineSeparator'];
		$result['line'].=$csvSetting['lineSeparator'];
		return $result;
	}
	
	public function csvEditor($arr,$isDebugging=FALSE){
		if (!isset($arr['html'])){$arr['html']='';}
		if (!isset($_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']])){$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$arr['settings'];}
		$settings=$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']];
		$debugArr=array('arr in'=>$arr,'settings in'=>$settings,'valuesToUpdate'=>array());
		$attachedFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr['selector']);
		if (!is_file($attachedFile)){return $arr;}
		$csvEntry=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
		// get settings
		foreach($this->getSettings() as $settingKey=>$settingValue){
			if (!isset($settings[$settingKey])){$settings[$settingKey]=$settingValue;}
		}
		// form processing
		$valuesTorUpdate=array();
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing($arr['callingClass'],$arr['callingFunction']);
		if (!empty($formData['cmd'])){
			// csv settings
			$settings=array_replace_recursive($settings,$formData['val']['settings']);
			$this->setSetting($settings);
			// csv valus
			if (isset($formData['val']['values'])){
				$valuesTorUpdate=$formData['val']['values'];
				unset($valuesTorUpdate['settings']);
			}
		}
		// create sample
		$S=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
		$columns=array();
		$rowCount=0;
		$rowLimitCount=0;
		$matrix=array();
		foreach($this->csvIterator($arr['selector']) as $rowIndex=>$rowArr){
			$csvEntry['Content']=$rowArr;
			if ($rowLimitCount<$settings['limit'] && $rowIndex>=$settings['offset']){
				foreach($rowArr as $column=>$cellValue){
					$columns['Content'.$S.$column]=$column;
					if (empty($settings['mode'])){
						// show cell values
						$valArr=$cellValue;
					} else {
						// edit cell values
						if (isset($valuesTorUpdate[$rowIndex][$column])){$cellValue=$valuesTorUpdate[$rowIndex][$column];}
						$valArr=array('tag'=>'input','type'=>'text','key'=>array('values',$rowIndex,$column),'value'=>$cellValue,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'keep-element-content'=>TRUE);
					}
					$csvEntry['Content'][$column]=$cellValue;
					$matrix[$rowIndex][$column]=$valArr;
				}
				$rowLimitCount++;
			}
			if (!empty($valuesTorUpdate)){
				$debugArr['valuesToUpdate'][]=$csvEntry;
				$this->entry2csv($csvEntry);
			}
			$rowCount++;
		}
		if (!empty($debugArr['valuesToUpdate'])){$this->entry2csv();}
		$options=$this->alias(FALSE,'label');
		$caption='CSV sample:';
		$caption.=' "'.$csvEntry['Name'].'" ';
		$caption.=' ('.$rowCount.')';
		$sampleHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		// create control
		$selectArr=array('key'=>array('settings','limit'),'value'=>$settings['limit'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'keep-element-content'=>TRUE);
		$selectArr['options']=array(5=>'5',10=>'10',25=>'25',50=>'50');
		$limitSelector=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
		$selectArr['key']=array('settings','enclosure');
		$selectArr['value']=$settings['enclosure'];
		$selectArr['options']=$this->alias(FALSE,'enclosure');
		$enclosureSelector=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
		$selectArr['key']=array('settings','separator');
		$selectArr['value']=$settings['separator'];
		$selectArr['options']=$this->alias(FALSE,'separator');
		$separatorSelector=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
		$selectArr['key']=array('settings','lineSeparator');
		$selectArr['value']=$settings['lineSeparator'];
		$selectArr['options']=$this->alias(FALSE,'lineSeparator');
		$lineSeparatorSelector=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
		$selectArr['key']=array('settings','mode');
		$selectArr['value']=$settings['mode'];
		$selectArr['options']=array('Show','Edit');
		$modeSelector=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
		$matrix=array();
		$matrix['Cntr']['Offset']=array('tag'=>'input','type'=>'range','min'=>0,'max'=>($rowCount>$settings['limit'])?$rowCount>$settings['limit']:0,'value'=>$settings['offset'],'key'=>array('settings','offset'),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']);
		$matrix['Cntr']['Limit']=$limitSelector;
		if (empty($settings['mode'])){
			$matrix['Cntr']['Separator']=$separatorSelector;
			$matrix['Cntr']['Enclosure']=$enclosureSelector;
			$matrix['Cntr']['Line separator']=$lineSeparatorSelector;
		}
		$matrix['Cntr']['Mode']=$modeSelector;
		$caption='CSV control';
		$cntrHtml=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption));
		//
		$arr['html'].=$cntrHtml;
		$arr['html'].=$sampleHtml;
		if ($isDebugging){
			$debugArr['arr out']=$arr;
			$debugArr['settings out']=$settings;
			$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $arr;
	}
	
}
?>