<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging;

use ilDateTime;
use ilException;

/**
 * Class Log
 * @package ILIAS\Plugin\ElectronicCourseReserve\Logging
 */
class Log implements Logger
{
    protected static Log $instance;

    /**
     * @var Writer[]
     */
    protected array $writer = array();

    /**
     *
     */
    public function __construct()
    {
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->shutdown();
    }

    public function shutdown(): void
    {
        foreach ($this->writer as $writer) {
            $writer->shutdown();
        }
    }

    /**
     * Get singleton instance
     * @return self
     */
    public static function getInstance(): Log
    {
        if (null !== self::$instance) {
            return self::$instance;
        }

        return (self::$instance = new self());
    }

    /**
     * @return array
     */
    public static function getPriorities(): array
    {
        return array(
            self::EMERG => 'EMERG',
            self::ALERT => 'ALERT',
            self::CRIT => 'CRIT',
            self::ERR => 'ERR',
            self::WARN => 'WARN',
            self::NOTICE => 'NOTICE',
            self::INFO => 'INFO',
            self::DEBUG => 'DEBUG',
        );
    }

    /**
     * @param Writer $writer
     * @param int $priority
     */
    public function addWriter(Writer $writer, int $priority = 1): void
    {
        $this->writer[] = $writer;
    }

    /**
     * @param Writer $writer
     */
    public function removeWriter(Writer $writer): void
    {
        $key = array_search($writer, $this->writer);
        if ($key !== false) {
            unset($this->writer[$key]);
        }
    }

    /**
     * @param int $priority
     * @param mixed $message
     * @param array $extra
     * @throws ilException
     */
    public function log(int $priority, mixed $message, array $extra = array()): void
    {
        if (($priority < 0) || ($priority >= count(self::getPriorities()))) {
            throw new ilException(sprintf('$priority must be an integer > 0 and < %d; received %s',
                count(self::getPriorities()),
                var_export($priority, 1)
            ));
        }

        if (is_object($message) && !method_exists($message, '__toString')) {
            throw new ilException('$message must implement magic __toString() method');
        }

        if (is_array($message)) {
            $message = var_export($message, true);
        }

        $timestamp = new ilDateTime(time(), IL_CAL_UNIX);

        $priorities = self::getPriorities();
        foreach ($this->writer as $writer) {
            $writer->write(array(
                'timestamp' => $timestamp,
                'priority' => $priority,
                'priorityName' => $priorities[$priority],
                'message' => (string) $message,
                'extra' => $extra
            ));
        }
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     * @throws ilException
     */
    public function emerg(string $message, array $extra = array()): void
    {
        $this->log(self::EMERG, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     * @throws ilException
     */
    public function alert(string $message, array $extra = array()): void
    {
        $this->log(self::ALERT, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     * @throws ilException
     */
    public function crit(string $message, array $extra = array()): void
    {
        $this->log(self::CRIT, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     * @throws ilException
     */
    public function err(string $message, array $extra = array()): void
    {
        $this->log(self::ERR, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     * @throws ilException
     */
    public function info(string $message, array $extra = array()): void
    {
        $this->log(self::INFO, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     * @throws ilException
     */
    public function warn(string $message, array $extra = array()): void
    {
        $this->log(self::WARN, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     * @throws ilException
     */
    public function notice(string $message, array $extra = array()): void
    {
        $this->log(self::NOTICE, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     * @throws ilException
     */
    public function debug(string $message, array $extra = array()): void
    {
        $this->log(self::DEBUG, $message, $extra);
    }
}