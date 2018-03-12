<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
require_once 'Services/Component/classes/class.ilPluginConfigGUI.php';

class ilElectronicCourseReserveConfigGUI extends ilPluginConfigGUI
{
	/**
	 * @var ilElectronicCourseReservePlugin
	 */
	public $pluginObj = null;

	/**
	 * @var ilPropertyFormGUI
	 */
	public $form = null;
	
	
	public $tabs;
	public $ctrl;
	public $lng;
	public $tpl;
	public $settings;
	
	public function __construct()
	{
		global $DIC;
		$this->tabs = $DIC->tabs();
		$this->ctrl = $DIC->ctrl();
		$this->lng = $DIC->language();
		$this->tpl = $DIC->ui()->mainTemplate();
		$this->settings = $DIC->settings();
	}
	
	/**
	 * @param string $cmd
	 */
	public function performCommand($cmd)
	{
		$this->pluginObj = ilPlugin::getPluginObject('Services', 'UIComponent', 'uihk', 'ElectronicCourseReserve');
		$this->getTabs();
		
		switch($cmd)
		{
			default:
				$this->getSubTabs($cmd);
				$this->$cmd();
				break;
		}
	}

	
	public function getTabs()
	{
		$this->tabs->addTab('configure', $this->lng->txt('settings'), $this->ctrl->getLinkTarget($this, 'configure'));
		$this->tabs->addTab('showUseAgreementSettings', $this->lng->txt('use_agreement'), $this->ctrl->getLinkTarget($this, 'showUseAgreementSettings'));
	}
	
	public function getSubTabs($cmd)
	{
		switch($cmd)
		{
			case 'showUseAgreementSettings':
			case 'editUseAgreements':
			case 'showUseAgreementForm':
				$this->tabs->activateTab('showUseAgreementSettings');
				$this->tabs->addSubTab('showUseAgreementSettings', $this->lng->txt('settings'), $this->ctrl->getLinkTarget($this, 'showUseAgreementSettings'));
				$this->tabs->addSubTab('editUseAgreements', $this->lng->txt('edit_use_agreement'), $this->ctrl->getLinkTarget($this, 'editUseAgreements'));

				break;
		}
		
	}
	
	
	public function initUseAgreementSettingsForm()
	{
		if($this->form instanceof ilPropertyFormGUI)
		{
			return;
		}
		
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($this->ctrl->getFormAction($this, 'saveUseAgreementSettings'));
		$this->form->setTitle($this->lng->txt('settings'));
		$this->form->addCommandButton('saveUseAgreementSettings', $this->lng->txt('save'));
		
		$enable_use_agreement = new ilCheckboxInputGUI($this->pluginObj->txt('enable_use_agreement'), 'enable_use_agreement');
		$this->form->addItem($enable_use_agreement);

	}
	
	public function showUseAgreementSettings()
	{
		$this->tabs->activateSubTab('showUseAgreementSettings');
		$this->initUseAgreementSettingsForm();
		$this->populateValues();
		$this->tpl->setContent($this->form->getHTML());
		
	}
	/**
	 *
	 */
	public function saveUseAgreementSettings()
	{
		$this->initUseAgreementSettingsForm();
		
		if($this->form->checkInput())
		{
			$this->pluginObj->setSetting('enable_use_agreement', (int)$this->form->getInput('enable_use_agreement'));
			
			ilUtil::sendSuccess($this->lng->txt('saved_successfully'), true);
			$this->ctrl->redirect($this, 'showUseAgreementSettings');
		}
		
		$this->form->setValuesByPost();
		$this->tpl->setContent($this->form->getHTML());
	}
	
	
	public function editUseAgreements()
	{
		global $DIC;
		
		$toolbar = $DIC->toolbar();
		
		require_once 'Services/UIComponent/Button/classes/class.ilLinkButton.php';
		$button = ilLinkButton::getInstance();
		$button->setCaption($this->lng->txt('add_use_agreement'));
		$button->setUrl($this->ctrl->getLinkTarget($this, 'showUseAgreementForm'));
		$toolbar->addButtonInstance($button);
		
		$this->tabs->activateSubTab('editUseAgreements');
		
		$this->pluginObj->includeClass('tables/class.ilUseAgreementTableGUI.php');
		
		$table = new ilUseAgreementTableGUI($this);
		
		$this->tpl->setContent($table->getHTML());		
	}
	
