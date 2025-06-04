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

class ExifTools{
    
    private $oc;
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function addExif2entry(array $entry,string $file):array
    {
        $entry=$this->oc['SourcePot\Datapool\Tools\MediaTools']->addExif2entry($entry,$file);
        $entry=$this->addMimeType($entry);
        $entry=$this->addOrientation($entry);
        $entry=$this->addCamera($entry);
        $entry=$this->addGPS($entry);
        $entry=$this->addDateTime($entry);
        unset($entry['exif']);
        return $entry;
    }
    
    private function addMimeType(array $entry):array
    {
        if (isset($entry['exif']['MimeType'])){
            $entry['Params']['File']['MIME-Type']=$entry['exif']['MimeType'];
            unset($entry['exif']['MimeType']);
        }
        return $entry;
    }
    
    private function addCamera(array $entry):array
    {
        $defs=['Model'=>'Model','Make'=>'Make',
                'XResolution'=>'XResolution','YResolution'=>'YResolution','Compression'=>'Compression',
                'ISOSpeedRatings'=>'ISOSpeedRatings','FNumber'=>'FNumber','ExposureTime'=>'ExposureTime',
                'FocalLength'=>'FocalLength','DigitalZoomRatio'=>'DigitalZoomRatio','ShutterSpeedValue'=>'ShutterSpeedValue',
                'ApertureValue'=>'ApertureValue','BrightnessValue'=>'BrightnessValue','ExposureBiasValue'=>'ExposureBiasValue',
                'MaxApertureValue'=>'MaxApertureValue','SubjectDistance'=>'SubjectDistance','SubjectDistanceRange'=>'SubjectDistanceRange',
                'MeteringMode'=>'MeteringMode','Flash'=>'Flash','FocalLengthIn35mmFilm'=>'FocalLengthIn35mmFilm',
                ];
        $entry['Params']['Camera']=[];
        foreach($defs as $targetKey=>$sourceKey){
            if (!isset($entry['exif'][$sourceKey])){continue;}
            $entry['exif'][$sourceKey]=$this->normalizeEncoding($entry['exif'][$sourceKey]);
            $entry['Params']['Camera'][$targetKey]=$this->degMinSec2float($entry['exif'][$sourceKey],FALSE);
            unset($entry['exif'][$sourceKey]);
        }
        return $entry;
    }
    
    private function addGPS(array $entry):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        if (isset($entry['Params']['Geo'])){
            $oldGeo=$entry['Params']['Geo'];
        } else {
            $oldGeo=['lat'=>9999,'lon'=>9999];
        }
        $entry['Params']['Geo']=[];
        // get GPS date time
        if (isset($entry['exif']['GPSTimeStamp']) && isset($entry['exif']['GPSDateStamp'])){
            // get UTC date time
            $gpsDateTime=str_replace(':','-',$entry['exif']['GPSDateStamp']);
            $gpsDateTime.=' '.str_pad(strval($this->fraction2float($entry['exif']['GPSTimeStamp'][0])),2,'0',STR_PAD_LEFT);
            $gpsDateTime.=':'.str_pad(strval($this->fraction2float($entry['exif']['GPSTimeStamp'][1])),2,'0',STR_PAD_LEFT);
            $gpsDateTime.=':'.str_pad(strval($this->fraction2float($entry['exif']['GPSTimeStamp'][2])),2,'0',STR_PAD_LEFT);
            // convert to database date time
            if (empty(trim($entry['exif']['GPSDateStamp'],'0:'))){
                $context['gpsDateTime']=$gpsDateTime;
                $this->oc['logger']->log('notice','Function "{class} &rarr; {function}()" GPS date "{gpsDateTime}" was invalid and won\'t be used as entry[Date].',$context);
            } else {
                $dateTime=\DateTime::createFromFormat('Y-m-d H:i:s',$gpsDateTime,new \DateTimeZone('UTC'));
                $dateTime->setTimeZone(new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
                $entry['Date']=$dateTime->format('Y-m-d H:i:s');
                $entry['Params']['DateTime']['GPS']=$entry['Date'];
            }
        }
        // get GPS data, e.g. lat and lon from exif
        $defs=['lat'=>'GPSLatitude','lon'=>'GPSLongitude','alt'=>'GPSAltitude','imgDirectionRef'=>'GPSImgDirectionRef','imgDirection'=>'GPSImgDirection','dateStamp'=>'GPSDateStamp','status'=>'GPSStatus','dop'=>'GPSDOP','mapDatum'=>'GPSMapDatum','satellites'=>'GPSSatellites'];        
        foreach($defs as $targetKey=>$sourceKey){
            if (!isset($entry['exif'][$sourceKey])){
                if (isset($entry['Params']['Geo'][$targetKey])){unset($entry['Params']['Geo'][$targetKey]);}
                continue;
            }
            $entry['Params']['Geo'][$targetKey]=$this->degMinSec2float($entry['exif'][$sourceKey],FALSE);
            unset($entry['exif'][$sourceKey]);
        }
        // skip further processing if GPS information is missing
        if (empty($entry['Params']['Geo'])){
            $entry['Params']['Address']=[];
            return $entry;
        }
        // if lon and lat are present, get multiplier from exif
        if (!empty($entry['exif']['GPSLatitudeRef'])){
            if (strcmp($entry['exif']['GPSLatitudeRef'],'S')===0){$entry['Params']['Geo']['lat']=-1*$entry['Params']['Geo']['lat'];}
        } else if (!empty($entry['exif']['GPSLatitudeMultiplier'])){
            $entry['Params']['Geo']['lat']=$entry['Params']['Geo']['lat']*$entry['exif']['GPSLatitudeMultiplier'];
        }
        if (!empty($entry['exif']['GPSLongitudeRef'])){
            if (strcmp($entry['exif']['GPSLongitudeRef'],'W')===0){$entry['Params']['Geo']['lon']=-1*$entry['Params']['Geo']['lon'];}
        } else if (!empty($entry['exif']['GPSLongitudeMultiplier'])){
            $entry['Params']['Geo']['lon']=$entry['Params']['Geo']['lon']*$entry['exif']['GPSLongitudeMultiplier'];
        }
        // get address if location has been updated
        if ($entry['Params']['Geo']['lat']!=$oldGeo['lat'] || $entry['Params']['Geo']['lon']!=$oldGeo['lon']){
            $entry=$this->oc['SourcePot\Datapool\Tools\GeoTools']->location2address($entry,'Address');
        }
        return $entry;
    }

