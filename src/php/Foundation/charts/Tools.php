<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation\Charts;

class Tools{
    
    private $oc;
    
    function __construct($oc){
        $this->oc=$oc;
    }

    public function getMatchIndex($ordinalScale=array('a','b','c','d','e','f'),$value='c'){
        // get equal/identical match
        $matchIndex=array_search($value,$ordinalScale);
        // get weak match
        if ($matchIndex===FALSE){
            if (is_numeric(current($ordinalScale))){
                $value=floatval($value);
                $minDistance=$matchIndex;
                foreach($ordinalScale as $index=>$category){
                    $distance=abs($value-$category);
                    if ($minDistance===FALSE || $minDistance>$distance){
                        $minDistance=$distance;
                        $matchIndex=$index;
                    }
                }
            } else {
                // no weak match for non-numeric values available
            }
        }
        return $matchIndex;
    }
    
    public function value2label($value,$dataType='float',$alias=array()){
        if (stripos($dataType,'date')!==FALSE){
            $pageTimeZone=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTimeZone');
            $dateTime=new \DateTime('@'.$value);
            $dateTime->setTimezone(new \DateTimeZone($pageTimeZone));
            $value=$dateTime->format('Y-m-d H:i:s');
        }
        if (is_numeric($value)){
            $label=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($value);
        } else if (isset($alias[$value])){
            $label=$alias[$value];
        } else {
            $label=$value;
        }
        $label=strval($label);
        return $label;
    }
    
    public function getScaleParams($rangeIn=array(0,1),$rangeOut=array(250,0)){
        $params=array();
        if ($rangeIn[0]==$rangeIn[1]){
            $params['scaler']=0;
            $params['offset']=$rangeOut[0];
        } else {
            $params['scaler']=($rangeOut[0]-$rangeOut[1])/($rangeIn[0]-$rangeIn[1]);
            $params['offset']=$rangeOut[0]-$params['scaler']*$rangeIn[0];
        }
        return $params;
    }
    
    public function scale($params,$value){
        $scaledValue=$params['scaler']*$value+$params['offset'];
        return $scaledValue;
    }
    
    public function scaleRgb($rangeIn=array(0,1),$rangeOut=array('#ffffff','#000000'),$value=0){
        $compArr=array();
        // range out hex to int
        foreach($rangeOut as $index=>$rangeOutValue){
            $rangeOutValue=trim($rangeOutValue,'#');
            $rangeOutValueComps=str_split($rangeOutValue,2);
            foreach($rangeOutValueComps as $compIndex=>$compValue){
                $compArr[$compIndex][$index]=hexdec($compValue);
            }
        }
        // scale all values and get result
        $scaledRgb='#';
        $scaledValues=array();
        foreach($compArr as $compIndex=>$rangeOut){
            $params=$this->getScaleParams($rangeIn,$rangeOut);
            $scaledValue=intval($this->scale($params,$value));
            $scaledValue=dechex($scaledValue);
            if (strlen($scaledValue)<2){$scaledValue='0'.$scaledValue;}
            $scaledRgb.=$scaledValue;
        }
        return $scaledRgb;
    }
    
}
?>