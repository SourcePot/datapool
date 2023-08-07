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
            $value=date("Y-m-d m:i:s",$value);
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
    
}
?>