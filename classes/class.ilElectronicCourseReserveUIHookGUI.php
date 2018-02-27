<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/UIComponent/classes/class.ilUIHookPluginGUI.php';
require_once 'Services/Mail/classes/class.ilMailbox.php';

/**
 * Class ilElectronicCourseReserveUIHookGUI
 * @auhtor Nadia Matuschek <nmatuschek@databay.de>
 * 
 * @ilCtrl_isCalledBy ilElectronicCourseReserveUIHookGUI: ilObjPluginDispatchGUI, ilRepositoryGUI, ilPersonalDesktopGUI 
 * @ilCtrl_Calls ilElectronicCourseReserveUIHookGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_isCalledBy ilElectronicCourseReserveUIHookGUI: ilUIPluginRouterGUI  
 */
class ilElectronicCourseReserveUIHookGUI extends ilUIHookPluginGUI
{
	/**
	 * @var array
	 */
	protected static $modifier_cache = array();
	
	public function __construct()
	{
		parent::getPluginObject();
		if(!self::$modifier_cache || count(self::$modifier_cache) == 0)
		{
			$this->initModifier();
		}
	}
	
	public function executeCommand()
	{
		global $tpl, $ilCtrl;
		
		$tpl->getStandardTemplate();

		$ilCtrl->saveParameter($this, 'ref_id');
		$next_class = $ilCtrl->getNextClass();
		
		switch(strtolower($next_class))
		{
			default:
				$response = '';
				ilElectronicCourseReservePlugin::getInstance()->includeClass('dispatcher/class.ilECRCommandDispatcher.php');
				$dispatcher = ilECRCommandDispatcher::getInstance($this);
				$response   = $dispatcher->dispatch($ilCtrl->getCmd());
				$tpl->setContent($response);
				$tpl->show();
				break;
		}
	}
	
	/**
	 * @param       $a_comp
	 * @param       $a_part
	 * @param array $a_par
	 * @return array
	 */
	public function getHTML($a_comp, $a_part, $a_par = array())
	{
		/**
		 * @var $ilTabs    ilTabsGUI
		 * @var $tpl       ilTemplate
		 * @var $lng       ilLanguage
		 * @var $ilCtrl    ilCtrl
		 * @var $ilUser    ilObjUser
		 * @var $ilCtrl    ilCtrl
		 * @var $ilAccess  ilAccessHandler
		 * @var $ilLog     ilLog
		 * @var $ilSetting ilSetting
		 */
		global $ilTabs, $tpl, $lng, $ilCtrl, $ilUser, $ilAccess, $ilLog, $ilSetting;
		
		if($a_part != 'template_get')
		{
			return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
		}
		
		/**
		 * @var $case ilECRBaseModifier
		 */
		foreach(self::$modifier_cache as $modifier)
		{
			if($modifier->shouldModifyHtml($a_comp, $a_part, $a_par))
			{
				return  $modifier->modifyHtml($a_comp, $a_part, $a_par);
			}
		}
		if(!isset($_GET['pluginCmd']) || 'Services/PersonalDesktop' != $a_comp || !isset($_GET['ref_id']))
		{
			return parent::getHTML($a_comp, $a_part, $a_par);
		}
		$plugin = ilElectronicCourseReservePlugin::getInstance();

		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		if(!($obj instanceof ilObjCourse) || !$ilAccess->checkAccess('write', '', $obj->getRefId()) || !$plugin->isAssignedToRequiredRole($ilUser->getId()))
		{
			return parent::getHTML($a_comp, $a_part, $a_par);
		}
		if('center_column' == $a_part )
		{
			return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => '' );
		}
		else if(in_array($a_part, array('left_column', 'right_column')))
		{
			return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => '');
		}

		return parent::getHTML($a_comp, $a_part, $a_par);
	}
	
	/**
	 * @param       $a_comp
	 * @param       $a_part
	 * @param array $a_par
	 */
	public function modifyGUI($a_comp, $a_part, $a_par = array())
	{
		if(!isset($_GET['pluginCmd']) && 'tabs' == $a_part && isset($_GET['ref_id']))
		{
			/**
			 * @var $ilTabs   ilTabsGUI
			 * @var $ilCtrl   ilCtrl
			 * @var $ilAccess ilAccessHandler
			 * @var $ilUser   ilObjUser
			 */
			global $ilTabs, $ilCtrl, $ilAccess, $ilUser;

			$this->getPluginObject()->loadLanguageModule();

			$ref_id = (int)$_GET['ref_id'];
			$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
			if($obj instanceof ilObjCourse && $ilAccess->checkAccess('write', '', $obj->getRefId()) && $this->getPluginObject()->isAssignedToRequiredRole($ilUser->getId()))
			{
				$ilCtrl->setParameter($this, 'pluginCmd', 'pluginCmd');
				$ilTabs->addTab('ecr_tab_title',$this->getPluginObject()->txt('ecr_tab_title'), $ilCtrl->getLinkTarget($this, 'ilECRContentController.showECRContent'));
			}
		}
	}
	protected function initModifier()
	{
		$this->plugin_object = ilElectronicCourseReservePlugin::getInstance();
		$this->plugin_object->includeClass("modifier/class.ilECRInfoScreenModifier.php");
		
		self::$modifier_cache = array(
			new ilECRInfoScreenModifier()
		);
	}
	
	/**
	 * @return bool
	 */
	public function setCreationMode()
	{
		return false;
	}
}
