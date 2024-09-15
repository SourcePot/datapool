<?php
/*
* This file is part of the Datapool CMS package.
* This class provides client acces to a resource. 
* Security is provided through Basic Authentication and limited scopes defined as part of the user credentials.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class ClientAccess{
    
    private $authorizationLifespan=60;
    
    private $oc;
    
    private $entryTable;
    private $entryTemplate=array('Read'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'),
                                 'Write'=>array('type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'),
                                 'Owner'=>array('type'=>'VARCHAR(100)','value'=>'SYSTEM','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.'),
                                 );

    private $methodBlackList=array('run','init');
    
    public function __construct($oc){
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
    public function request($arr,$isDebugging=FALSE){
        $debugArr=array('arr in'=>$arr);
        $header=array();
        $whitelist=array('127.0.0.1','::1');
       if (isset($_SERVER['HTTPS']) || in_array($_SERVER['REMOTE_ADDR'],$whitelist)){
            // process the request if https is confirmed
            $data=$this->globals2data();
            $headers=apache_request_headers();
            if (isset($headers['Authorization'])){
                $data['Authorization']=$headers['Authorization'];
            }
            $debugArr['headers in']=$headers;
            $data=$this->request2data($data);
        } else {
            // client requests must use HTTPS
            $data['answer']=array('error'=>'https is required');
        }
        $arr['data']=$data;
        $this->oc['SourcePot\Datapool\Tools\NetworkTools']->answer($header,$data['answer']);
        if ($isDebugging){
            $debugArr['arr out']=$arr;
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
        }
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
    * This method compiles the answer from the request and adds it to the data argument.
    * There are two request types: a request for an new access token and a request to invoke the method stated in the request.
    * The scope of the request is the object (class with namespace) provided by the "Client credentials"-entry.
    * @param array data
    * @return array data
    */
    private function request2data($data,$isDebugging=FALSE){
        $this->deleteExpiredEntries();
        $data['grant_type']=(isset($data['grant_type']))?$data['grant_type']:'';
        $data['Authorization']=(isset($data['Authorization']))?$data['Authorization']:'';
        if (strcmp($data['grant_type'],'authorization_code')===0 && mb_strpos($data['Authorization'],'Basic ')===0){
            // new token request
            $data=$this->newToken($data);
        } else if (mb_strpos($data['Authorization'],'Bearer ')===0){
            // check token
            $data=$this->checkToken($data);
            unset($data['Authorization']);
            // call the method on the object provided as Client credential's scope
            if (empty($data['answer']['error'])){
                $class=$data['answer']['scope'];
                $method=$data['answer']['method'];
                $data['client_id']=$data['answer']['client_id'];
                if (in_array($method,$this->methodBlackList)){
                    $data['answer']['error']='Access '.$class.'::'.$method.'() blocked';
                    $this->oc['logger']->log('warning',$data['answer']['error'],array());    
                } else if (method_exists($this->oc[$class],$method)){
                    // set user from owner
                    $user=array('Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$data['answer']['owner']);
                    $user=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($user,TRUE);
                    $this->oc['SourcePot\Datapool\Root']->updateCurrentUser($user);
                    // invoke client requested method
                    $tokenExpiresInSec=$data['answer']['expires_in'];
                    unset($data['answer']);
                    $data['answer']=$this->oc[$class]->$method($data);
                    $data['answer']['token_expires_in_sec']=$tokenExpiresInSec;
                } else {
                    $data['answer']=array('error'=>'Method '.$class.'::'.$method.'() does not exist');
                }
            } else {
                $this->oc['logger']->log('error','Access token failed: {failed}',array('failed'=>$data['answer']['error']));    
            }
        } else {
            // authorization missing
            $data['answer']['error']='invalid_request';
        }
        if ($isDebugging){
            $debugArr['data']=$data;
            $this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2file($debugArr);
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
        $authorizationArr['ip']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getIP(FALSE);
        // get credentials entry and try match
        $authorizationEntry=FALSE;
        if (!empty($authorizationArr['type']) && !empty($authorizationArr['client_id']) && !empty($authorizationArr['client_secret'])){
            $credentialsSelector=array('Source'=>$this->entryTable,'Group'=>'Client credentials','Content'=>'%'.$authorizationArr['client_id'].'%');
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($credentialsSelector,TRUE) as $entry){
                if (strcmp($entry['Content']['client_id'],$authorizationArr['client_id'])===0 && strcmp($entry['Content']['client_secret'],$authorizationArr['client_secret'])===0){
                    $authorizationEntry=$entry;
                    break;
                }
            }
        } else {
            $data['answer']['error']='invalid_authorization_request';
            $this->oc['logger']->log('error','Invalid authorization request origin "{ip}": type="{type}", client_secret="{client_secret}", client_id="{client_id}"',$authorizationArr);
        }
        if (empty($authorizationEntry)){
            // no matching credentials entry found
            $data['answer']['error']='invalid_client';
            $this->oc['logger']->log('error','Invalid client origin "{ip}": type="{type}", client_secret="{client_secret}", client_id="{client_id}"',$authorizationArr);    
        } else {
            // create new token
            $accessToken=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(64);
            $authorizationEntry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.strval(time()+$this->authorizationLifespan));
            $authorizationEntry['Owner']='SYSTEM';
            $authorizationEntry['Name']=$accessToken;
            $authorizationEntry['Group']='Client token';
            $authorizationEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($authorizationEntry);
            $tokenContent=array('access_token'=>$accessToken,'expires_in'=>$this->authorizationLifespan,'expires'=>time()+$this->authorizationLifespan,'expires_datetime'=>$authorizationEntry['Expires']);
            $authorizationEntry['Content']=array_replace_recursive($authorizationEntry['Content'],$tokenContent);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($authorizationEntry,TRUE);
            // return new token
            $data['answer']=$authorizationEntry['Content'];
            $this->oc['logger']->log('info','Client authorization success origin "{ip}": type="{type}", client_secret="***", client_id="{client_id}"',$authorizationArr);    
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
        $tokenSelector=array('Source'=>$this->entryTable,'Name'=>mb_substr($data['Authorization'],7));
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($tokenSelector,TRUE) as $token){
            $data['answer']=$token['Content'];
            $data['answer']['owner']=$token['Owner'];
            unset($data['answer']['access_token']);
            unset($data['answer']['error']);
            //
            $datetimeObj=new \DateTime($token['Expires'],new \DateTimeZone(\SourcePot\Datapool\Root::DB_TIMEZONE));
            $data['answer']['expires_in']=$datetimeObj->getTimestamp()-time();
            return $data;
        }
        $tokenSelector['ip']=$this->oc['SourcePot\Datapool\Tools\NetworkTools']->getIP(FALSE);
        $this->oc['logger']->log('notice','Client token originating from {ip} failed: Source="{Source}", Name="{Name}"',$tokenSelector);    
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
            //var_dump(urlencode($header['Authorization']));
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
        $contentStructure=array('scope'=>array('method'=>'select','excontainer'=>TRUE,'value'=>'SourcePot\Datapool\Processing\RemoteClient','keep-element-content'=>TRUE,'options'=>$this->getScopeOptions()),
                                'method'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'clientCall','excontainer'=>TRUE),
                                'client_id'=>array('method'=>'element','tag'=>'input','type'=>'text','value'=>'pi','excontainer'=>TRUE),
                                'client_secret'=>array('method'=>'element','tag'=>'input','value'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(32),'type'=>'text','excontainer'=>TRUE),
                                'Access token request'=>array('method'=>'getClientInfo'),
                                );
        $selector=array('Source'=>$this->entryTable,'Group'=>'Client credentials','Folder'=>$arr['selector']['EntryId'],'Owner'=>$arr['selector']['Owner']);
        $selector['Name']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(array('selector'=>$arr['selector']),4);
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($selector,array('Group','Folder','Name'),0);
        $selector=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($selector,'ALL_CONTENTADMIN_R','ALL_CONTENTADMIN_R');
        $arr=array('selector'=>$selector);
        $arr['callingClass']=__CLASS__;
        $arr['callingFunction']=__FUNCTION__;
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Client resource access';
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $arr;
    }

    public function getClientInfo(array $arr):string
    {
        $client=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(array('Source'=>$this->entryTable,'EntryId'=>$arr['key'][0]));
        if (isset($client['Content']['client_id']) && isset($client['Content']['client_secret'])){
            $authorization=$this->getAuthorizationHeader($client['Content']);
            $text='resource.php?grant_type=authorization_code&Authorization='.urlencode($authorization['Authorization']);
        } else {
            $text='"client_id" or "client_secret" not set';    
        }
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'p','element-content'=>$text,'style'=>array('width'=>'max-content','background-color'=>'#fff','color'=>'#000','padding'=>'0 0.25rem 1rem 0.25rem')));
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(array('tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>array('max-width'=>'200px','overflow'=>'auto')));
        return $html;
    }
    
}
?>