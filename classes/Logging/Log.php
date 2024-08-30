<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging;

/**
 * Class Log
 * @package ILIAS\Plugin\ElectronicCourseReserve\Logging
 */
class Log implements Logger
{
    /**
     * @var self
     */
    protected static $instance;

    /**
     * @var Writer[]
     */
    protected $writer = array();

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

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        foreach ($this->writer as $writer) {
            $writer->shutdown();
        }
    }

    /**
     * Get singleton instance
     * @return self
     */
    public static function getInstance()
    {
        if (null !== self::$instance) {
            return self::$instance;
        }

        return (self::$instance = new self());
    }

    /**
     * @return array
     */
    public static function getPriorities()
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
    public function addWriter(Writer $writer, $priority = 1)
    {
        $this->writer[] = $writer;
    }

    /**
     * @param Writer $writer
     */
    public function removeWriter(Writer $writer)
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
     * @throws \ilException
     */
    public function log($priority, $message, $extra = array())
    {
        if (!is_int($priority) || ($priority < 0) || ($priority >= count(self::getPriorities()))) {
            throw new \ilException(sprintf('$priority must be an integer > 0 and < %d; received %s',
                count(self::getPriorities()),
                var_export($priority, 1)
            ));
        }

        if (is_object($message) && !method_exists($message, '__toString')) {
            throw new \ilException('$message must implement magic __toString() method');
        }

        if (is_array($message)) {
            $message = var_export($message, true);
        }

        $timestamp = new \ilDateTime(time(), IL_CAL_UNIX);

        $priorities = self::getPriorities();
        foreach ($this->writer as $writer) {
            $writer->write(array(
                'timestamp' => $timestamp,
                'priority' => (int) $priority,
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
     */
    public function emerg($message, $extra = array())
    {
        $this->log(self::EMERG, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function alert($message, $extra = array())
    {
        $this->log(self::ALERT, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function crit($message, $extra = array())
    {
        $this->log(self::CRIT, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function err($message, $extra = array())
    {
        $this->log(self::ERR, $message, $extra);
    }

    /**
     * @param string $message
     * @param array
     * @return void
     */
    public function info($message, $extra = array())
    {
        $this->log(self::INFO, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function warn($message, $extra = array())
    {
        $this->log(self::WARN, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function notice($message, $extra = array())
    {
        $this->log(self::NOTICE, $message, $extra);
    }

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function debug($message, $extra = array())
    {
        $this->log(self::DEBUG, $message, $extra);
    }
}