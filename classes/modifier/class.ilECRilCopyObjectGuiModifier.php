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
use ILIAS\Refinery\Factory;

class ilECRilCopyObjectGuiModifier implements ilECRBaseModifier
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
        $cmd_class = strtolower($this->httpWrapper->query()->retrieve(
            'cmdClass',
            $this->refinery->byTrying([$this->refinery->kindlyTo()->string(), $this->refinery->always('')])
        ));
        $cmd = strtolower($this->httpWrapper->query()->retrieve(
            'cmd',
            $this->refinery->byTrying([$this->refinery->kindlyTo()->string(), $this->refinery->always('')])
        ));

        $template = $a_par['tpl_id'] ?? '';
        if ($template !== 'Services/Table/tpl.table2.html') {
            return false;
        }

        if ($cmd !== 'inittargetselection' && $cmd_class === strtolower(ilObjectCopyGUI::class)) {
            return true;
        }

        return false;
    }

    public function modifyHtml($a_comp, $a_part, $a_par): array
    {
        $html = $a_par['html'];
        $dom = new DOMDocument('1.0', 'utf-8');
        if (!@$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>')) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }
        $dom->encoding = 'UTF-8';

        $plugin = ilElectronicCourseReservePlugin::getInstance();
        $xpath = new DomXPath($dom);
        $item_ref_ids = $plugin->getAllRefIds();
        foreach ($item_ref_ids as $item_ref_id) {
            $this->replaceCheckbox($xpath, $item_ref_id, $dom);
            $this->removeRadioButton($xpath, $item_ref_id, $dom);
        }

        $processed_html = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));

        if ($processed_html === '') {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $processed_html];
    }

    private function replaceCheckbox(DomXPath $xpath, int $item_ref_id, DOMDocument $dom): void
    {
        $node_list = $xpath->query("//li/input[contains(@value, '" . $item_ref_id . "')]");
        $placeholder_div = $dom->createElement('div');
        $placeholder_div->setAttribute('style', 'width:15px');
        for ($i = 0, $iMax = count($node_list); $i < $iMax; $i++) {
            $node = $node_list->item($i);
            $node?->parentNode->replaceChild($placeholder_div, $node);
        }
    }

    private function removeRadioButton(DomXPath $xpath, int $item_ref_id, DOMDocument $dom): void
    {
        $node_list = $xpath->query('//input[contains(@name, "cp_options[' . $item_ref_id . '][type]")]');
        for ($i = 0, $iMax = count($node_list); $i < $iMax; $i++) {
            $node = $node_list->item($i);
            if ($node !== null) {
                $parent = $node->parentNode;
                $nodesToDelete = [];
                foreach ($parent->childNodes as $childNode) {
                    $nodesToDelete[] = $childNode;
                }

                foreach ($nodesToDelete as $childNode) {
                    $parent->removeChild($childNode);
                }
            }
        }

        $node_list = $xpath->query('//input[contains(@id, "source_' . $item_ref_id . '")]');
        for ($i = 0, $iMax = count($node_list); $i < $iMax; $i++) {
            $node = $node_list->item($i);
            if ($node !== null) {
                $parent = $node->parentNode;
                $parent->removeChild($node);
            }
        }
    }
}
