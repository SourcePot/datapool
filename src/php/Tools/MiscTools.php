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

final class MiscTools{

    //public const UNYCOM_REGEX='/([0-9]\s*[0-9]\s*[0-9]\s*[0-9]|[0-9]\s*[0-9])(\s*[FPRZXM]{1,2})([0-9\s]{5,6})/u';
    //public const UNYCOM_REGEX='/([0-9]{4})([XPEFMR]{1,2})([0-9]{5})(\s{0,2}|WO|WE|EP|AP|EA|OA)([A-Z ]{0,2})(\s{0,1}[0-9]{0,2})/u';
    public const UNYCOM_REGEX='/([0-9]{4})([XPEFMR]{1,2})([0-9]{5})([A-Z ]{0,4})([0-9 ]{0,2})/u';
    public $emojis=array();
    private $emojiFile='';
    
    private $oc=NULL;
    
    private $dataTypes=array('string'=>'&rarr; String','stringNoWhitespaces'=>'&rarr; String remove \s','stringWordChrsOnly'=>'&rarr; String remove \W',
                             'splitString'=>'&rarr; Split string','int'=>'&rarr; integer','float'=>'&rarr; float','fraction'=>'Fraction &rarr; float',
                             'bool'=>'&rarr; boolean','money'=>'&rarr; money','date'=>'&rarr; date','dateString'=>'&rarr; date, empty if invalid',
                             'excelDate'=>'Excel &rarr; date','timestamp'=>'Timestamp &rarr; date','shortHash'=>'&rarr; Hash (short)','hash'=>'&rarr; Hash',
                             'codepfad'=>'&rarr; codepfad','unycom'=>'&rarr; UNYCOM','unycomCountry'=>'&rarr; UNYCOM country','unycomRegion'=>'&rarr; UNYCOM region','unycomFallNoWhitspaces'=>'&rarr; UNYCOM reference no \s',
                             'userIdNameComma'=>'UserId &rarr; Name, First name','userIdName'=>'UserId &rarr; First name Name','useridemail'=>'UserId &rarr; email',
                             'userIdPhone'=>'UserId &rarr; phone','userIdMobile'=>'UserId &rarr; mobile',
                             );
    
    private $conditionTypes=array('empty'=>'empty','!empty'=>'not empty','strpos'=>'contains','!strpos'=>'does not contain',
                                  '>'=>'>','='=>'=','!='=>'&ne;','<'=>'<',
                                  '&'=>'AND','|'=>'OR','^'=>'XOR','~'=>'Inverse',
                                  );
    
    private $matchTypes=array('identical'=>'Identical','contains'=>'Contains','unycom'=>'UNYCOM Case','|'=>'Separated by |','number'=>'Numbers');
    
    private $combineOptions=array('overwrite'=>'Overwrite','addFloat'=>'float(A + B)','chainPipe'=>'string(A|B)','chainComma'=>'string(A, B)','chainSemicolon'=>'string(A; B)');
    
