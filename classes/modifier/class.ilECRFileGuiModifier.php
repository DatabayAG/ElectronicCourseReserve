<?php

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveListGUIHelper.php";

/**
 * Class ilECRCourseListGuiModifier
 */
class ilECRFileGuiModifier implements ilECRBaseModifier
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

	/**
	 * @var bool 
	 */
	protected $modified = false;

	public function __construct()
	{
		global $DIC;
		$this->access = $DIC->access();
		$this->data_cache = $DIC['ilObjDataCache'];
		$this->list_gui_helper = new ilElectronicCourseReserveListGUIHelper();
	}

	/**
	 * @param $a_comp
	 * @param $a_part
	 * @param $a_par
	 * @return bool
	 */
	public function shouldModifyHtml($a_comp, $a_part, $a_par)
	{
		if($this->modified){
			return false;
		}

		$refId = (int)$_GET['ref_id'];
		if (!$refId) {
			return false;
		}

		$obj_id = $this->data_cache->lookupObjId($refId);
		$type = $this->data_cache->lookupType($obj_id);

		if($type == 'file' || $type == 'webr') {
			$this->modified = true;
			return true;
		}
		return false;
	}

	/**
	 * @param $a_comp
	 * @param $a_part
	 * @param $a_par
	 * @return string|void
	 */
	public function modifyHtml($a_comp, $a_part, $a_par)
	{
		global $DIC;
		$plugin      = ilElectronicCourseReservePlugin::getInstance();
		$refId       = (int)$_GET['ref_id'];
		$item_data   = $plugin->queryItemData($refId);
		if(is_array($item_data)
			&& array_key_exists('icon', $item_data)
			&& strlen($item_data['icon']) > 0
			&& array_key_exists('show_image', $item_data)
			&& $item_data['show_image'] == 1) {

			$replace = '#headerimage';
			if(array_key_exists('icon_type', $item_data) && $item_data['icon_type'] === 'url'){
				$with = $item_data['icon'];
			}
			else {
				$with = ILIAS_WEB_DIR . DIRECTORY_SEPARATOR . CLIENT_ID . DIRECTORY_SEPARATOR . $item_data['icon'];
			}

			$DIC->ui()->mainTemplate()->addJavaScript('Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/js/ElectronicCourseReserveObjectIcon.js');
			$DIC->ui()->mainTemplate()->addOnLoadCode('il.ElectronicCourseReserveObjectIcon.setConfig("'.$replace.'", "'.$with.'");');
		}
	}
}
