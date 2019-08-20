<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging\Writer;

use ILIAS\Plugin\ElectronicCourseReserve\Logging;

require_once 'Services/Calendar/classes/class.ilDateTime.php';

/**
 * Class File
 * @author Michael Jansen <mjansen@databay.de>
 */
class File extends Base
{
    /**
     * @var \ilLogger
     */
    protected $aggregated_logger;

    /**
     * @var bool
     */
    protected $shutdown_handled = false;

    /**
     * File constructor.
     * @param Logging\Settings $settings
     */
    public function __construct(Logging\Settings $settings)
    {
        $factory = \ilLoggerFactory::newInstance($settings);
        $this->aggregated_logger = $factory->getComponentLogger('GfoUsrOuImport');
        $this->aggregated_logger->getLogger()->popProcessor();
        $this->aggregated_logger->getLogger()->pushProcessor(new Logging\TraceProcessor(\ilLogLevel::DEBUG));
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

        $this->aggregated_logger->{$method}($line);
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