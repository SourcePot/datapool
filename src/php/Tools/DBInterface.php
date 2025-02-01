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

class DBInterface implements \SourcePot\Datapool\Interfaces\Receiver{
    
    private $oc;
    private $db;
    
    private $entryTable='';
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 );

    public $receiverDef=array('Type'=>array('@tag'=>'p','@default'=>'settings receiver','@Read'=>'NO_R'),
                              'Content'=>array('EntryId'=>array('@tag'=>'p','@default'=>'','@excontainer'=>TRUE),
                                               'dbServer'=>array('@tag'=>'input','@type'=>'text','@default'=>'localhost','placeholder'=>'localhost','@excontainer'=>TRUE),
                                               'dbName'=>array('@tag'=>'input','@type'=>'text','@default'=>'','placeholder'=>'','@excontainer'=>TRUE),
                                               'dbUser'=>array('@tag'=>'input','@type'=>'text','@default'=>'','placeholder'=>'localhost','@excontainer'=>TRUE),
                                               'dbUserPsw'=>array('@tag'=>'input','@type'=>'password','@default'=>'','@excontainer'=>TRUE),
                                               'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'),
                                            ),
                            );

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
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion('!'.__CLASS__,$this->receiverDef);
    }

    public function getEntryTable(){
        return $this->entryTable;
    }
    
    public function getEntryTemplate(){
        return $this->entryTemplate;
    }
    
    /******************************************************************************************************************************************
    * DATASOURCE: Email receiver
    *
    * 'EntryId' ... arr-property selects the inbox
    * 
    */
    
    public function receive(string $id):array
    {
        $result=array();
        try{
            $this->db=$this->oc['SourcePot\Datapool\Foundation\Database']->connect(__CLASS__,$id,FALSE);
        } catch (\Exception $e){
            $result['Error (check settings)']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$e->getMessage(),'class'=>'sample'));
        }
        if ($this->db){

        }
        return $result;
    }
    
    public function receiverPluginHtml(array $arr):string
    {
        $html='';
        // add settings form
        $setting=$this->getReceiverSetting($arr['selector']['EntryId']);
        $settingsHtml=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($setting,FALSE);
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$settingsHtml,'icon'=>'Settings'));
        // add meta data info
        $meta=$this->getReceiverMeta($arr['selector']['EntryId']);
        $matrix=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2matrix($meta);
        $metaHtml=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Meta'));   
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->app(array('html'=>$metaHtml,'icon'=>'Meta'));
        return $html;
    }
    
    public function receiverSelector(string $id):array
    {
        $Group='INBOX|'.preg_replace('/\W/','_',$id);
        return array('Source'=>$this->entryTable,'Group'=>$Group);
    }

    private function id2entrySelector($id):array
    {
        $canvasElement=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\DataExplorer']->getEntryTable(),'EntryId'=>$id);
        $canvasElement=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($canvasElement,TRUE);
        if (isset($canvasElement['Content']['Selector'])){
            return $this->oc['SourcePot\Datapool\Tools\MiscTools']->arrRemoveEmpty($canvasElement['Content']['Selector']);
        } else {
            return array();
        }
    }
    
    private function getReceiverSetting($id){
        $setting=array('Class'=>__CLASS__,'EntryId'=>$id);
        $setting['Content']=array('EntryId'=>$id,
                                  'dbServer'=>'localhost',
                                  'dbName'=>'',
                                  'dbUser'=>'',
                                  'dbUserPsw'=>'');
        return $this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($setting,TRUE);
    }
    
    private function getReceiverMeta($id){
        $meta=array();
        try{
            $this->db=$this->oc['SourcePot\Datapool\Foundation\Database']->connect(__CLASS__,$id,FALSE);
        } catch (\Exception $e){
            $result['Error (check settings)']=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$e->getMessage(),'class'=>'sample'));
        }
        if ($this->db){
            $meta['Table']=array();
            foreach ($this->db->query('SHOW TABLES;') as $row){
                $meta['Table'][]=$row[0];
            }
            $meta['Table']=implode(', ',$meta['Table']);
        }
        return $meta;
    }

}
?>