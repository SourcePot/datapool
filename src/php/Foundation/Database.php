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
    
    private $dbObj;
    
    public const TIME_BETWEEN_OPTIMIZE_TABLES=36000;               // Make sure this value is larger than the minimum time between Database jobs!!
    public const TABLE_UNLOCK_REQUIRED=['persistency'=>TRUE];
    public const CHARACTER_SET='utf8';
    public const MULTIBYTE_COUNT='4';
    public const MAX_IDLIST_COUNT=2000;
    
    private $rootEntryTemplate=['EntryId'=>['type'=>'VARCHAR(255)','value'=>'{{EntryId}}','Description'=>'This is the unique entry key, e.g. EntryId, User hash, etc.','Write'=>0],
                                 'Group'=>['type'=>'VARCHAR(255)','value'=>'...','Description'=>'First level ordering criterion'],
                                 'Folder'=>['type'=>'VARCHAR(255)','value'=>'...','Description'=>'Second level ordering criterion'],
                                 'Name'=>['type'=>'VARCHAR(1024)','value'=>'New','Description'=>'Third level ordering criterion'],
                                 'Type'=>['type'=>'VARCHAR(240)','value'=>'000000|en|000|{{Source}}','Description'=>'This is the data-type of Content'],
                                 'Date'=>['type'=>'DATETIME','value'=>'{{nowDateUTC}}','Description'=>'This is the entry date and time'],
                                 'Content'=>['type'=>'MEDIUMBLOB','value'=>[],'Description'=>'This is the entry Content data'],
                                 'Params'=>['type'=>'MEDIUMBLOB','value'=>[],'Description'=>'This are the entry Params, e.g. file information of any file attached to the entry, size, name, MIME-type etc.'],
                                 'Expires'=>['type'=>'DATETIME','value'=>\SourcePot\Datapool\Root::NULL_DATE,'Description'=>'If the current date is later than the Expires-date the entry will be deleted. On insert-entry the init-value is used only if the Owner is not anonymous, set to 10mins otherwise.'],
                                 'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
                                 'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'],
                                 'Owner'=>['type'=>'VARCHAR(100)','value'=>'{{Owner}}','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.']
                                ];
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $this->resetStatistic();
        // initialize database and get connection
        $this->dbObj=$this->connect();
        $this->collectDatabaseInfo();
        // set default entry access rights
        $accessOptions=$oc['SourcePot\Datapool\Foundation\Access']->getAccessOptions();
        $this->rootEntryTemplate['Read']['value']=$accessOptions['ALL_CONTENTADMIN_R'];
        $this->rootEntryTemplate['Write']['value']=$accessOptions['ALL_CONTENTADMIN_R'];
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
    }
    
    public function job(array $vars):array
    {
        $lastRun=$vars['Last run']??0;
        // select table
        if (empty($vars['tables2process'])){
            $keysArr=array_keys($GLOBALS['dbInfo']);
            $vars['tables2process']=array_combine($keysArr,$keysArr);
        }
        $selectedKey=key($vars['tables2process']);
        $selectedTable=$vars['tables2process'][$selectedKey];
        $vars['tables2process'][$selectedKey]='__TODELETE__';
        if (time()-$lastRun>self::TIME_BETWEEN_OPTIMIZE_TABLES){
            // optimize table
            $sql='OPTIMIZE TABLE `'.$selectedTable.'`;';
            $stmt=$this->executeStatement($sql,[]);
            $vars['OPTIMIZE TABLE'][$selectedTable]=$stmt->fetchAll(\PDO::FETCH_ASSOC);
            $vars['action']='Check and repair table "'.$selectedTable.'"';
        } else {
            // delete expitred entries
            $selector=['Source'=>$selectedTable,'Expires<'=>date('Y-m-d H:i:s'),'unlock'=>TRUE];
            $statistic=$this->deleteEntries($selector,TRUE);
            // update delefted signal
            $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,__FUNCTION__,'Deleted expired entries',$statistic['deleted'],'int');
            $vars['action']='Deleted expired entries of table "'.$selectedTable.'"';
        }
        // add inofs to html
        $vars['html']='<h3>'.$vars['action'].'</h3>';
        $vars['Last run']=time();
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

    public function enrichToReplace(array $toReplace=[]):array
    {
        $toReplace['{{Expires}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','PT10M');
        $toReplace['{{EntryId}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getEntryId();
        $toReplace['{{Owner}}']=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId();
        return $toReplace;
    }

    public function resetStatistic():array
    {
        $_SESSION[__CLASS__]['Statistic']=['matches'=>0,'updated'=>0,'inserted'=>0,'deleted'=>0,'removed'=>0,'black hole'=>0,'failed'=>0,'skipped'=>0];
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
        $matrix=[];
        if (isset($_SESSION[__CLASS__]['Statistic'])){
            foreach($_SESSION[__CLASS__]['Statistic'] as $key=>$value){
                $matrix[$key]=['Value'=>$value];
            }
        }
        return $matrix;
    }
    
    /**
    * @return string|FALSE The method returns the database name or FALSE if connection to the database failed.
    */
    public function getDbName():string|bool
    {
        if ($this->dbObj){
            $stmt=$this->executeStatement('SELECT DATABASE()');
            $row=$stmt->fetch(\PDO::FETCH_ASSOC);
            return current($row);
        } else {
            return FALSE;
        }
    }
    
    /**
    * @return array|FALSE The method returns entry template for all columns of the provided database table or all columns of all tables if no table is provided or FALSE if the table does not exist.
    */
    public function getEntryTemplate(string|bool $table=FALSE):array|bool
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'table'=>$table];
        if ($table){
            if (isset($GLOBALS['dbInfo'][$table])){return $GLOBALS['dbInfo'][$table];}
        } else {
            return $GLOBALS['dbInfo'];
        }
        if (isset($this->oc['logger'])){
            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" called with table="{table}" returned false, table missing.',$context);
        }
        return FALSE;
    }
    
    /**
    * This function returns the entry template based on the root entry template.
    * It creates all tables which do not exist.
    *
    * @return array The method returns the entry template array for the table.
    */
    public function getEntryTemplateCreateTable(string $table,string $callingClass=''):array
    {
        // If template is registered already, return this template
        if (isset($GLOBALS['dbInfo'][$table]['EntryId']['baseClass'])){
            return $GLOBALS['dbInfo'][$table];
        }
        // Get template
        $entryTemplate=$this->rootEntryTemplate;
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
            $sql="CREATE TABLE `".$table."` (".$columnsDefSql.") DEFAULT CHARSET=".self::CHARACTER_SET." COLLATE ".self::CHARACTER_SET."_unicode_ci;";
            $this->executeStatement($sql,[]);
            if (isset($this->oc['logger'])){
                $this->oc['logger']->log('notice','Created missing database table "{table}"',['table'=>$table,'function'=>__FUNCTION__,'class'=>__CLASS__]);
            }
            // set standard indices
            $this->setTableIndices($table);
        }
        return $GLOBALS['dbInfo'][$table]=$entryTemplate;
    }
    
    public function getTableIndices(string $table):array
    {
        $indices=[];
        $sql="SHOW INDEXES FROM `".$table."`;";
        $stmt=$this->executeStatement($sql,[]);
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
        $context=['table'=>$table,'class'=>__CLASS__,'function'=>__FUNCTION__,'dropped'=>''];
        // drop all existing indices
        $sql="";
        $indices=$this->getTableIndices($table);
        foreach($indices as $keyName=>$indexArr){
            $context['dropped']=$keyName.' | ';
            $sql.="ALTER TABLE `".$table."` DROP INDEX `".$keyName."`;"; 
        }
        if (!empty($sql)){$this->executeStatement($sql,[]);}
        $context['dropped']=trim($context['dropped'],'| ');
        $this->oc['logger']->log('notice','Existing indices "{dropped}" of database table "{table}" dropped',$context);
        // set new indices
        $sql="";
        $sql.="ALTER TABLE `".$table."` ADD PRIMARY KEY (`EntryId`);";
        $sql.="ALTER TABLE `".$table."` ADD INDEX STD (`EntryId`(40),`Group`(30),`Folder`(30),`Name`(40));";
        $this->oc['logger']->log('notice','Added index to database table "{table}"',$context);
        return $this->executeStatement($sql,[]);
    }

    /**
    * This function selects the entry-specific unifyEntry() function based on $entry['Source']
    * If the $entry-specific unifyEntry() function is found it will be used to unify the entry.
    */
    public function unifyEntry(array $entry,bool $addDefaults=FALSE):array
    {
        if (empty($entry['Source'])){
            throw new \ErrorException('Method '.__FUNCTION__.' called with empty entry Source.',0,E_ERROR,__FILE__,__LINE__);    
        }
        $entry=$this->addType2entry($entry);
        $classWithNamespace=$this->oc['SourcePot\Datapool\Root']->source2class($entry['Source']);
        if (isset($this->oc[$classWithNamespace])){
            if (method_exists($this->oc[$classWithNamespace],'unifyEntry')){
                $entry=$this->oc[$classWithNamespace]->unifyEntry($entry,$addDefaults);
            }
        }
        if ($addDefaults){
            $entry=$this->addEntryDefaults($entry);
        }
        $entry=$this->oc['SourcePot\Datapool\Tools\FileContent']->enrichEntry($entry);
        $entry=$this->oc['SourcePot\Datapool\Root']->substituteWithPlaceholder($entry);
        return $entry;    
    }

    public function addEntryDefaults(array $entry):array
    {
        $entryTemplate=(isset($GLOBALS['dbInfo'][$entry['Source']]))?$GLOBALS['dbInfo'][$entry['Source']]:$this->rootEntryTemplate;
        foreach($entryTemplate as $column=>$defArr){
            if (!isset($defArr['value'])){continue;}
            if (!isset($entry[$column])){
                $entry[$column]=$defArr['value'];
            } else if ($entry[$column]===FALSE){
                $entry[$column]=$defArr['value'];
            }
        }
        return $entry;
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
            $typeArr=[];
        }
        // is guide entry?
        if (mb_strpos(strval($entry['EntryId']??''),\SourcePot\Datapool\Root::GUIDEINDICATOR)!==FALSE){
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
    public function connect(string $class=__CLASS__, string $entryId='connect', bool $exitOnException=TRUE):object|bool
    {
        $dbObj=FALSE;
        $namespaceComps=explode('\\',__NAMESPACE__);
        $dbName=mb_strtolower($namespaceComps[0]);
        $access=['Class'=>$class,'EntryId'=>$entryId,'Read'=>65535];
        $access['Content']=['dbServer'=>'localhost','dbName'=>$dbName,'dbUser'=>'webpage','dbUserPsw'=>session_id()];
        $access=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($access,TRUE);
        try{
            $dbObj=new \PDO('mysql:host='.$access['Content']['dbServer'].';dbname='.$access['Content']['dbName'],$access['Content']['dbUser'],$access['Content']['dbUserPsw']);
            $dbObj->exec("SET CHARACTER SET '".self::CHARACTER_SET."'");
            $dbObj->exec("SET NAMES ".self::CHARACTER_SET.'mb'.self::MULTIBYTE_COUNT);
        } catch (\Exception $e){
            if ($exitOnException){
                $entryFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($access);
                $msg=$e->getMessage();
                echo $this->oc['SourcePot\Datapool\Root']->getBackupPageContent('<i>The problem is: '.$msg.'</i><br/>Please check the credentials '.$entryFile);
                exit(0);
            } else {
                throw new \Exception($e->getMessage());
            }
        }
        return $dbObj;
    }
    
    /**
    * The method collects all relevant data of the database tables.
    *
    * @return array Is content of the global variable 'dbInfo'
    */
    private function collectDatabaseInfo():array
    {
        $GLOBALS['dbInfo']=[];
        $sql='SHOW TABLES;';
        $stmt=$this->executeStatement($sql);
        $tables=$stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach($tables as $table){
            $table=current($table);
            $GLOBALS['dbInfo'][$table]=$this->rootEntryTemplate;
        }
        return $GLOBALS['dbInfo'];
    }

    public function executeStatement(string $sql,array $inputs=[],object|bool $dbObj=FALSE):object
    {
        if ($dbObj===FALSE){$dbObj=$this->dbObj;}
        $stmtArr=$this->bindValues($sql,$inputs,$dbObj);
        $this->oc['SourcePot\Datapool\Root']->startStopWatch(__CLASS__,__FUNCTION__,$stmtArr['sqlSimulated']);
        try{
            $stmtArr['stmt']->execute();
        } catch (\Exception $e){
            $context=$stmtArr;
            $context['error']=$e->getMessage();
            $this->oc['logger']->log('critical','SQL execution for "{sqlSimulated}" triggered error: "{error}".',$context);
        }
        $this->oc['SourcePot\Datapool\Root']->stopStopWatch(__CLASS__,__FUNCTION__,$stmtArr['sqlSimulated']);
        return $stmtArr['stmt'];
    }
    
    private function bindValues(string $sql,array $inputs=[],object $dbObj):array
    {
        $return=['sql'=>$sql,'input'=>$inputs,'stmt'=>$dbObj->prepare($sql),'sqlSimulated'=>$sql];
        foreach($inputs as $bindKey=>$bindValue){
            $simulatedValue="'".$bindValue."'";
            $return['sqlSimulated']=str_replace($bindKey,$simulatedValue,$return['sqlSimulated']);
            $return['stmt']->bindValue($bindKey,$bindValue);
        }
        return $return;
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
        if ($removeGuideEntries){
            $selector['Type=!']=\SourcePot\Datapool\Root::GUIDEINDICATOR.'%';
        }
        $entryTemplate=$GLOBALS['dbInfo'][$selector['Source']];
        $opAlias=['<'=>'LT','<='=>'LE','=<'=>'LE','>'=>'GT','>='=>'GE','=>'=>'GE','='=>'EQ','!'=>'NOT','!='=>'NOT','=!'=>'NOT'];
        $sqlArr=['sql'=>[],'inputs'=>[]];            
        foreach($selector as $column=>$value){
            if ($value===FALSE || $value==\SourcePot\Datapool\Root::GUIDEINDICATOR){continue;}
            preg_match('/([^<>=!]+)([<>=!]+)/',$column,$match);
            $operator=$match[2]??'=';
            $placeholder=':'.md5($column.$opAlias[$operator[0]]);
            $columns=explode(\SourcePot\Datapool\Root::ONEDIMSEPARATOR,$column);
            $column=trim($columns[0],' <>=!');
            if (!isset($entryTemplate[$column])){continue;}
            if (is_array($value)){$value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($value);}
            if ((strpos($entryTemplate[$column]['type'],'VARCHAR')!==FALSE || strpos($entryTemplate[$column]['type'],'BLOB')!==FALSE) || $this->containsStringWildCards(strval($value))){
                $column='`'.$column.'`';
                if (empty($value)){
                    if ($operator[0]==='<' || $operator[0]==='>' || $operator[0]==='!'){$value='';} else {continue;}
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
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file(['selector'=>$selector,'sqlArr'=>$sqlArr,'entryTemplate'=>$entryTemplate]);
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
        if (strpos($sqlArr['sql'],'WHERE')===FALSE){$sqlArr['sql']=' WHERE '.$sqlArr['sql'];}
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
            $result[$column]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr((string)$value);
            if (isset($result[$column]['!serialized!']) && empty($result['unlock'])){
                $result[$column]=unserialize($result[$column]['!serialized!']);
            }
        } else if (strpos($entryTemplate[$column]['type'],'INT')!==FALSE){
            $result[$column]=intval($value);
        } else if (strpos($entryTemplate[$column]['type'],'FLOAT')!==FALSE || strpos($entryTemplate[$column]['type'],'DOUBLE')!==FALSE){
            $result[$column]=floatval($value);
        } else {
            $result[$column]=$value;
        }
        return $result;    
    }

    private function standardSelectQuery($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$removeGuideEntries=TRUE){
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        $sqlArr=$this->selector2sql($selector,$removeGuideEntries);
        $sqlArr=$this->addRights2sql($sqlArr,$user,$isSystemCall,$rightType);
        $sqlArr=$this->addSuffix2sql($sqlArr,$GLOBALS['dbInfo'][$selector['Source']],$orderBy,$isAsc,$limit,$offset);
        return $sqlArr;
    }
    
    public function getRowCount($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$removeGuideEntries=TRUE){
        if (empty($selector['Source']) || !isset($GLOBALS['dbInfo'][$selector['Source']])){return 0;}
        $sqlArr=$this->standardSelectQuery($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$removeGuideEntries);
        $sqlArr['sql']='SELECT COUNT(*) FROM (SELECT `EntryId` FROM `'.$selector['Source'].'`'.$sqlArr['sql'].') AS a;';
        $stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs']);
        $rowCount=current($stmt->fetch());
        return intval($rowCount);
    }
    
    public function entriesByRight($column='Read',$right='ADMIN_R',$returnPrimaryKeyOnly=TRUE){
        $selector=['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable()];
        if ($returnPrimaryKeyOnly){$return='EntryId';} else {$return='*';}
        $rights=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights([],$right,$right);
        $right=intval($rights['Read']);
        $sql="SELECT ".$return." FROM `".$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable()."` WHERE ((`".$column."` & ".$right.")>0);";
        $stmt=$this->executeStatement($sql);
        $entries=[];
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
        $result=['isFirst'=>TRUE,'rowIndex'=>0,'rowCount'=>0,'hash'=>''];
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
            $sqlArr['sql']='SELECT DISTINCT '.$selector['Source'].'.'.$column.' FROM `'.$selector['Source'].'`'.$sqlArr['sql'].';';
            //var_dump($sqlArr);
            $stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
            $result=['isFirst'=>TRUE,'rowIndex'=>0,'rowCount'=>$stmt->rowCount(),'Source'=>$selector['Source'],'hash'=>'','unlock'=>$selector['unlock']??FALSE];
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
    
    public function entryIterator(array $selector,bool $isSystemCall=FALSE,string $rightType='Read',string|bool $orderBy=FALSE,bool $isAsc=TRUE,int|bool|string $limit=FALSE,int|bool|string $offset=FALSE,array $selectExprArr=[],bool $removeGuideEntries=TRUE):\Generator
    {
        if (empty($selector['Source']) || !isset($GLOBALS['dbInfo'][$selector['Source']])){return [];}
        $sqlArr=$this->standardSelectQuery($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$removeGuideEntries);
        if (empty($selectExprArr)){
            $selectExprSQL=$selector['Source'].'.*';
        } else {
            if (!in_array('EntryId',$selectExprArr)){$selectExprArr[]='EntryId';}
            $selectExprSQL=$selector['Source'].'.'.implode(','.$selector['Source'].'.',$selectExprArr);
        }
        $sqlArr['sql']='SELECT '.$selectExprSQL.' FROM `'.$selector['Source'].'`'.$sqlArr['sql'];
        $sqlArr['sql'].=';';
        $stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs']);
        $result=['isFirst'=>TRUE,'isLast'=>TRUE,'rowIndex'=>-1,'rowCount'=>$stmt->rowCount(),'now'=>time(),'Source'=>$selector['Source'],'hash'=>'','unlock'=>$selector['unlock']??FALSE];
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
        $result=[];
        if (empty($selector['Source'])){
            return $result;
        } else if (strcmp($selector['Source'],'!GUIDE')===0){
            return $result;
        }
        // get entry
        $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();
        if (!empty($selector['EntryId'])){
            $sqlPlaceholder=':'.'EntryId';
            $sqlArr=['sql'=>"SELECT * FROM `".$selector['Source']."` WHERE `".'EntryId'."`=".$sqlPlaceholder,'inputs'=>[$sqlPlaceholder=>$selector['EntryId']]];
            $sqlArr=$this->addRights2sql($sqlArr,$user,$isSystemCall,$rightType);
            $sqlArr['sql'].=';';
            //var_dump($sqlArr);
            $stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs']);
            $result=['isFirst'=>TRUE,'rowIndex'=>0,'rowCount'=>$stmt->rowCount(),'primaryKey'=>'EntryId','primaryValue'=>$selector['EntryId'],'Source'=>$selector['Source'],'unlock'=>$selector['unlock']??FALSE];
            $this->addStatistic('matches',$result['rowCount']);
            $row=$stmt->fetch(\PDO::FETCH_ASSOC);
            if (is_array($row)){
                foreach($row as $column=>$value){
                    $result=$this->addColumnValue2result($result,$column,$value,$GLOBALS['dbInfo'][$selector['Source']]);
                }
                $result=$this->oc['SourcePot\Datapool\Tools\FileContent']->enrichEntry($result);
                $result=$this->addSelector2result($selector,$result);
            } else {
                if (!$returnMetaOnNoMatch){$result=[];}
            }
        }
        return $result;
    }
    
    /**
    * The method creates an array containing an EntryId-list as SQL-suffix of the entries selected by the method parameters.
    *
    * @param array $selector Is the selector  
    * @param bool $isSystemCall If true read and write access is granted  
    * @param string $rightType Sets the relevant right-type for the creation of the EntryId-list
    * @param string|bool $orderBy Selects the column the EntryId-list will be ordered by
    * @param bool $isAsc Selects order direction for the EntryId-list
    * @param int|bool|string $limit Limits the size of the Entry-list
    * @param int|bool|string $offset Set the start of the Entry-list
    * @param array $selectExprArr Sets the select column of the database table (is irrelevant in this context)
    * @param bool $removeGuideEntries If true Guide-entries will be removed from the Entry-list
    *
    * @return array Array containing the EntryId-list as SQL-suiffix
    */
    private function selector2idGroups(array $selector,bool $isSystemCall=FALSE,string $rightType='Read',string|bool $orderBy=FALSE,bool $isAsc=TRUE,int|bool|string $limit=FALSE,int|bool|string $offset=FALSE,bool $removeFile=TRUE):array
    {
        $groupIdIndex=0;
        $entryIdGroups=[];
        foreach($this->entryIterator($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,['EntryId'],FALSE,FALSE) as $row){
            // build entryId list
            $groupIdIndex=($groupIdIndex>self::MAX_IDLIST_COUNT)?($groupIdIndex++):$groupIdIndex;
            $entryIdGroups[$groupIdIndex][]="'".$row['EntryId']."'";
            // remove attached file
            if (!$removeFile){continue;}
            $entrySelector=['Source'=>$selector['Source'],'EntryId'=>$row['EntryId']];
            $fileToDelete=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entrySelector);
            $this->removeFile($fileToDelete);
        }
        return $entryIdGroups;
    }

    private function ids2IdListSelector(array $ids):string
    {
        return 'WHERE `'.'EntryId'.'` IN('.implode(',',$ids).')';
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
        // check for lock
        if (!empty(self::TABLE_UNLOCK_REQUIRED[$selector['Source']]) && empty($selector['unlock'])){
            $this->oc['logger']->log('notice','Tried to delete table entry of locked table "{Source}" without setting entry[unlock]=TRUE',$selector);
            return $this->addStatistic('deleted',0);
        }
        // delete entries in groups
        $rowCount=0;
        $idGroups=$this->selector2idGroups($selector,$isSystemCall,'Write',FALSE,FALSE,FALSE,FALSE,$removeFile=TRUE);
        foreach($idGroups as $ids){
            // delete entries by id-list
            $sqlWhereClause=$this->ids2IdListSelector($ids);
            $sql='DELETE FROM `'.$selector['Source'].'` '.$sqlWhereClause.';';
            $stmt=$this->executeStatement($sql,[]);
            $rowCount+=$stmt->rowCount();
        }
        return $this->addStatistic('deleted',$rowCount);
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
    public function insertEntry(array $entry,bool $addDefaults=TRUE):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        // check for lock
        if (!empty(self::TABLE_UNLOCK_REQUIRED[$entry['Source']]) && empty($entry['unlock'])){
            $this->oc['logger']->log('notice','Tried to insert table entry of locked table "{Source}" without setting entry[unlock]=TRUE',$entry);
            return [];
        }
        // complete entry
        $entryTemplate=$this->getEntryTemplate($entry['Source']);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($entry,$addDefaults);
        if (!empty($entry['Owner'])){
            if (strpos($entry['Owner'],'ANONYM_')!==FALSE){
                $entry['Expires']=date('Y-m-d H:i:s',time()+600);
            }
        }
        $columns='';
        $values='';
        $inputs=[];
        foreach ($entry as $column => $value){
            if (!isset($entryTemplate[$column])){continue;}
            if (strcmp($column,'Source')===0){continue;}
            $sqlPlaceholder=':'.$column;
            $columns.='`'.$column.'`,';
            $values.=$sqlPlaceholder.',';
            if (is_array($value)){
                $value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($value);
            }
            $inputs[$sqlPlaceholder]=strval($value);
        }
        $sql="INSERT INTO `".$entry['Source']."` (".trim($columns,',').") VALUES (".trim($values,',').") ON DUPLICATE KEY UPDATE `EntryId`='".$entry['EntryId']."';";
        $stmt=$this->executeStatement($sql,$inputs);
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
    * @param bool $isSystemCall If true read and write access is granted  
    * @param string $rightType Sets the relevant right-type for the creation of the EntryId-list
    * @param string|bool $orderBy Selects the column, the EntryId-list will be ordered by
    * @param bool $isAsc Selects order direction for the EntryId-list
    * @param int|bool $limit Limits the size of the Entry-list
    * @param int|bool $offset Set the start of the Entry-list
    * @param array $selectExprArr Sets the select column of the database table (is irrelevant in this context)
    * @param bool $removeGuideEntries If true Guide-entries will be removed from the Entry-list
    *
    * @return int|boolean The updated entry count or false on failure
    */
    public function updateEntries($selector,$entry,$isSystemCall=FALSE,string $rightType='Write',$orderBy=FALSE,$isAsc=FALSE,$limit=FALSE,$offset=FALSE,$selectExprArr=[],$removeGuideEntries=FALSE,$isDebugging=FALSE):int
    {
        // only the Admin has the right to change data in the Privileges column
        if (!empty($entry['Privileges']) && !$this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin() && !$isSystemCall){
            unset($entry['Privileges']);
        }
        if (empty($entry)){return 0;}
        // check tables with locks
        if (!empty(self::TABLE_UNLOCK_REQUIRED[$selector['Source']]) && empty($entry['unlock'])){
            $this->oc['logger']->log('notice','Tried to update table entry of locked table "{Source}" without setting entry[unlock]=TRUE',$selector);
            return 0;
        }
        // get entry list
        $idGroups=$this->selector2idGroups($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$removeFile=FALSE);
        if (empty($idGroups)){return 0;}
        // prepare update sql string, set values and add sql where clause
        $entryTemplate=$this->getEntryTemplate($selector['Source']);
        $inputs=[];
        $valueSql='';
        foreach($entry as $column=>$value){
            if (!isset($entryTemplate[$column])){continue;}
            if ($value===FALSE){continue;}
            if (strcmp($column,'Source')===0){continue;}
            $sqlPlaceholder=':'.$column;
            $valueSql.="`".$column."`=".$sqlPlaceholder.",";
            if (is_array($value)){
                $value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($value);
            }
            $inputs[$sqlPlaceholder]=strval($value);
        }
        foreach($idGroups as $ids){
            $sqlWhereClause=$this->ids2IdListSelector($ids);
            $sql="UPDATE `".$selector['Source']."` SET ".trim($valueSql,',')." ".$sqlWhereClause.";";
            $stmt=$this->executeStatement($sql,$inputs);
            $this->addStatistic('updated',$stmt->rowCount());    
        }
        return $this->getStatistic('updated');
    }
    
    /**
    * The method updates an existing entry (based on the columns provided) OR inserts an entry that does not exist. .
    * Default values are added if any entry property is missing.
    *
    * @param array $entry Is entry array, entry['Source'] and entry['EntryId'] must not be empty  
    * @param boolean $isSystemCall The value is provided to access control to establish read/write access within the method 
    * @param boolean $noUpdateButCreateIfMissing If true, an existing entry won't be updated
    * @param boolean $addLog If true, an entry update will be documented in the entry processing log
    *
    * @return array|boolean The inserted, updated or created entry, an empty array if access rights were insufficient or false on error.
    */
    public function updateEntry(array $entry,bool $isSystemCall=FALSE,bool $noUpdateButCreateIfMissing=FALSE,bool $addLog=TRUE):array|bool
    {
        // only the Admin has the right to update the Privileges column
        if (!empty($entry['Privileges']) && !$this->oc['SourcePot\Datapool\Foundation\Access']->isAdmin() && !$isSystemCall){
            unset($entry['Privileges']);
        }
        // test for required keys and set selector
        if (empty($entry['Source']) || empty($entry['EntryId'])){return FALSE;}
        $selector=['Source'=>$entry['Source'],'EntryId'=>$entry['EntryId']];
        // replace right constants
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Read');
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Write');
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Privileges');
        // get existing entry
        $existingEntry=$this->entryById($selector,TRUE,'Write',TRUE);
        if (empty($existingEntry['rowCount'])){
            // no existing entry found -> insert and return entry
            $currentFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
            if (!is_file($currentFile)){
                // no valid file attachment. clear related meta data
                if (isset($entry['Params']['File'])){$entry['Params']['File']=NULL;}
            }
            // add log
            if ($addLog){
                $currentUserId=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId();
                $dateTimeArr=$this->oc['SourcePot\Datapool\GenericApps\Calendar']->timestamp2date(time(),'UTC');
                $entry['Params']['Log'][__FUNCTION__]['insert']=['user'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($currentUserId,1),'userEmail'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($currentUserId,7),'userId'=>$currentUserId,'timestamp'=>$dateTimeArr['Timestamp'],'System'=>$dateTimeArr['System'],'RFC2822'=>$dateTimeArr['RFC2822']];
            }
            // insert new entry
            $entry=$this->insertEntry($entry,TRUE);
        } else if (empty($noUpdateButCreateIfMissing) && $this->oc['SourcePot\Datapool\Foundation\Access']->access($existingEntry,'Write',FALSE,$isSystemCall)){
            // existing entry -> update
            $isSystemCall=TRUE; // if there is write access to an entry, missing read access must not interfere
            $entry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->mergeArr($existingEntry,$entry);
            // add log
            if ($addLog){
                $currentUserId=$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId();
                $dateTimeArr=$this->oc['SourcePot\Datapool\GenericApps\Calendar']->timestamp2date(time(),'UTC');
                $entry['Params']['Log'][__FUNCTION__]['update']=['user'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($currentUserId,1),'userEmail'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($currentUserId,7),'userId'=>$currentUserId,'timestamp'=>$dateTimeArr['Timestamp'],'System'=>$dateTimeArr['System'],'RFC2822'=>$dateTimeArr['RFC2822']];
            }
            // update entry
            $entry=$this->unifyEntry($entry,TRUE);
            $this->updateEntries($selector,$entry,$isSystemCall,'Write',FALSE,FALSE,FALSE,FALSE,[],FALSE,$isDebugging=FALSE);
            $entry=$this->entryById($selector,$isSystemCall,'Read');
        } else if (!$this->oc['SourcePot\Datapool\Foundation\Access']->access($existingEntry,'Write',FALSE,$isSystemCall)){
            // existing entry -> no write access -> no update 
            $entry=$existingEntry;
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
    * The method returns the first entry that matches the selector or FALSE, if no match is found.
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
            foreach($this->entryIterator($selector,$isSystemCall,$rightType,FALSE,TRUE,2,FALSE,[],$removeGuideEntries) as $entry){
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
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'sourceUpdatedFirst'=>FALSE,'sourceTargetEntryIdMatch'=>FALSE,'copyAttachedFile'=>FALSE,'movedAttachedFile'=>FALSE,'attachedFileProcessed'=>FALSE,'noWriteAccess'=>FALSE];
        // test for required keys and set selector
        if (empty($sourceEntry['Source']) || empty($sourceEntry['EntryId']) || empty($targetSelector)){
            $this->oc['logger']->log('error','{class} &rarr; {function} called with empty sourceEntry[Source], sourceEntry[EntryId] or targetEntry. Source entry was not moved.',$context);    
            return [];
        }
        if ($this->oc['SourcePot\Datapool\Foundation\Access']->access($sourceEntry,'Write',FALSE,$isSystemCall)){
            // write access
            if ($updateSourceFirst && !$isTestRun){
                $sourceEntry=$this->updateEntry($sourceEntry,$isSystemCall);
                $context['sourceUpdatedFirst']=TRUE;
            }
            // apply target selector to source entry
            $targetEntry=array_replace_recursive($sourceEntry,$targetSelector);
            $targetEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($targetEntry,['Source','Group','Folder','Name'],'0','',FALSE);
            if (strcmp($sourceEntry['EntryId'],$targetEntry['EntryId'])===0){
                // source and target EntryId identical, attachment does not need to be touched
                if (!$isTestRun){
                    $targetEntry=$this->updateEntry($targetEntry,$isSystemCall);
                    $context['sourceTargetEntryIdMatch']=TRUE;
                }
            } else {
                // move or copy attached file to target
                $context['fileError']=FALSE;
                $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
                $targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($targetEntry);
                if (is_file($sourceFile) && !$isTestRun && !isset($targetEntry['__BLACKHOLE__'])){
                    try{
                        $this->oc['SourcePot\Datapool\Foundation\Filespace']->addStatistic('inserted files',intval(copy($sourceFile,$targetFile)));
                    } catch(\Exception $e){
                        $context['fileError']=$e->getMessage();
                    }
                }
                // update entry and delete source file if source is not kept
                if ($isTestRun){
                    // nothing to do
                } else if ($context['fileError']){
                    $context['Name']=$targetEntry['Name'];
                    $context['sourceFile']=$sourceFile;
                    $context['action']=($keepSource)?'to copy':'to move';
                    $this->oc['logger']->log('notice','Failed {action} file "{sourceFile}" with "{fileError}". The entry "{Name}" was not updated.',$context);     
                } else {
                    if (isset($targetEntry['__BLACKHOLE__'])){
                        // black hole -> target won't be created
                        $this->addStatistic('black hole',1);
                    } else {
                        // create target
                        $targetEntry=$this->updateEntry($targetEntry,$isSystemCall);
                    }
                    if ($keepSource){
                        $context['copyAttachedFile']=TRUE;
                    } else {
                        $context['movedAttachedFile']=TRUE;
                        $this->deleteEntries(['Source'=>$sourceEntry['Source'],'EntryId'=>$sourceEntry['EntryId']],$isSystemCall);
                    }
                }
            }
        } else {
            // no write access
            $targetEntry=$sourceEntry;
            $context['noWriteAccess']=TRUE;
        }
        $targetEntry[__FUNCTION__]=$context;
        return $targetEntry;
    }

    public function removeFileFromEntry(array $entry,bool $isSystemCall=FALSE):bool
    {
        if (!empty($entry['EntryId']) && $this->oc['SourcePot\Datapool\Foundation\Access']->access($entry,'Write',FALSE,$isSystemCall)){
            $file=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
            $removed=$this->oc['SourcePot\Datapool\Foundation\Database']->removeFile($file);
            if (isset($entry['Params']['File']) && $removed){
                $entry['Params']['File']=NULL;
                $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);
                return TRUE;
            }
        }
        return FALSE;
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
                $this->oc['logger']->log('warning','Invalid EntryId "{EntryId}" supplied to "{function}"',['function'=>__FUNCTION__,'EntryId'=>$primaryKeyValue]);
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
    
    public function rebuildOrderedList(array $selector,array $cmd=[]):string
    {
        $targetIndex=0;
        $targetEntryId='';
        $notices=[];
        $cmd=array_merge(['newOlKey'=>'','removeEntryId'=>'SKIP','moveUpEntryId'=>'!!SKIP!!','moveDownEntryId'=>'!!SKIP!!'],$cmd);
        if (!empty($selector['Source'])){
            $storageObj='SourcePot\Datapool\Foundation\Database';
        } else {
            $storageObj='SourcePot\Datapool\Foundation\Filespace';
        }
        // get all items of the list
        $lastEntryId='SKIP';
        $currentIndex=-1;
        $items=[];
        $olKey=$this->getOrderedListKeyFromEntryId($selector['EntryId']);
        $olSelector=['Source'=>$selector['Source'],'EntryId'=>'%'.$olKey];
        foreach($this->entryIterator($olSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
            $targetFile='';
            if (is_file($sourceFile) && !empty($selector['Source'])){
                $fileName=basename($sourceFile);
                $targetFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->getPrivatTmpDir().$fileName;
                if (!$this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($sourceFile,$targetFile)){
                    $notices[]="Problem copy ordered list source file \"$sourceFile\"";
                    $targetFile='';
                }
            }
            // check if item should be removed
            if ($cmd['removeEntryId']===$entry['EntryId']){
                $notices[]='Removed item "'.$this->getOrderedListIndexFromEntryId($entry['EntryId']).'"';
            } else {
                $items[]=['entry'=>$entry,'file'=>$targetFile];
                $currentIndex++;
            }
            // move entry up or down
            if ($cmd['moveUpEntryId']===$lastEntryId || $cmd['moveDownEntryId']===$entry['EntryId']){
                $currentItem=$items[$currentIndex];
                $items[$currentIndex]=$items[$currentIndex-1];
                $items[$currentIndex-1]=$currentItem;
                $targetIndex=($cmd['moveUpEntryId']===$lastEntryId)?($currentIndex):(($cmd['moveDownEntryId']===$entry['EntryId'])?($currentIndex-1):$currentIndex);
            }
            // delete exsisting entry
            $lastEntryId=$entry['EntryId'];
            $this->oc[$storageObj]->deleteEntries($entry);
        }
        // rebuild list
        if (empty($cmd['newOlKey'])){$cmd['newOlKey']=$olKey;}
        $mapping=[];
        foreach($items as $index=>$item){
            $newListIndex=$index+1;
            $mapping[]=$this->getOrderedListIndexFromEntryId($item['entry']['EntryId']).' &rarr; '.$newListIndex;
            $item['entry']['EntryId']=$this->addOrderedListIndexToEntryId($cmd['newOlKey'],$newListIndex);
            if (is_file($item['file'])){
                $sourceFile=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($item['entry']);
                if (empty($this->oc['SourcePot\Datapool\Foundation\Filespace']->tryCopy($sourceFile,$item['file']))){
                    $notices[]='Problem copy ordered list target file "'.$item['file'].'"';
                }
            }
            if ($targetIndex===$index){$targetEntryId=$item['entry']['EntryId'];}
            $this->oc[$storageObj]->insertEntry($item['entry'],TRUE);
        }
        return $targetEntryId;
    }

}
?>