    private function addDateTime(array $entry):array
    {
        foreach($entry['exif'] as $key=>$value){
            if (stripos($key,'DateTime')===FALSE){continue;}
            if (is_array($value)){continue;}
            $newKey=str_replace('DateTime','',$key);
            if (empty($newKey)){$newKey='EXIF';}
            $entry['Params']['DateTime'][$newKey]=$this->time2str($entry['exif'][$key]);
        }
        return $entry;
    }
    
    private function addOrientation(array $entry):array
    {
        if (isset($entry['exif']['Orientation'])){
            if ($entry['exif']['Orientation']===1){
                $entry['Params']['File']['Style class']='rotate0';
            } else if ($entry['exif']['Orientation']===2){
                $entry['Params']['File']['Style class']='rotate0 flippedY';
            } else if ($entry['exif']['Orientation']===3){
                $entry['Params']['File']['Style class']='rotate180';    
            } else if ($entry['exif']['Orientation']===4){
                $entry['Params']['File']['Style class']='rotate180 flippedY';    
            } else if ($entry['exif']['Orientation']===5){
                $entry['Params']['File']['Style class']='rotate90 flippedX';
            } else if ($entry['exif']['Orientation']===6){
                $entry['Params']['File']['Style class']='rotate90';
            } else if ($entry['exif']['Orientation']===7){
                $entry['Params']['File']['Style class']='rotate270 flippedX';
            } else if ($entry['exif']['Orientation']===8){
                $entry['Params']['File']['Style class']='rotate270';
            }
            unset($entry['exif']['Orientation']);
        }
        return $entry;
    }
    
    private function normalizeEncoding($exifValue)
    {
        if (is_string($exifValue)){
            $encoding=mb_detect_encoding($exifValue,mb_detect_order(),TRUE);
            if (is_string($encoding)){
                $exifValue=@iconv($encoding,"UTF-8//IGNORE",$exifValue);
            }
        }
        return $exifValue;
    }
    
    private function time2str($time):string
    {
        $timeStr='';
        if (is_array($time)){
            foreach($time as $index=>$value){
                $value=(string)$this->fraction2float($value,TRUE);
                if (strlen($value)<2){$value='0'.$value;}
                $timeStr.=$value.':';    
            }
            $timeStr=trim($timeStr,':');
        } else if (mb_strpos((string)$time,':')!==FALSE){
            $timeStr=$time;
        } else {
            $timeStr=date('Y-m-d H:i:s',$time);
        }
        return $timeStr;
    }
    
    public function degMinSec2float($degMinSec,bool $returnFloat=TRUE)
    {
        if (is_array($degMinSec)){
            $result=0;
            $multipliers=array(1,1/60,1/3600);
            foreach($multipliers as $index=>$multiplier){
                if (isset($degMinSec[$index])){
                    $result+=$multiplier*$this->fraction2float($degMinSec[$index],TRUE);
                }
            }
            return $result;
        } else {
            return $this->fraction2float($degMinSec,$returnFloat);    
        }
    }

    /**
    * This method calculates a fraction provided from a provided string,e.g. "... 12/13 ...".
    *
    * @param string|int|float|bool Input value for conversion to float  
    * @return   float if $returnFloat==TRUE and no division by zero, e.g. 0.5 for "These are 1/5 litres"; 
    *           false if $returnFloat==TRUE and division by zero, e.g. FALSE for "These are 1/0 litres";
    *           string if $returnFloat==FALSE e.g. "These are 0.5 litres" for "These are 1/5 litres" or; 
    *                     "These are 1/0 litres" for "These are 1/0 litres" 
    */
    public function fraction2float($str,bool $returnFloat=TRUE):float|string|bool
    {
        $str=strval($str);
        preg_match_all("/(\d*)(\/)(\d*)/",(string)$str,$matches);
        if (isset($matches[1][0]) && isset($matches[3][0])){
            $a=floatval($matches[1][0]);
            $b=floatval($matches[3][0]);
            if ($b==0){
                $fraction=$matches[0][0];
                if ($returnFloat){return FALSE;}
            } else {
                $fraction=$a/$b;
                if ($returnFloat){return $fraction;}
            }
            return str_replace($matches[0][0],(string)$fraction,$str);
        } if ($returnFloat){
            $float=preg_replace('/[^0-9\.]/','',$str);
            return floatval($float);
        } else {
            return $str;
        }
    }

}
?>