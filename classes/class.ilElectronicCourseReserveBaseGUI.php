<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use Zend\Crypt\BlockCipher;

/**
 * @author Michael Jansen <mjansen@databay.de>
 */
abstract class ilElectronicCourseReserveBaseGUI extends ilPluginConfigGUI
{
    /** @var ilCtrl */
    protected $ctrl;

    /** @var ilTabsGUI */
    protected $tabs;

    /** @var ilLanguage */
    protected $lng;

    /** @var ilTemplate */
    protected $tpl;

    /** @var ilObjUser */
    protected $user;

    /** @var ilToolbarGUI */
    protected $toolbar;

    /** @var ilObjectDataCache */
    protected $objectCache;

    /** @var ilRbacReview */
    public $rbacreview;

    /** @var ilSetting */
    public $settings;

    /** @var ILIAS\Plugin\ElectronicCourseReserve\Locker\LockerInterface */
    protected $lock;

    /** @var BlockCipher */
    protected $encrypter;

    /** @var BlockCipher $symmetric */
    protected $plugin_object;

    /** @var Factory */
    protected $uiFactory;

    /** @var Renderer */
    protected $uiRenderer;

    /** @var ilLogger */
    protected $log;

    /**
     * ilCourseBookingDecisionMakerGUI constructor.
     * @param ilElectronicCourseReservePlugin $plugin
     */
    public function __construct(ilElectronicCourseReservePlugin $plugin = null)
    {
        global $DIC;

        if (null === $plugin) {
            $plugin = ilPlugin::getPluginObject('Services', 'UIComponent', 'uihk', 'ElectronicCourseReserve');
        }
        $this->plugin_object = $plugin;

        $this->plugin_object->includeClass('class.ilElectronicCourseReserveLangData.php');

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
    public function executeCommand()
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
     */
    protected function showTabs()
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
    }

    /**
     *
     */
    protected function showBackTargetTab()
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
    public function performCommand($cmd)
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
    abstract protected function getDefaultCommand();
}