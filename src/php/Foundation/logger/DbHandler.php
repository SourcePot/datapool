<?php

declare(strict_types=1);

namespace SourcePot\Datapool\Foundation\Logger;

use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * This class is a handler for Monolog to write logs as entries to the database
 *
 * Class MySQLHandler
 * @package wazaari\MysqlHandler
 */
class DbHandler extends AbstractProcessingHandler
{
    public function __construct(private array $oc,int $level=Logger::DEBUG,bool $bubble=TRUE)
    {
        parent::__construct($level,$bubble);
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     * @return void
     */
    protected function write(LogRecord $record):void
    {
        //var_dump($record);
        $this->oc['SourcePot\Datapool\Foundation\Logger']->addLog($record);
    }
}