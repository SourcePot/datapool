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

class MiscTools{

    const ONEDIMSEPARATOR='|[]|';
    const GUIDEINDICATOR='!GUIDE';

    public $emojis=array();
    private $emojiFile='';
    
    private $oc=NULL;
    
    public function __construct(){
        $this->emojiFile=$GLOBALS['dirs']['setup'].'emoji.json';
        $this->loadEmojis($this->emojiFile);
    }
    
    public function init(array $oc){
        $this->oc=$oc;
    }
        
    /******************************************************************************************************************************************
    * XML tools
    */

    public function arr2style($arr){
        $style='';
        foreach($arr as $property=>$value){
            $property=strtolower($property);
            if (strpos($property,'height')!==FALSE || strpos($property,'width')!==FALSE || strpos($property,'size')!==FALSE || strpos($property,'top')!==FALSE || strpos($property,'left')!==FALSE || strpos($property,'bottom')!==FALSE || strpos($property,'right')!==FALSE){
                if (is_numeric($value)){$value=strval($value).'px';} else {$value=strval($value);}
            }
            $style.=$property.':'.$value.';';
        }
        return $style;
    }
    
    public function style2arr($style){
        $arr=array();
        $styleChunks=explode(';',$style);
        while($styleChunk=array_shift($styleChunks)){
            $styleDef=explode(':',$styleChunk);
            if (count($styleDef)!==2){continue;}
            $arr[$styleDef[0]]=$styleDef[1];
        }
        return $arr;
    }
    

    /*  Many thanks to 
    *   http://php.net/manual/en/class.simplexmlelement.php#108867
    */
    private function normalize_xml2array($obj,&$result){
        $data=$obj;
        if (is_object($data)){
            $data=get_object_vars($data);
            foreach($obj->getDocNamespaces() as $ns_name=>$ns_uri){
                if ($ns_name===''){continue;}
                $ns_obj=$obj->children($ns_uri);
                foreach(get_object_vars($ns_obj) as $k=>$v){
                    $data[$ns_name.':'.$k]=$v;
                }
            }
        }
        if (is_array($data)){
            foreach ($data as $key=>$value){
                $res=null;
                $this->normalize_xml2array($value,$res);
                $result[$key]=$res;
            }
        } else {
            $result=$data;
        }
    }
    
