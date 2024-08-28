<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\ElectronicCourseReserve\Library\LinkBuilder;
use ILIAS\Plugin\ElectronicCourseReserve\Objects\Helper;
use ILIAS\Refinery\Factory;

class ilECRBibliographicItemModifier implements ilECRBaseModifier
{
    private WrapperFactory $httpWrapper;
    private Factory $refinery;

    public function __construct()
    {
        global $DIC;
        $this->httpWrapper = $DIC->http()->wrapper();
        $this->refinery = $DIC->refinery();
    }

    public function shouldModifyHtml($a_comp, $a_part, $a_par): bool
    {
        $refId = $this->httpWrapper->query()->retrieve(
            'ref_id',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(0)
            ])
        );
        if (!$refId) {
            return false;
        }

        if (!$this->isListView() && !$this->isDetailView()) {
            return false;
        }

        if ($this->isListView() && $a_par['tpl_id'] !== 'Services/Table/tpl.table2.html') {
            return false;
        }

        if ($this->isDetailView() && $a_par['tpl_id'] !== 'src/UI/templates/default/Panel/tpl.sub.html') {
            return false;
        }

        if (!ilElectronicCourseReservePlugin::getInstance()->getSetting('token_append_to_bibl')) {
            return false;
        }

        /** @var Helper $objectHelper */
        $objectHelper = $GLOBALS['DIC']['plugin.esa.object.helper'];
        $instance = $objectHelper->getInstanceByRefId($refId);
        if ($instance->getType() !== 'bibl') {
            return false;
        }

        return true;
    }

    protected function isDetailView(): bool
    {
        if (!$this->httpWrapper->query()->has('cmdClass') ||
            !$this->httpWrapper->query()->retrieve('cmdClass', $this->refinery->kindlyTo()->string())) {
            return false;
        }

        return (
            strtolower(
                $this->httpWrapper->query()->retrieve('cmdClass', $this->refinery->kindlyTo()->string())
            ) === strtolower(ilObjBibliographicGUI::class) &&
            strtolower(
                $this->httpWrapper->query()->retrieve('cmd', $this->refinery->kindlyTo()->string())
            ) === 'showdetails'
        );
    }

    protected function isListView(): bool
    {
        // TODO: Links like http://localhost.php8-1/ilias/9x/ilias.php?baseClass=ilrepositorygui&cmd=render&ref_id=148 should be considred as LIST VIEW context
        if (!$this->httpWrapper->query()->has('cmdClass') || !$this->httpWrapper->query()->has('cmd')) {
            return false;
        }

        $cmdClass = $this->httpWrapper->query()->retrieve('cmdClass', $this->refinery->kindlyTo()->string());
        $cmd = $this->httpWrapper->query()->retrieve('cmd', $this->refinery->kindlyTo()->string());

        if (!$cmdClass) {
            return false;
        }

        return (
            strtolower($cmdClass) === strtolower(ilObjBibliographicGUI::class) &&
            in_array(
                strtolower($cmd),
                ['showcontent', 'render', 'view']
            )
        ) || (
            strtolower($cmdClass) === strtolower(ilRepositoryGUI::class) &&
            strtolower($cmd) === 'render'
        ) || (
            strtolower($cmdClass) === 'ilbibliographicdetailsgui' &&
            strtolower($cmd) === 'showcontent'
        );
    }

    protected function manipulateListView(ilObjCourse $crs, array $a_par): array
    {
        /** @var list<ilBiblLibrary> $libs */
        $libs = ilBiblLibrary::get();
        $libsShownInList = array_filter($libs, static function (ilBiblLibrary $lib) {
            return $lib->isShownInList();
        });

        if (count($libsShownInList) === 0) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $dom = new DOMDocument("1.0", "utf-8");
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;
        if (!@$dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"/></head><body>' . $a_par['html'] . '</body></html>'
        )) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $table = $dom->getElementById(
            'tbl_bibl_overview_' . $this->httpWrapper->query()->retrieve(
                'ref_id',
                $this->refinery->kindlyTo()->int()
            )
        );
        if (!$table) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $xp = new DOMXPath($dom);
        $actionCells = $xp->query('tbody/tr/td[last()]', $table);
        if ($actionCells->length <= 0) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        /** @var LinkBuilder $linkBuilder */
        $linkBuilder = $GLOBALS['DIC']['plugin.esa.library.linkbuilder'];
        $linkParameters = $linkBuilder->getLibraryUrlParameters($crs);

        foreach ($actionCells as $actionCell) {
            /** @var DOMElement $actionCell */
            $bibButtons = $xp->query('button[@data-action]', $actionCell);
            if ($bibButtons->length < 1) {
                continue;
            }

            foreach ($bibButtons as $bibButton) {
                /** @var DOMElement $bibButton */
                $href = $bibButton->getAttribute('data-action');
                foreach ($linkParameters as $paramKey => $paramValue) {
                    $href = ilUtil::appendUrlParameterString($href, $paramKey . '=' . $paramValue);
                }
                $bibButton->setAttribute('data-action', $href);
            }
        }

        $processedHtml = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
        if (!$processedHtml) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        return [
            'mode' => ilUIHookPluginGUI::REPLACE,
            'html' => str_replace(['<body>', '</body>'], '', $processedHtml)
        ];
    }

    protected function manipulateDetailView(ilObjCourse $crs, array $a_par): array
    {
        $dom = new DOMDocument("1.0", "utf-8");
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;
        if (!@$dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"/></head><body>' . $a_par['html'] . '</body></html>'
        )) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $xp = new DOMXPath($dom);
        $bibButtons = $xp->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' il-listing-characteristic-value-item ')]//button[@data-action]");
        if ($bibButtons->length <= 0) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        /** @var LinkBuilder $linkBuilder */
        $linkBuilder = $GLOBALS['DIC']['plugin.esa.library.linkbuilder'];
        $linkParameters = $linkBuilder->getLibraryUrlParameters($crs);

        foreach ($bibButtons as $bibButton) {
            /** @var DOMElement $bibButton */
            $href = $bibButton->getAttribute('data-action');
            foreach ($linkParameters as $paramKey => $paramValue) {
                $href = ilUtil::appendUrlParameterString($href, $paramKey . '=' . $paramValue);
            }
            $bibButton->setAttribute('data-action', $href);
        }

        $processedHtml = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
        if (!$processedHtml) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        return [
            'mode' => ilUIHookPluginGUI::REPLACE,
            'html' => str_replace(['<body>', '</body>'], '', $processedHtml)
        ];
    }

    public function modifyHtml($a_comp, $a_part, $a_par): array
    {
        global $DIC;

        /** @var Helper $objectHelper */
        $objectHelper = $GLOBALS['DIC']['plugin.esa.object.helper'];

        $instance = $objectHelper->getInstanceByRefId(
            $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int())
        );
        $parentCrsRefId = $DIC->repositoryTree()->checkForParentType($instance->getRefId(), 'crs', true);
        if (!$parentCrsRefId) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $crs = $objectHelper->getInstanceByRefId($parentCrsRefId);
        if (!($crs instanceof ilObjCourse) ||
            !$DIC->access()->checkAccess('write', '', $crs->getRefId()) ||
            !ilElectronicCourseReservePlugin::getInstance()->isAssignedToRequiredRole($DIC->user()->getId())) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        if ($this->isListView()) {
            return $this->manipulateListView($crs, $a_par);
        } elseif ($this->isDetailView()) {
            return $this->manipulateDetailView($crs, $a_par);
        }

        return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
    }
}
