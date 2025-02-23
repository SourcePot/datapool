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

interface App{
    
    /**
    * Initializes the instance that implments the interface. The oc-argument is an array of objects, it contains all instantiated classes of datappol, e.g.
    * instances that provide database access, filespace access, html-templates etc.
    */
    
    /**
     * Adds the web-page content
     *
     * @param array|bool $arr     If TRUE, the method returns an array, e.g. ['Category'=>'Admin','Emoji'=>'&#9787;','Label'=>'Account','Read'=>'ALL_REGISTERED_R','Class'=>__CLASS__]
     *                            If type array, the method returns the web page content
     * @return array The App description or web page content.
     *
     */
    public function run(array|bool $arr=TRUE):array;
    
}
?>
