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

/**
 * Receiver interface.
 * Sevice classes for datapool such as email-sender or SMS-sender should implement this interface.
 *
 * @author Carsten Wallenhauer <admin@datapool.info>
 */
interface Sender{
	
	/**
     * Initializes the instance that implments the interface. The $arr-argument contains all references to instances of datappol classes, e.g.
	 * instances that provide database access, filespace access, html-templates etc. The $arr-argument is returned by the method.
	 * 
     */
    public function init(array $arr):array;
	
	public function getSenderSettingsWidget(array $arr):string;
	
	public function getSenderWidget(array $arr):string;
	
	public function getSenderSettings(array $arr):array;

	public function getSenderSelector(array $arr):array;

	public function getSenderMeta(array $arr):array;

	public function send(array $arr):bool;
	
}
?>
