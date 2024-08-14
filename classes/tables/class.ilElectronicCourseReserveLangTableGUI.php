<?php

/**
 * Class ilElectronicCourseReserveLangTableGUI
 */
class ilElectronicCourseReserveLangTableGUI extends ilTable2GUI
{
    /**
     * @var ilCtrl
     */
    protected ilCtrl $ctrl;

    /**
     * @inheritdoc
     * @throws ilException
     */
    public function __construct($a_parent_obj, $a_parent_cmd = "", array $a_params = array())
    {
        global $ilCtrl, $lng;

        $this->ctrl = $ilCtrl;

        $this->setId('esa_crs_lang_adm');
        parent::__construct($a_parent_obj, $a_parent_cmd);

        $lng->loadLanguageModule('meta');
        $this->setTitle($a_parent_obj->getPluginObject()->txt('adm_ecr_tab_title'));
        $this->setDescription($a_parent_obj->getPluginObject()->txt('ecr_contents_adm_tbl_head'));
        $this->setRowTemplate($a_parent_obj->getPluginObject()->getDirectory() . '/templates/tpl.lang_items_row.html');
        $this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
        $this->setDisableFilterHiding();

        $this->addColumn($lng->txt('language'), 'lang_key', '30%');
        $this->addColumn(ilElectronicCourseReservePlugin::getInstance()->txt('tab_translation_value'), 'value', '60%');
        $this->addColumn($lng->txt('actions'), '', '10%');
        $this->addCommandButton('saveTabTranslationsVars', $lng->txt('save'));
    }


    /**
     * @inheritdoc
     * @throws ilCtrlException
     * @throws JsonException
     */
    protected function fillRow($a_set): void
    {
        $field = new ilTextInputGUI('', $a_set['lang_key']);
        $field->setValue($a_set['value'] ?? '');

        $this->tpl->setVariable('LANG_KEY', ilLegacyFormElementsUtil::prepareFormOutput($this->lng->txt('meta_l_' . $a_set['lang_key'])));
        $this->tpl->setVariable('TRANSLATION_FIELD', $field->getToolbarHTML());

        $actions = new ilAdvancedSelectionListGUI();
        $actions->setId('action' . $a_set['lang_key']);
        $actions->setListTitle($this->lng->txt('actions'));

        $this->ctrl->setParameter($this->parent_obj, 'ecr_lang', $a_set['lang_key']);
        $edit_url = $this->ctrl->getLinkTarget($this->parent_obj, 'editContent');
        $actions->addItem($this->parent_obj->getPluginObject()->txt('edit_ecr_content'), '', $edit_url);
        $this->tpl->setVariable('ACTIONS', $actions->getHTML());
    }
}
