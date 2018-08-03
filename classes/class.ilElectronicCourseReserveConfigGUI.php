<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
require_once dirname(__FILE__) . '/class.ilElectronicCourseReserveBaseGUI.php';

/**
 * Class ilElectronicCourseReserveConfigGUI
 * @ilCtrl_calls ilElectronicCourseReserveConfigGUI: ilFileSystemGUI
 * @ilCtrl_calls ilElectronicCourseReserveConfigGUI: ilElectronicCourseReserveAgreementConfigGUI, ilElectronicCourseReserveContentConfigGUI
 */
class ilElectronicCourseReserveConfigGUI extends \ilElectronicCourseReserveBaseGUI
{
	/**
	 * @inheritdoc
	 */
	protected function getDefaultCommand()
	{
		return 'showGeneralConfiguration';
	}

	/**
	 * @inheritdoc
	 */
	public function executeCommand()
	{
		$nextClass = $this->ctrl->getNextClass();
		switch (strtolower($nextClass)) {
			case 'ilfilesystemgui':
				$this->tpl->setTitle($this->lng->txt('cmps_plugin'). ': ' . $_GET["pname"]);
				$this->tpl->setDescription("");

				$this->ctrl->setParameterByClass('ilfilesystemgui', 'ctype', $_GET['ctype']);
				$this->ctrl->setParameterByClass('ilfilesystemgui', 'cname', $_GET['cname']);
				$this->ctrl->setParameterByClass('ilfilesystemgui', 'slot_id', $_GET['slot_id']);
				$this->ctrl->setParameterByClass('ilfilesystemgui', 'plugin_id', $_GET['plugin_id']);
				$this->ctrl->setParameterByClass('ilfilesystemgui', 'pname', $_GET['pname']);

				$this->ctrl->setParameterByClass(__CLASS__, 'ctype', $_GET['ctype']);
				$this->ctrl->setParameterByClass(__CLASS__, 'cname', $_GET['cname']);
				$this->ctrl->setParameterByClass(__CLASS__, 'slot_id', $_GET['slot_id']);
				$this->ctrl->setParameterByClass(__CLASS__, 'plugin_id', $_GET['plugin_id']);
				$this->ctrl->setParameterByClass(__CLASS__, 'pname', $_GET['pname']);

				$this->showTabs();
				$this->tabs->setSubTabActive('import_directory');

				$importDirectory = ilUtil::getDataDir() . '/' . $this->getPluginObject()->getSetting('import_directory');
				if ($this->isValidDirectory($importDirectory) && is_dir($importDirectory)) {
					require_once 'Services/FileSystem/classes/class.ilFileSystemGUI.php';
					$gui = new ilFileSystemGUI(ilUtil::getDataDir() . '/' . $this->getPluginObject()->getSetting('import_directory'));
					$gui->setAllowFileCreation(true);
					$gui->setAllowDirectoryCreation(false);
					$gui->setAllowedSuffixes(array('xml', 'jpeg', 'jpg', 'svg', 'png'));
					$commands = $gui->getActionCommands();
					$commands = array_filter($commands, function($cmd) {
						return $cmd['method'] != 'renameFileForm';
					});
					$gui->clearCommands();
					foreach ($commands as $cmd) {
						$gui->addCommand($cmd['object'], $cmd['method'], $cmd['name'], $cmd['single'], $cmd['allow_dir']);
					}
					$this->ctrl->forwardCommand($gui);
					return;
				} else {
					$this->ctrl->redirect($this);
				}
				break;

			case strtolower('ilElectronicCourseReserveAgreementConfigGUI'):
				\ilElectronicCourseReservePlugin::getInstance()->includeClass('class.ilElectronicCourseReserveAgreementConfigGUI.php');
				$this->ctrl->forwardCommand(new \ilElectronicCourseReserveAgreementConfigGUI(\ilElectronicCourseReservePlugin::getInstance()));
				break;

			case strtolower('ilElectronicCourseReserveContentConfigGUI'):
				\ilElectronicCourseReservePlugin::getInstance()->includeClass('class.ilElectronicCourseReserveContentConfigGUI.php');
				$this->ctrl->forwardCommand(new \ilElectronicCourseReserveContentConfigGUI(\ilElectronicCourseReservePlugin::getInstance()));
				break;

			default:
				parent::executeCommand();
				$this->tabs->setSubTabActive('settings');
				break;
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function showTabs()
	{
		parent::showTabs();

		$this->tabs->addSubTab(
			'settings',
			$this->lng->txt('settings'),
			$this->ctrl->getLinkTarget($this, 'configure')
		);

		$importDirectory = \ilUtil::getDataDir() . '/' . $this->getPluginObject()->getSetting('import_directory');
		if ($this->isValidDirectory($importDirectory) && is_dir($importDirectory)) {
			$this->ctrl->setParameterByClass('ilfilesystemgui', 'ctype', $_GET['ctype']);
			$this->ctrl->setParameterByClass('ilfilesystemgui', 'cname', $_GET['cname']);
			$this->ctrl->setParameterByClass('ilfilesystemgui', 'slot_id', $_GET['slot_id']);
			$this->ctrl->setParameterByClass('ilfilesystemgui', 'plugin_id', $_GET['plugin_id']);
			$this->ctrl->setParameterByClass('ilfilesystemgui', 'pname', $_GET['pname']);

			$this->tabs->addSubTab(
				'import_directory',
				$this->getPluginObject()->txt('import_directory'),
				$this->ctrl->getLinkTargetByClass('ilfilesystemgui', 'listFiles')
			);
		}

		if (in_array(strtolower($this->ctrl->getCmd()), ['listfiles']) || strtolower($_GET['cmdClass']) === 'ilfilesystemgui') {
			$this->tabs->activateSubTab('import_directory');
		} else {
			$this->tabs->activateSubTab('configure');
		}
	}


	/**
	 * @param string $a_directory
	 * @return bool
	 */
	protected function isValidDirectory($a_directory)
	{
		$a_directory = basename($a_directory);
		if (substr($a_directory, 0, 1) === '/' || substr($a_directory, 0, 1) === '.' || $a_directory === '') {
			return false;
		}

		return true;
	}

	/**
	 * @param ilPropertyFormGUI|null $form
	 */
	protected function showGeneralConfiguration(\ilPropertyFormGUI $form = null)
	{
		if (!$this->settings->get('soap_user_administration')) {
			$ids = \ilObject::_getIdsForTitle('System Settings', 'adm');
			$id = current($ids);
			$ref_ids = \ilObject::_getAllReferences($id);
			$ref_id = current($ref_ids);
			$url = $this->ctrl->getLinkTarget(
				array(
					'iladministrationgui',
					'ilobjsystemfoldergui'
				),
				array(
					'admin' => 'settings',
					'ref_id' => $ref_id
				),
				'showWebServices'
			);
			\ilUtil::sendFailure(sprintf($this->getPluginObject()->txt('ecr_soap_activation_required'), $url));
		}

		if (null === $form) {
			$form = $this->getGeneralSettingsForm();
		}

		$this->populateValues($form);
		$this->tpl->setContent($form->getHTML());
	}

	/**
	 * @param ilPropertyFormGUI $form
	 */
	protected function populateValues(\ilPropertyFormGUI $form)
	{
		$form->setValuesByArray([
			'gpg_homedir' => $this->getPluginObject()->getSetting('gpg_homedir'),
			'sign_key_email' => $this->getPluginObject()->getSetting('sign_key_email'),
			'limit_to_groles' => $this->getPluginObject()->getSetting('limit_to_groles'),
			'global_roles' => explode(',', $this->getPluginObject()->getSetting('global_roles')),
			'url_search_system' => $this->getPluginObject()->getSetting('url_search_system'),
			'enable_use_agreement' => $this->getPluginObject()->getSetting('enable_use_agreement'),
			'token_append_obj_title' => $this->getPluginObject()->getSetting('token_append_obj_title'),
			'token_append_to_bibl' => $this->getPluginObject()->getSetting('token_append_to_bibl'),
			'is_mail_enabled' => $this->getPluginObject()->getSetting('is_mail_enabled'),
			'recipients' => explode(',', $this->getPluginObject()->getSetting('mail_recipients')),
			'import_directory' => $this->getPluginObject()->getSetting('import_directory')
		]);
	}

	/**
	 *
	 */
	protected function getGeneralSettingsForm()
	{
		$disabled = false;
		if ($this->lock->isLocked()) {
			$disabled = true;
		}

		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this, 'saveSettings'));
		$form->setTitle($this->lng->txt('settings'));

		$form_gpg_homedir = new \ilTextInputGUI($this->getPluginObject()->txt('ecr_gpg_homedir'), 'gpg_homedir');
		$form_gpg_homedir->setDisabled($disabled);
		$form_gpg_homedir->setRequired(true);
		$form_gpg_homedir->setInfo($this->getPluginObject()->txt('ecr_gpg_homedir_info'));

		$form_key_email = new \ilTextInputGUI($this->getPluginObject()->txt('ecr_sign_key_email'), 'sign_key_email');
		$form_key_email->setDisabled($disabled);
		$form_key_email->setRequired(true);
		$form_key_email->setInfo($this->getPluginObject()->txt('ecr_sign_key_email_info'));

		$form_key_passphrase = new \ilPasswordInputGUI($this->getPluginObject()->txt('ecr_sign_key_passphrase'), 'sign_key_passphrase');
		$form_key_passphrase->setDisabled($disabled);
		$form_key_passphrase->setRetypeValue(true);
		$form_key_passphrase->setSkipSyntaxCheck(true);
		$form_key_passphrase->setInfo($this->getPluginObject()->txt('ecr_sign_key_passphrase_info'));

		$form_search_system_url = new \ilTextInputGUI($this->getPluginObject()->txt('ecr_url_search_system'), 'url_search_system');
		$form_search_system_url->setDisabled($disabled);
		$form_search_system_url->setRequired(true);
		$form_search_system_url->setValidationRegexp('/((([A-Za-z]{3,9}:(?:\/\/)?)(?:[-;:&=\+\$,\w]+@)?[A-Za-z0-9.-]+|(?:www.|[-;:&=\+\$,\w]+@)[A-Za-z0-9.-]+)((?:\/[\+~%\/.\w-_]*)?\??(?:[-\+=&;%@.\w_]*)#?(?:[.\!\/\\w]*))?)/');
		$form_search_system_url->setValidationFailureMessage($this->getPluginObject()->txt('ecr_url_search_system_invalid'));
		$form_search_system_url->setInfo($this->getPluginObject()->txt('ecr_url_search_system_info'));


		$form->setValuesByArray([
			'gpg_homedir' => $this->getPluginObject()->getSetting('gpg_homedir'),
			'sign_key_email' => $this->getPluginObject()->getSetting('sign_key_email'),
			'limit_to_groles' => $this->getPluginObject()->getSetting('limit_to_groles'),
			'global_roles' => explode(',', $this->getPluginObject()->getSetting('global_roles')),
			'url_search_system' => $this->getPluginObject()->getSetting('url_search_system'),
			'enable_use_agreement' => $this->getPluginObject()->getSetting('enable_use_agreement'),
			'token_append_obj_title' => $this->getPluginObject()->getSetting('token_append_obj_title'),
			'token_append_to_bibl' => $this->getPluginObject()->getSetting('token_append_to_bibl'),
			'is_mail_enabled' => $this->getPluginObject()->getSetting('is_mail_enabled'),
			'recipients' => explode(',', $this->getPluginObject()->getSetting('mail_recipients')),
			'import_directory' => $this->getPluginObject()->getSetting('import_directory')
		]);

		if (
			$this->getPluginObject()->getSetting('gpg_homedir') &&
			$this->getPluginObject()->getSetting('sign_key_email') &&
			$this->getPluginObject()->getSetting('sign_key_passphrase') &&
			$this->getPluginObject()->getSetting('url_search_system')
		) {

			$dummyCrs = new \ilObjCourse();
			$dummyCrs->setId(PHP_INT_MAX);
			$dummyCrs->setRefId(PHP_INT_MAX);
			$dummyCrs->setTitle('Example');

			$exampleUrlTpl = $this->getPluginObject()->getTemplate('tpl.example_url.html', true, true);
			/** @var ILIAS\Plugin\ElectronicCourseReserve\Library\LinkBuilder $linkBuilder */
			$linkBuilder = $GLOBALS['DIC']['plugin.esa.library.linkbuilder'];
			$exampleUrlTpl->setVariable('URL', $linkBuilder->getLibraryOrderLink($dummyCrs));

			$exampleLink = new \ilNonEditableValueGUI($this->getPluginObject()->txt('ecr_example_url'), '', true);
			$exampleLink->setInfo($this->getPluginObject()->txt('ecr_example_url_info'));
			$exampleLink->setValue($exampleUrlTpl->get());
			$form_search_system_url->addSubItem($exampleLink);
		}

		$tokenAppendCrsTitle = new \ilCheckboxInputGUI($this->getPluginObject()->txt('token_append_obj_title'), 'token_append_obj_title');
		$tokenAppendCrsTitle->setDisabled($disabled);
		$tokenAppendCrsTitle->setInfo($this->getPluginObject()->txt('token_append_obj_title_info'));
		$tokenAppendCrsTitle->setValue(1);

		$tokenAppendToBibItems = new \ilCheckboxInputGUI($this->getPluginObject()->txt('token_append_to_bibl'), 'token_append_to_bibl');
		$tokenAppendToBibItems->setDisabled($disabled);

		$bibObjIds = array_keys(\ilObject::_getObjectsByType('bibs'));
		$bibObjId = current($bibObjIds);
		$bibRefIds = \ilObject::_getAllReferences($bibObjId);
		$this->ctrl->setParameterByClass('ilobjbibliographicadmingui', 'ref_id', current($bibRefIds));
		$bitAdmUrl = $this->ctrl->getLinkTargetByClass(['ilAdministrationGUI', 'ilobjbibliographicadmingui'], 'view');
		$this->ctrl->setParameterByClass('ilobjbibliographicadmingui', 'ref_id', null);
		$tokenAppendToBibItems->setInfo(sprintf(
			$this->getPluginObject()->txt('token_append_to_bibl_info'),
			$bitAdmUrl
		));
		$tokenAppendToBibItems->setValue(1);

		$accessFormSection = new \ilFormSectionHeaderGUI();
		$accessFormSection->setTitle($this->getPluginObject()->txt('form_header_access'));

		$limitToGlobalRoles = new \ilCheckboxInputGUI($this->getPluginObject()->txt('limit_to_groles'), 'limit_to_groles');
		$limitToGlobalRoles->setInfo($this->getPluginObject()->txt('global_roles_info'));
		$limitToGlobalRoles->setDisabled($disabled);
		require_once 'Services/Form/classes/class.ilMultiSelectInputGUI.php';
		$permittedRoles = new \ilMultiSelectInputGUI(
			$this->getPluginObject()->txt('global_roles'),
			'global_roles'
		);
		$permittedRoles->setDisabled($disabled);
		$roles = [];
		foreach($this->rbacreview->getGlobalRoles() as $role_id) {
			if ($role_id != ANONYMOUS_ROLE_ID ) {
				$roles[$role_id] = $this->objectCache->lookupTitle($role_id);
			}
		}
		$permittedRoles->setOptions($roles);
		$limitToGlobalRoles->addSubItem($permittedRoles);

		$importFormSection = new \ilFormSectionHeaderGUI();
		$importFormSection->setTitle($this->getPluginObject()->txt('form_header_import'));

		$mail = new \ilCheckboxInputGUI($this->getPluginObject()->txt('notification_mail'), 'is_mail_enabled');
		$mail->setInfo($this->getPluginObject()->txt('notification_mail_info'));
		$mail->setDisabled($disabled);

		$dsDataLink = $this->ctrl->getLinkTarget($this, 'doUserAutoComplete', '', true);
		$recipients = new \ilTextInputGUI($this->getPluginObject()->txt('recipients'), 'recipients');
		$recipients->setRequired(true);
		$recipients->setValue([]);
		$recipients->setDataSource($dsDataLink);
		$recipients->setMaxLength(null);
		$recipients->setMulti(true);
		$recipients->setInfo($this->getPluginObject()->txt('recipients_info'));
		$mail->addSubItem($recipients);
		$recipients->setDisabled($disabled);

		$importDirectory = new \ilTextInputGUI($this->getPluginObject()->txt('import_directory'), 'import_directory');
		$dir = \ilUtil::getDataDir() . DIRECTORY_SEPARATOR . $this->getPluginObject()->getSetting('import_directory');
		$importDirectory->setInfo(sprintf($this->getPluginObject()->txt('import_directory_info'), $dir));
		$importDirectory->setRequired(true);
		$importDirectory->setSize(120);
		$importDirectory->setMaxLength(512);
		$importDirectory->setDisabled($disabled);

		$form->addItem($form_gpg_homedir);
		$form->addItem($form_key_email);
		$form->addItem($form_key_passphrase);
		$form->addItem($form_search_system_url);
		$form->addItem($tokenAppendCrsTitle);
		$form->addItem($tokenAppendToBibItems);
		$form->addItem($accessFormSection);
		$form->addItem($limitToGlobalRoles);
		$form->addItem($importFormSection);
		$form->addItem($mail);
		$form->addItem($importDirectory);

		$form->addCommandButton('saveSettings', $this->lng->txt('save'));
		if ($disabled) {
			$form->addCommandButton('confirmReleaseLock', $this->getPluginObject()->txt('release_lock'));
		}

		return $form;
	}