	/**
	 * 
	 */
	public function initUseAgreementForm()
	{
		global $DIC;
		
		if($this->form instanceof ilPropertyFormGUI)
		{
			return;
		}
		
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($this->ctrl->getFormAction($this, 'saveUseAgreement'));
		$this->form->setTitle($this->lng->txt('addUseAgreement'));
		$this->form->addCommandButton('saveUseAgreement', $this->lng->txt('add'));
		$this->form->addCommandButton('editUseAgreements', $this->lng->txt('cancel'));
		
		$installed_langs  = $this->lng->getInstalledLanguages();
		$this->lng->loadLanguageModule('meta');
		foreach($installed_langs as $lang)
		{
			$lang_options[$lang] = $this->lng->txt('meta_l_'.$lang);
		}
		
		$lang_select = new ilSelectInputGUI($this->lng->txt('language'), 'lang');
		$lang_select->setOptions($lang_options);
		$this->form->addItem($lang_select);
		
		$agreement_input = new ilTextAreaInputGUI($this->lng->txt('agreement'), 'agreement');
		$agreement_input->setRequired(true);
		$agreement_input->setRows(15);
		$agreement_input->setUseRte(true);
		
		$agreement_input->removePlugin('advlink');
		$agreement_input->removePlugin('ilimgupload');
		$agreement_input->setRTERootBlockElement('');
		$agreement_input->usePurifier(true);
		$agreement_input->disableButtons(array(
			'charmap',
			'undo',
			'redo',
			'justifyleft',
			'justifycenter',
			'justifyright',
			'justifyfull',
			'anchor',
			'fullscreen',
			'cut',
			'copy',
			'paste',
			'pastetext',
			'formatselect'
		));
		
		//		 $agreement->getId()
		// @todo set agreement_id
		$agreement_input->setRTESupport($DIC->user()->getId(), 'ecr_ua', 'ecr_ua');
		
		// purifier
		require_once 'Services/Html/classes/class.ilHtmlPurifierFactory.php';
		$agreement_input->setPurifier(ilHtmlPurifierFactory::_getInstanceByType('frm_post'));
		
		$this->form->addItem($agreement_input);
	}
	
	/**
	 * 
	 */
	public function showUseAgreementForm()
	{
		$this->tabs->activateSubTab('editUseAgreements');
		
		$this->initUseAgreementForm();
		$this->tpl->setContent($this->form->getHTML());
	}
	
	/**
	 * 
	 */
	public function  saveUseAgreement()
	{
		$this->initUseAgreementForm();
		
		if($this->form->checkInput())
		{
			$lang = $this->form->getInput('lang');
			$agreement_text = $this->form->getInput('agreement');
				
			$this->pluginObj->includeClass('class.ilUseAgreement.php');
			$agreement_obj = new ilUseAgreement();
			$agreement_obj->setLang($lang);
			$agreement_obj->setAgreement($agreement_text);
				
			$agreement_obj->saveAgreement();
		}
	}
	
	/**
	 *
	 */
	protected function configure()
	{
		$this->tabs->activateTab('configure');
		$this->editSettings();
	}

	/**
	 *
	 */
	protected function editSettings()
	{
		global  $DIC;
		$tpl = $DIC->ui()->mainTemplate();
		$ilSetting = $DIC->settings();

		if(!$ilSetting->get('soap_user_administration'))
		{
			$ids     = ilObject::_getIdsForTitle('System Settings', 'adm');
			$id      = current($ids);
			$ref_ids = ilObject::_getAllReferences($id);
			$ref_id  = current($ref_ids);
			$url     = $this->getPluginObject()->getLinkTarget(
				array(
					'iladministrationgui',
					'ilobjsystemfoldergui'
				),
				array(
					'admin'  => 'settings',
					'ref_id' => $ref_id
				),
				'showWebServices'
			);
			ilUtil::sendFailure(sprintf($this->pluginObj->txt('ecr_soap_activation_required'), $url));
		}

		$this->initSettingsForm();
		$this->populateValues();
		$tpl->setContent($this->form->getHTML());
	}

	/**
	 *
	 */
	protected function populateValues()
	{
		$this->form->setValuesByArray(array(
			'gpg_homedir'         => $this->pluginObj->getSetting('gpg_homedir'),
			'sign_key_email'      => $this->pluginObj->getSetting('sign_key_email'),
			'limit_to_groles'     => $this->pluginObj->getSetting('limit_to_groles'),
			'global_roles'        => explode(',', $this->pluginObj->getSetting('global_roles')),
			'url_search_system'   => $this->pluginObj->getSetting('url_search_system'),
			'enable_use_agreement' => $this->pluginObj->getSetting('enable_use_agreement')
		));
	}

