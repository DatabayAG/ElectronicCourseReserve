<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema\Validation;

use ILIAS\Data\Result;

/**
 * Class ValidationResult
 * @package ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema\Validation
 * @author Michael Jansen <mjansen@databay.de>
 */
final class ValidationResult
{
    /** @var Result */
    private $result;

    /**
     * ValidationResult constructor.
     * @param Result $result
     */
    public function __construct(Result $result)
    {
        $this->result = $result;
    }

    /**
     * @return Result
     */
    public function result() : Result
    {
        return $this->result;
    }
}
