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
    
    private $entryTable;
    private $entryTemplate=array('Type'=>array('type'=>'VARCHAR(100)','value'=>'user','Description'=>'This is the data-type of Content'),
                                 'Privileges'=>array('type'=>'SMALLINT UNSIGNED','value'=>1,'Description'=>'Is the user level the user was granted.'),
                                 'LoginId'=>array('type'=>'VARCHAR(512)','value'=>'','Description'=>'Is a login id derived from the passphrase.')
                                 );
    
    public $definition=array('Type'=>array('@tag'=>'p','@default'=>'user','@Read'=>'NO_R'),
                             'Content'=>array('Contact details'=>array('Title'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'First name'=>array('@tag'=>'input','@type'=>'text','@default'=>'John','@excontainer'=>TRUE),
                                                                 'Middle name'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Family name'=>array('@tag'=>'input','@type'=>'text','@default'=>'Doe','@excontainer'=>TRUE),
                                                                 'Gender'=>array('@function'=>'select','@options'=>array('male'=>'male','female'=>'female','divers'=>'divers'),'@default'=>'male','@excontainer'=>TRUE),
                                                                 'Language'=>array('@function'=>'select','@options'=>array('en'=>'English','de'=>'German','es'=>'Spanish','fr'=>'Frensh'),'@default'=>'en','@excontainer'=>TRUE),
                                                                 'Email'=>array('@tag'=>'input','@type'=>'email','@filter'=>FILTER_SANITIZE_EMAIL,'@default'=>'','@placeholder'=>'e.g. info@company.com','@excontainer'=>TRUE),
                                                                 'Phone'=>array('@tag'=>'input','@type'=>'tel','@default'=>'','@placeholder'=>'e.g. +49 89 1234567','@excontainer'=>TRUE),
                                                                 'Mobile'=>array('@tag'=>'input','@type'=>'tel','@default'=>'','@placeholder'=>'e.g. +49 160 1234567','@excontainer'=>TRUE),
                                                                 'Fax'=>array('@tag'=>'input','@type'=>'tel','@default'=>'','@excontainer'=>TRUE),
                                                                 'My reference'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@placeholder'=>'e.g. Invoice processing','@excontainer'=>TRUE),
                                                                 'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save'),
                                                                 ),
                                                'Address'=>array('Company'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Department'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@placeholder'=>'e.g. Patent Department','@excontainer'=>TRUE),
                                                                 'Street'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'House number'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Town'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Zip'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'State'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Country'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Country code'=>array('@tag'=>'input','@type'=>'text','@default'=>'','@excontainer'=>TRUE),
                                                                 'Save'=>array('@tag'=>'button','@value'=>'save','@element-content'=>'Save','@default'=>'save','@isApp'=>'&#127758;'),
                                                                ),
                                              ),
                             'Login'=>array('@function'=>'getLoginForm','@isApp'=>'&#8688;','@hideKeys'=>TRUE,'@hideCaption'=>TRUE,'@class'=>'SourcePot\Datapool\Components\Login'),
                             'Icon etc.'=>array('@function'=>'entryControls','@isApp'=>'&#128736;','@hideHeader'=>TRUE,'@hideKeys'=>TRUE,'@hideCaption'=>FALSE,'@hideDelete'=>TRUE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'),
                             'Privileges'=>array('@function'=>'setAccessByte','@default'=>1,'@Write'=>'ADMIN_R','@Read'=>'ADMIN_R','@key'=>'Privileges','@isApp'=>'P','@hideKeys'=>TRUE,'@hideCaption'=>TRUE,'@class'=>'SourcePot\Datapool\Tools\HTMLbuilder'),
                             'App credentials'=>array('@function'=>'clientAppCredentialsForm','@Write'=>'ALL_CONTENTADMIN_R','@Read'=>'ALL_CONTENTADMIN_R','@key'=>'Content','@isApp'=>'&#128274;','@hideKeys'=>TRUE,'@hideCaption'=>TRUE,'@class'=>'SourcePot\Datapool\Foundation\ClientAccess'),
                             'Map'=>array('@function'=>'getMapHtml','@class'=>'SourcePot\Datapool\Tools\GeoTools','@default'=>'','@style'=>array('width'=>360,'height'=>400)),
                             );

    private $userRols=array('Content'=>array(0=>array('Value'=>1,'Name'=>'Public','isAdmin'=>FALSE,'isPublic'=>TRUE,'Description'=>'Everybody not logged in'),
                                             1=>array('Value'=>2,'Name'=>'Registered','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Everybody registered'),
                                             2=>array('Value'=>4,'Name'=>'Member','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Initial member state'),
                                             3=>array('Value'=>8,'Name'=>'Group A','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group A member'),
                                             4=>array('Value'=>16,'Name'=>'Group B','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group B member'),
                                             5=>array('Value'=>32,'Name'=>'Group C','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group C member'),
                                             6=>array('Value'=>64,'Name'=>'Group D','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group D member'),
                                             7=>array('Value'=>128,'Name'=>'Group E','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group E member'),
                                             8=>array('Value'=>256,'Name'=>'Group F','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group F member'),
                                             9=>array('Value'=>512,'Name'=>'Group G','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group G member'),
                                             10=>array('Value'=>1024,'Name'=>'Sentinel','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Sentinel member'),
                                             11=>array('Value'=>2048,'Name'=>'Group I','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group I member'),
                                             12=>array('Value'=>4096,'Name'=>'Group J','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group J member'),
                                             13=>array('Value'=>8192,'Name'=>'Group K','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Group K member'),
                                             14=>array('Value'=>16384,'Name'=>'Config admin','isAdmin'=>FALSE,'isPublic'=>FALSE,'Description'=>'Configuration admin'),
                                             15=>array('Value'=>32768,'Name'=>'Admin','isAdmin'=>TRUE,'isPublic'=>FALSE,'Description'=>'Administrator')
                                             ),
                            'Type'=>'array',
                            'Read'=>'ALL_R',
                            'Write'=>'ADMIN_R',
                            );
    
    public function __construct(array $oc)
    {
        $this->oc=$oc;
        $table=str_replace(__NAMESPACE__,'',__CLASS__);
        $this->entryTable=mb_strtolower(trim($table,'\\'));
    }
    
    public function init(array $oc)
    {
        $this->oc=$oc;
        $this->entryTemplate=$oc['SourcePot\Datapool\Foundation\Database']->getEntryTemplateCreateTable($this->entryTable,__CLASS__);
        $this->userRols();
        // check database user entry definition 
        $oc['SourcePot\Datapool\Foundation\Definitions']->addDefintion(__CLASS__,$this->definition);
        $currentUser=$this->getCurrentUser();
        $this->initAdminAccount();
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
            $options=array();
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
        $userRols=array();
        foreach($this->userRols['Content'] as $index=>$rolArrc){
            if ((intval($user['Privileges']) & $rolArrc['Value'])>0){$userRols[]=$rolArrc['Name'];}
        }
        return implode(', ',$userRols);
    }
    
    public function getCurrentUser():array
    {
        if (empty($_SESSION['currentUser']['EntryId']) || empty($_SESSION['currentUser']['Privileges']) || empty($_SESSION['currentUser']['Owner'])){
            $this->anonymousUserLogin();
        }
        return $_SESSION['currentUser'];
    }
    
    public function unifyEntry(array $entry):array
    {
        $entry['Source']=$this->entryTable;
        if (!isset($entry['Content']['Address'])){$entry['Content']['Address']=array();}
        if (empty($entry['Content']['Contact details']['Email']) && !empty($entry['Email'])){
            $entry['Content']['Contact details']['Email']=$entry['Email'];
        }
        if (empty($entry['Params']['User registration']['Email']) && !empty($entry['Content']['Contact details']['Email'])){
            $entry['Params']['User registration']['Email']=$entry['Content']['Contact details']['Email'];
        }
        $entry=$this->oc['SourcePot\Datapool\Foundation\Access']->addRights($entry,'ADMIN_R','ADMIN_R');
        $entry['Group']=$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('pageTitle');
        $entry['Folder']=$this->getUserRolsString($entry);
        $entry=$this->oc['SourcePot\Datapool\Tools\GeoTools']->address2location($entry);
        $entry=$this->oc['SourcePot\Datapool\Foundation\Definitions']->definition2entry($this->definition,$entry,FALSE);    
        $entry['Name']=$this->userAbstract(array('selector'=>$entry),3);
        return $entry;
    }
    
    private function anonymousUserLogin():array
    {
        $user=array('Source'=>$this->entryTable,'Type'=>'user');
        $user['Owner']='ANONYM';
        $user['LoginId']=mt_rand(1,10000000);
        $user['Expires']=date('Y-m-d H:i:s',time()+300);
        $user['Privileges']=1;
        $user=$this->unifyEntry($user);
        $user=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($user,TRUE);
        $this->loginUser($user);
        return $user;
    }
    
    private function initPsw(){
        return trim(base64_encode(random_bytes(16)),'=');
    }
    
    private function initAdminAccount():bool
    {
        $noAdminAccountFound=empty($this->oc['SourcePot\Datapool\Foundation\Database']->entriesByRight('Privileges','ADMIN_R',TRUE));
        if ($noAdminAccountFound){
            $admin=array('Source'=>$this->entryTable,'Privileges'=>'ADMIN_R',
                         'Email'=>$this->oc['SourcePot\Datapool\Foundation\Backbone']->getSettings('emailWebmaster'),
                         'Password'=>$this->initPsw(),
                         'Owner'=>'SYSTEM'
                         );
            $admin['EntryId']=$this->oc['SourcePot\Datapool\Foundation\Access']->emailId($admin['Email']);
            $admin['LoginId']=$this->oc['SourcePot\Datapool\Foundation\Access']->loginId($admin['Email'],$admin['Password']);
            $admin['Content']['Contact details']['First name']='Admin';
            $admin['Content']['Contact details']['Family name']='Admin';
            $admin=$this->oc['SourcePot\Datapool\Foundation\Database']->unifyEntry($admin);
            $success=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($admin,TRUE);
            if ($success){
                // Save init admin details
                $adminFile=array('Class'=>__CLASS__,'EntryId'=>__FUNCTION__);
                $adminFile['Content']['Admin email']=$admin['Email'];
                $adminFile['Content']['Admin password']=$admin['Password'];
                $access=$this->oc['SourcePot\Datapool\Foundation\Filespace']->updateEntry($adminFile,TRUE);
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
        $user=$this->unifyEntry($user);
        $this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($user,TRUE);
        $this->loginUser($user);
        return $user;
    }
    
    public function userAbstract(array|string $arr=array(),int $template=0):string
    {
        // This method returns formated html text from an entry based on predefined templates.
        //     
        if (empty($arr)){
            $user=$_SESSION['currentUser'];
        } else if (!is_array($arr)){
            $user=array('Source'=>$this->entryTable,'EntryId'=>$arr);
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
            $abtract='{{ICON}} [p:{{Content'.$S.'Contact details'.$S.'First name}} {{Content'.$S.'Contact details'.$S.'Family name}}]';
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
        }
        $user['ICON']=$this->oc['SourcePot\Datapool\Tools\MediaTools']->getIcon(array('selector'=>$user,'returnHtmlOnly'=>TRUE));
        $abtract=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->template2string($abtract,$user,array('class'=>'user-abstract'));
        if (!empty($arr['wrapResult'])){
            $wrapper=$arr['wrapResult'];
            $wrapper['element-content']=$abtract;
            $wrapper['keep-element-content']=TRUE;
            $abtract=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($wrapper);
        }
        return $abtract;
    }
    
    public function ownerAbstract(array $arr):string
    {
        $template=(isset($arr['selector']['template']))?$arr['selector']['template']:2;
        $html=$this->userAbstract($arr['selector']['Owner'],$template);
        $arr['tag']='div';
        $arr['element-content']=$html;
        $arr['keep-element-content']=TRUE;
        $html=$this->oc['SourcePot\Datapool\Tools\HTMLbuilder']->element($arr);
        return $html;
    }
    
    public function userAccountForm(array $arr):array
    {
        $template=array('html'=>'');
        $arr=array_merge($template,$arr);
        if (isset($arr['selector']['EntryId'])){
            if (!isset($arr['selector']['Type'])){$arr['selector']['Type']='user';}
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
        if (strcmp($user['Owner'],'ANONYM')!==0){
            $this->oc['logger']->log('info','User login {user}',array('user'=>$_SESSION['currentUser']['Name']));    
        }
    }
    
    public function getUserOptions(array $selector=array(),string $flatContactDetailsKey=''):array
    {
        $selector['Source']=$this->entryTable;
        $selector['Privileges>']=1;
        $options=array();
        foreach($this->oc['SourcePot\Datapool\Foundation\Database']->entryIterator($selector,TRUE,'Read') as $user){
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