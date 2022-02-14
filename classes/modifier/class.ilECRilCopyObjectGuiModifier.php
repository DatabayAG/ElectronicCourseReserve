<?php

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveListGUIHelper.php";

/**
 * Class ilECRCourseListGuiModifier
 */
class ilECRilCopyObjectGuiModifier implements ilECRBaseModifier
{
    /**
     * @var ilElectronicCourseReserveListGUIHelper
     */
    protected $list_gui_helper;

    public function __construct()
    {
        $this->list_gui_helper = new ilElectronicCourseReserveListGUIHelper();
    }

    public function shouldModifyHtml($a_comp, $a_part, $a_par)
    {
        $cmd_class = ilUtil::stripSlashes((string) ($_GET['cmdClass'] ?? ''));
        $cmd = ilUtil::stripSlashes((string) ($_GET['cmd'] ?? ''));

        $template = $a_par['tpl_id'] ?? '';
        if ($template !== 'Services/Table/tpl.table2.html') {
           return false;
        }

        if (strtolower($cmd_class) === 'ilobjectcopygui' && $cmd !== 'initTargetSelection') {
            return true;
        }

        return false;
    }

    public function modifyHtml($a_comp, $a_part, $a_par)
    {
        $processed_html = '';
        $html = $a_par['html'];
        $dom = new DOMDocument("1.0", "utf-8");
        if (!@$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>')) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }
        $dom->encoding = 'UTF-8';

        $plugin = ilElectronicCourseReservePlugin::getInstance();
        $xpath = new DomXPath($dom);
        $item_ref_ids = $plugin->getAllRefIds();
        foreach ($item_ref_ids as $key => $item_ref_id) {
            $this->replaceCheckbox($xpath, $item_ref_id, $dom);
            $this->removeRadioButton($xpath, $item_ref_id, $dom);
        }

        $processed_html = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));

        if (strlen($processed_html) === 0) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }
        return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $processed_html];
    }

    /**
     * @param DomXPath $xpath
     * @param int $item_ref_id
     * @param DOMDocument $dom
     */
    public function replaceCheckbox($xpath, $item_ref_id, $dom)
    {
        $node_list = $xpath->query("//li/input[contains(@value,'" . $item_ref_id . "')]");
        $placeholder_div = $dom->createElement('div');
        $placeholder_div->setAttribute('style', 'width:15px');
        for ($i = 0, $iMax = count($node_list); $i < $iMax; $i++) {
            $node = $node_list->item($i);
            if ($node !== null) {
                $node->parentNode->replaceChild($placeholder_div, $node);
            }
        }
    }

    /**
     * @param DomXPath $xpath
     * @param int $item_ref_id
     * @param DOMDocument $dom
     */
    public function removeRadioButton($xpath, $item_ref_id, $dom)
    {
        $node_list = $xpath->query('//input[contains(@name,"cp_options[' . $item_ref_id . '][type]")]');
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

        $node_list = $xpath->query('//input[contains(@id,"source_' . $item_ref_id . '")]');
        for ($i = 0, $iMax = count($node_list); $i < $iMax; $i++) {
            $node = $node_list->item($i);
            if ($node !== null) {
                $parent = $node->parentNode;
                $parent->removeChild($node);
            }
        }
    }
}
