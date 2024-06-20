<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\Container;

/**
 * Class ilElectronicCourseReserveUIHookGUI
 * @author Nadia Matuschek <nmatuschek@databay.de>
 *
 * @ilCtrl_isCalledBy ilElectronicCourseReserveUIHookGUI: ilObjPluginDispatchGUI, ilRepositoryGUI, ilPersonalDesktopGUI
 * @ilCtrl_Calls ilElectronicCourseReserveUIHookGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_isCalledBy ilElectronicCourseReserveUIHookGUI: ilUIPluginRouterGUI
 */
class ilElectronicCourseReserveUIHookGUI extends ilUIHookPluginGUI
{
    private Container $dic;

    /** @var ilECRBaseModifier[]|null */
    protected static ?array $modifier = null;
    protected static array $tabsRendered = [];

    /**
     * ilServicePortalUserInterfaceUIHookGUI constructor.
     */
    public function __construct()
    {
        global $DIC;

        $this->dic = $DIC;
    }

    /**
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        $this->dic->ui()->mainTemplate()->loadStandardTemplate();

        $this->dic->ctrl()->saveParameter($this, 'ref_id');

        $dispatcher = ilECRCommandDispatcher::getInstance($this);
        $response = $dispatcher->dispatch($this->dic->ctrl()->getCmd());
        $this->dic->ui()->mainTemplate()->setContent($response);
        $this->dic->ui()->mainTemplate()->printToStdout();

    }

    /**
     * @inheritdoc
     */
    public function getHTML($a_comp, $a_part, $a_par = array()): array
    {
        global $DIC;

        $ilUser = $DIC->user();
        $ilAccess = $DIC->access();

        if ($a_part !== 'template_get' && !(isset($a_par['tpl_id']) && $a_par['tpl_id'] === 'tpl.main.html')) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $plugin = ilElectronicCourseReservePlugin::getInstance();

        $ref_id = (int) $_GET['ref_id'];
        if ($plugin->isFolderRelevant($ref_id)) {
            $plugin->queryFolderData($ref_id);
        }

        /**
         * @var $modifier ilECRBaseModifier
         */
        $this->initModifier();
        if (is_array(self::$modifier)) {
            foreach (self::$modifier as $modifier) {
                if ($modifier->shouldModifyHtml($a_comp, $a_part, $a_par)) {
                    return $modifier->modifyHtml($a_comp, $a_part, $a_par);
                }
            }
        }

        return parent::getHTML($a_comp, $a_part, $a_par);
    }

