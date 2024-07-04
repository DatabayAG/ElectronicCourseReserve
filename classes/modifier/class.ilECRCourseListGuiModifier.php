<?php

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveListGUIHelper.php";

/**
 * Class ilECRCourseListGuiModifier
 */
class ilECRCourseListGuiModifier implements ilECRBaseModifier
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
        if (
            $a_par['tpl_id'] != 'Services/Container/tpl.container_list_item.html' &&
            $a_par['tpl_id'] != 'Services/UIComponent/AdvancedSelectionList/tpl.adv_selection_list.html'
        ) {
            return false;
        }

        if($this->httpWrapper->query()->has("cmdClass")){
            $cmdClass = $this->httpWrapper->query()->retrieve("cmdClass", $this->refinery->kindlyTo()->string());
        } else {
            $cmdClass = "";
        }
        if (in_array(strtolower($cmdClass), array_map('strtolower', [ilObjectCopyGUI::class]), true)) {
            return false;
        }

        if(!$this->httpWrapper->query()->has('ref_id')){
            return false;
        }
        $refId = $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());
        if (!$refId) {
            return false;
        }

        $obj_id = $this->data_cache->lookupObjId($refId);
        $type = $this->data_cache->lookupType($obj_id);

        if ($type !== 'crs') {
            return false;
        }

        return true;
    }

    /**
     * @throws DOMException
     * @throws ilObjectNotFoundException
     * @throws ilDatabaseException
     */
    public function modifyHtml($a_comp, $a_part, $a_par): array
    {
        $processedHtml = '';
        $contextRefId = $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());;

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

            $linksWithRefIds = $xpath->query("//li/a[contains(@href, 'ref_id')]");
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
                    if (str_contains($action, 'cmd=' . $cmd)) {
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
