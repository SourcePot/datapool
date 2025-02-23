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

interface Processor{
    
    /**
    * Initializes the instance that implments the interface. The $oc-argument contains instantiated classes of datappol, e.g.
    * instances that provide database access, filespace access, html-templates etc.
    */

    /**
     * Returns processor database table name.
     *
     * @return string The name of the database table used by the processor
     */
    public function getEntryTable();
    
    /**
     * Fetches a value from the cache.
     *
     * @param array $callingElementSelector     Is the selector of the "Canvas Element" currently selected by the data explorer.
     *
     * @return string|array|TRUE If $callingElementSelector is empty the method returns TRUE, an html-string or result array otherwise.
     *
     */
    public function dataProcessor(array $callingElementSelector,string $action='info');

}
?>
