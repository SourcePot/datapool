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

class CSVtools{

    private $oc;
    
    private $csvTimestamp=FALSE;

    public const CSV_SELECTOR=[
        'input'=>['Source'=>'settings','Group'=>__CLASS__,'Folder'=>'csv-settings','Name'=>'input'],
        'output'=>['Source'=>'settings','Group'=>__CLASS__,'Folder'=>'csv-settings','Name'=>'output'],
    ];

    public const CSV_CONTAINER_SETTINGS=[
        'method'=>'csvOutputSettingsWidget',
        'classWithNamespace'=>__CLASS__,
    ];
    
    private const SETTINGS_OPTIONS=[
        'limit'=>[5=>'5',10=>'10',25=>'25',50=>'50',100=>'100',250=>'250',500=>'500',1000=>'1000',2500=>'2500',],
        'separator'=>[','=>'Comma',';'=>'Semicolon',"\t"=>'Tabulator',],
        'enclosure'=>['"'=>'"',"'"=>"'",''=>'None'],
        'escape'=>[''=>'None','\\'=>'\\',],
        'lineSeparator'=>['CRLF'=>'Carriage return & line feed','LF'=>'Line feed','CR'=>'Carriage return',],
    ];

    private const SETTINGS_HIDE=[
        'offset'=>['input'=>FALSE,'output'=>TRUE,'editor'=>FALSE],
        'limit'=>['input'=>FALSE,'output'=>TRUE,'editor'=>FALSE],
        'enclosure'=>['input'=>FALSE,'output'=>FALSE,'editor'=>FALSE],
        'separator'=>['input'=>FALSE,'output'=>FALSE,'editor'=>FALSE],
        'escape'=>['input'=>FALSE,'output'=>FALSE,'editor'=>FALSE],
        'lineSeparator'=>['input'=>FALSE,'output'=>FALSE,'editor'=>FALSE],
        'mode'=>['input'=>TRUE,'output'=>TRUE,'editor'=>FALSE],    
    ];
    
