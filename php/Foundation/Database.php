<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace Datapool\Foundation;

class Database{

	private $arr;
	
	private $statistic=array();

	private $dbObj;
	private $dbName=FALSE;
	private $dbInfo=array();

	public const ADMIN_R=32768;
	
	private $entryTable='settings';
	private $entryTemplate=array();

	private $rootEntryTemplate=array('ElementId'=>array('index'=>'PRIMARY','type'=>'VARCHAR(255)','value'=>'{{ElementId}}','Description'=>'This is the unique entry key, e.g. ElementId, User hash, etc.','Write'=>0),
								 'Group'=>array('index'=>FALSE,'type'=>'VARCHAR(255)','value'=>'...','Description'=>'First level ordering criterion'),
								 'Folder'=>array('index'=>FALSE,'type'=>'VARCHAR(255)','value'=>'...','Description'=>'Second level ordering criterion'),
								 'Name'=>array('index'=>'NAME_IND','type'=>'VARCHAR(1024)','value'=>'...','Description'=>'Third level ordering criterion'),
								 'Type'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'array','Description'=>'This is the data-type of Content'),
								 'Date'=>array('index'=>FALSE,'type'=>'DATETIME','value'=>'{{NOW}}','Description'=>'This is the entry date and time'),
								 'Content'=>array('index'=>FALSE,'type'=>'LONGBLOB','value'=>array(),'Description'=>'This is the entry Content, the structure of depends on the MIME-type.'),
								 'Params'=>	array('index'=>FALSE,'type'=>'LONGBLOB','value'=>array(),'Description'=>'This are the entry Params, e.g. file information of any file attached to the entry, size, name, MIME-type etc.'),
								 'Expires'=>array('index'=>FALSE,'type'=>'DATETIME','value'=>'2999-01-01 01:00:00','Description'=>'If the current date is later than the Expires-date the entry will be deleted. On insert-entry the init-value is used only if the Owner is not anonymous, set to 10mins otherwise.'),
								 'Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>self::ADMIN_R,'Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>self::ADMIN_R,'Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
								 'Owner'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'{{Owner}}','Description'=>'This is the Owner\'s ElementId or SYSTEM. The Owner has Read and Write access.')
								 );
	
	private $entryTemplates=array();
    
	public function __construct($arr){
		$this->arr=$arr;
		$this->resetStatistic();
		$arr=$this->connect($arr);
	}

	public function init($arr){
		$this->arr=$arr;
		$this->dbInfo=$this->collectDatabaseInfo();
		$this->entryTemplate=$this->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		return $this->arr;
	}
	
	public function job($vars){
		$this->deleteExpiredEntries();
		return $vars;
	}

	public function resetStatistic(){
		$_SESSION[__CLASS__]['Statistic']=array('matches'=>0,'updated'=>0,'inserted'=>0,'deleted'=>0,'removed'=>0);
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
	
	/**
	* @return array|FALSE The method returns information for all columns of the provided database table or all columns of all tables if no table is provided or FALS if the table does not exist.
	*/
	public function getDbInfo($table=FALSE){
		if ($table){
			if (isset($this->dbInfo[$table])){
				return $this->dbInfo[$table];
			} else {
				return FALSE;
			}
		} else {
			return $this->dbInfo;
		}
	}
	
	/**
	* @return string|FALSE The method returns the table for the provided class with namespace. If the table does not exist, FALSE will be returned.
	*/
	public function class2source($class,$toTypeOnly=FALSE,$keepCapitalization=FALSE){
		$source=explode('\\',$class);
		$source=array_pop($source);
		if ($toTypeOnly || isset($this->dbInfo[$source])){
			if (!$keepCapitalization){$source=strtolower($source);}
			return $source;
		} else {
			return FALSE;
		}
	}

	/**
	* @return array The method returns the entry template array based on the table and template provided. The method completes the class property entryTemplates which contains all entry templates for all tables.
	*/
	public function getEntryTemplateCreateTable($table,$template=array()){
		// This function returns the entry template based on the root entry template and
		// the argument $template. In addition this funtion calls create table which creates and updates the
		// database table based on the entry template.
		$this->entryTemplates[$table]=array_merge($this->rootEntryTemplate,$template);
		$this->createTable($table,$this->entryTemplates[$table]);
		return $this->entryTemplates[$table];
	}

	/**
	* @return array The method returns the entry template array based on the provided selector.
	*/
	public function entryTemplate($selector){
		if (empty($selector['Source'])){
			throw new \ErrorException('Function '.__FUNCTION__.': Missing Source key in argument selector',0,E_ERROR,__FILE__,__LINE__);
		} else {
			$table=$selector['Source'];
			if (isset($this->entryTemplates[$table])){
				$entryTemplate=$this->entryTemplates[$table];
			} else {
				$entryTemplate=$this->rootEntryTemplate;
			}
		}
		return $entryTemplate;
	}
	
	public function getPrimaryKeyValue($selector){
		if (empty($selector['Source'])){
			throw new \ErrorException('Function '.__FUNCTION__.': Missing Source key in argument selector',0,E_ERROR,__FILE__,__LINE__);
		}
		$result=FALSE;
		if (isset($this->dbInfo[$selector['Source']])){
			foreach($this->dbInfo[$selector['Source']] as $column=>$defArr){
				if (empty($defArr["Key"])){continue;}
				if (strcmp($defArr["Key"],'PRI')===0){
					// get template
					if (isset($this->entryTemplates[$selector['Source']])){
						$entryTemplate=$this->entryTemplates[$selector['Source']];
					} else {
						$entryTemplate=$this->rootEntryTemplate;
					}
					$result=array('entryTemplate'=>$entryTemplate,'primaryKey'=>$column);
					// get primary value
					if (isset($selector[$column])){$result['primaryValue']=$selector[$column];} else {$result['primaryValue']=FALSE;}
					break;
				}
			}
		}
		return $result;
	}

	public function addEntryDefaults($entry,$isDebugging=FALSE){
		$entryTemplate=$this->entryTemplate($entry);
		$debugArr=array('entryTemplate'=>$entryTemplate,'entry in'=>$entry);
		foreach($entryTemplate as $column=>$defArr){
			if (!isset($defArr['value'])){continue;}
			if (!isset($entry[$column]) || ($defArr['value']===TRUE && empty($entry[$column]))){
				if (is_string($defArr['value'])){$defArr['value']=$this->arr['Datapool\Tools\StrTools']->stdReplacements($defArr['value']);}
				$entry[$column]=$defArr['value'];
			} // if not set or empty but must not be empty
			$entry=$this->arr['Datapool\Foundation\Access']->replaceRightConstant($entry,$column);
		} // loop throug entry-template-array
		$debugArr['entry out']=$entry;
		if ($isDebugging){$this->arr['Datapool\Tools\ArrTools']->arr2file($debugArr,__FUNCTION__.'-'.$entry['Source']);}
		return $entry;
	}
	
	public function getDbName(){return $this->dbName;}	
	
	private function connect($arr){
		// This function establishes the database connection and saves the PDO-object in dbObj.
		// The database user credentials will be taken from 'connect.json' in the '.\setup\Database\' directory.
		// 'connect.json' file will be created if it does not exist. Make sure database user credentials in connect.json are valid for your database.
		$namespaceComps=explode('\\',__NAMESPACE__);
		$dbName=strtolower($namespaceComps[0]);
		$access=array('Class'=>__CLASS__,'SettingName'=>'connect');
		$access['Read']=65535;
		$access['Content']=array('dbServer'=>'localhost','dbName'=>$dbName,'dbUser'=>'webpage','dbUserPsw'=>session_id());
		$access=$this->arr['Datapool\Tools\FileTools']->entryByKeyCreateIfMissing($access,TRUE);
		$this->dbObj=new \PDO('mysql:host='.$access['Content']['dbServer'].';dbname='.$access['Content']['dbName'],$access['Content']['dbUser'],$access['Content']['dbUserPsw']);
		$this->dbObj->exec("SET CHARACTER SET 'utf8'");
		$this->dbName=$access['Content']['dbName'];
		return $arr;
	}
	
	private function collectDatabaseInfo(){
		$dbInfo=array();
		$sql='SHOW TABLES;';
		$stmt=$this->executeStatement($sql);
		$tables=$stmt->fetchAll(\PDO::FETCH_ASSOC);
		foreach($tables as $table){
			$table=current($table);
			$sql='SHOW COLUMNS FROM `'.$table.'`;';
			$stmt=$this->executeStatement($sql);
			$tableInfo=$stmt->fetchAll(\PDO::FETCH_ASSOC);
			foreach($tableInfo as $columnArr){
				$dbInfo[$table][$columnArr['Field']]=$columnArr;
			}
		}
		return $dbInfo;
	}

	private function executeStatement($sql,$inputs=array(),$debugging=FALSE){
		$debugArr=array('sql'=>$sql,'inputs'=>$inputs);
		$stmt=$this->dbObj->prepare($sql);
		foreach($inputs as $bindKey=>$bindValue){
			if ($debugging){$debugArr['sql']=str_replace($bindKey,$bindValue,$debugArr['sql']);}
			$stmt->bindValue($bindKey,$bindValue);
		}
		if ($debugging){$this->arr['Datapool\Tools\ArrTools']->arr2file($debugArr,__FUNCTION__);}
		$stmt->execute();
		$this->arr['Datapool\Foundation\Haystack']->processSQLquery($sql,$inputs);
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
		foreach($this->dbInfo as $table=>$infoArr){
			$selector=array('Source'=>$table,'Expires<'=>date('Y-m-d H:i:s'));
			$this->deleteEntries($selector,TRUE);
		}
		return $this->getStatistic();
	}
	
	private function containsStringWildCards($string='abcd\_fgh\%jkl'){
		$string=strval($string);
		return preg_match('/[^\\\\][%_]{1}/',$string);
	}
	
	private function selector2sql($selector,$entryTemplate){
		// This function creates a sql-query from a selector.
		// For types VARCHAR and BLOB the mysql keyword LIKE is used, for all other datatypes math operators will be used.
		// f no operator is provided the '=' operator will be applied. Use '!' operator for 'NOT EQUAL'.
		// Operator need to be added to the end of the column name with the selector,
		// e.g. column name 'Date>=' means Dates larger than or equal to the value provided in the selctor array will be returned.
		// If the selector-key contains the flat-array-key separator, the first part of the key is used as column, 
		// e.g. 'Date|[]|Start' -> refers to column 'Date'.
		$opAlias=array('<'=>'LT','<='=>'LE','=<'=>'LE','>'=>'GT','>='=>'GE','=>'=>'GE','='=>'EQ','!'=>'NOT','!='=>'NOT','=!'=>'NOT');
		$sqlArr=array('sql'=>array(),'inputs'=>array());			
		foreach($selector as $column=>$value){
			if ($value===FALSE){continue;}
			preg_match('/([^<>=!]+)([<>=!]+)/',$column,$match);
			if (!empty($match[2])){$operator=$match[2];} else {$operator='=';}
			$column=explode($this->arr['Datapool\Tools\ArrTools']->getSeparator(),$column);
			$column=trim($column[0],' <>=!');
			if (!isset($entryTemplate[$column])){continue;}
			$placeholder=':'.$column.$opAlias[$operator];
			if (is_array($value)){$value=$this->arr['Datapool\Tools\ArrTools']->arr2json($value);}
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
		return $sqlArr;		
	}	
	
	private function addRights2sql($sqlArr,$user,$isSystemCall=FALSE,$rightType='Read'){
		if ($isSystemCall===TRUE){return $sqlArr;}
		if (strcmp($rightType,'Read')!==0 && strcmp($rightType,'Write')!==0){
			throw new \ErrorException('Function '.__FUNCTION__.': right type '.$rightType.' unknown.',0,E_ERROR,__FILE__,__LINE__);	
		}
		if (empty($user['Owner'])){
			throw new \ErrorException('Function '.__FUNCTION__.': user[Owner] must not be empty.',0,E_ERROR,__FILE__,__LINE__);	
		}
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
			$result[$column]=$this->arr['Datapool\Tools\ArrTools']->json2arr($value);
		} else if (strpos($entryTemplate[$column]['type'],'INT')!==FALSE){
			$result[$column]=intval($value);
		} else if (strpos($entryTemplate[$column]['type'],'FLOAT')!==FALSE || strpos($entryTemplate[$column]['type'],'DOUBLE')!==FALSE){
			$result[$column]=floatval($value);
		} else {
			$result[$column]=$value;
		}
		return $result;	
	}
	
	private function standardSelectQuery($selector,$primaryKeyValue,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE){
		if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
		$sqlArr=$this->selector2sql($selector,$primaryKeyValue['entryTemplate']);
		$sqlArr=$this->addRights2sql($sqlArr,$user,$isSystemCall,$rightType);
		$sqlArr=$this->addSuffix2sql($sqlArr,$primaryKeyValue['entryTemplate'],$orderBy,$isAsc,$limit,$offset);
		return $sqlArr;
	}
	
	public function getRowCount($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE){
		$primaryKeyValue=$this->getPrimaryKeyValue($selector);
		$sqlArr=$this->standardSelectQuery($selector,$primaryKeyValue,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset);
		$selectExprSQL='';
		$sqlArr['sql']='SELECT COUNT(*) FROM `'.$selector['Source'].'`'.$sqlArr['sql'];
		$sqlArr['sql'].=';';
		//var_dump($sqlArr);
		$stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
		$rowCount=current(current($stmt->fetchAll()));
		return $rowCount;
	}
	
	public function entriesByRight($column='Read',$right='ADMIN_R',$returnPrimaryKeyOnly=TRUE){
		$entries=array();
		$primaryKeyValue=$this->getPrimaryKeyValue(array('Source'=>$this->arr['Datapool\Foundation\User']->getEntryTable()));
		if ($returnPrimaryKeyOnly){$return=$primaryKeyValue['primaryKey'];} else {$return='*';}
		$rights=$this->arr['Datapool\Foundation\Access']->addRights(array(),$right,$right);
		$right=intval($rights['Read']);
		$sql="SELECT ".$return." FROM `".$this->arr['Datapool\Foundation\User']->getEntryTable()."` WHERE ((`".$column."` & ".$right.")>0);";
		$stmt=$this->executeStatement($sql);
		while (($row=$stmt->fetch(\PDO::FETCH_ASSOC))!==FALSE){
			foreach($row as $column=>$value){
				$row=$this->addColumnValue2result($row,$column,$value,$primaryKeyValue['entryTemplate']);
			}
			$entries[$row[$primaryKeyValue['primaryKey']]]=$row;
		}
		return $entries;
	}
	
	public function getDistinct($selector,$column,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE){
		$column=trim($column,'!');
		$primaryKeyValue=$this->getPrimaryKeyValue($selector);
		if (empty($primaryKeyValue)){return array();}
		$sqlArr=$this->standardSelectQuery($selector,$primaryKeyValue,$isSystemCall,$rightType,$orderBy,$isAsc,$limit=FALSE,$offset=FALSE);
		$selectExprSQL='';
		$sqlArr['sql']='SELECT DISTINCT '.$selector['Source'].'.'.$column.' FROM `'.$selector['Source'].'`'.$sqlArr['sql'];
		$sqlArr['sql'].=';';
		//var_dump($sqlArr);
		$stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
		$result=array('isFirst'=>TRUE,'rowIndex'=>0,'rowCount'=>$stmt->rowCount(),'Source'=>$selector['Source'],'hash'=>'');
		$this->addStatistic('matches',$result['rowCount']);
		while (($row=$stmt->fetch(\PDO::FETCH_ASSOC))!==FALSE){
			foreach($row as $column=>$value){
				$result=$this->addColumnValue2result($result,$column,$value,$primaryKeyValue['entryTemplate']);
			}
			yield $result;
			$result['isFirst']=FALSE;
			$result['rowIndex']++;
		}
		return $result;
	}
	
	public function entryIterator($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$selectExprArr=array(),$includeHash=FALSE){
		if (empty($selector['Source'])){return FALSE;}
		$primaryKeyValue=$this->getPrimaryKeyValue($selector);
		if (empty($primaryKeyValue)){return array();}
		$sqlArr=$this->standardSelectQuery($selector,$primaryKeyValue,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset);
		if (empty($selectExprArr)){
			$selectExprSQL=$selector['Source'].'.*';
		} else {
			if (!in_array($primaryKeyValue['primaryKey'],$selectExprArr)){$selectExprArr[]=$primaryKeyValue['primaryKey'];}
			$selectExprSQL=$selector['Source'].'.'.implode(','.$selector['Source'].'.',$selectExprArr);
		}
		$sqlArr['sql']='SELECT '.$selectExprSQL.' FROM `'.$selector['Source'].'`'.$sqlArr['sql'];
		$sqlArr['sql'].=';';
		//var_dump($sqlArr);
		//if (strcmp($selector['Source'],'calendar')===0 && !isset($sqlArr['inputs'][':ElementIdEQ'])){$this->arr['Datapool\Tools\ArrTools']->arr2file($sqlArr);}
		$stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
		$result=array('isFirst'=>TRUE,'isLast'=>TRUE,'rowIndex'=>0,'rowCount'=>$stmt->rowCount(),'Source'=>$selector['Source'],'hash'=>'');
		if ($includeHash){$result['hash']='';}
		$this->addStatistic('matches',$result['rowCount']);
		while (($row=$stmt->fetch(\PDO::FETCH_ASSOC))!==FALSE){
			if (strpos($row[$primaryKeyValue['primaryKey']],'-guideEntry')===FALSE){$result['isSkipRow']=FALSE;} else {$result['isSkipRow']=TRUE;}
			foreach($row as $column=>$value){
				if (isset($result['hash'])){$result['hash']=md5($result['hash'].$value);}
				$result=$this->addColumnValue2result($result,$column,$value,$primaryKeyValue['entryTemplate']);
			}
			$result['isLast']=($result['rowIndex']+1)===$result['rowCount'];
			yield $result;
			$result['isFirst']=FALSE;
			$result['rowIndex']++;
		}
		return $result;
	}
	
	public function entryByKey($selector,$isSystemCall=FALSE,$rightType='Read',$returnMetaOnNoMatch=FALSE){
		$result=array();
		if (empty($selector['Source'])){return $result;}
		if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
		$primaryArr=$this->getPrimaryKeyValue($selector);
		if (!empty($primaryArr['primaryValue'])){
			$sqlPlaceholder=':'.$primaryArr['primaryKey'];
			$sqlArr=array('sql'=>"SELECT * FROM `".$selector['Source']."` WHERE `".$primaryArr['primaryKey']."`=".$sqlPlaceholder,'inputs'=>array($sqlPlaceholder=>$primaryArr['primaryValue']));
			$sqlArr=$this->addRights2sql($sqlArr,$user,$isSystemCall,$rightType);
			$sqlArr['sql'].=';';
			//var_dump($sqlArr);
		$stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
			$result=array('isFirst'=>TRUE,'rowIndex'=>0,'rowCount'=>$stmt->rowCount(),'primaryKey'=>$primaryArr['primaryKey'],'primaryValue'=>$primaryArr['primaryValue'],'Source'=>$selector['Source']);
			$this->addStatistic('matches',$result['rowCount']);
			$row=$stmt->fetch(\PDO::FETCH_ASSOC);
			if (is_array($row)){
				foreach($row as $column=>$value){
					$result=$this->addColumnValue2result($result,$column,$value,$primaryArr['entryTemplate']);
				}
			} else {
				if (!$returnMetaOnNoMatch){$result=array();}
			}
		}
		return $result;
	}

	private function sqlPrimaryKeyListSelector($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE){
		$result=$this->getPrimaryKeyValue($selector);
		$result['primaryKeys']=array();
		$result['sql']='';
		foreach($this->entryIterator($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,array($result['primaryKey'])) as $row){
			$result['sql'].=",'".$row[$result['primaryKey']]."'";
			$result['primaryKeys'][]=$row[$result['primaryKey']];
		}
		$result['sql']='WHERE `'.$result['primaryKey'].'` IN('.trim($result['sql'],',').')';
		return $result;
	}	
	
	public function updateEntries($selector,$entry,$isSystemCall=FALSE,$isDebugging=FALSE){
		$entryList=$this->sqlPrimaryKeyListSelector($selector,$isSystemCall,'Write');
		if (empty($entryList['primaryKeys'])){
			return FALSE;
		} else {
			// set values
			$inputs=array();
			$valueSql='';
			foreach($entry as $column=>$value){
				if (!isset($this->dbInfo[$selector['Source']][$column])){continue;}
				if (strcmp($column,'Source')===0){continue;}
				$sqlPlaceholder=':'.$column;
				$valueSql.="`".$column."`=".$sqlPlaceholder.",";
				if (is_array($value)){$value=$this->arr['Datapool\Tools\ArrTools']->arr2json($value);}
				$inputs[$sqlPlaceholder]=strval($value);
			}
			$sql="UPDATE `".$selector['Source']."` SET ".trim($valueSql,',')." ".$entryList['sql'].";";
			$stmt=$this->executeStatement($sql,$inputs,$isDebugging);
			$this->addStatistic('updated',$stmt->rowCount());
			return $this->getStatistic('updated');
		}
	}
	
	public function deleteEntriesOnly($selector,$isSystemCall=FALSE){
		if (empty($selector['Source'])){return FALSE;}
		$primaryKeyValue=$this->getPrimaryKeyValue($selector);
		if (empty($primaryKeyValue)){return array();}
		$sqlArr=$this->standardSelectQuery($selector,$primaryKeyValue,$isSystemCall,'Write');
		$sqlArr['sql']='DELETE FROM `'.$selector['Source'].'`'.$sqlArr['sql'].';';
		//var_dump($sqlArr);
		$stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
		$this->addStatistic('deleted',$stmt->rowCount());
	}
	
	public function deleteEntries($selector,$isSystemCall=FALSE){
		$this->deleteEntriesOnly($selector,$isSystemCall);
		// delete files
		$entryList=$this->sqlPrimaryKeyListSelector($selector,$isSystemCall,'Read',FALSE,TRUE,FALSE,FALSE);
		if (empty($entryList['primaryKeys'])){return FALSE;}
		foreach($entryList['primaryKeys'] as $index=>$primaryKeyValue){
			$entrySelector=array('Source'=>$selector['Source'],$entryList['primaryKey']=>$primaryKeyValue);
			$fileToDelete=$this->arr['Datapool\Tools\FileTools']->selector2file($entrySelector);
			if (is_file($fileToDelete)){
				$this->addStatistic('removed',1);
				unlink($fileToDelete);
			}
		}
		return $this->getStatistic('deleted');
	}
	
	public function insertEntry($entry){
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
			if (!isset($this->dbInfo[$entry['Source']][$column])){continue;}
			if (strcmp($column,'Source')===0){continue;}
			$sqlPlaceholder=':'.$column;
			$columns.='`'.$column.'`,';
			$values.=$sqlPlaceholder.',';
			if (is_array($value)){$value=$this->arr['Datapool\Tools\ArrTools']->arr2json($value);}
			$inputs[$sqlPlaceholder]=strval($value);
		}
		$sql="INSERT INTO `".$entry['Source']."` (".trim($columns,',').") VALUES (".trim($values,',').") ON DUPLICATE KEY UPDATE `ElementId`='".$entry['ElementId']."';";
		$stmt=$this->executeStatement($sql,$inputs,FALSE);
		$this->addStatistic('inserted',$stmt->rowCount());
		return $entry;
	}

	public function updateEntry($entry,$isSystemCall=FALSE){
		// This function updates the selected entry or inserts a new entry.
		// The primary key needs to be provided.
		$existingEntry=$this->entryByKey($entry,TRUE,'Write',TRUE);
		if (empty($existingEntry['rowCount'])){
			$entry=$this->insertEntry($entry);
		} else {
			// update entry
			$primaryKeyValue=$this->getPrimaryKeyValue($entry);
			unset($entry[$primaryKeyValue['primaryKey']]);
			$selector=array('Source'=>$entry['Source'],$primaryKeyValue['primaryKey']=>$primaryKeyValue['primaryValue']);
			$entry=$this->arr['Datapool\Foundation\Access']->replaceRightConstant($entry,'Read');
			$entry=$this->arr['Datapool\Foundation\Access']->replaceRightConstant($entry,'Write');
			$entry=$this->arr['Datapool\Foundation\Access']->replaceRightConstant($entry,'Privileges');
			$this->updateEntries($selector,$entry,$isSystemCall);
			$entry=$this->entryByKey($selector,$isSystemCall,'Write');
		}
		return $entry;
	}
	
	public function entryByKeyCreateIfMissing($entry,$isSystemCall=FALSE){
		// This function updates the selected entry or inserts a new entry.
		// The existing entry is selecvted by kay, i.e. the primary key must to be provided!
		if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
		$existingEntry=$this->hasEntry($entry,$isSystemCall,TRUE);
		if (empty($existingEntry['rowCount']) && isset($this->dbInfo[$entry['Source']])){
			$existingEntry=$this->insertEntry($entry);
		}
		if ($this->arr['Datapool\Foundation\Access']->access($existingEntry,'Read',$user,$isSystemCall)){
			$return=$existingEntry;
		} else {
			$return=FALSE;
		}
		return $return;
	}
	
	public function hasEntry($selector,$isSystemCall=TRUE,$returnMetaOnNoMatch=FALSE){
		if (empty($selector['Source'])){
			throw new \ErrorException('Function '.__FUNCTION__.': Source missing in selector',0,E_ERROR,__FILE__,__LINE__);	
		}
		if (empty($selector['ElementId'])){
			foreach($this->entryIterator($selector,$isSystemCall,'Read',FALSE,TRUE,1) as $entry){
				return $entry;
			}
		} else {
			return $this->entryByKey($selector,TRUE,'Read',$returnMetaOnNoMatch);
		}
		return FALSE;
	}
	
	public function moveEntryByElementId($entry,$targetSelector){
		$entryFileName=$this->arr['Datapool\Tools\FileTools']->selector2file($entry);
		$targetFileName=$this->arr['Datapool\Tools\FileTools']->selector2file($targetSelector);
		// backup an existing entry with ElementId=$targetSelector
		$return=$this->entryByKey($targetSelector);
		if (!empty($return)){
			$return['File']=$this->arr['Datapool\Tools\FileTools']->getTmpDir().__FUNCTION__.'.file';
			@rename($targetFileName,$return['File']);
		}
		// move entry
		@rename($entryFileName,$targetFileName);
		$newEntry=$entry;
		$newEntry['ElementId']=$targetSelector['ElementId'];
		$this->updateEntry($newEntry);
		$this->deleteEntries($entry);
		return $return;
	}
	
	public function swapEntriesByElementId($entryA,$entryB){
		$entryB=$this->moveEntryByElementId($entryA,$entryB);
		if (!empty($entryB)){
			$entryB['ElementId']=$entryA['ElementId'];
			$entryBfileName=$this->arr['Datapool\Tools\FileTools']->selector2file($entryB);
			@rename($entryB['File'],$entryBfileName);
			$this->updateEntry($entryB);
		}
		return $entryB;
	}
	
	public function addOrderedListIndexToElementId($primaryKeyValue,$index){
		$primaryKeyValue=$this->orderedListComps($primaryKeyValue);
		$primaryKeyValue=array_pop($primaryKeyValue);	
		return str_pad(strval($index),4,'0',STR_PAD_LEFT).'___'.$primaryKeyValue;
	}
	
	public function getOrderedListIndexFromElementId($primaryKeyValue){
		$comps=$this->orderedListComps($primaryKeyValue);
		if (count($comps)<2){return 0;}
		$index=array_shift($comps);
		return intval($index);
	}
	
	public function getOrderedListKeyFromElementId($primaryKeyValue){
		$comps=$this->orderedListComps($primaryKeyValue);
		$key=array_pop($comps);
		return $key;
	}
	
	public function orderedListComps($primaryKeyValue){
		return explode('___',$primaryKeyValue);	
	}
	
	private function orderedList2selector($entry){
		if (empty($entry['Source']) || empty($entry['ElementId'])){return FALSE;}
		$selector=array('Source'=>$entry['Source'],'ElementId'=>'%'.$this->getOrderedListKeyFromElementId($entry['ElementId']));
		return $selector;
	}
	
	public function orderedEntryListCleanup($selector,$isDebugging=FALSE){
		$orderedListSelector=$this->orderedList2selector($selector);
		if (empty($orderedListSelector)){return FALSE;}
		$targetIndex=1;
		$debugArr=array('selector'=>$selector,'orderedListSelector'=>$orderedListSelector);
		foreach($this->entryIterator($orderedListSelector,FALSE,'Read','ElementId',TRUE) as $entry){
			$targetElementId=$this->addOrderedListIndexToElementId($entry['ElementId'],$targetIndex);
			if (strcmp($entry['ElementId'],$targetElementId)!==0){
				$this->moveEntryByElementId($entry,array('Source'=>$selector['Source'],'ElementId'=>$targetElementId));
			}
			$debugArr['stpes'][]=array('targetIndex'=>$targetIndex,'entry ElementId'=>$entry['ElementId'],'target ElementId'=>$targetElementId);
			$targetIndex++;
		}
		if ($isDebugging){
			if (isset($_SESSION[__CLASS__][__FUNCTION__]['callCount'])){$_SESSION[__CLASS__][__FUNCTION__]['callCount']++;} else {$_SESSION[__CLASS__][__FUNCTION__]['callCount']=1;}
			$this->arr['Datapool\Tools\ArrTools']->arr2file($debugArr,'DebugArr '.__FUNCTION__.'-'.$_SESSION[__CLASS__][__FUNCTION__]['callCount'].'-');
		}
		return TRUE;
	}
	
	public function moveEntry($selector,$moveUp=TRUE){
		// This method requires column ElementId have the format [constant prefix]|[index]
		// The index range is: 1...index...rowCount
		$orderedListSelector=$this->orderedList2selector($selector);
		if (empty($orderedListSelector)){return FALSE;}
		$status=array('rowCount'=>0,'targetElementId'=>FALSE,'selectedElementId'=>FALSE,'entries'=>array());
		foreach($this->entryIterator($orderedListSelector,FALSE,'Read','ElementId',TRUE) as $entry){
			$status['rowCount']=$entry['rowCount'];
			$status['entries'][$entry['ElementId']]=$entry;
			if (strcmp($entry['ElementId'],$selector['ElementId'])!==0){continue;}
			$currentIndex=$this->getOrderedListIndexFromElementId($entry['ElementId']);
			if ($moveUp){
				if ($currentIndex<$entry['rowCount']){$targetIndex=$currentIndex+1;} else {return TRUE;}
			} else {
				if ($currentIndex>1){$targetIndex=$currentIndex-1;} else {return TRUE;}
			}
			$key=$this->getOrderedListKeyFromElementId($entry['ElementId']);
			$targetSelector=array('Source'=>$selector['Source']);
			$targetSelector['ElementId']=$this->addOrderedListIndexToElementId($key,$targetIndex);
			$this->swapEntriesByElementId($entry,$targetSelector);
		}
		return TRUE;
	}

}
?>