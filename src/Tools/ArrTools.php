<?php
declare(strict_types=1);

namespace Datapool\Tools;

class ArrTools{
	
	private $arr;
	private $recursionResult;
    
	const ONEDIMSEPARATOR='|[]|';
	
	public function __construct($arr){
		$this->arr=$arr;
	}
	
	public function init($arr){
		$this->arr=$arr;
		return $this->arr;
	}
	
	public function getSeparator(){return self::ONEDIMSEPARATOR;}
	
	public function arr2file($inArr,$fileName=FALSE,$addDateTime=FALSE){
		/*	This function converts t$inArr to json format and saves the json data to a file. 
		*	If the fileName argument is empty, it will be created from the name of the calling class and function.
		*	The function returns the byte count written to the file or false in case of an error.
		*/
		if (empty($fileName)){
			$trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
			$fileName='';
			if ($addDateTime){$fileName.=date('Y-m-d h_m_s').' ';}
			$fileName.=$trace[1]['class'].' '.$trace[1]['function'];
			$fileName=mb_ereg_replace("[^A-Za-z0-9\-\_ ]",'_', $fileName);
			$file=$GLOBALS['base dir'].'debugging/'.$fileName.'.json';
		} else if (strpos($fileName,'/')===FALSE && strpos($fileName,'\\')===FALSE){
			$fileName=mb_ereg_replace("[^A-Za-z0-9\-\_ ]",'_', $fileName);
			$file=$GLOBALS['base dir'].'debugging/'.$fileName.'.json';
		} else {
			$file=$fileName;
		}
		$json=$this->arr2json($inArr);
		return file_put_contents($file,$json);
	}
		
