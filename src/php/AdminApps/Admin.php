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

class Admin implements \SourcePot\Datapool\Interfaces\App{
    
    private $oc;
    private $entryTable='';
    
    public function __construct($oc){
        $this->oc=$oc;
        $this->entryTable=$this->oc['SourcePot\Datapool\Foundation\Logger']->getEntryTable();
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        // save picture of admin email address to assets directory 
        $email=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('emailWebmaster');
        $dim=array('x'=>intval(10*strlen($email)),'y'=>18);
        $im=imagecreate($dim['x'],$dim['y']);
        $bgColor=imagecolorallocate($im,255,255,255);
        $fColor=imagecolorallocate($im,100,100,100);
        imagefill($im,0,0,$bgColor);
        imagestring($im,4,0,2,$email,$fColor);
        imagepng($im,$GLOBALS['dirs']['assets'].'email.png');
        imagedestroy($im);
    }

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return array('Category'=>'Admin','Emoji'=>'&#8582;','Label'=>'Admin','Read'=>'ADMIN_R','Class'=>__CLASS__);
        } else {
            // get page content
            $html='';
            $settings=array('method'=>'debugFilesHtml','classWithNamespace'=>__CLASS__);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Filespace']->loggerFilesWidget();
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Exception logs','generic',array('Source'=>$this->entryTable),$settings,array('style'=>array('margin'=>'0')));
            $html.=$this->getPageSettingsHtml();
            $html.=$this->appAdminHtml();
            $html.=$this->backupArticle();
            $settings=array('method'=>'ftpFileUpload','classWithNamespace'=>__CLASS__);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('FTP manual upload','generic',array('Source'=>$this->entryTable),$settings,array('style'=>array('margin'=>'0')));
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    } 
    
    public function backupArticle()
    {
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        $this->oc['SourcePot\Datapool\Foundation\Database']->resetStatistic();
        if (isset($formData['cmd']['export'])){
            $selectors=array($formData['val']);
            $pageTitle=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');
            $fileName=date('Y-m-d H_i_s').' '.$pageTitle.' '.current($selectors)['Source'].' dump.zip';
            $this->oc['SourcePot\Datapool\Foundation\Filespace']->downloadExportedEntries($selectors,$fileName,FALSE,intval($formData['val']['Size']));    
        } else if (isset($formData['cmd']['import'])){
            $tmpFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getTmpDir().'tmp.zip';
            if (!empty($formData['files']['import'])){
                foreach($formData['files']['import'] as $fileIndex=>$file){
                    $success=move_uploaded_file($file['tmp_name'],$tmpFile);
                    if ($success){
                        $this->oc['SourcePot\Datapool\Foundation\Filespace']->importEntries($tmpFile,$file['name'],TRUE);
                    } else {
                        $this->oc['logger']->log('notice','Import of "{name}" failed',$file);    
                    }
                }
            } else {
                $this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'Import file missing','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
            }
        } else if (isset($formData['cmd']['renew'])){
            $objectListFile=$GLOBALS['dirs']['setup'].'objectList.csv';
            unlink($objectListFile);
        }
        // export html
        $matrix=[];
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
        $btnArr['title']="Create export from database table\nand download as file";
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
        $btnArr['title']="Import database entries from file.\nEntries with the same EntryId will be replaced by the import!";
        $btnArr['element-content']='Import';
        $btnArr['hasCover']=TRUE;
        $matrix['Recover from file']=array('Input'=>$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileArr),
                                           'Cmd'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr));
        // renew object init file
        $btnArr['key']=array('renew');
        $btnArr['title']="Deletes existing object collection and\ntriggers creation of up-to-date object collection.";
        $btnArr['element-content']='Renew';
        $btnArr['hasCover']=FALSE;
        $matrix['Object list']=array('Input'=>$this->objectListHtml(),
                                     'Cmd'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr)
                                    );
        
        $tableHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Backup, recover, object list','hideKeys'=>FALSE,'hideHeader'=>TRUE));
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$tableHtml,'keep-element-content'=>TRUE));
    }
    
    private function objectListHtml()
    {
        $matrix=[];
        $objectListFile=$GLOBALS['dirs']['setup'].'objectList.csv';
        if (!is_file($objectListFile)){return 'Please reload to create a new object list.';}
        foreach($this->oc['SourcePot\Datapool\Tools\CSVtools']->csvIterator($objectListFile) as $row){
            if (!isset($row['type'])){continue;}
            $matrix[$row['class']]=$row;
        }
        $tableHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'','hideKeys'=>TRUE,'hideHeader'=>FALSE));
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$tableHtml,'keep-element-content'=>TRUE));
    }
    
    public function appAdminHtml()
    {
        $html=$this->replicateAppHtml();
        $html.=$this->deleteAppHtml();
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE));
    }
    
    public function replicateAppHtml()
    {
        $apps=[];
        foreach($this->oc['SourcePot\Datapool\Foundation\Menu']->getCategories() as $category=>$def){
            if ($category!=='Apps' && $category!=='Data'){continue;}
            $apps[$def['Class']]=$def['Name'];
        }
        $readOptions=array_flip($this->oc['SourcePot\Datapool\Foundation\Access']->getAccessOptions());
        // init arr
        $arr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'noBtns'=>TRUE);
        $arr['selector']=array('Source'=>'settings','Group'=>__CLASS__,'Folder'=>__FUNCTION__,'Name'=>'Replicate app');
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],array('Source','Group','Folder','Name'),'0','',FALSE);
        $arr['selector']['Content']=array('Source class'=>key($apps),'New class'=>'Inventory','Label'=>'Inventory','Emoji'=>'€','Read'=>32768);
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (!empty($formData['cmd']) && !isset($formData['cmd']['save'])){
            $entryKey=key($formData['cmd']);
            $arr['selector']['Content']=$formData['val'][$entryKey]['Content'];
            $this->replicateApp($formData['val'][$entryKey]['Content']);
        }
        $contentStructure=array('Source class'=>array('method'=>'select','options'=>$apps,'excontainer'=>TRUE),
                                'New class'=>array('method'=>'element','tag'=>'input','type'=>'text','minlength'=>3),
                                'Label'=>array('method'=>'element','tag'=>'input','type'=>'text','minlength'=>3),
                                'Emoji'=>array('method'=>'element','tag'=>'input','type'=>'text','minlength'=>1,'maxlength'=>1),
                                'Read'=>array('method'=>'select','options'=>$readOptions,'excontainer'=>TRUE),
                                ' '=>array('method'=>'element','tag'=>'button','hasCover'=>TRUE,'title'=>'Check input before proceeding','element-content'=>'Replicate'),
                                );
        // get HTML
        $arr['contentStructure']=$contentStructure;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->row2table($row,'Replicate app',TRUE);
    }
    
    public function deleteAppHtml()
    {
        $classes=[];
        $classes2files=[];
        $objectListFile=$GLOBALS['dirs']['setup'].'objectList.csv';
        if (!is_file($objectListFile)){return '';}
        foreach($this->oc['SourcePot\Datapool\Tools\CSVtools']->csvIterator($objectListFile) as $row){
            if (!isset($row['classWithNamespace'])){continue;}
            if ($row['type']==='Application object' && (strpos($row['classWithNamespace'],'\GenericApps')!==FALSE || strpos($row['classWithNamespace'],'\DataApps')!==FALSE) &&
                (strpos($row['classWithNamespace'],'\Invoices')===FALSE && strpos($row['classWithNamespace'],'\Multimedia')===FALSE && strpos($row['classWithNamespace'],'\Calendar')===FALSE && strpos($row['classWithNamespace'],'\Forum')===FALSE && strpos($row['classWithNamespace'],'\Documents')===FALSE && strpos($row['classWithNamespace'],'\Feeds')===FALSE)){
                $classes[$row['classWithNamespace']]=$row['classWithNamespace'];
                $classes2files[$row['classWithNamespace']]=$row['file'];
            }
        }
        // init arr
        $arr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'noBtns'=>TRUE);
        $arr['selector']=array('Source'=>'settings','Group'=>__CLASS__,'Folder'=>__FUNCTION__,'Name'=>'Delete app');
        $arr['selector']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($arr['selector'],array('Source','Group','Folder','Name'),'0','',FALSE);
        $arr['selector']['Content']=array('Class'=>key($classes));
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (!empty($formData['cmd']) && !isset($formData['cmd']['save'])){
            $entryKey=key($formData['cmd']);
            $class2delete=$formData['val'][$entryKey]['Content']['Class'];
            if (unlink($classes2files[$class2delete])){
                unlink($objectListFile);
                $this->oc['logger']->log('info','Class "{class}" has been deleted. But the corresponding database table and filespace was left alone',array('class'=>$class2delete));         
            } else {
                $this->oc['logger']->log('error','Failed to remove class "{class}", file {file}',array('class'=>$class2delete,'file'=>$classes2files[$class2delete]));         
            }
        }
        $contentStructure=array('Class'=>array('method'=>'select','options'=>$classes,'excontainer'=>TRUE),
                                ' '=>array('method'=>'element','tag'=>'button','hasCover'=>TRUE,'title'=>'Check input before proceeding','element-content'=>'Delete'),
                                );
        // get HTML
        $arr['contentStructure']=$contentStructure;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->row2table($row,'Delete app',TRUE);
    }
    
    
    private function replicateApp($data)
    {
        $target=[];
        $readOptions=array_flip($this->oc['SourcePot\Datapool\Foundation\Access']->getAccessOptions());
        $objectListFile=$GLOBALS['dirs']['setup'].'objectList.csv';
        if (!is_file($objectListFile)){return FALSE;}
        foreach($this->oc['SourcePot\Datapool\Tools\CSVtools']->csvIterator($objectListFile) as $row){
            if (strcmp($row['classWithNamespace'],$data['Source class'])!==0){continue;}
            $target['class']=ucfirst(preg_replace('/[^a-zA-Z]/','',$data['New class']));
            $source=$row;
            $source['namespace']=explode('\\',$source['classWithNamespace']);
            array_pop($source['namespace']);
            $target['namespace']=$source['namespace']=implode('\\',$source['namespace']);
            $target['classWithNamespace']=$target['namespace'].'\\'.$target['class'];
            $target['type']=$source['type'];
            $target['file']=str_replace($source['class'],$target['class'],$source['file']);
            break;
        }
        if (isset($source)){
            $category=$this->oc['SourcePot\Datapool\Foundation\Menu']->class2category($source['class']);
            if (is_file($target['file'])){
               $this->oc['logger']->log('warning','Target class "{class}" exists already and was not changed',array('class'=>$target['class']));     
            } else if (strlen($target['class'])<3){
               $this->oc['logger']->log('warning','Target class name "{class}" is invalid',array('class'=>$data['New class']));     
            } else if (empty($category)){
               $this->oc['logger']->log('warning','Category info missing for source class "{class}", nothing created',array('class'=>$data['New class']));     
            } else {
                $fileContent=file_get_contents($source['file']);
                $fileContent=str_replace('class '.$source['class'].' ','class '.$target['class'].' ',$fileContent);
                $newDef="if (\$arr===TRUE){\n            return array('Category'=>'".$category['Category']."','Emoji'=>'".$data['Emoji']."','Label'=>'".$data['Label']."','Read'=>'".$readOptions[intval($data['Read'])]."','Class'=>__CLASS__);";
                $fileContent=preg_replace('/(if \(\$arr\=+TRUE\)\{\s+return )(array\([^)]+\)\;)/',$newDef,$fileContent);
                if (file_put_contents($target['file'],$fileContent)){
                    unlink($objectListFile);
                    $this->oc['logger']->log('info','New class "{class}" created',array('class'=>$data['New class']));
                } else {
                    $this->oc['logger']->log('error','Creation of class "{class}" failed',array('class'=>$data['New class']));
                }
            }
        }
        return TRUE;
    }
    
    public function debugFilesHtml($arr):array
    {
        $arr['html']=(isset($arr['html']))?$arr['html']:'';
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing($arr['callingClass'],$arr['callingFunction']);
        if (isset($formData['cmd']['delete'])){
            $filetoDelete=key($formData['cmd']['delete']);
            if (is_file($filetoDelete)){unlink($filetoDelete);}
        }
        //
        $files=scandir($GLOBALS['dirs']['debugging']);
        sort($files);
        $matrix=[];
        foreach($files as $file){
            if (strpos($file,'exceptionsLog.json')===FALSE){continue;}
            $fullFileName=$GLOBALS['dirs']['debugging'].$file;
            $delArr=array('Cmd'=>array('tag'=>'button','element-content'=>'&coprod;','keep-element-content'=>TRUE,'title'=>'Delete file','key'=>array('delete',$fullFileName),'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']));
            $matrix[$file]=$this->oc['SourcePot\Datapool\Root']->file2arr($fullFileName);
            $matrix[$file]=$delArr+$matrix[$file];
            if (isset($matrix[$file]['traceAsString'])){
                $matrix[$file]['traceAsString']=preg_split('/#\d+\s/',$matrix[$file]['traceAsString']);
                $matrix[$file]['traceAsString']=implode('<br/>',$matrix[$file]['traceAsString']);
            }
        }
        $tableStyle=array('background-color'=>'#fcc');
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Exception logs','hideKeys'=>TRUE,'hideHeader'=>FALSE,'style'=>$tableStyle));
        return $arr;
    }
    
    public function getPageSettingsHtml()
    {
        $homePageContentOptions=array(''=>'None','imageShuffle'=>'Image shuffle','video'=>'Video (./www/assets/home.mp4)');
        $timezones=$this->oc['SourcePot\Datapool\GenericApps\Calendar']->getAvailableTimezones();
        $contentStructure=array('pageTitle'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'Datapool'),
                                'metaViewport'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'width=device-width, initial-scale=1','style'=>array('min-width'=>'50vw')),
                                'metaDescription'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'Web application for data processing','style'=>array('min-width'=>'50vw')),
                                'metaRobots'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'index','style'=>array('min-width'=>'50vw')),
                                'pageTimeZone'=>array('method'=>'select','options'=>$timezones,'excontainer'=>TRUE),
                                'logLevel'=>array('method'=>'select','options'=>array('Production','Monitoring','Debugging'),'excontainer'=>TRUE),
                                'emailWebmaster'=>array('method'=>'element','tag'=>'input','type'=>'email','value'=>'admin@datapool.info'),
                                'Google Project ID'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>''),
                                'Google reCAPTCHA site key [not used if empty]'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>''),
                                'loginForm'=>array('method'=>'select','options'=>array('Password','Pass icons'),'excontainer'=>TRUE),
                                'homePageContent'=>array('method'=>'select','options'=>$homePageContentOptions,'value'=>'video','excontainer'=>TRUE),
                                'Spatie path to Xpdf pdftotext executable'=>array('method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'C:\Program Files\Xpdf\pdftotext.exe','style'=>array('min-width'=>'50vw')),
                                );
        // get selector
        $arr=array('callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'movedEntryId'=>'init');
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryById(array('Class'=>'SourcePot\Datapool\Foundation\Backbone','EntryId'=>'init'));
        // get HTML
        $arr['contentStructure']=$contentStructure;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,FALSE,TRUE);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->row2table($row,'Web application settings',TRUE);
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE));
    }

    public function ftpFileUpload(array $arr):array
    {
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['uploadBtn'])){
            foreach($formData['files']['upload'] as $index=>$fileArr){
                $success=move_uploaded_file($fileArr['tmp_name'],$GLOBALS['dirs']['ftp'].$fileArr['name']);
            }
        } else if (isset($formData['cmd']['delete'])){
            $file=key($formData['cmd']['delete']);
            unlink($GLOBALS['dirs']['ftp'].$file);
        }
        // compile html
        $arr['html']=$arr['html']??'';
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'h1','element-content'=>'FTP manual upload'));
        $fileArr=array('tag'=>'input','type'=>'file','key'=>array('upload'),'multiple'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>TRUE);
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileArr);
        $btnArr=array('tag'=>'input','type'=>'submit','key'=>array('uploadBtn'),'value'=>'Upload','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>FALSE);
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr);
        $filesHtml='';
        $files=scandir($GLOBALS['dirs']['ftp']);
        foreach($files as $file){
            if (strcmp($file,'.')===0 || strcmp($file,'..')===0){continue;}
            $dleArr=array('tag'=>'button','key'=>array('delete',$file),'element-content'=>'&coprod;','hasCover'=>TRUE,'keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>FALSE);
            $delHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($dleArr);
            $liArr=array('tag'=>'li','element-content'=>$file.$delHtml,'keep-element-content'=>TRUE);
            $filesHtml.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($liArr);
        }
        $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'ol','element-content'=>$filesHtml,'keep-element-content'=>TRUE));
        return $arr;
    }
}
?>