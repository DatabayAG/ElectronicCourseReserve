<?php
include_once 'Services/Table/classes/class.ilTable2GUI.php';
require_once 'Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php';

/**
 * Class ilElectronicCourseReserveDeletionProtocolTableGUI
 */
class ilElectronicCourseReserveDeletionProtocolTableGUI extends ilTable2GUI
{
    /**
     * ilElectronicCourseReserveDeletionProtocolTableGUI constructor.
     * @param $a_parent_obj
     * @param string $a_parent_cmd
     */
    public function __construct($a_parent_obj, string $a_parent_cmd)
    {
        $this->setId('tbl_ecr_deletion_protocol');
        parent::__construct($a_parent_obj, $a_parent_cmd, '');

        $this->setTitle($this->parent_obj->getPluginObject()->txt('adm_ecr_tab_del_procotol'));

        $this->setRowTemplate($a_parent_obj->getPluginObject()->getDirectory() . '/templates/tpl.row_deletion_protocol.html');
    }

    /**
     * @inheritDoc
     */
    public function fillRow($a_set)
    {
        parent::fillRow($a_set);
    }
}
