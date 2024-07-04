<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;

require_once __DIR__ . '/class.ilElectronicCourseReserveBaseGUI.php';

/**
 * Class ilElectronicCourseReserveContentConfigGUI
 */
class ilElectronicCourseReserveContentConfigGUI extends ilElectronicCourseReserveBaseGUI
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
        return 'showTabTranslationTable';
    }

    /**
     *
     * @throws ilException
     */
    protected function showTabTranslationTable(): void
    {
        $table = new ilElectronicCourseReserveLangTableGUI($this, 'showTabTranslationTable');
        $provider = new ilElectronicCourseReserveLangTableProvider();
        $table->setData($provider->getTableData());

        $this->tpl->setContent($table->getHTML());
    }

    /**
     *
     * @throws ilException
     */
    protected function saveTabTranslationsVars(): void
    {
        $translationData = new ilElectronicCourseReserveLangData();

        $installed_langs = ilLanguage::_getInstalledLanguages();
        foreach ($installed_langs as $lang) {
            if ($this->httpWrapper->post()->has($lang)) {
                $translationData->setLangKey($lang);
                $translationData->setValue(trim(ilUtil::stripSlashes($this->httpWrapper->post()->retrieve($lang, $this->refinery->kindlyTo()->string()))));
                $translationData->saveTranslation();
            }
        }

        $this->tpl->setOnScreenMessage("success", $this->lng->txt('saved_successfully'));
        $this->showTabTranslationTable();
    }

    /**
     * @param ilPropertyFormGUI|null $form
     * @throws ilCtrlException
     */
    protected function editContent(ilPropertyFormGUI $form = null): void
    {
        if (!$this->httpWrapper->query()->has('ecr_lang')) {
            $this->tpl->setOnScreenMessage("failure", $this->lng->txt('obj_not_found'), true);
            $this->ctrl->redirect($this, 'showTabTranslationTable');
            return;
        }

        $lang_key = trim($this->httpWrapper->query()->retrieve('ecr_lang', $this->refinery->kindlyTo()->string()));
        $lang_obj_id = ilElectronicCourseReserveLangData::lookupObjIdByLangKey($lang_key);
        if (!$lang_obj_id) {
            $this->tpl->setOnScreenMessage("failure", $this->lng->txt('obj_not_found'), true);
            $this->ctrl->redirect($this, 'showTabTranslationTable');
        }

        if (null === $form) {
            $form = $this->getContentForm();
        }

        $ecr_content = ilElectronicCourseReserveLangData::lookupEcrContentByLangKey($lang_key);
        $content = ilRTE::_replaceMediaObjectImageSrc($ecr_content, 1);

        $form->setValuesByArray(array(
            'ecr_content' => (strlen($content) > 0 ? $content : '###URL_ESA###'),
            'ecr_lang' => $lang_key
        ));

        $this->tpl->setContent($form->getHTML());
    }

    /**
     *
     * @throws ilCtrlException
     */
    protected function saveContent(): void
    {
        $form = $this->getContentForm();
        $form->checkInput();

        $content = ilRTE::_replaceMediaObjectImageSrc($form->getInput('ecr_content'));

        $lang_key = $form->getInput('ecr_lang');
        $lang_obj_id = ilElectronicCourseReserveLangData::lookupObjIdByLangKey($lang_key);

        ilElectronicCourseReserveRTEHelper::moveMediaObjects($lang_obj_id, $form->getInput('ecr_content'),
            'ecr_content~:html', 'ecr_content:html');

        $oldMediaObjects = ilObjMediaObject::_getMobsOfObject('ecr_content:html', $lang_obj_id);
        $curMediaObjects = ilRTE::_getMediaObjects($form->getInput('ecr_content'));
        foreach ($oldMediaObjects as $oldMob) {
            $found = false;

            foreach ($curMediaObjects as $curMob) {
                if ($oldMob == $curMob) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                if (ilObjMediaObject::_exists($oldMob)) {
                    ilObjMediaObject::_removeUsage($oldMob, 'ecr_content:html', $lang_obj_id);
                    $mob_obj = new ilObjMediaObject($oldMob);
                    $mob_obj->delete();
                }
            }
        }

        ilElectronicCourseReserveLangData::writeEcrContent($lang_key, $content);

        $this->tpl->setOnScreenMessage("success", $this->lng->txt('saved_successfully'), true);
        $this->ctrl->setParameter($this, 'ecr_lang', $lang_key);
        $this->ctrl->redirect($this, 'editContent');
    }

    /**
     * @return ilPropertyFormGUI
     * @throws ilCtrlException
     */
    protected function getContentForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, 'saveContent'));
        $lang = $this->httpWrapper->query()->has('ecr_lang') ? $this->httpWrapper->query()->retrieve('ecr_lang', $this->refinery->kindlyTo()->string()) : "";
        $form->setTitle($this->getPluginObject()->txt('edit_ecr_content') . ': ' . $this->lng->txt('meta_l_' . $lang));

        $ecr_content_input = new ilTextAreaInputGUI($this->getPluginObject()->txt('ecr_content'), 'ecr_content');
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

        $purifier = new ilElectronicCourseReservePostPurifier();
        $ecr_content_input->usePurifier(true);
        $ecr_content_input->setPurifier($purifier);

        $ecr_lang = new ilHiddenInputGUI('ecr_lang');
        if ($this->httpWrapper->query()->has('ecr_lang')) {
            $ecr_lang->setValue($this->httpWrapper->query()->retrieve('ecr_lang', $this->refinery->kindlyTo()->string()));
        }

        $form->addItem($ecr_lang);
        $form->addItem($ecr_content_input);

        $form->addCommandButton('saveContent', $this->lng->txt('save'));
        $form->addCommandButton('showTabTranslationTable', $this->lng->txt('cancel'));

        return $form;
    }
}