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
            $this->oc['SourcePot\Datapool\Foundation\Logger']->log('error','File "countryCodes.json" missing.',array());    
        }
        $cc=file_get_contents($file);
        $this->countryCodes=$this->oc['SourcePot\Datapool\Tools\MiscTools']->json2arr($cc);
        if (empty($this->countryCodes)){
            $this->oc['SourcePot\Datapool\Foundation\Logger']->log('error','File error "countryCodes.json"',array());    
        }
    }

    public function location2address($entry,$targetKey='Address',$isDebugging=FALSE){
        $debugArr=array('entry_in'=>$entry);
        if (isset($entry['Params']['Geo']['lon']) && isset($entry['Params']['Geo']['lat'])){
            $entry['Params']['Geo']['lat']=floatval($entry['Params']['Geo']['lat']);
            $entry['Params']['Geo']['lon']=floatval($entry['Params']['Geo']['lon']);
            $query=array('lat'=>$entry['Params']['Geo']['lat'],'lon'=>$entry['Params']['Geo']['lon']);
            $requestArr=array('method'=>'GET','url'=>'https://nominatim.openstreetmap.org','resource'=>'reverse','query'=>$query,'header'=>$this->requestHeader);
            $response=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->request($requestArr,FALSE);
            $debugArr['requestArr']=$requestArr;
            $debugArr['response']=$response;
            if (isset($response['data']['addressparts'])){
                $entry['Params'][$targetKey]=$this->normalizeAddress($response['data']['addressparts']);
                if (isset($entry['Content'][$targetKey])){$entry['Content'][$targetKey]=$entry['Params'][$targetKey];}
                if (isset($response['data']['result'])){
                    $entry['Params'][$targetKey]['display_name']=$response['data']['result'];
                } else {
                    $entry['Params'][$targetKey]['display_name']=implode(', ',$response['data']['addressparts']);
                }
            }
            $debugArr['entry_out']=$entry;
        }
        if ($isDebugging){
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
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
        $debugArr['address']=$address;
        if (!empty($address)){
            $query=$this->getRequestAddress($address);
            $debugArr['query']=$query;
            if ($query){
                $requestArr=array('method'=>'GET','url'=>'https://nominatim.openstreetmap.org','resource'=>'search','query'=>$query,'header'=>$this->requestHeader);
                $debugArr['requestArr']=$requestArr;
                $response=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->request($requestArr,FALSE);
                $debugArr['response']=$response;
                if (isset($response['data']['0'])){
                    $entry['Params']['Geo']=$response['data']['0'];
                    $entry['Params']['Address']=$address;
                    $debugArr['entry_out']=$entry;
                }
            }
            
        }
        if ($isDebugging){
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
        $template=array('style'=>array('float'=>'left','clear'=>'both'),'class'=>'ep-std','dL'=>0.001);
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
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','class'=>'btn','href'=>$href,'element-content'=>'Open Map','target'=>'_blank','style'=>array('clear'=>'left')));
        $href='https://www.google.de/maps/@'.$entry['Params']['Geo']['lat'].','.$entry['Params']['Geo']['lon'].',16z';
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'a','class'=>'btn','href'=>$href,'element-content'=>'Open Google Maps','target'=>'_blank'));
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>array('margin'=>'5px 5px 10px 2px')));
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
    
    public function getDynamicMap($arr=array()){
        $html='';
        $toLoadArr=array('leafletCss'=>array('tag'=>'link','rel'=>'stylesheet','href'=>'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css','integrity'=>'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=','crossorigin'=>'','element-content'=>''),
                         'leafletJ'=>array('tag'=>'script','src'=>'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js','integrity'=>'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=','crossorigin'=>'','element-content'=>''),
                         );
        foreach($toLoadArr as $index=>$elementArr){
            $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($elementArr);
        }
        $arr['tag']='div';
        $arr['id']='dynamic-map';
        $arr['style']['width']=600;
        $arr['style']['height']=400;
        $arr['function']=__FUNCTION__;
        if (!isset($arr['element-content'])){$arr['element-content']=' ';}
        if (!isset($arr['keep-element-content'])){$arr['keep-element-content']=TRUE;}
        $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element($arr);
        $matrix=array(array('value'=>$html));
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'hideHeader'=>TRUE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>'Map'));
        return $html;
    }
    
}
?>