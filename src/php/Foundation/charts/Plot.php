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

class Plot{
    
    private $oc;
    private $id;
    private $oTools;
    
    function __construct($oc){
        $this->oc=$oc;
        $this->id=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(8);
        $this->oTools=new Tools($oc);
    }
    
    public function getPlotArea(Axis $axisX,Axis $axisY,$props=array()){
        $propsTemplate=array('plotArea'=>array('element'=>array('tag'=>'rect','fill'=>'#fff')),
                             'grid'=>array('x'=>array('element'=>array('tag'=>'line','stroke'=>'#000','stroke-dasharray'=>'1 4')),
                                           'y'=>array('element'=>array('tag'=>'line','stroke'=>'#000','stroke-dasharray'=>'1 4')),
                                           ),
                             'cursor'=>array('element'=>array('tag'=>'line','stroke'=>'#0f0','stroke-dasharray'=>'4 1')),
                             );
        $props=array_merge($propsTemplate,$props);
        $rangeOut=array('x'=>$axisX->getRangeOut(),'y'=>$axisY->getRangeOut());
        sort($rangeOut['x']);
        sort($rangeOut['y']);
        // get plotarea cutt off 
        $rectArr=array('tag'=>'rect','x'=>$rangeOut['x'][0],'y'=>$rangeOut['y'][0],'width'=>$rangeOut['x'][1]-$rangeOut['x'][0],'height'=>$rangeOut['y'][1]-$rangeOut['y'][0]);
        $svg=$this->oc['SourcePot\Datapool\Foundation\Element']->element($rectArr); 
        $clipPathArr=array('tag'=>'clipPath','id'=>"plotarea",'element-content'=>$svg,'keep-element-content'=>TRUE);
        $svg=$this->oc['SourcePot\Datapool\Foundation\Element']->element($clipPathArr); 
        $defsArr=array('tag'=>'defs','element-content'=>$svg,'keep-element-content'=>TRUE);
        $svg=$this->oc['SourcePot\Datapool\Foundation\Element']->element($defsArr); 
        // get plot area background
        $rectArr=array_merge($rectArr,$props['plotArea']['element']);
        $rectArr['id']='plot-'.$this->id;
        $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($rectArr); 
        // get x/y-grid
        $invDim=array('x'=>'y','y'=>'x');
        $gridPosArr=array('x'=>$axisX->getTickPosArr(),'y'=>$axisY->getTickPosArr());
        foreach($gridPosArr as $dim=>$posArr){
            $gridArr=$props['grid'][$dim]['element'];
            foreach($posArr as $pos){
                $gridArr[$dim.'1']=$gridArr[$dim.'2']=$pos;
                $gridArr[$invDim[$dim].'1']=$rangeOut[$invDim[$dim]][0];
                $gridArr[$invDim[$dim].'2']=$rangeOut[$invDim[$dim]][1];
                $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($gridArr); 
            }
        }
        // get cursor
        $cursorArr=$props['cursor']['element'];
        $cursorArr['id']='cursor-'.$this->id;
        $cursorArr['x1']=$rangeOut['x'][0];
        $cursorArr['x2']=$rangeOut['x'][0];
        $cursorArr['y1']=$rangeOut['y'][0];
        $cursorArr['y2']=$rangeOut['y'][1];
        $svg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($cursorArr); 
        return $svg;
    }
    public function getLegend($traces,$props=array()){
        $html='';
        $spanArr=array('tag'=>'span','style'=>array('display'=>'inherit'),'keep-element-content'=>TRUE,'style'=>array('float'=>'left','clear'=>'both','display'=>'none'));
        $matrix=array('Name'=>array(),'Trace'=>array(),'Selected'=>array());
        foreach($traces as $traceId=>$trace){
            $dataset=$trace->getDataset();
            $matrix['Name'][$traceId]=$trace->getName();
            $matrix['Trace'][$traceId]='&fnof;('.$dataset['x']['label'].','.$dataset['y']['label'].')';
            $matrix['Selected'][$traceId]='';
            foreach($dataset['data'] as $datasetIndex=>$datapoint){
                $spanArr['itemid']=$trace->getId().'-'.$datasetIndex;
                $spanArr['element-content']=$this->oTools->value2label($datapoint['x'],$dataset['x']['dataType']);
                $spanArr['element-content'].=', '.$this->oTools->value2label($datapoint['y'],$dataset['y']['dataType']);
                $matrix['Selected'][$traceId].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($spanArr);
            }
        }
        $html.=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>'Legend','keep-element-content'=>TRUE,'hideHeader'=>TRUE,'hideKeys'=>FALSE,'class'=>'max-content'));
        $html.=$this->embedJs();
        return $html;
    }

	private function embedJs(){
		$classWithNamespaceComps=explode('\\',__CLASS__);
		$class=array_pop($classWithNamespaceComps);
		$jsFile=__DIR__.'/'.$class.'.js';
        if (!is_file($jsFile)){
            file_put_contents($jsFile,'jQuery(document).ready(function(){});');
        }
		return '<script>'.file_get_contents($jsFile).'</script>';
	}
    
}
?>