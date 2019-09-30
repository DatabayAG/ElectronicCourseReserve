<?php

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveListGUIHelper.php";

/**
 * Class ilECRCourseFolderTileGuiModifier
 */
class ilECRCourseFolderTileGuiModifier implements ilECRBaseModifier
{
    /**
     * @var ilElectronicCourseReserveListGUIHelper
     */
    protected $list_gui_helper;

    /**
     * @var ilObjDataCache
     */
    protected $data_cache;

    /**
     * @var ilAccessHandler
     */
    protected $access;

    public function __construct()
    {
        global $DIC;
        $this->access = $DIC->access();
        $this->data_cache = $DIC['ilObjDataCache'];
        $this->list_gui_helper = new ilElectronicCourseReserveListGUIHelper();
    }

    public function shouldModifyHtml($a_comp, $a_part, $a_par)
    {
        if ($a_par['tpl_id'] != 'src/UI/templates/default/Deck/tpl.deck_card.html') {
            return false;
        }

        $refId = (int) $_GET['ref_id'];
        if (!$refId) {
            return false;
        }

        $obj_id = $this->data_cache->lookupObjId($refId);
        $type = $this->data_cache->lookupType($obj_id);

        if (in_array($type, ['crs', 'fold'])) {
            return true;
        }

        return false;
    }

    public function modifyHtml($a_comp, $a_part, $a_par)
    {
        $contextRefId = (int) $_GET['ref_id'];

        $obj = ilObjectFactory::getInstanceByRefId($contextRefId, false);
        if ((!($obj instanceof ilObjCourse) && !($obj instanceof ilObjFolder)) || !$this->access->checkAccess('read', '', $obj->getRefId())) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $html = $a_par['html'];

        $dom = new DOMDocument("1.0", "utf-8");
        if (!@$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>')) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }
        $dom->encoding = 'UTF-8';

        $xpath = new DomXPath($dom);

        $plugin = ilElectronicCourseReservePlugin::getInstance();

        $elements = [];

        if ($obj instanceof ilObjCourse) {
            $itemData = $plugin->getRelevantCourseAndFolderData($obj->getRefId());

            if (count($itemData) > 0) {
                $linkedTitleNodeList = $xpath->query("//div[@class='il-card thumbnail']/a");
                if ($linkedTitleNodeList->length > 0) {
                    foreach ($linkedTitleNodeList as $linkedTitleNode) {
                        /** @var $linkedTitleNode DOMElement */
                        if ($linkedTitleNode->hasAttribute('href')) {
                            $action = $linkedTitleNode->getAttribute('href');
                            $matches = null;

                            if (preg_match('/item_ref_id=(\d+)/', $action, $matches)) {
                                if (!array_key_exists($matches[1], $itemData)) {
                                    continue;
                                }
                                $elements[] = $linkedTitleNode->parentNode->parentNode;
                            }

                            if (preg_match('/ref_id=(\d+)/', $action, $matches)) {
                                if (!array_key_exists($matches[1], $itemData)) {
                                    continue;
                                }
                                $elements[] = $linkedTitleNode->parentNode->parentNode;
                            }
                        }
                    }
                }
            }
        } else {
            $itemData = $plugin->getItemData();

            if (count($itemData) > 0) {
                $linkedTitleNodeList = $xpath->query("//div[@class='il-card thumbnail']/a");
                if ($linkedTitleNodeList->length > 0) {
                    foreach ($linkedTitleNodeList as $linkedTitleNode) {
                        /** @var $linkedTitleNode DOMElement */
                        if ($linkedTitleNode->hasAttribute('href')) {
                            $action = $linkedTitleNode->getAttribute('href');
                            $matches = null;
                            if (preg_match('/ref_id=(\d+)|_(\d+)/', $action, $matches)) {
                                if (
                                    !array_key_exists($matches[1], $itemData) &&
                                    !array_key_exists($matches[2], $itemData)
                                ) {
                                    continue;
                                }
                                $elements[] = $linkedTitleNode->parentNode->parentNode;
                            }
                        }
                    }
                }
            }
        }

        foreach ($elements as $element) {
            $nodeList = $xpath->query("//ul[@class='dropdown-menu']", $element);
            if ($nodeList->length > 0) {
                foreach ($nodeList as $node) {
                    foreach ($this->list_gui_helper->actions_to_remove as $key => $action) {
                        $nodesToDelete = $xpath->query(
                            "//button[contains(@data-action,'cmd=" . $action . "')]",
                            $node
                        );
                        $this->list_gui_helper->removeAction($nodesToDelete);
                    }
                }
            }
        }

        $processed_html = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));

        if (strlen($processed_html) === 0) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $processed_html];
    }
}