	/**
	 *
	 */
	protected function saveSettings()
	{
		if ($this->lock->isLocked()) {
			\ilUtil::sendInfo($this->lng->txt('could_not_save_job_prob_runs'), true);
			$this->ctrl->redirect($this);
		}

		$form = $this->getGeneralSettingsForm();
		if ($form->checkInput()) {
			$recipients = (array)$form->getInput('recipients');

			$this->getPluginObject()->setSetting('limit_to_groles', (int)$form->getInput('limit_to_groles'));
			$this->getPluginObject()->setSetting('global_roles', implode(',', (array)$form->getInput('global_roles')));
			$this->getPluginObject()->setSetting('gpg_homedir', $form->getInput('gpg_homedir'));
			$this->getPluginObject()->setSetting('sign_key_email', $form->getInput('sign_key_email'));
			$this->getPluginObject()->setSetting('is_mail_enabled', $form->getInput('is_mail_enabled'));
			$this->getPluginObject()->setSetting('mail_recipients', implode(',', $recipients));
			$this->getPluginObject()->setSetting('import_directory', $form->getInput('import_directory'));

			if ($form->getInput('sign_key_passphrase')) {
				$this->getPluginObject()->setSetting('sign_key_passphrase', $this->encrypter->encrypt($form->getInput('sign_key_passphrase')));
			}

			$this->getPluginObject()->setSetting('url_search_system', $form->getInput('url_search_system'));
			$this->getPluginObject()->setSetting('token_append_obj_title', (int)$form->getInput('token_append_obj_title'));
			$this->getPluginObject()->setSetting('token_append_to_bibl', (int)$form->getInput('token_append_to_bibl'));

			\ilUtil::sendSuccess($this->lng->txt('saved_successfully'), true);
			$this->ctrl->redirect($this);
		}

		$form->setValuesByPost();
		$this->tpl->setContent($form->getHTML());
	}

