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

namespace SourcePot\Datapool\Foundation;

class Database{

	private $arr;
	
	private $statistic=array();

	private $dbObj;
	private $dbName=FALSE;
	
	public const ADMIN_R=32768;
	
	private $entryTable='settings';
	private $entryTemplate=array();

	private $rootEntryTemplate=array('EntryId'=>array('index'=>'PRIMARY','type'=>'VARCHAR(255)','value'=>'{{EntryId}}','Description'=>'This is the unique entry key, e.g. EntryId, User hash, etc.','Write'=>0),
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
								 'Owner'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'{{Owner}}','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.')
								 );
	
	public function __construct($arr){
		$this->arr=$arr;
		$this->resetStatistic();
		$arr=$this->connect($arr);
	}

	public function init($arr){
		$this->arr=$arr;
		$this->collectDatabaseInfo();
		$this->entryTemplate=$this->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		return $this->arr;
	}
	
	public function job($vars){
		$vars['statistics']=$this->deleteExpiredEntries();
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
	* @return string|FALSE The method returns the table for the provided class with namespace. If the table does not exist, FALSE will be returned.
	*/
	public function class2source($class,$toTypeOnly=FALSE,$keepCapitalization=FALSE){
		$source=explode('\\',$class);
		$source=array_pop($source);
		if ($toTypeOnly || isset($GLOBALS['dbInfo'][$source])){
			if (!$keepCapitalization){$source=strtolower($source);}
			return $source;
		} else {
			return FALSE;
		}
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

	public function unifyEntry($entry){
		// This function selects the $entry-specific unifyEntry() function based on $entry['Source']
		// If the $entry-specific unifyEntry() function is found it will be used to unify the entry.
		$this->resetStatistic();
		if (isset($this->arr['source2class'][$entry['Source']])){
			$classWithNamespace=$this->arr['source2class'][$entry['Source']];
			if (isset($this->arr['registered methods']['unifyEntry'][$classWithNamespace])){
				return $this->arr[$classWithNamespace]->unifyEntry($entry);
			}
		}	
		return $entry;	
	}

	public function addEntryDefaults($entry,$isDebugging=FALSE){
		$entryTemplate=$GLOBALS['dbInfo'][$entry['Source']];
		$debugArr=array('entryTemplate'=>$entryTemplate,'entry in'=>$entry);
		foreach($entryTemplate as $column=>$defArr){
			if (!isset($defArr['value'])){continue;}
			if (!isset($entry[$column]) || ($defArr['value']===TRUE && empty($entry[$column]))){
				$entry[$column]=$defArr['value'];
			} // if not set or empty but must not be empty
			if (is_string($entry[$column])){
				$entry[$column]=$this->stdReplacements($entry[$column]);
			}
			$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,$column);
		} // loop throug entry-template-array
		$debugArr['entry out']=$entry;
		if ($isDebugging){$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr,__FUNCTION__.'-'.$entry['Source']);}
		return $entry;
	}

	public function stdReplacements($str=''){
		if (is_array($str)){return $str;}
		$toReplace['{{NOW}}']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
		$toReplace['{{YESTERDAY}}']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getDateTime('yesterday');
		$toReplace['{{TOMORROW}}']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getDateTime('tomorrow');
		$toReplace['{{TIMEZONE-SERVER}}']=date_default_timezone_get();
		$toReplace['{{Expires}}']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','PT10M');
		$toReplace['{{EntryId}}']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getEntryId();
		if (!isset($_SESSION['currentUser']['EntryId'])){
			$toReplace['{{Owner}}']='SYSTEM';
		} else if (strpos($_SESSION['currentUser']['EntryId'],'EID')===FALSE){
			$toReplace['{{Owner}}']=$_SESSION['currentUser']['EntryId'];
		} else {
			$toReplace['{{Owner}}']='ANONYM';
		}
		if (isset($this->arr['SourcePot\Datapool\Tools\HTMLbuilder'])){
			$pageSettings=$this->arr['SourcePot\Datapool\Foundation\Backbone']->getSettings();
			$toReplace['{{pageTitle}}']=$pageSettings['pageTitle'];
			$toReplace['{{pageTimeZone}}']=$pageSettings['pageTimeZone'];
		}
		//
		if (is_array($str)){
			throw new \ErrorException('Function '.__FUNCTION__.' called with argument str of type array.',0,E_ERROR,__FILE__,__LINE__);	
		} else if (is_string($str)){
			foreach($toReplace as $needle=>$replacement){
				$str=str_replace($needle,$replacement,$str);
			}
		}
		//$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($toReplace);
		return $str;
	}

	public function arrStdReplacements($arr,$flatKeyMapping=array(),$isDebugging=FALSE){
		// This method maps array-keys found in flatKeyMapping-argument to new array-keys.
		// On array-values class 'Datapool\Tools\MiscTools' method 'stdReplacements' will be called
		// If different source keys mapped on the same target key, the string-value will be added to the end of the existing string-value with a space character in between.
		// The structur of argument flatKeyMapping is:
		// array({1. flat source key}=>{1. flat target key} or FALSE,{2. flat source key}=>{2. flat target key} or FALSE,....)
		// If FALSE is used instead of a valid flat target key, the key-value will be removed from the result.
		$debugArr=array('arr'=>$arr,'flatKeyMapping'=>$flatKeyMapping);
		$result=array();
		$flatArr=$this->arr2flat($arr);
		foreach($flatArr as $flatKey=>$value){
			if (isset($flatKeyMapping[$flatKey])){$flatResultKey=$flatKeyMapping[$flatKey];} else {$flatResultKey=$flatKey;}
			if ($flatResultKey===FALSE){continue;}
			if (empty($result[$flatResultKey])){
				$result[$flatResultKey]=$this->stdReplacements($value);
			} else {
				$result[$flatResultKey].=' '.$this->stdReplacements($value);
			}
		}
		$result=$this->flat2arr($result);
		if ($isDebugging){
			$debugArr['result']=$result;
			$this->arr2file($debugArr);
		}
		return $result;
	}

	private function connect($arr){
		// This function establishes the database connection and saves the PDO-object in dbObj.
		// The database user credentials will be taken from 'connect.json' in the '.\setup\Database\' directory.
		// 'connect.json' file will be created if it does not exist. Make sure database user credentials in connect.json are valid for your database.
		$namespaceComps=explode('\\',__NAMESPACE__);
		$dbName=strtolower($namespaceComps[0]);
		$access=array('Class'=>__CLASS__,'SettingName'=>'connect');
		$access['Read']=65535;
		$access['Content']=array('dbServer'=>'localhost','dbName'=>$dbName,'dbUser'=>'webpage','dbUserPsw'=>session_id());
		$access=$this->arr['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($access,TRUE);
		$this->dbObj=new \PDO('mysql:host='.$access['Content']['dbServer'].';dbname='.$access['Content']['dbName'],$access['Content']['dbUser'],$access['Content']['dbUserPsw']);
		$this->dbObj->exec("SET CHARACTER SET 'utf8'");
		$this->dbName=$access['Content']['dbName'];
		return $arr;
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

	private function executeStatement($sql,$inputs=array(),$debugging=FALSE){
		$debugArr=array('sql'=>$sql,'inputs'=>$inputs);
		$stmt=$this->dbObj->prepare($sql);
		foreach($inputs as $bindKey=>$bindValue){
			if ($debugging){$debugArr['sql']=str_replace($bindKey,$bindValue,$debugArr['sql']);}
			$stmt->bindValue($bindKey,$bindValue);
		}
		if ($debugging){$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr,__FUNCTION__);}
		$stmt->execute();
		if (isset($this->arr['SourcePot\Datapool\Foundation\Haystack'])){$this->arr['SourcePot\Datapool\Foundation\Haystack']->processSQLquery($sql,$inputs);}
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
	
	private function selector2sql($selector){
		// This function creates a sql-query from a selector.
		// For types VARCHAR and BLOB the mysql keyword LIKE is used, for all other datatypes math operators will be used.
		// f no operator is provided the '=' operator will be applied. Use '!' operator for 'NOT EQUAL'.
		// Operator need to be added to the end of the column name with the selector,
		// e.g. column name 'Date>=' means Dates larger than or equal to the value provided in the selctor array will be returned.
		// If the selector-key contains the flat-array-key separator, the first part of the key is used as column, 
		// e.g. 'Date|[]|Start' -> refers to column 'Date'.
		$entryTemplate=$GLOBALS['dbInfo'][$selector['Source']];
		$opAlias=array('<'=>'LT','<='=>'LE','=<'=>'LE','>'=>'GT','>='=>'GE','=>'=>'GE','='=>'EQ','!'=>'NOT','!='=>'NOT','=!'=>'NOT');
		$sqlArr=array('sql'=>array(),'inputs'=>array());			
		foreach($selector as $column=>$value){
			if ($value===FALSE){continue;}
			preg_match('/([^<>=!]+)([<>=!]+)/',$column,$match);
			if (!empty($match[2])){$operator=$match[2];} else {$operator='=';}
			$column=explode($this->arr['SourcePot\Datapool\Tools\MiscTools']->getSeparator(),$column);
			$column=trim($column[0],' <>=!');
			if (!isset($entryTemplate[$column])){continue;}
			$placeholder=':'.$column.$opAlias[$operator];
			if (is_array($value)){$value=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2json($value);}
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
			$result[$column]=$this->arr['SourcePot\Datapool\Tools\MiscTools']->json2arr($value);
		} else if (strpos($entryTemplate[$column]['type'],'INT')!==FALSE){
			$result[$column]=intval($value);
		} else if (strpos($entryTemplate[$column]['type'],'FLOAT')!==FALSE || strpos($entryTemplate[$column]['type'],'DOUBLE')!==FALSE){
			$result[$column]=floatval($value);
		} else {
			$result[$column]=$value;
		}
		return $result;	
	}
	
	private function standardSelectQuery($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE){
		if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
		$sqlArr=$this->selector2sql($selector,);
		$sqlArr=$this->addRights2sql($sqlArr,$user,$isSystemCall,$rightType);
		$sqlArr=$this->addSuffix2sql($sqlArr,$GLOBALS['dbInfo'][$selector['Source']],$orderBy,$isAsc,$limit,$offset);
		return $sqlArr;
	}
	
	public function getRowCount($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE){
		$sqlArr=$this->standardSelectQuery($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset);
		$selectExprSQL='';
		$sqlArr['sql']='SELECT COUNT(*) FROM `'.$selector['Source'].'`'.$sqlArr['sql'];
		$sqlArr['sql'].=';';
		//var_dump($sqlArr);
		$stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
		$rowCount=current(current($stmt->fetchAll()));
		return $rowCount;
	}
	
	public function entriesByRight($column='Read',$right='ADMIN_R',$returnPrimaryKeyOnly=TRUE){
		$selector=array('Source'=>$this->arr['SourcePot\Datapool\Foundation\User']->getEntryTable());
		if ($returnPrimaryKeyOnly){$return='EntryId';} else {$return='*';}
		$rights=$this->arr['SourcePot\Datapool\Foundation\Access']->addRights(array(),$right,$right);
		$right=intval($rights['Read']);
		$sql="SELECT ".$return." FROM `".$this->arr['SourcePot\Datapool\Foundation\User']->getEntryTable()."` WHERE ((`".$column."` & ".$right.")>0);";
		$stmt=$this->executeStatement($sql);
		$entries=array();
		while (($row=$stmt->fetch(\PDO::FETCH_ASSOC))!==FALSE){
			foreach($row as $column=>$value){
				$row=$this->addColumnValue2result($row,$column,$value,$GLOBALS['dbInfo'][$selector['Source']]);
			}
			$entries[$row['EntryId']]=$row;
		}
		return $entries;
	}
	
	public function getDistinct($selector,$column,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE){
		$column=trim($column,'!');
		$sqlArr=$this->standardSelectQuery($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit=FALSE,$offset=FALSE);
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
			yield $result;
			$result['isFirst']=FALSE;
			$result['rowIndex']++;
		}
		return $result;
	}
	
	public function entryIterator($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE,$selectExprArr=array()){
		if (empty($selector['Source'])){return FALSE;}
		$sqlArr=$this->standardSelectQuery($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset);
		if (empty($selectExprArr)){
			$selectExprSQL=$selector['Source'].'.*';
		} else {
			if (!in_array('EntryId',$selectExprArr)){$selectExprArr[]='EntryId';}
			$selectExprSQL=$selector['Source'].'.'.implode(','.$selector['Source'].'.',$selectExprArr);
		}
		$sqlArr['sql']='SELECT '.$selectExprSQL.' FROM `'.$selector['Source'].'`'.$sqlArr['sql'];
		$sqlArr['sql'].=';';
		//var_dump($sqlArr);
		//if (strcmp($selector['Source'],'calendar')===0 && !isset($sqlArr['inputs'][':EntryIdEQ'])){$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($sqlArr);}
		$stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
		$result=array('isFirst'=>TRUE,'isLast'=>TRUE,'rowIndex'=>0,'rowCount'=>$stmt->rowCount(),'now'=>time(),'Source'=>$selector['Source'],'hash'=>'');
		$this->addStatistic('matches',$result['rowCount']);
		while (($row=$stmt->fetch(\PDO::FETCH_ASSOC))!==FALSE){
			if (strpos($row['EntryId'],'-guideEntry')===FALSE){$result['isSkipRow']=FALSE;} else {$result['isSkipRow']=TRUE;}
			foreach($row as $column=>$value){
				$result['hash']=crc32($result['hash'].$value);
				$result=$this->addColumnValue2result($result,$column,$value,$GLOBALS['dbInfo'][$selector['Source']]);
			}
			$result['isLast']=($result['rowIndex']+1)===$result['rowCount'];
			yield $result;
			$result['isFirst']=FALSE;
			$result['rowIndex']++;
		}
		return $result;
	}
	
	public function entryById($selector,$isSystemCall=FALSE,$rightType='Read',$returnMetaOnNoMatch=FALSE){
		$result=array();
		if (empty($selector['Source'])){return $result;}
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
			} else {
				if (!$returnMetaOnNoMatch){$result=array();}
			}
		}
		return $result;
	}

	private function sqlEntryIdListSelector($selector,$isSystemCall=FALSE,$rightType='Read',$orderBy=FALSE,$isAsc=TRUE,$limit=FALSE,$offset=FALSE){
		$result=array('primaryKeys'=>array(),'sql'=>'');
		foreach($this->entryIterator($selector,$isSystemCall,$rightType,$orderBy,$isAsc,$limit,$offset,array('EntryId')) as $row){
			$result['sql'].=",'".$row['EntryId']."'";
			$result['primaryKeys'][]=$row['EntryId'];
		}
		$result['sql']='WHERE `'.'EntryId'.'` IN('.trim($result['sql'],',').')';
		return $result;
	}	
	
	public function updateEntries($selector,$entry,$isSystemCall=FALSE,$isDebugging=FALSE){
		$entryList=$this->sqlEntryIdListSelector($selector,$isSystemCall,'Write');
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
				if (is_array($value)){$value=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2json($value);}
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
		$sqlArr=$this->standardSelectQuery($selector,$isSystemCall,'Write');
		$sqlArr['sql']='DELETE FROM `'.$selector['Source'].'`'.$sqlArr['sql'].';';
		//var_dump($sqlArr);
		$stmt=$this->executeStatement($sqlArr['sql'],$sqlArr['inputs'],FALSE);
		$this->addStatistic('deleted',$stmt->rowCount());
	}
	
	public function deleteEntries($selector,$isSystemCall=FALSE){
		$this->deleteEntriesOnly($selector,$isSystemCall);
		// delete files
		$entryList=$this->sqlEntryIdListSelector($selector,$isSystemCall,'Read',FALSE,TRUE,FALSE,FALSE);
		if (empty($entryList['primaryKeys'])){return FALSE;}
		foreach($entryList['primaryKeys'] as $index=>$primaryKeyValue){
			$entrySelector=array('Source'=>$selector['Source'],$entryList['primaryKey']=>$primaryKeyValue);
			$fileToDelete=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($entrySelector);
			if (is_file($fileToDelete)){
				$this->addStatistic('removed',1);
				unlink($fileToDelete);
			}
		}
		return $this->getStatistic('deleted');
	}
	
	/**
	* @return array|FALSE This method adds the provided entry to the database. Default values are added if any entry property is missing. If the entry could not be inserted, the method returns FALSE..
	*/
	public function insertEntry($entry){
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
			if (is_array($value)){$value=$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2json($value);}
			$inputs[$sqlPlaceholder]=strval($value);
		}
		$sql="INSERT INTO `".$entry['Source']."` (".trim($columns,',').") VALUES (".trim($values,',').") ON DUPLICATE KEY UPDATE `EntryId`='".$entry['EntryId']."';";
		$stmt=$this->executeStatement($sql,$inputs,FALSE);
		$this->addStatistic('inserted',$stmt->rowCount());
		return $entry;
	}

	public function updateEntry($entry,$isSystemCall=FALSE){
		// This function updates the selected entry or inserts a new entry.
		// The primary key needs to be provided.
		$existingEntry=$this->entryById($entry,TRUE,'Write',TRUE);
		if (empty($existingEntry['rowCount'])){
			$entry=$this->insertEntry($entry);
		} else {
			// update entry
			$selector=array('Source'=>$entry['Source'],'EntryId'=>$entry['EntryId']);
			unset($entry['EntryId']);
			$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Read');
			$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Write');
			$entry=$this->arr['SourcePot\Datapool\Foundation\Access']->replaceRightConstant($entry,'Privileges');
			$this->updateEntries($selector,$entry,$isSystemCall);
			$entry=$this->entryById($selector,$isSystemCall,'Write');
		}
		return $entry;
	}
	
	public function entryByIdCreateIfMissing($entry,$isSystemCall=FALSE){
		// This function updates the selected entry or inserts a new entry.
		// The existing entry is selecvted by kay, i.e. the primary key must to be provided!
		if (empty($_SESSION['currentUser'])){$user=array('Privileges'=>1,'Owner'=>'ANONYM');} else {$user=$_SESSION['currentUser'];}
		$existingEntry=$this->hasEntry($entry,$isSystemCall,TRUE);
		if (empty($existingEntry['rowCount']) && isset($GLOBALS['dbInfo'][$entry['Source']])){
			$existingEntry=$this->insertEntry($entry);
		}
		if ($this->arr['SourcePot\Datapool\Foundation\Access']->access($existingEntry,'Read',$user,$isSystemCall)){
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
		if (empty($selector['EntryId'])){
			foreach($this->entryIterator($selector,$isSystemCall,'Read',FALSE,TRUE,1) as $entry){
				return $entry;
			}
		} else {
			return $this->entryById($selector,TRUE,'Read',$returnMetaOnNoMatch);
		}
		return FALSE;
	}
	
	public function moveEntryOverwriteTraget($sourceEntry,$targetSelector=FALSE,$relevantKeys=array('Source','Group','Folder','Name')){
		$sourceFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($sourceEntry);
		if (!empty($targetSelector)){$targetEntry=array_replace_recursive($sourceEntry,$targetSelector);}
		$targetEntry=$this->arr['SourcePot\Datapool\Tools\MiscTools']->addEntryId($sourceEntry,$relevantKeys,'0','',FALSE);
		$targetFile=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($targetEntry);
		if (strcmp($sourceEntry['EntryId'],$targetEntry['EntryId'])!==0){
			if (is_file($sourceFile)){
				@rename($sourceFile,$targetFile);
				if (empty($_SESSION['currentUser']['EntryId'])){$userId='ANONYM';} else {$userId=$_SESSION['currentUser']['EntryId'];}
				$entryTemplate['Params']['Attachment log'][]=array('timestamp'=>time(),'Params|File|Source'=>array('old'=>$sourceFile,'new'=>$targetFile,'userId'=>$userId));
			}
			$this->arr['SourcePot\Datapool\Foundation\Database']->deleteEntries(array('Source'=>$sourceEntry['Source'],'EntryId'=>$sourceEntry['EntryId']));
		}
		return $this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($targetEntry);
	}
	
	public function moveEntryByEntryId($entry,$targetSelector){
		$entryFileName=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($entry);
		$targetFileName=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($targetSelector);
		// backup an existing entry file with EntryId equal to $targetSelector at the tmp dir 
		$return=$this->entryById($targetSelector);
		if (!empty($return)){
			$return['File']=$this->arr['SourcePot\Datapool\Foundation\Filespace']->getTmpDir().__FUNCTION__.'.file';
			@rename($targetFileName,$return['File']);
		}
		// move entry
		@rename($entryFileName,$targetFileName);
		$newEntry=$entry;
		$newEntry['EntryId']=$targetSelector['EntryId'];
		$this->updateEntry($newEntry);
		$this->deleteEntries($entry);
		return $return;
	}
	
	public function swapEntriesByEntryId($entryA,$entryB){
		$entryB=$this->moveEntryByEntryId($entryA,$entryB);
		if (!empty($entryB)){
			$entryB['EntryId']=$entryA['EntryId'];
			$entryBfileName=$this->arr['SourcePot\Datapool\Foundation\Filespace']->selector2file($entryB);
			@rename($entryB['File'],$entryBfileName);
			$this->updateEntry($entryB);
		}
		return $entryB;
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
		foreach($this->entryIterator($orderedListSelector,FALSE,'Read','EntryId',TRUE) as $entry){
			$targetEntryId=$this->addOrderedListIndexToEntryId($entry['EntryId'],$targetIndex);
			if (strcmp($entry['EntryId'],$targetEntryId)!==0){
				$this->moveEntryByEntryId($entry,array('Source'=>$selector['Source'],'EntryId'=>$targetEntryId));
			}
			$debugArr['stpes'][]=array('targetIndex'=>$targetIndex,'entry EntryId'=>$entry['EntryId'],'target EntryId'=>$targetEntryId);
			$targetIndex++;
		}
		if ($isDebugging){
			if (isset($_SESSION[__CLASS__][__FUNCTION__]['callCount'])){$_SESSION[__CLASS__][__FUNCTION__]['callCount']++;} else {$_SESSION[__CLASS__][__FUNCTION__]['callCount']=1;}
			$this->arr['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr,'DebugArr '.__FUNCTION__.'-'.$_SESSION[__CLASS__][__FUNCTION__]['callCount'].'-');
		}
		return TRUE;
	}
	
	public function moveEntry($selector,$moveUp=TRUE){
		// This method requires column EntryId have the format [constant prefix]|[index]
		// The index range is: 1...index...rowCount
		$orderedListSelector=$this->orderedList2selector($selector);
		if (empty($orderedListSelector)){return FALSE;}
		$status=array('rowCount'=>0,'targetEntryId'=>FALSE,'selectedEntryId'=>FALSE,'entries'=>array());
		foreach($this->entryIterator($orderedListSelector,FALSE,'Read','EntryId',TRUE) as $entry){
			$status['rowCount']=$entry['rowCount'];
			$status['entries'][$entry['EntryId']]=$entry;
			if (strcmp($entry['EntryId'],$selector['EntryId'])!==0){continue;}
			$currentIndex=$this->getOrderedListIndexFromEntryId($entry['EntryId']);
			if ($moveUp){
				if ($currentIndex<$entry['rowCount']){$targetIndex=$currentIndex+1;} else {return TRUE;}
			} else {
				if ($currentIndex>1){$targetIndex=$currentIndex-1;} else {return TRUE;}
			}
			$key=$this->getOrderedListKeyFromEntryId($entry['EntryId']);
			$targetSelector=array('Source'=>$selector['Source']);
			$targetSelector['EntryId']=$this->addOrderedListIndexToEntryId($key,$targetIndex);
			$this->swapEntriesByEntryId($entry,$targetSelector);
		}
		return TRUE;
	}

}
?>