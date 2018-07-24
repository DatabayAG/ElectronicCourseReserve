<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/UIComponent/classes/class.ilUIHookPluginGUI.php';
require_once 'Services/Mail/classes/class.ilMailbox.php';

/**
 * Class ilElectronicCourseReserveUIHookGUI
 * @author Nadia Matuschek <nmatuschek@databay.de>
 *
 * @ilCtrl_isCalledBy ilElectronicCourseReserveUIHookGUI: ilObjPluginDispatchGUI, ilRepositoryGUI, ilPersonalDesktopGUI
 * @ilCtrl_Calls ilElectronicCourseReserveUIHookGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_isCalledBy ilElectronicCourseReserveUIHookGUI: ilUIPluginRouterGUI
 */
class ilElectronicCourseReserveUIHookGUI extends ilUIHookPluginGUI
{
	/**
	 * @var \ilECRBaseModifier[]|null
	 */
	protected static $modifier = null;

	/**
	 * ilElectronicCourseReserveUIHookGUI constructor.
	 */
	public function __construct()
	{
		parent::getPluginObject();

		if (null === self::$modifier) {
			$this->initModifier();
		}
	}

	public function executeCommand()
	{
		global $DIC;

		$tpl = $DIC->ui()->mainTemplate();
		$ilCtrl = $DIC->ctrl();

		$tpl->getStandardTemplate();

		$ilCtrl->saveParameter($this, 'ref_id');
		$next_class = $ilCtrl->getNextClass();

		switch (strtolower($next_class)) {
			default:
				ilElectronicCourseReservePlugin::getInstance()->includeClass('dispatcher/class.ilECRCommandDispatcher.php');
				$dispatcher = ilECRCommandDispatcher::getInstance($this);
				$response = $dispatcher->dispatch($ilCtrl->getCmd());
				$tpl->setContent($response);
				$tpl->show();
				break;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getHTML($a_comp, $a_part, $a_par = array())
	{
		global $DIC;

		$ilUser = $DIC->user();
		$ilAccess = $DIC->access();

		if ($a_part != 'template_get') {
			return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

		/**
		 * @var $modifier \ilECRBaseModifier
		 */
		foreach (self::$modifier as $modifier) {
			if ($modifier->shouldModifyHtml($a_comp, $a_part, $a_par)) {
				return $modifier->modifyHtml($a_comp, $a_part, $a_par);
			}
		}

		if (!isset($_GET['pluginCmd']) || 'Services/PersonalDesktop' != $a_comp || !isset($_GET['ref_id'])) {
			return parent::getHTML($a_comp, $a_part, $a_par);
		}

		$plugin = ilElectronicCourseReservePlugin::getInstance();

		$ref_id = (int)$_GET['ref_id'];
		$obj = ilObjectFactory::getInstanceByRefId($ref_id, false);
		if (!($obj instanceof ilObjCourse) || !$ilAccess->checkAccess('write', '',
				$obj->getRefId()) || !$plugin->isAssignedToRequiredRole($ilUser->getId())) {
			return parent::getHTML($a_comp, $a_part, $a_par);
		}

		if ('center_column' == $a_part) {
			return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => '');
		} else {
			if (in_array($a_part, array('left_column', 'right_column'))) {
				return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => '');
			}
		}

		return parent::getHTML($a_comp, $a_part, $a_par);
	}

	/**
	 * @inheritdoc
	 */
	public function modifyGUI($a_comp, $a_part, $a_par = array())
	{
		global $DIC;

		if (!isset($_GET['pluginCmd']) && 'tabs' == $a_part && isset($_GET['ref_id'])) {
			$ilTabs = $DIC->tabs();
			$ilCtrl = $DIC->ctrl();
			$ilAccess = $DIC->access();
			$ilUser = $DIC->user();

			$this->getPluginObject()->loadLanguageModule();

			$ref_id = (int)$_GET['ref_id'];
			$obj = ilObjectFactory::getInstanceByRefId($ref_id, false);
			if ($obj instanceof ilObjCourse &&
				$ilAccess->checkAccess('read', '', $obj->getRefId()) && $this->getPluginObject()->isAssignedToRequiredRole($ilUser->getId()))
			{
				$ilCtrl->setParameterByClass(__CLASS__, 'ref_id', $obj->getRefId());
				$ilTabs->addTab(
					'ecr_tab_title',
					$this->getPluginObject()->ecr_txt('ecr_tab_title'),
					$ilCtrl->getLinkTargetByClass(['ilUIPluginRouterGUI', __CLASS__],
						'ilECRContentController.showECRContent')
				);
			}
		}
	}

	/**
	 * 
	 */
	protected function initModifier()
	{
		$this->plugin_object = ilElectronicCourseReservePlugin::getInstance();
		$this->plugin_object->includeClass('modifier/class.ilECRInfoScreenModifier.php');
		$this->plugin_object->includeClass('modifier/class.ilECRBibliographicItemModifier.php');

		self::$modifier = [
			new ilECRInfoScreenModifier(),
			new ilECRBibliographicItemModifier(),
		];
	}

	/**
	 * @return bool
	 */
	public function setCreationMode()
	{
		return false;
	}
}
