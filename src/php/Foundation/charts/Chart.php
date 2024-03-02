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

class Chart{
    
    private $oc;
    private $id;
    private $props;
    
    private $xAxisArr=array();
    private $xAxisHashes=array();
    
    private $yAxisArr=array();
    private $yAxisHashes=array();
    
    private $tracesArr=array();
    private $tracesSvg='';
    
    public function __construct($oc,$props=array()){
        $this->oc=$oc;
        $this->id='chart-'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(8);
        $propsTemplate=array('width'=>1000,'height'=>800,'margin'=>array(25,50,60,90));
        $this->props=array_merge($propsTemplate,$props);
        //
        require_once(__DIR__.'/Axis.php');
        require_once(__DIR__.'/Plot.php');
        require_once(__DIR__.'/Tools.php');
        require_once(__DIR__.'/Trace.php');
    }

    public function addTrace($trace,$traceProps=array()){
        $traceId='trace-'.$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(8);
        $dataset=$trace->getDataset();
        // add x-axis
        $xRangeOut=array($this->props['margin'][3],$this->props['width']-$this->props['margin'][1]);
        $this->xAxisArr[$traceId]=new \SourcePot\Datapool\Foundation\Charts\Axis($this->oc);
        $this->xAxisArr[$traceId]->rangeOut($xRangeOut);
        $xAxisHash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash(array($dataset['x']['range'],$xRangeOut));
        $xAxisHidden=(isset($this->xAxisHashes[$xAxisHash]))?TRUE:FALSE;
        $this->xAxisHashes[$xAxisHash]=TRUE;
        // add y-axis
        $yRangeOut=array($this->props['height']-$this->props['margin'][2],$this->props['margin'][0]);
        $this->yAxisArr[$traceId]=new \SourcePot\Datapool\Foundation\Charts\Axis($this->oc);
        $this->yAxisArr[$traceId]->rangeOut($yRangeOut);
        $yAxisHash=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash(array($dataset['y']['range'],$yRangeOut));
        $yAxisHidden=(isset($this->yAxisHashes[$yAxisHash]))?TRUE:FALSE;
        $this->yAxisHashes[$yAxisHash]=TRUE;
        // get color
        $rgb=$this->oc['SourcePot\Datapool\Tools\MiscTools']->var2color(array($dataset['x']['label'],$dataset['y']['label']),3,FALSE,FALSE);
        // add trace
        $props=array('bar'=>array('element'=>array('fill'=>$rgb,'stroke'=>$rgb)),
                     'path'=>array('element'=>array('fill'=>$rgb,'stroke'=>$rgb)),
                     'xAxis'=>array('shift'=>30*(count($this->xAxisHashes)-1),'hidden'=>$xAxisHidden,'element'=>array('stroke'=>$rgb)),
                     'yAxis'=>array('shift'=>50*(count($this->yAxisHashes)-1),'hidden'=>$yAxisHidden,'element'=>array('stroke'=>$rgb)),
                    );
        $props=array_replace_recursive($props,$traceProps);
        $this->tracesArr[$traceId]=$trace;
        $this->tracesSvg.=$trace->getTrace($this->xAxisArr[$traceId],$this->yAxisArr[$traceId],$props);
    }
    
    public function getChart($caption=FALSE,$props=array()){
        $propsTemplate=array('caption'=>array('margin'=>5,'fontSize'=>16,'element'=>array('tag'=>'text','style'=>array('font'=>"{{captionFontSize}}px sans-serif"))),
                             'chart'=>array('element'=>array('tag'=>'svg','id'=>$this->id,'width'=>$this->props['width'],'height'=>$this->props['height'],'keep-element-content'=>TRUE)),
                            );
        $props=array_replace_recursive($propsTemplate,$props);
        $svg='';
        $html='';
        // get plot area and traces
        reset($this->xAxisArr);
        reset($this->yAxisArr);
        $plot=new \SourcePot\Datapool\Foundation\Charts\Plot($this->oc);
        $xAxis=current($this->xAxisArr);
        $yAxis=current($this->yAxisArr);
        if (is_bool($xAxis) || is_bool($yAxis)){return '<p>Axis could not be drawn...</p>';}
        $svg.=$plot->getPlotArea($xAxis,$yAxis);
        $svg.=$this->tracesSvg;
        // add chart and finalize svg
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
        $html.=$plot->getLegend($this->tracesArr);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE));
        return $html;
    }
}
?>