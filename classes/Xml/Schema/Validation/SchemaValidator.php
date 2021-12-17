<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema\Validation;

use DOMDocument;
use Exception;
use ILIAS\Data\Factory as DataTypeFactory;
use ILIAS\Plugin\ElectronicCourseReserve\Xml\DomDocumentFactory;
use ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema\PathResolver as SchemaPathResolver;
use ILIAS\Plugin\VistisSoapApi\Xml\Exceptions\UnparseableXmlException;
use InvalidArgumentException;
use LibXMLError;
use RuntimeException;

/**
 * Class SchemaValidator
 * @package ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema\Validation
 * @author Michael Jansen <mjansen@databay.de>
 */
final class SchemaValidator
{
    /** @var int */
    private const DOM_MISSING_NS_CODE = 1845;

    /** @var DataTypeFactory */
    private $dataFactory;
    /** @var SchemaPathResolver */
    private $pathResolver;
    /** @var ErrorFormatter */
    private $errorFormatter;

    /** @var array This is an stack of error logs. The topmost element is the one we are currently working on. */
    private $errorStack = [];
    /** @var bool This is the xml error state we had before we began logging. */
    private $xmlErrorState;

    /**
     * SchemaValidator constructor.
     * @param DataTypeFactory $dataFactory
     * @param SchemaPathResolver $pathResolver
     * @param ErrorFormatter $errorFormatter
     */
    public function __construct(
        DataTypeFactory $dataFactory,
        SchemaPathResolver $pathResolver,
        ErrorFormatter $errorFormatter
    ) {
        $this->dataFactory = $dataFactory;
        $this->pathResolver = $pathResolver;
        $this->errorFormatter = $errorFormatter;
    }

    /**
     * Start error logging.
     * A call to this function will begin a new error logging context. Every call must have
     * a corresponding call to end().
     */
    private function beginLogging() : void
    {
        if (!function_exists('libxml_use_internal_errors')) {
            return;
        }

        if (0 === count($this->errorStack)) {
            // No error logging is currently in progress. Initialize it.
            $this->xmlErrorState = libxml_use_internal_errors(true);
            libxml_clear_errors();
        } else {
            /* We have already started error logging. Append the current errors to the
             * list of errors in this level.
             */
            $this->addErrors();
        }

        // Add a new level to the error stack
        $this->errorStack[] = [];
    }

    /**
     * Append current XML errors to the current stack level.
     */
    private function addErrors() : void
    {
        $currentErrors = libxml_get_errors();
        libxml_clear_errors();

        $level = count($this->errorStack) - 1;
        $this->errorStack[$level] = array_merge($this->errorStack[$level], $currentErrors);
    }

    /**
     * End error logging.
     * @return LibXMLError[] An array with the LibXMLErrors which has occurred since begin() was called.
     */
    private function endLogging() : array
    {
        // Check whether the error access functions are present
        if (!function_exists('libxml_use_internal_errors')) {
            // Pretend that no errors occurred
            return [];
        }

        // Add any errors which may have occurred
        $this->addErrors();

        $errors = array_pop($this->errorStack);

        if (0 === count($this->errorStack)) {
            // Disable our error logging and restore the previous state
            libxml_use_internal_errors($this->xmlErrorState);
        }

        return $errors;
    }

