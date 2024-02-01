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
    
    public function init(array $oc)
    {
        $this->oc=$oc;
    }

    public function addExif2entry(array $entry,string $file):array
    {
        $entry=$this->oc['SourcePot\Datapool\Tools\MediaTools']->addExif2entry($entry,$file);
        $entry=$this->addMimeType($entry);
        $entry=$this->addOrientation($entry);
        $entry=$this->addCamera($entry);
        if (empty($entry['Content']['Address']['Town'])){$entry=$this->addGPS($entry);}
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
        $defs=array('Model'=>'Model','Make'=>'Make',
                    'XResolution'=>'XResolution','YResolution'=>'YResolution','Compression'=>'Compression',
                    'ISOSpeedRatings'=>'ISOSpeedRatings','FNumber'=>'FNumber','ExposureTime'=>'ExposureTime',
                    'FocalLength'=>'FocalLength','DigitalZoomRatio'=>'DigitalZoomRatio','ShutterSpeedValue'=>'ShutterSpeedValue'
                    );
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
        if (isset($entry['Params']['Geo'])){$oldGeo=$entry['Params']['Geo'];} else {$oldGeo=array('lat'=>9999,'lon'=>9999);}
        // get lat and lon from exif
        $defs=array('lat'=>'GPSLatitude','lon'=>'GPSLongitude','alt'=>'GPSAltitude');        
        foreach($defs as $targetKey=>$sourceKey){
            if (!isset($entry['exif'][$sourceKey])){
                if (isset($entry['Params']['Geo'][$targetKey])){unset($entry['Params']['Geo'][$targetKey]);}
                continue;
            }
            $entry['Params']['Geo'][$targetKey]=$this->degMinSec2float($entry['exif'][$sourceKey],FALSE);
            unset($entry['exif'][$sourceKey]);
        }
        if (!isset($entry['Params']['Geo']['lat']) || !isset($entry['Params']['Geo']['lon'])){return $entry;}
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
        $exifDateTime='';
        if (isset($entry['exif']['GPSDateStamp'])){
            $exifDateTime=str_replace(':','-',$entry['exif']['GPSDateStamp']);
            if (isset($entry['exif']['GPSTimeStamp'])){
                $exifDateTime.=' '.$this->time2str($entry['exif']['GPSTimeStamp']);
            } else {
                $exifDateTime.=' 12:00:00';
            }
            $pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
            $dateTime=\DateTime::createFromFormat('Y-m-d H:i:s',$exifDateTime,new \DateTimeZone('UTC'));
            $dateTime->setTimeZone(new \DateTimeZone($pageSettings['pageTimeZone']));
            $entry['Date']=$dateTime->format('Y-m-d H:i:s');
            $entry['Params']['DateTime']['GPS']=$entry['Date'];
        }
        if (isset($entry['exif']['DateTime'])){
            $entry['Params']['DateTime']['DateTime']=$this->time2str($entry['exif']['DateTime']);
        }
        if (isset($entry['exif']['DateTimeDigitized'])){
            $entry['Params']['DateTime']['DateTimeDigitized']=$this->time2str($entry['exif']['DateTimeDigitized']);
        }
        if (isset($entry['exif']['DateTimeOriginal'])){
            $entry['Params']['DateTime']['DateTimeOriginal']=$this->time2str($entry['exif']['DateTimeOriginal']);
        }
        if (isset($entry['exif']['FileDateTime'])){
            $entry['Params']['DateTime']['FileDateTime']=$this->time2str($entry['exif']['FileDateTime']);
            $entry['Params']['File']['FileDateTime']=$entry['exif']['FileDateTime'];
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
        } else if (strpos((string)$time,':')!==FALSE){
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