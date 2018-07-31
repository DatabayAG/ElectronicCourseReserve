<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once dirname(__FILE__) . '/class.ilElectronicCourseReserveBaseGUI.php';

/**
 * Class ilElectronicCourseReserveContentConfigGUI
 */
class ilElectronicCourseReserveContentConfigGUI extends \ilElectronicCourseReserveBaseGUI
{
	/**
	 * @inheritdoc
	 */
	protected function getDefaultCommand()
	{
		return 'showTabTranslationTable';
	}

	/**
	 *
	 */
	protected function showTabTranslationTable()
	{
		$this->getPluginObject()->includeClass('tables/class.ilElectronicCourseReserveLangTableGUI.php');
		$this->getPluginObject()->includeClass('tables/class.ilElectronicCourseReserveLangTableProvider.php');

		$table = new \ilElectronicCourseReserveLangTableGUI($this, 'showTabTranslationTable');
		$provider = new \ilElectronicCourseReserveLangTableProvider();
		$table->setData($provider->getTableData());

		$this->tpl->setContent($table->getHTML());
	}

	/**
	 *
	 */
	protected function savTabTranslationsVars()
	{
		$translationData = new \ilElectronicCourseReserveLangData();

		$installed_langs = \ilLanguage::_getInstalledLanguages();
		foreach ($installed_langs as $lang) {
			if (isset($_POST[$lang])) {
				$translationData->setLangKey($lang);
				$translationData->setValue(trim(\ilUtil::stripSlashes($_POST[$lang])));
				$translationData->saveTranslation();
			}
		}

		\ilUtil::sendSuccess($this->lng->txt('saved_successfully'));
		$this->showTabTranslationTable();
	}

	/**
	 * @param ilPropertyFormGUI|null $form
	 */
	protected function editContent(\ilPropertyFormGUI $form = null)
	{
		if (!isset($_GET['ecr_lang'])) {
			\ilUtil::sendFailure($this->lng->txt('obj_not_found'), true);
			$this->ctrl->redirect($this, 'showTabTranslationTable');
			return;
		}

		$lang_key = trim($_GET['ecr_lang']);
		$lang_obj_id = \ilElectronicCourseReserveLangData::lookupObjIdByLangKey($lang_key);
		if (!$lang_obj_id) {
			\ilUtil::sendFailure($this->lng->txt('obj_not_found'), true);
			$this->ctrl->redirect($this, 'showTabTranslationTable');
		}

		if (null === $form) {
			$form = $this->getContentForm();
		}

		$ecr_content = \ilElectronicCourseReserveLangData::lookupEcrContentByLangKey($lang_key);
		$content = \ilRTE::_replaceMediaObjectImageSrc($ecr_content, 1);

		$form->setValuesByArray(array(
			'ecr_content' => $content,
			'ecr_lang' => $lang_key
		));

		$this->tpl->setContent($form->getHTML());
	}

	/**
	 *
	 */
	protected function saveContent()
	{
		$form = $this->getContentForm();
		$form->checkInput();

		$content = \ilRTE::_replaceMediaObjectImageSrc($form->getInput('ecr_content'), 0);

		$this->getPluginObject()->includeClass('class.ilElectronicCourseReserveRTEHelper.php');

		$lang_key = $form->getInput('ecr_lang');
		$lang_obj_id = \ilElectronicCourseReserveLangData::lookupObjIdByLangKey($lang_key);

		\ilElectronicCourseReserveRTEHelper::moveMediaObjects($lang_obj_id, $form->getInput('ecr_content'),
			'ecr_content~:html', 'ecr_content:html');

		require_once 'Services/MediaObjects/classes/class.ilObjMediaObject.php';
		$oldMediaObjects = \ilObjMediaObject::_getMobsOfObject('ecr_content:html', $lang_obj_id);
		$curMediaObjects = \ilRTE::_getMediaObjects($form->getInput('ecr_content'), 0);
		foreach ($oldMediaObjects as $oldMob) {
			$found = false;

			foreach ($curMediaObjects as $curMob) {
				if ($oldMob == $curMob) {
					$found = true;
					break;
				}
			}

			if (!$found) {
				if (\ilObjMediaObject::_exists($oldMob)) {
					\ilObjMediaObject::_removeUsage($oldMob, 'ecr_content:html', $lang_obj_id);
					$mob_obj = new \ilObjMediaObject($oldMob);
					$mob_obj->delete();
				}
			}
		}

		\ilElectronicCourseReserveLangData::writeEcrContent($lang_key, $content);

		\ilUtil::sendSuccess($this->lng->txt('saved_successfully'), true);
		$this->ctrl->setParameter($this, 'ecr_lang', $lang_key);
		$this->ctrl->redirect($this, 'editContent');
	}

	/**
	 * @return \ilPropertyFormGUI
	 */
	protected function getContentForm()
	{
		require_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new \ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this, 'saveContent'));
		$form->setTitle($this->getPluginObject()->txt('edit_ecr_content') . ': ' . $this->lng->txt('meta_l_' . $_GET['ecr_lang']));

		$ecr_content_input = new \ilTextAreaInputGUI($this->getPluginObject()->txt('ecr_content'), 'ecr_content');
		$ecr_content_input->setRequired(true);
		$ecr_content_input->setRows(15);
		$ecr_content_input->setUseRte(true);

		$ecr_content_input->removePlugin('advlink');
		$ecr_content_input->setRTERootBlockElement('');
		$ecr_content_input->disableButtons(array(
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

		$ecr_content_input->setRTESupport($this->user->getId(), 'ecr_content', 'ecr_content');
		$ecr_content_input->setInfo($this->getPluginObject()->txt('insert_url_esa_info'));

		$this->getPluginObject()->includeClass('class.ilElectronicCourseReservePostPurifier.php');
		$purifier = new \ilElectronicCourseReservePostPurifier();
		$ecr_content_input->usePurifier(true);
		$ecr_content_input->setPurifier($purifier);

		$ecr_lang = new \ilHiddenInputGUI('ecr_lang');
		if (isset($_GET['ecr_lang'])) {
			$ecr_lang->setValue($_GET['ecr_lang']);
		}

		$form->addItem($ecr_lang);
		$form->addItem($ecr_content_input);

		$form->addCommandButton('saveContent', $this->lng->txt('save'));
		$form->addCommandButton('showTabTranslationTable', $this->lng->txt('cancel'));

		return $form;
	}
}