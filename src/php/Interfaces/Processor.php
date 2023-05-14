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
     * Initializes the instance that implments the interface. The $arr-argument contains all references to instances of datappol classes, e.g.
	 * instances that provide database access, filespace access, html-templates etc. The $arr-argument is returned by the method.
	 * 
     */
    public function init(array $arr):array;
	
	public function getProcessorSettingsWidget(array $callingElement):string;
	
	public function getProcessorWidget(array $callingElement):string;
	
	public function run(array $callingElement, bool $isTestOnly):array;
	
}
?>
