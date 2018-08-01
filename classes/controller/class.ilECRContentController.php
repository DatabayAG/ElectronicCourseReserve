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

	protected $tpl;
	protected $ctrl;
	protected $tabs;
	protected $lng;
	protected $logger;
	protected $settings;
	protected $user;
	protected $access;

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
		$this->access   = $DIC->access();
	}

	/**
	 *
	 */
	public function executeCommand()
	{
//		$this->checkPermission('write');
		$cmd = $this->ctrl->getCmd();
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

		$this->showUseAgreement();
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
		$this->plugin_object->includeClass('class.ilElectronicCourseReserveAgreement.php');
		$tpl = $this->tpl;

		// CONFIRMATION
		include_once('Services/Utilities/classes/class.ilConfirmationGUI.php');
		$c_gui = new ilConfirmationGUI();

		$url = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'), 'ilECRContentController.handleAcceptanceCmd');

		$c_gui->setFormAction($url);
		$c_gui->setHeaderText($this->plugin_object->txt('use_agreement'));
		$c_gui->setCancel($this->lng->txt('cancel'), 'cancelAcceptance');
		$c_gui->setConfirm($this->lng->txt('confirm'), 'saveAcceptedUserAgreement');

		$agreement = new ilElectronicCourseReserveAgreement();
		$agreement->loadByLang($this->user->getLanguage());
		$text = $agreement->getAgreement();
		$c_gui->addItem('accepted_ua', $this->user->getId(), $text);

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

		$html = $this->getDefaultECRContent();
		return $html;
	}

	/**
	 * @param $obj
	 * @return string
	 */
	protected function getDefaultECRContent()
	{
		$form = new ilPropertyFormGUI();
		$form->setTitle($this->plugin_object->txt('ecr_title'));

		$link = new ilNonEditableValueGUI($this->plugin_object->txt('ecr_url_search_system'), 'ecr', true);
		$url  = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'), 'ilECRContentController.performRedirect');
		$link->setValue('<a href="' . $url . '&pluginCmd=perform" target="_blank">' . $this->plugin_object->getSetting('url_search_system') . '</a>');

		$link->setInfo($this->plugin_object->txt('ecr_desc'));
		$form->addItem($link);
		
		return $form->getHTML();
	}

	/**
	 * @return string
	 */
	public function showECRItemContent()
	{
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		$item   = $this->plugin_object->queryItemData($ref_id);

		$this->checkPermission('write');

		$this->tpl->setTitle($obj->getTitle());

		if($obj->getType() === 'file' )
		{
			if(array_key_exists('show_image', $item)
				&& $item['show_image']
				&& $item['show_image'] == 1
				&& strlen($item['icon']) > 0)
			{
				$image_path = ILIAS_WEB_DIR . DIRECTORY_SEPARATOR . CLIENT_ID . DIRECTORY_SEPARATOR . $item['icon'];
				$this->tpl->setTitleIcon($image_path);
			}
			else
			{
				$this->tpl->setTitleIcon(ilUtil::getImagePath('icon_file.svg'));
			}
			$this->ctrl->setParameterByClass('ilObjFileGUI', 'ref_id', $obj->getRefId());
			$this->tabs->setBackTarget($this->lng->txt('back'), $this->ctrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjFileGUI'), 'infoScreen'));
		}
		else if($obj->getType() === 'webr' )
		{
			if(array_key_exists('show_image', $item)
				&& $item['show_image']
				&& $item['show_image'] == 1
				&& strlen($item['icon']) > 0)
			{
				$image_path = ILIAS_WEB_DIR . DIRECTORY_SEPARATOR . CLIENT_ID . DIRECTORY_SEPARATOR . $item['icon'];
				$this->tpl->setTitleIcon($image_path);
			}
			else
			{
				$this->tpl->setTitleIcon(ilUtil::getImagePath('icon_webr.svg'));
			}
			$this->ctrl->setParameterByClass('ilObjLinkResourceGUI', 'ref_id', $obj->getRefId());
			$this->tabs->setBackTarget($this->lng->txt('back'), $this->ctrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjLinkResourceGUI'), 'infoScreen'));
		}

		$form = new ilPropertyFormGUI();
		$form->setFormAction($url = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'), 'ilECRContentController.updateItemSettings'));
		$form->addCommandButton('ilECRContentController', $this->lng->txt('submit'));
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

		$hidden = new ilHiddenInputGUI('ref_id');
		$hidden->setValue($ref_id);
		$form->addItem($hidden);

		$form->addItem($show_image);

		return $form->getHTML();
	}

	protected function replacePlaceholder($html)
	{
		$url  = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'), 'ilECRContentController.performRedirect');
		$esa_url= '<a href="' . $url . '&pluginCmd=perform" target="_blank">' . $this->plugin_object->getSetting('url_search_system') . '</a>';
		return str_replace('###URL_ESA###', $esa_url, $html);
	}
	
	/**
	 *
	 */
	public function updateItemSettings()
	{
		$show_description = (int) $_POST['show_description'];
		$show_image = (int) $_POST['show_image'];
		$ref_id = (int) $_POST['ref_id'];
		if($ref_id > 0)
		{
			$this->plugin_object->updateItemData($ref_id, $show_description, $show_image);
		}

		$this->ctrl->redirect(new ilElectronicCourseReserveUIHookGUI(), 'ilECRContentController.showECRItemContent');
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
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		
		if(!(($obj instanceof ilObjCourse || $obj instanceof ilObjFile || $obj instanceof ilObjLinkResource)
			&& $this->access->checkAccess($permission, '', $obj->getRefId())
			&& $this->plugin_object->isAssignedToRequiredRole($this->user->getId())))
		{
			ilUtil::sendFailure($this->lng->txt("msg_no_perm_read"), true);
		}
	}
}