<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\UI\Factory;
use ILIAS\UI\Implementation\Component\Legacy\Legacy;
use ILIAS\UI\Renderer;

/**
 * Class ilGpgFingerPrintInputGUI
 */
class ilGpgFingerPrintInputGUI extends ilTextInputGUI
{
    /** @var ilElectronicCourseReservePlugin */
    protected $plugin;

    /** @var Factory */
    protected $uiFactory;

    /** @var Renderer */
    protected $uiRenderer;

    /** @var ilCtrl */
    protected $ctrl;

    /** @var ilLogger */
    protected $log;

    /** @var ilGpgHomeDirInputGUI */
    protected $homeDirInputGUI;

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
        ilGpgHomeDirInputGUI $homeDirInputGUI,
        ilCtrl $ctrl,
        ilLogger $log,
        Factory $uiFactory,
        Renderer $uiRenderer,
        $a_title = '',
        $a_postvar = ''
    ) {
        parent::__construct($a_title, $a_postvar);
        $this->plugin = $plugin;
        $this->uiFactory = $uiFactory;
        $this->uiRenderer = $uiRenderer;
        $this->homeDirInputGUI = $homeDirInputGUI;
        $this->ctrl = $ctrl;
        $this->log = $log;
    }

    /**
     *
     */
    public function renderKeyList()
    {
        $response = new stdClass();
        $response->html = $this->getKeyListHtml(isset($_GET['path']) && is_string($_GET['path']) ? $_GET['path'] : '');

        echo json_encode($response);
        exit();
    }

    /**
     * @inheritdoc
     */
    public function executeCommand()
    {
        $nextClass = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd('renderKeyList');

        $this->{$cmd}();
    }

    /**
     * @param string $homeDirectory
     * @return string
     */
    protected function getKeyListHtml($homeDirectory = '')
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
                        if (version_compare(ILIAS_VERSION_NUMERIC, '5.3.0', '>=')) {
                            $items[$key['fingerprint']] = $this->uiRenderer->render([
                                new Legacy('Key Id: ' . $key['keyid']),
                                new Legacy(' | '),
                                new Legacy('UID: ' . implode('/', array_map('htmlspecialchars', (array) $key['uid'])))
                            ]);
                        } else {
                            $items[$key['fingerprint']] = implode('', [
                                'Key Id: ' . $key['keyid'],
                                ' | ',
                                'UID: ' . implode('/', array_map('htmlspecialchars', (array) $key['uid'])),
                            ]);
                        }
                    }
                }

                if (count($items) > 0) {
                    $this->log->info(count($items) . ' keys found');

                    $list = $this->uiFactory->listing()->descriptive($items);
                    $keyList->setValue($this->uiRenderer->render($list));
                    return $keyList->render();
                } else {
                    $this->log->info('No keys found');
                }
            } catch (Throwable $e) {
                $this->log->error($e->getMessage());
            } catch (Exception $e) {
                $this->log->error($e->getMessage());
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function render($a_mode = "")
    {
        $html = parent::render($a_mode);

        $tpl = $this->plugin->getTemplate('tpl.gpg_keys.html', true, true);
        $tpl->setVariable('LOADER_IMG_SRC', ilUtil::getImagePath('loader.svg'));
        $tpl->setVariable('HTML', $this->getKeyListHtml($this->plugin->getSetting('gpg_homedir')));
        $tpl->setVariable('OBSERVABLE_ELEMENT_ID', $this->homeDirInputGUI->getFieldId());
        $tpl->setVariable('URL', $this->ctrl->getLinkTargetByClass(
            ['ilAdministrationGUI', 'ilobjcomponentsettingsgui', 'ilElectronicCourseReserveConfigGUI', self::class],
            'renderKeyList', '', true, false
        ));

        return $html . $tpl->get();
    }
}