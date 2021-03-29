<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\Container;
use ILIAS\Plugin\ElectronicCourseReserve\HttpContext\HttpContext;

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/HttpContext/HttpContext.php";

/**
 * Class ViewModifier
 * @package ILIAS\Plugin\ElectronicCourseReserve\modifier
 * @author Michael Jansen <mjansen@databay.de>
 */
abstract class Base implements ilECRBaseModifier
{
    use HttpContext;

    /** @var Container */
    protected $dic;

    /**
     * ilServicePortalUserInterfaceUIHookGUI constructor.
     */
    public function __construct()
    {
        global $DIC;

        $this->dic = $DIC;
    }

    /**
     * @return array<string,string>
     */
    final protected function getUnmodifiedContent() : array
    {
        return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
    }

    /**
     * @param string $html
     * @param bool $wrapInDocument
     * @return DOMDocument
     */
    final protected function getDocumentForHtml(string $html, bool $wrapInDocument = true) : DOMDocument
    {
        $document = new DOMDocument('1.0', 'utf-8');
        $document->preserveWhiteSpace = true;
        $document->formatOutput = true;

        if ($wrapInDocument) {
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"/></head><body>' . $html . '</body></html>';
        }
        @$document->loadHTML($html);

        return $document;
    }

    /**
     * @param DOMDocument $document
     * @param bool $bodyOnly
     * @return array<string,string>
     */
    final protected function getDocumentContentAsUiHookOutput(DOMDocument $document, bool $bodyOnly = true) : array
    {
        if ($bodyOnly) {
            $processedHtml = $document->saveHTML($document->getElementsByTagName('body')->item(0));
        } else {
            $processedHtml = $document->saveHTML();
        }
        if (!$processedHtml) {
            return $this->getUnmodifiedContent();
        }

        return [
            'mode' => ilUIHookPluginGUI::REPLACE,
            'html' => $bodyOnly ? str_replace(['<body>', '</body>'], '', $processedHtml) : $processedHtml
        ];
    }
}
