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
use SourcePot\Datapool\Foundation\Charts\Axis;
use SourcePot\Datapool\Foundation\Charts\Tools;

class Trace{
    
    private $oc;
    private $oTools;
    private $id='';
    private $name='';
    private $rangeHashes=array();
    private $datasetIndex=-1;
    private $dataset=array('x'=>array('invDim'=>'y','selector'=>'Date','range'=>FALSE,'dataType'=>'dateTime','label'=>'Datum'),
                           'y'=>array('invDim'=>'x','selector'=>'Test','range'=>FALSE,'dataType'=>'float','label'=>'Values'),
                           'data'=>array(),
                           );
    
    private $minMax=array('x'=>array(FALSE,FALSE),'y'=>array(FALSE,FALSE));
    
    function __construct($oc,$xDef=array(),$yDef=array(),$name=''){
        $this->oc=$oc;
        $this->oTools=new Tools($oc);
        $this->id='trace-'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(8);
        $this->name=$name;
        $this->dataset=array_replace_recursive($this->dataset,array('x'=>$xDef,'y'=>$yDef));
    }

    public function getId(){return $this->id;}
    public function getName(){return $this->name;}
    public function getDataset(){return $this->dataset;}
    
    public function addEntry($entry){
        $dims=array_keys($this->dataset);
        $datapoint=array();
        $flatEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($entry);
        unset($dims[2]);
        foreach($dims as $dim){
            foreach($flatEntry as $key=>$value){
                if (empty($datapoint[$dim]) && strpos($key,$this->dataset[$dim]['selector'])!==FALSE){
                    $datapoint[$dim]=$value;
                } else if (isset($flatEntry[$this->dataset[$dim]['selector']])){
                    $datapoint[$dim]=$flatEntry[$this->dataset[$dim]['selector']];
                    break;
                }
            }
        }
        $this->addDatapoint($datapoint); 
    }

    public function addDatapoint($datapoint){
        $dims=array_keys($this->dataset);
        // apply dataType
        $datapointValid=TRUE;
        foreach($dims as $dim){
            if (!isset($datapoint[$dim])){
                $datapointValid=FALSE;
                break;
            }
            if (stripos($this->dataset[$dim]['dataType'],'date')!==FALSE){
                $datapoint[$dim]=strtotime(strval($datapoint[$dim]));
            } else if (stripos($this->dataset[$dim]['dataType'],'float')!==FALSE){
                $datapoint[$dim]=floatval($datapoint[$dim]);
            } else if (stripos($this->dataset[$dim]['dataType'],'int')!==FALSE){
                $datapoint[$dim]=intval($datapoint[$dim]);
            }
            if ($this->minMax[$dim][0]===FALSE || $this->minMax[$dim][0]>$datapoint[$dim]){$this->minMax[$dim][0]=$datapoint[$dim];}
            if ($this->minMax[$dim][1]===FALSE || $this->minMax[$dim][1]<$datapoint[$dim]){$this->minMax[$dim][1]=$datapoint[$dim];}
        }
        // add datapoint to dataset
        $this->datasetIndex++;
        $this->dataset['data'][$this->datasetIndex]=$datapoint;
    }
    
    public function done(){
        $ordinalScale=FALSE;
        $dims=array_keys($this->dataset);
        unset($dims[2]);
        // complete range
        foreach($dims as $dim){
            $this->dataset[$dim]['range'][0]=(isset($this->dataset[$dim]['range'][0]))?$this->dataset[$dim]['range'][0]:$this->minMax[$dim][0];
            $this->dataset[$dim]['range'][1]=(isset($this->dataset[$dim]['range'][1]))?$this->dataset[$dim]['range'][1]:$this->minMax[$dim][1];
            if (count($this->dataset[$dim]['range'])>2){$ordinalScale=$dim;}
        }
        if ($ordinalScale){
            $dataArr=array();
            $dim=$ordinalScale;
            $invDim=$this->dataset[$dim]['invDim'];
            // init new data array
            foreach($this->dataset[$dim]['range'] as $datasetIndex=>$category){
                $dataArr[$datasetIndex][$dim]=0;
                $dataArr[$datasetIndex][$invDim]=$category;
            }
            // update data array from existig data array
            $max=0;
            foreach($this->dataset['data'] as $datapoint){
                $matchIndex=$this->oTools->getMatchIndex($this->dataset[$ordinalScale]['range'],$datapoint[$ordinalScale]);
                if ($matchIndex!==FALSE){
                    $dataArr[$matchIndex][$dim]++;
                    if ($max<$dataArr[$matchIndex][$dim]){$max=$dataArr[$matchIndex][$dim];}
                }
            }
            $this->dataset['data']=array();
            $newDataset=array($dim=>array('range'=>array(0,$max),'dataType'=>'int','label'=>'Frequency'),
                              $invDim=>array('range'=>$this->dataset[$dim]['range'],'dataType'=>'string','label'=>$this->dataset[$dim]['label']),
                              'data'=>$dataArr
                              );
            $this->dataset=array_merge($this->dataset,$newDataset);
        }
    }
    
