<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging;

/**
 * Class Logger
 * @author Michael Jansen <mjansen@databay.de>
 */
interface Logger
{
    /**
     * @const int defined from the BSD Syslog message severities
     * @link  http://tools.ietf.org/html/rfc3164
     */

    const EMERG = 0;
    const ALERT = 1;
    const CRIT = 2;
    const ERR = 3;
    const WARN = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function emerg(string $message, array $extra = array()): void;

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function alert(string $message, array $extra = array()): void;

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function crit(string $message, array $extra = array()): void;

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function err(string $message, array $extra = array()): void;

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function info(string $message, array $extra = array()): void;

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function warn(string $message, array $extra = array()): void;

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function notice(string $message, array $extra = array()): void;

    /**
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function debug(string $message,
                          array $extra = array()): void;
}
