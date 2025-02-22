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

class ReCAPTCHA{
    private const RECATCHA_CLASS_INDICATOR='g-recaptcha';
    private const SERVICE_ACCOUNT_FILE='service_account.json';
    private const MIN_SCORE=0.8;
    
    private $oc;
    private $siteKey='';
    private $projectId='';
    private $serviceAccountDir='';
    private $serviceAccountFile='';

    public function __construct($oc){
        $this->oc=$oc;
        // create directory for Service Account json
        $this->serviceAccountDir=$GLOBALS['dirs']['setup'].'ReCAPTCHA/';
        if (!is_dir($this->serviceAccountDir)){mkdir($this->serviceAccountDir,0750,TRUE);}
        // load service account file to environment
        $this->serviceAccountFile=$this->serviceAccountDir.self::SERVICE_ACCOUNT_FILE;
        if (is_file($this->serviceAccountFile)){
            putenv("GOOGLE_APPLICATION_CREDENTIALS=$this->serviceAccountFile");
        }
    }

    public function loadOc(array $oc):void
    {
        $this->oc=$oc;
        $this->siteKey=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('Google reCAPTCHA site key [not used if empty]');
        $this->projectId=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('Google Project ID');
    }

    /**
    * This method is called by the Root class during web page creation, after all html is added and before finalizing the page. 
    * @param array  $arr    Is the web page array 
    * @return array Returns the web page arrey with added ReCAPTCHA tags
    */
    public function add(array $arr):array
    {
        if (!empty($this->siteKey)){
            if (!is_file($this->serviceAccountFile)){
                $this->oc['logger']->log('warning','Google Service Account file not found, check {file}',array('file'=>$this->serviceAccountFile));
            } else if (empty(getenv("GOOGLE_APPLICATION_CREDENTIALS"))){
                $this->oc['logger']->log('warning','Google Service Account environment is not set',[]);
            } else if (strpos($arr['toReplace']['{{content}}'],self::RECATCHA_CLASS_INDICATOR)!==FALSE){
                // ReCAPTCHA class attributes found 
                $arr['toReplace']['{{head}}']='<script src="https://www.google.com/recaptcha/enterprise.js?render='.urlencode($this->siteKey).'" async defer></script>'.$arr['toReplace']['{{head}}'];
                //$arr['toReplace']['{{head}}']='<script src="https://www.google.com/recaptcha/api.js"></script>'.$arr['toReplace']['{{head}}'];
                $arr['toReplace']['{{content}}']=$this->extractAndReplaceReCAPTCHAtags($arr['toReplace']['{{content}}']);
            }
        }
        return $arr;
    }

    /**
    * This method adds to all html-elements with the self::RECATCHA_CLASS_INDICATOR class atrriute futher attributes, i.e data-sitekey, data-callback, data-action. 
    * @param string  $str is the html string    
    * @return string Returns enriched html string
    */
    private function extractAndReplaceReCAPTCHAtags(string $str):string
    {
        preg_match_all('/<[^<]+class=\"'.self::RECATCHA_CLASS_INDICATOR.'\"[^>]+>/',$str,$matches);
        foreach(current($matches) as $matchIndex=>$match){
            $attrs=[];
            preg_match_all('/(\s)([a-z]+)(=")([^\"]+)(")/',$match,$attrMatches);
            foreach($attrMatches[2] as $index=>$attr){
                $attrs[$attr]=$attrMatches[4][$index];
            }
            if (empty($attrs['value'])){continue;}
            // add attributes to session
            $action=preg_replace('/[^a-zA-Z]/','_',$attrs['value']);
            $_SESSION[__CLASS__][$action]=$attrs;
            // create new tag, remove id and name
            $newTag=str_replace('class="'.self::RECATCHA_CLASS_INDICATOR.'"','class="'.self::RECATCHA_CLASS_INDICATOR.'" data-sitekey="'.htmlspecialchars($this->siteKey).'" data-callback="onSubmit" data-action="'.$action.'"',$match);
            if (isset($attrs['name'])){
                $newTag=str_replace('name="'.$attrs['name'].'"','',$newTag);
            }
            $tagId=md5($newTag);
            if (isset($attrs['id'])){$newTag=str_replace('id="'.$attrs['id'].'"','id="'.$tagId.'"',$newTag);}
            $_SESSION[__CLASS__][$action]['tagId']=$tagId;
            // replace original tag with new tag
            $str=str_replace($match,$newTag,$str);
        }
        return $str;
    }

    /**
    * Create an assessment to analyse the risk of a UI action.
    * @param array $arr The array containing the the generated token obtained from the client.
    */
    public function createAssessment($arr):array
    {
        $return=array('error'=>'','score'=>0,'action'=>'');
        if (empty($this->siteKey)){
            $return['error']='reCaptchaKey is empty';
        } else {
            // Create the reCAPTCHA client.
            try{
                $client = new \Google\Cloud\RecaptchaEnterprise\V1\RecaptchaEnterpriseServiceClient();
                $projectName = $client->projectName($this->projectId);
            } catch (\Exception $e){
                $return['error']='Google object creation RecaptchaEnterpriseServiceClient failed';
                $this->oc['logger']->log('warning',$return['error'].' with: {exception}',array('exception'=>$e->getMessage()));
            }
            if (empty($return['error'])){
                // Set the properties of the event to be tracked.
                $event = (new \Google\Cloud\RecaptchaEnterprise\V1\Event())->setSiteKey($this->siteKey)->setToken($arr['token']);
                // Build the assessment request.
                $assessment = (new \Google\Cloud\RecaptchaEnterprise\V1\Assessment())->setEvent($event);
                try{
                    $response = $client->createAssessment($projectName,$assessment);
                    if ($response->getTokenProperties()->getValid()==false){
                        //The CreateAssessment() call failed because the token was invalid
                        $return['error']=\Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason::name($response->getTokenProperties()->getInvalidReason());
                    } else {
                        $return['score']=$response->getRiskAnalysis()->getScore();
                    }
                    // Check if the expected action was executed.
                    $return['action']=$response->getTokenProperties()->getAction();
                } catch (\Exception $e) {
                    $return['error']='CreateAssessment() call failed with the following error: '.$e->getMessage();
                }
                $client->close(); // call client.close() before exiting the method
            }
        }
        $return['tagId']=$_SESSION[__CLASS__][$return['action']]['tagId'];
        if ($return['score']>self::MIN_SCORE){
            // accept action
            $return['grant']=1;
            if (isset($_SESSION[__CLASS__][$return['action']]['id'])){$return['id']=$_SESSION[__CLASS__][$return['action']]['id'];}
            if (isset($_SESSION[__CLASS__][$return['action']]['name'])){$return['name']=$_SESSION[__CLASS__][$return['action']]['name'];}
            $_SESSION[__CLASS__]=[];
        } else {
            // reject action
            $return['grant']=0;
        }
        return $return;
    }

}
?>