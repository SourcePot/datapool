<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\AdminApps;

class DerivedSignals implements \SourcePot\Datapool\Interfaces\App{
    private $oc;

    private const APP_ACCESS='ADMIN_R';
    
    private $entryTable='';
    private $entryTemplate=[
        'Expires'=>['type'=>'DATETIME','value'=>\SourcePot\Datapool\Root::NULL_DATE,'Description'=>'If the current date is later than the Expires-date the entry will be deleted. On insert-entry the init-value is used only if the Owner is not anonymous, set to 10mins otherwise.'],
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_MEMBER_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'],
        'Owner'=>['type'=>'VARCHAR(100)','value'=>'SYSTEM','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.']
    ];
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }
    
    public function getEntryTemplate()
    {
        return $this->entryTemplate;
    }

    public function run(array|bool $arr=TRUE):array
    {
        if ($arr===TRUE){
            return ['Category'=>'Admin','Emoji'=>'~','Label'=>'DerivedSignals','Read'=>self::APP_ACCESS,'Class'=>__CLASS__];
        } else {
            $arr['toReplace']['{{explorer}}']=$this->oc['SourcePot\Datapool\Foundation\Explorer']->getExplorer(__CLASS__,['EntryId'=>FALSE]);
            $selector=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__,['Group'=>FALSE,'Folder'=>FALSE]);
            $html='';
            if (empty($selector['Group'])){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h2','keep-element-content'=>TRUE,'element-content'=>'Please select or create a Group...']);
            } else if (empty($selector['Folder'])){
                $html.=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'h2','keep-element-content'=>TRUE,'element-content'=>'Please select or create a Folder...']);
            } else {
                $html.=$this->signalsDerived($selector);
            }
            // finalize page
            $arr['toReplace']['{{content}}']=$html;
            return $arr;
        }
    }

