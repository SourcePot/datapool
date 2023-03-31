<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\GenericApps;

class Calendar{
	
	private $arr;
	
	private $entryTable;
	private $entryTemplate=array('Group'=>array('index'=>FALSE,'value'=>'{{pageTitle}}','type'=>'VARCHAR(255)','Description'=>'This is the Group category'),
								 'Folder'=>array('index'=>FALSE,'value'=>'Events','type'=>'VARCHAR(255)','Description'=>'This is the Group category'),
								 'Start'=>array('index'=>FALSE,'value'=>'{{TODAY}}','type'=>'DATETIME','Description'=>'Is the start of an event, event, etc.'),
								 'End'=>array('index'=>FALSE,'value'=>'{{TOMORROW}}','type'=>'DATETIME','Description'=>'Is the end of an event, event, etc.')
								 );

	private $setting=array();

	private $pageState=array();
	private $pageStateTemplate=array();

	public $definition=array('Type'=>array('@tag'=>'p','@default'=>'calendar event','@Read'=>'NO_R'),
							 'Map'=>array('@function'=>'getMapHtml','@class'=>'SourcePot\Datapool\Tools\GeoTools','@default'=>''),
							 'Content'=>array('Event'=>array('Description'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
															 'Type'=>array('@function'=>'select','@options'=>array('meeting'=>'Meeting','travel'=>'Travel','event'=>'Event'),'@default'=>'meeting','@excontainer'=>TRUE),
															 'Start'=>array('@tag'=>'input','@type'=>'datetime-local','@default'=>'{{NOW}})','@excontainer'=>TRUE),
															 'Start timezone'=>array('@function'=>'select','@default'=>'{{TIMEZONE-SERVER}}','@excontainer'=>TRUE),
															 'End'=>array('@tag'=>'input','@type'=>'datetime-local','@default'=>'{{TOMORROW}})','@excontainer'=>TRUE),
															 'End timezone'=>array('@function'=>'select','@default'=>'{{TIMEZONE-SERVER}}','@excontainer'=>TRUE),
															 'Recurrence'=>array('@function'=>'select','@options'=>array('+0 day'=>'Same day','+1 day"'=>'Daily','+1 week"'=>'Weeklyy','+1 month"'=>'Monthly','+1 year"'=>'Yearly'),'@default'=>'+0 day','@excontainer'=>TRUE),
															 'Recurrence times'=>array('@tag'=>'input','@type'=>'number','@min'=>0,'@max'=>100,'@default'=>0,'@excontainer'=>TRUE),
															 'Recurrence id'=>array('@tag'=>'p'),
															 'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'),
															),
												'Location/Destination'=>array('Company'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Department'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Street'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'House number'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Town'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Zip'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Country'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
																 'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'),
																 ),
											),
							 'Misc'=>array('@function'=>'entryControls','@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'),
							 'Read'=>array('@function'=>'setAccessByte','@default'=>'ALL_MEMBER_R','@key'=>'Read','@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'),
							 );

	private $options=array('Type'=>array('event'=>'Event','trip'=>'Trip','meeting'=>'Meeting','todo'=>'To do','done'=>'To do done','training_0'=>'Training scheduled','training_1'=>'Training prepared','training_2'=>'Training canceled','training_3'=>'Training no-show'),
						   'Days to show'=>array(10=>'Show 10 days',20=>'Show 20 days',45=>'Show 45 days',90=>'Show 90 days',180=>'Show 180 days',370=>'Show 370 days'),
						   'Day width'=>array(200=>'Small day width',400=>'Middle day width',800=>'Big day width',1600=>'Biggest day width'),
						   'Recurrence'=>array('+0 day'=>'Single event','+1 day'=>'Daily','+1 week'=>'Weekly','+1 month'=>'Monthly','+1 year'=>'Yearly'),
						   'Timezone'=>array('Europe/Berlin'=>'+1 Europe/Berlin','Europe/London'=>'0 Europe/London','Atlantic/Azores'=>'-1 Atlantic/Azores','Atlantic/South_Georgia'=>'-2 Atlantic/South_Georgia',
											 'America/Sao_Paulo'=>'-3 America/Sao_Paulo','America/Halifax'=>'-4 America/Halifax','America/New_York'=>'-5 America/New York','America/Mexico_City'=>'-6 America/Mexico City',
											 'America/Denver'=>'-7 America/Denver','America/Vancouver'=>'-8 America/Vancouver','America/Anchorage'=>'-9 America/Anchorage','Pacific/Honolulu'=>'-10 Pacific/Honolulu',
											 'Pacific/Midway'=>'-11 Pacific/Midway','Pacific/Kiritimati'=>'-12 Pacific/Kiritimati','Pacific/Fiji'=>'+12 Pacific/Fiji','Asia/Magadan'=>'+11 Asia/Magadan',
											 'Pacific/Guam'=>'+10 Pacific/Guam','Asia/Tokyo'=>'+9 Asia/Tokyo','Asia/Shanghai'=>'+8 Asia/Shanghai','Asia/Novosibirsk'=>'+7 Asia/Novosibirsk','Asia/Omsk'=>'+6 Asia/Omsk',
											 'Asia/Yekaterinburg'=>'+5 Asia/Yekaterinburg','Europe/Samara'=>'+4 Europe/Samara','Europe/Moscow'=>'+3 Europe/Moscow','Africa/Cairo'=>'+2 Africa/Cairo','UTC'=>'UTC'),
							);

	private $oldEventsKeyMapping=array('Content|[]|Entry|[]|Description'=>'Content|[]|Event|[]|Description',
										'Content|[]|Entry|[]|Description'=>'Content|[]|Event|[]|Description',
										'Content|[]|Entry|[]|Type'=>'Content|[]|Event|[]|Type',
										'Content|[]|Entry|[]|Start|[]|Date'=>'Content|[]|Event|[]|Start',
										'Content|[]|Entry|[]|Start|[]|Time'=>'Content|[]|Event|[]|Start',
										'Content|[]|Entry|[]|Start timezone'=>'Content|[]|Event|[]|Start timezone',
										'Content|[]|Entry|[]|End|[]|Date'=>'Content|[]|Event|[]|End',
										'Content|[]|Entry|[]|End|[]|Time'=>'Content|[]|Event|[]|End',
										'Content|[]|Entry|[]|End timezone'=>'Content|[]|Event|[]|End timezone',
										'Content|[]|Entry|[]|Visibility'=>FALSE,
										'Content|[]|Settings|[]|Recurrence'=>'Content|[]|Event|[]|Recurrence',
										'Content|[]|Settings|[]|Recurrence times'=>'Content|[]|Event|[]|Recurrence times',
										'Content|[]|Settings|[]|Recurrence id'=>'Content|[]|Event|[]|Recurrence id'
										);

	public function __construct($arr){
		$this->arr=$arr;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
		//
	}

	public function init($arr){
		$this->entryTemplate=$arr['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		$this->definition['Content']['Event']['Start timezone']['@options']=$this->options['Timezone'];
		$this->definition['Content']['Event']['End timezone']['@options']=$this->options['Timezone'];
		$this->definition['Content']['Event']['Recurrence']['@options']=$this->options['Recurrence'];
		$arr['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
		// get settings
		$this->setting=array('Days to show'=>31,'Day width'=>300,'Timezone'=>date_default_timezone_get());
		$this->setting=$arr['SourcePot\Datapool\AdminApps\Settings']->getSetting(__CLASS__,$_SESSION['currentUser']['EntryId'],$this->setting,'Calendar',FALSE);
		// get page state
		$this->pageStateTemplate=array('Type'=>$this->definition['Type']['@default'],'EntryId'=>'{{EntryId}}','calendarDate'=>'{{YESTERDAY}}','addDate'=>'','refreshInterval'=>300);
		$this->pageState=$arr['SourcePot\Datapool\Tools\NetworkTools']->getPageState(__CLASS__,$this->pageStateTemplate);
		$this->arr=$arr;
		return $this->arr;
	}

	public function job($vars){
		return $vars;
	}

	public function getEntryTable(){
		return $this->entryTable;
	}
	
	public function getEntryTemplate(){
		return $this->entryTemplate;
	}

	public function run($arr=TRUE){
		if ($arr===TRUE){
			return array('Category'=>'Apps','Emoji'=>'&#9992;','Label'=>'Calendar','Read'=>'ALL_MEMBER_R','Class'=>__CLASS__);
		} else {
			$html='';
			$html.=$this->arr['SourcePot\Datapool\Foundation\Container']->container('Calendar by '.__FUNCTION__,'generic',$this->pageState,array('method'=>'getCalendar','classWithNamespace'=>__CLASS__),array('style'=>array()));
			$arr['page html']=str_replace('{{content}}',$html,$arr['page html']);
			return $arr;
		}
	}
	
	public function unifyEntry($entry){
		$entry['Source']=$this->entryTable;	
		if (empty($entry['Type'])){$entry['Type']=$this->definition['Type']['@default'];}
		if (empty($entry['Content']['Event']) && !empty($entry['addDate'])){
			$entry['Content']['Event']['Start']=$entry['addDate'].'T00:00';
			$entry['Content']['Event']['Start timezone']=$this->setting['Timezone'];
			$entry['Content']['Event']['End']=$entry['addDate'].'T23:59';
			$entry['Content']['Event']['End timezone']=$this->setting['Timezone'];
			$entry['Content']['Event']['Description']='';
		}
		if (!isset($entry['Folder'])){
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->addEntryDefaults($entry);		
		} else if (strpos($entry['Folder'],'--')===0){
			// old style event needs to be translated into event
			$entry['Type']='calendar event';
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->stdReplacements($entry,$this->oldEventsKeyMapping);
		} else {
			$entry=$this->arr['SourcePot\Datapool\Foundation\Database']->stdReplacements($entry);	
		}
		if (!empty($entry['Content']['Event']['Start']) && !empty($entry['Content']['Event']['Start timezone']) && 
			!empty($entry['Content']['Event']['End']) && !empty($entry['Content']['Event']['End timezone'])){
			$entry['Name']=substr($entry['Content']['Event']['Description'],0,100);
			$entry['Start']=$this->getTimezoneDate($entry['Content']['Event']['Start'],$entry['Content']['Event']['Start timezone'],date_default_timezone_get());
			$entry['End']=$this->getTimezoneDate($entry['Content']['Event']['End'],$entry['Content']['Event']['End timezone'],date_default_timezone_get());
			if (!empty($entry['entryIsUpdated'])){$entry=$this->updateCalendarEventEntry($entry,TRUE);}
		}
		$entry=$this->arr['SourcePot\Datapool\Foundation\Definitions']->definition2entry($this->definition,$entry);
		return $entry;
	}
	
	public function getCalendar($arr){
		if (!isset($arr['html'])){$arr['html']='';}
		$this->eventsFormProcessing();
		$settingsArr=$this->getCalendarSettings($arr);
		$entryArr=$this->getCalendarEntry($arr);
		$calendarArr=$this->getCalendarSheet($arr);
		$arr['html'].=$settingsArr['html'];
		$arr['html'].=$calendarArr['html'];
		$arr['html'].=$entryArr['html'];
		return $arr;
	}
	
	private function getCalendarEntry($arr=array()){
		$template=array('html'=>'','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$arr=array_merge($template,$arr);
		$event=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($this->pageState);
		if (empty($event)){$event=$this->pageState;}
		$event=$this->arr['SourcePot\Datapool\Foundation\Database']->unifyEntry($event);
		$definition=$this->arr['SourcePot\Datapool\Foundation\Definitions']->getDefinition($event);
		if (strcmp($this->pageState['EntryId'],'{{EntryId}}')===0 && empty($this->pageState['addDate'])){
			$arr['html'].=$this->getEventsOverview($arr);
		} else {
			$arr['html'].=$this->arr['SourcePot\Datapool\Foundation\Definitions']->definition2form($definition,$event);
		}
		return $arr;		
	}
	
	private function getCalendarSettings($arr=array()){
		$template=array('html'=>'','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$arr=array_merge($template,$arr);
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing($arr['callingClass'],$arr['callingFunction']);
		if (isset($formData['cmd']['Home'])){
			$this->pageState['EntryId']='{{EntryId}}';
			$this->pageState['calendarDate']='{{YESTERDAY}}';
			$this->pageState['addDate']='';
			$this->pageState=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageState(__CLASS__,$this->pageState);
		} else if (!empty($formData['cmd'])){
			$newPageState=array_merge($this->pageStateTemplate,$this->pageState,$formData['val']['pageState']);
			$this->pageState=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageState(__CLASS__,$newPageState);
			$this->setting=$this->arr['SourcePot\Datapool\AdminApps\Settings']->setSetting(__CLASS__,$_SESSION['currentUser']['EntryId'],$formData['val']['setting'],'Calendar',FALSE);
		}
		$calendarDate=new \DateTime('@'.$this->calendarStartTimestamp());
		$calendarDate->setTimezone(new \DateTimeZone($this->setting['Timezone']));
		$calendarDateArr=$arr;
		$calendarDateArr['tag']='input';
		$calendarDateArr['type']='date';
		$calendarDateArr['title']='Press enter to select';
		$calendarDateArr['value']=$calendarDate->format('Y-m-d');
		$calendarDateArr['key']=array('pageState','calendarDate');
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element($calendarDateArr);
		$btnArr=$arr;
		$btnArr['style']=array('font-size'=>'20px','line-height'=>'15px','width'=>'50px','color'=>'#fff','background-color'=>'#000');
		$btnArr['cmd']='Set';
		$btnArr['title']='Set';
		$btnArr['element-content']='&#10022;';
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
		$btnArr['cmd']='Home';
		$btnArr['title']='Home';
		$btnArr['element-content']='&#9750;';
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->btn($btnArr);
		$timezoneArr=$arr;
		$timezoneArr['selected']=$this->setting['Timezone'];
		$timezoneArr['options']=$this->options['Timezone'];
		$timezoneArr['key']=array('setting','Timezone');
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($timezoneArr);
		$daysToShowArr=$arr;
		$daysToShowArr['selected']=$this->setting['Days to show'];
		$daysToShowArr['options']=$this->options['Days to show'];
		$daysToShowArr['key']=array('setting','Days to show');
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($daysToShowArr);
		$dayWidthArr=$arr;
		$dayWidthArr['selected']=$this->setting['Day width'];
		$dayWidthArr['options']=$this->options['Day width'];
		$dayWidthArr['key']=array('setting','Day width');
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->select($dayWidthArr);
		return $arr;
	}
	
	private function getEventsOverview(){
		$matrices=array();
		$events=$this->getEvents(time());
		foreach($events as $EntryId=>$event){
			if (strpos($event['State'],'Upcomming')!==FALSE){
				$matrices[$event['State']][$EntryId]=array('Event'=>$event['Name'],'Starts in'=>$this->getTimeDiff($event['Start'],'@'.time(),date_default_timezone_get()));
			} else {
				$matrices[$event['State']][$EntryId]=array('Event'=>$event['Name'],'End in'=>$this->getTimeDiff($event['End'],'@'.time(),date_default_timezone_get()));
			}
		}
		$html='';
		foreach($matrices as $caption=>$matrix){
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->table(array('matrix'=>$matrix,'caption'=>$caption,'hideKeys'=>TRUE));
		}
		return $html;
	}
	
	private function getCalendarSheet($arr=array()){
		$template=array('html'=>'','callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__);
		$arr=array_merge($template,$arr);
		$style=array('left'=>$this->date2pos());
		$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>'','keep-element-content'=>TRUE,'class'=>'calendar-timeline','style'=>$style));
			
		$lastDayPos=0;
		$dayInterval=\DateInterval::createFromDateString('1 days');
		$calendarDateTime=new \DateTime('@'.$this->calendarStartTimestamp());
		$calendarDateTime->setTimezone(new \DateTimeZone($this->setting['Timezone']));
		for($day=0;$day<$this->setting['Days to show'];$day++){
			//var_dump($calendarDateTime->format('Y-m-d H:i:s'));
			$weekDay=$calendarDateTime->format('D');
			$date=$calendarDateTime->format('Y-m-d');
			$dayContent=$this->arr['SourcePot\Datapool\Foundation\Dictionary']->lng('Week').' '.intval($calendarDateTime->format('W')).', '.$this->arr['SourcePot\Datapool\Foundation\Dictionary']->lng($weekDay).'<br/>';
			$dayContent.=$date;
			$calendarDateTime->add($dayInterval);
			$newDayPos=$this->date2pos($calendarDateTime->format('Y-m-d H:i:s'));
			$dayStyle=array('left'=>$lastDayPos,'width'=>$newDayPos-$lastDayPos-1);
			if (strcmp($date,$this->pageState['addDate'])===0){$dayStyle['background-color']='#f008';}
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>'','keep-element-content'=>TRUE,'class'=>'calendar-day','style'=>$dayStyle));
			$dayStyle=array('left'=>$lastDayPos,'width'=>$newDayPos-$lastDayPos-1);
			if (strcmp($weekDay,'Sun')===0 || strcmp($weekDay,'Sat')===0){
				$dayStyle['background-color']='#af6';
			} else {
				$dayStyle['background-color']='#fff';
			}
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'button','element-content'=>$dayContent,'keep-element-content'=>TRUE,'key'=>array('Add',$date),'title'=>'Click here to open a new event','callingClass'=>__CLASS__,'callingFunction'=>'addEvents','class'=>'calendar-day','style'=>$dayStyle));
			$arr['html'].=$this->timeLineHtml($date);
			$lastDayPos=$newDayPos;
		}
		$arr=$this->addEvents($arr);
		$wrapperStyle=array('width'=>$this->getCalendarWidth(),'height'=>$arr['calendarSheetHeight']);
		$arr['html']=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'class'=>'calendar-sheet','style'=>$wrapperStyle));
		$arr['html']=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>$arr['html'],'keep-element-content'=>TRUE,'class'=>'calendar-sheet-wrapper'));
		return $arr;
	}
	
	private function eventsFormProcessing(){
		$formData=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->formProcessing(__CLASS__,'addEvents');
		if (isset($formData['cmd']['EntryId'])){
			$selector=$this->pageState;
			$selector['EntryId']=key($formData['cmd']['EntryId']);
			$event=$this->arr['SourcePot\Datapool\Foundation\Database']->entryById($selector);
			$this->pageState['EntryId']=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'EntryId',key($formData['cmd']['EntryId']));
			$this->pageState['calendarDate']=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'calendarDate',$event['Start']);
			$this->pageState['addDate']=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'addDate','');
		} else if (isset($formData['cmd']['Add'])){
			$this->pageState['EntryId']=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'EntryId','{{EntryId}}');
			$this->pageState['calendarDate']=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'calendarDate',key($formData['cmd']['Add']));
			$this->pageState['addDate']=$this->arr['SourcePot\Datapool\Tools\NetworkTools']->setPageStateByKey(__CLASS__,'addDate',key($formData['cmd']['Add']));
		}
		return $formData;
	}
	
	private function addEvents($arr){
		$timestamp=$this->calendarStartTimestamp();
		$events=$this->getEvents($timestamp);
		$arr['calendarSheetHeight']=120;
		foreach($events as $EntryId=>$event){
			$style=array();
			$style['top']=100+$event['y']*40;
			if ($style['top']+50>$arr['calendarSheetHeight']){$arr['calendarSheetHeight']=$style['top']+50;}
			$style['left']=$event['x0'];
			$style['width']=$event['x1']-$event['x0']-2;
			$style['border']='1px solid #aaa';
			if (!empty($this->pageState['EntryId'])){
				if (strcmp($EntryId,$this->pageState['EntryId'])===0){
					$style['border']='1px solid #fff';
					$style['color']='#000';
					$style['background-color']='#fff';
				}
			}
			$arr['html'].=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'button','element-content'=>$event['Name'],'key'=>array('EntryId',$EntryId),'entry-id'=>$EntryId,'callingClass'=>__CLASS__,'callingFunction'=>__FUNCTION__,'class'=>'calendar-event','style'=>$style));
		}
		return $arr;
	}
		
	private function timeLineHtml($date){
		$html='';
		$lastPos=0;
		for($h=0;$h<24;$h++){
			if ($h<10){$dateTime=$date.' 0'.$h;} else {$dateTime=$date.' '.$h;}
			$dateTime.=':00:00';
			$newPos=$this->date2pos($dateTime);
			if ($newPos-$lastPos<30){continue;}
			$content=strval($h);
			$contentPos=round($newPos-(strlen($content)*10)/2);
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'p','element-content'=>$content,'class'=>'calendar-hour','style'=>array('left'=>$contentPos)));
			$html.=$this->arr['SourcePot\Datapool\Tools\HTMLbuilder']->element(array('tag'=>'div','element-content'=>'','class'=>'calendar-hour','style'=>array('left'=>$newPos)));
			$lastPos=$newPos;
		}
		return $html;
	}
	
	private function calendarStartTimestamp(){
		if (empty($this->pageState['calendarDate'])){return 0;}
		$calendarTimezone=new \DateTimeZone($this->setting['Timezone']);
		$calendarDate=$this->arr['SourcePot\Datapool\Foundation\Database']->stdReplacements($this->pageState['calendarDate']);
		$calendarDateTime=new \DateTime($calendarDate,$calendarTimezone);
		$date=$calendarDateTime->format('Y-m-d 00:00:00');
		$calendarDateTime=new \DateTime($date,$calendarTimezone);
		return $calendarDateTime->getTimestamp();
	}
	
	private function date2pos($date='now',$timezone=FALSE){
		if (empty($timezone)){
			$timezone=new \DateTimeZone($this->setting['Timezone']);
		} else {
			$timezone=new \DateTimeZone($timezone);	
		}
		$dateTime=new \DateTime($this->arr['SourcePot\Datapool\Foundation\Database']->stdReplacements($date),$timezone);
		return floor(($dateTime->getTimestamp()-$this->calendarStartTimestamp())*$this->setting['Day width']/86400);
	}

	private function pos2date($pos){
		$timestamp=$this->calendarStartTimestamp()+$pos*86400/$this->setting['Day width'];
		$dateTime=new \DateTime('@'.$timestamp);
		$dateTime->setTimezone(new \DateTimeZone($this->setting['Timezone']));
		return $dateTime->format('Y-m-d H:i:s');
	}
	
	private function getCalendarWidth(){
		$calendarStartTimestamp=$this->calendarStartTimestamp();
		$calendarDateTime=new \DateTime('@'.$calendarStartTimestamp);
		$calendarDateTime->add(\DateInterval::createFromDateString($this->setting['Days to show'].' days'));
		$calendarDateTime->setTimezone(new \DateTimeZone($this->setting['Timezone']));
		return ceil(($calendarDateTime->getTimestamp()-$calendarStartTimestamp)*$this->setting['Day width']/86400);
	}
	
	public function getTimezoneDate($date,$sourceTimezone,$targetTimezone){
		$sourceTimezone=new \DateTimeZone($sourceTimezone);
		$targetTimezone=new \DateTimeZone($targetTimezone);
		$dateTime=new \DateTime($date,$sourceTimezone);
		$dateTime->setTimezone($targetTimezone);
		return $dateTime->format('Y-m-d H:i:s');
	}

	private function getEvents($timestamp){
		$calendarDateTime=new \DateTime('@'.$timestamp);
		$serverTimezone=new \DateTimeZone(date_default_timezone_get());
		$calendarDateTime->setTimezone($serverTimezone);
		$viewStart=$calendarDateTime->format('Y-m-d H:i:s');
		$calendarDateTime->add(\DateInterval::createFromDateString($this->setting['Days to show'].' days'));
		$viewEnd=$calendarDateTime->format('Y-m-d H:i:s');
		$events=array();
		$oldEvents=array();
		$selectors=array();
		$selectors['Ongoing event']=array('Source'=>$this->entryTable,'Start<'=>$viewStart,'End>'=>$viewEnd);
		$selectors['Finnishing event']=array('Source'=>$this->entryTable,'End>='=>$viewStart,'End<='=>$viewEnd);
		$selectors['Upcomming event']=array('Source'=>$this->entryTable,'Start>='=>$viewStart,'Start<='=>$viewEnd);
		foreach($selectors as $state=>$selector){
			foreach($this->arr['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,FALSE,'Read','Start') as $entry){
				$key=$entry['EntryId'];
				$eventStartTimestamp=strtotime($entry['Start']);
				if (strcmp($state,'Finnishing event')===0){
					if ($eventStartTimestamp>time()){$state='Upcomming event';}
				}
				$events[$key]=$entry;
				$events[$key]['State']=$state;
				$events[$key]['x0']=$this->date2pos($entry['Start'],date_default_timezone_get());
				$events[$key]['x1']=$this->date2pos($entry['End'],date_default_timezone_get());
				// add y-index
				$events[$key]=$this->addYindex($events[$key],$oldEvents);
				$oldEvents[$key]=$events[$key];
			}
		}
		return $events;
	}

	private function addYindex($newEvent,$oldEvents){
		$newEvent['y']=0;
		do{
			$nextTry=FALSE;
			foreach($oldEvents as $EntryId=>$oldEvent){
				if ($newEvent['y']===$oldEvent['y']){
					if ($this->eventsOverlap($newEvent,$oldEvent)){
						$newEvent['y']++;
						$nextTry=TRUE;
						break;
					}
				}
			}
		} while($nextTry);
		return $newEvent;
	}

	private function eventsOverlap($eventA,$eventB){
		return (($eventA['x0']>$eventB['x0'] && $eventA['x0']<=$eventB['x1']) || ($eventA['x1']>$eventB['x0'] && $eventA['x1']<=$eventB['x1']) || ($eventA['x0']<$eventB['x0'] && $eventA['x1']>=$eventB['x1']));
	}

	private function getTimeDiff($dateA,$dateB,$timezoneA='Europe/Berlin',$timezoneB='Europe/Berlin'){
		$timezoneA=new \DateTimezone($timezoneA);
		$timezoneB=new \DateTimezone($timezoneB);
		$dateA=new \DateTime($dateA,$timezoneA);
		$dateB=new \DateTime($dateB,$timezoneB);
		$interval=$dateA->diff($dateB);
		$str='';
		$nonZeroDetected=FALSE;
		$template=array('years'=>'y','months'=>'m','days'=>'d','hours'=>'h','minutes'=>'i');
		//$template=array('years'=>'y','months'=>'m','days'=>'d','hours'=>'h','minutes'=>'i','seconds'=>'s');
		foreach($template as $label=>$index){
			$value=$interval->format('%'.$index);
			if (intval($value)===1){$label=rtrim($label,'s');}
			if (intval($value)===0 && $nonZeroDetected===FALSE){continue;} else {$nonZeroDetected=TRUE;}
			$str.=$value.' '.$this->arr['SourcePot\Datapool\Foundation\Dictionary']->lng($label).', ';
		}
		return trim($str,', ');
	}
	
	private function updateCalendarEventEntry($entry){
		$entry=$this->arr['SourcePot\Datapool\Tools\GeoTools']->address2location($entry);
		if (empty($entry['Content']['Event']['Recurrence id'])){$entry['Content']['Event']['Recurrence id']=$entry['EntryId'];}
		// delete all related entries
		$toDeleteSelector=array('Source'=>$entry['Source'],'Content'=>'%'.$entry['Content']['Event']['Recurrence id'].'%');
		$this->arr['SourcePot\Datapool\Foundation\Database']->deleteEntries($toDeleteSelector,TRUE);
		// create recurring entries
		$startSourceTimezone=new \DateTimeZone($entry['Content']['Event']['Start timezone']);
		$endSourceTimezone=new \DateTimeZone($entry['Content']['Event']['End timezone']);
		$startDateTime=new \DateTime($entry['Content']['Event']['Start'],$startSourceTimezone);
		$endDateTime=new \DateTime($entry['Content']['Event']['End'],$endSourceTimezone);
		$intervallRecurrence=\DateInterval::createFromDateString(trim($entry['Content']['Event']['Recurrence'],'+'));
		$loopEntry=$entry;
		for($loop=1;$loop<=$entry['Content']['Event']['Recurrence times'];$loop++){
			$loopEntry['EntryId']=$this->arr['SourcePot\Datapool\Tools\MiscTools']->getEntryId();
			$startDateTime->add($intervallRecurrence);
			$loopEntry['Content']['Event']['Start']=$startDateTime->format('Y-m-d H:i:s');
			$endDateTime->add($intervallRecurrence);
			$loopEntry['Content']['Event']['End']=$endDateTime->format('Y-m-d H:i:s');
			$loopEntry['Start']=$this->getTimezoneDate($loopEntry['Content']['Event']['Start'],$loopEntry['Content']['Event']['Start timezone'],date_default_timezone_get());
			$loopEntry['End']=$this->getTimezoneDate($loopEntry['Content']['Event']['End'],$loopEntry['Content']['Event']['End timezone'],date_default_timezone_get());
			$loopEntry['Content']['Event']['Recurrence index']=$loop;
			$this->arr['SourcePot\Datapool\Foundation\Database']->updateEntry($loopEntry);
		}
		return $entry;
	}
	
	public function regexDateMatch($regex='....-..-.. ..:..:.. . . ..',$timestamp=null){
		// The regex will be matched against the date string of following format (format characters are used):
		// "YYYY-mm-dd HH:ii:ss N L WW" with
		// 'N' ... ISO 8601 numeric representation of the day of the week, 1 (for Monday) through 7 (for Sunday)
		// 'WW' ... ISO 8601 week number of year, weeks starting on Monday
		// 'L' ... Whether it's a leap year, 1 if it is a leap year, 0 otherwise.
		$now=date('Y-m-d H:i:s N L WW',$timestamp);
		return preg_match('/'.$regex.'/',$now,$matches);
	}

}
?>