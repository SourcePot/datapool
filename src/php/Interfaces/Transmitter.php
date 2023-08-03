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

interface Transmitter{
    
    /**
    * Initializes the instance that implments the Transmitter interface.
    * This interface is used by classes providing functionality to send entries to users by e.g. email, sms, ...
    */
    public function send(string $recipient,array $entry):int;
    
    public function transmitterPluginHtml(array $arr):string;
    
    public function getRelevantFlatUserContentKey():string;
    
}
?>
