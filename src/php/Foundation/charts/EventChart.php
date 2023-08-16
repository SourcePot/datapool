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

class EventChart{
    
    private $oc;
    private $id;
    private $props;
    
    private $tracesArr=array();
    private $rangesArr=array();
    
    public function __construct($oc,$props=array()){
        $this->oc=$oc;
        $this->id='chart-'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(8);
        $propsTemplate=array('timespan'=>3600,'maxTimestamp'=>time(),'width'=>1000,'height'=>800,'margin'=>array(25,100,60,5));
        $this->props=array_merge($propsTemplate,$props);
        //
        require_once(__DIR__.'/Axis.php');
        require_once(__DIR__.'/Tools.php');
    }
    
    /**
    * @return NULL
    * This method adds an event array('name'=>'...','timestamp'=>...,'value'=>...) to the traceArr. 
    */
    public function addEvent($event){
        $value=floatval($event['value']);
        $this->tracesArr[$event['name']][]=$event;
        if (isset($this->rangesArr[$event['name']])){
            if ($this->rangesArr[$event['name']]['min']>$value){$this->rangesArr[$event['name']]['min']=$value;}
            if ($this->rangesArr[$event['name']]['max']<$value){$this->rangesArr[$event['name']]['max']=$value;}
        } else {
            $this->rangesArr[$event['name']]=array('min'=>0,'max'=>$value);
        }
    }

    private function getPlotSvg($props=array()){
        $propsTemplate=array('trace'=>array('height'=>50,'element'=>array('tag'=>'rect','width'=>6,'stroke'=>'none','fill'=>'#00a')),
                             'point'=>array('element'=>array('tag'=>'circle','r'=>3,'stroke'=>'none','stroke'=>'none','fill'=>'#000')),
                             'label'=>array('height'=>13,'element'=>array('tag'=>'text')),
                             'plot'=>array('element'=>array('tag'=>'rect','fill'=>'none','stroke'=>'#aaa')),
                            );
        $props=array_replace_recursive($propsTemplate,$props);
        $this->props['height']=count($this->tracesArr)*$props['trace']['height']+$this->props['margin'][0]+$this->props['margin'][2];
        // add x-axis
        $xRangeIn=array($this->props['maxTimestamp']-$this->props['timespan'],$this->props['maxTimestamp']);
        $xRangeOut=array($this->props['margin'][3],$this->props['width']-$this->props['margin'][1]);
        $xAxis=new \SourcePot\Datapool\Foundation\Charts\Axis($this->oc);
        $xAxis->rangeIn($xRangeIn);
        $xAxis->rangeOut($xRangeOut);
        $xAxis->dataType('datetime');
        // add trace
        //$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($this->rangesArr);
        $chartTools=new \SourcePot\Datapool\Foundation\Charts\Tools($this->oc);
        $svg='';
        $yPos0=$this->props['margin'][0];
        foreach($this->tracesArr as $name=>$events){
            $rangeIn=array($this->rangesArr[$name]['min'],$this->rangesArr[$name]['max']);
            $rangeOut=array('#2cff0000','#0000ff70');
            //
            $traceId='trace-'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(8);
            $plotArr=$props['plot']['element'];
            $plotArr['x']=$this->props['margin'][3];
            $plotArr['y']=$yPos0;
            $plotArr['width']=$this->props['width']-$this->props['margin'][1]-$this->props['margin'][3];
            $plotArr['height']=$props['trace']['height'];
            $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($plotArr);
            $nameArr=$props['label']['element'];
            $nameArr['x']=$this->props['margin'][3]+5;
            $nameArr['y']=$yPos0+$props['label']['height'];
            $nameArr['element-content']=$name;
            $nameArr['keep-element-content']=TRUE;
            $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($nameArr);
            foreach($events as $eventIndex=>$event){
                $xPos0=$xAxis->scale($event['timestamp']);
                $eventId=$traceId.'-'.$eventIndex;
                $eventArr=$props['trace']['element'];
                $eventArr['x']=$xPos0-intval($props['trace']['element']['width']/2);
                $eventArr['y']=$yPos0+$props['label']['height'];
                $eventArr['height']=$props['trace']['height']-$props['label']['height'];
                $rgba=$chartTools->scaleRgb($rangeIn,$rangeOut,$event['value']);
                $eventArr['fill']=substr($rgba,0,7);
                $eventArr['fill-opacity']=hexdec(substr($rgba,7,2))/255;
                $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($eventArr);
                $pointArr=$props['point']['element'];
                $pointArr['id']=$eventId;
                $pointArr['cx']=$xPos0;
                $pointArr['cy']=$yPos0+$props['trace']['height'];
                $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($pointArr);
            }
            $yPos0+=$props['trace']['height'];
        }
        $svg.=$xAxis->getAxis('x',array('axis'=>array('shift'=>$yPos0)));
        return $svg;
    }    
    
    public function getChart($caption=FALSE,$props=array()){
        $svg='';
        $html='';
        // get plot area and traces
        $svg.=$this->getPlotSvg();
        // add chart and finalize svg
        $propsTemplate=array('caption'=>array('margin'=>5,'fontSize'=>16,'element'=>array('tag'=>'text','style'=>array('font'=>"{{captionFontSize}}px sans-serif"))),
                             'chart'=>array('element'=>array('tag'=>'svg','id'=>$this->id,'width'=>$this->props['width'],'height'=>$this->props['height'],'keep-element-content'=>TRUE)),
                            );
        $props=array_replace_recursive($propsTemplate,$props);
        if ($caption){
            $coord=array('x'=>0.5*$props['chart']['element']['width']-0.3*$props['caption']['fontSize']*strlen($caption),
                         'y'=>$props['caption']['margin']+0.9*$props['caption']['fontSize']
                        );
            $captionArr=array_merge($props['caption']['element'],$coord);
            $captionArr['element-content']=$caption;
            $captionSvg=$this->oc['SourcePot\Datapool\Foundation\Element']->element($captionArr);
            $svg.=str_replace('{{captionFontSize}}',strval($props['caption']['fontSize']),$captionSvg);
        }
        $chartArr=$props['chart']['element'];
        $chartArr['element-content']=$svg;
        $chartArr['viewBox']='0 0 '.$props['chart']['element']['width'].' '.$props['chart']['element']['height'];
        $svg=$this->oc['SourcePot\Datapool\Foundation\Element']->element($chartArr);
        // download chart
        $callingFunction=md5($caption);
        $formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,$callingFunction);
        if (isset($formData['cmd']['download'])){
            header('Content-Type: image/svg+xml');
            header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-zA-Z0-9]/','_',$caption).'.svg"');
            header('Content-Length: '.strlen($svg));
            echo $svg;
            exit;
        }
        // add legend
        $html.=$svg;
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'button','element-content'=>'&#8892;','keep-element-content'=>TRUE,'key'=>array('download'),'callingClass'=>__CLASS__,'callingFunction'=>$callingFunction,'excontainer'=>TRUE));
        //$html.=$plot->getLegend($this->tracesArr);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE));
        return $html;
    }
}
?>