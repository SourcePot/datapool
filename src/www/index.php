<?php
/*
* This file is part of the Datapool CMS package.
* @package Datapool
* @author Carsten Wallenhauer <admin@datapool.info>
* @copyright 2023 to today Carsten Wallenhauer
* @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-v3
*/
	
declare(strict_types=1);
	
namespace SourcePot\Datapool;
	
mb_internal_encoding("UTF-8");

require_once('../php/Root.php');
$pageObj=new Root('index.php');
$arr=$pageObj->run();
echo $arr['page html'];
?>