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

class GeoTools{
	
	private $oc;
	
	private $alias=array('Number'=>'House number','Street number'=>'House number','House_number'=>'House number','House number'=>'House number',
						 'Road'=>'Street','Street'=>'Street',
						 'City'=>'Town','Village'=>'Town','Stadt'=>'Town','Town'=>'Town',
						 'Postcode'=>'Zip','Post code'=>'Zip','Post_code'=>'Zip','Zip'=>'Zip',
						 'Bundesland'=>'State','State'=>'State',
						 'Country'=>'Country',
						 'Country_code'=>'Country code','Country code'=>'Country code',
						 );
	
	private $countryCodes=array();
    
	private $requestHeader=array('Content-Type'=>'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
								 'User-agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:108.0) Gecko/20100101 Firefox/108.0');
	
	public function __construct($oc){
		$this->oc=$oc;
	}
	
	public function init($oc){
		$this->oc=$oc;
		// load country codes
		$file=$GLOBALS['dirs']['setup'].'/countryCodes.json';
		if (!is_file($file)){
			$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'File "countryCodes.json" missing.','priority'=>26,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
		$cc=file_get_contents($file);
		$this->countryCodes=$this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr($cc);
		if (empty($this->countryCodes)){
			$this->oc['SourcePot\Datapool\Foundation\Logging']->addLog(array('msg'=>'File error "countryCodes.json"','priority'=>26,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__));
		}
	}

	public function location2address($entry,$targetKey='Address'){
		// This method adds['reverseGeoLoc'] to $entry['Params']
		if (isset($entry['Params']['Geo']['lon']) && isset($entry['Params']['Geo']['lat'])){	// 0,0 will be skipped
			$entry['Params']['Geo']['lat']=floatval($entry['Params']['Geo']['lat']);
			$entry['Params']['Geo']['lon']=floatval($entry['Params']['Geo']['lon']);
			$query=$entry['Params']['Geo'];
			$response=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->performRequest('GET',"https://nominatim.openstreetmap.org/",'reverse',$query,$this->requestHeader);
			if (isset($response['response'][1]['addressparts'])){
				$entry['Params'][$targetKey]=$this->normalizeAddress($response['response'][1]['addressparts']);
				if (isset($response['response'][1]['result'])){
					$entry['Params'][$targetKey]['display_name']=$response['response'][1]['result'];
				} else {
					$entry['Params'][$targetKey]['display_name']=implode(', ',$response['response'][1]['addressparts']);
				}				
			}
		}
		return $entry;
	}
	
	public function address2location($entry,$isDebugging=FALSE){
		$debugArr=array('entry_in'=>$entry);
		if (!empty($entry['Content']['Address'])){
			$address=$entry['Content']['Address'];
		} else if (!empty($entry['Content']['Location/Destination'])){
			$address=$entry['Content']['Location/Destination'];
		} else {
			$address=array();
		}
		if (!empty($address)){
			$query=$this->getRequestAddress($address);
			if ($query){
				$response=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->performRequest('GET',"https://nominatim.openstreetmap.org/",'search',$query,$this->requestHeader);
				if (isset($response['response'][1][0])){$entry['Params']['Geo']=$response['response'][1][0];}
			}
		}
		if ($isDebugging){
			$debugArr['address']=$address;
			if (isset($query)){$debugArr['query']=$query;}
			if (isset($response)){$debugArr['response']=$response;}
			$debugArr['entry_out']=$entry;
			$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
		}
		return $entry;	
	}
	
	private function normalizeAddress($address){
		$normAddress=array();
		foreach ($address as $oldKey=>$value){
			$newKey=ucfirst($oldKey);
			if (isset($this->alias[$newKey])){
				$newKey=$this->alias[$newKey];
				$normAddress[$newKey]=$value;
			}
		}
		return $normAddress;
	}

	private function getRequestAddress($address=array()){
		$osmAlias=array('House number'=>'housenumber',
						'Street'=>'street',
						'Town'=>'city',
						'State'=>'state',
						//'Country'=>'country',
						'Zip'=>'postalcode'
						);
		$query=array();
		foreach($osmAlias as $from=>$to){
			if (empty($address[$from])){continue;}
			if (isset($address[$from])){$query[$to]=trim($address[$from]);}
		}
		if (!empty($query['housenumber']) && isset($query['street'])){
			$query['street']=$query['housenumber'].' '.$query['street'];
			unset($query['housenumber']);
		}
		if (!empty($query)){$query['format']='json';}
		return $query;
	}

	public function getMapHtml($arr){
		// This method returns the html-code for a map.
		// The map is based on the data provided by $entry['Params']['Geo'], if $entry is empty the current user obj will be used
		//
		$template=array('style'=>array(),'class'=>'ep-std','dL'=>0.001);
		$arr=array_replace_recursive($template,$arr);
		if (!isset($arr['html'])){$arr['html']='';}
		if (empty($arr['selector'])){return $arr;}
		$entry=$arr['selector'];
		if (empty($entry['Params']['Geo']['lat'])){return $arr;}
		$entry['Params']['Geo']['lat']=floatval($entry['Params']['Geo']['lat']);
		$entry['Params']['Geo']['lon']=floatval($entry['Params']['Geo']['lon']);
		$bbLat1=$entry['Params']['Geo']['lat']-$arr['dL'];
		$bbLat2=$entry['Params']['Geo']['lat']+$arr['dL'];
		$bbLon1=$entry['Params']['Geo']['lon']-$arr['dL'];
		$bbLon2=$entry['Params']['Geo']['lon']+$arr['dL'];
		$arr['html'].='<h3 class="whiteBoard">'.$this->oc['SourcePot\Datapool\Foundation\Dictionary']->lng('Location').'</h3>';
		$elementArr=array('tag'=>'iframe','element-content'=>'','style'=>$arr['style'],'class'=>$arr['class']);
		$elementArr['src']='https://www.openstreetmap.org/export/embed.html?bbox='.$bbLon1.','.$bbLat1.','.$bbLon2.','.$bbLat2.'&marker='.$entry['Params']['Geo']['lat'].','.$entry['Params']['Geo']['lon'].'&layer=mapnik';
		$arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elementArr);
		$arr['html'].=$this->getMapLink($entry);
		return $arr;
	}
	
	private function getMapLink($entry){
		if (empty($entry['Params']['Geo'])){return '';}
		$href='http://www.openstreetmap.org/';
		$href.='?lat='.$entry['Params']['Geo']['lat'].'&amp;lon='.$entry['Params']['Geo']['lon'];
		$href.='&amp;zoom=16&amp;layers=M&amp;mlat='.$entry['Params']['Geo']['lat'].'&amp;mlon='.$entry['Params']['Geo']['lon'];
		$html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','class'=>'btn','href'=>$href,'element-content'=>'Open Map','target'=>'_blank'));
		$href='https://www.google.de/maps/@'.$entry['Params']['Geo']['lat'].','.$entry['Params']['Geo']['lon'].',16z';
		$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','class'=>'btn','href'=>$href,'element-content'=>'Open Google Maps','target'=>'_blank'));
		$href='https://www.taxifarefinder.com';
		$html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','class'=>'btn','href'=>$href,'element-content'=>'Cab Fares','target'=>'_blank'));
		return $html;
	}
	
	public function getCountryCodes($country=FALSE){
		if ($country===FALSE){
			return $this->countryCodes;
		} else {
			foreach($this->countryCodes as $code=>$ccArr){
				if (stripos($ccArr['Country'],$country)===FALSE){continue;}
				return $ccArr;
				break;
			}
			return array();
		}
	}
	
}
?>