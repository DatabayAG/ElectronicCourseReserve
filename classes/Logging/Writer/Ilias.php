<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging\Writer;

use ILIAS\Plugin\ElectronicCourseReserve\Logging;

/**
 * Class ILIAS
 * @author Michael Jansen <mjansen@databay.de>
 */
class ILIAS extends Base
{
    /** @var \ilLogger */
    protected $aggregated_logger;

    /** @var string */
    private $logLevel;

    /** @var Logging\TraceProcessor */
    protected $processor;

    /**
     * @var bool
     */
    protected $shutdown_handled = false;

    public function __construct(\ilLogger $log, $logLevel)
    {
        $this->aggregated_logger = $log;
        $this->logLevel = $logLevel;

        $this->processor = new Logging\TraceProcessor(\ilLogLevel::DEBUG);
    }

    /**
     * @param array $message
     * @return void
     */
    protected function doWrite(array $message)
    {
        $line = $message['message'];

        switch ($message['priority']) {
            case Logging\Logger::EMERG:
                $method = 'emergency';
                break;

            case Logging\Logger::ALERT:
                $method = 'alert';
                break;

            case Logging\Logger::CRIT:
                $method = 'critical';
                break;

            case Logging\Logger::ERR:
                $method = 'error';
                break;

            case Logging\Logger::WARN:
                $method = 'warning';
                break;

            case Logging\Logger::INFO:
                $method = 'info';
                break;

            case Logging\Logger::NOTICE:
                $method = 'notice';
                break;

            case Logging\Logger::DEBUG:
            default:
                $method = 'debug';
                break;
        }

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
    public function shutdown()
    {
        unset($this->aggregated_logger);

        $this->shutdown_handled = true;
    }
}