    /**
     * @inheritdoc
     * @param $a_comp
     * @param $a_part
     * @param array $a_par
     * @throws ilCtrlException
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     */
    public function modifyGUI($a_comp, $a_part, array $a_par = array()): void
    {
        global $DIC;

        $isAdminContext = !isset($_GET['baseClass']) || strtolower($_GET['baseClass']) === 'iladministrationgui';

        if (!$isAdminContext && !isset($_GET['pluginCmd']) && 'tabs' == $a_part && isset($_GET['ref_id'])) {
            $ilCtrl = $DIC->ctrl();
            $ilAccess = $DIC->access();
            $ilUser = $DIC->user();

            $this->getPluginObject()->loadLanguageModule();

            $ref_id = (int) $_GET['ref_id'];
            $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);
            if ($obj instanceof ilObjCourse &&
                $ilAccess->checkAccess('read', '', $obj->getRefId()) &&
                $this->getPluginObject()->isAssignedToRequiredRole($ilUser->getId()) &&
                $this->shouldRenderCustomCourseTabs() &&
                !isset(self::$tabsRendered['ecr_tab_title'])
            ) {
                $ilCtrl->setParameterByClass(__CLASS__, 'ref_id', $obj->getRefId());
                $DIC->tabs()->addTab(
                    'ecr_tab_title',
                    $this->getPluginObject()->ecr_txt('ecr_tab_title'),
                    $ilCtrl->getLinkTargetByClass(['ilUIPluginRouterGUI', __CLASS__],
                        'ilECRContentController.showECRContent')
                );
                self::$tabsRendered['ecr_tab_title'] = true;
            } else {
                if (
                    ($obj instanceof ilObjFile || $obj instanceof ilObjLinkResource)
                    && $ilAccess->checkAccess('write', '', $obj->getRefId())
                    && $this->getPluginObject()->isAssignedToRequiredRole($ilUser->getId())
                    && $this->getPluginObject()->queryItemData($ref_id) &&
                    !isset(self::$tabsRendered['ecr_tab_title'])
                ) {
                    $ilCtrl->setParameterByClass(__CLASS__, 'ref_id', $obj->getRefId());
                    $DIC->tabs()->addTab(
                        'ecr_tab_title',
                        $this->getPluginObject()->txt('ecr_tab_title'),
                        $ilCtrl->getLinkTargetByClass(['ilUIPluginRouterGUI', __CLASS__],
                            'ilECRContentController.showECRItemContent')
                    );
                    self::$tabsRendered['ecr_tab_title'] = true;
                }
            }
        }
    }

    /**
     * @return bool
     */
    private function shouldRenderCustomCourseTabs() : bool
    {
        $isBlackListedCommandClass = (
            (
                $this->isCommandClass(ilObjectCustomUserFieldsGUI::class) &&
                $this->isOneOfCommands(['editMember', 'saveMember', 'cancelEditMember',])
            ) || (
                $this->isCommandClass(ilCourseMembershipGUI::class) &&
                $this->isOneOfCommands(['printMembers', 'printMembersOutput'])
            ) ||
            $this->isCommandClass(ilContainerStartObjectsGUI::class) ||
            $this->isCommandClass(ilCalendarPresentationGUI::class) ||
            $this->isCommandClass(ilCalendarCategoryGUI::class) ||
            $this->isCommandClass(ilPublicUserProfileGUI::class) ||
            $this->isCommandClass(self::class) ||
            $this->isCommandClass(ilMailMemberSearchGUI::class) || (
                $this->isOneOfCommands(['create',]) &&
                $this->isBaseClass(ilRepositoryGUI::class)
            )
        );

        return !$isBlackListedCommandClass;
    }

    /**
     * @return bool
     */
    final public function hasCommandClass() : bool
    {
        return isset($this->dic->http()->request()->getQueryParams()['cmdClass']);
    }

    /**
     * @param string[] $commands
     * @return bool
     */
    final public function isOneOfCommands(array $commands) : bool
    {
        return in_array(
            strtolower((string) $this->dic->ctrl()->getCmd()),
            array_map(
                'strtolower',
                $commands
            )
        );
    }

    /**
     * @param string $class
     * @return bool
     */
    final public function isBaseClass(string $class) : bool
    {
        $baseClass = (string) ($this->dic->http()->request()->getQueryParams()['baseClass'] ?? '');

        return strtolower($class) === strtolower($baseClass);
    }

    /**
     * @param string $class
     * @return bool
     */
    final public function isCommandClass(string $class) : bool
    {
        $cmdClass = (string) ($this->dic->http()->request()->getQueryParams()['cmdClass'] ?? '');

        return strtolower($class) === strtolower($cmdClass);
    }

    /**
     *
     */
    protected function initModifier(): void
    {
        if (
            !isset($this->dic['tpl']) ||
            !isset($this->dic['ilToolbar'])
        ) {
            return;
        }

        if (!isset($this->dic['refinery'])) {
            return;
        }

        if (null !== self::$modifier) {
            return;
        }

        $this->plugin_object = ilElectronicCourseReservePlugin::getInstance();

        self::$modifier = [
            new ilECRCourseListGuiModifier(),
            new ilECRFileAndWebResourceImageGuiModifier(),
            new ilECRInfoScreenModifier(),
            new ilECRFolderListGuiModifier(),
            new ilECRilCopyObjectGuiModifier(),
            new ilECRBibliographicItemModifier(),
            new ilECRCourseFolderTileGuiModifier(),
            new ilECRFolderContentModifier(),
        ];
    }

    /**
     * @return bool
     */
    public function setCreationMode(): bool
    {
        return false;
    }
}
