<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

use Zend\Crypt;

require_once 'Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php';

class ilElectronicCourseReservePlugin extends \ilUserInterfaceHookPlugin
{
	/**
	 * @var string
	 */
	const CTYPE = 'Services';

	/**
	 * @var string
	 */
	const CNAME = 'UIComponent';

	/**
	 * @var string
	 */
	const SLOT_ID = 'uihk';

	/**
	 * @var string
	 */
	const PNAME = 'ElectronicCourseReserve';

	/**
	 * @var \ilElectronicCourseReservePlugin
	 */
	private static $instance = null;

	/** @var bool */
	protected static $initialized = false;

	/**
	 * @inheritdoc
	 */
	public function getPluginName()
	{
		return self::PNAME;
	}

	/**
	 * @inheritdoc
	 */
	protected function init()
	{
		parent::init();
		$this->registerAutoloader();

		if (!self::$initialized) {
			self::$initialized = true;

			$that = $this;

			$GLOBALS['DIC']['plugin.esa.object.helper'] = function (\ILIAS\DI\Container $c) use ($that) {
				return new \ILIAS\Plugin\ElectronicCourseReserve\Objects\Helper();
			};

			$GLOBALS['DIC']['plugin.esa.crypt.blockcipher'] = function (\ILIAS\DI\Container $c) use ($that) {
				$cipher = Crypt\BlockCipher::factory('openssl', ['algorithm' => 'aes']);
				$cipher->setKey($that->getSymKey());

				return $cipher;
			};
		}
	}

	/**
	 * Registers the plugin autoloader
	 */
	public function registerAutoloader()
	{
		require_once dirname(__FILE__) . '/../autoload.php';
	}

	/**
	 * @return string
	 */
	protected function getSymKey()
	{
		return md5(implode('|', [
			CLIENT_ID
		]));
	}

	/**
	 * @param string $keyword
	 * @param mixed $value
	 */
	public function setSetting($keyword, $value)
	{
		global $DIC;

		$ilSetting = $DIC['ilSetting'];

		$ilSetting->set('ecr_' . $keyword, $value);
	}

	/**
	 * @param string $keyword
	 * @return mixed
	 */
	public function getSetting($keyword)
	{
		global $DIC;

		$ilSetting = $DIC['ilSetting'];

		return $ilSetting->get('ecr_' . $keyword, '');
	}

	/**
	 * @param array $path
	 * @param array $params
	 * @param $cmd
	 * @return string
	 */
	public function getLinkTarget(array $path, array $params = array(), $cmd)
	{
		global $DIC;

		$ilDB = $DIC->database();

		$class_IN_ctrlClasses = $ilDB->in('class', $path, false, 'text');

		$query = "
            SELECT    class, cid
           
            FROM    ctrl_classfile
           
            WHERE    $class_IN_ctrlClasses
        ";

		$resultSet = $ilDB->query($query);

		$ctrlClasses = array_flip($path);

		$commandNodeIds = array();

		while ($dataSet = $ilDB->fetchAssoc($resultSet)) {
			$commandNodeIds[$ctrlClasses[$dataSet['class']]] = $dataSet['cid'];
		}

		ksort($commandNodeIds);

		$params = array_merge(array(
			'cmd' => $cmd,
			'baseClass' => $path[0],
			'cmdClass' => $path[count($path) - 1],
			'cmdNode' => implode(':', $commandNodeIds)
		), $params);

		$target = 'ilias.php';

		foreach ($params as $paramName => $paramValue) {
			$target = ilUtil::appendUrlParameterString($target, "$paramName=$paramValue", false);
		}

		return $target;
	}

	/**
	 * @return ilElectronicCourseReservePlugin
	 */
	public static function getInstance()
	{
		if (null === self::$instance) {
			require_once 'Services/Component/classes/class.ilPluginAdmin.php';
			return self::$instance = ilPluginAdmin::getPluginObject(
				self::CTYPE,
				self::CNAME,
				self::SLOT_ID,
				self::PNAME
			);
		}

		return self::$instance;
	}

	/**
	 * @param int $usr_id
	 * @return bool
	 */
	public function isAssignedToRequiredRole($usr_id)
	{
		global $DIC;
		$rbacreview = $DIC->rbac()->review();

		$plugin = self::getInstance();

		if (!$plugin->getSetting('limit_to_groles')) {
			return true;
		}

		$groles = explode(',', $plugin->getSetting('global_roles'));
		$groles = array_filter($groles);

		if (!$groles) {
			return true;
		}

		foreach ($groles as $role_id) {
			if ($rbacreview->isAssigned($usr_id, $role_id)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param \ilContainer $container
	 * @throws Exception
	 */
	public function getLibraryOrderLink(\ilContainer $container)
	{
		$params = $this->getLibraryUrlParameters($container);

		$url = $this->getSetting('url_search_system');
		if (strpos($url, '?') === false) {
			$separator = '?';
		} else {
			$separator = '&';
		}

		return $url . $separator . http_build_query($params);
	}

	/**
	 * @param \ilContainer $container
	 * @return array
	 */
	public function getLibraryUrlParameters(\ilContainer $container)
	{
		global $DIC;

		$ilSetting = $DIC['ilSetting'];
		$ilUser = $DIC->user();
		/** @var \Zend\Crypt\BlockCipher $symmetric */
		$symmetric = $DIC['plugin.esa.crypt.symmetric'];

		$default_auth = $ilSetting->get('auth_mode') ? $ilSetting->get('auth_mode') : AUTH_LOCAL;
		$usr_id = $ilUser->getLogin();

		if (
			strlen(trim($ilUser->getExternalAccount())) &&
			!(
				(
					$ilUser->getAuthMode() == 'default' &&
					$default_auth == AUTH_LOCAL
				) ||
				$ilUser->getAuthMode(true) == AUTH_LOCAL
			)
		) {
			$usr_id = $ilUser->getExternalAccount();
		}

		$params = array(
			'ref_id' => $container->getRefId(),
			'usr_id' => $usr_id,
			'ts' => time(),
			'email' => $ilUser->getEmail()
		);
		$data_to_sign = implode('', $params);

		require_once $this->getDirectory() . '/lib/php-gnupg/gpg.php';
		$gpg = new GnuPG($this->getSetting('gpg_homedir'));
		$passphrase = strlen($this->getSetting('sign_key_passphrase')) ? $symmetric->decrypt($this->getSetting('sign_key_passphrase')) : '';

		$key_id = null;
		foreach ($gpg->listKeys(true) as $result) {
			foreach ($result as $key) {
				if (strpos($key['uid'][0], '<' . $this->getSetting('sign_key_email') . '>') !== false) {
					$sign_result = $gpg->sign($data_to_sign, $key_id, $passphrase, false, true);
					$signed_data = $sign_result->data;
					$sign_error = $sign_result->err;

					if ($signed_data && !$sign_error) {
						$signature = $gpg->sign($data_to_sign, $key_id, $passphrase, false, true)->data;
						$params['iltoken'] = base64_encode($signature);
						break 2;
					}
				}
			}
		}

		return $params;
	}

	/**
	 * @param string $identifier
	 * @return string
	 */
	public function ecr_txt($identifier)
	{
		$this->includeClass('class.ilElectronicCourseReserveLangData.php');
		$ecr_lang_data = new ilElectronicCourseReserveLangData();
		return $ecr_lang_data->txt($identifier);
	}
}
