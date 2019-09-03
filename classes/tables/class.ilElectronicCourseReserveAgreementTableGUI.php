<?php
include_once 'Services/Table/classes/class.ilTable2GUI.php';
require_once 'Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php';

/**
 * Class ilElectronicCourseReserveAgreementTableGUI
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilElectronicCourseReserveAgreementTableGUI extends ilTable2GUI
{
    public $ctrl;

    public $tpl;

    public function __construct($a_parent_obj, $a_parent_cmd = "", $a_template_context = "")
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->lng = $DIC->language();

        $this->setId('tbl_ecr_use_agreement');

        parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);

        $this->setTitle($this->parent_obj->getPluginObject()->txt('use_agreements'));

        $this->addColumn($this->lng->txt('language'), 'lang');
        $this->addColumn($this->lng->txt('actions'), 'actions', '10%');
        $this->setRowTemplate($a_parent_obj->getPluginObject()->getDirectory() . '/templates/tpl.row_use_agreement.html');
    }

    /**
     * @param array $a_set
     */
    public function fillRow($a_set)
    {
        $actions = new ilAdvancedSelectionListGUI();
        $actions->setId('action' . $a_set['lang']);
        $actions->setListTitle($this->lng->txt('actions'));

        $this->ctrl->setParameter($this->parent_obj, 'ecr_lang', $a_set['lang']);
        $edit_url = $this->ctrl->getLinkTarget($this->parent_obj, 'editUserAgreement');
        $actions->addItem($this->lng->txt('edit'), '', $edit_url);

        $a_set['lang'] = $this->lng->txt('meta_l_' . $a_set['lang']);

        parent::fillRow($a_set);

        $this->tpl->setVariable('ACTIONS', $actions->getHTML());
        $this->tpl->parseCurrentBlock();
    }
}