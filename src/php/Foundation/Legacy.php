<?php
/*
* This file is part of the Datapool CMS package.
* ANY METHODS DEALING WITH IMPORTING ENBTRIES AND WITH LEGACY ISSUES SHOULD BE PLACED HERE.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Foundation;

class Legacy{
    private $oc=[];
        
    public function __construct(array $oc)
    {
        $this->oc=$oc;
    }

    Public function loadOc(array $oc):void
    {
        $this->oc=$oc;
    }

    public function updateUser(array $user,#[\SensitiveParameter]string $password):array
    {
        if (empty($user)){
            return $user;
        } else if (hash_equals(\SourcePot\Datapool\Foundation\User::TARGET_USER_GROUP,$user['Group'])){
            // user is up-to-date
            $this->oc['logger']->log('debug','User account for "{Name}" is up-to-date.',$user);
            return $user;
        }
        // user needs update
        $legacyUserPass=$password.$user['EntryId'];
        if ($this->updatePassword($user,$legacyUserPass,$password)){
            $this->oc['logger']->log('info','Legacy user account update from "{Group}" to "'.\SourcePot\Datapool\Foundation\User::TARGET_USER_GROUP.'" for "{Name}".',$user);
        } else if ($this->updatePassword($user,$password,$password)){
            $this->oc['logger']->log('info','Legacy user account update from "{Group}" to "'.\SourcePot\Datapool\Foundation\User::TARGET_USER_GROUP.'" for "{Name}".',$user);
        } else {
            $this->oc['logger']->log('info','Legacy user account update for "{Name}" failed. Wrong password provided.',$user);
        }
        return $this->oc['SourcePot\Datapool\Foundation\Database']->entryById($user,TRUE);
    }

    private function updatePassword(array $user, string $oldPsw, string $newPsw):bool
    {
        if (password_verify($oldPsw,$user['LoginId'])===TRUE){
            // valid password provided
            $user['LoginId']=password_hash($newPsw,PASSWORD_DEFAULT);
            $user['Group']=\SourcePot\Datapool\Foundation\User::TARGET_USER_GROUP;
            $user=$this->oc['SourcePot\Datapool\Foundation\Database']->updateEntry($user,TRUE);
            return TRUE;
        } else {
            // invalid password provided
            return FALSE;
        }
    }
}
?>