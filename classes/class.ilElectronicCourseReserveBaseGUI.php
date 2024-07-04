<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\Filesystem\Filesystem;
use ILIAS\HTTP\Wrapper\WrapperFactory;
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
    protected Filesystem $filesystem;
    private WrapperFactory $httpWrapper;
    private \ILIAS\Refinery\Factory $refinery;

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
        $this->filesystem = $DIC->filesystem()->storage();
        $this->httpWrapper = $DIC->http()->wrapper();
        $this->refinery = $DIC->refinery();

        $this->lng->loadLanguageModule('meta');
    }

    /**
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'ctype', $this->httpWrapper->query()->retrieve('ctype', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'cname', $this->httpWrapper->query()->retrieve('cname', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'slot_id', $this->httpWrapper->query()->retrieve('slot_id', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'plugin_id', $this->httpWrapper->query()->retrieve('plugin_id', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass(strtolower(get_class($this)), 'pname', $this->httpWrapper->query()->retrieve('pname', $this->refinery->kindlyTo()->string()));

        $this->tpl->setTitle($this->lng->txt('cmps_plugin') . ': ' . $this->httpWrapper->query()->retrieve('pname', $this->refinery->kindlyTo()->string()));
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

        $this->ctrl->setParameterByClass('ilobjcomponentsettingsgui', 'ctype', $this->httpWrapper->query()->retrieve('ctype', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass('ilobjcomponentsettingsgui', 'cname', $this->httpWrapper->query()->retrieve('cname', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass('ilobjcomponentsettingsgui', 'slot_id',  $this->httpWrapper->query()->retrieve('slot_id', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass('ilobjcomponentsettingsgui', 'plugin_id', $this->httpWrapper->query()->retrieve('plugin_id', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass('ilobjcomponentsettingsgui', 'pname', $this->httpWrapper->query()->retrieve('pname', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'ctype', $this->httpWrapper->query()->retrieve('ctype', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'cname', $this->httpWrapper->query()->retrieve('cname', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'slot_id',  $this->httpWrapper->query()->retrieve('slot_id', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'plugin_id', $this->httpWrapper->query()->retrieve('plugin_id', $this->refinery->kindlyTo()->string()));
        $this->ctrl->setParameterByClass('ilElectronicCourseReserveConfigGUI', 'pname', $this->httpWrapper->query()->retrieve('pname', $this->refinery->kindlyTo()->string()));

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
        if ($this->httpWrapper->query()->has('plugin_id') && $this->httpWrapper->query()->retrieve('plugin_id', $this->refinery->kindlyTo()->string())) {
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