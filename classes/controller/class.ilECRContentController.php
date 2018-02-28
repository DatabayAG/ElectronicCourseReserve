<?php
ilElectronicCourseReservePlugin::getInstance()->includeClass('controller/class.ilECRBaseController.php');

/**
 * Class ilECRContentController
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilECRContentController extends ilECRBaseController
{
	/**
	 * @var ilElectronicCourseReservePlugin
	 */
	protected $plugin_object;
	
	/**
	 * ilECRContentController constructor.
	 */
	public function __construct()
	{
		$this->plugin_object = ilElectronicCourseReservePlugin::getInstance();
	}
	
	/**
	 * 
	 */
	public function executeCommand()
	{
		global $DIC;
		
		$this->checkPermission('write');
		
		$cmd = $DIC->ctrl()->getCmd();
		if(method_exists($this, $cmd))
		{
			$this->$cmd();
		}
	}
	
	/**
	 * @return string
	 */
	public function showECRContent()
	{
		global $DIC;
		
		$ilCtrl = $DIC->ctrl();
		$tpl    = $DIC->ui()->mainTemplate();
		$ilTabs = $DIC->tabs();
		$lng    = $DIC->language();
		
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		
		$this->checkPermission('write');
		
		$tpl->setTitle($obj->getTitle());
		$tpl->setTitleIcon(ilUtil::getImagePath('icon_crs.svg'));
		
		$ilCtrl->setParameterByClass('ilObjCourseGUI', 'ref_id', $obj->getRefId());
		$ilTabs->setBackTarget($lng->txt('back'), $ilCtrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjCourseGUI'), 'view'));
		
		require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new ilPropertyFormGUI();
		$form->setTitle($this->plugin_object->txt('ecr_title'));

		$crs_ref_id =  new ilNonEditableValueGUI($this->plugin_object->txt('crs_ref_id'), 'crs_ref_id');
		$crs_ref_id->setValue($obj->getRefId());
		$form->addItem($crs_ref_id);
		
		$link = new ilNonEditableValueGUI('', 'ecr', true);
		$url = $ilCtrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'), 'ilECRContentController.performRedirect');
		$link->setValue('<a href="'.$url.'&pluginCmd=perform" target="_blank">' . $this->plugin_object->getSetting('url_search_system') . '</a>');
		
		$link->setInfo($this->plugin_object->txt('ecr_desc'));
		$form->addItem($link);
		
		return $form->getHTML();
	}
	
	/**
	 * @return string
	 */
	public function performRedirect() 
	{
		global $DIC;
		
		$this->checkPermission('write');
		
		try
		{
			$ref_id = (int)$_GET['ref_id'];
			$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
			$url = $this->plugin_object->getLibraryOrderLink($obj);
			
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
				$DIC->logger()->write($e->getMessage());
				ilUtil::sendFailure($this->plugin_object->txt('ecr_sign_error_occured'));
			}
			return '';
		}
	}
	
	/**
	 * @param string $permission
	 */
	public function checkPermission($permission = 'write')
	{
		global $DIC;
		
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		
		if(!($obj instanceof ilObjCourse
			&& $DIC->access()->checkAccess($permission, '', $obj->getRefId())
			&& $this->plugin_object->isAssignedToRequiredRole($DIC->user()->getId())))
		{
			$DIC['ilErr']->raiseError($DIC->language()->txt("msg_no_perm_read"), $DIC['ilErr']->MESSAGE);
		}
	}
}