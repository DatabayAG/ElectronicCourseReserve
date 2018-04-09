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
	
	protected $tpl;
	protected $ctrl;
	protected $tabs;
	protected $lng;
	protected $logger;
	protected $settings;
	
	/**
	 * ilECRContentController constructor.
	 */
	public function __construct()
	{
		global $DIC;
		
		$this->plugin_object = ilElectronicCourseReservePlugin::getInstance();
		
		$this->ctrl = $DIC->ctrl();
		$this->tpl = $DIC->ui()->mainTemplate();
		$this->tabs = $DIC->tabs();
		$this->lng = $DIC->language();
		$this->logger = $DIC->logger()->root();
		$this->settings = $DIC->settings();
	}
	
	/**
	 * 
	 */
	public function executeCommand()
	{
		$this->checkPermission('write');
		
		$cmd = $this->ctrl()->getCmd();
		if(method_exists($this, $cmd))
		{
			$this->$cmd();
		}
	}
	
	// @todo nadia: evtl in eigenen controller auslagern
	private function checkUseAgreementCondition()
	{
		$is_use_agreement_enabled = $this->settigns->get('ecr_enable_use_agreement', 0);
		
		if($is_use_agreement_enabled)
		{
			$this->checkUserAcceptance();
		}
		else
		{
			return true;
		}	
	}
 // @todo nadia: evtl in eigenen controller auslagern
	private function checkUserAcceptance()
	{
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		
		$this->plugin_object->includeClass('class.ilUserAcceptance.php');

		
		$ilUserAcceptance = new ilUserAcceptance($obj->getRefId());
		if($ilUserAcceptance->hasUserAcceptedAgreement())
		{
			return true;
		}
		
		$this->showUseAgreement($ref_id);
	}

 // @todo nadia: evtl in eigenen controller auslagern
	private function showUseAgreement()
	{
		global $DIC;
		$this->plugin_object->includeClass('class.ilUseAgreement.php');
		
		$agreement = new ilUseAgreement();
		$agreement->loadByLang($DIC->user()->getLanguage());
		
		
	}
	
	
	/**
	 * @return string
	 */
	public function showECRContent()
	{
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		
		$this->checkPermission('write');
		
		$this->tpl->setTitle($obj->getTitle());
		$this->tpl->setTitleIcon(ilUtil::getImagePath('icon_crs.svg'));
		
		$this->ctrl->setParameterByClass('ilObjCourseGUI', 'ref_id', $obj->getRefId());
		$this->tabs->setBackTarget($this->lng->txt('back'), $this->ctrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjCourseGUI'), 'view'));
		
		require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new ilPropertyFormGUI();
		$form->setTitle($this->plugin_object->txt('ecr_title'));

		$crs_ref_id =  new ilNonEditableValueGUI($this->plugin_object->txt('crs_ref_id'), 'crs_ref_id');
		$crs_ref_id->setValue($obj->getRefId());
		$form->addItem($crs_ref_id);
		
		$link = new ilNonEditableValueGUI('', 'ecr', true);
		$url = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'), 'ilECRContentController.performRedirect');
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
				$this->logger->write($e->getMessage());
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