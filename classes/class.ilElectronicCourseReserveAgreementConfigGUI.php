<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;

require_once __DIR__ . '/class.ilElectronicCourseReserveBaseGUI.php';

/**
 * Class ilElectronicCourseReserveAgreementConfigGUI
 */
class ilElectronicCourseReserveAgreementConfigGUI extends ilElectronicCourseReserveBaseGUI
{

    private WrapperFactory $httpWrapper;
    private Factory $refinery;

    public function __construct(?ilElectronicCourseReservePlugin $plugin = null)
    {
        parent::__construct($plugin);
        global $DIC;
        $this->httpWrapper = $DIC->http()->wrapper();
        $this->refinery = $DIC->refinery();
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultCommand(): string
    {
        return 'showSettings';
    }

    /**
     * @inheritdoc
     * @throws ilCtrlException
     */
    protected function showTabs(): void
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
     * @throws ilCtrlException
     */
    protected function getSettingsForm(): ilPropertyFormGUI
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
     * @throws ilCtrlException
     */
    protected function showSettings(ilPropertyFormGUI $form = null): void
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
     * @throws ilCtrlException
     */
    protected function saveSettings(): void
    {
        $form = $this->getSettingsForm();
        if ($form->checkInput()) {
            $this->getPluginObject()->setSetting('enable_use_agreement', (int) $form->getInput('enable_use_agreement'));

            $this->tpl->setOnScreenMessage("success", $this->lng->txt('saved_successfully'), true);
            $this->ctrl->redirect($this, 'showUseAgreementSettings');
        }

        $form->setValuesByPost();
        $this->showSettings($form);
    }

    /**
     *
     * @throws ilCtrlException|ilException
     */
    protected function editUserAgreements(): void
    {
        $this->tabs->activateSubTab('editUserAgreements');

        $button = ilLinkButton::getInstance();
        $button->setCaption($this->getPluginObject()->txt('add_use_agreement'), false);
        $button->setUrl($this->ctrl->getLinkTarget($this, 'showUserAgreementForm'));
        $this->toolbar->addButtonInstance($button);

        $table = new ilElectronicCourseReserveAgreementTableGUI($this, 'editUserAgreements');
        $provider = new ilElectronicCourseReserveAgreementTableProvider();
        $table->setData($provider->getTableData());

        $this->tpl->setContent($table->getHTML());
    }

    /**
     * @return ilPropertyFormGUI
     * @throws ilCtrlException
     */
    protected function getUserAgreementForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, 'saveUserAgreement'));
        $form->setTitle($this->getPluginObject()->txt('add_use_agreement'));

        $installed_langs = $this->lng->getInstalledLanguages();
        $this->lng->loadLanguageModule('meta');
        $lang_options = [];
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
     * @throws ilCtrlException
     */
    protected function showUserAgreementForm(ilPropertyFormGUI $form = null): void
    {
        $this->tabs->activateSubTab('editUserAgreements');

        if (null === $form) {
            $form = $this->getUserAgreementForm();
        }

        $this->tpl->setContent($form->getHTML());
    }

    /**
     *
     * @throws ilCtrlException
     */
    protected function saveUserAgreement(): void
    {
        $form = $this->getUserAgreementForm();
        if ($form->checkInput()) {
            $lang = $form->getInput('lang');
            $agreement_text = $form->getInput('agreement');

            $agreement_obj = new ilElectronicCourseReserveAgreement();
            $agreement_obj->setLang($lang);
            $agreement_obj->setAgreement($agreement_text);
            $agreement_obj->saveAgreement();

            $this->tpl->setOnScreenMessage("success", $this->lng->txt('saved_successfully'), true);
            $this->ctrl->redirect($this, 'editUserAgreements');
        }

        $form->setValuesByPost();
        $this->showUserAgreementForm($form);
    }

    /**
     * @param ilPropertyFormGUI|null $form
     * @throws ilCtrlException
     */
    protected function editUserAgreement(ilPropertyFormGUI $form = null): void
    {
        $this->tabs->activateSubTab('editUserAgreements');

        if($this->httpWrapper->query()->has('ecr_lang')){
            $language = $this->httpWrapper->query()->retrieve('ecr_lang', $this->refinery->kindlyTo()->string());
        } else {
            $language = '';
        }
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
    protected function getUserAgreementValues(ilPropertyFormGUI $form, string $language): void
    {
        $use_agreement = new ilElectronicCourseReserveAgreement();
        $use_agreement->loadByLang($language);

        $values['lang'] = $use_agreement->getLang();
        $values['agreement'] = $use_agreement->getAgreement();

        $form->setValuesByArray($values);
    }
}