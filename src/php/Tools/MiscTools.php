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

    private const SERIALIZED_INDICATOR='@@@@';
    
    public $emojis=[];
    private $emojiFile='';

    private $multipleHitsStatistic=[];
    
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

    public function getDateTime(string $datetime='now',string $addDateInterval='',string $timezone='',string $format='Y-m-d H:i:s',string $targetTimezone=\SourcePot\Datapool\Root::DB_TIMEZONE):string
    {
        if ($datetime[0]==='@'){
            $timestamp=intval(trim($datetime,'@'));
            $dateTime=new \DateTime();
            $dateTime->setTimestamp($timestamp); 
        } else {
            $timezone=$timezone?:(\SourcePot\Datapool\Root::DB_TIMEZONE);
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
        $dateTime->setTimeZone(new \DateTimeZone($targetTimezone));
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
                if (!is_string($flatValue)){
                    continue;
                }
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
    
    public function add2history($history,array $newElement,int $maxSize=100):array
    {
        if ($newElement['timeStamp']===NULL){
            //history is array of type [0=>[], 1=>[], 2=>[], 3=>[], 4=>[], ...]
            $newElement['timeStamp']=time();
            $history=$history??[];
            $history[]=$newElement;
        } else {
            //history is array of type [1770670446=>[], 1770670456=>[], 1770670466=>[], 1770670476=>[], 1770670486=>[], ...]
            $history[$newElement['timeStamp']]=$newElement;
        }
        $count=count($history);
        foreach($history as $index=>$element){
            if ($count>$maxSize){
                $history[$index]=NULL;
            } else {
                break;
            }
            $count--;
        }
        return $history;
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
            if (empty($value)){
                continue;
            }
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
        
    public function arr2json(array $arr):string
    {
        // json encoding but serialize on failure
        $jsonStr=json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_IGNORE);
        return $jsonStr?:(self::SERIALIZED_INDICATOR.serialize($arr));
    }
    
    public function json2arr(string $json):array
    {
        $json=strval($json);
        if (strpos($json,self::SERIALIZED_INDICATOR)===0){
            $arr=unserialize(substr($json,strlen(self::SERIALIZED_INDICATOR)));
        } else {
            $arr=json_decode($json,TRUE,512,JSON_INVALID_UTF8_IGNORE);
            $arr=$arr?:json_decode(stripslashes($json),TRUE,512,JSON_INVALID_UTF8_IGNORE);
        }
        return $arr?:[];
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
    
    private function flatKey2arr($key,$value,string $S=\SourcePot\Datapool\Root::ONEDIMSEPARATOR):array
    {
        if (!is_string($key)){
            return [$key=>$value];
        }
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
            if (mb_strpos($arrKey,$flatKey)===FALSE){
                continue;
            }
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

}
?>