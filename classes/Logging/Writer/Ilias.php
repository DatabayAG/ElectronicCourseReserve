<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging\Writer;

use ILIAS\Plugin\ElectronicCourseReserve\Logging;
use ilLogger;
use ilLogLevel;

/**
 * Class Ilias
 * @author Michael Jansen <mjansen@databay.de>
 */
class Ilias extends Base
{
    protected ilLogger $aggregated_logger;

    protected Logging\TraceProcessor $processor;

    protected bool $shutdown_handled = false;

    public function __construct(ilLogger $log, $logLevel)
    {
        $this->aggregated_logger = $log;

        $this->processor = new Logging\TraceProcessor(ilLogLevel::DEBUG);
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

        $poppedProcessors = [];
        while ($this->aggregated_logger->getLogger()->getProcessors() !== array()) {
            $processor = $this->aggregated_logger->getLogger()->popProcessor();
            $poppedProcessors[] = $processor;
        }
        $this->aggregated_logger->getLogger()->pushProcessor($this->processor);
        $this->aggregated_logger->{$method}($line);
        $this->aggregated_logger->getLogger()->popProcessor();
        foreach (array_reverse($poppedProcessors) as $processor) {
            $this->aggregated_logger->getLogger()->pushProcessor($processor);
        }
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
