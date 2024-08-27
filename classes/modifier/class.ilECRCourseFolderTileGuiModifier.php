<?php

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveListGUIHelper.php";

/**
 * Class ilECRCourseFolderTileGuiModifier
 */
class ilECRCourseFolderTileGuiModifier implements ilECRBaseModifier
{
    protected ilElectronicCourseReserveListGUIHelper $list_gui_helper;

    protected ilObjectDataCache $data_cache;

    protected ilAccessHandler $access;
    private WrapperFactory $httpWrapper;
    private Factory $refinery;

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
        if ($a_par['tpl_id'] != 'src/UI/templates/default/Deck/tpl.deck_card.html') {
            return false;
        }

        if(!$this->httpWrapper->query()->has('ref_id')) {
            return false;
        }

        $refId = $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());

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

    /**
     * @throws ilObjectNotFoundException
     * @throws ilDatabaseException
     */
    public function modifyHtml($a_comp, $a_part, $a_par): array
    {
        $contextRefId = $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());

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
                $linkedTitleNodeList = $xpath->query("//div[@class='il-card thumbnail']//a");
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

                            if ($this->isPluginItemDataRefId($action, $itemData)) {
                                $elements[] = $linkedTitleNode->parentNode->parentNode;
                            }
                        }
                    }
                }
            }
        } else {
            $itemData = $plugin->getItemData();

            if (count($itemData) > 0) {
                $linkedTitleNodeList = $xpath->query("//div[@class='il-card thumbnail']//a");
                if ($linkedTitleNodeList->length > 0) {
                    foreach ($linkedTitleNodeList as $linkedTitleNode) {
                        /** @var $linkedTitleNode DOMElement */
                        if ($linkedTitleNode->hasAttribute('href')) {
                            $action = $linkedTitleNode->getAttribute('href');
                            if ($this->isPluginItemDataRefId($action, $itemData)) {
                                $elements[] = $linkedTitleNode->parentNode->parentNode;
                            }
                        }
                    }
                }
            }

            $linkedTitleNodeList = $xpath->query("//div[@class='il-card thumbnail']//div[contains(concat(' ', normalize-space(@class), ' '), ' card-title ')]//span[@data-list-item-id]");
            if ($linkedTitleNodeList->length > 0) {
                foreach ($linkedTitleNodeList as $linkedTitleNode) {
                    /** @var $linkedTitleNode DOMElement */
                    $action = $linkedTitleNode->getAttribute('data-list-item-id');
                    $matches = null;
                    if (preg_match('/lg_div_(\d+)_/', $action, $matches)) {
                        if (!array_key_exists($matches[1], $itemData)) {
                            continue;
                        }
                        $elements[] = $linkedTitleNode->parentNode->parentNode->parentNode;
                    }
                }
            }
        }

        $processed = false;

        foreach ($elements as $element) {
            $nodeList = $xpath->query(".//ul[@class='dropdown-menu']", $element);
            if ($nodeList->length > 0) {
                foreach ($nodeList as $node) {
                    foreach ($this->list_gui_helper->actions_to_remove as $key => $action) {
                        $nodesToDelete = $xpath->query(
                            ".//button[contains(@data-action,'cmd=" . $action . "')]",
                            $node
                        );
                        $this->list_gui_helper->removeAction($nodesToDelete);
                        $processed = true;
                    }
                }
            }
        }

        if (!$processed) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $processedHtml = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
        if (strlen($processedHtml) === 0) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $processedHtml];
    }

    private function isPluginItemDataRefId(string $action, array $itemData): bool
    {
        if (!preg_match('/ref_id=(\d+)|_(\d+)|goto\.php\/\w{3,4}\/(\d+)|go\/\w{3,4}\/(\d+)/', $action, $matches)) {
            return false;
        }

        if (
            !array_key_exists($matches[1], $itemData) &&
            !array_key_exists($matches[2], $itemData) &&
            !array_key_exists($matches[3], $itemData) &&
            !array_key_exists($matches[4], $itemData)
        ) {
            return false;
        }

        return true;
    }
}
