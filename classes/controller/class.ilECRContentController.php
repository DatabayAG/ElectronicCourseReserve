<?php
ilElectronicCourseReservePlugin::getInstance()->includeClass('controller/class.ilECRBaseController.php');
require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
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
	public function showECRItemContent()
	{
		global $DIC;

		$ilCtrl = $DIC->ctrl();
		$tpl    = $DIC->ui()->mainTemplate();
		$ilTabs = $DIC->tabs();
		$lng    = $DIC->language();

		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		$item   = $this->plugin_object->queryItemData($ref_id);

		$this->checkPermission('write');

		$tpl->setTitle($obj->getTitle());

		if($obj->getType() === 'file' )
		{
			if(array_key_exists('show_image', $item)
				&& $item['show_image']
				&& $item['show_image'] == 1
				&& strlen($item['icon']) > 0)
			{
				$tpl->setTitleIcon(ilUtil::getImagePath($item['icon']));
			}
			else
			{
				$tpl->setTitleIcon(ilUtil::getImagePath('icon_file.svg'));
			}
			$ilCtrl->setParameterByClass('ilObjFileGUI', 'ref_id', $obj->getRefId());
			$ilTabs->setBackTarget($lng->txt('back'), $ilCtrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjFileGUI'), 'infoScreen'));
		}
		else if($obj->getType() === 'webr' )
		{
			if(array_key_exists('show_image', $item)
				&& $item['show_image']
				&& $item['show_image'] == 1
				&& strlen($item['icon']) > 0)
			{
				$tpl->setTitleIcon(ilUtil::getImagePath($item['icon']));
			}
			else
			{
				$tpl->setTitleIcon(ilUtil::getImagePath('icon_webr.svg'));
			}
			$ilCtrl->setParameterByClass('ilObjLinkResourceGUI', 'ref_id', $obj->getRefId());
			$ilTabs->setBackTarget($lng->txt('back'), $ilCtrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjLinkResourceGUI'), 'infoScreen'));
		}

		$form = new ilPropertyFormGUI();
		#$form->setFormAction($ilCtrl->getFormAction(new ilECRContentController(), 'ilECRContentController'));
		#$form->addCommandButton('ilECRContentController', $lng->txt('submit'));
		$form->setTitle($this->plugin_object->txt('ecr_title'));

		$show_description =  new ilCheckboxInputGUI($this->plugin_object->txt('show_description'), 'show_description');

		if(array_key_exists('show_description', $item) 
			&& $item['show_description'] 
			&& $item['show_description'] == 1)
		{
			$show_description->setChecked(true);
		}
		$form->addItem($show_description);

		$show_image = new ilCheckboxInputGUI($this->plugin_object->txt('show_image'), 'show_image');
		if(array_key_exists('show_image', $item)
			&& $item['show_image']
			&& $item['show_image'] == 1)
		{
			$show_image->setChecked(true);
		}

		
		$form->addItem($show_image);

		return $form->getHTML();
	}
	
	public function updateItemSettings()
	{
		$a = 0;
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
		
		if(!(($obj instanceof ilObjCourse || $obj instanceof ilObjFile || $obj instanceof ilObjLinkResource)
			&& $DIC->access()->checkAccess($permission, '', $obj->getRefId())
			&& $this->plugin_object->isAssignedToRequiredRole($DIC->user()->getId())))
		{
			
			$DIC['ilErr']->raiseError($DIC->language()->txt("msg_no_perm_read"), $DIC['ilErr']->MESSAGE);
		}
	}
}