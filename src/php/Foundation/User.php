<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class User{
    
    private $oc;
    
    private $entryTable='';
    private $entryTemplate=['Privileges'=>['type'=>'SMALLINT UNSIGNED','value'=>1,'Description'=>'Is the user level the user was granted.'],
                            'LoginId'=>['type'=>'VARCHAR(512)','value'=>'','Description'=>'Is a login id derived from the passphrase.']
                            ];
    
    public $definition=['Type'=>['@tag'=>'p','@default'=>'user','@Read'=>'NO_R'],
                             'Content'=>['Contact details'=>['Title'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                                                            'First name'=>['@tag'=>'input','@type'=>'text','@default'=>'John','@excontainer'=>TRUE],
                                                            'Middle name'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                                                            'Family name'=>['@tag'=>'input','@type'=>'text','@default'=>'Doe','@excontainer'=>TRUE],
                                                            'Gender'=>['@function'=>'select','@options'=>['male'=>'male','female'=>'female','divers'=>'divers'],'@default'=>'male','@excontainer'=>TRUE],
                                                            'Language'=>['@function'=>'select','@options'=>['en'=>'English','de'=>'German','es'=>'Spanish','fr'=>'Frensh'],'@default'=>'en','@excontainer'=>TRUE],
                                                            'Email'=>['@tag'=>'input','@type'=>'email','@filter'=>FILTER_SANITIZE_EMAIL,'@default'=>'','@placeholder'=>'e.g. info@company.com','@excontainer'=>TRUE],
                                                            'Phone'=>['@tag'=>'input','@type'=>'tel','@default'=>'','@placeholder'=>'e.g. +49 89 1234567','@excontainer'=>TRUE],
                                                            'Mobile'=>['@tag'=>'input','@type'=>'tel','@default'=>'','@placeholder'=>'e.g. +49 160 1234567','@excontainer'=>TRUE],
                                                            'Fax'=>['@tag'=>'input','@type'=>'tel','@default'=>'','@excontainer'=>TRUE],
                                                            'My reference'=>['@tag'=>'input','@type'=>'text','@default'=>'','@placeholder'=>'e.g. Invoice processing','@excontainer'=>TRUE],
                                                            'Save'=>['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'],
                                                            ],
                                        'Address'=>['Company'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                                                            'Department'=>['@tag'=>'input','@type'=>'text','@default'=>'','@placeholder'=>'e.g. Patent Department','@excontainer'=>TRUE],
                                                            'Street'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                                                            'House number'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                                                            'Town'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                                                            'Zip'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                                                            'State'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                                                            'Country'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                                                            'Country code'=>['@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE],
                                                            'Save'=>['@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save','@isApp'=>'&#127758;'],
                                                        ],
                                              ],
                        'Login'=>['@function'=>'getLoginFormHtml','@isApp'=>'&#8688;','@hideKeys'=>TRUE,'@hideCaption'=>TRUE,'@class'=>'SourcePot\Datapool\Components\Login'],
                        'Icon etc.'=>['@function'=>'entryControls','@isApp'=>'&#128736;','@hideHeader'=>TRUE,'@hideKeys'=>TRUE,'@hideCaption'=>FALSE,'@hideDelete'=>TRUE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'],
                        'Privileges'=>['@function'=>'setAccessByte','@default'=>1,'@Write'=>'ADMIN_R','@Read'=>'ADMIN_R','@key'=>'Privileges','@isApp'=>'P','@hideKeys'=>TRUE,'@hideCaption'=>TRUE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'],
                        'App credentials'=>['@function'=>'clientAppCredentialsForm','@Write'=>'ALL_CONTENTADMIN_R','@Read'=>'ALL_CONTENTADMIN_R','@key'=>'Content','@isApp'=>'&#128274;','@hideKeys'=>TRUE,'@hideCaption'=>TRUE,'@class'=>'SourcePot\Datapool\Foundation\ClientAccess'],
                        'Map'=>['@function'=>'getMapHtml','@class'=>'SourcePot\Datapool\Tools\GeoTools','@default'=>'','@style'=>['width'=>360,'height'=>400]],
                        ];

    private $userRols=['Content'=>[0=>['Value'=>1,'Name'=>'Public','isAdmin'=>FALSE,'isPublic'=>TRUE,'Description'=>'Everybody not logged in'],
                                1=>['Value'=>2,'Name'=>'Registered','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Everybody registered'],
                                2=>['Value'=>4,'Name'=>'Member','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Initial member state'],
                                3=>['Value'=>8,'Name'=>'Group A','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group A member'],
                                4=>['Value'=>16,'Name'=>'Group B','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group B member'],
                                5=>['Value'=>32,'Name'=>'Group C','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group C member'],
                                6=>['Value'=>64,'Name'=>'Group D','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group D member'],
                                7=>['Value'=>128,'Name'=>'Group E','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group E member'],
                                8=>['Value'=>256,'Name'=>'Group F','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group F member'],
                                9=>['Value'=>512,'Name'=>'Group G','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group G member'],
                                10=>['Value'=>1024,'Name'=>'Sentinel','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Sentinel member'],
                                11=>['Value'=>2048,'Name'=>'Group I','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group I member'],
                                12=>['Value'=>4096,'Name'=>'Group J','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group J member'],
                                13=>['Value'=>8192,'Name'=>'Group K','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group K member'],
                                14=>['Value'=>16384,'Name'=>'Config admin','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Configuration admin'],
                                15=>['Value'=>32768,'Name'=>'Admin','isAdmin'=>TRUE,'isPublic'=>FALSE,'Description'=>'Administrator']
                                ],
                            'Type'=>'array',
                            'Read'=>'ALL_R',
                            'Write'=>'ADMIN_R',
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
        $this->userRols();
        // check database user entry definition 
        $this->oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
        // add calendar placeholder
        $this->oc['SourcePot\Datapool\Root']->addPlaceholder('{{Owner}}',$this->oc['SourcePot\Datapool\Root']->getCurrentUserEntryId());
    }
    
    public function getEntryTable():string
    {
        return $this->entryTable;
    }

    public function getEntryTemplate():array
    {
        return $this->entryTemplate;
    }
    
    private function userRols():array
    {
        $entry=$this->userRols;
        $entry['Class']=__CLASS__;
        $entry['EntryId']=__FUNCTION__;
        $this->userRols=$this->oc['SourcePot\Datapool\Foundation\Filespace']->entryByIdCreateIfMissing($entry,TRUE);
        return $this->userRols;
    }
    
    public function getUserRols(bool $asOptions=FALSE):array
    {
        if ($asOptions){
            $options=[];
            foreach($this->userRols['Content'] as $index=>$userRole){
                $options[$userRole['Value']]=$userRole['Name'];
            }
            return $options;
        } else {
            return $this->userRols['Content'];
        }
    }
    
    public function getUserRolsString(array $user):string
    {
        $userRols=[];
        foreach($this->userRols['Content'] as $index=>$rolArrc){
            if ((intval($user['Privileges']) & $rolArrc['Value'])>0){$userRols[]=$rolArrc['Name'];}
        }
        return implode(', ',$userRols);
    }
    
    public function unifyEntry(array $entry,bool $addDefaults=FALSE):array
    {
        $entry['Source']=$this->entryTable;
        if (empty($entry['Content']['Contact details']['Email']) && !empty($entry['Email']) && $addDefaults){
            $entry['Content']['Contact details']['Email']=$entry['Email'];
        }
        if (empty($entry['Params']['User registration']['Email']) && !empty($entry['Content']['Contact details']['Email'])){
            $entry['Params']['User registration']['Email']=$entry['Content']['Contact details']['Email'];
        }
        if (!isset($entry['Group'])){$entry['Group']=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');}
        if (!isset($entry['Folder'])){$entry['Folder']=$entry['Email'];}
        if (empty($entry['Name'])){$entry['Name']=$this->userAbstract(['selector'=>$entry],3);}
        if ($addDefaults){
            $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ADMIN_R','ADMIN_R');
            $entry=$this->oc['SourcePot\Datapool\Foundation\Definitions']->definition2entry($this->definition,$entry,FALSE);
        }
        $entry=$this->oc['SourcePot\Datapool\Tools\GeoTools']->address2location($entry);
        return $entry;
    }
    
    private function initPsw(){
        return trim(base64_encode(random_bytes(16)),'=');
    }
    
    public function initAdminAccount():bool
    {
        $noAdminAccountFound=empty($this->oc['SourcePot\Datapool\Foundation\Database']->entriesByRight('Privileges','ADMIN_R',TRUE));
        if ($noAdminAccountFound){
            $admin=['Source'=>$this->entryTable,'Privileges'=>'ADMIN_R',
                    'Email'=>$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('emailWebmaster'),
                    'Password'=>$this->initPsw(),
                    'Owner'=>'SYSTEM'
                    ];
            $admin['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($admin['Email']);
            $admin['LoginId']=$this->oc['SourcePot\Datapool\Foundation\Access']->loginId($admin['Email'],$admin['Password']);
            $admin['Content']['Contact details']['First name']='Admin';
            $admin['Content']['Contact details']['Family name']='Admin';
            $success=$this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($admin,TRUE);
            if ($success){
                // Save init admin details
                $adminFile=['Class'=>__CLASS__,'EntryId'=>__FUNCTION__];
                $adminFile['Content']['Admin email']=$admin['Email'];
                $adminFile['Content']['Admin password']=$admin['Password'];
                $access=$this->oc['SourcePot\Datapool\Foundation\Filespace']->insertEntry($adminFile,TRUE);
                $this->oc['logger']->log('alert','No admin account found. I have created a new admin account, the credential can be found in ..\\setup\\User\\'.__FUNCTION__.'.json');    
                return TRUE;
            }
        }
        return FALSE;
    }
    
    public function newlyRegisteredUserLogin(array $user):array
    {
        $user['Owner']=$user['EntryId'];
        $user['LoginId']=$user['LoginId'];
        $user['Privileges']='REGISTERED_R';
        $user=$this->oc['SourcePot\Datapool\Foundation\Database']->insertEntry($user);
        $this->loginUser($user);
        return $user;
    }
    
    public function userAbstract(array|string $arr=[],int $template=0):string
    {
        // This method returns formated html text from an entry based on predefined templates.
        //     
        if (empty($arr)){
            $user=$this->oc['SourcePot\Datapool\Root']->getCurrentUser();;
        } else if (!is_array($arr)){
            $user=['Source'=>$this->entryTable,'EntryId'=>trim($arr)];
        } else if (isset($arr['selector'])){
            $user=$arr['selector'];
        } else {
            $user=$arr;
        }
        if (!isset($user['Content'])){
            if ($template<4){
                $isSystemCall=TRUE;
            } else {
                $isSystemCall=FALSE;
            }
            $user=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,$isSystemCall);
            if (empty($user)){
                return '';
            }
        }
        $S=$this->oc['SourcePot\Datapool\Tools\MiscTools']->getSeparator();
        if ($template===0){
            $abtract='{{Content'.$S.'Contact details'.$S.'First name}}';
        } else if ($template===1){
            $abtract='{{Content'.$S.'Contact details'.$S.'First name}} {{Content'.$S.'Contact details'.$S.'Family name}}';
        } else if ($template===2){
            $abtract='{{ICON}} <p class="user-abstract">{{Content'.$S.'Contact details'.$S.'First name}} {{Content'.$S.'Contact details'.$S.'Family name}}</p>';
        } else if ($template===3){
            $abtract='{{Content'.$S.'Contact details'.$S.'Family name}}, {{Content'.$S.'Contact details'.$S.'First name}}';
        } else if ($template===4){
            $abtract='{{Content'.$S.'Contact details'.$S.'Family name}}, {{Content'.$S.'Contact details'.$S.'First name}} ({{Content'.$S.'Contact details'.$S.'Email}})';
        } else if ($template===5){
            $abtract='{{Content'.$S.'Contact details'.$S.'Family name}}, {{Content'.$S.'Contact details'.$S.'First name}} ({{Content'.$S.'Address'.$S.'Town}})';
        } else if ($template===6){
            $abtract='{{Content'.$S.'Contact details'.$S.'First name}} {{Content'.$S.'Contact details'.$S.'Family name}} <{{Content'.$S.'Contact details'.$S.'Email}}>';
        } else if ($template===7){
            $abtract='{{Content'.$S.'Contact details'.$S.'Email}}';
        } else if ($template===8){
            $abtract='{{Content'.$S.'Contact details'.$S.'Phone}}';
        } else if ($template===9){
            $abtract='{{Content'.$S.'Contact details'.$S.'Mobile}}';
        }
        $user['ICON']=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getIcon(['selector'=>$user,'returnHtmlOnly'=>TRUE]);
        $abtract=trim($this->template2string($abtract,$user,['class'=>'user-abstract']),' ,;.|');
        if (!empty($arr['wrapResult'])){
            $wrapper=$arr['wrapResult'];
            $wrapper['element-content']=$abtract;
            $wrapper['keep-element-content']=TRUE;
            $abtract=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($wrapper);
        }
        return $abtract;
    }
    
    private function template2string(string $template='Hello {{key}}...',array $arr=['key'=>'world']):string
    {
        $flatArr=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($arr);
        foreach($flatArr as $flatArrKey=>$flatArrValue){
            $template=str_replace('{{'.$flatArrKey.'}}',(string)$flatArrValue,$template);
        }
        return $template;
    }

    public function ownerAbstract(array $arr):string
    {
        $template=(isset($arr['selector']['template']))?$arr['selector']['template']:2;
        $html=$this->userAbstract($arr['selector']['Owner']??'MISSING',$template);
        $arr['tag']='div';
        $arr['element-content']=$html;
        $arr['keep-element-content']=TRUE;
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($arr);
        return $html;
    }
    
    public function userAccountForm(array $arr):array
    {
        $arr['html']=$arr['html']??'';
        if (isset($arr['selector']['EntryId'])){
            $arr['selector']=$this->oc['SourcePot\Datapool\Foundation\Database']->entryById($arr['selector'],TRUE);
            $arr['html'].=$this->oc['SourcePot\Datapool\Foundation\Definitions']->entry2form($arr['selector']);
        } else {
            $arr['html'].='Please select a user...';
        }
        return $arr;
    }

    public function loginUser(array $user)
    {
        $_SESSION['currentUser']=$user;
        $this->oc['SourcePot\Datapool\Root']->updateCurrentUser();
        $this->oc['logger']->log('info','Logged in "{userName}" at {dateTime}',['userName'=>$_SESSION['currentUser']['Name'],'dateTime'=>$this->oc['SourcePot\Datapool\Tools\MiscTools']->getDateTime('now','','','Y-m-d H:i:s (e)')]);    
    }
    
    public function getUserOptions(array $selector=[],string $flatContactDetailsKey=''):array
    {
        $selector['Source']=$this->entryTable;
        $selector['Privileges>']=1;
        $options=[];
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read') as $user){
            if (!isset($user['Content']['Contact details'])){continue;}
            $options[$user['EntryId']]=$user['Content']['Contact details']['Family name'].', '.$user['Content']['Contact details']['First name'];
            if (!empty($flatContactDetailsKey)){
                $flatUser=$this->oc['SourcePot\Datapool\Tools\MiscTools']->arr2flat($user);
                if (!empty($flatUser[$flatContactDetailsKey])){
                    $options[$user['EntryId']].=' ('.$flatUser[$flatContactDetailsKey].')';
                }
            }
        }
        asort($options);
        return $options;
    }

}
?>