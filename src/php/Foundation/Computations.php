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

class Computations{
    
    //public const UNYCOM_REGEX='/([0-9]{4})([XPEFMR]{1,2})([0-9]{5})([A-Z ]{0,4})([0-9 ]{0,3})/u';
    public const UNYCOM_REGEX='/([0-9]{4})([ ]{0,1}[XPEFMR]{1,2})([0-9]{5})([A-Z ]{0,5})([0-9]{0,2})\s/u';

    private const COMPARE_EQUAL_PRECISION=5;
    
    public const DATA_TYPES=[
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
        '>'=>'A > B','=='=>'A == B','!='=>'A != B','<'=>'A < B',
        '&&'=>'A AND B','||'=>'A OR B','^'=>'A XOR B','~'=>'A == !B',
        'containsRegexMatch'=>'A contains RegEx(B)','!containsRegexMatch'=>'A !contains RegEx(B)',
        'TRUE'=>'always TRUE','FALSE'=>'always FALSE',
        ];
    
    public const COMPARE_TYPES_CONST=[
        '>0'=>'> 0','==0'=>'== 0','!=0'=>'!= 0','<0'=>'< 0',
        '==INF'=>'== &infin;','!=INF'=>'!= &infin;',
        '==-INF'=>'== -&infin;','!=-INF'=>'!= -&infin;',
        '==±INF'=>'== &plusmn;&infin;','!=±INF'=>'!= &plusmn;&infin;',
        ];
    
    public const OPERATIONS=[
        '+'=>'A + B','-'=>'A - B','*'=>'A * B','/'=>'A / B','pow'=>'pow(A,B)','%'=>'A modulus B',
        '|'=>'A | B','&'=>'A & B',
        'regexMatch'=>'f(A,RegEx(B))',
        ];
    
    public const COMBINE_OPTIONS=[
        ''=>'{...}',
        'lastHit'=>'Last hit',
        'firstHit'=>'First hit',
        'int(A+B+...)'=>'int(A+B+...)',
        'float(A+B+...)'=>'float(A+B+...)',
        'average(A,B,...)'=>'average(A,B,...)',
        'string(A B)'=>'string(A B)',
        'string(A|B)'=>'string(A|B)',
        'string(A, B)'=>'string(A, B)',
        'string(A; B)'=>'string(A; B)'
    ];

    private const RELEVANT_DATATYPE_KEY=['System short','Reference','Amount',];

    private const ARR_COLUMNS=['Content'=>TRUE,'PARAMS'=>TRUE,];
    
    private $matchObj=NULL;
    private $combineCache=[];

    private $oc;

    public function __construct($oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->matchObj = new \SourcePot\Match\MatchValues();
    }
    
    public function getMatchTypes():array
    {
        return $this->matchObj->getMatchTypes();
    }
    
    /******************************************************************************************************************************************
    * Array operations
    */

    public function add2combineCache(string $combineOperation,string $column,string $key,$value)
    {
        $cacheIdStr=$combineOperation.$column;
        $cacheIdStr.=(empty(self::ARR_COLUMNS[$column]))?'':$key;
        $cachId=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($cacheIdStr,TRUE);
        $this->combineCache[$cachId]['__COLUMN__']=$column;
        $this->combineCache[$cachId]['__OPERATION__']=$combineOperation;
        if (stripos($combineOperation,'float')!==FALSE){
            $value=$this->arr2value($value);
            $value=floatval($value);
        } else if (stripos($combineOperation,'int')!==FALSE || stripos($combineOperation,'byte')!==FALSE){
            $value=$this->arr2value($value);
            $value=intval($value);
        } else if (stripos($combineOperation,'string')!==FALSE){
            $value=$this->arr2value($value);
            $value=strval($value);
        } else if (stripos($combineOperation,'bool')!==FALSE){
            $value=$this->arr2value($value);
            $value=!empty($value);
        }
        if (!empty(self::ARR_COLUMNS[$column])){
            // array columns
            $index=count($this->combineCache[$cachId]['__VALUES__'][$key]??[]);
            $this->combineCache[$cachId]['__VALUES__'][$key][$index]=$value;
        } else {
            // non-array columns
            if (!isset($this->combineCache[$cachId]['__VALUES__'][$key])){
                // only safe the first value at a specific key
                $this->combineCache[$cachId]['__VALUES__'][$key]=$value;
            }
        }
    }

