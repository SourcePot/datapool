<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Database{

    private $oc;
    
    private $statistic=array();
    private $toReplace=array();
    
    private $dbObj;
    private $dbName='';
    
    private $entryTable='settings';
    private $entryTemplate=array();

    private $rootEntryTemplate=array('EntryId'=>array('type'=>'VARCHAR(255)','value'=>'{{EntryId}}','Description'=>'This is the unique entry key, e.g. EntryId, User hash, etc.','Write'=>0),
                                 'Group'=>array('type'=>'VARCHAR(255)','value'=>'...','Description'=>'First level ordering criterion'),
                                 'Folder'=>array('type'=>'VARCHAR(255)','value'=>'...','Description'=>'Second level ordering criterion'),
                                 'Name'=>array('type'=>'VARCHAR(1024)','value'=>'New','Description'=>'Third level ordering criterion'),
                                 'Type'=>array('type'=>'VARCHAR(100)','value'=>'000000|en|000|{{Source}}','Description'=>'This is the data-type of Content'),
                                 'Date'=>array('type'=>'DATETIME','value'=>'{{NOW}}','Description'=>'This is the entry date and time'),
                                 'Content'=>array('type'=>'LONGBLOB','value'=>array(),'Description'=>'This is the entry Content, the structure of depends on the MIME-type.'),
                                 'Params'=>array('type'=>'LONGBLOB','value'=>array(),'Description'=>'This are the entry Params, e.g. file information of any file attached to the entry, size, name, MIME-type etc.'),
                                 'Expires'=>array('type'=>'DATETIME','value'=>\SourcePot\Datapool\Root::NULL_DATE,'Description'=>'If the current date is later than the Expires-date the entry will be deleted. On insert-entry the init-value is used only if the Owner is not anonymous, set to 10mins otherwise.'),
                                 'Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>0,'Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>0,'Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
                                 'Owner'=>array('type'=>'VARCHAR(100)','value'=>'{{Owner}}','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.')
                                 );
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $this->resetStatistic();
        $this->connect();
        $this->collectDatabaseInfo();
        // set defualt entry access rights
        $accessOptions=$oc['SourcePot\Datapool\Foundation\Access']->getAccessOptions();
        $this->rootEntryTemplate['Read']['value']=$accessOptions['ALL_CONTENTADMIN_R'];
        $this->rootEntryTemplate['Write']['value']=$accessOptions['ALL_CONTENTADMIN_R'];
    }

    public function init(array $oc):void
    {
        $this->oc=$oc;
        $this->entryTemplate=$this->getEntryTemplateCreateTable($this->entryTable);
    }
    
    public function job(array $vars):array
    {
        $vars['statistics']=$this->deleteExpiredEntries();
        $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'Deleted expired entries',$vars['statistics']['deleted'],'int'); 
        return $vars;
    }

    public function getDbStatus():string|bool
    {
        if (isset($this->dbObj)){
            return $this->dbObj->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
        } else {
            return FALSE;
        }
    }

    public function enrichToReplace(array $toReplace=array()):array
    {
        $toReplace['{{NOW}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
        $toReplace['{{YESTERDAY}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('yesterday');
        $toReplace['{{TOMORROW}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('tomorrow');
        $toReplace['{{TIMEZONE}}']=\SourcePot\Datapool\Root::DB_TIMEZONE;
        $toReplace['{{TIMEZONE-SERVER}}']=date_default_timezone_get();
        $toReplace['{{Expires}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','PT10M');
        $toReplace['{{EntryId}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getEntryId();
        if (!isset($_SESSION['currentUser']['EntryId'])){
            $toReplace['{{Owner}}']='SYSTEM';
        } else if (mb_strpos($_SESSION['currentUser']['EntryId'],'EID')===FALSE){
            $toReplace['{{Owner}}']=$_SESSION['currentUser']['EntryId'];
        } else {
            $toReplace['{{Owner}}']='ANONYM';
        }
        if (isset($this->oc['SourcePot\Datapool\Tools\HTMLbuilder'])){
            $toReplace['{{pageTitle}}']=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');
            $toReplace['{{pageTimeZone}}']=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
        }
        return $toReplace;
    }

    public function resetStatistic():array
    {
        $_SESSION[__CLASS__]['Statistic']=array('matches'=>0,'updated'=>0,'inserted'=>0,'deleted'=>0,'removed'=>0,'failed'=>0,'skipped'=>0);
        return $_SESSION[__CLASS__]['Statistic'];
    }
    
    public function addStatistic($key,$amount):array
    {
        if (!isset($_SESSION[__CLASS__]['Statistic'])){$this->resetStatistic();}
        $_SESSION[__CLASS__]['Statistic'][$key]+=$amount;
        return $_SESSION[__CLASS__]['Statistic'];
    }
    
    public function getStatistic($key=FALSE):array|int
    {
        if (isset($_SESSION[__CLASS__]['Statistic'][$key])){
            return $_SESSION[__CLASS__]['Statistic'][$key];
        } else {
            return $_SESSION[__CLASS__]['Statistic'];
        }
    }
    
    public function statistic2matrix():array
    {
        $matrix=array();
        if (isset($_SESSION[__CLASS__]['Statistic'])){
            foreach($_SESSION[__CLASS__]['Statistic'] as $key=>$value){
                $matrix[$key]=array('Value'=>$value);
            }
        }
        return $matrix;
    }
    
    /**
    * @return string|FALSE The method returns the database name or FALSE if connection to the database failed.
    */
    public function getDbName():string
    {
        return $this->dbName;
    }
    
    /**
    * @return array|FALSE The method returns entry template for all columns of the provided database table or all columns of all tables if no table is provided or FALSE if the table does not exist.
    */
    public function getEntryTemplate(string|bool $table=FALSE):array|bool
    {
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__,'table'=>$table);
        if ($table){
            if (isset($GLOBALS['dbInfo'][$table])){return $GLOBALS['dbInfo'][$table];}
        } else {
            return $GLOBALS['dbInfo'];
        }
        if (isset($this->oc['logger'])){
            $this->oc['logger']->log('warning','Function "{class}::{function}" called with table="{table}" returned false, table missing.',$context);
        }
        return FALSE;
    }
    
    /**
    * This function returns the entry template based on the root entry template.
    * It creates all tables which do not exist.
    *
    * @return array The method returns the entry template array for the table.
    */
    public function getEntryTemplateCreateTable(string $table,string $callingClass='',$isDebugging=FALSE):array
    {
        // If template is registered already, return this template
        if (isset($GLOBALS['dbInfo'][$table]['EntryId']['baseClass'])){
            return $GLOBALS['dbInfo'][$table];
        }
        // Get template
        $entryTemplate=array_merge($this->rootEntryTemplate);
        if (method_exists($callingClass,'getEntryTemplate')){
            $entryTemplate['EntryId']['baseClass']=$callingClass;
            $entryTemplate=array_merge($entryTemplate,$this->oc[$callingClass]->getEntryTemplate());                
        }
        // check if database table is missing
        if (!isset($GLOBALS['dbInfo'][$table])){
            // create column definition sql
            $columnsDefSql='';
            foreach($entryTemplate as $column=>$colTemplate){
                if (!empty($columnsDefSql)){$columnsDefSql.=", ";}
                $columnsDefSql.="`".$column."` ".$colTemplate['type'];
            }
            // create table
            $sql="CREATE TABLE `".$table."` (".$columnsDefSql.") DEFAULT CHARSET=utf8 COLLATE utf8_unicode_ci;";
            $this->executeStatement($sql,array());
            if (isset($this->oc['logger'])){
                $this->oc['logger']->log('notice','Created missing database table "{table}"',array('table'=>$table,'function'=>__FUNCTION__,'class'=>__CLASS__));
            }
            // set standard indices
            $this->setTableIndices($table);
        }
        return $GLOBALS['dbInfo'][$table]=$entryTemplate;
    }
    
    public function getTableIndices(string $table):array
    {
        $indices=array();
        $sql="SHOW INDEXES FROM `".$table."`;";
        $stmt=$this->executeStatement($sql,array());
        while($row=$stmt->fetch(\PDO::FETCH_ASSOC)){
            $indices[$row["Key_name"]]=$row;
        }
        return $indices;
    }

    /**
    * The method removes old indices as well as the primary key and adds the standard indices
    *
    * @param array $tabel Is the database table  
    * @return PDOstatement Is teh statement after execution of the prepared sql-statement
    */
    public function setTableIndices(string $table)
    {
        $context=array('table'=>$table,'class'=>__CLASS__,'function'=>__FUNCTION__,'dropped'=>'');
        // drop all existing indices
        $sql="";
        $indices=$this->getTableIndices($table);
        foreach($indices as $keyName=>$indexArr){
            $context['dropped']=$keyName.' | ';
            $sql.="ALTER TABLE `".$table."` DROP INDEX `".$keyName."`;"; 
        }
        if (!empty($sql)){$this->executeStatement($sql,array());}
        $context['dropped']=trim($context['dropped'],'| ');
        $this->oc['logger']->log('notice','Existing indices "{dropped}" of database table "{table}" dropped',$context);
        // set new indices
        $sql="";
        $sql.="ALTER TABLE `".$table."` ADD PRIMARY KEY (`EntryId`);";
        $sql.="ALTER TABLE `".$table."` ADD INDEX STD (`EntryId`(40),`Group`(30),`Folder`(30),`Name`(40));";
        $this->oc['logger']->log('notice','Added index to database table "{table}"',$context);
        return $this->executeStatement($sql,array());
    }

    /**
    * This function selects the entry-specific unifyEntry() function based on $entry['Source']
    * If the $entry-specific unifyEntry() function is found it will be used to unify the entry.
    */
    public function unifyEntry(array $entry,bool $addEntryDefaults=FALSE):array
    {
        if (empty($entry['Source'])){
            throw new \ErrorException('Method '.__FUNCTION__.' called with empty entry Source.',0,E_ERROR,__FILE__,__LINE__);    
        }
        $classWithNamespace=$this->oc['SourcePot\Datapool\Root']->source2class($entry['Source']);
        $registeredMethods=$this->oc['SourcePot\Datapool\Root']->getRegisteredMethods('unifyEntry');    
        
        $entry=$this->addType2entry($entry);
        
        //$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($entry,hrtime(TRUE).'-'.$entry['Source']);
        
        $entry=$this->addType2entry($entry);
        if (isset($registeredMethods[$classWithNamespace])){
            $entry=$this->oc[$classWithNamespace]->unifyEntry($entry);
        } else if ($addEntryDefaults){
            $entry=$this->addEntryDefaults($entry);
        }
        return $entry;    
    }

    public function addEntryDefaults(array $entry,bool $isDebugging=FALSE):array
    {
        $entryTemplate=(isset($GLOBALS['dbInfo'][$entry['Source']]))?$GLOBALS['dbInfo'][$entry['Source']]:$this->rootEntryTemplate;
        $toReplace=$this->getReplacmentArr($entry,$entryTemplate);
        $debugArr=array('entryTemplate'=>$entryTemplate,'entry in'=>$entry,'toReplace'=>$toReplace);
        foreach($entryTemplate as $column=>$defArr){
            if (!isset($defArr['value'])){
                continue;
            } else if (!isset($entry[$column])){
                $entry[$column]=$defArr['value'];
            } else if ($defArr['value']===TRUE && empty($entry[$column])){
                $entry[$column]=$defArr['value'];
            } else if (!empty($defArr['value']) && $entry[$column]===FALSE){
                $entry[$column]=$defArr['value'];
            }
            $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,$column);
            if (is_string($entry[$column])){
                $entry[$column]=strtr($entry[$column],$toReplace);
            }
        } // loop through entry-template-array
        $debugArr['entry out']=$entry;
        if ($isDebugging){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr,__FUNCTION__.'-'.$entry['Source']);
        }
        return $entry;
    }

    private function getReplacmentArr(array $entry,array $entryTemplate=array()):array
    {
        $toReplace=array();
        foreach($entry as $column=>$value){
            if (is_array($value)){continue;}
            $value=strval($value);
            if (empty($value) && !empty($entryTemplate[$column]['value'])){$value=strval($entryTemplate[$column]['value']);}
            $toReplace['{{'.$column.'}}']=$value;
        }
        $toReplace=$this->enrichToReplace($toReplace);
        return $toReplace;
    }
    
    /**
    * The method adds entry-Type and returns the entry. Entry-Type is the second- level selector after EntryId, Name, Folder and Group.
    * The selector is used for language-specific as well as file MIME-type specific entry handling.
    *
    * @param array $entry Is the orginal entry  
    * @return array $entry Is the enriched entry
    */
    public function addType2entry(array $entry):array
    {
        // recover existing type
        $typeComps=explode('|',(strval($entry['Type']??'')));
        if (count($typeComps)===4){
            $typeArr=$typeComps;
        } else {
            $typeArr=array();
        }
        // is guiede entry?
        if (mb_strpos(strval($entry['EntryId']??''),\SourcePot\Datapool\Root::GUIDEINDICATOR)!==FALSE){
            // if guide entry, this is the most probable option 
            $typeArr[0]=\SourcePot\Datapool\Root::GUIDEINDICATOR;
        } else if (mb_strpos(strval($entry['Name']??''),\SourcePot\Datapool\Root::GUIDEINDICATOR)!==FALSE){
            $typeArr[0]=\SourcePot\Datapool\Root::GUIDEINDICATOR;
        } else if (mb_strpos(strval($entry['Folder']??''),\SourcePot\Datapool\Root::GUIDEINDICATOR)!==FALSE){
            $typeArr[0]=\SourcePot\Datapool\Root::GUIDEINDICATOR;
        } else if (mb_strpos(strval($entry['Group']??''),\SourcePot\Datapool\Root::GUIDEINDICATOR)!==FALSE){
            $typeArr[0]=\SourcePot\Datapool\Root::GUIDEINDICATOR;
        } else {
            $typeArr[0]=str_pad('',strlen(\SourcePot\Datapool\Root::GUIDEINDICATOR),'0');
        }
        // use language code?
        $typeArr[1]=(empty(\SourcePot\Datapool\Root::USE_LANGUAGE_IN_TYPE[$entry['Source']]))?('00'):$_SESSION['page state']['lngCode'];
        // MIME-type of linked file
        if (empty($entry['Params']['File']['MIME-Type'])){
            $typeArr[2]='000';
        } else {
            $mimeComps=explode('/',$entry['Params']['File']['MIME-Type']);
            $typeArr[2]=array_pop($mimeComps);
        }
        // keep original entry Source
        $typeArr[3]=$typeArr[3]??$entry['Source'];
        // finalize Type
        $entry['Type']=implode('|',$typeArr);
        return $entry;
    }

    /**
    * This function establishes the database connection and saves the PDO-object in dbObj.
    * The database user credentials will be taken from 'connect.json' in the '.\setup\Database\' directory.
    * 'connect.json' file will be created if it does not exist. Make sure database user credentials in connect.json are valid for your database.
    */
    private function connect():string
    {
        $namespaceComps=explode('\\',__NAMESPACE__);
        $dbName=mb_strtolower($namespaceComps[0]);
        $access=array('Class'=>__CLASS__,'EntryId'=>'connect');
        $access['Read']=65535;
        $access['Content']=array('dbServer'=>'localhost','dbName'=>$dbName,'dbUser'=>'webpage','dbUserPsw'=>session_id());
        $access=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($access,TRUE);
        try{
            $this->dbObj=new \PDO('mysql:host='.$access['Content']['dbServer'].';dbname='.$access['Content']['dbName'],$access['Content']['dbUser'],$access['Content']['dbUserPsw']);
            $this->dbObj->exec("SET CHARACTER SET 'utf8'");
            $this->dbObj->exec("SET NAMES utf8mb4");
        } catch (\Exception $e){
            $msg=$e->getMessage();
            echo $this->oc['SourcePot\Datapool\Root']->getBackupPageContent('<i>The problem is: '.$msg.'</i>');
            exit(0);
        }
        $this->dbName=$access['Content']['dbName'];
        return $this->getDbStatus();
    }
    
    /**
    * The method collects all relevant data of the database tables.
    *
    * @return array Is content of the global variable 'dbInfo'
    */
    private function collectDatabaseInfo():array
    {
        $GLOBALS['dbInfo']=array();
        $sql='SHOW TABLES;';
        $stmt=$this->executeStatement($sql);
        $tables=$stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach($tables as $table){
            $table=current($table);
            $GLOBALS['dbInfo'][$table]=$this->rootEntryTemplate;
        }
        return $GLOBALS['dbInfo'];
    }

    public function executeStatement(string $sql,array $inputs=array()):object
    {
        $stmtArr=$this->bindValues($sql,$inputs);
        $this->oc['SourcePot\Datapool\Root']->startStopWatch(__CLASS__,__FUNCTION__,$stmtArr['sqlSimulated']);
        try{
            $stmtArr['stmt']->execute();
            if (isset($this->oc['SourcePot\Datapool\Foundation\Haystack'])){
                $this->oc['SourcePot\Datapool\Foundation\Haystack']->processSQLquery($stmtArr);
            }
        } catch (\Exception $e){
            $context=$stmtArr;
            $context['error']=$e->getMessage();
            $this->oc['logger']->log('critical','SQL execution for "{sqlSimulated}" triggered error: "{error}".',$context);
        }
        $this->oc['SourcePot\Datapool\Root']->stopStopWatch(__CLASS__,__FUNCTION__,$stmtArr['sqlSimulated']);
        return $stmtArr['stmt'];
    }
    
    private function bindValues(string $sql,array $inputs=array()):array
    {
        $return=array('sql'=>$sql,'input'=>$inputs,'stmt'=>$this->dbObj->prepare($sql),'sqlSimulated'=>$sql);
        foreach($inputs as $bindKey=>$bindValue){
            $simulatedValue="'".$bindValue."'";
            $return['sqlSimulated']=str_replace($bindKey,$simulatedValue,$return['sqlSimulated']);
            $return['stmt']->bindValue($bindKey,$bindValue);
        }
        return $return;
    }

    private function deleteExpiredEntries():array
    {
        foreach($GLOBALS['dbInfo'] as $table=>$entryTemplate){
            $selector=array('Source'=>$table,'Expires<'=>date('Y-m-d H:i:s'));
            $this->deleteEntries($selector,TRUE);
        }
        return $this->getStatistic();
    }
    
    private function containsStringWildCards(string $string='abcd\_fgh\%jkl'):int|bool
    {
        return preg_match('/[^\\\\][%_]{1}/',$string);
    }
    
    private function addSelector2result(array $selector,array $result):array
    {
        $selector['app']=$selector['app']??'';
        $result=array_merge($selector,$result);
        return $result;
    }
    
    /**
    * This function creates a sql-query from a selector.
    * For types VARCHAR and BLOB the mysql keyword LIKE is used, for all other datatypes math operators will be used.
    * If no operator is provided the '=' operator will be applied. Use '!' operator for 'NOT EQUAL'.
    * Operator need to be added to the end of the column name with the selector,
    * e.g. column name 'Date>=' means Dates larger than or equal to the value provided in the selctor array will be returned.
    * If the selector-key contains the flat-array-key separator, the first part of the key is used as column, 
    * e.g. 'Date|[]|Start' -> refers to column 'Date'.
    */
    private function selector2sql($selector,$removeGuideEntries=TRUE,$isDebugging=FALSE){
        if ($removeGuideEntries){$selector['Type=!']=\SourcePot\Datapool\Root::GUIDEINDICATOR.'%';}
        $entryTemplate=$GLOBALS['dbInfo'][$selector['Source']];
        $opAlias=array('<'=>'LT','<='=>'LE','=<'=>'LE','>'=>'GT','>='=>'GE','=>'=>'GE','='=>'EQ','!'=>'NOT','!='=>'NOT','=!'=>'NOT');
        $sqlArr=array('sql'=>array(),'inputs'=>array());            
        foreach($selector as $column=>$value){
            if ($value===FALSE || $value==\SourcePot\Datapool\Root::GUIDEINDICATOR){continue;}
            preg_match('/([^<>=!]+)([<>=!]+)/',$column,$match);
            if (!empty($match[2])){$operator=$match[2];} else {$operator='=';}
            $placeholder=':'.md5($column.$opAlias[$operator]);
            $columns=explode($this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator(),$column);
            $column=trim($columns[0],' <>=!');
            if (!isset($entryTemplate[$column])){continue;}
            if (is_array($value)){$value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($value);}
            if ((mb_strpos($entryTemplate[$column]['type'],'VARCHAR')!==FALSE || mb_strpos($entryTemplate[$column]['type'],'BLOB')!==FALSE) || $this->containsStringWildCards(strval($value))){
                $column='`'.$column.'`';
                if (empty($value)){
                    if ($operator==='<' || $operator==='>' || $operator==='!'){$value='';} else {continue;}
                } else {
                    $value=addslashes($value);
                }
                $query=match($operator){
                    '=' => $column.' LIKE '.$placeholder,
                    default => $column.' NOT LIKE '.$placeholder
                };    
            } else {
                if (empty($value)){$value=0;}
                $column='`'.$column.'`';
                $query=match($operator){
                    '<' => $column.'<'.$placeholder,
                    '<=','=<' => $column.'<='.$placeholder,
                    '>' => $column.'>'.$placeholder,
                    '>=','=>' => $column.'>='.$placeholder,
                    '!','!=','=!' => $column.'<>'.$placeholder,
                    default => $column.'='.$placeholder
                };
            }
            $sqlArr['sql'][]=$query;
            $sqlArr['inputs'][$placeholder]=$value;
        }
        $sqlArr['sql']=implode(' AND ',$sqlArr['sql']);
        if (empty($sqlArr['sql'])){$sqlArr['sql']='';} else {$sqlArr['sql']=' WHERE '.$sqlArr['sql'];}
        if ($isDebugging){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file(array('selector'=>$selector,'sqlArr'=>$sqlArr,'entryTemplate'=>$entryTemplate));
        }
        return $sqlArr;        
    }    
    
    private function addRights2sql($sqlArr,$user,$isSystemCall=FALSE,$rightType='Read'){
        if ($isSystemCall===TRUE){return $sqlArr;}
        if (strcmp($rightType,'Read')!==0 && strcmp($rightType,'Write')!==0){
            throw new \ErrorException('Function '.__FUNCTION__.': right type '.$rightType.' unknown.',0,E_ERROR,__FILE__,__LINE__);    
        }
        if (empty($user['Owner'])){$user['Owner']='SYSTEM';}
        $user['Owner']=str_replace('%','\%',$user['Owner']);
        $user['Owner']=str_replace('_','\_',$user['Owner']);
        if (!empty($sqlArr['sql'])){$sqlArr['sql'].=" AND";}
        $sqlArr['sql'].=" (((`".$rightType."` & ".intval($user['Privileges']).")>0) OR (`Owner` LIKE :Owner))";
        $sqlArr['inputs'][':Owner']=$user['Owner'];
        if (mb_strpos($sqlArr['sql'],'WHERE')===FALSE){$sqlArr['sql']=' WHERE '.$sqlArr['sql'];}
        return $sqlArr;
    }
    
    private function addSuffix2sql($sqlArr,$entryTemplate,$orderBy='Name',$isAsc=TRUE,$limit=FALSE,$offset=FALSE){
        if (!empty($orderBy) && isset($entryTemplate[$orderBy])){
            $sqlArr['sql'].=' ORDER BY `'.$orderBy.'`';
            if ($isAsc===TRUE){$sqlArr['sql'].=' ASC';} else {$sqlArr['sql'].=' DESC';}
        } else if ($orderBy=='rand()' || $orderBy=='RAND()'){
            $sqlArr['sql'].=' ORDER BY rand()';    
        }
        $limit=intval($limit);
        if (!empty($limit)){$sqlArr['sql'].=' LIMIT '.$limit;}
        $offset=intval($offset);
        if (!empty($offset)){$sqlArr['sql'].=' OFFSET '.$offset;}
        return $sqlArr;
    }
    
    private function addColumnValue2result($result,$column,$value,$entryTemplate){
        if (!isset($entryTemplate[$column]['value'])){
            $result[$column]=$value;
        } else if (is_array($entryTemplate[$column]['value'])){
            $result[$column]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr($value);
        } else if (mb_strpos($entryTemplate[$column]['type'],'INT')!==FALSE){
            $result[$column]=intval($value);
        } else if (mb_strpos($entryTemplate[$column]['type'],'FLOAT')!==FALSE || mb_strpos($entryTemplate[$column]['type'],'DOUBLE')!==FALSE){
            $result[$column]=floatval($value);
        } else {
            $result[$column]=$value;
        }
        return $result;    
    }

    private function standardSelectQuery($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$removeGuideEntries=TRUE){
        if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
        $sqlArr=$this->selector2sql($selector,$removeGuideEntries);
        $sqlArr=$this->addRights2sql($sqlArr,$user,$isSystemCall,$rightType);
        $sqlArr=$this->addSuffix2sql($sqlArr,$GLOBALS['dbInfo'][$selector['Source']],$orderBy,$isAsc,$limit,$offset);
        return $sqlArr;
    }
    
    public function getRowCount($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$removeGuideEntries=TRUE,$isDebugging=FALSE){
        if (empty($selector['Source']) || !isset($GLOBALS['dbInfo'][$selector['Source']])){return 0;}
        // count all selected rows
        $sqlArr=$this->standardSelectQuery($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$removeGuideEntries);
        $sqlArr['sql']='SELECT COUNT(*) FROM `'.$selector['Source'].'`'.$sqlArr['sql'].';';
        $stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],$isDebugging);
        $rowCount=current($stmt->fetch());
        return intval($rowCount);
    }
    
    public function entriesByRight($column='Read',$right='ADMIN_R',$returnPrimaryKeyOnly=TRUE){
        $selector=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable());
        if ($returnPrimaryKeyOnly){$return='EntryId';} else {$return='*';}
        $rights=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights(array(),$right,$right);
        $right=intval($rights['Read']);
        $sql="SELECT ".$return." FROM `".$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable()."` WHERE ((`".$column."` & ".$right.")>0);";
        $stmt=$this->executeStatement($sql);
        $entries=array();
        while (($row=$stmt->fetch(\PDO::FETCH_ASSOC))!==FALSE){
            foreach($row as $column=>$value){
                $row=$this->addColumnValue2result($row,$column,$value,$GLOBALS['dbInfo'][$selector['Source']]);
            }
            $row=$this->oc['SourcePot\Datapool\Tools\FileContent']->enrichEntry($row);
            $entries[$row['EntryId']]=$row;
        }
        return $entries;
    }
    
    public function getDistinct(array $selector,string $column,bool $isSystemCall=FALSE,string $rightType='Read',string|bool $orderBy=FALSE,bool $isAsc=TRUE,int|bool|string $limit=FALSE,int|bool|string $offset=FALSE,bool $removeGuideEntries=FALSE):\Generator
    {
        $result=array('isFirst'=>TRUE,'rowIndex'=>0,'rowCount'=>0,'hash'=>'');
        $column=trim($column,'!');
        if (strcmp($column,'Source')===0){
            $tableArr=$GLOBALS['dbInfo'];
            if ($isAsc){ksort($tableArr);} else {krsort($tableArr);}
            foreach($GLOBALS['dbInfo'] as $table=>$tableInfoArr){
                $result['Source']=$table;
                yield $result;
                $result['rowIndex']++;
            }
        } else if (!isset($GLOBALS['dbInfo'][$selector['Source']])){
            // selected table does not exist
        } else {
            $sqlArr=$this->standardSelectQuery($selector,$isSystemCall,$rightType,$column,$isAsc,$limit,$offset,$removeGuideEntries);
            $selectExprSQL='';
            $sqlArr['sql']='SELECT DISTINCT '.$selector['Source'].'.'.$column.' FROM `'.$selector['Source'].'`'.$sqlArr['sql'];
            $sqlArr['sql'].=';';
            //var_dump($sqlArr);
            $stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
            $result=array('isFirst'=>TRUE,'rowIndex'=>0,'rowCount'=>$stmt->rowCount(),'Source'=>$selector['Source'],'hash'=>'');
            $this->addStatistic('matches',$result['rowCount']);
            while (($row=$stmt->fetch(\PDO::FETCH_ASSOC))!==FALSE){
                foreach($row as $column=>$value){
                    $result=$this->addColumnValue2result($result,$column,$value,$GLOBALS['dbInfo'][$selector['Source']]);
                }
                $result=$this->oc['SourcePot\Datapool\Tools\FileContent']->enrichEntry($result);
                $result=$this->addSelector2result($selector,$result);
                yield $result;
                $result['isFirst']=FALSE;
                $result['rowIndex']++;
            }
        }
    }
    
    public function entryIterator(array $selector,bool $isSystemCall=FALSE,string $rightType='Read',string|bool $orderBy=FALSE,bool $isAsc=TRUE,int|bool|string $limit=FALSE,int|bool|string $offset=FALSE,array $selectExprArr=array(),bool $removeGuideEntries=TRUE,bool $isDebugging=FALSE):\Generator
    {
        if (empty($selector['Source']) || !isset($GLOBALS['dbInfo'][$selector['Source']])){return array();}
        $sqlArr=$this->standardSelectQuery($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$removeGuideEntries);
        if (empty($selectExprArr)){
            $selectExprSQL=$selector['Source'].'.*';
        } else {
            if (!in_array('EntryId',$selectExprArr)){$selectExprArr[]='EntryId';}
            $selectExprSQL=$selector['Source'].'.'.implode(','.$selector['Source'].'.',$selectExprArr);
        }
        $sqlArr['sql']='SELECT '.$selectExprSQL.' FROM `'.$selector['Source'].'`'.$sqlArr['sql'];
        $sqlArr['sql'].=';';
        $stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],$isDebugging);
        $result=array('isFirst'=>TRUE,'isLast'=>TRUE,'rowIndex'=>-1,'rowCount'=>$stmt->rowCount(),'now'=>time(),'Source'=>$selector['Source'],'hash'=>'');
        $this->addStatistic('matches',$result['rowCount']);
        while (($row=$stmt->fetch(\PDO::FETCH_ASSOC))!==FALSE){
            $result['rowIndex']++;
            if (mb_strpos($row['EntryId'],\SourcePot\Datapool\Root::GUIDEINDICATOR)===FALSE){$result['isSkipRow']=FALSE;} else {$result['isSkipRow']=TRUE;}
            foreach($row as $column=>$value){
                $result['hash']=crc32($result['hash'].$value);
                $result=$this->addColumnValue2result($result,$column,$value,$GLOBALS['dbInfo'][$selector['Source']]);
            }
            $result['isLast']=($result['rowIndex']+1)===$result['rowCount'];
            $result=$this->oc['SourcePot\Datapool\Tools\FileContent']->enrichEntry($result);
            $result=$this->addSelector2result($selector,$result);
            yield $result;
            $result['isFirst']=FALSE;
        }
    }
    
    public function entryById(array|bool $selector,bool $isSystemCall=FALSE,string $rightType='Read',bool $returnMetaOnNoMatch=FALSE):array
    {
        $result=array();
        if (empty($selector['Source'])){
            return $result;
        } else if (strcmp($selector['Source'],'!GUIDE')===0){
            return $result;
        }
        // get entry
        if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
        if (!empty($selector['EntryId'])){
            $sqlPlaceholder=':'.'EntryId';
            $sqlArr=array('sql'=>"SELECT * FROM `".$selector['Source']."` WHERE `".'EntryId'."`=".$sqlPlaceholder,'inputs'=>array($sqlPlaceholder=>$selector['EntryId']));
            $sqlArr=$this->addRights2sql($sqlArr,$user,$isSystemCall,$rightType);
            $sqlArr['sql'].=';';
            //var_dump($sqlArr);
            $stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
            $result=array('isFirst'=>TRUE,'rowIndex'=>0,'rowCount'=>$stmt->rowCount(),'primaryKey'=>'EntryId','primaryValue'=>$selector['EntryId'],'Source'=>$selector['Source']);
            $this->addStatistic('matches',$result['rowCount']);
            $row=$stmt->fetch(\PDO::FETCH_ASSOC);
            if (is_array($row)){
                foreach($row as $column=>$value){
                    $result=$this->addColumnValue2result($result,$column,$value,$GLOBALS['dbInfo'][$selector['Source']]);
                }
                $result=$this->oc['SourcePot\Datapool\Tools\FileContent']->enrichEntry($result);
                $result=$this->addSelector2result($selector,$result);
            } else {
                if (!$returnMetaOnNoMatch){$result=array();}
            }
        }
        return $result;
    }
    
    /**
    * The method creates an array containing an EntryId-list as SQL-suffix of the entries selected by the method parameters.
    *
    * @param array $selector Is the selector  
    * @param boolean $isSystemCall If true read and write access is granted  
    * @param boolean $rightType Sets the relevant right-type for the creation of the EntryId-list
    * @param boolean $orderBy Selects the column the EntryId-list will be ordered by
    * @param boolean $isAsc Selects order direction for the EntryId-list
    * @param boolean $limit Limits the size of the Entry-list
    * @param boolean $offset Set the start of the Entry-list
    * @param array $selectExprArr Sets the select column of the database table (is irrelevant in this context)
    * @param boolean $removeGuideEntries If true Guide-entries will be removed from the Entry-list
    *
    * @return array Array containing the EntryId-list as SQL-suiffix
    */
    private function sqlEntryIdListSelector(array $selector,bool $isSystemCall=FALSE,string $rightType='Read',string|bool $orderBy=FALSE,bool $isAsc=TRUE,int|bool|string $limit=FALSE,int|bool|string $offset=FALSE,array $selectExprArr=array('EntryId'),bool $removeGuideEntries=FALSE,bool $isDebugging=FALSE):array
    {
        $result=array('primaryKeys'=>array(),'sql'=>'','primaryKey'=>'EntryId');
        foreach($this->entryIterator($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$selectExprArr,$removeGuideEntries,$isDebugging) as $row){
            $result['sql'].=",'".$row['EntryId']."'";
            $result['primaryKeys'][]=$row['EntryId'];
        }
        $result['sql']='WHERE `'.'EntryId'.'` IN('.trim($result['sql'],',').')';
        return $result;
    }    
        
    /**
    * This method deletes the selected entries and returns the count of deleted entries or false on error.
    *
    * @param array $selector Is the selector to select the entries to be deleted  
    * @return int|boolean The count of deleted entries or false on failure
    */
    public function deleteEntriesOnly(array $selector,bool $isSystemCall=FALSE):array
    {
        if (empty($selector['Source']) || !isset($GLOBALS['dbInfo'][$selector['Source']])){
            return $this->getStatistic();
        }
        $sqlArr=$this->standardSelectQuery($selector,$isSystemCall,'Write',FALSE,TRUE,FALSE,FALSE,FALSE);
        $sqlArr['sql']='DELETE FROM `'.$selector['Source'].'`'.$sqlArr['sql'].';';
        $stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
        return $this->addStatistic('deleted',$stmt->rowCount());
    }
    
    /**
    * This method deletes the selected entries including linked files 
    * and returns the count of deleted entries or false on error.
    *
    * @param array $selector Is the selector to select the entries to be deleted  
    * @return int|boolean The count of deleted entries or false on failure
    */
    public function deleteEntries(array $selector,bool $isSystemCall=FALSE):array
    {
        // delete files
        $entryList=$this->sqlEntryIdListSelector($selector,$isSystemCall,'Read',FALSE,FALSE,FALSE,FALSE,array(),FALSE,FALSE);
        if (empty($entryList['primaryKeys'])){
            return $this->getStatistic();
        }
        foreach($entryList['primaryKeys'] as $index=>$primaryKeyValue){
            $entrySelector=array('Source'=>$selector['Source'],'EntryId'=>$primaryKeyValue);
            $fileToDelete=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entrySelector);
            $this->removeFile($fileToDelete);
        }
        // delete entries by id-list
        $sql='DELETE FROM `'.$selector['Source'].'`'.$entryList['sql'].';';
        $stmt=$this->executeStatement($sql,array(),FALSE);
        return $this->addStatistic('deleted',$stmt->rowCount());
    }
    
    /**
    * This method deletes files and updates the statistics.
    *
    * @param array $selector Is the selector to select the entries to be deleted  
    * @return int|boolean The count of deleted entries or false on failure
    */
    public function removeFile(string $file):bool
    {
        if (is_file($file)){
            $removed=unlink($file);
            $this->addStatistic('removed',intval($removed));
        }
        return $removed??FALSE;
    }
    
    /**
    * This method inserts the provided entry into the selected database table.
    * Default values are added if any entry property is missing.
    *
    * @param array $entry Is entry array, entry['Source'] and entry['EntryId'] must not be empty  
    * @return array|boolean The method returns the inserted entry or false
    */
    public function insertEntry(array $entry):array
    {
        $entryTemplate=$this->getEntryTemplate($entry['Source']);
        $entry=$this->addType2entry($entry);
        $entry=$this->addEntryDefaults($entry);
        if (!empty($entry['Owner'])){
            if (strcmp($entry['Owner'],'ANONYM')===0){
                $entry['Expires']=date('Y-m-d H:i:s',time()+600);
            }
        }
        $columns='';
        $values='';
        $inputs=array();
        foreach ($entry as $column => $value){
            if (!isset($entryTemplate[$column])){continue;}
            if (strcmp($column,'Source')===0){continue;}
            $sqlPlaceholder=':'.$column;
            $columns.='`'.$column.'`,';
            $values.=$sqlPlaceholder.',';
            if (is_array($value)){$value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($value);}
            $inputs[$sqlPlaceholder]=strval($value);
        }
        $sql="INSERT INTO `".$entry['Source']."` (".trim($columns,',').") VALUES (".trim($values,',').") ON DUPLICATE KEY UPDATE `EntryId`='".$entry['EntryId']."';";
        $stmt=$this->executeStatement($sql,$inputs,FALSE);
        $this->addStatistic('inserted',$stmt->rowCount());
        $entry=$this->oc['SourcePot\Datapool\Tools\FileContent']->enrichEntry($entry);
        return $entry;
    }

    /**
    * The method selects entries based on the selector and updates these entries based on the provided entry.
    * The method employs a two step approach: 
    * 1. Creation of an EntryId-list based on the provided selector and parameters, read access is required
    * 2. Update based on the EntryId-list, write access is required
    *
    * @param array $selector Is the selector  
    * @param array $entry Is the template used for the entry update  
    * @param boolean $isSystemCall If true read and write access is granted  
    * @param boolean $rightType Sets the relevant right-type for the creation of the EntryId-list
    * @param boolean $orderBy Selects the column, the EntryId-list will be ordered by
    * @param boolean $isAsc Selects order direction for the EntryId-list
    * @param boolean $limit Limits the size of the Entry-list
    * @param boolean $offset Set the start of the Entry-list
    * @param array $selectExprArr Sets the select column of the database table (is irrelevant in this context)
    * @param boolean $removeGuideEntries If true Guide-entries will be removed from the Entry-list
    *
    * @return int|boolean The updated entry count or false on failure
    */
    public function updateEntries($selector,$entry,$isSystemCall=FALSE,$rightType='Write',$orderBy=FALSE,$isAsc=FALSE,$limit=FALSE,$offset=FALSE,$selectExprArr=array(),$removeGuideEntries=FALSE,$isDebugging=FALSE):int|bool
    {
        // only the Admin has the right to change data in the Privileges column
        if (!empty($entry['Privileges']) && !$this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin() && !$isSystemCall){
            unset($entry['Privileges']);
        }
        if (empty($entry)){return FALSE;}
        //
        $entryList=$this->sqlEntryIdListSelector($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$selectExprArr,$removeGuideEntries,$isDebugging);
        $entryTemplate=$this->getEntryTemplate($selector['Source']);
        if (empty($entryList['primaryKeys'])){
            return FALSE;
        } else {
            // set values
            $inputs=array();
            $valueSql='';
            foreach($entry as $column=>$value){
                if (!isset($entryTemplate[$column])){continue;}
                if ($value===FALSE){continue;}
                if (strcmp($column,'Source')===0){continue;}
                $sqlPlaceholder=':'.$column;
                $valueSql.="`".$column."`=".$sqlPlaceholder.",";
                if (is_array($value)){$value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($value);}
                $inputs[$sqlPlaceholder]=strval($value);
            }
            $sql="UPDATE `".$selector['Source']."` SET ".trim($valueSql,',')." ".$entryList['sql'].";";
            $stmt=$this->executeStatement($sql,$inputs,$isDebugging);
            $this->addStatistic('updated',$stmt->rowCount());
            return $this->getStatistic('updated');
        }
    }
    
    /**
    * The method updates an existing entry (based on the columns provided) OR inserts an entry that does not exist. .
    * Default values are added if any entry property is missing.
    *
    * @param array $entry Is entry array, entry['Source'] and entry['EntryId'] must not be empty  
    * @param boolean $isSystemCall The value is provided to access control to establish read/write access within the method 
    * @param boolean $noUpdateButCreateIfMissing If true, an existing entry won't be updated
    * @param boolean $addLog If true, an entry update will be documented in the entry processing log
    * @param string $attachment If a valid file name is provided the file is added to the entry
    *
    * @return array|boolean The inserted, updated or created entry, an empty array if access rights were insufficient or false on error.
    */
    public function updateEntry(array $entry,bool $isSystemCall=FALSE,bool $noUpdateButCreateIfMissing=FALSE,bool $addLog=FALSE,string $attachment=''):array|bool
    {
        // only the Admin has the right to update the Privileges column
        if (!empty($entry['Privileges']) && !$this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin() && !$isSystemCall){unset($entry['Privileges']);}
        // test for required keys and set selector
        if (empty($entry['Source']) || empty($entry['EntryId'])){return FALSE;}
        $selector=array('Source'=>$entry['Source'],'EntryId'=>$entry['EntryId']);
        // replace right constants
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Read');
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Write');
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Privileges');
        // get existing entry
        $existingEntry=$this->entryById($selector,TRUE,'Write',TRUE);
        if (empty($existingEntry['rowCount'])){
            // no existing entry found -> insert and return entry
            if (is_file($attachment)){
                // valid file attachment found
                $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->addFile2entry($entry,$attachment);
            } else {
                $currentFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
                if (!is_file($currentFile)){
                    // no valid file attachment. clear related meta data
                    if (isset($entry['Params']['Attachment log'])){unset($entry['Params']['Attachment log']);}
                    if (isset($entry['Params']['File'])){unset($entry['Params']['File']);}
                }
            }
            $entry=$this->addLog2entry($entry,'Processing log',array('msg'=>'Entry created'),FALSE);
            $entry=$this->insertEntry($entry);
        } else if (empty($noUpdateButCreateIfMissing) && $this->oc['SourcePot\Datapool\Foundation\Access']->access($existingEntry,'Write',FALSE,$isSystemCall)){
            // existing entry -> update
            $isSystemCall=TRUE; // if there is write access to an entry, missing read access must not interfere
            // add attachment 
            if (is_file($attachment)){
                if (!isset($entry['Params'])){$entry['Params']=$existingEntry['Params'];}
                $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->addFile2entry($entry,$attachment);
            }
            // add log
            if ($addLog){
                if (!isset($entry['Params'])){$entry['Params']=$existingEntry['Params'];}
                $entry=$this->addLog2entry($entry,'Processing log',array('msg'=>'Entry updated','Expires'=>date('Y-m-d H:i:s',time()+604800)),FALSE);
            }
            // update entry
            $this->updateEntries($selector,$entry,$isSystemCall,'Write',FALSE,FALSE,FALSE,FALSE,array(),FALSE,$isDebugging=FALSE);
            $entry=$this->entryById($selector,$isSystemCall,'Read');
        } else {
            // existing entry -> no update 
            $entry=$existingEntry;
        }
        return $entry;
    }
    
    /**
    * The method returns an entry selected by entry['Source'] and entry['EntryId'].
    * If the entry does not exist it will be created. An existing entry will not be updated.
    *
    * @param array $entry Is entry array, entry['Source'] and entry['EntryId'] must not be empty  
    * @param boolean $isSystemCall The value is provided to access control to establish read/write access within the method 
    *
    * @return array|boolean The entry, an empty array if right are insufficient or false on error.
    */
    public function entryByIdCreateIfMissing($entry,$isSystemCall=FALSE){
        return $this->updateEntry($entry,$isSystemCall,TRUE);
    }
    
    /**
    * The method returns the first entry that matches the selector or false, if no match is found.
    *
    * @param array $selector Is the selector.  
    * @param boolean $isSystemCall The value is provided to access control. 
    * @param boolean $returnMetaOnNoMatch If true and EntryId is provided, meta data is return on no match instead of false. 
    *
    * @return array|boolean The entry, an empty array or false if no entry was found.
    */
    public function hasEntry(array $selector,bool $isSystemCall=TRUE,string $rightType='Read',bool $removeGuideEntries=TRUE):array|bool
    {
        if (empty($selector['Source'])){return FALSE;}
        if (empty($selector['EntryId'])){
            foreach($this->entryIterator($selector,$isSystemCall,$rightType,FALSE,TRUE,2,FALSE,array(),$removeGuideEntries) as $entry){
                return $entry;
            }
        } else {
            return $this->entryById($selector,$isSystemCall,$rightType);
        }
        return FALSE;
    }
        
    /**
    * The method moves or copies an entry to the target selected by argument targetSelector
    *
    * @param array $sourceEntry Is the source entry.  
    * @param array $targetSelector Is the tsrget selector.  
    * @param boolean $isSystemCall The value is provided to access control. 
    * @param boolean $isTestRun If true, the source entry will not be copied or moved. 
    * @param boolean $keepSource If true, the entry will be copied, not moved. 
    * @param boolean $updateSourceFirst If true, the source entry will be updated before further processing. 
    *
    * @return array The target entry.
    */
    public function moveEntryOverwriteTarget($sourceEntry,$targetSelector,$isSystemCall=TRUE,$isTestRun=FALSE,$keepSource=FALSE,$updateSourceFirst=FALSE):array
    {
        // test for required keys and set selector
        if (empty($sourceEntry['Source']) || empty($sourceEntry['EntryId'])){
            throw new \ErrorException('Function '.__FUNCTION__.': Mandatory sourceEntry-key(s) missing, either Source or EntryId',0,E_ERROR,__FILE__,__LINE__);    
        }
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($sourceEntry,'Write',FALSE,$isSystemCall)){
            // write access
            if ($updateSourceFirst && !$isTestRun){
                $sourceEntry=$this->updateEntry($sourceEntry,$isSystemCall);
            }
            // apply target selector to source entry
            $targetEntry=array_replace_recursive($sourceEntry,$targetSelector);
            $targetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($targetEntry,array('Source','Group','Folder','Name'),'0','',FALSE);
            if (strcmp($sourceEntry['EntryId'],$targetEntry['EntryId'])===0){
                // source and target EntryId identical, attachment does not need to be touched
                $sourceEntry=$this->addLog2entry($sourceEntry,'Processing log',array('failed'=>'Target and source EntryId identical'),FALSE);
                if ($isTestRun){
                    $targetEntry=$sourceEntry;
                } else {
                    $targetEntry=$this->updateEntry($sourceEntry,$isSystemCall);
                }
            } else {
                // move or copy attached file to target
                $fileRenameSuccess=TRUE;
                $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
                $targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($targetEntry);
                if (is_file($sourceFile) && !$isTestRun){
                    if ($keepSource){
                        $fileRenameSuccess=@copy($sourceFile,$targetFile);
                    } else {
                        $fileRenameSuccess=@rename($sourceFile,$targetFile);
                    }
                }
                // create target entry
                if ($fileRenameSuccess){
                    $targetEntry=$this->addLog2entry($targetEntry,'Attachment log',array('File source old'=>$sourceFile,'File source new'=>$targetFile),FALSE);
                    $targetEntry=$this->addLog2entry($targetEntry,'Processing log',array('success'=>(($keepSource)?'Copied':'Moved').' file from EntryId='.$sourceEntry['EntryId'].' to '.$targetEntry['EntryId']),FALSE);
                    if (!$isTestRun){
                        if (!$keepSource){
                            $this->deleteEntries(array('Source'=>$sourceEntry['Source'],'EntryId'=>$sourceEntry['EntryId']),$isSystemCall);
                        }
                        $targetEntry=$this->updateEntry($targetEntry,$isSystemCall,FALSE,FALSE,$targetFile);
                    }            
                } else {
                    // copying or renaming of attached file failed
                    $sourceEntry=$this->addLog2entry($sourceEntry,'Processing log',array('failed'=>'Failed to '.(($keepSource)?'copy':'move').' attached file, kept enrtry'),FALSE);
                    if ($isTestRun){
                        $targetEntry=$sourceEntry;
                    } else {
                        $targetEntry=$this->updateEntry($sourceEntry,$isSystemCall);
                    }
                }
            }
        } else {
            // no write access
            $sourceEntry=$this->addLog2entry($sourceEntry,'Processing log',array('failed'=>'Write access denied'),FALSE);
            if ($isTestRun){
                $targetEntry=$sourceEntry;
            } else {
                $targetEntry=$this->updateEntry($sourceEntry,TRUE);
            }
        }
        return $targetEntry;
    }

    public function swapEntries(array $entryA,array $entryB,bool $isSystemCall=FALSE):bool
    {
        if (empty($entryA['Source']) || empty($entryB['Source']) || empty($entryA['EntryId']) || empty($entryB['EntryId'])){
            throw new \ErrorException('Function '.__FUNCTION__.': required key(s) Source, EntryId missing',0,E_ERROR,__FILE__,__LINE__);
        }
        // swap entry data and update entries
        $targetEntryB=$this->entryById($entryA,$isSystemCall);
        $targetEntryA=$this->entryById($entryB,$isSystemCall);
        if (empty($targetEntryA) || empty($targetEntryB)){return FALSE;}
        $targetEntryA['Source']=$entryA['Source'];
        $targetEntryA['EntryId']=$entryA['EntryId'];
        $targetEntryB['Source']=$entryB['Source'];
        $targetEntryB['EntryId']=$entryB['EntryId'];
        $this->updateEntry($targetEntryA);
        $this->updateEntry($targetEntryB);
        // copy files A, B to temporary files B, A
        $sourceFileA=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entryA);
        $sourceFileB=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entryB);
        if (is_file($sourceFileA)){
            $targetFileB=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getPrivatTmpDir().$entryA['Source'].'_'.$entryA['EntryId'];
            if (copy($sourceFileA,$targetFileB)){
                unlink($sourceFileA);
            } else {
                throw new \ErrorException('Function '.__FUNCTION__.': copy('.$sourceFileA.','.$targetFileB.') failed',0,E_ERROR,__FILE__,__LINE__);
            }
        }
        if (is_file($sourceFileB)){
            $targetFileA=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getPrivatTmpDir().$entryB['Source'].'_'.$entryB['EntryId'];
            if (copy($sourceFileB,$targetFileA)){
                unlink($sourceFileB);
            } else {
                throw new \ErrorException('Function '.__FUNCTION__.': copy('.$sourceFileB.','.$targetFileA.') failed',0,E_ERROR,__FILE__,__LINE__);
            }
        }
        // copy temporary files A, B to files A, B
        if (isset($targetFileA)){copy($targetFileA,$sourceFileA);}
        if (isset($targetFileB)){copy($targetFileB,$sourceFileB);}
        return TRUE;
    }
    
    public function addOrderedListIndexToEntryId(string $primaryKeyValue,int $index):string
    {
        $primaryKeyValue=$this->orderedListComps($primaryKeyValue);
        $primaryKeyValue=array_pop($primaryKeyValue);    
        return str_pad(strval($index),4,'0',STR_PAD_LEFT).'___'.$primaryKeyValue;
    }
    
    public function getOrderedListIndexFromEntryId(string|bool $primaryKeyValue,bool $detectMalFormat=TRUE):int
    {
        if (is_bool($primaryKeyValue)){return 0;}
        $comps=$this->orderedListComps($primaryKeyValue);
        if (count($comps)<2){
            if ($detectMalFormat){
                $this->oc['logger']->log('warning','Invalid EntryId "{EntryId}" supplied to "{function}"',array('function'=>__FUNCTION__,'EntryId'=>$primaryKeyValue));
            }
            return 0;
        }
        $index=array_shift($comps);
        return intval($index);
    }
    
    public function getOrderedListKeyFromEntryId(string $primaryKeyValue):string
    {
        $comps=$this->orderedListComps($primaryKeyValue);
        $key=array_pop($comps);
        return $key;
    }
    
    public function orderedListComps(string $primaryKeyValue):array
    {
        return explode('___',$primaryKeyValue);    
    }
    
    public function orderedListInfo(array $orderedListSelector,bool $isSystemCall=TRUE,bool $isDebugging=FALSE):array
    {
        $loopIndex=$loopEntryIndex=1;
        $currentEntryId=(empty($orderedListSelector['EntryId']))?FALSE:$orderedListSelector['EntryId'];
        $currentEntryIndex=$this->getOrderedListIndexFromEntryId($currentEntryId,FALSE);
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($orderedListSelector,array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE));
        $olInfo=array('firstEntryId'=>FALSE,'lastEntryId'=>FALSE,'newEntryIndex'=>FALSE,'newEntryId'=>FALSE,'baseEntryId'=>FALSE,'currentEntryIndex'=>$currentEntryIndex,'selector'=>$selector,'error'=>array());
        if (empty($orderedListSelector['Source']) && !empty($orderedListSelector['Class'])){
            $olInfo['storageClass']='SourcePot\Datapool\Foundation\Filespace';
        } else {
            $olInfo['storageClass']=__CLASS__;
        }
        foreach($this->entryIterator($selector,$isSystemCall,'Read','EntryId',TRUE) as $entry){
            if ($olInfo['firstEntryId']===FALSE){
                $olInfo['firstEntryId']=$olInfo['lastEntryId']=$entry['EntryId'];
                $olInfo['baseEntryId']=$this->getOrderedListKeyFromEntryId($olInfo['firstEntryId']);
            }
            if (mb_strpos($entry['EntryId'],$olInfo['baseEntryId'])===FALSE){
                $this->oc['logger']->log('notice','"Deleted invalid ordered list entry: Source="{Source}", EntryId="{EntryId}"',array('Source'=>$entry['Source'],'EntryId'=>$entry['EntryId']));
                $olInfo['error'][]='Invalid ordered list entry '.$entry['EntryId'];
                $this->deleteEntries(array('Source'=>$entry['Source'],'EntryId'=>$entry['EntryId']),TRUE);
            } else {
                $olInfo['lastEntryId']=$entry['EntryId'];
                $loopEntryIndex=$this->getOrderedListIndexFromEntryId($entry['EntryId'],FALSE);
                if ($loopEntryIndex!==$loopIndex && $loopEntryIndex>0){
                    $olInfo['error'][]='Index mismatch at '.$entry['EntryId'].', index should be '.$loopIndex;
                }
                $loopIndex++;
            }
        }
        if ($olInfo['firstEntryId']===FALSE){
            $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($selector,$relevantKeys=array('Source','Group','Folder','Name'),'0','',TRUE);
            $olInfo['baseEntryId']=$selector['EntryId'];
            $olInfo['firstEntryId']=$olInfo['lastEntryId']=$this->addOrderedListIndexToEntryId($olInfo['baseEntryId'],0);
        }
        $olInfo['lastEntryIndex']=$olInfo['moveUpIndex']=$this->getOrderedListIndexFromEntryId($olInfo['lastEntryId'],FALSE);
        $olInfo['newEntryIndex']=$olInfo['lastEntryIndex']+1;
        $olInfo['newEntryId']=$this->addOrderedListIndexToEntryId($olInfo['baseEntryId'],$olInfo['newEntryIndex']);
        if ($currentEntryIndex>1){
            $olInfo['moveDownIndex']=$currentEntryIndex-1;
            $olInfo['hasMoveDownBtn']=TRUE;
        } else {
            $olInfo['moveDownIndex']=$currentEntryIndex;
            $olInfo['hasMoveDownBtn']=FALSE;
        }
        if ($currentEntryIndex<$olInfo['lastEntryIndex']){
            $olInfo['moveUpIndex']=$currentEntryIndex+1;
            $olInfo['hasMoveUpBtn']=TRUE;
        } else {
            $olInfo['moveUpIndex']=$currentEntryIndex;
            $olInfo['hasMoveUpBtn']=FALSE;
        }
        if ($isDebugging || !empty($olInfo['error'])){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file(array('orderedListSelector'=>$orderedListSelector,'infoArr'=>$olInfo));
        }
        return $olInfo;
    }
    
    public function deleteOrderedListEntry(array $selector,bool $isSystemCall=TRUE)
    {
        $toDeleteEntry=$this->entryById($selector,TRUE);
        if (empty($toDeleteEntry)){return FALSE;}
        $entryDetected=FALSE;
        $lastEntrySelector=array('Source'=>$selector['Source'],'EntryId'=>'');
        $olSelector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2selector($toDeleteEntry,array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE));
        foreach($this->entryIterator($olSelector,$isSystemCall,'Read','EntryId',TRUE) as $entry){
            if ($entryDetected){
                $this->swapEntries($lastEntrySelector,$entry,$isSystemCall);
            }
            $lastEntrySelector['EntryId']=$entry['EntryId'];
            if (strcmp($entry['EntryId'],$toDeleteEntry['EntryId'])===0){$entryDetected=TRUE;}
        }
        if ($entryDetected){
            $this->deleteEntries(array('Source'=>$selector['Source'],'EntryId'=>$lastEntrySelector['EntryId']),$isSystemCall);
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    public function removeFileFromEntry(array $entry,bool $isSystemCall=FALSE):bool
    {
        if (!empty($entry['EntryId']) && $this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE,$isSystemCall)){
            $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
            $removed=$this->oc['SourcePot\Datapool\Foundation\Database']->removeFile($file);
            if (isset($entry['Params']['File']) && $removed){
                unset($entry['Params']['File']);
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
                return TRUE;
            }
        }
        return FALSE;
    }
    
    public function moveEntry(array $selector,bool $moveUp=TRUE,bool $isSystemCall=FALSE):string|bool
    {
        $sourceEntry=$this->entryById($selector,TRUE);
        $olInfo=$this->orderedListInfo($sourceEntry,TRUE,FALSE);
        if (empty($olInfo['selector'])){return FALSE;}
        $sourceSelector=$targetSelector=$olInfo['selector'];
        $sourceSelector['EntryId']=$this->addOrderedListIndexToEntryId($olInfo['baseEntryId'],$olInfo['currentEntryIndex']);
        if ($moveUp){
            $targetSelector['EntryId']=$this->addOrderedListIndexToEntryId($olInfo['baseEntryId'],$olInfo['moveUpIndex']);
        } else {
            $targetSelector['EntryId']=$this->addOrderedListIndexToEntryId($olInfo['baseEntryId'],$olInfo['moveDownIndex']);
        }
        $this->swapEntries($sourceSelector,$targetSelector,$isSystemCall);
        return $targetSelector['EntryId'];
    }

    public function addLog2entry(array $entry,string $logType='Processing log',array $logContent=array(),bool $updateEntry=FALSE)
    {
        if (empty($_SESSION['currentUser']['EntryId'])){$userId='ANONYM';} else {$userId=$_SESSION['currentUser']['EntryId'];}
        if (!isset($entry['Params'][$logType])){$entry['Params'][$logType]=array();}
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,5);    
        $logContent['timestamp']=time();
        $logContent['time']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','');
        $logContent['timezone']=date_default_timezone_get();
        $logContent['method_0']=$trace[1]['class'].'::'.$trace[1]['function'];
        $logContent['method_1']=$trace[2]['class'].'::'.$trace[2]['function'];
        $logContent['method_2']=$trace[3]['class'].'::'.$trace[3]['function'];
        $logContent['userId']=(empty($_SESSION['currentUser']['EntryId']))?'ANONYM':$_SESSION['currentUser']['EntryId'];
        $entry['Params'][$logType][]=$logContent;
        // remove expired logs
        foreach($entry['Params'][$logType] as $logIndex=>$logArr){
            if (!isset($logArr['Expires'])){continue;}
            $expires=strtotime($logArr['Expires']);
            if ($expires<time()){unset($entry['Params'][$logType][$logIndex]);}
        }
        if ($updateEntry){
            $entry=$this->updateEntry($entry,TRUE);
        }
        return $entry;
    }
    
    /**
    * The method moves or copies an entry to the target selected by argument targetSelector
    *
    * @param array $selectorA Is the first selector.  
    * @param array $selectorB Is the second selector.  
    *
    * @return boolean TRUE if selectors are equal, FALSE if they are not equal.
    */
    public function isSameSelector(array $selectorA,array $selectorB):bool
    {
        $relevantKeys=array('Source','Group','Folder','Name','EntryId');
        foreach($relevantKeys as $column){
            if (empty($selectorA[$column])){$selectorA[$column]='';}
            if (empty($selectorB[$column])){$selectorB[$column]='';}
            if ($selectorA[$column]!=$selectorB[$column]){return FALSE;}
        }
        return TRUE;
    }

}
?>