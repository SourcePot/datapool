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
    
    private const APP_ACCESS='ADMIN_R';
    private const CLASS_LINE_REGEX="/class\s+([A-Za-z]+)\s+implements\s+/";
    private const APP_ACCESS_REGEX="/private const APP_ACCESS='([^´;]+)'/";
    private const APP_DEF_REGEX="/return\s\['Category'=>'([^']+)','Emoji'=>'([^']+)','Label'=>'([^']+)','Read'=>([A-Z_\']+|self::APP_ACCESS),'Class'=>/";
    private const APP_NAMESPACE_CLASSNAME="/namespace\s([^\s]+)\s*;\s+class\s([^\s]+)/";
    private const CORE_APPS=['SourcePot\Datapool\GenericApps\Documents'=>TRUE,
                             'SourcePot\Datapool\GenericApps\Multimedia'=>TRUE,
                             'SourcePot\Datapool\GenericApps\Feeds'=>TRUE,
                             'SourcePot\Datapool\DataApps\Misc'=>TRUE
                            ];
    private const TEMPLATE_APPS=['GenericApps'=>'SourcePot\Datapool\GenericApps\Documents','DataApps'=>'SourcePot\Datapool\DataApps\Misc'];
    
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
        $dim=['x'=>intval(10*strlen($email)),'y'=>18];
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
            return ['Category'=>'Admin','Emoji'=>'&#8582;','Label'=>'Admin','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            // get page content
            $selector=['Source'=>$this->entryTable,'disableAutoRefresh'=>TRUE];
            $html='';
            $html.=$this->oc['SourcePot\Datapool\Foundation\Filespace']->loggerFilesWidget();
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Exception logs','generic',$selector,['method'=>'debugFilesHtml','classWithNamespace'=>__CLASS__],['style'=>['margin'=>'0']]);
            $html.=$this->getPageSettingsHtml();
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('FTP manual upload','generic',$selector,['method'=>'ftpFileUpload','classWithNamespace'=>__CLASS__],['style'=>['margin'=>'0']]);
            $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('App management','generic',$selector,['method'=>'appManagement','classWithNamespace'=>__CLASS__],['style'=>['margin'=>'0']]);
            $html.=$this->backupArticle();
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
            $selectors=[$formData['val']];
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
                $this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(['msg'=>'Import file missing','priority'=>10,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__]);
            }
        } else if (isset($formData['cmd']['renew'])){
            $objectListFile=$GLOBALS['dirs']['setup'].'objectList.csv';
            unlink($objectListFile);
        }
        // export html
        $matrix=[];
        $attachedFileSizeOptions=[0=>'Skip attached files',1000000=>'Skip files if >1 MB',10000000=>'Skip files if >10 MB',100000000=>'Skip files if >100 MB',1000000000=>'Skip files if >1 GB',10000000000=>'Skip files if >10 GB'];
        $tables=[''=>'none'];
        $entryTemplates=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplate();
        foreach($entryTemplates as $table=>$entryTemplate){
            $tables[$table]=ucfirst($table);
        }
        $btnArr=['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'tag'=>'button','keep-element-content'=>TRUE,'style'=>['float'=>'left','clear'=>'both'],'excontainer'=>TRUE];
        $tableSelect=$btnArr;
        $tableSelect['style']=['margin'=>'0.4em 0.2em'];
        $tableSelect['key']=['Source'];
        $tableSelect['options']=$tables;
        $sizeSelect=$btnArr;
        $sizeSelect['key']=['Size'];
        $sizeSelect['selected']=10000000;
        $sizeSelect['options']=$attachedFileSizeOptions;
        $btnArr['key']=['export'];
        $btnArr['title']="Create export from database table\nand download as file";
        $btnArr['element-content']='Export';
        $matrix['Backup to file']=['Input'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($tableSelect).$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select($sizeSelect),
                                    'Cmd'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr)];
        // import html        
        $fileArr=$btnArr;
        unset($fileArr['element-content']);
        $fileArr['tag']='input';
        $fileArr['type']='file';
        $fileArr['multiple']=TRUE;
        $fileArr['key']=$btnArr['key']=['import'];
        $btnArr['title']="Import database entries from file.\nEntries with the same EntryId will be replaced by the import!";
        $btnArr['element-content']='Import';
        $btnArr['hasCover']=TRUE;
        $matrix['Recover from file']=['Input'=>$this->oc['SourcePot\Datapool\Foundation\Element']->element($fileArr),'Cmd'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr)];
        // renew object init file
        $btnArr['key']=['renew'];
        $btnArr['title']="Deletes existing object collection and\ntriggers creation of up-to-date object collection.";
        $btnArr['element-content']='Renew';
        $btnArr['hasCover']=FALSE;
        $matrix['Object list']=['Input'=>$this->objectListHtml(),'Cmd'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($btnArr)];
        $tableHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Backup, recover, object list','hideKeys'=>FALSE,'hideHeader'=>TRUE]);
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$tableHtml,'keep-element-content'=>TRUE]);
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
        return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'','hideKeys'=>TRUE,'hideHeader'=>FALSE]);
    }

    public function appManagement($arr):array
    {
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'appProperties');
        if (isset($formData['cmd']['replicateApp']) || isset($formData['cmd']['updateApp'])){
            if (isset($formData['cmd']['replicateApp'])){
                $class=key($formData['cmd']['replicateApp']);
                $templateClass=self::TEMPLATE_APPS[$class];
                $fileMeta=$this->oc['SourcePot\Datapool\Root']->class2fileMeta($templateClass);
            } else {
                $class=key($formData['cmd']['updateApp']);
                $fileMeta=$this->oc['SourcePot\Datapool\Root']->class2fileMeta($class);
            }
            $appData=$formData['val'][$class];
            if (empty($appData['Label'])){
                $this->oc['logger']->log('notice','App was not updated nor replicated, invalid app data Emoji="{Emoji}", Label="{Label}"',$appData);
            } else {
                $newClassStr=file_get_contents($fileMeta['file']);
                // replace class name
                $newClassName=preg_replace('/[^A-Za-z]/','',$appData['Label']);
                $replacementStr="class ".$newClassName." implements ";
                $newClassStr=preg_replace(self::CLASS_LINE_REGEX,$replacementStr,$newClassStr);
                // replace app definition array
                $replacementStr="return ['Category'=>'".(($class==='GenericApps')?'Apps':'Data')."','Emoji'=>'".$appData['Emoji']."','Label'=>'".$appData['Label']."','Read'=>'".$appData['Read']."','Class'=>";
                $newClassStr=preg_replace(self::APP_DEF_REGEX,$replacementStr,$newClassStr);
                // save new class file
                $newFile=$fileMeta['dir'].'/'.$newClassName.'.php';            
                file_put_contents($newFile,$newClassStr);
                unlink($GLOBALS['dirs']['setup'].'objectList.csv');
                // add to oc
                preg_match(self::APP_NAMESPACE_CLASSNAME,$newClassStr,$match);
                $class=$match[1].'\\'.$match[2];
                $this->oc[$class]=TRUE;
            }
        } else if (isset($formData['cmd']['deleteApp'])){
            $class=key($formData['cmd']['deleteApp']);
            $fileMeta=$this->oc['SourcePot\Datapool\Root']->class2fileMeta($class);
            $sql='DROP TABLE `'.$this->oc[$class]->getEntryTable().'`;';
            $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql,[]);
            $stmt->fetchAll(\PDO::FETCH_ASSOC);
            unset($this->oc[$class]);
            unlink($fileMeta['file']);
            unlink($GLOBALS['dirs']['setup'].'objectList.csv');
        }
        // html creation
        $arr['html']=$arr['html']??'';
        $matrices=['GenericApps'=>[],'DataApps'=>[]];
        foreach($this->oc as $class=>$obj){
            if (strpos($class,'\DataApps')!==FALSE){
                $matrices['DataApps'][$class]=$this->appProperties($class);
            } else if (strpos($class,'\GenericApps')!==FALSE){
                $matrices['GenericApps'][$class]=$this->appProperties($class);
            }
        }
        foreach($matrices as $caption=>$matrix){
            $matrix['New']=$this->appProperties($caption);
            $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>$caption.' management','hideKeys'=>TRUE,'hideHeader'=>FALSE]);
        }
        return $arr;
    }

    private function appProperties($class):array
    {
        $classComps=explode('\\',$class);
        $accessOptions=$this->oc['SourcePot\Datapool\Foundation\Access']->getAccessOptions();
        $accessOptions=array_combine(array_keys($accessOptions),array_keys($accessOptions));
        // html form creation
        $btns='';
        $readR='ALL_MEMBER_R';
        $arr=['class'=>$class];
        if (count($classComps)<4){
            $folder=array_pop($classComps);
            $match=['',(($folder==='GenericApps')?'Apps':'Data'),'?',''];
            $btns.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'+','keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>['replicateApp',$arr['class']]]);
        } else {
            $fileMeta=$this->oc['SourcePot\Datapool\Root']->class2fileMeta($class);
            if (is_file($fileMeta['file'])){
                $classFileContent=file_get_contents($fileMeta['file']);
                preg_match(self::APP_ACCESS_REGEX,$classFileContent,$accessMatch);
                preg_match(self::APP_DEF_REGEX,$classFileContent,$match);
                if (strpos($match[4]??'','APP_ACCESS')!==FALSE){unset($match[4]);}
                if (!isset(self::CORE_APPS[$class])){
                    $btns.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','hasCover'=>TRUE,'element-content'=>'&coprod;','keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>['deleteApp',$arr['class']]]);
                    $btns.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'button','element-content'=>'&check;','keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>['updateApp',$arr['class']]]);
                }
                $readR=trim($match[4]??$accessMatch[1]??'','\'"');
            } else {
                $match=['-','-','-','-'];
            }
        }
        $readRbyte=$accessOptions[$readR];
        $arr['Category']=$match[1];
        $arr['Emoji']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','keep-element-content'=>TRUE,'value'=>html_entity_decode($match[2]??''),'excontainer'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>[$arr['class'],'Emoji']]);
        $arr['Label']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'input','type'=>'text','keep-element-content'=>TRUE,'value'=>html_entity_decode($match[3]??''),'placeholder'=>'MyNewApp','excontainer'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>[$arr['class'],'Label']]);
        $arr['Read']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->select(['options'=>$accessOptions,'selected'=>$readRbyte,'excontainer'=>TRUE,'excontainer'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>[$arr['class'],'Read']]);
        $arr['Cmd']=$btns;
        return $arr;
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
            $delArr=['Cmd'=>['tag'=>'button','element-content'=>'&coprod;','keep-element-content'=>TRUE,'title'=>'Delete file','key'=>['delete',$fullFileName],'callingClass'=>$arr['callingClass'],'callingFunction'=>$arr['callingFunction']]];
            $matrix[$file]=$this->oc['SourcePot\Datapool\Root']->file2arr($fullFileName);
            $matrix[$file]=$delArr+$matrix[$file];
            if (isset($matrix[$file]['traceAsString'])){
                $matrix[$file]['traceAsString']=preg_split('/#\d+\s/',$matrix[$file]['traceAsString']);
                $matrix[$file]['traceAsString']=implode('<br/>',$matrix[$file]['traceAsString']);
            }
        }
        $tableStyle=['background-color'=>'#fcc'];
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Exception logs','hideKeys'=>TRUE,'hideHeader'=>FALSE,'style'=>$tableStyle]);
        return $arr;
    }
    
    public function getPageSettingsHtml()
    {
        $homePageContentOptions=[''=>'None','imageShuffle'=>'Image shuffle','video'=>'Video (./www/assets/home.mp4)'];
        $timezones=$this->oc['SourcePot\Datapool\Calendar\Calendar']->getAvailableTimezones();
        $contentStructure=['pageTitle'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'Datapool'],
                        'metaViewport'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'width=device-width, initial-scale=1','style'=>['min-width'=>'50vw']],
                        'metaDescription'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'Web application for data processing','style'=>['min-width'=>'50vw']],
                        'metaRobots'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'index','style'=>['min-width'=>'50vw']],
                        'pageTimeZone'=>['method'=>'select','options'=>$timezones,'excontainer'=>TRUE],
                        'logLevel'=>['method'=>'select','options'=>['Production','Monitoring','Debugging'],'excontainer'=>TRUE],
                        'emailWebmaster'=>['method'=>'element','tag'=>'input','type'=>'email','value'=>'admin@datapool.info'],
                        'loginForm'=>['method'=>'select','options'=>['Password','Pass icons'],'excontainer'=>TRUE],
                        'homePageContent'=>['method'=>'select','options'=>$homePageContentOptions,'value'=>'video','excontainer'=>TRUE],
                        'Spatie path to Xpdf pdftotext executable'=>['method'=>'element','tag'=>'input','type'=>'text','placeholder'=>'C:\Program Files\Xpdf\pdftotext.exe','style'=>['min-width'=>'50vw']],
                        ];
        // get selector
        $arr=['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'movedEntryId'=>'init'];
        $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryById(['Class'=>'SourcePot\Datapool\Foundation\Backbone','EntryId'=>'init']);
        // get HTML
        $arr['contentStructure']=$contentStructure;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr,TRUE);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->row2table($row,'Web application settings',TRUE);
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE]);
    }

    public function ftpFileUpload(array $arr):array
    {
        // form processing
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
        if (isset($formData['cmd']['upload'])){
            $files=current($formData['files']);
            foreach($files as $fileArr){
                $success=move_uploaded_file($fileArr['tmp_name'],$GLOBALS['dirs']['ftp'].$fileArr['name']);
            }
        } else if (isset($formData['cmd']['delete'])){
            $file=key($formData['cmd']['delete']);
            unlink($GLOBALS['dirs']['ftp'].$file);
        }
        $matrix=[];
        $matrix[]=['value'=>$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->fileUpload(['callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'key'=>['upload']],[]),'cmd'=>''];
        // file list
        $files=scandir($GLOBALS['dirs']['ftp']);
        foreach($files as $file){
            if (strcmp($file,'.')===0 || strcmp($file,'..')===0){continue;}
            $dleArr=['tag'=>'button','key'=>['delete',$file],'element-content'=>'&coprod;','hasCover'=>TRUE,'keep-element-content'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'excontainer'=>FALSE];
            $delHtml=$this->oc['SourcePot\Datapool\Foundation\Element']->element($dleArr);
            $matrix[]=['value'=>$file,'cmd'=>$delHtml];
        }        
        // compile html
        $arr['html']=$arr['html']??'';
        $arr['html'].=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'keep-element-content'=>TRUE,'caption'=>'Add files to FTP folder','hideKeys'=>FALSE,'hideHeader'=>TRUE]);
        return $arr;
    }
}
?>