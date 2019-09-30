<?php

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveListGUIHelper.php";

/**
 * Class ilECRCourseListGuiModifier
 */
class ilECRCourseListGuiModifier implements ilECRBaseModifier
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
        if (
            $a_par['tpl_id'] != 'Services/Container/tpl.container_list_item.html' &&
            $a_par['tpl_id'] != 'Services/UIComponent/AdvancedSelectionList/tpl.adv_selection_list.html'
        ) {
            return false;
        }

        $refId = (int) $_GET['ref_id'];
        if (!$refId) {
            return false;
        }

        $obj_id = $this->data_cache->lookupObjId($refId);
        $type = $this->data_cache->lookupType($obj_id);

        if ($type == 'crs') {
            return true;
        }

        return false;
    }

    public function modifyHtml($a_comp, $a_part, $a_par)
    {
        $processedHtml = '';
        $contextRefId = (int) $_GET['ref_id'];

        $obj = ilObjectFactory::getInstanceByRefId($contextRefId, false);
        if (!($obj instanceof ilObjCourse) || !$this->access->checkAccess('read', '', $obj->getRefId())) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $html = $a_par['html'];

        $dom = new DOMDocument("1.0", "utf-8");
        if (!@$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>')) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }
        $dom->encoding = 'UTF-8';

        $plugin = ilElectronicCourseReservePlugin::getInstance();
        $xpath = new DomXPath($dom);
        $itemData = $plugin->getRelevantCourseAndFolderData($obj->getRefId());

        if (count($itemData) > 0) {
            $elements = [];
            $refIds   = [];

            $linksWithRefIds = $xpath->query("//li/a[@href]");
            if ($linksWithRefIds->length > 0) {
                foreach ($linksWithRefIds as $linksWithRefId) {
                    $action  = $linksWithRefId->getAttribute('href');
                    $matches = null;

                    if (preg_match('/item_ref_id=(\d+)/', $action, $matches)) {
                        if (!array_key_exists($matches[1], $itemData)) {
                            continue;
                        }

                        $refIds[$matches[1]] = $matches[1];
                        $elements[]          = $linksWithRefId;
                        continue;
                    }

                    if (preg_match('/ref_id=(\d+)/', $action, $matches)) {
                        if (!array_key_exists($matches[1], $itemData)) {
                            continue;
                        }
                        $refIds[$matches[1]] = $matches[1];
                        $elements[]          = $linksWithRefId;
                    }
                }
            }
            
            $processed = false;

            foreach ($elements as $element) {
                $action = $element->getAttribute('href');
                foreach ($this->list_gui_helper->actions_to_remove as $key => $cmd) {
                    if (strpos($action, 'cmd=' . $cmd) !== false) {
                        $element->parentNode->removeChild($element);
                        $processed = true;
                    }
                }
            }

            foreach ($refIds as $refId) {
                $this->list_gui_helper->replaceCheckbox($xpath, $refId, $dom);
                $processed = true;
            }

            if ($processed) {
                $processedHtml = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
            }
        }

        if (strlen($processedHtml) === 0) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $processedHtml];
    }
}