    public function xml2arr($xml) {
        $arr=array('xml'=>$xml);
        if (extension_loaded('SimpleXML')){
            $this->normalize_xml2array(simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA),$result);
            $json=json_encode($result);
            return json_decode($json,TRUE);
        } else {
            throw new \ErrorException('Function '.__FUNCTION__.': PHP extension SimpleXML missing.',0,E_ERROR,__FILE__,__LINE__);
            return FALSE;
        }
    }
    
    public function arr2xml($arr,$rootElement=NULL,$xml=NULL){
        if ($xml===NULL){
            $xml=new \SimpleXMLElement($rootElement===NULL?'<root/>':$rootElement);
        }
        foreach($arr as $key=>$value){
            if (is_array($value)){
                $this->arr2xml($value,$key,$xml->addChild($key));
            } else {
                $xml->addChild($key,$value);
            }
        }
        return $xml->asXML();
    }
    
    public function containsTags($str){
        if (strlen($str)===strlen(strip_tags($str))){return FALSE;} else {return TRUE;}
    }
    
    public function wrapUTF8($str){
        preg_match_all("/[\x{1f000}-\x{1ffff}]/u",$str,$matches);
        foreach($matches[0] as $matchIndex=>$match){
            $str=str_replace($match,'<span class="emoji">'.$match.'</span>',$str);
        }
        return $str;
    }
    
    public function str2bool($str){
        if (is_bool($str)){
            return $str;
        } else if (is_numeric($str)){
            return boolval(intval($str));
        } else {
            return !empty($str);
        }
    }
    
    public function bool2element($value,$element=array()){
        $boolval=$this->str2bool($value);
        $element['class']=$boolval?'status-on':'status-off';
        if (!isset($element['element-content'])){$element['element-content']=$boolval?'TRUE':'FALSE';}
        if (!isset($element['tag'])){$element['tag']='p';}
        return $element;
    }
    
    /******************************************************************************************************************************************
    * String tools
    */
    
    public function startsWithUpperCase($str){
        $startChr=mb_substr($str,0,1,"UTF-8");
        return mb_strtolower($startChr,"UTF-8")!=$startChr;
    }

    public function base64decodeIfEncoded($str){
        $decoded=base64_decode($str,TRUE);
        if (empty($decoded)){return $str;}
        $encoded=base64_encode($decoded);
        if ($encoded===$str){
            return $decoded;
        } else {
            return $str;
        }
    }
    
    public function getRandomString($length){
        $hash='';
        $byteStr=random_bytes($length);
        for ($i=0;$i<$length;$i++){
            $byte=ord($byteStr[$i]);
            if ($byte>180){
                $hash.=chr(97+($byte%26));
            } else if ($byte>75){
                $hash.=chr(65+($byte%26));
            } else {
                $hash.=chr(48+($byte%10));
            }
        }
        return $hash;
    }

    public function getHash($arr,$short=FALSE){
        if (is_array($arr)){$hash=json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_IGNORE);} else {$hash=strval($arr);}
        $hash=hash('sha256',$hash);
        if (!empty($short)){
            // short hash
            $hash=base_convert($hash,16,32);
            $hash=str_replace('0','',$hash);
            $hash=str_replace('|','x',$hash);
        }
        return $hash;
    }    
    
    public function getEntryId($base=FALSE,$timestamp=FALSE){
        //    Creates and returns the unique EntryId
        if ($base){$suffix=$this->getHash($base,TRUE);} else {$suffix=mt_rand(100000,999999);}
        if ($timestamp===FALSE){
            $timestamp=time();
        } else {
            $timestamp=$timestamp;
        }
        $entryId="EID".$timestamp.'-'.$suffix."eid";
        return $entryId;
    }
    
    public function getEntryIdAge($entryId){
        // Returns the age of a provided EntryId
        if (strpos($entryId,'eid')===FALSE || strpos($entryId,'EID')===FALSE){return 0;}
        $timestamp=substr($entryId,3,strpos($entryId,'-')-1);
        $timestamp=intval($timestamp);
        return time()-$timestamp;
    }
    
    public function addEntryId($entry,$relevantKeys=array('Source','Group','Folder','Name','Type'),$timestampToUse=FALSE,$suffix='',$keepExistingEntryId=FALSE){
        if (!empty($entry['EntryId']) && $keepExistingEntryId){return $entry;}
        $base=array();
        foreach($relevantKeys as $keyIindex=>$relevantKey){
            if (isset($entry[$relevantKey])){$base[]=$entry[$relevantKey];}
        }
        if ($timestampToUse===FALSE){
            if (empty($entry['Date'])){
                $timestamp=time();
            } else {
                $timestamp=strtotime($entry['Date']);
            }
        } else {
            $timestamp=$timestampToUse;
        }
        $entry['EntryId']=$this->getEntryId($base,$timestamp);
        if (!empty($suffix)){$entry['EntryId'].=$suffix;}
        return $entry;
    }

    public function getDateTime($datetime='now',$addDateInterval=FALSE,$timezone=FALSE){
        // This is the standard method to get a formated date-string.
        // It returns the date based on the selected timezone.
        $dateTime=new \DateTime($datetime);
        if (!empty($addDateInterval)){
            $dateTime->add(new \DateInterval($addDateInterval));
        }
        if (empty($timezone)){
            $timezone=$this->oc['SourcePot\Datapool\Foundation\Database']->getDbTimezone();
        }
        $dateTime->setTimezone(new \DateTimeZone($timezone));
        return $dateTime->format('Y-m-d H:i:s');
    }
    
    public function code2utf($code){
        if($code<128)return chr($code);
        if($code<2048)return chr(($code>>6)+192).chr(($code&63)+128);
        if($code<65536)return chr(($code>>12)+224).chr((($code>>6)&63)+128).chr(($code&63)+128);
        if($code<2097152)return chr(($code>>18)+240).chr((($code>>12)&63)+128).chr((($code>>6)&63)+128) .chr(($code&63)+128);
        return '';
    }
    
    private function emojiList2file(){
        //$html=file_get_contents('https://unicode.org/emoji/charts/full-emoji-list.html');
        $html=file_get_contents('D:/Full Emoji List, v15.0.htm');
        if (empty($html)){return FALSE;}
        $result=array();
        $rows=explode('</tr>',$html);
        while($row=array_shift($rows)){
            $startPos=strpos($row,'<tr');
            if ($startPos===FALSE){continue;}
            $row=substr($row,$startPos);
            if (strpos($row,'</th>')===FALSE){
                // is content row
                $cells=explode('</td>',$row);
                foreach($cells as $cellIndex=>$cell){
                    if (stripos($cell,'"code"')!==FALSE){
                        $cell=strtolower(strip_tags($cell));
                        $cell=trim($cell);
                        $key2arr=explode(' ',$cell);
                    }
                    if (stripos($cell,'"name"')!==FALSE){
                        foreach($key2arr as $key2index=>$key2){
                            $key2=trim($key2,'u+');
                            $key2=hexdec($key2);
                            $result[$key0][$key1][$key2]=html_entity_decode(trim(strip_tags($cell)));
                        }
                    }
                }
            } else {
                // is key row
                if (stripos($row,'"bighead"')!==FALSE){
                    $key0=html_entity_decode(strip_tags($row));
                } else if (stripos($row,'"mediumhead"')!==FALSE){
                    $key1=html_entity_decode(strip_tags($row));    
                }
            }
        }
        $this->arr2file($result,$this->emojiFile);
        return $result;
    }

    private function loadEmojis($emojiFile){
        if (is_file($emojiFile)){
            $json=file_get_contents($emojiFile);
            $this->emojis=$this->json2arr($json);
        } else {
            $this->emojis=$this->emojiList2file();
        }
        
    }

    public function float2str($float,$prec=3,$base=1000){
        // Thanks to "c0x at mail dot ru" based on https://www.php.net/manual/en/function.log.php
        $float=floatval($float);
        $e=array('a','f','p','n','u','m','','k','M','G','T','P','E');
        $p=min(max(floor(log(abs($float), $base)),-6),6);
        $value=round((float)$float/pow($base,$p),$prec);
        if ($value==0){
            return $value;
        } else {
            return $value.$e[$p+6];
        }
    }
    
    public function var2color($var,$colorScheme=0,$light=FALSE,$decimal=TRUE){
        $colorArr=array();
        $hash=$this->getHash($var);
        $colorValuesArr=str_split($hash,2);
        for($index=0;$index<3;$index++){
            $colorArr[$index]=$colorValuesArr[$index+$colorScheme];
            $colorArr[$index]=intval(0.6*hexdec($colorArr[$index]));
            if ($light){$colorArr[$index]=255-$colorArr[$index];}
            if (!$decimal){$colorArr[$index]=dechex($colorArr[$index]);}
        }
        if ($decimal){
            return 'rgb('.implode(',',$colorArr).')';
        } else {
            return '#'.implode('',$colorArr);    
        }
    }

    /******************************************************************************************************************************************
    * Array tools
    */
    
    public function add2history($arr,$newElement,$maxSize=10){
        if (is_array($arr)){
            array_unshift($arr,$newElement);
        } else {
            $arr=array($newElement);
        }
        while(count($arr)>$maxSize){
            array_pop($arr);
        }
        return $arr;
    }

    public function getSeparator(){return self::ONEDIMSEPARATOR;}
    
    public function arr2selector($arr,$defaultValues=array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE,'Type'=>FALSE,'app'=>'')){
        $selector=array();
        foreach($defaultValues as $key=>$defaultValue){
            //$selector[$key]=(isset($arr[$key]))?$arr[$key]:$defaultValue;
            $selector[$key]=(empty($arr[$key]))?$defaultValue:$arr[$key];
            $selector[$key]=(strpos(strval($selector[$key]),self::GUIDEINDICATOR)===FALSE)?$selector[$key]:FALSE;
        }
        return $selector;
    }
    
    public function selectorAfterDeletion($selector,$columns=array('Source','Group','Folder','Name','EntryId')){
        $unselectedColumnSelected=FALSE;
        $lastColumn='Source';
        $newSelector=array('app'=>(isset($selector['app'])?$selector['app']:''));
        foreach($columns as $column){
            if ($lastColumn==='Source'){
                $unselectedColumnSelected=FALSE;
            } else if (!isset($selector[$column])){
                $unselectedColumnSelected=TRUE;
            } else if ($selector[$column]===FALSE){
                $unselectedColumnSelected=TRUE;
            } else if (strcmp(strval($selector[$column]),self::GUIDEINDICATOR)===0){
                $unselectedColumnSelected=TRUE;
            }
            if ($unselectedColumnSelected){
                $newSelector[$lastColumn]=FALSE;
            } else {
                $newSelector[$lastColumn]=isset($selector[$lastColumn])?$selector[$lastColumn]:FALSE;
            }
            $lastColumn=$column;
        }
        return $newSelector;
    }    
    
    public function arr2file($inArr,$fileName=FALSE,$addDateTime=FALSE){
        /*   This function converts t$inArr to json format and saves the json data to a file. 
        *    If the fileName argument is empty, it will be created from the name of the calling class and function.
        *    The function returns the byte count written to the file or false in case of an error.
        */
        if (empty($fileName)){
            $trace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
            $fileName='';
            if ($addDateTime){$fileName.=date('Y-m-d H_i_s').' ';}
            $fileName.=$trace[1]['class'].' '.$trace[1]['function'];
            $fileName=mb_ereg_replace("[^A-Za-z0-9\-\_ ]",'_', $fileName);
            $file=$GLOBALS['dirs']['debugging'].'/'.$fileName.'.json';
        } else if (strpos($fileName,'/')===FALSE && strpos($fileName,'\\')===FALSE){
            $fileName=mb_ereg_replace("[^A-Za-z0-9\-\_ ]",'_', $fileName);
            $file=$GLOBALS['dirs']['debugging'].'/'.$fileName.'.json';
        } else {
            $file=$fileName;
        }
        $json=$this->arr2json($inArr);
        return file_put_contents($file,$json);
    }
        
    /**
    * @return string This method converts an array to the corresponding json string.
    */
    public function arr2json($arr){
        return json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_IGNORE);
    }
    
    /**
    * @return arr This method converts a json string to the corresponding array.
    */
    public function json2arr($json){
        if (is_string($json)){
            $arr=json_decode($json,TRUE,512,JSON_INVALID_UTF8_IGNORE);
            if (empty($arr)){$arr=json_decode(stripslashes($json),TRUE,512,JSON_INVALID_UTF8_IGNORE);}
            return $arr;
        } else {
            return array();
        }
    }
    
    /**
    * @return arr This method converts an array to the corresponding flat array.
    */
    public function arr2flat($arr,$S=self::ONEDIMSEPARATOR){
        if (!is_array($arr)){return $arr;}
        $flat=array();
        $this->arr2flatHelper($arr,$flat,'',$S);
        return $flat;
    }
    
    private function arr2flatHelper($arr,&$flat,$oldKey='',$S=self::ONEDIMSEPARATOR){
        $result=array();
        foreach ($arr as $key=>$value){
            if (strlen(strval($oldKey))===0){$newKey=$key;} else {$newKey=$oldKey.$S.$key;}
            if (is_array($value)){
                $result[$newKey]=$this->arr2flatHelper($value,$flat,$newKey,$S); 
            } else {
                $result[$newKey]=$value;
                $flat[$newKey]=$value;
            }
        }
        return $result;
    }
    
    /**
    * @return arr This method converts a flat array to the corresponding array.
    */
    public function flat2arr($arr,$S=self::ONEDIMSEPARATOR){
        if (!is_array($arr)){return $arr;}
        $result=array();
        foreach($arr as $key=>$value){
            $result=array_replace_recursive($result,$this->flatKey2arr($key,$value,$S));
        }
        return $result;
    }
    
    private function flatKey2arr($key,$value,$S=self::ONEDIMSEPARATOR){
        if (!is_string($key)){return array($key=>$value);}
        $k=explode($S,$key);
        while(count($k)>0){
            $subKey=array_pop($k);
            $value=array($subKey=>$value);
        }
        return $value;
    }
    
    /**
    * @return arr This method deletes a key-value-pair selecting the key by the corresponding flat key.
    */
    public function arrDeleteKeyByFlatKey($arr,$flatKey){
        $flatArr=$this->arr2flat($arr);
        foreach($flatArr as $arrKey=>$arrValue){
            if (strpos($arrKey,$flatKey)===FALSE){continue;}
            unset($flatArr[$arrKey]);
        }
        $arr=$this->flat2arr($flatArr);
        return $arr;
    }
    
    /**
    * @return arr This method updates a key-value-pair selecting the key by the corresponding flat key.
    */
    public function arrUpdateKeyByFlatKey($arr,$flatKey,$value){
        $flatArr=$this->arr2flat($arr);
        $flatArr[$flatKey]=$value;
        $arr=$this->flat2arr($flatArr);
        return $arr;    
    }
        
    /**
    * @return string This method returns a string representing the provided flat key for a web page.
    */
    public function flatKey2label($key,$S=self::ONEDIMSEPARATOR){
        return str_replace($S,' &rarr; ',$key);
    }
    
    /**
    * @return array This method returns an array representing last subkey value pairs
    */
    public function flatArrLeaves($flatArr,$S=self::ONEDIMSEPARATOR){
        $leaves=array();
        foreach($flatArr as $flatKey=>$flatValue){
            $leafKey=explode($S,$flatKey);
            $leafKey=array_pop($leafKey);
            $leaves[$leafKey]=$flatValue;
        }
        return $leaves;
    }


    /**
    * @return string This method returns a string for a web page created from a statistics array, e.g. array('matches'=>0,'updated'=>0,'inserted'=>0,'deleted'=>0,'removed'=>0,'file added'=>0)
    */
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
    
    /**
    * @return array This method returns an array which is a matrix used to create an html-table and a representation of the provided array.
    */
    public function arr2matrix($arr,$S=self::ONEDIMSEPARATOR){
        $matrix=array();
        $rowIndex=0;
        $rows=array();
        $maxColumnCount=0;
        foreach($this->arr2flat($arr) as $flatKey=>$value){
            $columns=explode($S,$flatKey);
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
    
    /**
    * @return array This method adds the values (if numeric mathmatically, if string ) with the same key of multiple arrays.
    */
    public function addArrValuesKeywise(...$arrays){
        // Example: Arguments "array('deleted'=>2,'inserted'=>1,'steps'=>'Open web page','done'=>FALSE)" and "array('deleted'=>0,'inserted'=>4,'steps'=>'Close web page','done'=>TRUE)"
        // will return array('deleted'=>2,'inserted'=>5,'steps'=>'Open web page|Close web page','done'=>TRUE)
        $result=array();
        array_walk_recursive($arrays,function($item,$key) use (&$result){
            if (is_numeric($item)){
                $result[$key]=isset($result[$key])?intval($item)+intval($result[$key]):intval($item);
            } else if (is_string($item)){
                $result[$key]=isset($result[$key])?$result[$key].'|'.$item:$item;
            } else {
                $result[$key]=$item;
            }
        });
        return $result;
    }

    /******************************************************************************************************************************************
    * Generic conversions
    */
    
    public function var2dataType($var){
        $strVar=strval($var);
        if (is_numeric($var)){
            if (strpos($strVar,'.')!==FALSE){
                $dataType='float';
            } else {
                $dataType='int';
            }
        } else if (is_bool($var)){
            $dataType='bool';
        } else {
            if (strcmp($strVar,'FALSE')===0 || strcmp($strVar,'TRUE')===0 || strcmp($strVar,'false')===0 || strcmp($strVar,'true')===0){
                $dataType='bool';
            } else {                
                $strVarComps=explode('-',$strVar);
                if (count($strVarComps)===3){
                    $dataType='date';
                    $strVarComps=explode(':',$strVarComps[2]);
                    if (count($strVarComps)===3){
                        $dataType.='Time';
                    }
                } else {
                    $dataType='string';
                }
            }
        }
        return $dataType;
    }

    public function convert($value,$dataType){
        $dataType=strtolower($dataType);
        $newValue=match($dataType){
                    'string'=>strval($value),
                    'stringnowhitespaces'=>$this->convert2stringNoWhitespaces($value),
                    'splitstring'=>$this->convert2splitString($value),
                    'int'=>$this->str2int($value),
                    'float'=>$this->str2float($value),
                    'bool'=>!empty($value),
                    'money'=>$this->oc['SourcePot\Datapool\Foundation\Money']->str2money($value),
                    'date'=>$this->oc['SourcePot\Datapool\GenericApps\Calendar']->str2date($value),
                    'codepfad'=>$this->convert2codepfad($value),
                    'unycom'=>$this->convert2unycom($value),
                };
        return $newValue;
    }

    public function str2int($string,$lang=''){
        $value=$this->str2float($string,$lang);
        return round($value);
    }
    
    public function str2float($string,$lang=''){
        $string=strval($string);
        $lang=strtolower($lang);
        // get number from string
        $string=preg_replace('/[^0-9\.\,\-]/','',$string);
        // validate language for number format
        $dotChunk=mb_strrchr($string,'.');
        $dotCount=mb_strlen($string)-mb_strlen(str_replace('.','',$string));
        if ($dotCount>1){
            // e.g. 1.234.456,78 -> 1234456,78
            $lang='de';
            $numberStr=str_replace('.','',$string);
        } else {
            $numberStr=$string;
        }
        $commaChunk=mb_strrchr($string,',');
        $commaCount=mb_strlen($string)-mb_strlen(str_replace(',','',$string));
        if ($commaCount>1){
            // e.g. 1,234,456.78 -> 1234456,78
            $lang='en';
            $numberStr=str_replace(',','',$string);
        } else {
            $numberStr=$string;
        }
        if ($dotCount===1 && $commaCount===1){
            if (mb_strlen($commaChunk)>mb_strlen($dotChunk)){
                // e.g. 1,234.56
                $lang='en';
            } else {
                // e.g. 1.234,56
                $lang='de';
            }
        } else if ($dotCount===1){
            // e.g. 1234.567
            if (mb_strlen($numberStr)>7 || empty($lang)){$lang='en';}
        } else if ($commaCount===1 && mb_strlen($numberStr)>7){
            // e.g. 1234,567
            if (mb_strlen($numberStr)>7 || empty($lang)){$lang='de';}
        }
        // convert to float based on number format
        if ($lang==='en'){
            $numberStr=str_replace(',','',$numberStr);
            return floatval($numberStr);
        } else {
            $numberStr=str_replace('.','',$numberStr);
            $numberStr=str_replace(',','.',$numberStr);
            return floatval($numberStr);
        }
    }
    public function convert2stringNoWhitespaces($value){
        $value=preg_replace("/\s/",'',strval($value));
        return $value;
    }

    public function convert2splitString($value){
        $value=strtolower(strval($value));
        $value=trim($value);
        $value=preg_split("/[^a-zäöü0-9ß]+/",$value);
        return $value;
    }
    
    public function convert2codepfad($value){
        $codepfade=explode(';',strval($value));
        $arr=array();
        foreach($codepfade as $codePfadIndex=>$codepfad){
            $codepfadComps=explode('\\',$codepfad);
            if ($codePfadIndex===0){
                if (isset($codepfadComps[0])){$arr['FhI']=$codepfadComps[0];}
                if (isset($codepfadComps[1])){$arr['FhI Teil']=$codepfadComps[1];}
                if (isset($codepfadComps[2])){$arr['Codepfad 3']=$codepfadComps[2];}
            } else {
                if (isset($codepfadComps[0])){$arr[$codePfadIndex]['FhI']=$codepfadComps[0];}
                if (isset($codepfadComps[1])){$arr[$codePfadIndex]['FhI Teil']=$codepfadComps[1];}
                if (isset($codepfadComps[2])){$arr[$codePfadIndex]['Codepfad 3']=$codepfadComps[2];}
            }
        }
        return $arr;
    }
    
    public function convert2unycom($value){
        $value=strval($value);
        $keyTemplate=array('Match','Year','Type','Number');
        $regions=array('WO'=>'PCT','WE'=>'Euro-PCT','EP'=>'European patent','EU'=>'Unitary Patent','AP'=>'ARIPO patent','EA'=>'Eurasian patent','OA'=>'OAPI patent');
        preg_match('/([0-9]\s*[0-9]\s*[0-9]\s*[0-9]|[0-9]\s*[0-9])(\s*[FPRZXM]{1,2})([0-9\s]{5,6})/',$value,$matches);
        if (empty($matches[0])){return array();}
        $arr=array_combine($keyTemplate,$matches);
        $arr['Region']='  ';
        $arr['Country']='  ';
        $arr['Part']='  ';
        $prefixSuffix=explode($matches[0],$value);
        if (!empty($prefixSuffix[1])){
            $suffix=preg_replace('/\s+/','',$prefixSuffix[1]);
            $suffix=strtoupper($suffix);
            $suffix=str_split($suffix,2);
            foreach($regions as $rc=>$region){
                if (strpos($suffix[0],$rc)!==0){continue;}
                array_shift($suffix);
                $arr['Region']=$rc;
                $arr['Region long']=$region;
                break;
            }
            if (!empty($suffix[0])){
                // get country codes from file
                $file=$GLOBALS['dirs']['setup'].'/countryCodes.json';
                if (is_file($file)){
                    $cc=file_get_contents($file);
                    $countries=json_decode($cc,TRUE,512,JSON_INVALID_UTF8_IGNORE);
                    foreach($countries as $alpha2code=>$countryArr){
                        if (strpos($suffix[0],$alpha2code)===FALSE){continue;}
                        $arr['Country']=$alpha2code;
                        $arr['Country long']=$countryArr['Country'];
                        break;
                    }
                }
                // get part
                $suffix=implode('',$suffix);
                $part=preg_replace('/[^0-9]+/','',$suffix);
                $nonNumericSuffix=str_replace($part,'',$suffix);
                if (strlen($part)<2){$part='0'.$part;}
                if (!empty($part)){$arr['Part']=$part;}
                if (empty($arr['Country long']) && strlen($nonNumericSuffix)>1){
                    // get country, if country is empty
                    $arr['Country']=substr($nonNumericSuffix,0,2);
                }
            }
        }
        foreach($keyTemplate as $key){$arr[$key]=preg_replace('/\s+/','',$arr[$key]);}
        if (strlen($arr['Year'])===2){
            if (intval($arr['Year'])<50){
                $arr['Year']='20'.$arr['Year'];
            } else {
                $arr['Year']='19'.$arr['Year'];
            }
        }
        $reference=$arr['Year'].$arr['Type'].$arr['Number'].$arr['Region'].$arr['Country'].$arr['Part'];
        $arr=array('Reference'=>$reference,'Full'=>$reference,'Family'=>$arr['Year'].'F'.$arr['Number'])+$arr;
        $arr['Prefix']=trim($prefixSuffix[0],'- ');
        if (!empty($arr['Prefix'])){$arr['Full']=$arr['Prefix'].' - '.$arr['Full'];}
        //$this->arr2file($arr);
        return $arr;
    }

}
?>