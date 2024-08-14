<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use JetBrains\PhpStorm\NoReturn;

/**
 * Class ilGpgFingerPrintInputGUI
 */
class ilGpgFingerPrintInputGUI extends ilTextInputGUI
{
    protected ilElectronicCourseReservePlugin $plugin;

    protected Factory $uiFactory;

    protected Renderer $uiRenderer;

    protected ilLogger $log;

    protected ilGpgHomeDirInputGUI $homeDirInputGUI;
    private WrapperFactory $httpWrapper;

    /***
     * ilGpgHomeDirInputGUI constructor.
     * @param ilElectronicCourseReservePlugin $plugin
     * @param ilGpgHomeDirInputGUI $homeDirInputGUI
     * @param ilCtrl $ctrl
     * @param ilLogger $log
     * @param Factory $uiFactory
     * @param Renderer $uiRenderer
     * @param string $a_title
     * @param string $a_postvar
     */
    public function __construct(
        ilElectronicCourseReservePlugin $plugin,
        ilGpgHomeDirInputGUI            $homeDirInputGUI,
        ilCtrl                          $ctrl,
        ilLogger                        $log,
        Factory                         $uiFactory,
        Renderer                        $uiRenderer,
        string                          $a_title = '',
        string                          $a_postvar = ''
    ) {
        parent::__construct($a_title, $a_postvar);
        $this->plugin = $plugin;
        $this->uiFactory = $uiFactory;
        $this->uiRenderer = $uiRenderer;
        $this->homeDirInputGUI = $homeDirInputGUI;
        $this->ctrl = $ctrl;
        $this->log = $log;
        global $DIC;
        $this->httpWrapper = $DIC->http()->wrapper();
    }

    /**
     *
     */
    #[NoReturn] public function renderKeyList(): void
    {
        $path = $this->httpWrapper->query()->has('path') ? $this->httpWrapper->query()->retrieve('path', $this->refinery->kindlyTo()->string()) : "";
        $response = new stdClass();
        $response->html = $this->getKeyListHtml(is_string($path) ? $path : '');

        echo json_encode($response);
        exit();
    }

    /**
     * @inheritdoc
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        $nextClass = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd('renderKeyList');

        $this->{$cmd}();
    }

    protected function getKeyListHtml(string $homeDirectory = ''): ?string
    {
        if ($homeDirectory) {
            try {
                $keyList = new ilNonEditableValueGUI($this->plugin->txt('ecr_gpg_secret_keys'), '', true);
                $keyList->setInfo($this->plugin->txt('ecr_gpg_secret_keys_info'));

                $items = [];

                /** @var GnuPG $gpg */
                $gpg = $GLOBALS['DIC']['plugin.esa.crypt.gpg.factory']($homeDirectory);
                $keys = $gpg->listKeys(true);
                foreach ($keys as $result) {
                    if (!is_array($result)) {
                        continue;
                    }
                    foreach ($result as $key) {
                        $items[$key['fingerprint']] = $this->uiRenderer->render([
                            $this->uiFactory->legacy('Key Id: ' . $key['keyid']),
                            $this->uiFactory->legacy(' | '),
                            $this->uiFactory->legacy('UID: ' . implode('/', array_map('htmlspecialchars', (array) $key['uid'])))
                        ]);

                    }
                }

                if (count($items) > 0) {
                    $this->log->info(count($items) . ' keys found');

                    $list = $this->uiFactory->listing()->descriptive($items);
                    $keyList->setValue($this->uiRenderer->render($list));
                    return $keyList->render();
                }

                $this->log->info('No keys found');
            } catch (Throwable $e) {
                $this->log->error($e->getMessage());
            }
        }
        return null;
    }

    /**
     * @throws ilCtrlException
     * @throws ilTemplateException
     */
    public function render($a_mode = ""): string
    {
        $html = parent::render($a_mode);

        $tpl = $this->plugin->getTemplate('tpl.gpg_keys.html');
        $tpl->setVariable('LOADER_IMG_SRC', ilUtil::getImagePath('media/loader.svg'));
        $tpl->setVariable('HTML', $this->getKeyListHtml($this->plugin->getSetting('gpg_homedir')));
        $tpl->setVariable('OBSERVABLE_ELEMENT_ID', $this->homeDirInputGUI->getFieldId());
        $tpl->setVariable('URL', $this->ctrl->getLinkTargetByClass(
            ['ilAdministrationGUI', 'ilobjcomponentsettingsgui', 'ilElectronicCourseReserveConfigGUI', self::class],
            'renderKeyList', '', true
        ));

        return $html . $tpl->get();
    }
}