<?php

/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;
use JetBrains\PhpStorm\NoReturn;

require_once dirname(__FILE__) . '/class.ilElectronicCourseReserveBaseGUI.php';

/**
 * Class ilElectronicCourseReserveDeletionProtocolGUI
 */
class ilElectronicCourseReserveDeletionProtocolGUI extends ilElectronicCourseReserveBaseGUI
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
        return 'showProtocol';
    }

    /**
     * @return ilElectronicCourseReserveDeletionProtocolTableGUI
     * @throws ilCtrlException
     * @throws ilException
     */
    private function getProtocolTable(): ilElectronicCourseReserveDeletionProtocolTableGUI
    {
        return new ilElectronicCourseReserveDeletionProtocolTableGUI($this, 'showProtocol');
    }

    /**
     * @throws ilException
     * @throws ilCtrlException
     */
    public function resetFilter(): void
    {
        $table = $this->getProtocolTable();
        $table->resetOffset();
        $table->resetFilter();

        $this->showProtocol();
    }

    /**
     * @throws ilException
     * @throws ilCtrlException
     */
    public function applyFilter(): void
    {
        $table = $this->getProtocolTable();
        $table->resetOffset();
        $table->writeFilterToSession();

        $this->showProtocol();
    }


    #[NoReturn] protected function fetchCourseTitleAutocompletionResults(): void
    {
        $term = $this->httpWrapper->query()->has('term') ? $this->httpWrapper->query()->retrieve('term', $this->refinery->kindlyTo()->string()) : "";
        $p = new DeletionLogTableProvider($GLOBALS['DIC']->database());
        $crsTitles = $p->getListOfLoggedObjectTitles(
            ilUtil::stripSlashes($term),
            'crs'
        );

        echo json_encode($crsTitles);
        exit();
    }


    #[NoReturn] protected function fetchFolderTitleAutocompletionResults(): void
    {
        $term = $this->httpWrapper->query()->has('term') ? $this->httpWrapper->query()->retrieve('term', $this->refinery->kindlyTo()->string()) : "";
        $p = new DeletionLogTableProvider($GLOBALS['DIC']->database());
        $crsTitles = $p->getListOfLoggedObjectTitles(
            ilUtil::stripSlashes($term),
            'fold'
        );

        echo json_encode($crsTitles);
        exit();
    }

    /**
     * @throws ilException
     * @throws ilCtrlException
     */
    protected function showProtocol(): void
    {
        $table = $this->getProtocolTable();
        $table = $table
            ->withProvider(new DeletionLogTableProvider($GLOBALS['DIC']->database()));
        $table->populate();

        $this->tpl->setContent($table->getHTML());
    }
}
