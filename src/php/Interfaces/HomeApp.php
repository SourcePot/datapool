<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
declare(strict_types=1);

namespace SourcePot\Datapool\Interfaces;

interface HomeApp{
    
    /**
    * Initializes the instance that implments the HomeApp interface.
    * This interface is used by classes providing functionality to Home App
    */
    public function getHomeAppWidget():string;
    
    public function getHomeAppCaption():string;
    
    public function getHomeAppPriority():int;
    
}
?>