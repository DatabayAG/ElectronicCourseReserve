<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
require_once 'Services/Component/classes/class.ilPluginConfigGUI.php';
require_once 'Services/User/classes/class.ilUserAutoComplete.php';

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
			case 'doUserAutoComplete':
				$this->doUserAutoComplete();
				break;
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
			'url_search_system'   => $this->pluginObj->getSetting('url_search_system'),
			'is_mail_enabled'     => $this->pluginObj->getSetting('is_mail_enabled'),
			'recipients'          => explode(',', $this->pluginObj->getSetting('mail_recipients'))
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

		$mail = new ilCheckboxInputGUI($this->getPluginObject()->txt('notification_mail'), 'is_mail_enabled');
		$mail->setInfo($this->getPluginObject()->txt('notification_mail_info'));

		// RECIPIENT
		$dsDataLink = $DIC->ctrl()->getLinkTarget($this, 'doUserAutoComplete', '', true);
		$recipients = new ilTextInputGUI($this->getPluginObject()->txt('recipients'), 'recipients');
		$recipients->setRequired(true);
		$recipients->setValue(array());
		$recipients->setDataSource($dsDataLink);
		$recipients->setMaxLength(null);
		$recipients->setMulti(true);
		$recipients->setInfo($this->getPluginObject()->txt('recipients_info'));
		$mail->addSubItem($recipients);
		$this->form->addItem($mail);
		
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
			$recipients = $this->form->getInput('recipients');
			$this->pluginObj->setSetting('limit_to_groles', (int)$this->form->getInput('limit_to_groles'));
			$this->pluginObj->setSetting('global_roles', implode(',', (array)$this->form->getInput('global_roles')));
			$this->pluginObj->setSetting('gpg_homedir', $this->form->getInput('gpg_homedir'));
			$this->pluginObj->setSetting('sign_key_email', $this->form->getInput('sign_key_email'));
			$this->pluginObj->setSetting('is_mail_enabled', $this->form->getInput('is_mail_enabled'));
			$this->pluginObj->setSetting('mail_recipients', implode(',', $recipients));

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

	/**
	 * Do auto completion
	 * @return void
	 */
	public function doUserAutoComplete()
	{

		if(!isset($_GET['autoCompleteField']))
		{
			$a_fields = array('login','firstname','lastname','email', 'recipients');
			$result_field = 'login';
		}
		else
		{
			$a_fields = array((string) $_GET['autoCompleteField']);
			$result_field = (string) $_GET['autoCompleteField'];
		}

		$GLOBALS['ilLog']->write(print_r($a_fields,true));
		$auto = new ilUserAutoComplete();
		$auto->setSearchFields($a_fields);
		$auto->setResultField($result_field);
		$auto->enableFieldSearchableCheck(true);
		echo $auto->getList($_REQUEST['term']);
		exit();
	}
}
