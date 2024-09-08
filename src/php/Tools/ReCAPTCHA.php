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
    private const SERVICE_SCCOUNT_FILE='service_account.json';
    private $serviceAccountDir='';
    private $serviceAccountFile='';

    private $oc;
    private $siteKey='';
    private $projectId='';
    
    public function __construct($oc){
        $this->oc=$oc;
        // create directory for Service Account json
        $this->serviceAccountDir=$GLOBALS['dirs']['setup'].'ReCAPTCHA/';
        if (!is_dir($this->serviceAccountDir)){mkdir($this->serviceAccountDir,0750,TRUE);}
        // load service account file to environment
        $this->serviceAccountFile=$this->serviceAccountDir.self::SERVICE_SCCOUNT_FILE;
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

    public function add(array $arr):array
    {
        if (!empty($this->siteKey)){
            if (!is_file($this->serviceAccountFile)){
                $this->oc['logger']->log('warning','Google Service Account file not found, check {file}',array('file'=>$this->serviceAccountFile));
            } else if (empty(getenv("GOOGLE_APPLICATION_CREDENTIALS"))){
                $this->oc['logger']->log('warning','Google Service Account environment is not set',array());
            } else if (strpos($arr['toReplace']['{{content}}'],'g-recaptcha')!==FALSE){
                $arr['toReplace']['{{head}}']='<script src="https://www.google.com/recaptcha/enterprise.js?render='.urlencode($this->siteKey).'" async defer></script>'.$arr['toReplace']['{{head}}'];
                //$arr['toReplace']['{{head}}']='<script src="https://www.google.com/recaptcha/api.js"></script>'.$arr['toReplace']['{{head}}'];
                preg_match_all('/<[^<]+class=\"g-recaptcha\"[^>]+>/',$arr['toReplace']['{{content}}'],$matches);
                foreach(current($matches) as $matchIndex=>$match){
                    preg_match_all('/(name=")([^\"]+)(")/',$match,$nameMatches);
                    if (!empty($nameMatches[2][0])){
                        $action=$nameMatches[2][0];
                    } else {
                        $action='submit';
                    }
                    $newTag=str_replace('class="g-recaptcha"','class="g-recaptcha" data-sitekey="'.htmlspecialchars($this->siteKey).'" data-callback="onSubmit" data-action="'.$action.'"',$match);
                    $arr['toReplace']['{{content}}']=str_replace($match,$newTag,$arr['toReplace']['{{content}}']);
                }
            }
        }
        return $arr;
    }

    /**
     * Create an assessment to analyse the risk of a UI action.
    * @param string $recaptchaKey The reCAPTCHA key associated with the site/app
    * @param string $token The generated token obtained from the client.
    * @param string $project Your Google Cloud project ID.
    * @param string $action Action name corresponding to the token.
    */
    public function createAssessment($arr):array
    {
        $return=array('error'=>'','score'=>0,'action'=>'');
        if (empty($this->siteKey)){
            $return['error']='reCaptchaKey is empty';
        } else {
            $action='submit';
            // Create the reCAPTCHA client.
            // TODO: Cache the client generation code (recommended) or call client.close() before exiting the method.
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
            }
        }
        return $return;
    }

}
?>