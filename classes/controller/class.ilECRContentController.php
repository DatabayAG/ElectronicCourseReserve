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

		$this->ctrl     = $DIC->ctrl();
		$this->tpl      = $DIC->ui()->mainTemplate();
		$this->tabs     = $DIC->tabs();
		$this->lng      = $DIC->language();
		$this->logger   = $DIC->logger()->root();
		$this->settings = $DIC['ilSetting'];
		$this->user     = $DIC->user();
	}

	/**
	 *
	 */
	public function executeCommand()
	{
//		$this->checkPermission('write');
		$cmd = $this->ctrl()->getCmd();
		if(method_exists($this, $cmd))
		{
			$this->$cmd();
		}
	}

	private function checkUseAgreementCondition()
	{
		$is_use_agreement_enabled =$this->plugin_object->getSetting('enable_use_agreement');

		if($is_use_agreement_enabled)
		{
			$this->checkUserAcceptance();
		}
		else
		{
			return true;
		}
	}

	private function checkUserAcceptance()
	{
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);

		$this->plugin_object->includeClass('class.ilElectronicCourseReserveAcceptance.php');

		$ilUserAcceptance = new ilElectronicCourseReserveAcceptance($obj->getRefId());
		if($ilUserAcceptance->hasUserAcceptedAgreement())
		{
			return true;
		}

		$this->showUseAgreement($ref_id);
	}

	public function handleAcceptanceCmd()
	{
		if(isset($_POST['cmd']['saveAcceptedUserAgreement']))
		{
			$this->saveAcceptedUserAgreement();
		}
		else
		{
			$this->cancelAcceptance();
		}
	}

	private function showUseAgreement()
	{
		global $DIC;
		$this->plugin_object->includeClass('class.ilElectronicCourseReserveAgreement.php');
		$tpl = $DIC->ui()->mainTemplate();

		// CONFIRMATION
		include_once('Services/Utilities/classes/class.ilConfirmationGUI.php');
		$c_gui = new ilConfirmationGUI();

		$url = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'), 'ilECRContentController.handleAcceptanceCmd');

		$c_gui->setFormAction($url);
		$c_gui->setHeaderText($this->plugin_object->txt('use_agreement'));
		$c_gui->setCancel($this->lng->txt('cancel'), 'cancelAcceptance');
		$c_gui->setConfirm($this->lng->txt('confirm'), 'saveAcceptedUserAgreement');

		$agreement = new ilElectronicCourseReserveAgreement();
		$agreement->loadByLang($DIC->user()->getLanguage());
		$text = $agreement->getAgreement();
		$c_gui->addItem('accepted_ua', $DIC->user()->getId(), $text);

		$tpl->setContent($c_gui->getHTML());
		$tpl->show();
		exit;
	}

	public function cancelAcceptance()
	{
		$ref_id = (int)$_GET['ref_id'];
		$this->ctrl->setParameterByClass('ilObjCourseGUI', 'ref_id', $ref_id);
		$url = $this->ctrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjCourseGUI'), 'view', '', false, false);

		$this->ctrl->redirectToURL($url);
	}

	public function saveAcceptedUserAgreement()
	{
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);

		$this->plugin_object->includeClass('class.ilElectronicCourseReserveAcceptance.php');

		$ilUserAcceptance = new ilElectronicCourseReserveAcceptance($obj->getRefId());
		$ilUserAcceptance->saveUserAcceptance();
		$url = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'), 'ilECRContentController.showECRContent', '', false, false);

		$this->ctrl->redirectToURL($url);
	}

	/**
	 * @return string
	 */
	public function showECRContent()
	{
		$this->plugin_object->includeClass('class.ilElectronicCourseReserveLangData.php');

		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);

		$this->checkUseAgreementCondition();
//		$this->checkPermission('write');

		$this->tpl->setTitle($obj->getTitle());
		$this->tpl->setTitleIcon(ilUtil::getImagePath('icon_crs.svg'));

		$this->ctrl->setParameterByClass('ilObjCourseGUI', 'ref_id', $obj->getRefId());
		$this->tabs->setBackTarget($this->lng->txt('back'), $this->ctrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjCourseGUI'), 'view'));

		$ecr_content = ilElectronicCourseReserveLangData::lookupEcrContentByLangKey($this->user->getLanguage());
		$html = ilRTE::_replaceMediaObjectImageSrc($ecr_content, 1);
		if(strlen($html))
		{

			$html = $this->replacePlaceholder($html);
			return $html;
		}

		$ecr_content = ilElectronicCourseReserveLangData::lookupEcrContentByLangKey($this->lng->getDefaultLanguage());
		$html = ilRTE::_replaceMediaObjectImageSrc($ecr_content, 1);
		if(strlen($html))
		{
			$html = $this->replacePlaceholder($html);
			return $html;
		}

		$html = $this->getDefaultECRContent($obj);
		return $html;
	}

	/**
	 * @param $obj
	 * @return string
	 */
	protected function getDefaultECRContent($obj)
	{
		require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new ilPropertyFormGUI();
		$form->setTitle($this->plugin_object->txt('ecr_title'));

		$crs_ref_id = new ilNonEditableValueGUI($this->plugin_object->txt('crs_ref_id'), 'crs_ref_id');
		$crs_ref_id->setValue($obj->getRefId());
		$form->addItem($crs_ref_id);

		$link = new ilNonEditableValueGUI('', 'ecr', true);
		$url  = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'), 'ilECRContentController.performRedirect');
		$link->setValue('<a href="' . $url . '&pluginCmd=perform" target="_blank">' . $this->plugin_object->getSetting('url_search_system') . '</a>');

		$link->setInfo($this->plugin_object->txt('ecr_desc'));
		$form->addItem($link);

		return $form->getHTML();
	}

	protected function replacePlaceholder($html)
	{
		$url  = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'), 'ilECRContentController.performRedirect');
		$esa_url= '<a href="' . $url . '&pluginCmd=perform" target="_blank">' . $this->plugin_object->getSetting('url_search_system') . '</a>';
		return str_replace('###URL_ESA###', $esa_url, $html);
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

			/** @var \ILIAS\Plugin\ElectronicCourseReserve\Library\LinkBuilder $linkBuilder */
			$linkBuilder = $GLOBALS['DIC']['plugin.esa.library.linkbuilder'];

			$url = $linkBuilder->getLibraryOrderLink($obj);

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