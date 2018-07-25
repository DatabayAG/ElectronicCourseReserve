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
		$ref_id = (int)$_GET['ref_id'];
		if ($a_par['tpl_id'] != 'Services/Container/tpl.container_list_item.html') {
			return false;
		}

		$obj_id = $this->data_cache->lookupObjId($ref_id);
		$type = $this->data_cache->lookupType($obj_id);

		if($type == 'crs') {
			return true;
		}
		return false;
	}

	public function modifyHtml($a_comp, $a_part, $a_par)
	{
		$processed_html = '';
		$ref_id         = (int)$_GET['ref_id'];
		$obj            = ilObjectFactory::getInstanceByRefId($ref_id, false);
		if (!($obj instanceof ilObjCourse) || !$this->access->checkAccess('read', '', $obj->getRefId())) {
			return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

		$html = $a_par['html'];

		$dom = new \DOMDocument("1.0", "utf-8");
		if (!@$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>')) {
			return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
		}
		$dom->encoding = 'UTF-8';

		$plugin      = ilElectronicCourseReservePlugin::getInstance();
		$xpath       = new DomXPath($dom);
		$item_data   = $plugin->getRelevantCourseAndFolderData($ref_id);
		$item_ref_id = $this->list_gui_helper->getRefIdFromItemUrl($xpath);
		if (count($item_data) > 0 && array_key_exists($item_ref_id, $item_data)) {
			foreach ($this->list_gui_helper->actions_to_remove as $key => $action) {
				$node_list = $xpath->query("//li/a[contains(@href,'cmd=" . $action . "')]");
				$this->list_gui_helper->removeAction($node_list);
			}

			$this->list_gui_helper->replaceCheckbox($xpath, $item_ref_id, $dom);
			$processed_html = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
		}
		if (strlen($processed_html) === 0) {
			return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
		}
		return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $processed_html];
	}
}
