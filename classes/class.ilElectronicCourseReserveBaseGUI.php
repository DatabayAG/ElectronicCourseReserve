<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use Laminas\Crypt\BlockCipher;

/**
 * @author Michael Jansen <mjansen@databay.de>
 */
abstract class ilElectronicCourseReserveBaseGUI extends ilPluginConfigGUI
{
    protected ilCtrl|ilCtrlInterface $ctrl;

    protected ilTabsGUI $tabs;

    protected ilLanguage $lng;

    protected ilTemplate|ilGlobalTemplateInterface $tpl;

    protected ilObjUser $user;

    protected ilToolbarGUI $toolbar;

    protected ilObjectDataCache $objectCache;

    public ilRbacReview $rbacreview;

    public ilSetting $settings;

    protected ILIAS\Plugin\ElectronicCourseReserve\Locker\LockerInterface $lock;

    protected BlockCipher $encrypter;
    protected ?ilPlugin $plugin_object;

    protected Factory $uiFactory;

    protected Renderer $uiRenderer;

    protected ilLogger $log;

    /**
     * ilCourseBookingDecisionMakerGUI constructor.
     */
    public function __construct(?ilElectronicCourseReservePlugin $plugin = null)
    {
        global $DIC;

        if (null === $plugin) {
            $plugin = ilElectronicCourseReservePlugin::getInstance();
        }
        $this->plugin_object = $plugin;

        $this->tabs = $DIC->tabs();
        $this->ctrl = $DIC->ctrl();
        $this->log = $DIC->logger()->root();
        $this->lng = $DIC->language();
        $this->toolbar = $DIC->toolbar();
        $this->user = $DIC->user();
        $this->rbacreview = $DIC->rbac()->review();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->settings = $DIC['ilSetting'];
        $this->uiFactory = $DIC->ui()->factory();
        $this->uiRenderer = $DIC->ui()->renderer();
        $this->lock = $DIC['plugin.esa.locker'];
        $this->encrypter = $DIC['plugin.esa.crypt.blockcipher'];
        $this->objectCache = $DIC['ilObjDataCache'];

        $this->lng->loadLanguageModule('meta');
    }

    /**
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'ctype', $_GET['ctype']);
        $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'cname', $_GET['cname']);
        $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'slot_id', $_GET['slot_id']);
        $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'plugin_id', $_GET['plugin_id']);
        $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'pname', $_GET['pname']);

        $this->tpl->setTitle($this->lng->txt('cmps_plugin') . ': ' . $_GET['pname']);
        $this->tpl->setDescription('');

        $this->showTabs();
        $this->performCommand($this->ctrl->getCmd());
    }

    /**
     *
     * @throws ilCtrlException
     */
    protected function showTabs(): void
    {
        $this->tabs->clearTargets();

        $this->ctrl->setParameterByClass('ilobjcomponentsettingsgui', 'ctype', $_GET['ctype']);
        $this->ctrl->setParameterByClass('ilobjcomponentsettingsgui', 'cname', $_GET['cname']);
        $this->ctrl->setParameterByClass('ilobjcomponentsettingsgui', 'slot_id', $_GET['slot_id']);
        $this->ctrl->setParameterByClass('ilobjcomponentsettingsgui', 'plugin_id', $_GET['plugin_id']);
        $this->ctrl->setParameterByClass('ilobjcomponentsettingsgui', 'pname', $_GET['pname']);
        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'ctype', $_GET['ctype']);
        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'cname', $_GET['cname']);
        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'slot_id', $_GET['slot_id']);
        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'plugin_id', $_GET['plugin_id']);
        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'pname', $_GET['pname']);

        $this->showBackTargetTab();

        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'id', '');

        $this->tabs->addTarget(
            'settings', $this->ctrl->getLinkTargetByClass('ilElectronicCourseReserveConfigGUI'),
            '', ['ilElectronicCourseReserveConfigGUI', 'ilelectroniccoursereserveconfiggui', 'ilfilesystemgui']
        );
        $this->tabs->addTarget(
            'ui_uihk_ecr_use_agreement',
            $this->ctrl->getLinkTargetByClass('ilElectronicCourseReserveAgreementConfigGUI'),
            '', 'ilElectronicCourseReserveAgreementConfigGUI'
        );
        $this->tabs->addTarget(
            'ui_uihk_ecr_adm_ecr_tab_title',
            $this->ctrl->getLinkTargetByClass('ilElectronicCourseReserveContentConfigGUI'),
            '', 'ilElectronicCourseReserveContentConfigGUI'
        );
        $this->tabs->addTarget(
            'ui_uihk_ecr_adm_ecr_tab_del_protocol',
            $this->ctrl->getLinkTargetByClass(ilElectronicCourseReserveDeletionProtocolGUI::class),
            '', ilElectronicCourseReserveDeletionProtocolGUI::class
        );
    }

    /**
     *
     * @throws ilCtrlException
     */
    protected function showBackTargetTab(): void
    {
        if (isset($_GET['plugin_id']) && $_GET['plugin_id']) {
            $this->tabs->setBackTarget(
                $this->lng->txt('cmps_plugin'),
                $this->ctrl->getLinkTargetByClass('ilobjcomponentsettingsgui', 'showPlugin')
            );
        } else {
            $this->tabs->setBackTarget(
                $this->lng->txt('cmps_plugins'),
                $this->ctrl->getLinkTargetByClass('ilobjcomponentsettingsgui', 'listPlugins')
            );
        }
    }

    /**
     * @param string $cmd
     */
    public function performCommand(string $cmd): void
    {
        switch (true) {
            case method_exists($this, $cmd):
                $this->$cmd();
                break;

            default:
                $this->{$this->getDefaultCommand()}();
                break;
        }
    }

    /**
     * @return string
     */
    abstract protected function getDefaultCommand(): string;
}