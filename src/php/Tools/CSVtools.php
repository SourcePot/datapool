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
        'lineSeparator'=>['CRLF'=>'Carriage return & line feed','LF'=>'Line feed','CR'=>'Carriage return','PHP_EOL'=>'PHP_EOL'],
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
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        ini_set('auto_detect_line_endings',TRUE);
        //$this->entry2csv();
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
        $headerChrCount=count_chars($header??'',0);
        foreach(self::SETTINGS_OPTIONS['separator'] as $chr=>$desc){
            if ($headerChrCount[ord($chr)]>$maxCount){
                $maxCount=$headerChrCount[ord($chr)];
                $setting['separator']=$chr;
            }
        }
        return $setting;
    }

    public function csvIterator(array|string $selector,string $reader='csv',array $csvSetting=[]):\Generator
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
            $keys=$result=[];
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

}
?>