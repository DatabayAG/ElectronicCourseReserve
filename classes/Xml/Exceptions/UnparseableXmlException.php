<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Xml\Exceptions;

use LibXMLError;
use RuntimeException;

/**
 * Class UnparseableXmlException
 * @package ILIAS\Plugin\ElectronicCourseReserve\Xml\Exceptions
 * @author Michael Jansen <mjansen@databay.de>
 */
class UnparseableXmlException extends RuntimeException
{
    /** @var array<int, string> */
    private static $levelMap = [
        LIBXML_ERR_WARNING => 'WARNING',
        LIBXML_ERR_ERROR => 'ERROR',
        LIBXML_ERR_FATAL => 'FATAL'
    ];

    /**
     * UnparseableXmlException constructor.
     * @param LibXMLError $error
     */
    public function __construct(LibXMLError $error)
    {
        $message = sprintf(
            'Unable to parse XML - "%s[%d]": "%s" in "%s" at line %d on column %d"',
            self::$levelMap[$error->level],
            $error->code,
            $error->message,
            $error->file ?: '(string)',
            $error->line,
            $error->column
        );

        parent::__construct($message);
    }
}
