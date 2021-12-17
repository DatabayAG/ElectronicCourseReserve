<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema\Validation;

use LibXMLError;

/**
 * Class ErrorFormatter
 * @package ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema\Validation
 * @author Michael Jansen <mjansen@databay.de>
 */
final class ErrorFormatter
{
    /**
     * Format an error as a string.
     * This function formats the given LibXMLError object as a string.
     * @param LibXMLError $error The LibXMLError which should be formatted.
     * @return string A string representing the given LibXMLError.
     */
    private function formatError(LibXMLError $error) : string
    {
        return implode(',', [
            'level=' . $error->level,
            'code=' . $error->code,
            'line=' . $error->line,
            'col=' . $error->column,
            'msg=' . trim($error->message)
        ]);
    }

    /**
     * Format a list of errors as a string.
     *
     * This function takes an array of LibXMLError objects and creates a string with all the errors.
     * Each error will be separated by a newline, and the string will end with a newline-character.
     *
     * @param LibXMLError[] $errors An array of errors.
     * @return string A string representing the errors. An empty string will be returned if there were no errors in the array.
     */
    public function formatErrors(array $errors) : string
    {
        $text = '';

        array_walk($errors, function (LibXMLError $error) : void {
        });

        foreach ($errors as $error) {
            $text .= $this->formatError($error) . "\n";
        }

        return $text;
    }
}
