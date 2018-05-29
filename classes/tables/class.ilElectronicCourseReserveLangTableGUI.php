<?php
include_once  'Services/Table/classes/class.ilTable2GUI.php';

/**
 * Class ilElectronicCourseReserveLangTableGUI
 */
class ilElectronicCourseReserveLangTableGUI extends ilTable2GUI
{
	/**
	 * Size of input fields
	 * @var  string
	 */
	private $inputsize = 40;

	/**
	 * @var array   call parameters
	 */
	private $params = array();

	function __construct($a_parent_obj, $a_parent_cmd= "", $a_params = array())
	{
		global $ilCtrl, $lng;

		$this->params = $a_params;

		parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->setRowTemplate($a_parent_obj->pluginObj->getDirectory().'/templates/tpl.lang_items_row.html');
		$this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
		$this->setDisableFilterHiding(true);

		$this->addColumn($lng->txt('language'), 'lang_key');
		$this->addColumn(ucfirst($lng->txt("identifier")),"identifier", "10em");
		$this->addColumn($lng->txt('value'),"value");
		$this->addCommandButton('saveEcrLangVars', $lng->txt('save'));
	}


	/**
	 * Fill a single data row.
	 */
	protected function fillRow($data)
	{
		$this->tpl->setVariable("T_SIZE", $this->inputsize);
		$this->tpl->setVariable("T_NAME", $data["lang_key"]);
		$this->tpl->setVariable("LANG_KEY", ilUtil::prepareFormOutput($data["lang_key"]));
		$this->tpl->setVariable("VALUE", ilUtil::prepareFormOutput($data["value"]));

		$this->tpl->setVariable("IDENTIFIER", ilUtil::prepareFormOutput($data["identifier"]));
	}
}