private function signalsDerived(array $selector):string
    {
        $html='';
        $selector['Name']='Params';
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($selector,['Source','Group','Folder','Name'],0);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Derived signal params '.$selector['EntryId'],'generic',$selector+['disableAutoRefresh'=>TRUE],['method'=>'derivedSignalParams','classWithNamespace'=>__CLASS__],[]);
        $selector['Name']='Rules';
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($selector,['Source','Group','Folder','Name'],0);
        $html.=$this->oc['SourcePot\Datapool\Foundation\Container']->container('Derived signal rules'.$selector['EntryId'],'generic',$selector+['disableAutoRefresh'=>TRUE],['method'=>'derivedSignalRules','classWithNamespace'=>__CLASS__],[]);
        return $html;
    }

    public function derivedSignalParams($arr):array
    {
    // rules
        $timespanOptions=[
            'Y-m-d H:i'=>'Minute',
            'Y-m-d H'=>'Hour',
            'Y-m-d'=>'Day',
            'Y-m'=>'Month',
            'Y'=>'Year',
        ];
        $contentStructure=[
            'Timespan'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$timespanOptions,'keep-element-content'=>TRUE,'excontainer'=>TRUE],
            'Timezone'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>\SourcePot\Datapool\Root::TIMEZONES,'value'=>\SourcePot\Datapool\Root::DB_TIMEZONE,'keep-element-content'=>TRUE,'excontainer'=>TRUE],
            'min'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            'max'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
            'description'=>['method'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE],
        ];
		// rules list
        $arr['contentStructure']=$contentStructure;
		$caption='Params for '.$arr['selector']['Group'].' &rarr; '.$arr['selector']['Folder'];
        $row=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entry2row($arr);
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->table(['matrix'=>['Parameter'=>$row],'style'=>'clear:left;','hideHeader'=>FALSE,'hideKeys'=>TRUE,'keep-element-content'=>TRUE,'caption'=>$caption]);
		return $arr;
	}
    
    public function derivedSignalRules($arr):array
    {
		// rules
        $signalOptions=$this->oc['SourcePot\Datapool\Foundation\Signals']->getOptions(['Group'=>'signal']);
        $processingOptions=[
            'avg'=>'Average',
            'min'=>'Min',
            'minExZero'=>'Min exclude zero',
            'max'=>'Max',
            'range'=>'Range',
            'sum'=>'Sum',
            'count'=>'Count',
        ];
        $contentStructure=[
            'Operation'=>['method'=>'select','excontainer'=>TRUE,'value'=>'+','options'=>['+'=>'+','-'=>'-','*'=>'*'],'keep-element-content'=>TRUE,'excontainer'=>TRUE],
            'Signal'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$signalOptions,'keep-element-content'=>TRUE,'excontainer'=>TRUE],
            'Processing'=>['method'=>'select','excontainer'=>TRUE,'value'=>'','options'=>$processingOptions,'keep-element-content'=>TRUE,'excontainer'=>TRUE],
            'Offset'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>0,'excontainer'=>TRUE],
            'Scaler'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>1,'excontainer'=>TRUE],
        ];
		// rules list
        $arr['contentStructure']=$contentStructure;
		$arr['caption']='Rules for '.$arr['selector']['Group'].' &rarr; '.$arr['selector']['Folder'];
		$arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $arr;
	}

    public function updateDerivedSignals()
    {
        $formats=[
            'Y-m-d H:i'=>'Y-m-d H:i:00',
            'Y-m-d H'=>'Y-m-d H:30:00',
            'Y-m-d'=>'Y-m-d 12:30:00',
            'Y-m'=>'Y-m-15 12:30:00',
            'Y'=>'Y-06-15 12:30:00',
        ];
        $signals=[];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator(['Source'=>$this->entryTable],TRUE,'Read','Name',$isAsc=TRUE) as $derivedSignal){
            $signalId=$derivedSignal['Group'].'|'.$derivedSignal['Folder'];
            $signals[$signalId][$derivedSignal['Name']][]=$derivedSignal['Content']+['Group'=>$derivedSignal['Group'],'Folder'=>$derivedSignal['Folder']];
        }
        foreach($signals as $signalId=>$signalArr){
            $params=current($signalArr['Params']??[]);
            $result=FALSE;
            foreach($signalArr['Rules'] as $rule){
                $sourceSignal=['Source'=>$this->oc['SourcePot\Datapool\Foundation\Signals']->getEntryTable(),'EntryId'=>$rule['Signal']];
                $sourceSignalProperties=$this->oc['SourcePot\Datapool\Foundation\Signals']->getSignalPropertiesById($sourceSignal,$params['Timespan'],$params['Timezone']);
                $signalValue=($sourceSignalProperties[$rule['Processing']]+floatval($rule['Offset']))*$rule['Scaler'];
                if ($result===FALSE){
                    $result=$signalValue;    
                } else if ($rule['Operation']==='+'){
                    $result+=$signalValue;    
                } else if ($rule['Operation']==='-'){
                    $result-=$signalValue;    
                } else if ($rule['Operation']==='*'){
                    $result=$result*$signalValue;    
                }
            }
            // signal params
            $signalParams=['description'=>$params['description']];
            if (empty($params['min'])){
                $signalParams['yMin']=$params['min'];
            }
            if (empty($params['max'])){
                $signalParams['yMax']=$params['max'];
            }
            $targetTimeZone=new \DateTimeZone($params['Timezone']);
            $nowDateTime=new \DateTime('@'.time());
            $nowDateTime->setTimezone($targetTimeZone);
            $dateTimeStr=$nowDateTime->format($formats[$params['Timespan']]);
            $signalDateTime=new \DateTime($dateTimeStr,new \DateTimeZone($params['Timezone']));
            $signalTimeStamp=$signalDateTime->getTimestamp();
            $this->oc['SourcePot\Datapool\Foundation\Signals']->updateSignal(__CLASS__,$params['Group'],$params['Folder'],$result,'float',$signalParams,$signalTimeStamp);
        }
    }    

}
?>