	/**
	 *
	 */
	protected function initSettingsForm()
	{
		global $DIC; 
		$lng = $DIC->language();
		$ilCtrl = $DIC->ctrl(); 
		$rbacreview = $DIC->rbac()->review();
		$ilObjDataCache = $DIC['ilObjDataCache'];

		if($this->form instanceof ilPropertyFormGUI)
		{
			return;
		}

		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($ilCtrl->getFormAction($this, 'saveSettings'));
		$this->form->setTitle($lng->txt('settings'));
		$this->form->addCommandButton('saveSettings', $lng->txt('save'));

		$form_gpg_homedir = new ilTextInputGUI($this->pluginObj->txt('ecr_gpg_homedir'), 'gpg_homedir');
		$form_gpg_homedir->setRequired(true);
		$form_gpg_homedir->setInfo($this->pluginObj->txt('ecr_gpg_homedir_info'));

		$form_key_email = new ilTextInputGUI($this->pluginObj->txt('ecr_sign_key_email'), 'sign_key_email');
		$form_key_email->setRequired(true);
		$form_key_email->setInfo($this->pluginObj->txt('ecr_sign_key_email_info'));

		$form_key_passphrase = new ilPasswordInputGUI($this->pluginObj->txt('ecr_sign_key_passphrase'), 'sign_key_passphrase');
		$form_key_passphrase->setRetypeValue(true);
		$form_key_passphrase->setSkipSyntaxCheck(true);
		$form_key_passphrase->setInfo($this->pluginObj->txt('ecr_sign_key_passphrase_info'));

		$form_search_system_url = new ilTextInputGUI($this->pluginObj->txt('ecr_url_search_system'), 'url_search_system');
		$form_search_system_url->setRequired(true);
		$form_search_system_url->setValidationRegexp('/((([A-Za-z]{3,9}:(?:\/\/)?)(?:[-;:&=\+\$,\w]+@)?[A-Za-z0-9.-]+|(?:www.|[-;:&=\+\$,\w]+@)[A-Za-z0-9.-]+)((?:\/[\+~%\/.\w-_]*)?\??(?:[-\+=&;%@.\w_]*)#?(?:[.\!\/\\w]*))?)/');
		$form_search_system_url->setValidationFailureMessage($this->pluginObj->txt('ecr_url_search_system_invalid'));
		$form_search_system_url->setInfo($this->pluginObj->txt('ecr_url_search_system_info'));

		$form_limit_to_groles = new ilCheckboxInputGUI($this->pluginObj->txt('limit_to_groles'), 'limit_to_groles');
		include_once 'Services/Form/classes/class.ilMultiSelectInputGUI.php';
		$sub_mlist = new ilMultiSelectInputGUI(
			$this->pluginObj->txt('global_roles'),
			'global_roles'
		);
		$roles = array();
		foreach($rbacreview->getGlobalRoles() as $role_id)
		{
			if( $role_id != ANONYMOUS_ROLE_ID )
				$roles[$role_id] = $ilObjDataCache->lookupTitle($role_id);
		}
		$sub_mlist->setOptions($roles);
		$form_limit_to_groles->addSubItem($sub_mlist);

		$this->form->addItem($form_gpg_homedir);
		$this->form->addItem($form_key_email);
		$this->form->addItem($form_key_passphrase);
		$this->form->addItem($form_search_system_url);
		$this->form->addItem($form_limit_to_groles);
		
	}

	/**
	 *
	 */
	public function saveSettings()
	{
		global $DIC;
		$tpl = $DIC->ui()->mainTemplate(); 
		$lng = $DIC->language(); 
		$ilCtrl = $DIC->ctrl();

		$this->initSettingsForm();

		if($this->form->checkInput())
		{
			$this->pluginObj->setSetting('limit_to_groles', (int)$this->form->getInput('limit_to_groles'));
			$this->pluginObj->setSetting('global_roles', implode(',', (array)$this->form->getInput('global_roles')));
			$this->pluginObj->setSetting('gpg_homedir', $this->form->getInput('gpg_homedir'));
			$this->pluginObj->setSetting('sign_key_email', $this->form->getInput('sign_key_email'));
			if($this->form->getInput('sign_key_passphrase'))
			{
				$this->pluginObj->setSetting('sign_key_passphrase', ilElectronicCourseReservePlugin::encrypt($this->form->getInput('sign_key_passphrase')));
			}
			$this->pluginObj->setSetting('url_search_system', $this->form->getInput('url_search_system'));

			ilUtil::sendSuccess($lng->txt('saved_successfully'), true);
			$ilCtrl->redirect($this);
		}

		$this->form->setValuesByPost();
		$tpl->setContent($this->form->getHTML());
	}
}