	public function arr2json($arr){
		return json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_IGNORE);
	}
	
	public function json2arr($json){
		$arr=json_decode($json,TRUE,512,JSON_INVALID_UTF8_IGNORE);
		if (empty($arr)){$arr=json_decode(stripslashes($json),TRUE,512,JSON_INVALID_UTF8_IGNORE);}
		return $arr;
	}
	
	// flattend-array functions
	public function arr2flat($arr){
		if (!is_array($arr)){return $arr;}
		$flat=array();
		$this->arr2flatHelper($arr,$flat);
		return $flat;
	}
	
	private function arr2flatHelper($arr,&$flat,$oldKey=''){
		$result=array();
		foreach ($arr as $key=>$value){
			if (strlen(strval($oldKey))===0){$newKey=$key;} else {$newKey=$oldKey.self::ONEDIMSEPARATOR.$key;}
			if (is_array($value)){
				$result[$newKey]=$this->arr2flatHelper($value,$flat,$newKey); 
			} else {
				$result[$newKey]=$value;
				$flat[$newKey]=$value;
			}
		}
		return $result;
	}
	
	public function flat2arr($arr){
		if (!is_array($arr)){return $arr;}
		$result=array();
		foreach($arr as $key=>$value){
			$k=explode(self::ONEDIMSEPARATOR,$key);
			$dim=count($k);
			if ($dim===1){
				$result[$k[0]]=$value;
			} else if ($dim===2){
				$result[$k[0]][$k[1]]=$value;
			} else if ($dim===3){
				$result[$k[0]][$k[1]][$k[2]]=$value;
			} else if ($dim===4){
				$result[$k[0]][$k[1]][$k[2]][$k[3]]=$value;
			} else if ($dim===5){
				$result[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]]=$value;
			} else if ($dim===6){
				$result[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]][$k[5]]=$value;
			} else if ($dim===7){
				$result[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]][$k[5]][$k[6]]=$value;
			} else if ($dim===8){
				$result[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]][$k[5]][$k[6]][$k[7]]=$value;
			} else if ($dim===9){
				$result[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]][$k[5]][$k[6]][$k[7]][$k[8]]=$value;
			} else if ($dim===10){
				$result[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]][$k[5]][$k[6]][$k[7]][$k[8]][$k[9]]=$value;
			} else if ($dim===11){
				$result[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]][$k[5]][$k[6]][$k[7]][$k[8]][$k[9]][$k[10]]=$value;
			} else if ($dim===12){
				$result[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]][$k[5]][$k[6]][$k[7]][$k[8]][$k[9]][$k[10]][$k[11]]=$value;
			} else if ($dim===13){
				$result[$k[0]][$k[1]][$k[2]][$k[3]][$k[4]][$k[5]][$k[6]][$k[7]][$k[8]][$k[9]][$k[10]][$k[11]][$k[12]]=$value;
			} else {
				$this->arr['Datapool\Tools\ArrTools']->arr2file($arr);
				throw new \ErrorException('Function '.__FUNCTION__.' called with associative array with too high dimension ('.$dim.')',0,E_ERROR,__FILE__,__LINE__);
			}
		}
		return $result;
	}	
	
	public function arrDeleteKeyByFlatKey($arr,$flatKey){
		$flatArr=$this->arr2flat($arr);
		foreach($flatArr as $arrKey=>$arrValue){
			if (strpos($arrKey,$flatKey)===FALSE){continue;}
			unset($flatArr[$arrKey]);
		}
		$arr=$this->flat2arr($flatArr);
		return $arr;
	}
	
	public function arrUpdateKeyByFlatKey($arr,$flatKey,$value){
		$flatArr=$this->arr2flat($arr);
		$flatArr[$flatKey]=$value;
		$arr=$this->flat2arr($flatArr);
		return $arr;	
	}
	
	public function arrMerge($arrA,$arrB){
		$flatArrA=$this->arr2flat($arrA);
		$flatArrB=$this->arr2flat($arrB);
		$flatArr=array_merge($flatArrA,$flatArrB);
		$arr=$this->flat2arr($flatArr);
		return $arr;	
	}
	
	public function flatKey2label($key){
		return str_replace(self::ONEDIMSEPARATOR,' &rarr; ',$key);
	}
	
	public function statistic2str($statistic){
		$str=array();
		foreach($statistic as $key=>$value){
			if (is_array($value)){
				$str[]=$key.': '.implode(', ',$value);
			} else {
				$str[]=$key.'='.$value;
			}
		}
		return implode(' | ',$str);
	}
	
	public function arr2matrix($arr){
		//$this->arr['Datapool\Tools\ArrTools']->arr2file($arr);
		$matrix=array();
		$rowIndex=0;
		$rows=array();
		$maxColumnCount=0;
		foreach($this->arr2flat($arr) as $flatKey=>$value){
			$columns=explode(self::ONEDIMSEPARATOR,$flatKey);
			$columnCount=count($columns);
			$rows[$rowIndex]=array('columns'=>$columns,'value'=>$value);
			if ($columnCount>$maxColumnCount){$maxColumnCount=$columnCount;}
			$rowIndex++;
		}
		foreach($rows as $rowIndex=>$rowArr){
			for($i=0;$i<$maxColumnCount;$i++){
				$key='';
				if (isset($rowArr['columns'][$i])){
					if ($rowIndex===0 ){
						$key=$rowArr['columns'][$i];
					} else if (isset($rows[($rowIndex-1)]['columns'][$i])){
						if (strcmp($rows[($rowIndex-1)]['columns'][$i],$rowArr['columns'][$i])===0){
							$key='&#10149;';
						} else {
							$key=$rowArr['columns'][$i];
						}
					} else {
						$key=$rowArr['columns'][$i];
					}
				}
				$matrix[$rowIndex][$i]=$key;
			}
			$matrix[$rowIndex]['value']=$rowArr['value'];
		}
		return $matrix;
	}
	
	public function addArrValuesKeywise(...$arrays){
		// This method adds same key values of the arrays provided and returns the resulting array.
		$result=array();
		array_walk_recursive($arrays,function($item,$key) use (&$result){
			$result[$key]=isset($result[$key])?intval($item)+intval($result[$key]):intval($item);
		});
		return $result;
	}
		
	public function unifyEntry($entry){
		// This function selects the $entry-specific unifyEntry() function based on $entry['Source']
		// If the $entry-specific unifyEntry() function is found it will be used to unify the entry.
		$this->arr['Datapool\Foundation\Database']->resetStatistic();
		$className=ucfirst($entry['Source']);
		foreach($this->arr['registered methods']['unifyEntry'] as $classWithNamespace=>$return){
			if (strpos($classWithNamespace,$className)===FALSE){continue;}
			$class=$classWithNamespace;
			break;
		}
		if (empty($class)){
			return $entry;	
		} else {
			return $this->arr[$class]->unifyEntry($entry);
		}
	}
	
	public function stdReplacements($arr,$flatKeyMapping=array(),$isDebugging=FALSE){
		// this method maps array-keys found in flatKeyMapping-argument to new array-keys.
		// On array-values class 'Datapool\Tools\StrTools' method 'stdReplacements' will be called
		// If different source keys mapped on the syame target key, the string-value will be added to the end of the existing string-value with a space character in between.
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
				$result[$flatResultKey]=$this->arr['Datapool\Tools\StrTools']->stdReplacements($value);
			} else {
				$result[$flatResultKey].=' '.$this->arr['Datapool\Tools\StrTools']->stdReplacements($value);
			}
		}
		$result=$this->flat2arr($result);
		if ($isDebugging){
			$debugArr['result']=$result;
			$this->arr['Datapool\Tools\ArrTools']->arr2file($debugArr);
		}
		return $result;
	}
	
}
?>