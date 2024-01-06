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
    private $dbName=FALSE;
    
    const DB_TIMEZONE='UTC';
    
    const GUIDEINDICATOR='!GUIDE';
    
    private $entryTable='settings';
    private $entryTemplate=array();

    private $rootEntryTemplate=array('EntryId'=>array('index'=>'PRIMARY','type'=>'VARCHAR(255)','value'=>'{{EntryId}}','Description'=>'This is the unique entry key, e.g. EntryId, User hash, etc.','Write'=>0),
                                 'Group'=>array('index'=>FALSE,'type'=>'VARCHAR(255)','value'=>'...','Description'=>'First level ordering criterion'),
                                 'Folder'=>array('index'=>FALSE,'type'=>'VARCHAR(255)','value'=>'...','Description'=>'Second level ordering criterion'),
                                 'Name'=>array('index'=>'NAME_IND','type'=>'VARCHAR(1024)','value'=>'New','Description'=>'Third level ordering criterion'),
                                 'Type'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'{{Source}}','Description'=>'This is the data-type of Content'),
                                 'Date'=>array('index'=>FALSE,'type'=>'DATETIME','value'=>'{{NOW}}','Description'=>'This is the entry date and time'),
                                 'Content'=>array('index'=>FALSE,'type'=>'LONGBLOB','value'=>array(),'Description'=>'This is the entry Content, the structure of depends on the MIME-type.'),
                                 'Params'=>array('index'=>FALSE,'type'=>'LONGBLOB','value'=>array(),'Description'=>'This are the entry Params, e.g. file information of any file attached to the entry, size, name, MIME-type etc.'),
                                 'Expires'=>array('index'=>FALSE,'type'=>'DATETIME','value'=>'2999-01-01 01:00:00','Description'=>'If the current date is later than the Expires-date the entry will be deleted. On insert-entry the init-value is used only if the Owner is not anonymous, set to 10mins otherwise.'),
                                 'Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>0,'Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>0,'Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
                                 'Owner'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'{{Owner}}','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.')
                                 );
    
    public function __construct($oc)
    {
        $this->oc=$oc;
        $this->resetStatistic();
        $this->connect();
        // set defualt entry access rights
        $accessOptions=$oc['SourcePot\Datapool\Foundation\Access']->getAccessOptions();
        $this->rootEntryTemplate['Read']['value']=$accessOptions['ALL_CONTENTADMIN_R'];
        $this->rootEntryTemplate['Write']['value']=$accessOptions['ALL_CONTENTADMIN_R'];
    }

    public function init($oc)
    {
        $this->oc=$oc;
        $this->collectDatabaseInfo();
        $this->entryTemplate=$this->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
    }
    
    public function job($vars):array
    {
        $vars['statistics']=$this->deleteExpiredEntries();
        return $vars;
    }

    public function getDbTimezone():string
    {
        return self::DB_TIMEZONE;
    }
    
    public function getDbStatus(){
        return $this->dbObj->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
    }

    public function enrichToReplace($toReplace=array()){
        $toReplace['{{NOW}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
        $toReplace['{{YESTERDAY}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('yesterday');
        $toReplace['{{TOMORROW}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('tomorrow');
        $toReplace['{{TIMEZONE}}']=self::DB_TIMEZONE;
        $toReplace['{{TIMEZONE-SERVER}}']=date_default_timezone_get();
        $toReplace['{{Expires}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','PT10M');
        $toReplace['{{EntryId}}']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getEntryId();
        if (!isset($_SESSION['currentUser']['EntryId'])){
            $toReplace['{{Owner}}']='SYSTEM';
        } else if (strpos($_SESSION['currentUser']['EntryId'],'EID')===FALSE){
            $toReplace['{{Owner}}']=$_SESSION['currentUser']['EntryId'];
        } else {
            $toReplace['{{Owner}}']='ANONYM';
        }
        if (isset($this->oc['SourcePot\Datapool\Tools\HTMLbuilder'])){
            $pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
            $toReplace['{{pageTitle}}']=$pageSettings['pageTitle'];
            $toReplace['{{pageTimeZone}}']=$pageSettings['pageTimeZone'];
        }
        return $toReplace;
    }

    public function resetStatistic(){
        $_SESSION[__CLASS__]['Statistic']=array('matches'=>0,'updated'=>0,'inserted'=>0,'deleted'=>0,'removed'=>0,'failed'=>0,'skipped'=>0);
        return $_SESSION[__CLASS__]['Statistic'];
    }
    
    public function addStatistic($key,$amount){
        if (!isset($_SESSION[__CLASS__]['Statistic'])){$this->resetStatistic();}
        $_SESSION[__CLASS__]['Statistic'][$key]+=$amount;
    }
    
    public function getStatistic($key=FALSE){
        if (isset($_SESSION[__CLASS__]['Statistic'][$key])){
            return $_SESSION[__CLASS__]['Statistic'][$key];
        } else {
            return $_SESSION[__CLASS__]['Statistic'];
        }
    }
    
    public function statistic2matrix(){
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
    public function getDbName(){return $this->dbName;}
    
    /**
    * @return array|FALSE The method returns entry template for all columns of the provided database table or all columns of all tables if no table is provided or FALSE if the table does not exist.
    */
    public function getEntryTemplate($table=FALSE){
        if ($table){
            if (isset($GLOBALS['dbInfo'][$table])){return $GLOBALS['dbInfo'][$table];}
        } else {
            return $GLOBALS['dbInfo'];
        }
        return FALSE;
    }
    
    /**
    * @return array The method returns the entry template array based on the table and template provided. The method completes the class property dbInfo which contains all entry templates for all tables.
    */
    public function getEntryTemplateCreateTable($table,$template=array()){
        // This function returns the entry template based on the root entry template and
        // the argument $template. In addition this funtion calls create table which creates and updates the
        // database table based on the entry template.
        $GLOBALS['dbInfo'][$table]=array_merge($this->rootEntryTemplate,$template);
        $this->createTable($table,$GLOBALS['dbInfo'][$table]);
        return $GLOBALS['dbInfo'][$table];
    }

    public function unifyEntry($entry,$addEntryDefaults=FALSE){
        // This function selects the $entry-specific unifyEntry() function based on $entry['Source']
        // If the $entry-specific unifyEntry() function is found it will be used to unify the entry.
        if (empty($entry['Source'])){
            throw new \ErrorException('Method '.__FUNCTION__.' called with empty entry Source.',0,E_ERROR,__FILE__,__LINE__);    
        }
        $classWithNamespace=$this->oc['SourcePot\Datapool\Root']->source2class($entry['Source']);
        $registeredMethods=$this->oc['SourcePot\Datapool\Root']->getRegisteredMethods('unifyEntry');    
        if (isset($registeredMethods[$classWithNamespace])){
            $entry=$this->oc[$classWithNamespace]->unifyEntry($entry);
        } else if ($addEntryDefaults){
            $entry=$this->addEntryDefaults($entry);
        }
        return $entry;    
    }

    public function addEntryDefaults($entry,$isDebugging=FALSE){
        $entryTemplate=$GLOBALS['dbInfo'][$entry['Source']];
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
        } // loop throug entry-template-array
        $debugArr['entry out']=$entry;
        if ($isDebugging){$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr,__FUNCTION__.'-'.$entry['Source']);}
        return $entry;
    }

    private function getReplacmentArr($entry,$entryTemplate=array()){
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

    private function connect(){
        // This function establishes the database connection and saves the PDO-object in dbObj.
        // The database user credentials will be taken from 'connect.json' in the '.\setup\Database\' directory.
        // 'connect.json' file will be created if it does not exist. Make sure database user credentials in connect.json are valid for your database.
        $namespaceComps=explode('\\',__NAMESPACE__);
        $dbName=strtolower($namespaceComps[0]);
        $access=array('Class'=>__CLASS__,'EntryId'=>'connect');
        $access['Read']=65535;
        $access['Content']=array('dbServer'=>'localhost','dbName'=>$dbName,'dbUser'=>'webpage','dbUserPsw'=>session_id());
        $access=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($access,TRUE);
        $this->dbObj=new \PDO('mysql:host='.$access['Content']['dbServer'].';dbname='.$access['Content']['dbName'],$access['Content']['dbUser'],$access['Content']['dbUserPsw']);
        $this->dbObj->exec("SET CHARACTER SET 'utf8'");
        $this->dbObj->exec("SET NAMES utf8mb4");
        $this->dbName=$access['Content']['dbName'];
        return $this->dbObj->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
    }
    
    private function collectDatabaseInfo(){
        $sql='SHOW TABLES;';
        $stmt=$this->executeStatement($sql);
        $tables=$stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach($tables as $table){
            $table=current($table);
            $GLOBALS['dbInfo'][$table]=$this->rootEntryTemplate;
        }
        return $GLOBALS['dbInfo'];
    }

    public function executeStatement($sql,$inputs=array(),$debugging=FALSE){
        $debugArr=array('sql'=>$sql,'inputs'=>$inputs);
        $stmt=$this->dbObj->prepare($sql);
        foreach($inputs as $bindKey=>$bindValue){
            if ($debugging){$debugArr['sql']=str_replace($bindKey,strval($bindValue),$debugArr['sql']);}
            $stmt->bindValue($bindKey,$bindValue);
        }
        if ($debugging){$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr,__FUNCTION__);}
        $stmt->execute();
        if (isset($this->oc['SourcePot\Datapool\Foundation\Haystack'])){$this->oc['SourcePot\Datapool\Foundation\Haystack']->processSQLquery($sql,$inputs);}
        return $stmt;
    }
    
    private function createTable($table,$entryTemplate,$engine='MyISAM'){
        $sql="CREATE TABLE IF NOT EXISTS `".$table."` (";
        foreach ($entryTemplate as $column=>$template){
            if ($template['index']===FALSE){
                // nothing to do
            } else if (strcmp($template['index'],'PRIMARY')===0){
                // set primary index
                $primarySQL="PRIMARY KEY  (`".$column."`),";
            } else {
                // set index
                $indexSQL="INDEX ".$template['index']."  (`".$column."`),";
            }
            $sql.="`".$column."` ".$template['type'].",";
        }
        if (isset($primarySQL)){$sql.=$primarySQL;}
        if (isset($indexSQL)){$sql.=$indexSQL;}
        $sql=trim($sql,',');
        $sql.=')';
        $sql.=" ENGINE=:engine DEFAULT CHARSET=utf8 COLLATE  utf8_unicode_ci;";
        $inputs=array(":engine"=>$engine);
        $this->executeStatement($sql,$inputs,FALSE);
    }
    
    private function deleteExpiredEntries(){
        foreach($GLOBALS['dbInfo'] as $table=>$entryTemplate){
            $selector=array('Source'=>$table,'Expires<'=>date('Y-m-d H:i:s'));
            $this->deleteEntries($selector,TRUE);
        }
        return $this->getStatistic();
    }
    
    private function containsStringWildCards($string='abcd\_fgh\%jkl'){
        $string=strval($string);
        return preg_match('/[^\\\\][%_]{1}/',$string);
    }
    
    private function addSelector2result($selector,$result){
        $result['app']=(isset($selector['app']))?$selector['app']:'';
        return $result;
    }
    
    private function selector2sql($selector,$removeGuideEntries=TRUE,$isDebugging=FALSE){
        // This function creates a sql-query from a selector.
        // For types VARCHAR and BLOB the mysql keyword LIKE is used, for all other datatypes math operators will be used.
        // f no operator is provided the '=' operator will be applied. Use '!' operator for 'NOT EQUAL'.
        // Operator need to be added to the end of the column name with the selector,
        // e.g. column name 'Date>=' means Dates larger than or equal to the value provided in the selctor array will be returned.
        // If the selector-key contains the flat-array-key separator, the first part of the key is used as column, 
        // e.g. 'Date|[]|Start' -> refers to column 'Date'.
        if ($removeGuideEntries){$selector['EntryId=!']='%'.self::GUIDEINDICATOR;}
        $entryTemplate=$GLOBALS['dbInfo'][$selector['Source']];
        $opAlias=array('<'=>'LT','<='=>'LE','=<'=>'LE','>'=>'GT','>='=>'GE','=>'=>'GE','='=>'EQ','!'=>'NOT','!='=>'NOT','=!'=>'NOT');
        $sqlArr=array('sql'=>array(),'inputs'=>array());            
        foreach($selector as $column=>$value){
            if ($value===FALSE){continue;}
            preg_match('/([^<>=!]+)([<>=!]+)/',$column,$match);
            if (!empty($match[2])){$operator=$match[2];} else {$operator='=';}
            $placeholder=':'.md5($column.$opAlias[$operator]);
            $columns=explode($this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator(),$column);
            $column=trim($columns[0],' <>=!');
            if (!isset($entryTemplate[$column])){continue;}
            if (is_array($value)){$value=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2json($value);}
            if ((strpos($entryTemplate[$column]['type'],'VARCHAR')!==FALSE || strpos($entryTemplate[$column]['type'],'BLOB')!==FALSE) || $this->containsStringWildCards($value)){
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
            $result[$column]=$this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr($value);
        } else if (strpos($entryTemplate[$column]['type'],'INT')!==FALSE){
            $result[$column]=intval($value);
        } else if (strpos($entryTemplate[$column]['type'],'FLOAT')!==FALSE || strpos($entryTemplate[$column]['type'],'DOUBLE')!==FALSE){
            $result[$column]=floatval($value);
        } else {
            $result[$column]=$value;
        }
        return $result;    
    }
    
    private function addUNYCOMrefs($entry){
        if (!empty($entry['Content']['File content'])){
            $entry['UNYCOM cases']=array();
            $entry['UNYCOM patents']=array();
            $entry['UNYCOM families']=array();
            $entry['UNYCOM inventions']=array();
            $entry['UNYCOM contracts']=array();
            preg_match_all('/([0-9]{4}[XPEF]{1,2}[0-9]{5})((\s{1,2}|WO|WE|EP|AP|EA|OA)[A-Z ]{0,2}[0-9]{0,2})/',$entry['Content']['File content'],$matches);
            if (!empty($matches[0][0])){
                foreach($matches[0] as $matchIndex=>$match){
                    if ($match[4]=='P' || $match[5]=='P'){$entry['UNYCOM patents'][$match]=$match;}
                    $familyRef=preg_replace('/[A-Z]+/','F',$matches[1][$matchIndex]);
                    $inventionRef=preg_replace('/[A-Z]+/','E',$matches[1][$matchIndex]);
                    $entry['UNYCOM cases'][$match]=$match;
                    $entry['UNYCOM families'][$familyRef]=$familyRef;
                    $entry['UNYCOM inventions'][$inventionRef]=$inventionRef;
                }
            }
            preg_match_all('/[0-9]{4}V[0-9]{5}/',$entry['Content']['File content'],$matches);
            if (!empty($matches[0][0])){
                foreach($matches[0] as $matchIndex=>$match){
                    $entry['UNYCOM contracts'][$match]=$match;
                }
            }
            $entry['UNYCOM cases']=implode(';',$entry['UNYCOM cases']);
            $entry['UNYCOM patents']=implode(';',$entry['UNYCOM patents']);
            $entry['UNYCOM families']=implode(';',$entry['UNYCOM families']);
            $entry['UNYCOM inventions']=implode(';',$entry['UNYCOM inventions']);
            $entry['UNYCOM contracts']=implode(';',$entry['UNYCOM contracts']);
        }
        return $entry;
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
            $row=$this->addUNYCOMrefs($row);
            $entries[$row['EntryId']]=$row;
        }
        return $entries;
    }
    
    public function getDistinct($selector,$column,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$removeGuideEntries=FALSE){
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
            $sqlArr=$this->standardSelectQuery($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$removeGuideEntries);
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
                $result=$this->addUNYCOMrefs($result);
                $result=$this->addSelector2result($selector,$result);
                yield $result;
                $result['isFirst']=FALSE;
                $result['rowIndex']++;
            }
        }
        return $result;
    }
    
    public function entryIterator($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$selectExprArr=array(),$removeGuideEntries=TRUE,$isDebugging=FALSE){
        if (empty($selector['Source']) || !isset($GLOBALS['dbInfo'][$selector['Source']])){return array();}
        // debugging trigger
        /*
        if (strcmp($selector['Source'],'multimedia')===0){
            $isDebugging=TRUE;
        }
        */
        //        
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
            if (strpos($row['EntryId'],self::GUIDEINDICATOR)===FALSE){$result['isSkipRow']=FALSE;} else {$result['isSkipRow']=TRUE;}
            foreach($row as $column=>$value){
                $result['hash']=crc32($result['hash'].$value);
                $result=$this->addColumnValue2result($result,$column,$value,$GLOBALS['dbInfo'][$selector['Source']]);
            }
            $result['isLast']=($result['rowIndex']+1)===$result['rowCount'];
            $result=$this->addUNYCOMrefs($result);
            $result=$this->addSelector2result($selector,$result);
            yield $result;
            $result['isFirst']=FALSE;
        }
        return $result;
    }
    
    public function entryById($selector,$isSystemCall=FALSE,$rightType='Read',$returnMetaOnNoMatch=FALSE){
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
                $result=$this->addUNYCOMrefs($result);
                $result=$this->addSelector2result($selector,$result);
            } else {
                if (!$returnMetaOnNoMatch){$result=array();}
            }
        }
        return $result;
    }
    private function sqlEntryIdListSelector($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$selectExprArr=array('EntryId'),$removeGuideEntries=FALSE,$isDebugging=FALSE){
        $result=array('primaryKeys'=>array(),'sql'=>'','primaryKey'=>'EntryId');
        foreach($this->entryIterator($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,$selectExprArr,$removeGuideEntries,$isDebugging) as $row){
            $result['sql'].=",'".$row['EntryId']."'";
            $result['primaryKeys'][]=$row['EntryId'];
        }
        $result['sql']='WHERE `'.'EntryId'.'` IN('.trim($result['sql'],',').')';
        return $result;
    }    
        
    public function updateEntries($selector,$entry,$isSystemCall=FALSE,$rightType='Write',$orderBy=FALSE,$isAsc=FALSE,$limit=FALSE,$offset=FALSE,$selectExprArr=array(),$removeGuideEntries=FALSE,$isDebugging=FALSE){
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
    
    public function deleteEntriesOnly($selector,$isSystemCall=FALSE){
        if (empty($selector['Source']) || !isset($GLOBALS['dbInfo'][$selector['Source']])){return FALSE;}
        $sqlArr=$this->standardSelectQuery($selector,$isSystemCall,'Write',FALSE,TRUE,FALSE,FALSE,FALSE);
        $sqlArr['sql']='DELETE FROM `'.$selector['Source'].'`'.$sqlArr['sql'].';';
        $stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
        $this->addStatistic('deleted',$stmt->rowCount());
    }
    
    public function deleteEntries($selector,$isSystemCall=FALSE){
        // delete files
        $entryList=$this->sqlEntryIdListSelector($selector,$isSystemCall,'Read',FALSE,FALSE,FALSE,FALSE,array(),FALSE,FALSE);
        if (empty($entryList['primaryKeys'])){return FALSE;}
        foreach($entryList['primaryKeys'] as $index=>$primaryKeyValue){
            $entrySelector=array('Source'=>$selector['Source'],'EntryId'=>$primaryKeyValue);
            $fileToDelete=$this->oc['SourcePot\Datapool\Foundation\Filespace']->selector2file($entrySelector);
            if (is_file($fileToDelete)){
                $this->addStatistic('removed',1);
                unlink($fileToDelete);
            }
        }
        // delete entries by id-list
        $sql='DELETE FROM `'.$selector['Source'].'`'.$entryList['sql'].';';
        $stmt=$this->executeStatement($sql,array(),FALSE);
        $this->addStatistic('deleted',$stmt->rowCount());
        return $this->getStatistic('deleted');
    }
        
    /**
    * @return array|FALSE This method adds the provided entry to the database. Default values are added if any entry property is missing. If the entry could not be inserted, the method returns FALSE..
    */
    private function insertEntry($entry){
        $entryTemplate=$this->getEntryTemplate($entry['Source']);
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
        return $entry;
    }

    public function updateEntry($entry,$isSystemCall=FALSE,$noUpdateButCreateIfMissing=FALSE,$addLog=FALSE,$attachment=''){
        // only the Admin has the right to update the data in the Privileges column
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
            // no existing entry found, insert and return entry
            if (is_file($attachment)){
                // valid file attachment found
                $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->addFile2entry($entry,$attachment);
            } else {
                // no valid file attachment
                if (isset($entry['Params']['Attachment log'])){unset($entry['Params']['Attachment log']);}
                if (isset($entry['Params']['File'])){unset($entry['Params']['File']);}
            }
            $entry=$this->addLog2entry($entry,'Processing log',array('msg'=>'Entry created'),FALSE);
            $entry=$this->insertEntry($entry);
        } else if (empty($noUpdateButCreateIfMissing) && $this->oc['SourcePot\Datapool\Foundation\Access']->access($existingEntry,'Write',FALSE,$isSystemCall)){
            // update and return entry | recover existing logs
            $entry=array_replace_recursive($existingEntry,$entry);
            // add attachment
            if (is_file($attachment)){
                $entry=$this->oc['SourcePot\Datapool\Foundation\Filespace']->addFile2entry($entry,$attachment);
            }
            // update entry
            if ($addLog){
                $entry=$this->addLog2entry($entry,'Processing log',array('msg'=>'Entry updated','Expires'=>date('Y-m-d H:i:s',time()+604800)),FALSE);
            }
            $this->updateEntries($selector,$entry,$isSystemCall,'Write',FALSE,FALSE,FALSE,FALSE,array(),FALSE,$isDebugging=FALSE);
            $entry=$this->entryById($selector,$isSystemCall,'Read');
        } else {
            // existing entry and no update 
            $entry=$this->entryById(array('Source'=>$entry['Source'],'EntryId'=>$entry['EntryId']),$isSystemCall,'Read');
        }
        return $entry;
    }
    
    public function entryByIdCreateIfMissing($entry,$isSystemCall=FALSE){
        return $this->updateEntry($entry,$isSystemCall,TRUE);
    }
    
    public function hasEntry($selector,$isSystemCall=TRUE,$returnMetaOnNoMatch=FALSE){
        if (empty($selector['Source'])){
            throw new \ErrorException('Function '.__FUNCTION__.': Source missing in selector',0,E_ERROR,__FILE__,__LINE__);    
        }
        if (empty($selector['EntryId'])){
            foreach($this->entryIterator($selector,$isSystemCall,'Read',$returnMetaOnNoMatch,FALSE,FALSE,FALSE,array(),FALSE) as $entry){
                return $entry;
            }
        } else {
            return $this->entryById($selector,$isSystemCall,'Read',$returnMetaOnNoMatch);
        }
        return FALSE;
    }
        
    public function moveEntryOverwriteTarget($sourceEntry,$targetSelector,$isSystemCall=TRUE,$isTestRun=FALSE,$keepSource=FALSE,$updateSourceFirst=FALSE){
        $userId=empty($_SESSION['currentUser']['EntryId'])?'ANONYM':$_SESSION['currentUser']['EntryId'];
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
                    $targetEntry=$this->addLog2entry($targetEntry,'Processing log',array('success'=>'Moved from EntryId='.$sourceEntry['EntryId'].' to '.$targetEntry['EntryId']),FALSE);
                    if (!$isTestRun){
                        if (!$keepSource){
                            $this->deleteEntries(array('Source'=>$sourceEntry['Source'],'EntryId'=>$sourceEntry['EntryId']),$isSystemCall);
                        }
                        $targetEntry=$this->updateEntry($targetEntry,$isSystemCall,FALSE,FALSE,$targetFile);
                    }            
                } else {
                    // copying or renaming of attached file failed
                    $sourceEntry=$this->addLog2entry($sourceEntry,'Processing log',array('failed'=>'Failed to rename attached file, kept enrtry'),FALSE);
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

    public function swapEntries($entryA,$entryB,$isSystemCall=FALSE)
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
    
    public function addOrderedListIndexToEntryId($primaryKeyValue,$index){
        $primaryKeyValue=$this->orderedListComps($primaryKeyValue);
        $primaryKeyValue=array_pop($primaryKeyValue);    
        return str_pad(strval($index),4,'0',STR_PAD_LEFT).'___'.$primaryKeyValue;
    }
    
    public function getOrderedListIndexFromEntryId($primaryKeyValue){
        $comps=$this->orderedListComps($primaryKeyValue);
        if (count($comps)<2){return 0;}
        $index=array_shift($comps);
        return intval($index);
    }
    
    public function getOrderedListKeyFromEntryId($primaryKeyValue){
        $comps=$this->orderedListComps($primaryKeyValue);
        $key=array_pop($comps);
        return $key;
    }
    
    public function orderedListComps($primaryKeyValue){
        return explode('___',$primaryKeyValue);    
    }
    
    private function orderedList2selector($entry){
        if (empty($entry['Source']) || empty($entry['EntryId'])){return FALSE;}
        $selector=array('Source'=>$entry['Source'],'EntryId'=>'%'.$this->getOrderedListKeyFromEntryId($entry['EntryId']));
        return $selector;
    }
    
    public function orderedEntryListCleanup($selector,$isDebugging=FALSE){
        $orderedListSelector=$this->orderedList2selector($selector);
        if (empty($orderedListSelector)){return FALSE;}
        $targetIndex=1;
        $debugArr=array('selector'=>$selector,'orderedListSelector'=>$orderedListSelector);
        foreach($this->entryIterator($orderedListSelector,TRUE,'Read','EntryId',TRUE) as $entry){
            $targetEntryId=$this->addOrderedListIndexToEntryId($entry['EntryId'],$targetIndex);
            if (strcmp($entry['EntryId'],$targetEntryId)!==0){
                $targetSelector=array('Source'=>$selector['Source'],'EntryId'=>$targetEntryId);
                $this->moveEntryOverwriteTarget($entry,$targetSelector,TRUE,FALSE,FALSE,FALSE);    
            }
            $debugArr['steps'][]=array('targetIndex'=>$targetIndex,'entry EntryId'=>$entry['EntryId'],'target EntryId'=>$targetEntryId,'rowCount'=>$entry['rowCount']);
            $targetIndex++;
        }
        if ($isDebugging){
            if (isset($_SESSION[__CLASS__][__FUNCTION__]['callCount'])){$_SESSION[__CLASS__][__FUNCTION__]['callCount']++;} else {$_SESSION[__CLASS__][__FUNCTION__]['callCount']=1;}
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr,'DebugArr '.__FUNCTION__.'-'.$_SESSION[__CLASS__][__FUNCTION__]['callCount'].'-');
        }
        return TRUE;
    }
    
    public function moveEntry($selector,$moveUp=TRUE,$isSystemCall=FALSE){
        // This method requires column EntryId have the format [constant prefix]|[index]
        // The index range is: 1...index...rowCount
        $orderedListSelector=$this->orderedList2selector($selector);
        if (empty($orderedListSelector)){return FALSE;}
        $status=array('rowCount'=>0,'entries'=>array());
        foreach($this->entryIterator($orderedListSelector,$isSystemCall,'Read','EntryId',TRUE) as $entry){
            $status['rowCount']=$entry['rowCount'];
            $status['entries'][$entry['EntryId']]=$entry;
            if (strcmp($entry['EntryId'],$selector['EntryId'])!==0){continue;}
            $currentIndex=$this->getOrderedListIndexFromEntryId($entry['EntryId']);
            if ($moveUp){
                if ($currentIndex<$entry['rowCount']){$targetIndex=$currentIndex+1;} else {return $entry['EntryId'];}
            } else {
                if ($currentIndex>1){$targetIndex=$currentIndex-1;} else {return $entry['EntryId'];}
            }
            $key=$this->getOrderedListKeyFromEntryId($entry['EntryId']);
            $targetSelector=array('Source'=>$selector['Source']);
            $targetSelector['EntryId']=$this->addOrderedListIndexToEntryId($key,$targetIndex);
            $this->swapEntries($entry,$targetSelector,$isSystemCall);
        }
        if (empty($targetSelector)){
            throw new \ErrorException('Function '.__FUNCTION__.': found "'.$status['rowCount'].'" entries, no targetselector created. Selector used returned no valid entry, isSystemCall='.intval($isSystemCall).'. ',0,E_ERROR,__FILE__,__LINE__);
        }
        return $targetSelector['EntryId'];
    }

    public function addLog2entry($entry,$logType='Processing log',$logContent=array(),$updateEntry=FALSE){
        if (empty($_SESSION['currentUser']['EntryId'])){$userId='ANONYM';} else {$userId=$_SESSION['currentUser']['EntryId'];}
        if (!isset($entry['Params'][$logType])){$entry['Params'][$logType]=array();}
        $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,5);    
        $logContent['timestamp']=time();
        $logContent['time']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now',FALSE,FALSE);
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