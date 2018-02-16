<?php
ilElectronicCourseReservePlugin::getInstance()->includeClass('controller/class.ilECRBaseController.php');

/**
 * Class ilECRContentController
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilECRContentController extends ilECRBaseController
{
	protected $plugin_object;
	
	public function __construct()
	{
		$this->plugin_object = ilElectronicCourseReservePlugin::getInstance();
	}
	
	public function executeCommand()
	{
		global $DIC;
		
		$cmd = $DIC->ctrl()->getCmd();
		if(method_exists($this, $cmd))
		{
			$this->$cmd();
		}
	}
	
	public function showLink()
	{
		global $DIC;
		
		$ilCtrl = $DIC->ctrl();
		$tpl = $DIC->ui()->mainTemplate();
		$ilTabs = $DIC->tabs();
		$lng = $DIC->language();
		
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		
		$tpl->setTitle($obj->getTitle());
		$tpl->setTitleIcon(ilUtil::getImagePath('icon_crs.svg'));
		
		$ilCtrl->setParameterByClass('ilRepositoryGUI', 'ref_id', $obj->getRefId());
		$ilTabs->setBackTarget($lng->txt('back'), $ilCtrl->getLinkTargetByClass('ilRepositoryGUI'));
		
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
	
	public function performRedirect() 
	{
		global $DIC;
		
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
}