    public function combineAll(array $flatEntry):array
    {
        //$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($this->combineCache,'combineCache.json');
        foreach($this->combineCache as $fcacheId=>$cache){
            $flatEntry=$this->combine($flatEntry,$cache);
        }
        $this->combineCache=[];
        //$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($flatEntry,'flatEntry.json');
        return $flatEntry;
    }

    public function combine(array $flatEntry,array $cache):array
    {
        if (empty(self::ARR_COLUMNS[$cache['__COLUMN__']])){
            // non-array columns
            $arr=[];
            foreach($cache['__VALUES__'] as $key=>$value){
                $arr[$key]=$value=$this->arr2value($value);
            }
            $flatEntry[$cache['__COLUMN__']]=$this->arrOperation($arr,$cache['__OPERATION__'],TRUE);
        } else {
            // array columns
            foreach($cache['__VALUES__'] as $key=>$value){
                if (empty($cache['__OPERATION__'])){
                    // Operation ''=>'{...}'
                    $flatEntry[$cache['__COLUMN__']][$key]=$this->arrOperation($value,$cache['__OPERATION__'],FALSE);
                } else {
                    // Operation not ''=>'{...}'
                    $flatEntry[$cache['__COLUMN__']][$key]=$this->arrOperation($value,$cache['__OPERATION__'],TRUE);
                }
            }
        }
        return $flatEntry;
    }

    private function arrOperation(array $arr,$operation,$forceScalar=FALSE)
    {
        ksort($arr);
        if (strpos($operation,'string')!==FALSE){
            $glue=trim($operation,'string()AB.');
            $result=implode($glue,$arr);
        } else if (strpos($operation,'+')!==FALSE){
            $result=array_sum($arr);
        } else if ($operation==='average'){
            $result=array_sum($arr)/count($arr);
        } else if ($operation==='lastHit' || $operation==='firstHit'){
            if ($operation==='lastHit'){end($arr);} else {reset($arr);}
            $result[key($arr)]=current($arr);
        } else {
            $result=$arr;
        }
        $result=(is_array($result) && $forceScalar)?($this->arr2value($result)):$result;
        return $result;
    }

