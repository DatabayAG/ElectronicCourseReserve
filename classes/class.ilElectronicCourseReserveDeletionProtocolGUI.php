<?php
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once dirname(__FILE__) . '/class.ilElectronicCourseReserveBaseGUI.php';

/**
 * Class ilElectronicCourseReserveDeletionProtocolGUI
 */
class ilElectronicCourseReserveDeletionProtocolGUI extends ilElectronicCourseReserveBaseGUI
{
    /**
     * @inheritDoc
     */
    public function performCommand($cmd)
    {
        $this->plugin_object->includeClass('UI/Table/Base.php');
        $this->plugin_object->includeClass('UI/Table/Data/Provider.php');
        $this->plugin_object->includeClass('UI/Table/Data/DatabaseProvider.php');
        parent::performCommand($cmd);
    }
    
    /**
     * @inheritdoc
     */
    protected function getDefaultCommand()
    {
        return 'showProtocol';
    }

    /**
     * @return ilElectronicCourseReserveDeletionProtocolTableGUI
     */
    private function getProtocolTable() : ilElectronicCourseReserveDeletionProtocolTableGUI
    {
        $this->plugin_object->includeClass('tables/class.ilElectronicCourseReserveDeletionProtocolTableGUI.php');
        $this->plugin_object->includeClass('tables/provider/DeletionLogTableProvider.php');
        return new ilElectronicCourseReserveDeletionProtocolTableGUI($this, 'showProtocol');
    }

    public function resetFilter()
    {
        $table = $this->getProtocolTable();
        $table->resetOffset();
        $table->resetFilter();

        $this->showProtocol();
    }

    public function applyFilter()
    {
        $table = $this->getProtocolTable();
        $table->resetOffset();
        $table->writeFilterToSession();

        $this->showProtocol();
    }

    protected function showProtocol()
    {
        $this->plugin_object->includeClass('tables/provider/DeletionLogTableProvider.php');
        $table = $this->getProtocolTable();
        $table = $table
            ->withProvider(new DeletionLogTableProvider($GLOBALS['DIC']->database()));
        $table->populate();

        $this->tpl->setContent($table->getHTML());
    }
}
