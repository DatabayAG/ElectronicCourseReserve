<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging;

/**
 * Class TraceProcessor
 * @package ILIAS\Plugin\ElectronicCourseReserve\Logging
 */
class TraceProcessor extends \ilTraceProcessor
{
    /**
     * @var int
     */
    private $level = 0;

    /**
     * ilElectronicCourseReserveLogTraceProcessor constructor.
     * @param int @a_level
     */
    public function __construct($a_level)
    {
        $this->level = $a_level;
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        if ($record['level'] < $this->level) {
            return $record;
        }

        $trace = debug_backtrace();

        // shift current method
        array_shift($trace);

        // shift plugin logger
        array_shift($trace);
        array_shift($trace);
        array_shift($trace);

        // shift internal Monolog calls
        array_shift($trace);
        array_shift($trace);
        array_shift($trace);
        array_shift($trace);

        $trace_info = $trace[1]['class'] . '::' . $trace[1]['function'] . ':' . $trace[0]['line'];

        if (isset($record['extra']) && is_array($record['extra'])) {
            $record['extra']['trace'] = $trace_info;
        }

        return $record;
    }
}