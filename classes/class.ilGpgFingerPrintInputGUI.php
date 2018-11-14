<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\UI\Implementation\Component\Legacy\Legacy;

/**
 * Class ilGpgFingerPrintInputGUI
 */
class ilGpgFingerPrintInputGUI extends \ilTextInputGUI
{
	/** @var \ilElectronicCourseReservePlugin */
	protected $plugin;

	/** @var \ILIAS\UI\Factory */
	protected $uiFactory;

	/** @var \ILIAS\UI\Renderer */
	protected $uiRenderer;

	/** @var \ilCtrl */
	protected $ctrl;

	/** @var \ilGpgHomeDirInputGUI */
	protected $homeDirInputGUI;

	/***
	 * ilGpgHomeDirInputGUI constructor.
	 * @param \ilElectronicCourseReservePlugin $plugin
	 * @param ilGpgHomeDirInputGUI $homeDirInputGUI
	 * @param \ilCtrl $ctrl
	 * @param \ILIAS\UI\Factory $uiFactory
	 * @param \ILIAS\UI\Renderer $uiRenderer
	 * @param string $a_title
	 * @param string $a_postvar
	 */
	public function __construct(
		\ilElectronicCourseReservePlugin $plugin,
		\ilGpgHomeDirInputGUI $homeDirInputGUI,
		\ilCtrl $ctrl,
		\ILIAS\UI\Factory $uiFactory,
		\ILIAS\UI\Renderer $uiRenderer,
		$a_title = '',
		$a_postvar = ''
	)
	{
		parent::__construct($a_title, $a_postvar);
		$this->plugin = $plugin;
		$this->uiFactory = $uiFactory;
		$this->uiRenderer = $uiRenderer;
		$this->homeDirInputGUI = $homeDirInputGUI;
		$this->ctrl = $ctrl;
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
				$keyList = new \ilNonEditableValueGUI($this->plugin->txt('ecr_gpg_secret_keys'), '', true);
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
							new Legacy('Key Id: ' . $key['keyid']),
							new Legacy(' | '),
							new Legacy('UID: ' . implode('/', array_map('htmlspecialchars', (array)$key['uid'])))
						]);
					}
				}

				if (count($items) > 0) {
					$list = $this->uiFactory->listing()->descriptive($items);
					$keyList->setValue($this->uiRenderer->render($list));
					return $keyList->render();
				}
			} catch (\Throwable $e) {
			} catch (\Exception $e) {
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
		$tpl->setVariable('LOADER_IMG_SRC', \ilUtil::getImagePath('loader.svg'));
		$tpl->setVariable('HTML', $this->getKeyListHtml($this->plugin->getSetting('gpg_homedir')));
		$tpl->setVariable('OBSERVABLE_ELEMENT_ID', $this->homeDirInputGUI->getFieldId());
		$tpl->setVariable('URL', $this->ctrl->getLinkTargetByClass(
			['ilAdministrationGUI', 'ilobjcomponentsettingsgui', 'ilElectronicCourseReserveConfigGUI', self::class],
			'renderKeyList', '', true, false
		));

		return $html . $tpl->get();
	}
}