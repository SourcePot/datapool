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
    public function init(array $oc):array;
	
	public function run(array $arr):array;
	
}
?>
