<?php
/*
* This file is part of the Datapool CMS package.
* This class provides client acces to a resource. 
* Security is provided through basic OAuth authorization and limited scopes defined as part of the user credentials.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class ClientAccess{
	
	private $authorizationLifespan=3600;
	
	private $oc;

	private $entryTable;
	private $entryTemplate=array('Read'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
								 'Write'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
								 'Owner'=>array('index'=>FALSE,'type'=>'VARCHAR(100)','value'=>'SYSTEM','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.'),
								 'Privileges'=>array('index'=>FALSE,'type'=>'SMALLINT UNSIGNED','value'=>1,'Description'=>'Is the user level the user was granted.'),
								 );

	public function __construct($oc){
		$this->oc=$oc;
		$table=str_replace(__NAMESPACE__,'',__CLASS__);
		$this->entryTable=strtolower(trim($table,'\\'));
	}
	
	public function init($oc){
		$this->oc=$oc;
		$this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,$this->entryTemplate);
		//var_dump($this->getAuthorizationHeader(array('client_id'=>'Gunterstraße 13','client_secret'=>'RsuQ632')));
	}

	public function getEntryTable(){return $this->entryTable;}

	public function getEntryTemplate(){return $this->entryTemplate;}

	/**
	* This methed is invoked when a client calls ../resource.php
	* The method outputs the answer created based on the request that consits of $_POST and/or $_GET values.
	* @param array arr
	* @return array arr
	*/
	public function request($arr){
		$header=array();
		if (isset($_SERVER['HTTPS'])){
			// process the request if https is confirmed
			$data=$this->globals2data();
			$data=$this->request2data($data);
		} else {
			// client requests must use HTTPS
			$data['answer']=array('error'=>'https is required');
		}
		$this->oc['SourcePot\Datapool\Tools\NetworkTools']->answer($header,$data['answer']);
		return $arr;
	}

	/**
	* This methed adds $_POST and/or $_GET values to data.
	* @param array data
	* @return array data
	*/
	private function globals2data($data=array()){
		// add all request data to $data
		foreach($_POST as $name=>$value){
			$data[$name]=filter_input(INPUT_POST,$name);
		}
		foreach($_GET as $name=>$value){
			$data[$name]=filter_input(INPUT_GET,$name);
		}
		return $data;
	}
	
	/**
	* This methed compiles the answer from the request and adds it to the data argument.
	* There are two request types: a request for an new access token and a request to invoke the method stated in the request.
	* The scope of the request is the object (class with namespace) provided by the "Client credentials"-entry.
	* @param array data
	* @return array data
	*/
	private function request2data($data){
		$this->deleteExpiredEntries();
		$data['grant_type']=(isset($data['grant_type']))?$data['grant_type']:'';
		$data['Authorization']=(isset($data['Authorization']))?$data['Authorization']:'';
		if (strcmp($data['grant_type'],'authorization_code')===0 && strpos($data['Authorization'],'Basic ')===0){
			// new token request
			$data=$this->newToken($data);
		} else if (strpos($data['Authorization'],'Bearer ')===0 && isset($data['method'])){
			// check token
			$data=$this->checkToken($data);
			// call the method on the object provided as Client credential's scope
			if (empty($data['answer']['error'])){
				$class=$data['answer']['scope'];
				$method=$data['method'];
				if (method_exists($this->oc[$class],$method)){
					$data['answer']=$this->oc[$class]->$method($data['answer']);
				} else {
					$data['answer']['error']='Method '.$class.'::'.$method.'() does not exist';
				}
			}
		} else {
			// authorization missing
			$data['answer']['error']='invalid_request';
		}
		return $data;
	}
	
	/**
	* This methed returns a new access-token if the user credentials provided in the request are correct.
	* The user credentials must be provided through $_POST['Authorization'] or $_GET['Authorization'], the format is "Basic ${Base64(<client_id>:<client_secret>)}"
	* @param array data
	* @return array data
	*/
	private function newToken($data){
		$authorizationArr=$this->decodeAuthorization($data['Authorization']);
		// get credentials entry and try match
		$authorizationEntry=FALSE;
		if (!empty($authorizationArr['type']) && !empty($authorizationArr['client_id']) && !empty($authorizationArr['client_secret'])){
			$credentialsSelector=array('Source'=>$this->entryTable,'Type'=>$this->entryTable.' credentials','Group'=>'Client credentials','Content'=>'%'.$authorizationArr['client_id'].'%');
			foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($credentialsSelector,TRUE) as $entry){
				if (strcmp($entry['Content']['client_id'],$authorizationArr['client_id'])===0 && strcmp($entry['Content']['client_secret'],$authorizationArr['client_secret'])===0){
					$authorizationEntry=$entry;
					break;
				}
			}
		}
		if (empty($authorizationEntry)){
			// no matching credentials entry found
			$data['answer']['error']='invalid_client';
		} else {
			// create new token
			$expires=time()+$this->authorizationLifespan;
			$accessToken=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(64);
			$authorizationEntry['Expires']=date('Y-m-d H:i:s',$expires);
			$tokenContent=array('access_token'=>$accessToken,'expires_in'=>$this->authorizationLifespan,'expires'=>$expires,'expires_datetime'=>$authorizationEntry['Expires'],'Privileges'=>$authorizationEntry['Privileges']);
			$authorizationEntry['Name']=$accessToken;
			$authorizationEntry['Type']=$this->entryTable.' token';
			$authorizationEntry['Group']='Client token';
			$authorizationEntry['Content']=array_replace_recursive($authorizationEntry['Content'],$tokenContent);
			$authorizationEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($authorizationEntry);
			$this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($authorizationEntry);
			// return new token
			$data['answer']=$authorizationEntry['Content'];
		}
		return $data;
	}
	
	/**
	* This methed checks the access-token provided through the request. 
	* If the acces-token is invalid or has expired an error is added to the answer.
	* @param array data
	* @return array data
	*/
	private function checkToken($data){
		$data['answer']['error']='invalid_grant';
		$tokenSelector=array('Source'=>$this->entryTable,'Name'=>substr($data['Authorization'],7));
		foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($tokenSelector,TRUE) as $token){
			$data['answer']=$token['Content'];
			unset($data['answer']['access_token']);
			unset($data['answer']['error']);
			$data['answer']['expires_in']=$data['answer']['expires']-time();
			break;
		}
		return $data;
	}
	
	private function deleteExpiredEntries(){
		$selector=array('Source'=>$this->entryTable,'Expires<'=>date('Y-m-d H:i:s'));
		$this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector,TRUE);
	}
	
	private function decodeAuthorization($authorization){
		$authorizationArr=array('type'=>FALSE,'client_id'=>FALSE,'client_secret'=>FALSE);
		$authComps=explode(' ',$authorization);
		$authorizationArr['type']=array_shift($authComps);
		$authComps=current($authComps);
		if (!empty($authComps)){
			$authComps=base64_decode($authComps);
			$authComps=explode(':',$authComps);
			$authorizationArr['client_id']=array_shift($authComps);
			$authorizationArr['client_secret']=array_shift($authComps);
		}
		return $authorizationArr;
	}
	
	private function getAuthorizationHeader($data){
		$header=array();
		if (isset($data['client_id']) && isset($data['client_secret'])){
			$header['Authorization']='Basic '.base64_encode($data['client_id'].':'.$data['client_secret']);
		}
		return $header;
	}
	
	private function getScopeOptions(){
		$options=array();
		foreach($this->oc as $classWithNamespace=>$obj){
			$options[$classWithNamespace]=$classWithNamespace;
		}
		ksort($options);
		return $options;
	}
	
	public function clientAppCredentialsForm($arr){
		$arr['html']=(isset($arr['html']))?$arr['html']:'';
		if (!$this->oc['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],'Write',FALSE,FALSE,TRUE)){return $arr;}
		$contentStructure=array('scope'=>array('htmlBuilderMethod'=>'select','excontainer'=>TRUE,'value'=>'','keep-element-content'=>TRUE,'options'=>$this->getScopeOptions()),
								'client_app'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'client_id'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								'client_secret'=>array('htmlBuilderMethod'=>'element','tag'=>'input','type'=>'text','excontainer'=>TRUE),
								);
		$selector=array('Source'=>$this->entryTable,'Type'=>$this->entryTable.' credentials','Group'=>'Client credentials','Folder'=>$arr['selector']['EntryId'],'Privileges'=>$arr['selector']['Privileges']);
		$selector['Name']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(array('selector'=>$arr['selector']),4);
		$selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($selector,array('Group','Folder','Name','Type'),0);
		$selector=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($selector,'ALL_CONTENTADMIN_R','ALL_CONTENTADMIN_R');
		$arr=array('selector'=>$selector);
		$arr['callingClass']=__CLASS__;
		$arr['callingFunction']=__FUNCTION__;
		$arr['contentStructure']=$contentStructure;
		$arr['caption']='Client resource access';
		$arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
		return $arr;
	}

	
}
?>