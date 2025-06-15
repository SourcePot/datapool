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

interface Job{
    
    /**
    * Initializes the instance that implments the interface. The oc-argument is an array of objects, it contains all instantiated classes of datappol, e.g.
    * instances that provide database access, filespace access, html-templates etc.
    */
    
    /**
    * Housekeeping method periodically executed by job.php (this script should be called once per minute through a CRON-job)
    * @param    string $vars Initial persistent data space
    * @return   array  Array Updateed persistent data space
    */
    public function job(array $vars):array;
    
}
?>
