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

class LinearChart{
	
	private $oc;
	
	public function __construct($oc){
		$this->oc=$oc;
	}
	
	public function init($oc){
		$this->oc=$oc;
	}
	
	public function getTestChart($arr=array()){
		$arr=array('caption'=>array('element-content'=>'Test chart'));
		// add 1st trace
		$trace=$this->getTestTrace('test trace');
		$trace['bar']['show']=TRUE;
		$trace['show']=FALSE;
		$arr=$this->addTrace($arr,$trace);
		// add 2nd trace
		$trace=$this->getTestTrace('B');
		$trace['show']=TRUE;
		$trace['bar']['show']=FALSE;
		$trace['point']['show']=FALSE;
		$arr=$this->addTrace($arr,$trace);
		// compile chart
		$arr['html']=$this->chartSvg($arr);
		return $arr;
	}
	
	private function getTestTrace($name='test trace'){
		$dataType=array('test trace'=>'float','B'=>'dateTime');
		$trace=array('id'=>$name,
					 'name'=>$name,
					 'label'=>array(),
					 'stroke'=>'rgb('.mt_rand(0,200).','.mt_rand(0,200).','.mt_rand(0,200).')',
					 'x'=>array('dataType'=>$dataType[$name],'data'=>array()),
					 //'y'=>array('dataType'=>'float','data'=>array(),'scale'=>array('minValue'=>-1000,'maxValue'=>1000)),
					 'y'=>array('dataType'=>'float','data'=>array()),
					 );
		$phase=2000000000+mt_rand(0,2000000000)/50;
		$offset=mt_rand(-5000,5000);
		$frequ=mt_rand(100000,800000);
		for($i=0;$i<62;$i++){
			$trace['x']['data'][$i]=50000*$i+$phase;
			$trace['y']['data'][$i]=5000*sin($trace['x']['data'][$i]/$frequ)+$offset;
			$trace['label'][$i]='Index '.$i;
		}
		return $trace;
	}