    private const ALIAS=[
        'LF'=>"\n",
        'CR'=>"\r",
        'CRLF'=>"\n\r",
    ];
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $this->csvTimestamp=time();
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entry2csv();
    }
    
    private function csvSettingSelector(bool $csvOutput=FALSE):array
    {
        $setting=self::CSV_SELECTOR[$csvOutput?'output':'input'];
        return $this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($setting,['Source','Group','Folder','Name'],'0','',FALSE);
    }

    public function getSetting(bool $csvOutput=FALSE):array
    {
        $csvSetting=$this->csvSettingSelector($csvOutput);
        foreach(self::SETTINGS_OPTIONS as $key=>$options){
            $csvSetting['Content'][$key]=key($options);
        }
        return $this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($csvSetting,TRUE)['Content'];
    }
    
    public function setSetting(array $setting,bool $csvOutput=FALSE):array
    {
        $csvSetting=$this->csvSettingSelector($csvOutput);
        $csvSetting['Content']=$setting;
        return $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($csvSetting,TRUE)['Content'];
    }
    
    public function isCSV(array $selector):bool
    {
        $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector);
        if (!is_file($file)){return FALSE;}
        if (mb_strpos(mime_content_type($file),'text/')!==0){return FALSE;}
        foreach($this->csvIterator($selector) as $rowIndex=>$rowArr){
            if (count($rowArr??[])>1){
                //change file content encoding to utf-8 if encoding is different from utf-8
                $csvContent=file_get_contents($file);
                $sourceEncoding=mb_detect_encoding($csvContent,["ASCII","ISO-8859-1","JIS","ISO-2022-JP","UTF-7","UTF-8",],TRUE);
                if ($sourceEncoding!=='UTF-8'){
                    $csvContent=mb_convert_encoding($csvContent,"UTF-8",$sourceEncoding);
                    file_put_contents($file,$csvContent);
                    $this->oc['logger']->log('notice','Changed file content encoding from {sourceEncoding} to UTF-8',['sourceEncoding'=>$sourceEncoding]);    
                }
                return TRUE;
            }
            break;
        }
        return FALSE;
    }
    
    private function detectCsvSetting(string $fileName):array
    {
        $setting=$this->getSetting(FALSE);
        $csvFileContent=@file_get_contents($fileName);
        // divide into entries
        if (strlen($csvFileContent)>20000){
            $csvFileContent=substr($csvFileContent,0,20000);
            $entries=explode(self::ALIAS[$setting['lineSeparator']]??"\n",$csvFileContent);
            array_pop($entries);
        } else {
            $entries=explode(self::ALIAS[$setting['lineSeparator']]??"\n",$csvFileContent);
        }
        $maxCount=0;
        $header=array_shift($entries);
        $headerChrCount=count_chars($header,0);
        foreach(self::SETTINGS_OPTIONS['separator'] as $chr=>$desc){
            if ($headerChrCount[ord($chr)]>$maxCount){
                $maxCount=$headerChrCount[ord($chr)];
                $setting['separator']=$chr;
            }
        }
        return $setting;
    }

    public function csvIterator(array|string $selector,array $csvSetting=[]):\Generator
    {
        if (is_array($selector)){
            $csvFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector);
        } else {
            $csvFile=$selector;
        }
        if (is_file($csvFile)){
            if (empty($csvSetting)){
                $csvSetting=$this->detectCsvSetting($csvFile);
            }
            $csv=new \SplFileObject($csvFile);
            $csv->setCsvControl($csvSetting['separator']??',',$csvSetting['enclosure']?:'"',$csvSetting['escape']??'');
            $keys=[];
            $rowIndex=0;
            while($csv->valid()){
                $csvArr=$csv->fgetcsv();
                foreach($csvArr as $columnIndex=>$cellValue){
                    if (isset($keys[$columnIndex])){
                        if ($cellValue==='TRUE'){
                            $cellValue=TRUE;
                        } else if ($cellValue==='FALSE'){
                            $cellValue=FALSE;
                        } else if ($cellValue==='NAN'){
                            $cellValue=NAN;
                        } else if ($cellValue==='INF'){
                            $cellValue=INF;
                        } else if ($cellValue==='NULL'){
                            $cellValue=NULL;
                        }
                        $result[$keys[$columnIndex]]=$cellValue;
                    } else {
                        $keys[$columnIndex]=$cellValue;
                    }
                }
                if ($rowIndex!==0){
                    yield $result;
                }
                $csv->next();
                $rowIndex++;
            }
        } else {
            yield [];
        }
    }
    
    public function entry2csv(array $entry=[]):array|bool
    {
        // When called with an object this method adds the object to the session var space for later csv-file creation.
        // Later when the class is created, the session var space will be written to respective csv-file-objects
        // csv-file name = $entry['Name'], if $entry['EntryId'] is not set it will be created from $entry['Name']
        if (empty($entry) && isset($_SESSION['csvVarSpace'])){
            $statistics=['csv entries'=>0,'row count'=>0,'header'=>''];
            $csvSetting=$this->getSetting(TRUE);
            foreach($_SESSION['csvVarSpace'] as $EntryId=>$csvDefArr){
                // reset csv var-space
                unset($_SESSION['csvVarSpace'][$EntryId]);
                //
                $csvContent='';
                $entry=$csvDefArr['entry'];
                foreach($csvDefArr['rows'] as $rowIndex=>$valArr){
                    $csvLineArr=$this->getCsvRow($valArr,$csvSetting);
                    if ($rowIndex===0){
                        $csvContent.=$csvLineArr['header'];
                    }
                    $csvContent.=$csvLineArr['line'];
                    $statistics['row count']++;
                    $statistics['header']=$csvLineArr['header'];
                }
                // save csv content
                $statistics['csv entries']++;
                $entry['fileContent']=trim($csvContent);
                if (empty($entry['Params']['File']['Name'])){
                    $entry['fileName']=str_replace('.csv','',$entry['Name']).'.csv';
                } else {
                    $entry['fileName']=$entry['Params']['File']['Name'];
                }
                $entry['Content']=$statistics;
                $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->fileContent2entry($entry);
                $this->oc['logger']->log('info','CSV-entry created named "{Name}" containing {rowCount} rows.',['Name'=>$entry['Name'],'rowCount'=>count($csvDefArr['rows'])]);    
            }
            return $entry;
        } else if (isset($entry['Content'])){
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($entry,['Source','Group','Folder'],$this->csvTimestamp,'',TRUE);
            $elementId=$entry['EntryId'];
            $flatContentArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry['Content']);
            if (!isset($_SESSION['csvVarSpace'][$elementId])){
                $_SESSION['csvVarSpace'][$elementId]=['rows'=>[],'entry'=>$entry,'first row'=>$flatContentArr];
            }
            $row=[];
            foreach($_SESSION['csvVarSpace'][$elementId]['first row'] as $column=>$firstRowValue){
                if (isset($flatContentArr[$column])){$row[$column]=$flatContentArr[$column];} else {$row[$column]='?';}
            }
            $_SESSION['csvVarSpace'][$elementId]['rows'][]=$row;
            return $entry;
        } else if (!isset($_SESSION['csvVarSpace'])){
            // nothing to do
            $_SESSION['csvVarSpace']=[];
        } else {
            $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
            $this->oc['logger']->log('notice','Method "{function}" called by "{trace}" without content',['function'=>__FUNCTION__,'trace'=>$trace[1]['function']]);    
        }
        return FALSE;
    }
    
    private function getCsvRow(array $row,array $csvSetting):array
    {
        $result=['header'=>'','line'=>''];
        $values=$columns=[];
        foreach($row as $column=>$value){
            $columns[]=$csvSetting['enclosure'].$column.$csvSetting['enclosure'];
            if ($value===TRUE){
                $value='TRUE';
            } else if ($value===FALSE){
                $value='FALSE';
            } else if ($value===NAN){
                $value='NAN';
            } else if ($value===INF){
                $value='INF';
            } else if ($value===NULL){
                $value='NULL';
            }
            if (empty($csvSetting['enclosure']) || (strpos($value,$csvSetting['enclosure'])===FALSE && strpos($value,$csvSetting['separator'])===FALSE && strpos($value,$csvSetting['lineSeparator'])===FALSE)){
                $values[]=$value;
            } else {
                $value=(empty($csvSetting['enclosure']))?$value:str_replace($csvSetting['enclosure'],$csvSetting['enclosure'].$csvSetting['enclosure'],$value);
                $values[]=$csvSetting['enclosure'].$value.$csvSetting['enclosure'];
            }
        }
        $result['header']=implode($csvSetting['separator'],$columns);
        $result['line']=implode($csvSetting['separator'],$values);
        $result['header'].=self::ALIAS[$csvSetting['lineSeparator']]??"\n";
        $result['line'].=self::ALIAS[$csvSetting['lineSeparator']]??"\n";
        return $result;
    }
    
    public function csvView(array $arr):array
    {
        // check for attached file & get csv entry
        $attachedFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr['selector']);
        if (!is_file($attachedFile)){return $arr;}
        $csvEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
        // settings form
        $cntrArr=$this->csvOutputSettingsWidget(['selector'=>$this->csvSettingSelector(FALSE),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'mode'=>'editor']);
        $setting=$this->getSetting();
        // compile html
        $matrix=$columns=[];
        $rowCount=$rowLimitCount=0;
        foreach($this->csvIterator($arr['selector'],$setting) as $rowIndex=>$rowArr){
            $csvEntry['Content']=$rowArr;
            if ($rowLimitCount<$setting['limit'] && $rowIndex>=$setting['offset']){
                foreach($rowArr as $column=>$cellValue){
                    $columns['Content'.(\SourcePot\Datapool\Root::ONEDIMSEPARATOR).$column]=$column;
                    $valArr=$cellValue;
                    $csvEntry['Content'][$column]=$cellValue;
                    $matrix[$rowIndex][$column]=$valArr;
                }
                $rowLimitCount++;
            }
            $rowCount++;
        }
        $arr['html']=$cntrArr['html'];
        $caption='CSV sample: "'.$csvEntry['Name'].'" ('.$rowCount.')';
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption]);
        return $arr;
    }
    
    public function csvOutputSettingsWidget(array $arr):array
    {
        // init setting
        $setting=$arr['selector'];
        $setting=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($setting,['Source','Group','Folder','Name'],'0','',FALSE);
        foreach(self::SETTINGS_OPTIONS as $key=>$options){
            $setting['Content'][$key]=key($options);
        }
        $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->entryByIdCreateIfMissing($setting,TRUE);
        // command processing
        $arr['formData']=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (!empty($arr['formData']['val']['setting'])){
            $setting['Content']=$arr['formData']['val']['setting'];
            $setting=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($setting,TRUE);
        }
        // compile html
        $matrix=[];
        $selectArr=['key'=>['setting'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'keep-element-content'=>TRUE];
        foreach(self::SETTINGS_OPTIONS as $key=>$options){
            if (!empty(self::SETTINGS_HIDE[$key][$arr['mode']??$arr['selector']['Name']])){continue;}
            $selectArr['key'][1]=$key;
            $selectArr['options']=$options;
            $selectArr['value']=$setting['Content'][$key]??current($options);
            $matrix[$key]=['value'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr)];
        }
        $caption='CSV '.$arr['selector']['Name'];
        if ($arr['selector']['Name']==='input'){
            $caption.=' base settings. The "separator" will be detected when a file is processed.';
        } else {
            $caption.=' settings';
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption]);    
        return $arr;
    }

    public function matrix2csvDownload(array $matrix):string
    {
        // write/update file
        $tmpDir=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getPrivatTmpDir();
        $file=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($matrix);
        $file=$tmpDir.$file.'.csv';
        $isFirstRow=TRUE;
        $fp=fopen($file,'w');
        foreach($matrix as $rowIndex=>$row){
            if ($isFirstRow){
                fputcsv($fp,array_keys($row));
            }
            foreach($row as $cellIndex=>$cell){
                if (is_array($cell)){$row[$cellIndex]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($cell);}
                $row[$cellIndex]=str_replace('</br>',"\n",strval($row[$cellIndex]));
                $row[$cellIndex]=str_replace('<br>',"\n",strval($row[$cellIndex]));
                $row[$cellIndex]=strip_tags($row[$cellIndex]);
            }
            fputcsv($fp,$row);
            $isFirstRow=FALSE;
        }
        fclose($fp);
        // command processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['download'])){
            $file2download=key($formData['cmd']['download']);
            if (is_file($file2download)){
                header('Content-Type: application/csv');
                header('Content-Disposition: attachment; filename="'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime().'_matrix.csv');
                header('Content-Length: '.fileSize($file2download));
                readfile($file2download);
                exit;
            }
        }
        // create html
        $html='';
        $btnArr=['cmd'=>'download','key'=>['download',$file],'title'=>'Download table as csv-file','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        $btnArr=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->getBtns($btnArr);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>['clear'=>'none']]);
        return $html;
    }

}
?>