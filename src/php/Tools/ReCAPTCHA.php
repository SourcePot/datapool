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
    
    private $oc;
    private $siteKey='';
    
    public function __construct($oc){
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
        $this->siteKey=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('Google reCAPTCHA site key [not used if empty]');
    }

    public function add(array $arr):array
    {
        if (!empty($this->siteKey)){
            $arr['toReplace']['{{head}}']='<script src="https://www.google.com/recaptcha/enterprise.js?render='.urlencode($this->siteKey).'"></script>'.$arr['toReplace']['{{head}}'];
            $arr['toReplace']['{{content}}']=str_replace('class="g-recaptcha"','class="g-recaptcha" data-sitekey="'.htmlspecialchars($this->siteKey).'" data-callback="onSubmit" data-action="submit"',$arr['toReplace']['{{content}}']);
        }
        return $arr;
    }

}
?>