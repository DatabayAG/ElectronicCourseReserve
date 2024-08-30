<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once __DIR__ . '/class.ilElectronicCourseReserveBaseGUI.php';

/**
 * Class ilElectronicCourseReserveAgreementConfigGUI
 */
class ilElectronicCourseReserveAgreementConfigGUI extends ilElectronicCourseReserveBaseGUI
{
    /**
     * @inheritdoc
     */
    protected function getDefaultCommand()
    {
        return 'showSettings';
    }

    /**
     * @inheritdoc
     */
    protected function showTabs()
    {
        parent::showTabs();

        $this->tabs->addSubTab(
            'showSettings',
            $this->lng->txt('settings'),
            $this->ctrl->getLinkTarget($this, 'showSettings')
        );

        $this->tabs->addSubTab(
            'editUserAgreements',
            $this->getPluginObject()->txt('edit_use_agreement'),
            $this->ctrl->getLinkTarget($this, 'editUserAgreements')
        );
    }

    /**
     * @return ilPropertyFormGUI
     */
    protected function getSettingsForm()
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, 'saveSettings'));
        $form->setTitle($this->lng->txt('settings'));

        $enable_use_agreement = new ilCheckboxInputGUI(
            $this->getPluginObject()->txt('enable_use_agreement'),
            'enable_use_agreement'
        );
        $enable_use_agreement->setValue(1);
        $form->addItem($enable_use_agreement);

        $form->addCommandButton('saveSettings', $this->lng->txt('save'));

        return $form;
    }

    /**
     * @param ilPropertyFormGUI|null $form
     */
    protected function showSettings(ilPropertyFormGUI $form = null)
    {
        $this->tabs->activateSubTab('showSettings');

        if (null === $form) {
            $form = $this->getSettingsForm();
        }

        $form->setValuesByArray([
            'enable_use_agreement' => $this->getPluginObject()->getSetting('enable_use_agreement'),
        ]);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     *
     */
    protected function saveSettings()
    {
        $form = $this->getSettingsForm();
        if ($form->checkInput()) {
            $this->getPluginObject()->setSetting('enable_use_agreement', (int) $form->getInput('enable_use_agreement'));

            ilUtil::sendSuccess($this->lng->txt('saved_successfully'), true);
            $this->ctrl->redirect($this, 'showUseAgreementSettings');
        }

        $form->setValuesByPost();
        $this->showSettings($form);
    }

    /**
     *
     */
    protected function editUserAgreements()
    {
        $this->tabs->activateSubTab('editUserAgreements');

        $button = ilLinkButton::getInstance();
        $button->setCaption($this->getPluginObject()->txt('add_use_agreement'), false);
        $button->setUrl($this->ctrl->getLinkTarget($this, 'showUserAgreementForm'));
        $this->toolbar->addButtonInstance($button);

        $this->getPluginObject()->includeClass('tables/class.ilElectronicCourseReserveAgreementTableGUI.php');
        $this->getPluginObject()->includeClass('tables/class.ilElectronicCourseReserveAgreementTableProvider.php');

        $table = new ilElectronicCourseReserveAgreementTableGUI($this, 'editUserAgreements');
        $provider = new ilElectronicCourseReserveAgreementTableProvider();
        $table->setData($provider->getTableData());

        $this->tpl->setContent($table->getHTML());
    }

    /**
     * @return ilPropertyFormGUI
     */
    protected function getUserAgreementForm()
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, 'saveUserAgreement'));
        $form->setTitle($this->getPluginObject()->txt('add_use_agreement'));

        $installed_langs = $this->lng->getInstalledLanguages();
        $this->lng->loadLanguageModule('meta');
        foreach ($installed_langs as $lang) {
            $lang_options[$lang] = $this->lng->txt('meta_l_' . $lang);
        }

        $lang_select = new ilSelectInputGUI($this->lng->txt('language'), 'lang');
        $lang_select->setOptions($lang_options);
        $form->addItem($lang_select);

        $agreement_input = new ilTextAreaInputGUI($this->getPluginObject()->txt('use_agreement'), 'agreement');
        $agreement_input->setRequired(true);
        $agreement_input->setRows(15);
        $agreement_input->setUseRte(true);

        $agreement_input->removePlugin('advlink');
        $agreement_input->removePlugin('ilimgupload');
        $agreement_input->setRTERootBlockElement('');
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

        $agreement_input->setRTESupport($this->user->getId(), 'ecr_ua', 'ecr_ua');

        $this->getPluginObject()->includeClass('class.ilElectronicCourseReservePostPurifier.php');
        $purifier = new ilElectronicCourseReservePostPurifier();
        $agreement_input->usePurifier(true);
        $agreement_input->setPurifier($purifier);

        $form->addCommandButton('saveUserAgreement', $this->lng->txt('add'));
        $form->addCommandButton('editUserAgreements', $this->lng->txt('cancel'));
        $form->addItem($agreement_input);

        return $form;
    }

    /**
     * @param ilPropertyFormGUI|null $form
     */
    protected function showUserAgreementForm(ilPropertyFormGUI $form = null)
    {
        $this->tabs->activateSubTab('editUserAgreements');

        if (null === $form) {
            $form = $this->getUserAgreementForm();
        }

        $this->tpl->setContent($form->getHTML());
    }

    /**
     *
     */
    protected function saveUserAgreement()
    {
        $form = $this->getUserAgreementForm();
        if ($form->checkInput()) {
            $lang = $form->getInput('lang');
            $agreement_text = $form->getInput('agreement');

            $this->getPluginObject()->includeClass('class.ilElectronicCourseReserveAgreement.php');
            $agreement_obj = new ilElectronicCourseReserveAgreement();
            $agreement_obj->setLang($lang);
            $agreement_obj->setAgreement($agreement_text);
            $agreement_obj->saveAgreement();

            ilUtil::sendSuccess($this->lng->txt('saved_successfully'), true);
            $this->ctrl->redirect($this, 'editUserAgreements');
        }

        $form->setValuesByPost();
        $this->showUserAgreementForm($form);
    }

    /**
     * @param ilPropertyFormGUI|null $form
     */
    protected function editUserAgreement(ilPropertyFormGUI $form = null)
    {
        $this->tabs->activateSubTab('editUserAgreements');

        $language = isset($_GET['ecr_lang']) ? $_GET['ecr_lang'] : '';
        if (null === $form) {
            $form = $this->getUserAgreementForm();
            $this->getUserAgreementValues($form, $language);
        }


        $this->tpl->setContent($form->getHTML());
    }

    /**
     * @param ilPropertyFormGUI $form
     * @param string $language
     */
    protected function getUserAgreementValues(ilPropertyFormGUI $form, $language)
    {
        $this->getPluginObject()->includeClass('class.ilElectronicCourseReserveAgreement.php');
        $use_agreement = new ilElectronicCourseReserveAgreement();
        $use_agreement->loadByLang($language);

        $values['lang'] = $use_agreement->getLang();
        $values['agreement'] = $use_agreement->getAgreement();

        $form->setValuesByArray($values);
    }
}