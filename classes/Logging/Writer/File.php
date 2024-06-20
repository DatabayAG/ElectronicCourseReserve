<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging\Writer;

use ILIAS\Plugin\ElectronicCourseReserve\Logging;
use ilLogger as ilLoggerAlias;
use ilLoggerFactory;
use ilLogLevel;

/**
 * Class File
 * @author Michael Jansen <mjansen@databay.de>
 */
class File extends Base
{
    protected ilLoggerAlias $aggregated_logger;

    protected bool $shutdown_handled = false;

    /**
     * File constructor.
     * @param Logging\Settings $settings
     */
    public function __construct(Logging\Settings $settings)
    {
        $factory = ilLoggerFactory::newInstance($settings);
        $this->aggregated_logger = $factory->getComponentLogger('GfoUsrOuImport');
        $this->aggregated_logger->getLogger()->popProcessor();
        $this->aggregated_logger->getLogger()->pushProcessor(new Logging\TraceProcessor(ilLogLevel::DEBUG));
    }

    /**
     * @param array $message
     * @return void
     */
    protected function doWrite(array $message): void
    {
        $line = $message['message'];

        $method = match ($message['priority']) {
            Logging\Logger::EMERG => 'emergency',
            Logging\Logger::ALERT => 'alert',
            Logging\Logger::CRIT => 'critical',
            Logging\Logger::ERR => 'error',
            Logging\Logger::WARN => 'warning',
            Logging\Logger::INFO => 'info',
            Logging\Logger::NOTICE => 'notice',
            default => 'debug',
        };

        $this->aggregated_logger->{$method}($line);
    }

    /**
     * @return void
     */
    public function shutdown(): void
    {
        unset($this->aggregated_logger);

        $this->shutdown_handled = true;
    }
}