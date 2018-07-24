<?php
include_once  'Services/Table/classes/class.ilTable2GUI.php';
require_once 'Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php';

/**
 * Class ilElectronicCourseReserveLangTableGUI
 */
class ilElectronicCourseReserveLangTableGUI extends ilTable2GUI
{
	/**
	 * @var array call parameters
	 */
	private $params = array();

	/**
	 * @inheritdoc
	 */
	public function __construct($a_parent_obj, $a_parent_cmd= "", $a_params = array())
	{
		global $ilCtrl, $lng;

		$this->params = $a_params;

		$this->setId('esa_crs_lang_adm');
		parent::__construct($a_parent_obj, $a_parent_cmd);

		$lng->loadLanguageModule('meta');
		$this->setTitle($a_parent_obj->pluginObj->txt('adm_ecr_tab_title'));
		$this->setRowTemplate($a_parent_obj->pluginObj->getDirectory().'/templates/tpl.lang_items_row.html');
		$this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
		$this->setDisableFilterHiding(true);

		$this->addColumn($lng->txt('language'), 'lang_key', '30%');
		$this->addColumn(ilElectronicCourseReservePlugin::getInstance()->txt('tab_translation_value'), 'value', '50%');
		$this->addColumn($lng->txt('actions'), '', '20%');
		$this->addCommandButton('saveEcrLangVars', $lng->txt('save'));
	}


	/**
	 * @inheritdoc
	 */
	protected function fillRow($data)
	{
		require_once 'Services/Form/classes/class.ilTextInputGUI.php';
		$field = new \ilTextInputGUI('', $data['lang_key']);
		$field->setValue($data['value']);

		$this->tpl->setVariable("LANG_KEY", ilUtil::prepareFormOutput($this->lng->txt('meta_l_'.$data["lang_key"])));
		$this->tpl->setVariable("TRANSLATION_FIELD", $field->getToolbarHTML());

		$actions = new ilAdvancedSelectionListGUI();
		$actions->setId('action' . $data['lang_key']);
		$actions->setListTitle($this->lng->txt('actions'));

		$this->ctrl->setParameter($this->parent_obj, 'ecr_lang', $data['lang_key']);
		$edit_url = $this->ctrl->getLinkTarget($this->parent_obj, 'editEcrContent');
		$actions->addItem($this->parent_obj->pluginObj->txt('edit_ecr_content'), '', $edit_url);
		$this->tpl->setVariable('ACTIONS', $actions->getHTML());
	}
}