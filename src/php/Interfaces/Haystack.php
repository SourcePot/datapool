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

interface Haystack{
    
    /**
    * This interface is used by classes providing functionality to Haystack
    */
    
    /**
    * Housekeeping method periodically executed by job.php (this script should be called once per minute through a CRON-job)
    * @param    string $query needle; must not be empty
    * @param    int $limit Maximum number of results to return; can be left empty, then a class specific default value will be used
    * @param    array $tags Is an array of relevant tags liimiting the result space, tage-format ['tag name'=>'Tag description']; can be left empty
    * @param    string $language Language limiting the result space, e.g. 'en' for English language results; can be left empty
    * @return   array  Array of relevant entries
    */
    public function query(string $query, int $limit, array $tags, string $language):array;
    
}
?>