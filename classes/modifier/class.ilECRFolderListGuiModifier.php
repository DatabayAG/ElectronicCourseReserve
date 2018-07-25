<?php

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveListGUIHelper.php";

/**
 * Class ilECRFolderListGuiModifier
 */
class ilECRFolderListGuiModifier implements ilECRBaseModifier
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
		if ($a_par['tpl_id'] != 'Services/Container/tpl.container_list_item.html') {
			return false;
		}

		$refId = (int)$_GET['ref_id'];
		if (!$refId) {
			return false;
		}

		$obj_id = $this->data_cache->lookupObjId($refId);
		$type = $this->data_cache ->lookupType($obj_id);

		if ($type !== 'fold') {
			return false;
		}
		return true;
	}

	public function modifyHtml($a_comp, $a_part, $a_par)
	{
		$processed_html = '';
		$ref_id         = (int)$_GET['ref_id'];
		$obj            = ilObjectFactory::getInstanceByRefId($ref_id, false);
		if (!($obj instanceof ilObjFolder) || !$this->access->checkAccess('read', '', $obj->getRefId())) {
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
		$item_data   = $plugin->getItemData();
		$item_ref_id = $this->list_gui_helper->getRefIdFromItemUrl($xpath);
		if (array_key_exists($item_ref_id, $item_data)) {
			$text_string      = $item_data[$item_ref_id]['description'];
			$image            = $item_data[$item_ref_id]['icon'];
			$show_image       = (int)$item_data[$item_ref_id]['show_image'];
			$show_description = (int)$item_data[$item_ref_id]['show_description'];

			if ($show_description == 1 && strlen($text_string) > 0) {
				$text_node_list = $xpath->query("//div[@class='il_ContainerListItem']");
				$text_node      = $text_node_list->item(0);
				$field_html     = $dom->createDocumentFragment();
				$field_html->appendXML($text_string);
				$field_div = $dom->createElement('div');
				$field_div->appendChild($field_html);
				$text_node->appendChild($field_div);
			}
			if ($show_image == 1 && strlen($image) > 0) {
				$image_node_list = $xpath->query("//img[@class='ilListItemIcon']");
				$image_node      = $image_node_list->item(0);
				/** @var ilElectronicCourseReservePlugin $plugin */
				$plugin = ilPlugin::getPluginObject('Services', 'UIComponent', 'uihk', 'ElectronicCourseReserve');

				if ($item_data[$item_ref_id]['icon_type'] === $plugin::ICON_URL) {
					$image_node->setAttribute('src', $image);
				} elseif ($item_data[$item_ref_id]['icon_type'] === $plugin::ICON_FILE) {
					$path_to_image = ILIAS_WEB_DIR . DIRECTORY_SEPARATOR . CLIENT_ID . DIRECTORY_SEPARATOR . $image;

					if (file_exists($path_to_image)) {
						$image_node->setAttribute('src', $path_to_image);
					}
				}
			}
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
