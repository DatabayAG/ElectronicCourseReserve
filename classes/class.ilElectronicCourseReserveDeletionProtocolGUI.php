<?php
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once dirname(__FILE__) . '/class.ilElectronicCourseReserveBaseGUI.php';

/**
 * Class ilElectronicCourseReserveDeletionProtocolGUI
 */
class ilElectronicCourseReserveDeletionProtocolGUI extends ilElectronicCourseReserveBaseGUI
{
    /**
     * @inheritdoc
     */
    protected function getDefaultCommand()
    {
        return 'showProtocol';
    }

    protected function showProtocol() : void
    {
        $this->tpl->setContent('Hello World');
    }
}
