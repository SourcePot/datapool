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

class ClientAccess implements \SourcePot\Datapool\Interfaces\Job{
    
    private const AUTHORIZATION_LIFESPAN=1200;
    private const FAILED_LOGIN_DETECTION_TIMESPAN=100;
    private const FAILED_LOGIN_COUNT_THRESHOLD=3;
    private const METHOD_WHITELIST=[
        'SourcePot\Datapool\Processing\RemoteClient::clientCall'=>'SourcePot\Datapool\Processing\RemoteClient&rarr;clientCall()',
    ];
    
    private $oc=[];
    
    private $entryTable='';
    private $entryTemplate=[
        'Read'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Read access setting. It is a bit-array.'],
        'Write'=>['type'=>'SMALLINT UNSIGNED','value'=>'ALL_CONTENTADMIN_R','Description'=>'This is the entry specific Write access setting. It is a bit-array.'],
        'Owner'=>['type'=>'VARCHAR(100)','value'=>'SYSTEM','Description'=>'This is the Owner\'s EntryId or SYSTEM. The Owner has Read and Write access.'],
    ];

    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }

    public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function init()
    {
        $this->entryTemplate=$this->oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
    }

    public function job(array $vars):array
    {
        $validUser=[];
        $validUserSelector=['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'Privileges>'=>2];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($validUserSelector,TRUE) as $user){
            if (strpos($user['EntryId'],'oneTimeLink')!==FALSE){continue;}
            $validUser[$user['EntryId']]=$user['Name'];
        }
        $deletedClientCredentials=0;
        $clientCredentialsSelector=['Source'=>$this->getEntryTable(),'Group'=>'Client credentials'];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($clientCredentialsSelector,TRUE) as $clientCredentials){
            if (isset($validUser[$clientCredentials['Folder']])){continue;}
            $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($clientCredentials,TRUE);
            $deletedClientCredentials++;
        }
        $vars['Msg']=['Privileged user found'=>count($validUser),'Client credentials deleted'=>$deletedClientCredentials];
        return $vars;
    }

    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
    
    public function request(array $arr):array
    {
        $header=[];
        if ($this->oc['SourcePot\Datapool\Tools\NetworkTools']->isHttps() || \SourcePot\Datapool\Root::PRODUCTION_ENVIRONMENT===FALSE){
            // process the request if https is confirmed or testing
            $data=$this->globals2data();
            $headers=apache_request_headers();
            if (isset($headers['Authorization'])){
                $data['Authorization']=$headers['Authorization'];
            }
            $data=$this->request2data($data);
        } else {
            // client requests must use HTTPS
            $data['answer']=['error'=>'https is required'];
        }
        $arr['data']=$data;
        $this->oc['SourcePot\Datapool\Tools\NetworkTools']->answer($header,$data['answer']);
        return $arr;
    }

    private function globals2data(array $data=[]):array
    {
        foreach($_POST as $name=>$value){
            $data[$name]=filter_input(INPUT_POST,$name);
        }
        foreach($_GET as $name=>$value){
            $data[$name]=filter_input(INPUT_GET,$name);
        }
        return $data;
    }
    
    private function request2data(array $data):array
    {
        $this->deleteExpiredEntries();
        $data['grant_type']=$data['grant_type']??'';
        $data['Authorization']=$data['Authorization']??'';
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__];
        $context['ipFailedNeedle']=$this->getFailedNeedle();
        if (strcmp($data['grant_type'],'authorization_code')===0 && mb_strpos($data['Authorization'],'Basic ')===0){
            // new token request
            $data=$this->newToken($data);
            return $data;
        } else if (mb_strpos($data['Authorization'],'Bearer ')===0){
            // check token
            $data=$this->checkToken($data);
            unset($data['Authorization']);
            // call the method on the object provided as Client credential's scope
            if (empty($data['answer']['error'])){
                $scopeComps=explode('::',$data['answer']['scope']);
                $class=$scopeComps[0];
                $method=$scopeComps[1]??'';
                $data['client_id']=$data['answer']['client_id'];
                if (!isset(self::METHOD_WHITELIST[$data['answer']['scope']])){
                    $data['answer']['error']='Invalid scope '.$data['answer']['scope'].'()';
                } else if (!method_exists($this->oc[$class],$method)){
                    $data['answer']['error']='Scope does not exist '.$data['answer']['scope'].'()';
                } else {
                    // set user from owner
                    $user=['Source'=>$this->oc['SourcePot\Datapool\Foundation\User']->getEntryTable(),'EntryId'=>$data['answer']['owner']];
                    $user=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry($user,TRUE);
                    $this->oc['SourcePot\Datapool\Root']->updateCurrentUser($user);
                    // invoke client requested method
                    $tokenExpiresInSec=$data['answer']['expires_in'];
                    unset($data['answer']);
                    $data['answer']=$this->oc[$class]->$method($data);
                    $data['answer']['token_expires_in_sec']=$tokenExpiresInSec;
                    return $data;
                }
            } else {
                // check token failed, logged by checkToken() already
                return $data;
            }
        } else {
            // authorization missing
            $data['answer']['error']='Authorization missing';
        }
        $this->oc['logger']->log('warning','{ipFailedNeedle}: '.$data['answer']['error'],$context);        
        return $data;
    }
    
    private function newToken(array $data):array
    {
        $context=['class'=>__CLASS__,'function'=>__FUNCTION__,'ipFailedNeedle'=>$this->getFailedNeedle()];
        $authorizationArr=$this->decodeAuthorization($data['Authorization']);
        // get credentials entry and try match
        $authorizationEntry=FALSE;
        if ($this->tooManyFailedTokenChecks($context['ipFailedNeedle'])){
            // ip is blocked
            $this->oc['logger']->log('info','{class}&rarr;{function}() IP blocked',['class'=>__CLASS__,'function'=>__FUNCTION__]);
            return $data;
        } else if (!empty($authorizationArr['type']) && !empty($authorizationArr['client_id']) && !empty($authorizationArr['client_secret'])){
            $context['client_id']=$authorizationArr['client_id'];
            $credentialsSelector=['Source'=>$this->entryTable,'Group'=>'Client credentials','Content'=>'%'.$authorizationArr['client_id'].'%'];
            foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($credentialsSelector,TRUE) as $entry){
                if (hash_equals($entry['Content']['client_id'],$authorizationArr['client_id']) && hash_equals($entry['Content']['client_secret'],$authorizationArr['client_secret'])){
                    $authorizationEntry=$entry;
                    unset($authorizationEntry['Content']['client_secret']);
                }
            }
            if (empty($authorizationEntry)){
                $data['answer']['error']='Invalid client';
            }
        } else {
            $data['answer']['error']='Invalid authorization request';
        }
        if (!empty($authorizationEntry)){
            // create new token
            $accessToken=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(64);
            $authorizationEntry['Expires']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.strval(time()+self::AUTHORIZATION_LIFESPAN));
            $authorizationEntry['Date']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now');
            $authorizationEntry['Owner']='SYSTEM';
            $authorizationEntry['Name']=$accessToken;
            $authorizationEntry['Group']='Client token';
            $authorizationEntry=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($authorizationEntry);
            $tokenContent=['access_token'=>$accessToken,'expires_in'=>self::AUTHORIZATION_LIFESPAN,'expires'=>time()+self::AUTHORIZATION_LIFESPAN,'expires_datetime'=>$authorizationEntry['Expires']];
            $authorizationEntry['Content']=array_replace_recursive($authorizationEntry['Content'],$tokenContent);
            $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($authorizationEntry,TRUE);
            // return new token
            $data['answer']=$authorizationEntry['Content'];
            $this->oc['logger']->log('info','Client "{client_id}" authorization success',$context);    
            return $data;
        }
        $this->oc['logger']->log('warning','{ipFailedNeedle}: '.$data['answer']['error'],$context);
        return $data;
    }
    
    private function checkToken(array $data):array
    {
        $data['answer']['error']='Invalid grant';
        $tokenSelector=['Source'=>$this->entryTable,'Name'=>mb_substr($data['Authorization'],7),'Expires>'=>date('Y-m-d H:i:s')];
        $tokenSelector['ipFailedNeedle']=$this->getFailedNeedle();
        if ($this->tooManyFailedTokenChecks($tokenSelector['ipFailedNeedle'])){
            $this->oc['logger']->log('info','{class}&rarr;{function}() IP blocked',['class'=>__CLASS__,'function'=>__FUNCTION__]);
            return $data;
        } else if (mb_strlen($tokenSelector['Name'])!==64){
            $data['answer']['error']='Invalid token';
        } else {
            // ip valid, check token
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
        }
        $this->oc['logger']->log('warning','{ipFailedNeedle}: '.$data['answer']['error'],$tokenSelector);
        return $data;
    }

    private function getFailedNeedle():string
    {
        $ip=$this->oc['SourcePot\Datapool\Root']->getIP(FALSE);
        $safeIP=str_replace(['%','_'],['\%','\_'],$ip);
        return 'Failed client request from '.$ip;
    }

    private function tooManyFailedTokenChecks(string $ipFailedNeedle):bool
    {
        $selector=['Source'=>$this->oc['SourcePot\Datapool\Foundation\Logger']->getEntryTable(),'Content'=>'%'.$ipFailedNeedle.'%'];
        $selector['Date>']=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('@'.(time()-self::FAILED_LOGIN_DETECTION_TIMESPAN));
        return $this->oc['SourcePot\Datapool\Foundation\Database']->getRowCount($selector,TRUE)>=self::FAILED_LOGIN_COUNT_THRESHOLD;
    }
    
    private function deleteExpiredEntries()
    {
        $selector=['Source'=>$this->entryTable,'Expires<'=>date('Y-m-d H:i:s')];
        $this->oc['SourcePot\Datapool\Foundation\Database']->deleteEntries($selector,TRUE);
    }
    
    private function decodeAuthorization(string $authorization):array
    {
        $authorizationArr=['type'=>FALSE,'client_id'=>FALSE,'client_secret'=>FALSE];
        $authComps=explode(' ',$authorization);
        $authorizationArr['type']=array_shift($authComps);
        $authComps=current($authComps);
        if (!empty($authComps)){
            $authComps=base64_decode($authComps);
            $colonPos=mb_strpos($authComps,':');
            if ($colonPos!==FALSE){
                $authorizationArr['client_id']=mb_substr($authComps,0,$colonPos);
                $authorizationArr['client_secret']=mb_substr($authComps,$colonPos+1);
            }
        }
        return $authorizationArr;
    }
    
    private function getAuthorizationHeader(array $data):array
    {
        $header=[];
        if (isset($data['client_id']) && isset($data['client_secret'])){
            $header['Authorization']='Basic '.base64_encode($data['client_id'].':'.$data['client_secret']);
        }
        return $header;
    }
    
    public function clientAppCredentialsForm(array $arr):array
    {
        $arr['html']=(isset($arr['html']))?$arr['html']:'';
        if (!$this->oc['SourcePot\Datapool\Foundation\Access']->access($arr['selector'],'Write',[],FALSE,TRUE)){return $arr;}
        $contentStructure=[
            'scope'=>['method'=>'select','excontainer'=>TRUE,'value'=>key(self::METHOD_WHITELIST),'keep-element-content'=>TRUE,'options'=>self::METHOD_WHITELIST],
            'method'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'clientCall','excontainer'=>TRUE],
            'client_id'=>['method'=>'element','tag'=>'input','type'=>'text','value'=>'pi','excontainer'=>TRUE],
            'client_secret'=>['method'=>'element','tag'=>'input','value'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getRandomString(32),'type'=>'text','excontainer'=>TRUE],
            'Access token request'=>['method'=>'getClientInfo'],
        ];
        $selector=['Source'=>$this->entryTable,'Group'=>'Client credentials','Folder'=>$arr['selector']['EntryId'],'Owner'=>$arr['selector']['Owner']];
        $selector['Name']=$this->oc['SourcePot\Datapool\Foundation\User']->userAbstract(['selector'=>$arr['selector']],4);
        $selector=$this->oc['SourcePot\Datapool\Tools\MiscTools']->addEntryId($selector,['Group','Folder','Name'],0);
        $selector=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($selector,'ALL_CONTENTADMIN_R','ALL_CONTENTADMIN_R');
        $arr=['selector'=>$selector];
        $arr['callingClass']=__CLASS__;
        $arr['callingFunction']=__FUNCTION__;
        $arr['contentStructure']=$contentStructure;
        $arr['caption']='Client resource access';
        $arr['html']=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->entryListEditor($arr);
        return $arr;
    }

    public function getClientInfo(array $arr):string
    {
        $client=$this->oc['SourcePot\Datapool\Foundation\Database']->hasEntry(['Source'=>$this->entryTable,'EntryId'=>$arr['key'][0]]);
        if (isset($client['Content']['client_id']) && isset($client['Content']['client_secret'])){
            $authorization=$this->getAuthorizationHeader($client['Content']);
            $text='resource.php?grant_type=authorization_code&Authorization='.urlencode($authorization['Authorization']);
        } else {
            $text='"client_id" or "client_secret" not set';    
        }
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'p','element-content'=>$text,'style'=>['width'=>'max-content','background-color'=>'#fff','color'=>'#000','padding'=>'0 0.25rem 1rem 0.25rem']]);
        $html=$this->oc['SourcePot\Datapool\Foundation\Element']->element(['tag'=>'div','element-content'=>$html,'keep-element-content'=>TRUE,'style'=>['max-width'=>'200px','overflow'=>'auto']]);
        return $html;
    }
    
}
?>