    public function getTrace(Axis $axisX,Axis $axisY,$props=array()){
        $propsTemplate=array('bar'=>array('barWidth'=>0.7,'element'=>array('tag'=>'rect','stroke'=>'#000','fill'=>'#00f','fill-opacity'=>"0.4",'clip-path'=>'url(#plotarea)')),
                             'path'=>array('element'=>array('tag'=>'path','stroke'=>'#000','fill'=>'#00f','fill-opacity'=>"0.25",'clip-path'=>'url(#plotarea)')),
                             'circle'=>array('element'=>array('tag'=>'circle','r'=>4,'stroke'=>'#000','stroke-width'=>'2','fill'=>'none','clip-path'=>'url(#plotarea)','style'=>array('display'=>'none'))),
                             'xAxis'=>array('hidden'=>FALSE,'shift'=>0,'element'=>array('stroke'=>'#000')),
                             'yAxis'=>array('hidden'=>FALSE,'shift'=>0,'element'=>array('stroke'=>'#000')),
                            );
        $props=array_replace_recursive($propsTemplate,$props);
        $axisX->rangeIn($this->dataset['x']['range']);
        $axisY->rangeIn($this->dataset['y']['range']);
        $axisX->dataType($this->dataset['x']['dataType']);
        $axisY->dataType($this->dataset['y']['dataType']);
        $rangeOutX=$axisX->getRangeOut();
        $rangeOutY=$axisY->getRangeOut();
        $props['xAxis']['shift']=$rangeOutY[0]+$props['xAxis']['shift'];
        $props['yAxis']['shift']=$rangeOutX[0]-$props['yAxis']['shift'];
        $svg='';
        $svg.=$axisX->getAxis('x',array('axis'=>$props['xAxis']));
        $svg.=$axisY->getAxis('y',array('axis'=>$props['yAxis']));
        // check if there are categories
        $rectProp=array('x'=>'width','y'=>'height');
        if (count($this->dataset['x']['range'])>2){
            $tickRangeOut=round(abs($rangeOutX[1]-$rangeOutX[0])/count($this->dataset['x']['range']));
            $scaledBarWidth=$tickRangeOut*$props['bar']['barWidth'];
            $ordDim='x';
            $ordInvDim='y';
        } else if (count($this->dataset['y']['range'])>2){
            $tickRangeOut=round(abs($rangeOutY[1]-$rangeOutY[0])/count($this->dataset['y']['range']));
            $scaledBarWidth=$tickRangeOut*$props['bar']['barWidth'];
            $ordDim='y';
            $ordInvDim='x';
        }
        $traceArr=$props['bar']['element']['id']=$this->id;
        $traceArr=$props['path']['element']['id']=$this->id;
        $circleArr=$props['circle']['element'];
        $path='';
        $yPosStartOrg=($this->dataset['y']['range'][0]<=0 && $this->dataset['y']['range'][1]>=0)?0:$this->dataset['y']['range'][0];
        foreach($this->dataset['data'] as $datasetIndex=>$datapoint){
            if (isset($ordDim) && isset($ordInvDim)){
                $traceArr=$props['bar']['element'];
                if ($ordDim=='x'){
                    $traceArr[$ordDim]=$axisX->scale($datapoint[$ordDim])+0.5*$tickRangeOut-0.5*$props['bar']['barWidth']*$scaledBarWidth;
                    $invDimRangeOut=array($axisY->scale(0),$axisY->scale($datapoint[$ordInvDim]));
                } else {
                    $traceArr[$ordDim]=$axisY->scale($datapoint[$ordDim])+0.5*$tickRangeOut-0.5*$props['bar']['barWidth']*$scaledBarWidth;
                    $invDimRangeOut=array($axisX->scale(0),$axisX->scale($datapoint[$ordInvDim]));
                }
                if ($invDimRangeOut[1]>$invDimRangeOut[0]){
                    $traceArr[$ordInvDim]=$invDimRangeOut[0];
                    $traceArr[$rectProp[$ordInvDim]]=$invDimRangeOut[1]-$invDimRangeOut[0];
                } else {
                    $traceArr[$ordInvDim]=$invDimRangeOut[1];
                    $traceArr[$rectProp[$ordInvDim]]=$invDimRangeOut[0]-$invDimRangeOut[1];
                }
                $circleArr['c'.$ordDim]=$traceArr[$ordDim];
                $circleArr['c'.$ordInvDim]=$traceArr[$ordInvDim];
                $traceArr[$rectProp[$ordDim]]=$scaledBarWidth;
                $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($traceArr);
            } else {
                $xPos=$axisX->scale($datapoint['x']);
                $yPos=$axisY->scale($datapoint['y']);
                if (!isset($yPosStart)){
                    $yPosStart=$axisY->scale($yPosStartOrg);
                    $path.='M '.$xPos.','.$yPosStart;
                }
                $path.=' L '.$xPos.','.$yPos;    
                $circleArr['cx']=$axisX->scale($datapoint['x']);
                $circleArr['cy']=$axisY->scale($datapoint['y']);
            }
            $circleArr['id']=$this->id.'-'.$datasetIndex;
            $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($circleArr);
        }
        if (isset($yPosStart)){
            $path.='L '.$xPos.','.$yPosStart;
            $traceArr=$props['path']['element'];
            $traceArr['d']=$path;
            $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($traceArr);    
        }
        return $svg;
    }

    public function debugState(){
        $debugArr=array('id'=>$this->id,
                        'dataset'=>$this->dataset,
                        );
        $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
    }

}
?>