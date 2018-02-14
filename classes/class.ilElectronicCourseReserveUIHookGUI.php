<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/UIComponent/classes/class.ilUIHookPluginGUI.php';
require_once 'Services/Mail/classes/class.ilMailbox.php';

class ilElectronicCourseReserveUIHookGUI extends ilUIHookPluginGUI
{
	public function __construct()
	{
	}

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

		if('center_column' == $a_part)
		{
			$tpl->setTitle($obj->getTitle());
			$tpl->setTitleIcon(ilUtil::getImagePath('icon_crs.svg'));

			$ilCtrl->setParameterByClass('ilRepositoryGUI', 'ref_id', $obj->getRefId());
			$ilTabs->setBackTarget($lng->txt('back'), $ilCtrl->getLinkTargetByClass('ilRepositoryGUI'));

			if('showLink' == $_GET['pluginCmd'])
			{
				require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
				$form = new ilPropertyFormGUI();
				$ilCtrl->setParameterByClass('ilPersonalDesktopGUI', 'ref_id', $obj->getRefId());
				$ilCtrl->setParameterByClass('ilPersonalDesktopGUI', 'pluginCmd', 'performRedirect');
				$form->setFormAction($ilCtrl->getFormActionByClass('ilPersonalDesktopGUI'));
				$form->setTitle($this->getPluginObject()->txt('ecr_title'));

				$link = new ilNonEditableValueGUI('', 'ecr', true);
				$link->setValue('<a href="' . $ilCtrl->getLinkTargetByClass('ilPersonalDesktopGUI', '') . '" target="_blank">' . $plugin->getSetting('url_search_system') . '</a>');
				$link->setInfo($this->getPluginObject()->txt('ecr_desc'));
				$form->addItem($link);

				return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => $form->getHTML());
			}
			else if('performRedirect' == $_GET['pluginCmd'])
			{
				try
				{
					$url = $plugin->getLibraryOrderLink($obj);
					ilUtil::redirect($url);
				}
				catch(Exception $e)
				{
					if(defined('DEVMODE') && DEVMODE)
					{
						ilUtil::sendFailure($e->getMessage());
					}
					else
					{
						$ilLog->write($e->getMessage());
						ilUtil::sendFailure($plugin->txt('ecr_sign_error_occured'));
					}
					return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => '');
				}
			}
		}
		else if(in_array($a_part, array('left_column', 'right_column')))
		{
			return array('mode' => ilUIHookPluginGUI::REPLACE, 'html' => '');
		}

		return parent::getHTML($a_comp, $a_part, $a_par);
	}

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
				$ilCtrl->setParameterByClass('ilPersonalDesktopGUI', 'ref_id', $obj->getRefId());
				$ilCtrl->setParameterByClass('ilPersonalDesktopGUI', 'pluginCmd', 'showLink');
				$ilTabs->addTarget($this->getPluginObject()->txt('ecr_tab_title'), $ilCtrl->getLinkTargetByClass('ilPersonalDesktopGUI', ''), 'showElectronicCourseReserve', '', '', false, true);
				$ilCtrl->setParameterByClass('ilPersonalDesktopGUI', 'pluginCmd', '');
				$ilCtrl->setParameterByClass('ilPersonalDesktopGUI', 'ref_id', '');
			}
		}
	}
}
