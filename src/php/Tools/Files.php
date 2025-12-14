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

class Files implements \SourcePot\Datapool\Interfaces\Receiver{
    
    private $oc;
    
    private const CONTENT_STRUCTURE_PARAMS=[
        'File name regexp'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'\w+','excontainer'=>TRUE],
        'File extension regexp'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'\w+','excontainer'=>TRUE],
        'Relevant mime-type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>[]],
        '..or mime-type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>[]],
        '...or mime-type'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>[]],
        'Max file size'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>[]],
    ];
        
    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
    ];

    public function __construct($oc){
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
    
    /******************************************************************************************************************************************
    * DATASOURCE: File receiver
    *
    * 'EntryId' ... arr-property selects the inbox
    * 
    */
    
    public function receive(string $id):array
    {
        $params=$this->getParams($id);
        $canvasElement=$this->id2canvasElement($id);
        // create entries
        $entryTemplate=$this->receiverSelector($id);
        if (isset($canvasElement['Content']['Widgets']['pdf-file parser']) && isset($canvasElement['Content']['Widgets']['File upload extract archive']) && isset($canvasElement['Content']['Widgets']['File upload extract email parts'])){
            $entryTemplate['pdf-file parser']=$canvasElement['Content']['Widgets']['pdf-file parser'];
            $entryTemplate['File upload extract archive']=$canvasElement['Content']['Widgets']['File upload extract archive'];
            $entryTemplate['File upload extract email parts']=$canvasElement['Content']['Widgets']['File upload extract email parts'];
        } else {
            $this->oc['logger']->log('notice','Canvas element settings missing');    
        }
        $result=['Files found'=>0,'Files uploaded'=>0,'Files removed from upload dir'=>0];
        $files=scandir($GLOBALS['dirs']['ftp']);
        foreach($files as $index=>$filename){
            if (strlen($filename)<3){continue;}
            $file=$GLOBALS['dirs']['ftp'].$filename;
            $fileArr=$this->getFileMetaArr($file);
            $matchArr=$this->fileMatch($file,$params,$fileArr);
            if ($matchArr['match']){
                // upload file
                $fileArr['id']=$id;
                $entry=$entryTemplate;
                $entry['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.filemtime($file));
                $entry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.time(),'P4D');
                $entry['Folder']=$fileArr['extension'];
                $entry['Name']=$fileArr['basename'];
                $entry['EntryId']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($fileArr);
                $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->file2entry($file,$entry,FALSE,TRUE);
                unlink($file);
            }
            $result['Files found']++;
        }
        return $result;
    }
    
    public function receiverPluginHtml(array $arr):string
    {
        // get selector
        $callingElementEntryId=$arr['selector']['EntryId'];
        $callingElement=['Folder'=>'Settings','EntryId'=>$callingElementEntryId];
        // build content structure
        $contentStructure=self::CONTENT_STRUCTURE_PARAMS;
        $mimeOptions=[''=>'...','text/'=>'text/*','application/'=>'application/*','image/'=>'image/*','video/'=>'video/*','audio/'=>'audio/*','message/'=>'message/*','/zip'=>'*/zip*','/pdf'=>'*/pdf','/json'=>'*/json'];
        $fileSizeOptions=[''=>'Only system limit',10240=>'10 kB',102400=>'100 kB',1048576=>'1 MB',10485760=>'10 MB',104857600=>'100 MB',209715200=>'200 MB'];
        $contentStructure['Relevant mime-type']['options']=$mimeOptions;
        $contentStructure['..or mime-type']['options']=$mimeOptions;
        $contentStructure['...or mime-type']['options']=$mimeOptions;
        $contentStructure['Max file size']['options']=$fileSizeOptions;
        $contentStructure=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->finalizeContentStructure($contentStructure,$callingElement);
        // get calling element and add content structure
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,__FUNCTION__,$callingElement,TRUE);
        $arr['canvasCallingClass']=$arr['selector']['Folder'];
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='File upload filter';
        $arr['noBtns']=TRUE;
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$arr['caption']]);
        $html.=$this->getDirContent($callingElementEntryId);
        return $html;
    }

    public function receiverSelector(string $id):array
    {
        $Group='INBOX|'.preg_replace('/\W/','_',$id);
        return ['Source'=>$this->entryTable,'Group'=>$Group];
    }    

    /******************************************************************************************************************************************
    * DATASOURCE: File receiver helper
    *
    */
    
    private function getParams(string $id):array
    {
        $arr=$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->callingElement2arr(__CLASS__,'receiverPluginHtml',['Folder'=>'Settings','EntryId'=>$id],TRUE);
        $paramsEntry=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector'],TRUE);
        if (isset($paramsEntry['Content'])){return $paramsEntry['Content'];} else {return [];}
    }
    
    private function getDirContent(string $id):string
    {
        $matrix=[];
        $params=$this->getParams($id);
        if (!isset($params['Max file size']) || !isset($params['File extension regexp']) || !isset($params['File name regexp'])){
            return '';
        } else {
            $files=scandir($GLOBALS['dirs']['ftp']);
            foreach($files as $index=>$filename){
                if (strlen($filename)<3){continue;}
                $file=$GLOBALS['dirs']['ftp'].$filename;
                $fileArr=$this->getFileMetaArr($file);
                $matchArr=$this->fileMatch($file,$params,$fileArr);
                $matrix[$index]=[];
                $matrix[$index]['filename']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','style'=>['color'=>($matchArr['nameMatch']?'green':'red')],'element-content'=>$fileArr['filename']]);
                $matrix[$index]['extension']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','style'=>['color'=>($matchArr['extensionMatch']?'green':'red')],'element-content'=>$fileArr['extension']]);
                $matrix[$index]['Size']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','style'=>['color'=>($matchArr['sizeOK']?'green':'red')],'element-content'=>$fileArr['Size']]);
                $matrix[$index]['MIME-Type']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','style'=>['color'=>($matchArr['mimeTypeMatch']?'green':'red')],'element-content'=>$fileArr['MIME-Type']]);
                $matrix[$index]['Match']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->bool2element($matchArr['match']);
            }
            return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Dir content']);
        }
    }

    private function getFileMetaArr($file):array
    {
        $fileArr=pathinfo($file);
        $fileArr['Size']=filesize($file);
        $fileArr['MIME-Type']=mime_content_type($file);
        unset($fileArr['dirname']);
        return $fileArr;   
    }

    private function fileMatch(string $file,array $params,array $fileArr):array
    {
        if (!isset($params['Max file size']) || !isset($params['File extension regexp']) || !isset($params['File name regexp'])){
            $this->oc['logger']->log('notice','Please set "File upload filter"');        
            return ['match'=>FALSE];
        } else {
            $mimeTypeMatch=FALSE;
            if (!empty($params['Relevant mime-type'])){$mimeTypeMatch=$mimeTypeMatch || strpos($fileArr['MIME-Type'],$params['Relevant mime-type'])!==FALSE;}
            if (!empty($params['..or mime-type'])){$mimeTypeMatch=$mimeTypeMatch || strpos($fileArr['MIME-Type'],$params['..or mime-type'])!==FALSE;}
            if (!empty($params['...or mime-type'])){$mimeTypeMatch=$mimeTypeMatch || strpos($fileArr['MIME-Type'],$params['...or mime-type'])!==FALSE;}
            $nameMatch=preg_match('/'.$params['File name regexp'].'/',$fileArr['filename']);
            $extensionMatch=preg_match('/'.$params['File extension regexp'].'/',$fileArr['extension']);
            $sizeOK=empty($params['Max file size']) || (intval($params['Max file size'])>=$fileArr['Size']);
            return ['match'=>$mimeTypeMatch && $nameMatch && $extensionMatch && $sizeOK,'mimeTypeMatch'=>$mimeTypeMatch,'nameMatch'=>$nameMatch,'extensionMatch'=>$extensionMatch,'sizeOK'=>$sizeOK];
        }
    }
    
    private function id2canvasElement($id):array
    {
        $canvasElement=['Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$id];
        return $this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElement,TRUE);
    }

}
?>