	/**
	 * 
	 */
	protected function doUserAutoComplete()
	{
		if (!isset($_GET['autoCompleteField'])) {
			$a_fields = array('login', 'firstname', 'lastname', 'email', 'recipients');
			$result_field = 'login';
		} else {
			$a_fields = array((string)$_GET['autoCompleteField']);
			$result_field = (string)$_GET['autoCompleteField'];
		}

		require_once 'Services/User/classes/class.ilUserAutoComplete.php';
		$auto = new \ilUserAutoComplete();
		$auto->setSearchFields($a_fields);
		$auto->setResultField($result_field);
		$auto->enableFieldSearchableCheck(true);
		echo $auto->getList(\ilUtil::stripSlashes($_REQUEST['term']));
		exit();
	}

	/**
	 *
	 */
	protected function confirmReleaseLock()
	{
		require_once 'Services/Utilities/classes/class.ilConfirmationGUI.php';
		$confirmation = new \ilConfirmationGUI();
		$confirmation->setFormAction($this->ctrl->getFormAction($this, 'showConfigurationForm'));
		$confirmation->setConfirm($this->lng->txt('confirm'), 'performReleaseLock');
		$confirmation->setCancel($this->lng->txt('cancel'), 'showConfigurationForm');
		$confirmation->setHeaderText($this->getPluginObject()->txt('sure_release_lock'));

		$this->tpl->setContent($confirmation->getHTML());
	}

	/**
	 *
	 */
	protected function performReleaseLock()
	{
		$this->lock->releaseLock();

		\ilUtil::sendSuccess($this->getPluginObject()->txt('released_lock'), true);
		$this->ctrl->redirect($this, 'showConfigurationForm');
	}
}
