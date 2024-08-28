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

class ilECRFolderListGuiModifier implements ilECRBaseModifier
{
    protected ilElectronicCourseReserveListGUIHelper $list_gui_helper;
    protected ilObjectDataCache $data_cache;
    protected ilAccessHandler $access;
    protected WrapperFactory $httpWrapper;
    protected Factory $refinery;

    public function __construct()
    {
        global $DIC;
        $this->access = $DIC->access();
        $this->data_cache = $DIC['ilObjDataCache'];

        $this->list_gui_helper = new ilElectronicCourseReserveListGUIHelper();
        $this->httpWrapper = $DIC->http()->wrapper();
        $this->refinery = $DIC->refinery();
    }

    public function shouldModifyHtml($a_comp, $a_part, $a_par): bool
    {
        if ($a_par['tpl_id'] !== 'Services/Container/tpl.container_list_item.html' &&
            $a_par['tpl_id'] !== 'Services/UIComponent/AdvancedSelectionList/tpl.adv_selection_list.html') {
            return false;
        }

        if (!$this->httpWrapper->query()->has('ref_id')) {
            return false;
        }
        $refId = $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());
        if (!$refId) {
            return false;
        }

        $obj_id = $this->data_cache->lookupObjId($refId);
        $type = $this->data_cache->lookupType($obj_id);

        if ($type !== 'fold') {
            return false;
        }

        return true;
    }

    public function modifyHtml($a_comp, $a_part, $a_par): array
    {
        $contextRefId = $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());

        $obj = ilObjectFactory::getInstanceByRefId($contextRefId, false);
        if (!($obj instanceof ilObjFolder) || !$this->access->checkAccess('read', '', $obj->getRefId())) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $html = $a_par['html'];
        $processedHtml = '';

        $dom = new DOMDocument('1.0', 'utf-8');
        if (!@$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>')) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }
        $dom->encoding = 'UTF-8';

        $plugin = ilElectronicCourseReservePlugin::getInstance();
        $xpath = new DomXPath($dom);
        $itemData = $plugin->getItemData();

        if ($a_par['tpl_id'] === 'Services/UIComponent/AdvancedSelectionList/tpl.adv_selection_list.html' &&
            count($itemData) > 0) {
            $linksWithRefIds = $xpath->query("//li/a[contains(@href, 'ref_id')]");
            if ($linksWithRefIds->length > 0) {
                $elements = [];

                foreach ($linksWithRefIds as $linksWithRefId) {
                    /** @var DOMElement $linksWithRefId */
                    $action = $linksWithRefId->getAttribute('href');
                    $matches = null;

                    if (preg_match('/item_ref_id=(\d+)/', $action, $matches)) {
                        if (!array_key_exists($matches[1], $itemData)) {
                            continue;
                        }

                        $elements[] = $linksWithRefId;
                        continue;
                    }

                    if (preg_match('/ref_id=(\d+)/', $action, $matches)) {
                        if (!array_key_exists($matches[1], $itemData)) {
                            continue;
                        }
                        $elements[] = $linksWithRefId;
                    }
                }

                $processed = false;

                foreach ($elements as $element) {
                    /** @var DOMElement $element */
                    $action = $element->getAttribute('href');
                    foreach ($this->list_gui_helper->actions_to_remove as $key => $cmd) {
                        if (str_contains($action, 'cmd=' . $cmd)) {
                            $element->parentNode->removeChild($element);
                            $processed = true;
                        }
                    }
                }

                if ($processed) {
                    $processedHtml = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
                }
            }
        } elseif (count($itemData) > 0) {
            $itemRefId = $this->list_gui_helper->getRefIdFromItemUrl($xpath);
            if (array_key_exists($itemRefId, $itemData)) {
                $text_string = $itemData[$itemRefId]['description'] ?? '';
                $image = $itemData[$itemRefId]['icon'] ?? '';
                $show_image = (int) $itemData[$itemRefId]['show_image'];
                $show_description = (int) $itemData[$itemRefId]['show_description'];

                if ($show_description === 1 && $text_string !== '') {
                    $text_node_list = $xpath->query("//div[@class='il_ContainerListItem']");
                    /** @var DOMElement $text_node */
                    $text_node = $text_node_list->item(0);
                    $field_html = $dom->createDocumentFragment();
                    $field_html->appendXML($text_string);
                    $field_div = $dom->createElement('div');
                    $field_div->appendChild($field_html);
                    $text_node->appendChild($field_div);
                }

                if ($show_image === 1 && $image !== '') {
                    $image_node_list = $xpath->query("//img[@class='ilListItemIcon']");
                    /** @var DOMElement $image_node */
                    $image_node = $image_node_list->item(0);
                    $plugin = ilElectronicCourseReservePlugin::getInstance();

                    if ($itemData[$itemRefId]['icon_type'] === $plugin::ICON_URL) {
                        $image_node->setAttribute('src', $image);
                    } elseif ($itemData[$itemRefId]['icon_type'] === $plugin::ICON_FILE) {
                        $path_to_image = ILIAS_WEB_DIR . DIRECTORY_SEPARATOR . CLIENT_ID . DIRECTORY_SEPARATOR . $image;

                        if (file_exists($path_to_image)) {
                            $image_node->setAttribute('src', $path_to_image);
                        }
                    }
                }
            }

            $this->list_gui_helper->replaceCheckbox($xpath, $itemRefId, $dom);

            $processedHtml = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
        }

        if ($processedHtml === '') {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $processedHtml];
    }
}
