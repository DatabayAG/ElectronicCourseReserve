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
	 * @var ilECRBaseModifier[]
	 */
	protected $modifier_cache = array();

	/**
	 * @var ilElectronicCourseReservePlugin
	 */
	public $plugin_object;

	public function __construct()
	{
		parent::getPluginObject();
		if(!$this->modifier_cache  || count($this->modifier_cache ) == 0)
		{
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
	 * @return array|string
	 */
	public function getHTML($a_comp, $a_part, $a_par = array())
	{
		global $DIC;
		$ilUser = $DIC->user();
		$ilAccess = $DIC->access();

		if($a_part != 'template_get')
		{
			return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

		$plugin = ilElectronicCourseReservePlugin::getInstance();

		$ref_id = (int)$_GET['ref_id'];
		if($plugin->isFolderRelevant($ref_id))
		{
			$plugin->queryFolderData($ref_id);
		}
		/**
		 * @var $modifier ilECRBaseModifier
		 */
		if(is_array($this->modifier_cache))
		{
			foreach($this->modifier_cache as $key => $modifier)
			{
				if($modifier->shouldModifyHtml($a_comp, $a_part, $a_par))
				{
					return $modifier->modifyHtml($a_comp, $a_part, $a_par);
				}
			}
		}

		if(!isset($_GET['pluginCmd']) || 'Services/PersonalDesktop' != $a_comp || !isset($_GET['ref_id']))
		{
			return parent::getHTML($a_comp, $a_part, $a_par);
		}

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
			global $DIC; 
			$ilCtrl   = $DIC->ctrl(); 
			$ilAccess = $DIC->access(); 
			$ilUser   = $DIC->user();

			$this->getPluginObject()->loadLanguageModule();

			$ref_id = (int)$_GET['ref_id'];
			$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);

			if( $obj instanceof ilObjCourse 
				&& $ilAccess->checkAccess('write', '', $obj->getRefId()) 
				&& $this->plugin_object->isCourseRelevant($ref_id) 
				&& $this->getPluginObject()->isAssignedToRequiredRole($ilUser->getId())
			)
			{
				$ilCtrl->setParameterByClass(__CLASS__, 'ref_id', $obj->getRefId());
				$DIC->tabs()->addTab(
					'ecr_tab_title',
					$this->getPluginObject()->txt('ecr_tab_title'),
					$ilCtrl->getLinkTargetByClass(['ilUIPluginRouterGUI', __CLASS__], 'ilECRContentController.showECRContent')
				);
			}
		}
	}
	protected function initModifier()
	{
		$this->plugin_object = ilElectronicCourseReservePlugin::getInstance();
		$this->plugin_object->includeClass("modifier/class.ilECRInfoScreenModifier.php");
		$this->plugin_object->includeClass("modifier/class.ilECRFolderListGuiModifier.php");
		
		$this->modifier_cache = array(
			new ilECRInfoScreenModifier(), 
			new ilECRFolderListGuiModifier()
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