    /**
     * @param DOMDocument $dom
     * @param string $pathToSchema
     * @param bool $addedFallbackNamespace
     * @param string $fallbackNamespaceUri
     * @return ValidationResult
     */
    private function doValidate(
        DOMDocument $dom,
        string $pathToSchema,
        bool $addedFallbackNamespace = false,
        string $fallbackNamespaceUri = ''
    ) {
        $this->beginLogging();

        libxml_set_external_entity_loader(
            function ($public, $system, $context) {
                if (filter_var($system, FILTER_VALIDATE_URL) === $system) {
                    return null;
                }

                return $system;
            }
        );

        $isValidXml = $dom->schemaValidate($pathToSchema);
        if (true === $isValidXml) {
            $this->endLogging();

            require_once __DIR__ . '/ValidationResult.php';
            return new ValidationResult(
                $this->dataFactory->ok(true)
            );
        }

        $errors = $this->endLogging();

        foreach ($errors as $error) {
            if ($error->code === self::DOM_MISSING_NS_CODE && !$addedFallbackNamespace && $fallbackNamespaceUri !== '') {
                try {
                    $this->beginLogging();

                    // see: https://stackoverflow.com/questions/15188700/php-change-xmlns-in-xml-file#:~:text=As%20the%20xmlns%20attribute%20only,you%20can%20not%20change%20it.&text=As%20you%20can%20see%20DOMDocument,elements%20out%20of%20the%20box.
                    $xml = simplexml_load_string($dom->saveXML());
                    $xml->addAttribute('xmlns', $fallbackNamespaceUri);
                    $xml->saveXML();

                    require_once __DIR__ . '/../../DomDocumentFactory.php';
                    $documentFactory = new DomDocumentFactory();
                    $dom = $documentFactory->fromString($xml->asXML());

                    return $this->doValidate(
                        $dom,
                        $pathToSchema,
                        true,
                        $fallbackNamespaceUri
                    );
                } catch (Exception $e) {
                } finally {
                    $errors = $this->endLogging();
                }
                break;
            }
        }

        require_once __DIR__ . '/ValidationResult.php';
        return new ValidationResult(
            $this->dataFactory->error(implode("\n", [
                "Schema validation failed on XML string:",
                $this->errorFormatter->formatErrors($errors)
            ]))
        );
    }

    /**
     * @param string|DOMDocument $xml The XML string or document which should be validated.
     * @param string $schemaFile The filename of the schema that should be used to validate the document.
     * @param string $fallbackNamespaceUri A fallback namespace URI to be used if validation failed
     *      because of a missing namespace in the XML file, e.g. 'http://www.ilias.de/Modules/StudyProgramme/prg/5_1'
     * @return ValidationResult
     * @throws InvalidArgumentException
     */
    public function validate($xml, string $schemaFile, string $fallbackNamespaceUri = '') : ValidationResult
    {
        if (!is_string($xml) && !($xml instanceof DOMDocument)) {
            throw new InvalidArgumentException('Invalid XML input.');
        }

        if (!is_string($schemaFile)) {
            throw new InvalidArgumentException('Invalid path to XSD schema.');
        }

        $pathToSchema = $this->pathResolver->resolvePath($schemaFile);
        if (!is_file($pathToSchema) || !is_readable($pathToSchema)) {
            throw new InvalidArgumentException('Invalid path to XSD schema.');
        }

        $this->beginLogging();

        $isValidXml = true;
        $exceptionMessage = '';
        if ($xml instanceof DOMDocument) {
            $dom = $xml;
        } else {
            try {
                require_once __DIR__ . '/../../DomDocumentFactory.php';
                $documentFactory = new DomDocumentFactory();
                $dom = $documentFactory->fromString($xml);
            } catch (Exception $e) {
                $isValidXml = false;
                $exceptionMessage = $e->getMessage();
            }
        }

        if (false === $isValidXml) {
            $errors = $this->endLogging();
            if ($errors !== []) {
                $errorResult = $this->dataFactory->error(implode("\n", [
                    'Failed to parse XML string for schema validation:',
                    $this->errorFormatter->formatErrors($errors)
                ]));
            } else {
                $errorResult = $this->dataFactory->error(implode("\n", [
                    'Failed to parse XML string for schema validation:',
                    $exceptionMessage ? $exceptionMessage : 'Unparseable XML'
                ]));
            }

            require_once __DIR__ . '/ValidationResult.php';
            return new ValidationResult($errorResult);
        }

        $this->endLogging();

        return $this->doValidate(
            $dom,
            $pathToSchema,
            false,
            $fallbackNamespaceUri
        );
    }
}
