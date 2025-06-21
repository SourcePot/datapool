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

class DbAdmin implements \SourcePot\Datapool\Interfaces\App{
    
    private const APP_ACCESS='ADMIN_R';
    
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

    public function run(array|bool $arr=TRUE):array{
        if ($arr===TRUE){
            return ['Category'=>'Admin','Emoji'=>'&','Label'=>'Database','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            // get page content
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__);
            $html='';
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__);
            $this->tableCmdsProcessing();
            if (empty($selector['Source'])){
                //$html.=$this->dbUser();   // <-- requires proper rights
                $html.=$this->dbInfo();
            } else {
                $html.=$this->tableInfo($selector);
            }
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }
    
    private function dbInfo():string
    {
        $matrices=[];
        $matrices['General']['Database name']=['value'=>$this->oc['SourcePot\Datapool\Foundation\Database']->getDbName(),'trStyle'=>['background-color'=>'#bbf']];
        $sql='SELECT table_schema "Database name",SUM(data_length+index_length) "Database size" FROM information_schema.tables GROUP BY table_schema;';
        $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql,[],FALSE);
        foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $dbInfo){
            if ($dbInfo['Database name']!==$matrices['General']['Database name']['value']){continue;}
            $value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($dbInfo['Database size'],3,1024).'B';
            $matrices['General']['Database size']=['value'=>$value];
            break;
        }
        $sql='SELECT CURRENT_USER();';
        $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql,[],FALSE);
        foreach($stmt->fetch(\PDO::FETCH_ASSOC) as $key=>$value){
            $matrices['General'][$key]=['value'=>$value];
        }
        $sql='SHOW GLOBAL VARIABLES;';
        $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql,[],FALSE);
        foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $keyValue){
            $key=$keyValue['Variable_name'];
            if ($key=='version' || $key=="version_ssl_library" || $key=='storage_engine'){
                $matrices['General'][trim($key,'@')]=['value'=>$keyValue['Value']];
            } else if (mb_strpos($key,'character_set')!==FALSE){
                $matrices['Character sets'][trim($key,'@')]=['value'=>$keyValue['Value']];
            } else if (mb_strpos($key,'timeout')!==FALSE){
                $matrices['Timeouts'][trim($key,'@')]=['value'=>$keyValue['Value']];
            } else if (mb_strpos($key,'_size')!==FALSE){
                if (intval($keyValue['Value'])>0){
                    $value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($keyValue['Value'],3,1024).'B';
                } else {
                    $value=$keyValue['Value'];
                }
                $matrices['Sizes'][trim($key,'@')]=['value'=>$value];
            }
        }
        $html='';
        foreach($matrices as $caption=>$matrix){
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'caption'=>$caption,'hideKeys'=>FALSE,'hideHeader'=>TRUE]);
        }
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE]);
        return $html;
    }
    
    private function dbUser():string
    {
        $matrix=[];
        $sql='SELECT Host,User,Select_priv,Insert_priv,Update_priv,Delete_priv,Create_priv,Drop_priv,Reload_priv,Shutdown_priv,Process_priv,File_priv,Grant_priv,References_priv,Index_priv,Alter_priv,Show_db_priv,Super_priv,Create_tmp_table_priv,Lock_tables_priv,Execute_priv,Repl_slave_priv,Repl_client_priv,Create_view_priv,Show_view_priv,Create_routine_priv,Alter_routine_priv,Create_user_priv,Event_priv,Trigger_priv,Create_tablespace_priv,Delete_history_priv FROM mysql.user;';
        $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql,[],FALSE);
        foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $userInfo){
            $row=$userInfo['User'].'@'.$userInfo['Host'];
            unset($userInfo['User']);
            unset($userInfo['Host']);
            $matrix[$row]=$userInfo;
        }
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'caption'=>'Database user privileges','hideKeys'=>FALSE,'hideHeader'=>FALSE]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE]);
        return $html;
    }

    private function tableInfo($selector):string
    {
        $db=$this->oc['SourcePot\Datapool\Foundation\Database']->getDbName();
        $table=$selector['Source'];
        $matrices=['Columns'=>[]];
        $sql='SHOW INDEX FROM `'.$table.'`;';
        $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql,[],FALSE);
        foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $columnInfo){
            $column=$columnInfo['Column_name'];
            unset($columnInfo['Column_name']);
            unset($columnInfo['Table']);
            $matrices['Index'][$column]=$columnInfo;
        }
        $sql='SHOW COLUMNS FROM `'.$table.'`;';
        $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql,[],FALSE);
        foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $columnInfo){
            $column=$columnInfo['Field'];
            unset($columnInfo['Field']);
            if (isset($matrices['Index'][$column])){$columnInfo['trStyle']=['background-color'=>'#bbf'];}
            $matrices['Columns'][$column]=$columnInfo;
        }
        $tableKey='Table "'.$table.'"';
        $sql="SELECT TABLE_NAME,TABLE_COLLATION,ENGINE,TABLE_ROWS,DATA_LENGTH,INDEX_LENGTH,AUTO_INCREMENT,CREATE_TIME,UPDATE_TIME FROM INFORMATION_SCHEMA.TABLES;";
        $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql,[],FALSE);
        foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $tableInfo){
            if ($tableInfo['TABLE_NAME']!==$table){continue;}
            unset($tableInfo['TABLE_NAME']);
            foreach($tableInfo as $key=>$value){
                if (mb_strpos($key,'_LENGTH')!==FALSE){
                    $value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($value,3,1024).'B';
                } else if (mb_strpos($key,'_ROWS')!==FALSE){
                    $value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($value,3,1000);
                }
                $matrices[$tableKey][$key]=['value'=>$value];
            }
            break;
        }
        $matrices[$tableKey]=$this->addTableCmds($matrices[$tableKey]??[],$selector);
        $html='';
        foreach($matrices as $caption=>$matrix){
            $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>$matrix,'caption'=>$caption,'keep-element-content'=>TRUE,'hideKeys'=>FALSE,'hideHeader'=>FALSE]);
        }
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'article','element-content'=>$html,'keep-element-content'=>TRUE]);
        return $html;
    }

    private function addTableCmds(array $matrix,array $selector):array
    {
        $btns=['INDICES'=>'Set standard indices','TRUNCATE'=>'Empty table','DROP'=>'Drop table'];
        $btnArr=['tag'=>'button','element-content'=>'','keep-element-content'=>TRUE,'hasCover'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__];
        foreach($btns as $sqlCmd=>$key){
            $btnArr['element-content']=$key;
            $btnArr['key']=[$sqlCmd,$selector['Source']];
            $matrix[$key]=['value'=>$this->oc['SourcePot\Datapool\Foundation\Element']->element($btnArr)];
        }
        return $matrix;
    }
    
    private function tableCmdsProcessing()
    {
        $context=['currentUser'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract([],4),'class'=>__CLASS__,'function'=>__FUNCTION__,];
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,'addTableCmds');
        if (isset($formData['cmd']['INDICES'])){
            $context['table']=key($formData['cmd']['INDICES']);
            $this->oc['SourcePot\Datapool\Foundation\Database']->setTableIndices($context['table']);
            $this->oc['logger']->log('notice','User "{currentUser}" set standard inices for table "{table}".',$context);
        } else if (isset($formData['cmd']['DROP'])){
            $context['table']=key($formData['cmd']['DROP']);
            $sql='DROP TABLE '.$context['table'].';';
            $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql,[],FALSE);
            $this->oc['logger']->log('notice','User "{currentUser}" dropped table "{table}"',$context);
            // Reset $GLOBALS['dbInfo'] to create table
            $baseClass=$GLOBALS['dbInfo'][$context['table']]['EntryId']['baseClass'];
            unset($GLOBALS['dbInfo'][$context['table']]);
            $this->oc['logger']->log('notice','User "{currentUser}" dropped table "{table}" and re-created this table.',$context);
        } else if (isset($formData['cmd']['TRUNCATE'])){
            $context['table']=key($formData['cmd']['TRUNCATE']);
            $sql='TRUNCATE TABLE '.$context['table'].';';
            $stmt=$this->oc['SourcePot\Datapool\Foundation\Database']->executeStatement($sql,[],FALSE);
            $this->oc['logger']->log('notice','User "{currentUser}" emptied table "{table}".',$context);
        }
    }
}
?>