	public function chartSvg($arr){
		// chart background settings
        $template=array('show'=>TRUE,'tag'=>'rect','x'=>'0','y'=>0,'width'=>$arr['chart']['width'],'height'=>$arr['chart']['height'],'fill'=>'white','stroke'=>'gray');
		$arr['background']=(isset($arr['background']))?$arr['background']:array();
		$arr['background']=array_replace_recursive($template,$arr['background']);
		$arr['svg']['background']=$this->getSvgElement($arr['background']);
		// caption settings
		$template=array('show'=>TRUE,'tag'=>'text','x'=>10,'y'=>10,'element-content'=>'','style'=>array('font'=>'16px Verdana, Helvetica, Arial, sans-serif'));
		$arr['caption']=(isset($arr['caption']))?$arr['caption']:array();
		$arr['caption']=array_replace_recursive($template,$arr['caption']);
		$captionFontSize=intval(preg_replace('/\D+/','',$arr['caption']['style']['font']))*0.55;
		$arr['caption']['x']=0.5*(intval($arr['chart']['width'])-strlen($arr['caption']['element-content'])*$captionFontSize);
		$arr['caption']['y']=2.5*$captionFontSize;
		$arr['svg']['caption']=$this->getSvgElement($arr['caption']);
		// compile chart
		$chartSvg='';
		foreach($arr['svg'] as $svgId=>$svg){
			$chartSvg.=$svg;
		}
		$arr['chart']['element-content']=$chartSvg;
		$chartSvg=$this->getSvgElement($arr['chart']);
		// download processing
		$formData=$this->oc['SourcePot\Datapool\Foundation\Element']->formProcessing(__CLASS__,__FUNCTION__);
		if (isset($formData['cmd']['download'])){
			header('Content-Type: image/svg+xml');
			header('Content-Disposition: attachment; filename="'.$arr['chart']['id'].'.svg"');
			header('Content-Length: '.strlen($chartSvg));
			echo $chartSvg;
			exit;
		}
		$downLoadBtn=array('tag'=>'button','key'=>array('download'),'element-content'=>'&#8892;','keep-element-content'=>TRUE,'excontainer'=>TRUE,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$chartSvg.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($downLoadBtn);
		return $chartSvg;
	}
	
	public function addTrace($arr,$trace){
		// chart settings
		if (!isset($arr['svg']['background'])){$arr['svg']['background']='';}
		if (!isset($arr['svg']['caption'])){$arr['svg']['caption']='';}
		$template=array('show'=>TRUE,'tag'=>'svg','id'=>'char-'.mt_rand(1,1000),'width'=>900,'height'=>500,'gaps'=>array(50,50,100,120),'keep-element-content'=>TRUE,'style'=>array('margin'=>'5px'));
		$arr['chart']=(isset($arr['chart']))?$arr['chart']:array();
		$arr['chart']=array_replace_recursive($template,$arr['chart']);
		// trace settings
		$template=array('show'=>TRUE,'tag'=>'path','id'=>$trace['id'],'stroke'=>"green",'fill-opacity'=>"0.25",'stroke-opacity'=>"0.8");
		$trace=array_replace_recursive($template,$trace);
		$trace['fill']=$trace['stroke'];
		$x0=$arr['chart']['gaps'][3];
		$x1=$arr['chart']['width']-$arr['chart']['gaps'][1];
		$y0=$arr['chart']['height']-$arr['chart']['gaps'][2];
		$y1=$arr['chart']['gaps'][0];
		$targetDomain=array('x'=>array('min'=>$x0,'max'=>$x1),'y'=>array('max'=>$y1,'min'=>$y0));
		$arr['svg']['plotarea']='<defs><clipPath id="plotarea"><rect x="'.$x0.'" y="'.$y1.'" width="'.($x1-$x0).'" height="'.($y0-$y1).'"/></clipPath></defs>';
		// point settings
		$template=array('show'=>TRUE,'tag'=>'circle','r'=>"2",'stroke'=>'black','fill'=>$trace['stroke'],'stroke-width'=>"1");
		$trace['point']=(isset($trace['point']))?$trace['point']:array();
		$trace['point']=array_replace_recursive($template,$trace['point']);
		// bar settings
		$template=array('show'=>FALSE,'tag'=>'rect','width'=>"5",'stroke'=>'black','fill'=>$trace['stroke'],'fill-opacity'=>"0.25",'stroke-width'=>"1");
		$trace['bar']=(isset($trace['bar']))?$trace['bar']:array();
		$trace['bar']=array_replace_recursive($template,$trace['bar']);
		// compile traces
		foreach(array('x','y','z') as $dimId){
			if (!isset($trace[$dimId])){continue;}
			// get ranges and ticks
			if (stripos($trace[$dimId]['dataType'],'time')!==FALSE){
				$traceArr=$this->arrFromTimeStamps($trace,$dimId);
			} else {
				$traceArr=$this->arrFromValues($trace,$dimId);
			}
			$sourceDomain=array('min'=>$traceArr['scale']['minValue'],'max'=>$traceArr['scale']['maxValue']);
			$mapping=$this->getMapping($sourceDomain,$targetDomain[$dimId]);
			$trace[$dimId]=$traceArr;
			$trace[$dimId]['mapping']=$mapping;
		}
		$trace['axis']['stroke']=$trace['tick']['stroke']=$trace['stroke'];
		$arr=$this->addAxis($arr,$trace,'x');
		$arr=$this->addAxis($arr,$trace,'y');
		// draw trace
		$path='';
		$arr['svg'][$trace['id']]='';
		foreach($trace['x']['data'] as $index=>$xValue){
			$yValue=$trace['y']['data'][$index];
			$xPos=$this->mapValue($trace['x']['mapping'],$xValue);
			$yPos=$this->mapValue($trace['y']['mapping'],$yValue);
			if (empty($path)){
				if ($trace['y']['scale']['minValue']>0){
					$startValue=$trace['y']['scale']['minValue'];
				} else if ($trace['y']['scale']['maxValue']<0){
					$startValue=$trace['y']['scale']['maxValue'];
				} else {
					$startValue=0;
				}
				$yPosStart=$this->mapValue($trace['y']['mapping'],$startValue);
				$path.='M '.$xPos.','.$yPosStart;
			}
			$path.=' L '.$xPos.','.$yPos;
			$arr['svg'][$trace['id']].=$this->getSvgElement($trace['point'],array('cx'=>$xPos,'cy'=>$yPos,'clip-path'=>'url(#plotarea)'));
			if ($yPosStart>$yPos){
				$arr['svg'][$trace['id']].=$this->getSvgElement($trace['bar'],array('x'=>$xPos-$trace['bar']['width']/2,'y'=>$yPos,'height'=>$yPosStart-$yPos,'clip-path'=>'url(#plotarea)'));
			} else {
				$arr['svg'][$trace['id']].=$this->getSvgElement($trace['bar'],array('x'=>$xPos-$trace['bar']['width']/2,'y'=>$yPosStart,'height'=>$yPos-$yPosStart,'clip-path'=>'url(#plotarea)'));
			}
		}
		$path.='L '.$xPos.','.$yPosStart;
		$arr['svg'][$trace['id']].=$this->getSvgElement($trace,array('d'=>$path,'clip-path'=>'url(#plotarea)'));	
		return $arr;
	}
	
	private function addAxis($arr,$trace,$dimId){
		// axis settings
		$template=array('show'=>TRUE,'tag'=>'path','stroke'=>'blue','stroke-width'=>'2');
		$trace['axis']=(isset($trace['axis']))?$trace['axis']:array();
		$trace['axis']=array_replace_recursive($template,$trace['axis']);
		// tick settings
		$template=array('show'=>TRUE,'tag'=>'path','stroke'=>'red','stroke-width'=>'2','tickLength'=>5);
		$trace['tick']=(isset($trace['tick']))?$trace['tick']:array();
		$trace['tick']=array_replace_recursive($template,$trace['tick']);
		// tick label settings
		$template=array('show'=>TRUE,'tag'=>'text','style'=>array('font'=>'10px Verdana, Helvetica, Arial, sans-serif'));
		$trace['tickLabel']=(isset($trace['tickLabel']))?$trace['tickLabel']:array();
		$trace['tickLabel']=array_replace_recursive($template,$trace['tickLabel']);
		// grid settings
		$template=array('show'=>TRUE,'tag'=>'path','stroke'=>'gray','stroke-dasharray'=>'2,2');
		$trace['grid']=(isset($trace['grid']))?$trace['grid']:array();
		$trace['grid']=array_replace_recursive($template,$trace['grid']);
		//
		$tickLabelFontSize=intval(preg_replace('/\D+/','',$trace['tickLabel']['style']['font']))*0.55;
		$svgId=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getHash($trace[$dimId]['scale'],TRUE);
		if (!isset($arr['svg'][$svgId])){
			$arr['axis']['count'][$dimId][$svgId]=TRUE;
			$arr['svg'][$svgId]='';
			$first=array();
			$offset=count($arr['axis']['count'][$dimId])-1;
			foreach($trace[$dimId]['scale']['ticks'] as $tick){
				if (strcmp($dimId,'x')===0){
					// x-axis with ticks
					$yShift=40*$offset;
					$xPos=$this->mapValue($trace['x']['mapping'],$tick['value']);
					$yPos=$this->mapValue($trace['y']['mapping'],$trace['y']['scale']['minValue'])+$yShift;
					$yMaxPos=$this->mapValue($trace['y']['mapping'],$trace['y']['scale']['maxValue'])+$yShift;
					if ($offset===0){
						$arr['svg'][$svgId].=$this->getSvgElement($trace['grid'],array('d'=>'M '.($xPos).','.$yPos.' L '.$xPos.','.$yMaxPos));
					}
					$arr['svg'][$svgId].=$this->getSvgElement($trace['tick'],array('d'=>'M '.($xPos).','.($yPos-$trace['tick']['tickLength']).' l 0,'.(2*$trace['tick']['tickLength'])));
					$arr['svg'][$svgId].=$this->getSvgElement($trace['tickLabel'],array('x'=>$xPos-strlen($tick['label'])*$tickLabelFontSize/2,'y'=>$yPos+20,'element-content'=>$tick['label']));
				} else if (strcmp($dimId,'y')===0){
					// y-axis with ticks
					$xShift=$trace[$dimId]['scale']['maxLabelLength']*(4+$tickLabelFontSize)*$offset;
					$xPos=$this->mapValue($trace['x']['mapping'],$trace['x']['scale']['minValue'])-$xShift;
					$xMaxPos=$this->mapValue($trace['x']['mapping'],$trace['x']['scale']['maxValue'])+$xShift;
					$yPos=$this->mapValue($trace['y']['mapping'],$tick['value']);
					if ($offset===0){
						$arr['svg'][$svgId].=$this->getSvgElement($trace['grid'],array('d'=>'M '.($xPos).','.$yPos.' L '.$xMaxPos.','.$yPos));
					}
					$arr['svg'][$svgId].=$this->getSvgElement($trace['tick'],array('d'=>'M '.($xPos-$trace['tick']['tickLength']).','.($yPos).' l '.(2*$trace['tick']['tickLength']).',0'));
					$arr['svg'][$svgId].=$this->getSvgElement($trace['tickLabel'],array('x'=>$xPos-strlen($tick['label'])*$tickLabelFontSize-$trace['tick']['tickLength']-2,'y'=>$yPos+$tickLabelFontSize/2,'element-content'=>$tick['label']));
				}
				if (empty($first)){$first=array('x'=>$xPos,'y'=>$yPos);}
			}
			$arr['svg'][$svgId].=$this->getSvgElement($trace['axis'],array('d'=>'M '.$first['x'].','.$first['y'].' L '.$xPos.','.$yPos));
			if (strcmp($dimId,'x')===0){$xPos+=10;} else {$yPos-=10;}
			$axisLabelOffset=0.8*$tickLabelFontSize*strlen($trace['name']);
			$arr['svg'][$svgId].=$this->getSvgElement($trace['tickLabel'],array('x'=>($xPos-$axisLabelOffset),'y'=>$yPos-1.2*$tickLabelFontSize,'element-content'=>$trace['name'],'style'=>array('font-weight'=>'bold','font-size'=>'0.9em'),'fill'=>$trace['axis']['stroke']));
		}
		return $arr;
	}
	
	private function getSvgElement($template,$element=array()){
		$element=array_merge($template,$element);
		if (empty($element['show'])){
			return '';
		} else {
			return $this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($element);
		}
	}
	
	private function arrFromTimeStamps($trace,$dimId){
		$pageSettings=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings();
		$timeStamps=$trace[$dimId]['data'];
		$template=array('timezone'=>$pageSettings['pageTimeZone'],'scale'=>array('tickCount'=>5,'axis'=>array()));
		$arr=array_replace_recursive($template,$trace[$dimId]);
		$arr['timeStampMin']=(isset($arr['timeStampMin']))?$arr['timeStampMin']:min($timeStamps);
		$arr['timeStampMax']=(isset($arr['timeStampMax']))?$arr['timeStampMax']:max($timeStamps);
		//
		$dateTemplate=array('year','month','day','hour','minute','second');
		$timeZone=new \DateTimeZone($arr['timezone']);
		$dateMax=new \DateTime('@'.$arr['timeStampMax'],$timeZone);
		$dateMin=new \DateTime('@'.$arr['timeStampMin'],$timeZone);
		$arr['dateTimeMin']=$dateMin->format('Y-m-d H:i:s');
		$arr['dateTimeMax']=$dateMax->format('Y-m-d H:i:s');
        $arr['dateTimeScaleMin']=preg_split('/[^0-9]+/',$arr['dateTimeMin']);
		$arr['dateTimeScaleMin']=array_combine($dateTemplate,$arr['dateTimeScaleMin']);
		$diff=$dateMin->diff($dateMax);
		$arr['dateTimeRange']=array($diff->format('%Y'),$diff->format('%m'),$diff->format('%d'),$diff->format('%H'),$diff->format('%i'),$diff->format('%s'));
		$arr['dateTimeRange']=array_combine($dateTemplate,$arr['dateTimeRange']);
		$dateMinArr=array('1970','01','01','00','00','00');
		$dateMinArr=array_combine($dateTemplate,$dateMinArr);
		$dateCompMultArr=array(0,12,31,24,60,60);
		$dateCompMultArr=array_combine($dateTemplate,$dateCompMultArr);
		$arr['toAddIntervall']=array();
		$availableTicks=0;
		$relevantKeyFound=FALSE;
		foreach($arr['dateTimeRange'] as $key=>$value){
			$availableTicks=$availableTicks*$dateCompMultArr[$key]+intval($arr['dateTimeRange'][$key]);
			if ($relevantKeyFound){
				$arr['dateTimeScaleMin'][$key]=$dateMinArr[$key];
			}
			if ($availableTicks>$arr['scale']['tickCount'] && !$relevantKeyFound){
				$relevantKeyFound=TRUE;
				$arr['toAddIntervall'][$key]=floor($availableTicks/$arr['scale']['tickCount']);
			} else {
				$arr['toAddIntervall'][$key]=0;
			}
		}
		$arr['dateTimeScaleMin']=$arr['dateTimeScaleMin']['year'].'-'.$arr['dateTimeScaleMin']['month'].'-'.$arr['dateTimeScaleMin']['day'].' '.$arr['dateTimeScaleMin']['hour'].':'.$arr['dateTimeScaleMin']['minute'].':'.$arr['dateTimeScaleMin']['second'];
		$tickDateTime=new \DateTime($arr['dateTimeScaleMin'],$timeZone);
		$tickIndex=0;
		$toAddIntervall='P'.$arr['toAddIntervall']['year'].'Y';
		$toAddIntervall.=$arr['toAddIntervall']['month'].'M';
		$toAddIntervall.=$arr['toAddIntervall']['day'].'D';
		$toAddIntervall.='T'.$arr['toAddIntervall']['hour'].'H';
		$toAddIntervall.=$arr['toAddIntervall']['minute'].'M';
		$toAddIntervall.=$arr['toAddIntervall']['second'].'S';
		$toAddIntervall=new \DateInterval($toAddIntervall);
		$arr['scale']['minValue']=$tickDateTime->getTimestamp();
		$arr['scale']['maxLabelLength']=0;
		$labelComps=array(array('date'=>'','time'=>''));
		while($tickIndex!==FALSE && $tickIndex<50){
			array_unshift($labelComps,array('date'=>$tickDateTime->format('Y-m-d'),'time'=>$tickDateTime->format('H:i:s')));
			if ($labelComps[0]['date']==$labelComps[1]['date']){
				$arr['scale']['ticks'][$tickIndex]['label']='';
			} else {
				$arr['scale']['ticks'][$tickIndex]['label']=$labelComps[0]['date'];
			}
			if ($labelComps[0]['time']==$labelComps[1]['time']){
				$arr['scale']['ticks'][$tickIndex]['label'].='';
			} else {
				$arr['scale']['ticks'][$tickIndex]['label'].=' '.$labelComps[0]['time'];
			}
			$arr['scale']['ticks'][$tickIndex]['label']=trim($arr['scale']['ticks'][$tickIndex]['label']);
			$arr['scale']['ticks'][$tickIndex]['value']=$tickDateTime->getTimestamp();
			$labelLength=strlen($arr['scale']['ticks'][$tickIndex]['label']);
			if ($labelLength>$arr['scale']['maxLabelLength']){$arr['scale']['maxLabelLength']=$labelLength;}
			if ($tickDateTime->getTimestamp()>$arr['timeStampMax']){$tickIndex=FALSE;}
			$arr['scale']['maxValue']=$tickDateTime->getTimestamp();
			$tickDateTime->add($toAddIntervall);
			$tickIndex++;
		}
		return $arr;
	}

	private function arrFromValues($trace,$dimId){
		$tickOptions=array(1000,500,250,125,100,50);
		$values=$trace[$dimId]['data'];
		$template=array('scale'=>array('tickCount'=>5,'tickUnit'=>'','isQuantity'=>FALSE,'maxLabelLength'=>0),'axis'=>array());
		$arr=array_replace_recursive($template,$trace[$dimId]);
		$arr['valuesMin']=(isset($arr['valuesMin']))?$arr['valuesMin']:min($values);
		$arr['valuesMax']=(isset($arr['valuesMax']))?$arr['valuesMax']:max($values);
		$arr['valuesRange']=$arr['valuesMax']-$arr['valuesMin'];
		$arr['valuesTickRange']=$arr['valuesRange']/$arr['scale']['tickCount'];
		$arr['tickOptionsScaler']=$this->decRangeMappingScaler($arr['valuesTickRange'],1000);
		$arr['scale']['tickRange']=$tickOptions[0]/$arr['tickOptionsScaler'];
		foreach($tickOptions as $tickOptionIndex=>$tickOption){
			if ($tickOption>$arr['valuesTickRange']*$arr['tickOptionsScaler']){
				$arr['scale']['tickRange']=$tickOption/$arr['tickOptionsScaler'];
			} else {
				$arr['scale']['minValue']=(isset($arr['scale']['minValue']))?$arr['scale']['minValue']:($arr['scale']['tickRange']*floor($arr['valuesMin']/$arr['scale']['tickRange']));
				$arr['scale']['maxValue']=(isset($arr['scale']['maxValue']))?$arr['scale']['maxValue']:($arr['scale']['tickRange']*ceil($arr['valuesMax']/$arr['scale']['tickRange']));
				break;
			}
		}
		$decimals=1-log10(1000/$arr['tickOptionsScaler']);
		if ($decimals<0){$decimals=0;}
		$decimals=intval($decimals);
		$tickIndex=0;
		do{
			$value=$arr['scale']['minValue']+$tickIndex*$arr['scale']['tickRange'];
			$arr['scale']['ticks'][$tickIndex]=array('value'=>$value);
			if ($arr['scale']['isQuantity']){
				$arr['scale']['ticks'][$tickIndex]['label']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->float2str($arr['scale']['ticks'][$tickIndex]['value']).$arr['scale']['tickUnit'];
			} else {
				$arr['scale']['ticks'][$tickIndex]['label']=number_format($arr['scale']['ticks'][$tickIndex]['value'],intval($decimals)).$arr['scale']['tickUnit'];
			}
			$labelLength=strlen($arr['scale']['ticks'][$tickIndex]['label']);
			if ($labelLength>$arr['scale']['maxLabelLength']){$arr['scale']['maxLabelLength']=$labelLength;}
			$tickIndex++;
		} while($value<$arr['scale']['maxValue']);
		return $arr;
	}
	
	private function decRangeMappingScaler($rangeIn,$targetRange=1000){
        if (empty($rangeIn)){
            $scaler=PHP_INT_MAX;
        } else {
            $scaler=$targetRange/$rangeIn;
        }
		$scaler=pow(10,floor(log10($scaler)));
		return $scaler;
	}
	
	private function getMapping($rangeIn=array('min'=>0.0,'max'=>1.0),$targetRange=array('min'=>-100,'max'=>0)){
		$mapping=array();
		$mapping['scaler']=($rangeIn['max']==$rangeIn['min'])?INF:($targetRange['max']-$targetRange['min'])/($rangeIn['max']-$rangeIn['min']);
		$mapping['offset']=$targetRange['min']-$mapping['scaler']*$rangeIn['min'];
		return $mapping;
	}
	
	private function mapValue($mapping,$value){
		return round($value*$mapping['scaler']+$mapping['offset']);
	}
	
	
	
	
}
?>