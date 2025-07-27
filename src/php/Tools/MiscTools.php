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

    private $oc=NULL;
    
    //public const UNYCOM_REGEX='/([0-9]{4})([XPEFMR]{1,2})([0-9]{5})([A-Z ]{0,4})([0-9 ]{0,3})/u';
    public const UNYCOM_REGEX='/([0-9]{4})([ ]{0,1}[XPEFMR]{1,2})([0-9]{5})([A-Z ]{0,5})([0-9]{0,2})\s/u';
    
    public $emojis=[];
    private $emojiFile='';

    private $multipleHitsStatistic=[];
    private $combineOptionCache=[];
    
    private const DATA_TYPES=[
        'string'=>'&rarr; String','stringNoMultipleWhitespaces'=>'&rarr; String remove multiple \s','stringNoWhitespaces'=>'&rarr; String remove \s','stringWordChrsOnly'=>'&rarr; String remove \W',
        'splitString'=>'&rarr; Split string','int'=>'&rarr; integer','float'=>'&rarr; float','fraction'=>'Fraction &rarr; float',
        'bool'=>'&rarr; boolean','money'=>'&rarr; money','date'=>'&rarr; date','dateString'=>'&rarr; date, empty if invalid',
        'excelDate'=>'Excel &rarr; date','timestamp'=>'Timestamp &rarr; date','dateExchageRates'=>'Date &rarr; EUR exchange rates','excelDateExchageRates'=>'Excel date &rarr; EUR exchange rates',
        'shortHash'=>'&rarr; Hash (short)','hash'=>'&rarr; Hash','codepfad'=>'&rarr; codepfad','unycom'=>'&rarr; UNYCOM','unycomFamily'=>'&rarr; UNYCOM family','unycomCountry'=>'&rarr; UNYCOM country',
        'unycomRegion'=>'&rarr; UNYCOM region','unycomRef'=>'&rarr; UNYCOM reference','unycomRefNoWhitspaces'=>'&rarr; UNYCOM reference no \s',
        'userIdNameComma'=>'UserId &rarr; Name, First name','userIdName'=>'UserId &rarr; First name Name','useridemail'=>'UserId &rarr; email',
        'userIdPhone'=>'UserId &rarr; phone','userIdMobile'=>'UserId &rarr; mobile',
        ];
    
    public const CONDITION_TYPES=[
        'empty'=>'empty(A)','!empty'=>'!empty(A)','strpos'=>'A contains B','!strpos'=>'A does not contain B',
        '>'=>'A > B','='=>'A == B','!='=>'A != B','<'=>'A < B',
        '&'=>'A AND B','|'=>'A OR B','^'=>'A XOR B','~'=>'A == !B',
        ];
    
    public const COMPARE_TYPES=[
        '>'=>'>','='=>'==','!='=>'!=','<'=>'<',
        ];
    
    public const COMPARE_TYPES_0=[
        '>'=>'> 0','='=>'== 0','!='=>'!= 0','<'=>'< 0',
        ];
    
    public const OPERATIONS=[
        '+'=>'A + B','-'=>'A - B','*'=>'A * B','/'=>'A / B','pow'=>'pow(A,B)','%'=>'A modulus B',
        'regexMatch'=>'A RegEx B',
        ];
    
    private const COMBINE_OPTIONS=[''=>'{...}','lastHit'=>'Last hit','firstHit'=>'First hit','addFloat'=>'float(A + B)','chainSpace'=>'string(A B)','chainPipe'=>'string(A|B)','chainComma'=>'string(A, B)','chainSemicolon'=>'string(A; B)'];
    
    private $matchObj=NULL;

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
        $this->matchObj = new \SourcePot\Match\MatchValues();
    }
    
    public function getDataTypes():array
    {
        return self::DATA_TYPES;
    }
    
    public function getCombineOptions():array
    {
        return self::COMBINE_OPTIONS;
    }

    public function getMatchTypes():array
    {
        return $this->matchObj->getMatchTypes();
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
    * This method returns a tag style definition from an array, e.g. ['float'=>'left','clear'=>'right'] returns float:left;clear:right; 
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
    * This method returns an array from a tag style definition, e.g. float:left;clear:right; returns ['float'=>'left','clear'=>'right']
    *
    * @param string $arr Is tag style definition string  
    * @return array The style array
    */
    public function style2arr(string $style):array
    {
        $arr=[];
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
    
    public function xml2arr(string $xml)
    {
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

    public function bool2html($arr):string
    {
        $element=$this->bool2element($arr['value']??FALSE,$arr['element']??[],$arr['invertColors']??FALSE);
        return $this->oc['SourcePot\Datapool\Foundation\Element']->element($element);
    }
    
    public function bool2element($value,array $element=[],bool $invertColors=FALSE):array
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
        $string=preg_replace('/(".+)('.$delimiter.')(.+")/u','$1'.(\SourcePot\Datapool\Root::ONEDIMSEPARATOR).'$3',$string);
        $comps=explode($delimiter,$string);
        foreach($comps as $index=>$comp){
            $comps[$index]=str_replace(\SourcePot\Datapool\Root::ONEDIMSEPARATOR,$delimiter,$comp);
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
    
    public function addEntryId(array $entry,array $relevantKeys=['Source','Group','Folder','Name'],$timestampToUse=FALSE,string $suffix='',bool $keepExistingEntryId=FALSE):array
    {
        if (!empty($entry['EntryId']) && $keepExistingEntryId){return $entry;}
        $base=[];
        foreach($relevantKeys as $relevantKey){
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
    
    private function emojiList2file():array|bool
    {
        //$html=file_get_contents('https://unicode.org/emoji/charts/full-emoji-list.html');
        $html=file_get_contents('D:/Full Emoji List, v15.0.htm');
        if (empty($html)){return FALSE;}
        $result=[];
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
                            $result[$key0??''][$key1??''][$key2]=html_entity_decode(trim(strip_tags($cell)));
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
        $e=['a','f','p','n','u','m','','k','M','G','T','P','E'];
        $p=min(max(floor(log(abs($float), $base)),-6),6);
        $value=round((float)$float/pow($base,$p),$prec);
        if ($value==0){
            return strval($value);
        } else {
            return $value.' '.$e[$p+6];
        }
    }

    /******************************************************************************************************************************************
    * Array tools
    */

    public function generic_strtr(string|array $haystack,$needleReplacement=['{{query}}'=>'%test%']):string|array
    {
        if (is_array($haystack)){
            $flatHaystack=$this->arr2flat($haystack);
            foreach($flatHaystack as $flatKey=>$flatValue){
                if (!is_string($flatValue)){continue;}
                $flatHaystack[$flatKey]=strtr($flatValue,$needleReplacement);
            }
            $haystack=$this->flat2arr($flatHaystack);
        } else {
            $haystack=strtr($haystack,$needleReplacement);
        }
        return $haystack;
    }
    
    public function formData2statisticlog($formData)
    {
        if (!empty($formData['cmd'])){
            $statistics=$this->oc['SourcePot\Datapool\Tools\MiscTools']->statistic2str();
            if ($statistics){
                $this->oc['logger']->log('info','Form processing "cmd={cmd}": {statistics}',['cmd'=>key($formData['cmd']),'statistics'=>$statistics]);    
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

    /**
    * The method returns TRUE if the entry was touched already or FALSE if not
    *
    * @param array $entry Is the entry.  
    * @param array $processId Is the process id, e.g. the class with namespace and method
    * @param boolean $isSystemCall The value is provided to access control. 
    * @param boolean $dontUpdate If true, the entry will not be updateed, e.g. equals testRun. 
    *
    * @return array The resulting entry entry.
    */
    public function wasTouchedByClass(array $entry,string $processId,bool $dontUpdate=FALSE):bool
    {
        if (isset($entry['Params'][$processId])){
            return TRUE;
        } else {
            $entry['Params'][$processId]=['user'=>$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId(),'timestamp'=>time()];
            if (!$dontUpdate){$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($entry);}
            return FALSE;
        }
    }
    
    public function arr2selector(array $arr,array $defaultValues=['app'=>'','Source'=>FALSE,'EntryId'=>FALSE,'Group'=>FALSE,'Folder'=>FALSE,'Name'=>FALSE]):array
    {
        $selector=[];
        foreach($defaultValues as $key=>$defaultValue){
            $selector[$key]=$arr[$key]??$defaultValue;
            $selector[$key]=(strpos(strval($selector[$key]),\SourcePot\Datapool\Root::GUIDEINDICATOR)===FALSE)?$selector[$key]:FALSE;
        }
        return $selector;
    }

    public function minimalSelector(array $selector):array
    {
        $selector['EntryId']=$selector['EntryId']??FALSE;
        if (!empty($selector['Source']) && !empty($selector['EntryId']) && strpos($selector['EntryId'],'%')===FALSE && strpos($selector['EntryId'],'?')===FALSE){
            return ['Source'=>$selector['Source'],'EntryId'=>$selector['EntryId'],];
        }
        return $selector;
    }

    public function arr2entry(array $arr):array
    {
        $entry=[];
        $Source=$arr['Source']??'settings';
        $entryTemplate=$GLOBALS['dbInfo'][$Source];
        foreach($entryTemplate as $column=>$infoArr){
            if (!isset($arr[$column])){continue;}
            $entry[$column]=$arr[$column];
        }
        return $entry;
    }
    
    public function arrRemoveEmpty(array $arr)
    {
        $flatResultArr=[];
        $flatArr=$this->arr2flat($arr);
        foreach($flatArr as $flatKey=>$value){
            if (empty($value)){continue;}
            $flatResultArr[$flatKey]=$value;
        }
        return $this->flat2arr($flatResultArr);
    }
    
    /**
    * Creates a new selector from a deletion selector. Example:
    * $selector=['Source'=>'SAP','Group'=>'Buchungen','Folder'=>'IP','Name'=>'Kanzlei XYZ']  
    * return ['Source'=>'SAP','Group'=>'Buchungen','Folder'=>'IP','Name'=>FALSE,'EntryId'=>FALSE]
    * 
    * @param    array   $selector   Is an selector to select entries or an entry 
    * @return   array   The new selector
    */
    public function selectorAfterDeletion(array $selector,array $columns=['Source','Group','Folder','EntryId','VOID']):array
    {
        $lastColumn='Source';
        $newSelector=['app'=>(isset($selector['app'])?$selector['app']:'')];
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
                return [];
            } else {
                return $arr;
            }
        } else {
            return [];
        }
    }
    
    /**
    * @return arr This method converts an array to the corresponding flat array.
    */
    public function arr2flat(array $arr,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):array
    {
        if (!is_array($arr)){return $arr;}
        $flat=[];
        $this->arr2flatHelper($arr,$flat,'',$S);
        return $flat;
    }
    
    private function arr2flatHelper($arr,&$flat,$oldKey='',string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR)
    {
        $result=[];
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
    public function flat2arr($arr,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):array
    {
        if (!is_array($arr)){return $arr;}
        $result=[];
        foreach($arr as $key=>$value){
            if ($value==='{}'){$value=[];}
            $result=array_replace_recursive($result,$this->flatKey2arr($key,$value,$S));
        }
        return $result;
    }

    /**
    * @return arr This method returns a sub array from a flat array selected by $subFlatKey.
    */
    public function subflat2arr(array $flatArr,string $subFlatKey='',string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):array
    {
        $subFlatArr=[];
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
        if (!is_string($key)){return [$key=>$value];}
        $k=explode($S,$key);
        while(count($k)>0){
            $subKey=array_pop($k);
            $value=[$subKey=>$value];
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
            $flatArr[$arrKey]=NULL;
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
    * @return string This method returns the column from a flat key provided.
    */
    public function flatKey2column(string $key,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):string
    {
        $flatKeyComps=explode($S,$key);
        return array_shift($flatKeyComps);
    }
    
    /**
    * @return array This method returns an array representing last subkey value pairs
    */
    public function flatArrLeaves(array $flatArr,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):array
    {
        $leaves=[];
        foreach($flatArr as $flatKey=>$flatValue){
            $leafKey=explode($S,$flatKey);
            $leafKey=array_pop($leafKey);
            $leaves[$leafKey]=$flatValue;
        }
        return $leaves;
    }

    /**
     * This method merges input arrays recursively and removes branches with NULL values and overwrites braches with empty arrays. It returns the merged array. 
     * Example: 
     * $a=['Params'=>['File'=>['Name'=>'test_A.pdf','Size'=>1200],'Geo'=>['lat'=>32.23,'lon'=>1.433]],'Content'=>['Message'=>'Hallo','Comment'=>'Test']] and
     * $b=['Params'=>['File'=>['Name'=>'test_A.pdf','Size'=>1200],'Geo'=>[]],'Content'=>['Message'=>'Hallo','Comment'=>NULL]] returns
     * ['Params'=>['File'=>['Name'=>'test_A.pdf','Size'=>1200],'Geo'=>[],'Content'=>['Message'=>'Hallo']]
     * 
     * @param array $a Is the first array
     * @param array $b Is the second array
     * @return array returns the merged array 
    */
    public function mergeArr(array $a,array $b)
    {
        $flatA=$this->arr2flat($a);
        $flatB=$this->arr2flat($b);
        foreach($flatB as $flatKeyB=>$valueB){
            if ($valueB==='{}' || $valueB===NULL || $valueB==='__TODELETE__'){
                // empty B
                if ($valueB===NULL || $valueB==='__TODELETE__'){
                    unset($flatB[$flatKeyB]);
                }
                // empty A with flatKeyB
                if (isset($flatA[$flatKeyB])){
                    unset($flatA[$flatKeyB]);
                } else {
                    // empty branch A starting with flatKeyB
                    foreach($flatA as $flatKeyA=>$valueA){
                        if (strpos($flatKeyA,$flatKeyB)===0){
                            unset($flatA[$flatKeyA]);
                        }
                    }
                }
            }
        }
        $flatA=array_merge($flatA,$flatB);
        return $this->flat2arr($flatA);
    }

    /**
    * @return string This method returns a string for a web page created from a statistics array, e.g. ['matches'=>0,'updated'=>0,'inserted'=>0,'deleted'=>0,'removed'=>0,'file added'=>0]
    */
    public function statistic2str(array|bool $statistic=FALSE):string
    {
        if ($statistic===FALSE){
            $statistic=$this->oc['SourcePot\Datapool\Foundation\Database']->getStatistic();
        }
        $str=[];
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
        $matrix=[];
        $previewRowCount=3;
        $rowIndex=0;
        $rows=[];
        $maxColumnCount=0;
        foreach($this->arr2flat($arr) as $flatKey=>$value){
            if (is_string($value)){
                $value=strip_tags($value);  // prevent XSS atacks
            }
            $columns=explode($S,strval($flatKey));
            $columnCount=count($columns);
            if (is_bool($value)){
                $value=$this->bool2element($value);
            }
            $rows[$rowIndex]=['columns'=>$columns,'value'=>$value];
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
        // Example: Arguments "['deleted'=>2,'inserted'=>1,'steps'=>'Open web page','done'=>FALSE]" and "['deleted'=>0,'inserted'=>4,'steps'=>'Close web page','done'=>TRUE]"
        // will return ['deleted'=>2,'inserted'=>5,'steps'=>'Open web page|Close web page','done'=>TRUE]
        $result=[];
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

    /**
    * @return array This method aadds an array [addKey=>value] to flatArr and combineOptions information to flatArr
    */
    public function addValue2flatArr(array $flatArr,string $flatKey,string $addKey='',$value,string $combineOptions='')
    {
        // initialize array- for provieded flatKey
        if (!isset($flatArr[$flatKey])){
            $flatArr[$flatKey]=[];
        } else if (!is_array($flatArr[$flatKey])){
            $flatArr[$flatKey]=[];
        }
        // array data type values only permitted for columns Content and Params
        if (strpos($flatKey,'Content')!==0 && strpos($flatKey,'Params')!==0 && is_array($value)){
            $value=$this->valueArr2value($value);
        } else if (strpos($combineOptions,'chain')===0){
            $value=$this->valueArr2value($value);
        }
        if (strlen($addKey)>0){
            if (strpos($flatKey,'Content')===0 || strpos($flatKey,'Params')===0){
                $flatKey.=\SourcePot\Datapool\Root::ONEDIMSEPARATOR.$addKey;
                $flatArr[$flatKey][]=$value;
            } else {
                $flatArr[$flatKey][$addKey]=$value;
            }
        } else {
            $flatArr[$flatKey][]=$value;
        }
        $this->combineOptionCache[$flatKey]=$combineOptions;
        return $flatArr;
    }

    public function valueArr2value($value,$keyNeedle='')
    {
        if (!is_array($value)){return $value;}
        if (isset($value[$keyNeedle])){
            return $value[$keyNeedle];
        }
        foreach(['System short','Amount','Reference'] as $keyNeedle){
            if (isset($value[$keyNeedle])){
                return $value[$keyNeedle];
            }
        }
        reset($value);
        return current($value);
    }

    /**
    * @return array This method aadds an array [addKey=>value] to flatArr and combineOptions information to flatArr
    */
    public function flatArrCombineValues(array $flatArr)
    {
        $combinedFlatArr=[];
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        foreach($flatArr as $flatArrKey=>$valueArr){
            $context['column']=$flatArrKey;
            if (is_array($valueArr)){ksort($valueArr);}
            if (isset($this->combineOptionCache[$flatArrKey])){
                $combineOption=$this->combineOptionCache[$flatArrKey];
            } else {
                $combineOption='';
            }
            if ($combineOption==='lastHit'){
                $value=array_pop($valueArr);
            } else if ($combineOption==='firstHit'){
                $value=array_shift($valueArr);
            } else if ($combineOption==='addFloat'){
                $value=0;
                foreach($valueArr as $arrValue){
                    $value+=floatval($arrValue);
                }
            } else if ($combineOption==='chainSpace'){
                $value=implode(' ',$valueArr);
            } else if ($combineOption==='chainPipe'){
                $value=implode('|',$valueArr);
            } else if ($combineOption==='chainComma'){
                $value=implode(', ',$valueArr);
            } else if ($combineOption==='chainSemicolon'){
                $value=implode('; ',$valueArr);
            } else {
                $value=$valueArr;
            }
            if (is_array($value)){
                if (strpos($flatArrKey,'Content')!==0 && strpos($flatArrKey,'Params')!==0){
                    $combinedFlatArr[$flatArrKey]='{}';
                    $this->oc['logger']->log('notice','Mapping failed for column "{column}". Please check "Combine"-option..',$context);
                } else {
                    $subFlatArr=$this->arr2flat($value);
                    foreach($subFlatArr as $subkey=>$subValue){
                        $combinedFlatArr[$flatArrKey.\SourcePot\Datapool\Root::ONEDIMSEPARATOR.$subkey]=$subValue;
                    }
                }
            } else {
                $combinedFlatArr[$flatArrKey]=$value;
            }
        }
        return $combinedFlatArr;
    }

    public function add2hitStatistics(array $entry,string $comment='')
    {
        if (isset($this->multipleHitsStatistic[$entry['EntryId']])){
            $this->multipleHitsStatistic[$entry['EntryId']]['Hits']++;
            if (strpos($this->multipleHitsStatistic[$entry['EntryId']]['Comment'],$comment)===FALSE){
                $this->multipleHitsStatistic[$entry['EntryId']]['Comment'].=', '.$comment;
            }
        } else {
            $this->multipleHitsStatistic[$entry['EntryId']]=['Name'=>$entry['Name'],'Hits'=>1,'Comment'=>$comment];
        } 
    }

    public function getMultipleHitsStatistic():array
    {
        return $this->multipleHitsStatistic;
    }

    /******************************************************************************************************************************************
    * Generic conversions
    */

    public function convert($value,$dataType){
        if (empty($dataType)){
            $newValue=$value;
        } else {
            $newValue=match($dataType){
                'string'=>$this->str2str($value),
                'stringNoWhitespaces'=>$this->convert2stringWhitespaces($value,''),
                'stringNoMultipleWhitespaces'=>$this->convert2stringWhitespaces($value,' '),
                'stringWordChrsOnly'=>$this->convert2stringWordChrsOnly($value),
                'splitString'=>$this->convert2splitString($value),
                'int'=>$this->str2int($value),
                'float'=>$this->str2float($value),
                'fraction'=>$this->fraction2float($value),
                'bool'=>(bool)$value,
                'money'=>$this->oc['SourcePot\Datapool\Foundation\Money']->str2money($value),
                'date'=>$this->oc['SourcePot\Datapool\Calendar\Calendar']->str2date($value),
                'excelDate'=>$this->oc['SourcePot\Datapool\Calendar\Calendar']->excel2date($value),
                'timestamp'=>$this->oc['SourcePot\Datapool\Calendar\Calendar']->timestamp2date($value),
                'dateString'=>$this->oc['SourcePot\Datapool\Calendar\Calendar']->str2dateString($value,'System'),
                'dateExchageRates'=>$this->oc['SourcePot\Datapool\Foundation\Money']->date2exchageRates($value),
                'excelDateExchageRates'=>$this->oc['SourcePot\Datapool\Foundation\Money']->excelDate2exchageRates($value),
                'shortHash'=>$this->getHash($value,TRUE),
                'hash'=>$this->getHash($value,False),
                'codepfad'=>$this->convert2codepfad($value),
                'unycom'=>$this->convert2unycom($value),
                'unycomFamily'=>$this->convert2unycomByKey($value,'Family'),
                'unycomCountry'=>$this->convert2unycomByKey($value,'Country'),
                'unycomRegion'=>$this->convert2unycomByKey($value,'Region'),
                'unycomRef'=>$this->convert2unycomByKey($value,'Reference'),
                'unycomRefNoWhitspaces'=>$this->convert2unycomByKey($value,'Reference without \s'),
                'useridNameComma'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($value,3),
                'useridName'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($value,1),
                'useridEmail'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($value,7),
                'useridPhone'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($value,8),
                'useridMobile'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract($value,9),
            };
        }
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

    public function str2int($string):int
    {
        $value=$this->str2float($string);
        return intval(round($value));
    }
    
    public function fraction2float($string)
    {
        $string=preg_replace('/[^0-9\.\/\-]/u','',strval($string));
        $comps=explode('/',$string);
        $float=NULL;
        foreach($comps as $divider){
            $divider=floatval($divider);
            $float=(isset($float))?($float/$divider):$divider;
        }
        return $float;
    }
    
    public function str2float($string):float
    {
        $asset=new \SourcePot\Asset\Asset();
        if (is_int($string) || is_float($string)){
            return floatval($string);
        } else if (is_bool($string)){
            return ($string)?1:0;
        } else if (is_string($string)){
            $assetArr=$asset->guessAssetFromString(strval($string));
            return $assetArr['value'];
        } else {
            return 0;
        }
    }

    public function convert2stringWhitespaces($value,$replacement=''):string
    {
        $value=strval($value);
        $value=preg_replace('/\s+/u',$replacement,$value);
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
        $arr=[];
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
    
    public function convert2unycom($value):array
    {
        $unycomObj = new \SourcePot\Match\UNYCOM();
        $unycomObj->set($value);  
        return $unycomObj->get();
    }

    /******************************************************************************************************************************************
    * Operations
    */

    public function isTrue($valueA,$valueB,$condition):bool|NULL
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
        } else if ($condition==='regexMatch'){
            return boolval(preg_match('/'.(string)$valueB.'/',(string)$valueA,$matches));
        } else if ($condition==='!regexMatch'){
            return boolval(preg_match('/'.(string)$valueB.'/',(string)$valueA,$matches))===FALSE;
        }
        $valueA=(is_string($valueA))?$this->oc['SourcePot\Datapool\Tools\MiscTools']->str2float($valueA):$valueA;
        $valueB=(is_string($valueB))?$this->oc['SourcePot\Datapool\Tools\MiscTools']->str2float($valueB):$valueB;
        // numeric tests
        if (is_int($valueA)){
            $valueB=intval($valueB);
        } else if (is_float($valueA)){
            $valueB=floatval($valueB);
        } else if (is_bool($valueA)){
            $valueA=intval($valueA);
            $valueB=intval($valueB);
        }
        if ($condition==='>'){
            return floatval($valueA)>floatval($valueB);
        } else if ($condition==='='){
            return $valueA==$valueB;
        } else if ($condition==='!='){
            return $valueA!=$valueB;
        } else if ($condition==='<'){
            return floatval($valueA)<floatval($valueB);
        } else if ($condition==='&'){
            return boolval($valueA&$valueB);
        } else if ($condition==='|'){
            return boolval($valueA|$valueB);
        } else if ($condition==='^'){
            return boolval($valueA^$valueB);
        } else if ($condition==='~'){
            return $valueA==-1*$valueB;
        }
        return NULL;
    }

    public function operation($valueA,$valueB,$operation)
    {
        $result=$this->isTrue($valueA,$valueB,$operation);
        if ($result===NULL){
            $valueA=(is_string($valueA))?$this->oc['SourcePot\Datapool\Tools\MiscTools']->str2float($valueA):$valueA;
            $valueB=(is_string($valueB))?$this->oc['SourcePot\Datapool\Tools\MiscTools']->str2float($valueB):$valueB;
            // numeric tests
            if (is_int($valueA)){
                $valueB=intval($valueB);
            } else if (is_float($valueA)){
                $valueB=floatval($valueB);
            } else if (is_bool($valueA)){
                $valueA=intval($valueA);
                $valueB=intval($valueB);
            }
            if ($operation==='-'){
                return $valueA-$valueB;
            } else if ($operation==='+'){
                return $valueA-$valueB;
            } else if ($operation==='*'){
                return $valueA*$valueB;
            } else if ($operation==='pow'){
                return pow($valueA,$valueB);
            } else if ($operation==='/'){
                if ($valueB===0){
                    return $valueA*INF; // avoid division by zero
                } else {
                    return $valueA/$valueB;
                }
            } else if ($operation==='%'){
                if ($valueB===0){
                    return $valueA*INF; // avoid division by zero
                } else {
                    return $valueA%$valueB;
                }
            } else if ($operation==='regexMatch'){
                preg_match('/'.$valueB.'/',$valueA,$match);
                return $match[0]??NULL;
            }
        }
        return $result;
    }
    
    public function matchEntry($needle,$matchSelector,$matchFlatKey,$matchType='contains',$isSystemCall=FALSE):array
    {
        $matchColumn=$this->oc['SourcePot\Datapool\Tools\MiscTools']->flatKey2column($matchFlatKey);
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'needle'=>$needle,'needleLength'=>strlen($needle),'matchColumn'=>$matchColumn,'matchFlatKey'=>$matchFlatKey];
        if ($context['needleLength']<3){
            $this->oc['logger']->log('info','Function "{class} &rarr; {function}()" called with very short needle "{needle}" for match column "{matchColumn}".',$context);
        }
        $this->matchObj->set($needle,$matchType);
        $dbNeedle=$this->matchObj->prepareMatch();
        if (strpos($matchType,'!')===0){
            $matchSelector['!'.$matchColumn]=$dbNeedle;
        } else {
            $matchSelector[$matchColumn]=$dbNeedle;
        }
        // get possible matches
        $bestMatch=['Content'=>['match'=>['probability'=>0,'needle'=>$needle,'sample'=>'']],'Params'=>[]];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($matchSelector,$isSystemCall) as $matchEntry){
            // get sample
            if (is_array($matchEntry[$matchColumn])){
                $flatMatchEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($matchEntry);
                if (empty($flatMatchEntry[$matchFlatKey])){
                    continue;
                } else {
                    $sample=$flatMatchEntry[$matchFlatKey];
                }
            } else {
                $sample=strval($matchEntry[$matchColumn]);
            }
            if (strlen($sample)===0){
                $context['Source']=$matchEntry['Source'];
                $context['EntryId']=$matchEntry['EntryId'];
                $context['Name']=$matchEntry['Name'];
                $this->oc['logger']->log('notice','Function "{class} &rarr; {function}()" empty sample for entry Source={Source}, EntryId={EntryId}, Name={Name} detected.',$context);
                continue;
            }
            try{
                $probability=$this->matchObj->match($sample);
            } catch(\Exception $e){
                $context['msg']=$e->getMessage();
                $this->oc['logger']->log('notice','Function "{class} &rarr; {function}()" match of needle "{needle}", with column "{matchColumn}" failed with "{msg}".',$context);
                continue;
            }
            if ($bestMatch['Content']['match']['probability']<$probability){
                $bestMatch=$matchEntry;
                $bestMatch['Content']['match']['needle']=$needle;
                $bestMatch['Content']['match']['sample']=$sample;
                $bestMatch['Content']['match']['probability']=$probability;
                if ($probability==1){break;}
            }
            
        }
        return $bestMatch;
    }

}
?>