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

	/**
	 * @param string $cmd
	 */
	public function performCommand($cmd)
	{
		$this->pluginObj = ilPlugin::getPluginObject('Services', 'UIComponent', 'uihk', 'ElectronicCourseReserve');
		switch($cmd)
		{
			default:
				$this->$cmd();
				break;
		}
	}

	/**
	 *
	 */
	protected function configure()
	{
		$this->editSettings();
	}

	/**
	 *
	 */
	protected function editSettings()
	{
		global  $DIC, $ilSetting;
		$tpl = $DIC->ui()->mainTemplate();

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
			'url_search_system'   => $this->pluginObj->getSetting('url_search_system')
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


	/**N
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
