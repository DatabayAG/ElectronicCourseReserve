<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Xml;

use DOMDocument;
use ILIAS\Plugin\ElectronicCourseReserve\Xml\Exceptions\UnparseableXmlException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class DomDocumentFactory
 * @package ILIAS\Plugin\ElectronicCourseReserve\Xml
 * @author Michael Jansen <mjansen@databay.de>
 */
final class DomDocumentFactory
{
    /**
     * @param string $xml
     * @return DOMDocument
     * @throws InvalidArgumentException|RuntimeException|UnparseableXmlException
     */
    public function fromString(string $xml) : DOMDocument
    {
        if (trim($xml) === '') {
            throw new InvalidArgumentException('Invalid XML string given');
        }

        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $entityLoader = libxml_disable_entity_loader(true);
        }
        $internalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $domDocument = new DOMDocument();
        $options = LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_NONET;
        if (defined('LIBXML_COMPACT')) {
            $options |= LIBXML_COMPACT;
        }

        $loaded = $domDocument->loadXML($xml, $options);

        libxml_use_internal_errors($internalErrors);
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            libxml_disable_entity_loader($entityLoader);
        }

        if (!$loaded) {
            $error = libxml_get_last_error();
            libxml_clear_errors();

            require_once __DIR__ . '/Exceptions/UnparseableXmlException.php';
            throw new UnparseableXmlException($error);
        }

        libxml_clear_errors();

        foreach ($domDocument->childNodes as $child) {
            if ($child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                throw new RuntimeException(
                    'Dangerous XML detected, DOCTYPE nodes are not allowed in the XML body'
                );
            }
        }

        return $domDocument;
    }
}
