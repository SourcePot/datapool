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
use SourcePot\Datapool\Foundation\Charts\Tools;

class Axis{
    
    private $oc;
    private $oTools;
    private $rangeIn=FALSE;
    private $rangeOut=FALSE;
    private $dataType='float';
    private $tickLabelAlias=FALSE;
    private $params=array();
    private $isOrdninal=FALSE;
    private $isHistogram=FALSE;
    private $tickPosArr=array();
    
    public function __construct($oc){
        $this->oc=$oc;
        $this->oTools=new Tools($oc);
    }

    public function getRangeIn(){return $this->rangeIn;}
    public function getRangeOut(){return $this->rangeOut;}
    public function getDataType(){return $this->dataType;}
    public function getTickLabelAlias(){return $this->tickLabelAlias;}
    public function getLinearParams(){return $this->params;}
    public function isOrdninal(){return $this->isOrdninal;}
    public function isHistogram(){return $this->isHistogram;}
    public function getTickPosArr(){return $this->tickPosArr;}
    
    public function rangeIn($range){
        $this->rangeIn=$range;
        $this->isOrdninal=!is_numeric(current($range));
        $this->isHistogram=!$this->isOrdninal && count($range)>2;
        if (!empty($this->rangeOut)){
            $this->calcParams($this->rangeIn,$this->rangeOut);
        }
        return $this->rangeIn;
    }
 
    public function rangeOut($range){
        $this->rangeOut=$range;
        if (!empty($this->rangeIn)){
            $this->calcParams($this->rangeIn,$this->rangeOut);
        }
        return $this->rangeOut;
    }
    
    public function tickLabelAlias($tickLabelAlias){
        $this->tickLabelAlias=$tickLabelAlias;
    }
    
    public function dataType($dataType){
        $this->dataType=$dataType;
    }

    private function calcParams($rangeIn,$rangeOut){
        $this->params=array();
        if ($this->isOrdninal || $this->isHistogram){
            $this->params['scaler']=($rangeOut[1]-$rangeOut[0])/(count($rangeIn)-1);
            $this->params['offset']=$rangeOut[0];
        } else {
            $rangeInRange=$rangeIn[0]-$rangeIn[1];
            if ($rangeIn[0]==$rangeIn[1]){$rangeInRange=0.00000001;}
            $this->params['scaler']=($rangeOut[0]-$rangeOut[1])/$rangeInRange;
            $this->params['offset']=$rangeOut[0]-$this->params['scaler']*$rangeIn[0];
        }
        return $this->params;
    }

    public function scale($value){
        if ($this->isOrdninal || $this->isHistogram){
            $value=$this->oTools->getMatchIndex($this->rangeIn,$value);
        }
        $scaledValue=$this->params['scaler']*$value+$this->params['offset'];
        return $scaledValue;
    }
    
    public function getAxis($type='x',$props=array()){
        $propsTemplate=array('tick'=>array('count'=>5,'halfLength'=>4,'element'=>array('tag'=>'line','stroke'=>'black','stroke-width'=>1,'stroke-linecap'=>'butt')),
                             'axis'=>array('shift'=>50,'hidden'=>FALSE,'element'=>array('tag'=>'line','stroke'=>'black','stroke-width'=>2,'stroke-linecap'=>'butt')),
                             'tickLabel'=>array('margin'=>10,'fontSize'=>12,'element'=>array('style'=>array('font'=>"{{tickLableFontSize}}px sans-serif"),'tag'=>'text','keep-element-content'=>TRUE)),
                             );
        $props=array_replace_recursive($propsTemplate,$props);
        if ($this->isHistogram || $this->isOrdninal){
            $props['tick']['count']=count($this->rangeIn);
        }
        // draw axis
        $invType=($type=='x')?'y':'x';
        $coord=array($type.'1'=>$this->rangeOut[0],$type.'2'=>$this->rangeOut[1],$invType.'1'=>$props['axis']['shift'],$invType.'2'=>$props['axis']['shift']);
        $axisArr=array_merge($props['axis']['element'],$coord);
        $svg=$this->oc['SourcePot\Datapool\Foundation\Element']->element($axisArr);
        // draw ticks and tick label
        for($tick=0;$tick<$props['tick']['count'];$tick++){
            if ($this->isOrdninal || $this->isHistogram){
                $value=$this->rangeIn[$tick];
            } else {
                $value=$this->rangeIn[0]+($this->rangeIn[1]-$this->rangeIn[0])*$tick/($props['tick']['count']-1);
            }
            // draw tick
            $pos=round($this->scale($value));
            $this->tickPosArr[]=$pos;
            $coord=array($type.'1'=>$pos,$type.'2'=>$pos,$invType.'1'=>$props['axis']['shift']-$props['tick']['halfLength'],$invType.'2'=>$props['axis']['shift']+$props['tick']['halfLength']);
            $tickArr=array_merge($props['tick']['element'],$coord);
            $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($tickArr);
            // draw tick label
            $elementContent=$this->oTools->value2label($value,$this->dataType,$this->tickLabelAlias);
            $coord=array($type=>$pos,$invType=>$props['axis']['shift']);
            if ($type=='x'){
                $coord['x']=$coord['x']-round(0.3*strlen($elementContent)*$props['tickLabel']['fontSize']);
                $coord['y']=$coord['y']+round(0.3*$props['tickLabel']['fontSize'])+$props['tickLabel']['margin']+$props['tick']['halfLength'];
            } else {
                $coord['x']=$coord['x']-round(0.4*strlen($elementContent)*$props['tickLabel']['fontSize'])-($props['tickLabel']['margin']+$props['tick']['halfLength']);
                $coord['y']=$coord['y']+round(0.3*$props['tickLabel']['fontSize']);
            }
            $labelArr=array_merge($props['tickLabel']['element'],$coord);
            $labelArr['element-content']=$elementContent;
            $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($labelArr);
        }
        $svg=str_replace('{{tickLableFontSize}}',strval($props['tickLabel']['fontSize']),$svg);
        if (!empty($props['axis']['hidden'])){$svg='';}
        return $svg;
    }
    
    public function debugState(){
        $debugArr=array('rangeIn'=>$this->rangeIn,
                        'rangeOut'=>$this->rangeOut,
                        'params'=>$this->params,
                        'state'=>array('isOrdninal'=>$this->isOrdninal,'isHistogram'=>$this->isHistogram),
                        );
        $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
    }
    
}
?>