    public function arr2value($arr,$keyNeedle='__NOT_SET__')
    {
        if (!is_array($arr)){
            return $arr;
        }
        if (isset($arr[$keyNeedle])){
            return $arr[$keyNeedle];
        }
        foreach(self::RELEVANT_DATATYPE_KEY as $keyNeedle){
            if (isset($arr[$keyNeedle])){
                return $arr[$keyNeedle];
            }
        }
        return array_shift($arr);
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
                'bool'=>($value==='TRUE')?TRUE:(!empty($value)),
                'money'=>$this->oc['SourcePot\Datapool\Foundation\Money']->str2money($value),
                'date'=>$this->oc['SourcePot\Datapool\Calendar\Calendar']->str2date($value),
                'excelDate'=>$this->oc['SourcePot\Datapool\Calendar\Calendar']->excel2date($value),
                'timestamp'=>$this->oc['SourcePot\Datapool\Calendar\Calendar']->timestamp2date($value),
                'dateString'=>$this->oc['SourcePot\Datapool\Calendar\Calendar']->str2dateString($value,'System'),
                'dateExchageRates'=>$this->oc['SourcePot\Datapool\Foundation\Money']->date2exchageRates($value),
                'excelDateExchageRates'=>$this->oc['SourcePot\Datapool\Foundation\Money']->excelDate2exchageRates($value),
                'shortHash'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($value,TRUE),
                'hash'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($value,False),
                'codepfad'=>$this->convert2codepfad($value),
                'unycom'=>$this->convert2unycom($value),
                'unycomFamily'=>$this->convert2unycomByKey($value,'Family'),
                'unycomCountry'=>$this->convert2unycomByKey($value,'Country'),
                'unycomRegion'=>$this->convert2unycomByKey($value,'Region'),
                'unycomRef'=>$this->convert2unycomByKey($value,'Reference'),
                'unycomRefNoWhitspaces'=>$this->convert2unycomByKey($value,'Reference without \s'),
                'userIdNameComma'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(strval($value),3),
                'userIdName'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(strval($value),1),
                'userIdEmail'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(strval($value),7),
                'userIdPhone'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(strval($value),8),
                'userIdMobile'=>$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(strval($value),9),
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
    * Comparisons and Operations
    */

    private function value2numeric($value):float|int|null
    {
        $value=($value==='INF')?(INF):(($value==='-INF')?(-INF):$value);
        $value=($value==='NAN')?NAN:$value;
        $value=($value==='NULL')?NULL:$value;
        $value=($value===TRUE || $value==='TRUE')?1:$value;
        $value=($value===FALSE || $value==='FALSE')?0:$value;
        if (is_string($value)){
            if (strpos($value,'.')===FALSE && strpos($value,',')===FALSE && stripos($value,'e')===FALSE){
                $value=intval($value);
            } else {
                $value=$this->str2float($value);
            }
        }
        return $value;
    }

    public function isTrueConst($value,$condition):bool|NULL
    {
        // value conditioning
        $value=$this->value2numeric($value);
        if ($value===NAN){
            return NULL;
        }
        if (strpos($condition,'INF')!==FALSE){
            $value=intval($value);
            $value=(strpos($condition,'±')===FALSE)?$value:abs($value);
            $const=INF;
        } else if (strpos($condition,'0')!==FALSE){
            if (strpos($condition,'=')!==FALSE){
                $value=round($value,self::COMPARE_EQUAL_PRECISION);
            }
            $const=0;
        } else {
            return NULL;
        }
        $condition=trim($condition,'INFNAN±0');
        // comparison
        return $this->isTrue($value,$const,$condition);
    }

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
        } else if ($condition==='containsRegexMatch'){
            return boolval(preg_match('/'.(string)$valueB.'/',(string)$valueA,$matches));
        } else if ($condition==='!containsRegexMatch'){
            return boolval(preg_match('/'.(string)$valueB.'/',(string)$valueA,$matches))===FALSE;
        } else if ($condition==='TRUE'){
            return TRUE;
        } else if ($condition==='FALSE'){
            return FALSE;
        }
        $valueA=$this->value2numeric($valueA);
        $valueB=$this->value2numeric($valueB);
        if ($valueA===NAN || $valueB===NAN){
            return NULL;
        }
        // numeric tests
        if ($condition==='>'){
            return $valueA>$valueB;
        } else if ($condition==='=='){
            return round($valueA,self::COMPARE_EQUAL_PRECISION)==round($valueB,self::COMPARE_EQUAL_PRECISION);
        } else if ($condition==='!='){
            return round($valueA,self::COMPARE_EQUAL_PRECISION)!=round($valueB,self::COMPARE_EQUAL_PRECISION);
        } else if ($condition==='<'){
            return $valueA<$valueB;
        } else if ($condition==='&&'){
            return boolval($valueA&$valueB);
        } else if ($condition==='||'){
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
            $valueAstr=strval($valueA);
            $valueBstr=strval($valueB);
            $valueA=$this->value2numeric($valueA);
            $valueB=$this->value2numeric($valueB);
            if ($valueA===NAN || $valueB===NAN){
                $result=NAN;
            } else if ($operation==='-'){
                $result=$valueA-$valueB;
            } else if ($operation==='+'){
                $result=$valueA+$valueB;
            } else if ($operation==='*'){
                $result=$valueA*$valueB;
            } else if ($operation==='pow'){
                $result=pow($valueA,$valueB);
            } else if ($operation==='|'){
                $result=intval($valueA)|intval($valueB);
            } else if ($operation==='&'){
                $result=intval($valueA)&intval($valueB);
            } else if ($operation==='/'){
                if ($valueB==0){
                    $result=($valueA<0)?(-INF):INF; // avoid division by zero
                } else {
                    $result=$valueA/$valueB;
                }
            } else if ($operation==='%'){
                if ($valueB==0){
                    $result=($valueA<0)?(-INF):INF; // avoid division by zero
                } else {
                    $result=$valueA%$valueB;
                }
            } else if ($operation==='regexMatch'){
                preg_match('/'.$valueBstr.'/',$valueAstr,$match);
                $result=$match[0]??NULL;
            }
        }
        $result=($result===NULL)?'NULL':$result;
        $result=($result===NAN)?'NAN':$result;
        $result=($result===INF)?'INF':$result;
        $result=($result===-INF)?'-INF':$result;
        $result=($result===FALSE)?'FALSE':$result;
        $result=($result===TRUE)?'TRUE':$result;
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