    public function __construct()
    {
        $this->emojiFile=$GLOBALS['dirs']['setup'].'emoji.json';
        $this->loadEmojis($this->emojiFile);
    }
 
    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }
   
    public function init()
    {
        // add calendar placeholder
        $this->oc['SourcePot\Datapool\Root']->addPlaceholder('{{EntryId}}',$this->getEntryId());
        $this->oc['SourcePot\Datapool\Root']->addPlaceholder('{{Expires}}',$this->getDateTime('now','PT10M'));
    }
    
    public function getDataTypes():array
    {
        return $this->dataTypes;
    }
    
    public function getConditions():array
    {
        return $this->conditionTypes;
    }
    
    public function getMatchTypes():array
    {
        return $this->matchTypes;
    }
    public function getCombineOptions():array
    
    {
        return $this->combineOptions;
    }
    
    /******************************************************************************************************************************************
    * XML tools
    */

    
    /**
    * This method returns the value from an attribute string, e.g. style="float:left;clear:right;" returns float:left;clear:right;
    *
    * @param $attr Is the attr string  
    * @return string Value contained in the attribute string
    */
    public function attr2value($attr):string
    {
        if (empty($attr)){return '';}
        $attrComps=explode('="',trim($attr,'"'));
        return array_pop($attrComps);
    }

    /**
    * This method returns a tag style definition from an array, e.g. array('float'=>'left','clear'=>'right') returns float:left;clear:right; 
    *
    * @param array $arr Is the style array  
    * @return string The style string
    */
    public function arr2style(array $arr):string
    {
        $style='';
        foreach($arr as $property=>$value){
            $property=mb_strtolower($property);
            if (mb_strpos($property,'height')!==FALSE || mb_strpos($property,'width')!==FALSE || mb_strpos($property,'size')!==FALSE || mb_strpos($property,'top')!==FALSE || mb_strpos($property,'left')!==FALSE || mb_strpos($property,'bottom')!==FALSE || mb_strpos($property,'right')!==FALSE){
                if (is_numeric($value)){$value=strval($value).'px';} else {$value=strval($value);}
            }
            $style.=$property.':'.$value.';';
        }
        return $style;
    }
    
    /**
    * This method returns an array from a tag style definition, e.g. float:left;clear:right; returns array('float'=>'left','clear'=>'right')
    *
    * @param string $arr Is tag style definition string  
    * @return array The style array
    */
    public function style2arr(string $style):array
    {
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
    private function normalize_xml2array($obj,&$result)
    {
        $data=$obj;
        if (is_object($data)){
            $attr=current($data->attributes());
            $data=get_object_vars($data);
            if ($attr){
                $data=array_merge($attr,$data);
            }
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
    
    public function xml2arr(string $xml):array|bool
    {
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
    
    public function arr2xml(array $arr,$rootElement=NULL,$xml=NULL)
    {
        if ($xml===NULL){
            $xml=new \SimpleXMLElement($rootElement===NULL?'<root/>':$rootElement);
        }
        foreach($arr as $key=>$value){
            $key=strval($key);
            if (is_array($value)){
                $this->arr2xml($value,$key,$xml->addChild($key));
            } else {
                $xml->addChild($key,strval($value));
            }
        }
        return $xml->asXML();
    }
    
    public function containsTags(string $str):bool
    {
        if (strlen($str)===strlen(strip_tags($str))){return FALSE;} else {return TRUE;}
    }
    
    public function wrapUTF8(string $str):string
    {
        $str=preg_replace("/([\x{1f000}-\x{1ffff}])/u",' ${1} ',$str);
        $str=preg_replace("/(\s)([\x{1f000}-\x{1ffff}])(\s)/u",'<span class="emoji">${2}</span>',$str);
        return $str;
    }
    
    public function str2bool($str):bool
    {
        if (is_bool($str)){
            return $str;
        } else if (is_numeric($str)){
            return boolval(intval($str));
        } else {
            return !empty($str);
        }
    }
    
    public function bool2element($value,array $element=array(),bool $invertColors=FALSE):array
    {
        $boolval=$this->str2bool($value);
        $element['class']=($boolval xor $invertColors)?'status-on':'status-off';
        if (!isset($element['element-content'])){$element['element-content']=$boolval?'TRUE':'FALSE';}
        if (!isset($element['tag'])){$element['tag']='p';}
        return $element;
    }
    
    /******************************************************************************************************************************************
    * String tools
    */
    
    public function explode($string,$delimiter):array
    {
        $S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
        $string=preg_replace('/(".+)('.$delimiter.')(.+")/u','$1'.$S.'$3',$string);
        $comps=explode($delimiter,$string);
        foreach($comps as $index=>$comp){
            $comps[$index]=str_replace($S,$delimiter,$comp);
            $comps[$index]=str_replace('\s+',' ',$comps[$index]);
        }
        return $comps;
    }
    
    public function startsWithUpperCase(string $str):bool
    {
        $startChr=mb_substr($str,0,1,"UTF-8");
        return mb_strtolower($startChr,"UTF-8")!=$startChr;
    }

    public function base64decodeIfEncoded(string $str):string
    {
        $decoded=base64_decode($str,TRUE);
        if (empty($decoded)){return $str;}
        $encoded=base64_encode($decoded);
        if ($encoded===$str){
            return $decoded;
        } else {
            return $str;
        }
    }
    
    public function getRandomString(int $length):string
    {
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

    public function getHash($arr,bool $short=FALSE):string
    {
        if (is_array($arr)){
            $hash=json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_IGNORE);
        } else {
            $hash=strval($arr);
        }
        $hash=hash('sha256',$hash);
        if (!empty($short)){
            // short hash
            $hash=base_convert($hash,16,32);
            $hash=str_replace('0','',$hash);
            $hash=str_replace('|','x',$hash);
        }
        return $hash;
    }    
    
    public function getEntryId($base=FALSE,$timestamp=FALSE):string
    {
        //    Creates and returns the unique EntryId
        if ($base){
            $suffix=$this->getHash($base,TRUE);
        } else {
            $suffix=mt_rand(100000,999999);
        }
        if ($timestamp===FALSE){
            $timestamp=time();
        } else {
            $timestamp=$timestamp;
        }
        $entryId="EID".$timestamp.'-'.$suffix."eid";
        return $entryId;
    }
    
    public function getEntryIdAge(string $entryId):int
    {
        // Returns the age of a provided EntryId
        if (mb_strpos($entryId,'eid')===FALSE || mb_strpos($entryId,'EID')===FALSE){return 0;}
        $timestamp=mb_substr($entryId,3,mb_strpos($entryId,'-')-1);
        $timestamp=intval($timestamp);
        return time()-$timestamp;
    }
    
    public function addEntryId(array $entry,array $relevantKeys=array('Source','Group','Folder','Name'),$timestampToUse=FALSE,string $suffix='',bool $keepExistingEntryId=FALSE):array
    {
        if (!empty($entry['EntryId']) && $keepExistingEntryId){return $entry;}
        $base=array();
        foreach($relevantKeys as $keyIindex=>$relevantKey){
            if (isset($entry[$relevantKey])){
                if ($entry[$relevantKey]!==FALSE){$base[]=$entry[$relevantKey];}
            }
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

    public function getDateTime(string $datetime='now',string $addDateInterval='',string $timezone='',string $format='Y-m-d H:i:s'):string
    {
        if ($datetime[0]==='@'){
            $timestamp=intval(trim($datetime,'@'));
            $dateTime=new \DateTime();
            $dateTime->setTimestamp($timestamp); 
        } else {
            $timezone=(empty($timezone))?(\SourcePot\Datapool\Root::DB_TIMEZONE):$timezone;
            $dateTime=new \DateTime($datetime,new \DateTimeZone($timezone));
        }
        if (!empty($addDateInterval)){
            $dateInterval=trim($addDateInterval,'-');
            if (strlen($dateInterval)<strlen($addDateInterval)){
                $dateTime->sub(new \DateInterval($dateInterval));
            } else {
                $dateTime->add(new \DateInterval($dateInterval));
            }
        }
        $dateTime->setTimeZone(new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
        return $dateTime->format($format);
    }
    
    public function code2utf(int $code):string
    {
        if($code<128)return chr($code);
        if($code<2048)return chr(($code>>6)+192).chr(($code&63)+128);
        if($code<65536)return chr(($code>>12)+224).chr((($code>>6)&63)+128).chr(($code&63)+128);
        if($code<2097152)return chr(($code>>18)+240).chr((($code>>12)&63)+128).chr((($code>>6)&63)+128) .chr(($code&63)+128);
        return '';
    }
    
    private function emojiList2file():array
    {
        //$html=file_get_contents('https://unicode.org/emoji/charts/full-emoji-list.html');
        $html=file_get_contents('D:/Full Emoji List, v15.0.htm');
        if (empty($html)){return FALSE;}
        $result=array();
        $rows=explode('</tr>',$html);
        while($row=array_shift($rows)){
            $startPos=mb_strpos($row,'<tr');
            if ($startPos===FALSE){continue;}
            $row=mb_substr($row,$startPos);
            if (mb_strpos($row,'</th>')===FALSE){
                // is content row
                $cells=explode('</td>',$row);
                foreach($cells as $cellIndex=>$cell){
                    if (stripos($cell,'"code"')!==FALSE){
                        $cell=mb_strtolower(strip_tags($cell));
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

    private function loadEmojis(string $emojiFile):void
    {
        if (is_file($emojiFile)){
            $json=file_get_contents($emojiFile);
            $this->emojis=$this->json2arr($json);
        } else {
            $this->emojis=$this->emojiList2file();
        } 
    }

    public function float2str($float,int $prec=3,int $base=1000):string
    {
        // Thanks to "c0x at mail dot ru" based on https://www.php.net/manual/en/function.log.php
        $float=floatval($float);
        $e=array('a','f','p','n','u','m','','k','M','G','T','P','E');
        $p=min(max(floor(log(abs($float), $base)),-6),6);
        $value=round((float)$float/pow($base,$p),$prec);
        if ($value==0){
            return strval($value);
        } else {
            return $value.' '.$e[$p+6];
        }
    }
    
    public function var2color($var,$colorScheme=0,$light=FALSE,$decimal=TRUE):string
    {
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
    
    public function formData2statisticlog($formData)
    {
        if (!empty($formData['cmd'])){
            $statistics=$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str();
            if ($statistics){
                $this->oc['logger']->log('info','Form processing "cmd={cmd}": {statistics}',array('cmd'=>key($formData['cmd']),'statistics'=>$statistics));    
            }
        }   
    }
    
    public function add2history($arr,array $newElement,int $maxSize=100):array
    {
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

    public function wasTouchedByClass(array $entry,string $classWithNamespace,bool $testRun=FALSE):bool
    {
        if (isset($entry['Params'][$classWithNamespace])){
            return TRUE;
        } else {
            $entry['Params'][$classWithNamespace]=array('user'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),'timestamp'=>time());
            if (!$testRun){$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);}
            return FALSE;
        }
    }

    public function getSeparator():string
    {
        return \SourcePot\Datapool\Root::ONEDIMSEPARATOR;
    }
    
    public function arr2selector(array $arr,array $defaultValues=array('Source'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE,'EntryId'=>FALSE,'app'=>'')):array
    {
        $selector=array();
        foreach($defaultValues as $key=>$defaultValue){
            $selector[$key]=(empty($arr[$key]))?$defaultValue:$arr[$key];
            $selector[$key]=(mb_strpos(strval($selector[$key]),\SourcePot\Datapool\Root::GUIDEINDICATOR)===FALSE)?$selector[$key]:FALSE;
        }
        return $selector;
    }
    
    /**
    * Creates a new selector from a deletion selector. Example:
    * $selector=array('Source'=>'SAP','Group'=>'Buchungen','Folder'=>'IP','Name'=>'Kanzlei XYZ')  
    * return array('Source'=>'SAP','Group'=>'Buchungen','Folder'=>'IP','Name'=>FALSE,'EntryId'=>FALSE)
    * 
    * @param    array   $selector   Is an selector to select entries or an entry 
    * @return   array   The new selector
    */
    public function selectorAfterDeletion(array $selector,array $columns=array('Source','Group','Folder','EntryId','VOID')):array
    {
        $lastColumn='Source';
        $newSelector=array('app'=>(isset($selector['app'])?$selector['app']:''));
        foreach($columns as $column){
            $newSelector[$column]=(isset($selector[$column]))?$selector[$column]:FALSE;
            if ($newSelector[$column]==\SourcePot\Datapool\Root::GUIDEINDICATOR || $newSelector[$column]===FALSE){
                $newSelector[$column]=FALSE;
                $newSelector[$lastColumn]=FALSE;
                break;
            }
            $lastColumn=$column;
        }
        return $newSelector;
    }    
    
    public function arr2file(array $inArr,string $fileName='',bool $addDateTime=FALSE):int|bool
    {
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
        } else if (mb_strpos($fileName,'/')===FALSE && mb_strpos($fileName,'\\')===FALSE){
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
    public function arr2json(array $arr):string
    {
        return json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_IGNORE);
    }
    
    /**
    * @return arr This method converts a json string to the corresponding array.
    */
    public function json2arr(string $json):array
    {
        if (is_string($json)){
            $arr=json_decode($json,TRUE,512,JSON_INVALID_UTF8_IGNORE);
            if (empty($arr)){
                $arr=json_decode(stripslashes($json),TRUE,512,JSON_INVALID_UTF8_IGNORE);
            }
            if (empty($arr)){
                return array();
            } else {
                return $arr;
            }
        } else {
            return array();
        }
    }
    
    /**
    * @return arr This method converts an array to the corresponding flat array.
    */
    public function arr2flat(array $arr,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):array
    {
        if (!is_array($arr)){return $arr;}
        $flat=array();
        $this->arr2flatHelper($arr,$flat,'',$S);
        return $flat;
    }
    
    private function arr2flatHelper($arr,&$flat,$oldKey='',string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR)
    {
        $result=array();
        foreach ($arr as $key=>$value){
            if (strlen(strval($oldKey))===0){$newKey=$key;} else {$newKey=$oldKey.$S.$key;}
            if (is_array($value)){
                $result[$newKey]=$this->arr2flatHelper($value,$flat,$newKey,$S);
                if (empty($value) && is_array($value)){
                    $result[$newKey]='{}';
                    $flat[$newKey]='{}';
                }
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
    public function flat2arr($arr,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR)
    {
        if (!is_array($arr)){return $arr;}
        $result=array();
        foreach($arr as $key=>$value){
            if ($value==='{}'){$value=array();}
            $result=array_replace_recursive($result,$this->flatKey2arr($key,$value,$S));
        }
        return $result;
    }

    /**
    * @return arr This method a sub array from a flat array selected by $subFlatKey.
    */
    public function subflat2arr(array $flatArr,string $subFlatKey='',string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):array
    {
        $subFlatArr=array();
        foreach($flatArr as $flatKey=>$flatValue){
            if (strpos($flatKey,$subFlatKey)===0){
                $newKey=trim(substr($flatKey,mb_strlen($subFlatKey)),$S);
                $subFlatArr[$newKey]=$flatValue;
            }
        }
        return $this->flat2arr($subFlatArr);
    }
    
    private function flatKey2arr($key,$value,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):array
    {
        if (!is_string($key)){return array($key=>$value);}
        $k=explode($S,$key);
        while(count($k)>0){
            $subKey=array_pop($k);
            $value=array($subKey=>$value);
        }
        return $value;
    }
    
    /**
    * @return array This method deletes a key-value-pair selecting the key by the corresponding flat key.
    */
    public function arrDeleteKeyByFlatKey(array $arr,string $flatKey):array
    {
        $flatArr=$this->arr2flat($arr);
        foreach($flatArr as $arrKey=>$arrValue){
            if (mb_strpos($arrKey,$flatKey)===FALSE){continue;}
            unset($flatArr[$arrKey]);
        }
        $arr=$this->flat2arr($flatArr);
        return $arr;
    }
    
    /**
    * @return array This method updates a key-value-pair selecting the key by the corresponding flat key.
    */
    public function arrUpdateKeyByFlatKey(array $arr,string $flatKey,$value):array
    {
        $flatArr=$this->arr2flat($arr);
        $flatArr[$flatKey]=$value;
        $arr=$this->flat2arr($flatArr);
        return $arr;    
    }
        
    /**
    * @return string This method returns a string representing the provided flat key for a web page.
    */
    public function flatKey2label(string $key,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):string
    {
        return str_replace($S,' &rarr; ',$key);
    }
    
    /**
    * @return array This method returns an array representing last subkey value pairs
    */
    public function flatArrLeaves(array $flatArr,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):array
    {
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
    public function statistic2str(array|bool $statistic=FALSE):string
    {
        if ($statistic===FALSE){
            $statistic=$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
        }
        $str=array();
        foreach($statistic as $key=>$value){
            if (empty($value)){continue;}
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
    public function arr2matrix(array $arr,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR,$previewOnly=FALSE):array
    {
        $matrix=array();
        $previewRowCount=3;
        $rowIndex=0;
        $rows=array();
        $maxColumnCount=0;
        foreach($this->arr2flat($arr) as $flatKey=>$value){
            $columns=explode($S,strval($flatKey));
            $columnCount=count($columns);
            if (is_bool($value)){
                $value=$this->bool2element($value);
            }
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
                if ($previewOnly && $rowIndex>$previewRowCount){
                    $matrix[''][$i]='...';
                } else {
                    $matrix[$rowIndex][$i]=$key;
                }
            }
            if ($previewOnly && $rowIndex>$previewRowCount){
                $matrix['']['value']='...';
            } else {
                $matrix[$rowIndex]['value']=$rowArr['value'];
            }
        }
        return $matrix;
    }
    
    /**
    * @return array This method adds the values (if numeric mathmatically), if string with the same key of multiple arrays.
    */
    public function addArrValuesKeywise(...$arrays)
    {
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
    
    public function var2dataType($var):string
    {
        if (is_array($var)){
            return 'array';
        }
        $strVar=strval($var);
        if (is_numeric($var)){
            if (mb_strpos($strVar,'.')!==FALSE){
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
        $newValue=match($dataType){
                    'string'=>$this->str2str($value),
                    'stringNoWhitespaces'=>$this->convert2stringNoWhitespaces($value),
                    'stringWordChrsOnly'=>$this->convert2stringWordChrsOnly($value),
                    'splitString'=>$this->convert2splitString($value),
                    'int'=>$this->str2int($value),
                    'float'=>$this->str2float($value),
                    'fraction'=>$this->fraction2float($value),
                    'bool'=>(intval($value)>0),
                    'money'=>$this->oc['SourcePot\Datapool\Foundation\Money']->str2money($value),
                    'date'=>$this->oc['SourcePot\Datapool\GenericApps\Calendar']->str2date($value),
                    'excelDate'=>$this->oc['SourcePot\Datapool\GenericApps\Calendar']->str2date($value,'UTC',TRUE),
                    'dateString'=>$this->oc['SourcePot\Datapool\GenericApps\Calendar']->str2dateString($value,'System'),
                    'timestamp'=>$this->oc['SourcePot\Datapool\GenericApps\Calendar']->timestamp2date($value),
                    'shortHash'=>$this->getHash($value,TRUE),
                    'hash'=>$this->getHash($value,False),
                    'codepfad'=>$this->convert2codepfad($value),
                    'unycom'=>$this->convert2unycom($value),
                    'unycomCountry'=>$this->convert2unycomByKey($value,'Country'),
                    'unycomRegion'=>$this->convert2unycomByKey($value,'Region'),
                    'unycomFallNoWhitspaces'=>$this->convert2unycomByKey($value,'Reference without \s'),
                    'useridNameComma'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($value,3),
                    'useridName'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($value,1),
                    'useridEmail'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($value,7),
                    'useridPhone'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($value,8),
                    'useridMobile'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($value,9),
                };
        return $newValue;
    }
    
    private function str2str($string):string
    {
        $string=strval($string);
        if ($string===\SourcePot\Datapool\Root::NULL_DATE){
            return '';
        } else {
            return $string;
        }
    }

    public function str2int($string,$lang='en'):int
    {
        $value=$this->str2float($string,$lang);
        return intval(round($value));
    }
    
    public function fraction2float($string)
    {
        $string=preg_replace('/[^0-9\.\/\-]/u','',$string);
        $comps=explode('/',$string);
        $float=NULL;
        foreach($comps as $divider){
            $divider=floatval($divider);
            $float=(isset($float))?($float/$divider):$divider;
        }
        return $float;
    }
    
    public function str2float($string,$lang=''):float
    {
        $string=strval($string);
        $lang=mb_strtolower($lang);
        // get number from string
        $string=preg_replace('/[^0-9\.\,\-]/u','',$string);
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
    public function convert2stringNoWhitespaces($value):string
    {
        $value=strval($value);
        $value=preg_replace('/\s+/u','',$value);
        return $value;
    }

    public function convert2stringWordChrsOnly($value):string
    {
        $value=strval($value);
        $value=preg_replace('/[^A-Za-zäüöÄÜÖßÁÓÍÀÒÌáíóàòìâôî\s\-]/u','',$value);
        return $value;
    }

    public function convert2splitString($value):array|bool
    {
        $value=strval($value);
        $value=mb_strtolower($value);
        $value=trim($value);
        $value=preg_split("/[^a-zäöü0-9ß]+/u",$value);
        return $value;
    }
    
    public function convert2codepfad($value):array
    {
        $codepfade=explode(';',strval($value));
        $arr=array();
        foreach($codepfade as $codePfadIndex=>$codepfad){
            $codepfadComps=explode('\\',$codepfad);
            if ($codePfadIndex===0){
                if (isset($codepfadComps[0])){$arr['FhI']=$codepfadComps[0];}
                if (isset($codepfadComps[1])){$arr['FhI Teil']=$codepfadComps[1];}
                if (isset($codepfadComps[2])){$arr['Codepfad 3']=$codepfadComps[2];}
                $arr['Codepfad all']=implode('|',$codepfadComps);
            } else {
                if (isset($codepfadComps[0])){$arr[$codePfadIndex]['FhI']=$codepfadComps[0];}
                if (isset($codepfadComps[1])){$arr[$codePfadIndex]['FhI Teil']=$codepfadComps[1];}
                if (isset($codepfadComps[2])){$arr[$codePfadIndex]['Codepfad 3']=$codepfadComps[2];}
                $arr[$codePfadIndex]['Codepfad all']=implode('|',$codepfadComps);
            }
        }
        return $arr;
    }
    
    public function convert2unycomByKey($value,string $key='Country'):string
    {
        $unycomArr=$this->convert2unycom($value);
        return (isset($unycomArr[$key]))?$unycomArr[$key]:'';
    }
    
    public function convert2unycom($value,$prefix=''):array
    {
        $value=strval($value);
        $keyTemplate=array('Match','Year','Type','Number');
        $regions=array('WO'=>'PCT','WE'=>'Euro-PCT','EP'=>'European patent','EU'=>'Unitary Patent','AP'=>'ARIPO patent','EA'=>'Eurasian patent','OA'=>'OAPI patent');
        preg_match(\SourcePot\Datapool\Tools\MiscTools::UNYCOM_REGEX,$value,$matches);
        if (empty($matches[0])){return array('Match'=>'','isValid'=>FALSE);}
        // initialize result array from match
        $arr=array();
        $suffix='';
        foreach($matches as $matchIndex=>$matchValue){
            if (isset($keyTemplate[$matchIndex])){
                $arr[$keyTemplate[$matchIndex]]=$matchValue;
            } else {
                $suffix.=$matchValue;
            }
        }
        $arr['Region']='  ';
        $arr['Country']='  ';
        $arr['Part']='  ';
        $arr['isValid']=TRUE;
        // process suffix
        $suffix=preg_replace('/\s+/u','',$suffix);
        if (empty($suffix)){
            if ($arr['Type']==='P' || $arr['Type']==='E'){
                $arr['Type']='F';
            }
        } else {
            $suffix=strtoupper($suffix);
            $suffix=str_split($suffix,2);
            foreach($regions as $rc=>$region){
                if (empty($suffix[0])){break;}
                if (mb_strpos($suffix[0],$rc)!==0){continue;}
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
                        if (mb_strpos($suffix[0],$alpha2code)===FALSE){continue;}
                        $arr['Country']=$alpha2code;
                        $arr['Country long']=$countryArr['Country'];
                        break;
                    }
                }
                // get part
                $suffix=implode('',$suffix);
                $part=preg_replace('/[^0-9]+/u','',$suffix);
                $nonNumericSuffix=str_replace($part,'',$suffix);
                if (strlen($part)<2){$part='0'.$part;}
                if (!empty($part)){$arr['Part']=$part;}
                if (empty($arr['Country long']) && strlen($nonNumericSuffix)>1){
                    // get country, if country is empty
                    $arr['Country']=mb_substr($nonNumericSuffix,0,2);
                }
            }
        }
        // check year
        foreach($keyTemplate as $key){$arr[$key]=preg_replace('/\s+/u','',$arr[$key]);}
        if (strlen($arr['Year'])===2){
            if (intval($arr['Year'])<50){
                $arr['Year']='20'.$arr['Year'];
            } else {
                $arr['Year']='19'.$arr['Year'];
            }
        }
        // compile result
        $reference=$arr['Year'].$arr['Type'].$arr['Number'].$arr['Region'].$arr['Country'].$arr['Part'];
        $arr=array('Reference'=>$reference,'Reference without \s'=>preg_replace('/\s+/u','',$reference),'Full'=>$reference,'Family'=>$arr['Year'].'F'.$arr['Number'])+$arr;
        $arr['Prefix']=trim($prefix,'- ');
        if (!empty($arr['Prefix'])){$arr['Full']=$arr['Prefix'].' - '.$arr['Full'];}
        return $arr;
    }

    public function valueArr2value($value,$datatypeOrKey='')
    {
        $datatype2key=array('date'=>'System short','timestamp'=>'System short','exceldate'=>'System short','money'=>'','unycom'=>'Match');
        $key=(isset($datatype2key[$datatypeOrKey]))?$datatype2key[$datatypeOrKey]:$datatypeOrKey;
        if (!isset($value[$key]) && $key!==''){
            // If datatypeOrKey exists, a suitable key-value pair should also exist in the data field
            $value=FALSE;
        } else if (is_array($value) && isset($value[$key])){
            // Standard key matches a key-value pair 
            $value=$value[$key];
        } else if (is_array($value)){
            // If no standard key exists, use the first key-value pair 
            reset($value);
            $value=current($value);
        }
        return $value;
    }

    public function isTrue($valueA,$valueB,$condition):bool
    {
        // string or simple tests
        if ($condition==='empty'){
            return empty($valueA);
        } else if ($condition==='!empty'){
            return !empty($valueA);
        } else if ($condition==='strpos'){
            return stripos((string)$valueA,(string)$valueB)!==FALSE;
        } else if ($condition==='!strpos'){
            return stripos((string)$valueA,(string)$valueB)===FALSE;
        }
        // numeric tests
        if (is_int($valueA)){$valueB=intval(round($valueB));}
        if (is_float($valueA)){$valueB=floatval($valueB);}
        if (is_bool($valueA)){
            $valueA=intval($valueA);
            $valueB=intval($valueB);
        }
        if ($condition==='>'){
            return $valueA>$valueB;
        } else if ($condition==='='){
            return $valueA==$valueB;
        } else if ($condition==='!='){
            return $valueA!=$valueB;
        } else if ($condition==='>'){
            return $valueA<$valueB;
        } else if ($condition==='&'){
            return boolval($valueA&$valueB);
        } else if ($condition==='|'){
            return boolval($valueA|$valueB);
        } else if ($condition==='^'){
            return boolval($valueA^$valueB);
        } else if ($condition==='~'){
            return $valueA==-1*$valueB;
        }
        $this->oc['logger']->log('error','"{class} &rarr; {function}()" called with undefined condition.',array('class'=>__CLASS__,'function'=>__FUNCTION__));    
        return FALSE;
    }
    
    public function matchEntry($needle,$matchSelector,$matchColumn,$matchType='contains',$isSystemCall=FALSE):array
    {
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__);
        // prepare match selector
        $isUNYCOMfamily=FALSE;
        $needles=array();
        if ($matchType=='identical'){
            $matchSelector[$matchColumn]=$needle;
        } else if ($matchType=='contains'){
            $matchSelector[$matchColumn]='%'.$needle.'%';
        } else if ($matchType=='unycom'){
            $unycomArr=$this->convert2unycom($needle);
            if ($unycomArr['Type']==='F'){
                $needleTemplate=array('Year','Type','Number');
                $isUNYCOMfamily=TRUE;
            } else {
                $needleTemplate=array('Year','Type','Number','Region','Country','Part');    
            }
            foreach($needleTemplate as $key){$needles[]=$unycomArr[$key];}
            $matchSelector[$matchColumn]='%'.trim($unycomArr['Number']).'%';
        } else if ($matchType=='|'){
            $needles=explode($matchType,$needle);
            $matchSelector[$matchColumn]='%'.$needles[0].'%';
        } else if ($matchType=='number'){
            $needles=preg_split('/\D+/',$needle);
            $matchSelector[$matchColumn]='';
            foreach($needles as $sampleNeedle){
                if (mb_strlen($matchSelector[$matchColumn])<mb_strlen($sampleNeedle)){$matchSelector[$matchColumn]=$sampleNeedle;}
            }
            $matchSelector[$matchColumn]='%'.$matchSelector[$matchColumn].'%';
        }
        // get possible matches
        $bestMatch=array('probability'=>0,'Content'=>array(),'Params'=>array());
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($matchSelector,$isSystemCall) as $matchEntry){
            // get sample
            $sample=$matchEntry[$matchColumn];
            if (strlen($sample)===0){
                $context['Source']=$matchEntry['Source'];
                $context['EntryId']=$matchEntry['EntryId'];
                $context['Name']=$matchEntry['Name'];
                $this->oc['logger']->log('notice','Function "{class} &rarr; {function}()" empty sample for entry Source={Source}, EntryId={EntryId}, Name={Name} detected.',$context);
            } else if ($count=count($needles)){
                // calculate probability
                $probability=0;
                $tmpSample=$sample;
                foreach($needles as $needle){
                    $needlePos=strpos($tmpSample,$needle);
                    if ($needlePos===FALSE){continue;}
                    $probability++;
                    $tmpSample=substr($tmpSample,($needlePos+strlen($needle)));
                }
                $probability=($probability/$count)-(strlen($tmpSample)/strlen($sample));
                // check probability against threshold
                if ($bestMatch['probability']<$probability){
                    $bestMatch=$matchEntry;    
                    $bestMatch['probability']=$probability;
                    if ($probability==1){break;}
                }
            } else {
                $this->oc['logger']->log('notice','WARNING: Function "{class} &rarr; {function}()" called with empty needles, probability set to zero.',$context);
                $bestMatch=array();
                $bestMatch['probability']=0;
                break;
            }
        }
        return $bestMatch;
    }

    public function combineEntryData($entry):array{
        $context=array('class'=>__CLASS__,'function'=>__FUNCTION__);
        if (!empty($entry['Params']['Combine on update']) && !empty($entry['preExistingEntry'])){
            $flatCalcEntry=array();
            $flatExsistingEntry=$this->arr2flat($entry['preExistingEntry']);
            unset($entry['preExistingEntry']);
            $flatEntry=$this->arr2flat($entry);
            $flatSetting=$this->arr2flat($entry['Params']['Combine on update']);
            foreach($flatSetting as $settingKey=>$setting){
                $glue=trim($this->combineOptions[$setting],'string(AB)');
                $context['key']=$settingKey;
                $context['setting']=$setting;
                if (isset($flatEntry[$settingKey]) || isset($flatExsistingEntry[$settingKey])){
                    // identical settings-key match
                    $combineValueCountKey=$settingKey.'|combineValueCount';
                    $combineValueCountA=(isset($flatEntry[$combineValueCountKey]))?$flatEntry[$combineValueCountKey]:((isset($flatEntry[$settingKey])?1:0));
                    $combineValueCountB=(isset($flatExsistingEntry[$combineValueCountKey]))?$flatExsistingEntry[$combineValueCountKey]:((isset($flatExsistingEntry[$settingKey])?1:0));
                    if ($setting==='overwrite'){
                        // don't calculate new value
                    } else if ($setting==='addFloat'){
                        $a=(isset($flatEntry[$settingKey]))?floatval($flatEntry[$settingKey]):0;
                        $b=(isset($flatExsistingEntry[$settingKey]))?floatval($flatExsistingEntry[$settingKey]):0;
                        $flatCalcEntry[$settingKey]=$a+$b;
                        $flatCalcEntry[$combineValueCountKey]=$combineValueCountA+$combineValueCountB;
                    } else if (strpos($setting,'chain')!==FALSE && isset($flatEntry[$settingKey]) && isset($flatExsistingEntry[$settingKey])){
                        $flatCalcEntry[$settingKey]=$flatEntry[$settingKey].$glue.$flatExsistingEntry[$settingKey];
                        $flatCalcEntry[$combineValueCountKey]=$combineValueCountA+$combineValueCountB;
                    }
                } else {
                    // complex datatype keys
                    $entryValue=$this->subflat2arr($flatEntry,$settingKey);
                    $exsistingEntryValue=$this->subflat2arr($flatExsistingEntry,$settingKey);
                    if ($setting==='overwrite'){
                        $flatCalcEntry[$settingKey]['combineValueCount']=1;
                    } else if ($setting==='addFloat'){
                            // adding current and previous value, use data type float
                        if (isset($entryValue['Currency']) || isset($exsistingEntryValue['Currency'])){
                            // add money float
                            $context['type']='money';
                            if ($setting==='addFloat'){
                                $flatCalcEntry[$settingKey]=$this->oc['SourcePot\Datapool\Foundation\Money']->addMoney($entryValue,$exsistingEntryValue);
                            } else {
                                $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" selected data combination key="{key}" is not defined for dataype="{type}" and setting="{setting}"',$context);
                            }
                        } else {
                            // add UNKNOWN float
                            $context['type']='UNKNOWN';
                            $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" selected data combination key="{key}" is not defined for dataype="{type}" and setting="{setting}"',$context);    
                        }
                    } else if (strpos($setting,'chain')!==FALSE){
                        // concatenate current and previous value, use data type string
                        $flatCalcEntry[$settingKey]=$this->chainComplexDataTypes($exsistingEntryValue,$entryValue,$glue);
                    } else {
                        // setting undefined
                        $this->oc['logger']->log('warning','Function "{class} &rarr; {function}()" selected data combination setting undefined, setting="{setting}".',$context);
                    }
                }
            }
            if (isset($entry['Params'][__FUNCTION__])){$entry['Params'][__FUNCTION__]++;} else {$entry['Params'][__FUNCTION__]=1;}
            $entry=array_replace_recursive($entry,$this->flat2arr($flatCalcEntry));
        }
        return $entry;
    }

    private function chainComplexDataTypes(array $a, array $b, string $glue):array
    {
        $c=array();
        $keys=array_keys($a+$b);
        $issetA=$issetB=0;
        $combineValueCountA=intval(isset($a['combineValueCount'])?$a['combineValueCount']:0);
        $combineValueCountB=intval(isset($b['combineValueCount'])?$b['combineValueCount']:0);
        foreach($keys as $key){
            if ($key==='combineValueCount'){continue;}
            $a[$key]=(isset($a[$key]))?$a[$key]:'';
            $b[$key]=(isset($b[$key]))?$b[$key]:'';
            $a[$key]=(is_array($a[$key]))?'{...}':strval($a[$key]);
            $b[$key]=(is_array($b[$key]))?'{...}':strval($b[$key]);
            if (!empty($a[$key]) && !empty($b[$key])){
                $c[$key]=$a[$key].$glue.$b[$key];
                $issetA=$issetB=1;
            } else if (!empty($a[$key])){
                $c[$key]=$a[$key];
                $issetA=1;
            } else if (!empty($b[$key])){
                $c[$key]=$b[$key];
                $issetB=1;
            }
        }
        $c['combineValueCount']=((empty($combineValueCountA))?$issetA:$combineValueCountA)+((empty($combineValueCountB))?$issetB:$combineValueCountB);
        return $c;
    }

}
?>