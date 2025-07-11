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
    
    private $csvAlias=[1=>['chr'=>';','label'=>';','validSeparator'=>TRUE,'validEnclosure'=>FALSE,'validLineSeparator'=>FALSE,'validEscape'=>FALSE],
                        2=>['chr'=>',','label'=>',','validSeparator'=>TRUE,'validEnclosure'=>FALSE,'validLineSeparator'=>FALSE,'validEscape'=>FALSE],
                        3=>['chr'=>"\t",'label'=>'Tabulator','validSeparator'=>TRUE,'validEnclosure'=>FALSE,'validLineSeparator'=>FALSE,'validEscape'=>FALSE],
                        4=>['chr'=>'"','label'=>'"','validSeparator'=>FALSE,'validEnclosure'=>TRUE,'validLineSeparator'=>FALSE,'validEscape'=>FALSE],
                        5=>['chr'=>"'",'label'=>"'",'validSeparator'=>FALSE,'validEnclosure'=>TRUE,'validLineSeparator'=>FALSE,'validEscape'=>FALSE],
                        6=>['chr'=>"\\",'label'=>"Backslash",'validSeparator'=>FALSE,'validEnclosure'=>FALSE,'validLineSeparator'=>FALSE,'validEscape'=>TRUE],
                        7=>['chr'=>"\n",'label'=>"Newline",'validSeparator'=>FALSE,'validEnclosure'=>FALSE,'validLineSeparator'=>TRUE,'validEscape'=>FALSE],
                        8=>['chr'=>"\r",'label'=>"Carriage return",'validSeparator'=>FALSE,'validEnclosure'=>FALSE,'validLineSeparator'=>TRUE,'validEscape'=>FALSE],
                        ];
    private $csvSettings=['offset'=>0,'limit'=>5,'enclosure'=>4,'separator'=>1,'escape'=>6,'lineSeparator'=>7,'noEnclosureOutput'=>TRUE,'mode'=>0];
    
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
    
    public function isCSV(array $selector):bool
    {
        $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector);
        if (!is_file($file)){return FALSE;}
        if (mb_strpos(mime_content_type($file),'text/')!==0){return FALSE;}
        foreach($this->csvIterator($selector) as $rowIndex=>$rowArr){
            if (count($rowArr)>1){
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
    
    public function alias($index=FALSE,$validate='separator')
    {
        // This method returns an alias based on the provided $index.
        // If $index is FALSE, an array of options is returned based on the labels in $csvAlias.
        // The options are filtered by the $validate argument.
        // If $index is >0 the alias char is returned if $validate is empty or label if $validate is not empty.
        if ($index===FALSE){
            $result=[];
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
    
    public function getSettings():array
    {
        return $this->csvSettings;
    }
    
    public function setSetting(array $setting):array
    {
        $this->csvSettings=array_merge($this->csvSettings,$setting);
        return $this->csvSettings;
    }
    
    private function csvSetting():array
    {
        $csvSettings=[];
        foreach($this->csvSettings as $settingKey=>$settingValueIndex){
            if (!isset($this->csvAlias[$settingValueIndex])){continue;}
            $csvSettings[$settingKey]=$this->csvAlias[$settingValueIndex]['chr'];
        }
        return $csvSettings;
    }
    
    public function csvIterator(array|string $selector):\Generator
    {
        if (is_array($selector)){
            $csvFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($selector);
        } else {
            $csvFile=$selector;
        }
        if (is_file($csvFile)){
            $csvSettings=$this->csvSetting();
            $csv=new \SplFileObject($csvFile);
            $csv->setCsvControl($csvSettings['separator'],$csvSettings['enclosure'],$csvSettings['escape']);
            $keys=[];
            $rowIndex=0;
            while($csv->valid()){
                $csvArr=$csv->fgetcsv();
                $result=[];
                foreach($csvArr as $columnIndex=>$cellValue){
                    if (isset($keys[$columnIndex])){
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
        // When called with an object this method adds the object to a session var space for later
        // csv-file creation. When the class is created the session var space will be written to respective csv-file-objects
        // csv-file name = $entry['Name'], if $entry['EntryId'] is not set it will be created from $entry['Name']
        //$_SESSION['csvVarSpace']=[];
        if (empty($entry) && isset($_SESSION['csvVarSpace'])){
            $statistics=['csv entries'=>0,'row count'=>0,'header'=>''];
            $csvSetting=$this->csvSetting();
            if (!empty($csvSetting['noEnclosureOutput'])){$csvSetting['enclosure']='';}
            $prodessedEntries=0;
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
                //$targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
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
    
    public function csvEditor(array $arr,bool $isDebugging=FALSE):array
    {
        if (!isset($arr['html'])){$arr['html']='';}
        if (!isset($_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']])){$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']]=$arr['settings'];}
        $settings=$_SESSION[__CLASS__][__FUNCTION__][$arr['containerId']];
        $debugArr=['arr in'=>$arr,'settings in'=>$settings,'valuesToUpdate'=>[]];
        $attachedFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($arr['selector']);
        if (!is_file($attachedFile)){return $arr;}
        $csvEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector']);
        // get settings
        foreach($this->getSettings() as $settingKey=>$settingValue){
            if (!isset($settings[$settingKey])){$settings[$settingKey]=$settingValue;}
        }
        // form processing
        $valuesToUpdate=[];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (!empty($formData['cmd']) && isset($formData['val']['settings'])){
            // csv settings
            $settings=array_replace_recursive($settings,$formData['val']['settings']);
            $this->setSetting($settings);
            // csv valus
            if (isset($formData['val']['values'])){
                $valuesToUpdate=$formData['val']['values'];
                unset($valuesToUpdate['settings']);
            }
        }
        // create sample
        $columns=[];
        $rowCount=0;
        $rowLimitCount=0;
        $matrix=[];
        foreach($this->csvIterator($arr['selector']) as $rowIndex=>$rowArr){
            $csvEntry['Content']=$rowArr;
            if ($rowLimitCount<$settings['limit'] && $rowIndex>=$settings['offset']){
                foreach($rowArr as $column=>$cellValue){
                    $columns['Content'.(\SourcePot\Datapool\Root::ONEDIMSEPARATOR).$column]=$column;
                    if (empty($settings['mode'])){
                        // show cell values
                        $valArr=$cellValue;
                    } else {
                        // edit cell values
                        if (isset($valuesToUpdate[$rowIndex][$column])){$cellValue=$valuesToUpdate[$rowIndex][$column];}
                        $valArr=['tag'=>'input','type'=>'text','key'=>['values',$rowIndex,$column],'value'=>$cellValue,'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'keep-element-content'=>TRUE];
                    }
                    $csvEntry['Content'][$column]=$cellValue;
                    $matrix[$rowIndex][$column]=$valArr;
                }
                $rowLimitCount++;
            }
            if (!empty($valuesToUpdate)){
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
        $sampleHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption]);
        // create control
        $selectArr=['key'=>['settings','limit'],'value'=>$settings['limit'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction'],'keep-element-content'=>TRUE];
        $selectArr['options']=[5=>'5',10=>'10',25=>'25',50=>'50',100=>'100',250=>'250',500=>'500'];
        $limitSelector=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $selectArr['key']=['settings','enclosure'];
        $selectArr['value']=$settings['enclosure'];
        $selectArr['options']=$this->alias(FALSE,'enclosure');
        $enclosureSelector=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $selectArr['key']=['settings','separator'];
        $selectArr['value']=$settings['separator'];
        $selectArr['options']=$this->alias(FALSE,'separator');
        $separatorSelector=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $selectArr['key']=['settings','lineSeparator'];
        $selectArr['value']=$settings['lineSeparator'];
        $selectArr['options']=$this->alias(FALSE,'lineSeparator');
        $lineSeparatorSelector=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $selectArr['key']=['settings','mode'];
        $selectArr['value']=$settings['mode'];
        $selectArr['options']=['Show','Edit'];
        $modeSelector=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($selectArr);
        $matrix=[];
        $matrix['Cntr']['Offset']=['tag'=>'input','type'=>'range','min'=>0,'max'=>($rowCount>$settings['limit'])?$rowCount-$settings['limit']:0,'value'=>$settings['offset'],'key'=>['settings','offset'],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']];
        $matrix['Cntr']['Limit']=$limitSelector;
        if (empty($settings['mode'])){
            $matrix['Cntr']['Separator']=$separatorSelector;
            $matrix['Cntr']['Enclosure']=$enclosureSelector;
            $matrix['Cntr']['Line separator']=$lineSeparatorSelector;
        }
        $matrix['Cntr']['Mode']=$modeSelector;
        $caption='CSV control';
        $cntrHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>FALSE,'keep-element-content'=>TRUE,'caption'=>$caption]);
        //
        $arr['html'].=$cntrHtml;
        $arr['html'].=$sampleHtml;
        if ($isDebugging){
            $debugArr['arr out']=$arr;
            $debugArr['settings out']=$settings;
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
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