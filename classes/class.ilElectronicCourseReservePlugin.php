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
	 * @var string
	 */
	const ICON_URL = 'url';

	/**
	 * @var string
	 */
	const ICON_FILE = 'file';

	/**
	 * @var ilElectronicCourseReservePlugin
	 */
	private static $instance = null;

	/**
	 * @var array
	 */
	protected $relevant_folder_cache = array();

	/**
	 * @var array
	 */
	protected $relevant_course_cache = array();

	/**
	 * @var array
	 */
	protected $already_queried_folders = array();

	/**
	 * @var array
	 */
	protected $already_queried_items = array();

	/**
	 * @var array
	 */
	protected $item_data = array();

	/** @var bool */
	protected static $initialized = false;

	/** @var array */
	protected static $active_plugins_check_cache = array();

	/** @var array */
	protected static $active_plugins_cache = array();

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
				$cipher->setKey(md5(implode('|', [
					CLIENT_ID
				])));

				return $cipher;
			};

			$GLOBALS['DIC']['plugin.esa.library.linkbuilder'] = function (\ILIAS\DI\Container $c) use ($that) {
				$linkBuilder = new \ILIAS\Plugin\ElectronicCourseReserve\Library\LinkBuilder(
					$that,
					$c['plugin.esa.crypt.gpg'],
					$c->user(),
					$c->settings(),
					$c['plugin.esa.crypt.blockcipher']
				);

				return $linkBuilder;
			};

			$GLOBALS['DIC']['plugin.esa.crypt.gpg'] = function (\ILIAS\DI\Container $c) use ($that) {
				return $c['plugin.esa.crypt.gpg.factory']($that->getSetting('gpg_homedir'));
			};

			$GLOBALS['DIC']['plugin.esa.crypt.gpg.factory'] = function (\ILIAS\DI\Container $c) use ($that) {
				return function($homeDirectory) use ($that) {
					require_once $that->getDirectory() . '/libs/php-gnupg/gpg.php';
					$gpg = new \GnuPG($homeDirectory);

					return $gpg;
				};
			};

			$GLOBALS['DIC']['plugin.esa.locker'] = function (\ILIAS\DI\Container $c) {
				return new \ILIAS\Plugin\ElectronicCourseReserve\Locker\PidBased(
					$c['ilSetting'],
					$c->logger()->root()
				);
			};

			$GLOBALS['DIC']['plugin.esa.logger.writer.ilias'] = function (\ILIAS\DI\Container $c) {
				$logLevel = \ilLoggingDBSettings::getInstance()->getLevel();

				return new \ILIAS\Plugin\ElectronicCourseReserve\Logging\Writer\Ilias($c['ilLog'], $logLevel);
			};

			$GLOBALS['DIC']['plugin.esa.cronjob.logger'] = function (\ILIAS\DI\Container $c) {
				$logger = new \ILIAS\Plugin\ElectronicCourseReserve\Logging\Log();

				$logger->addWriter(new \ILIAS\Plugin\ElectronicCourseReserve\Logging\Writer\StdOut());
				$logger->addWriter($c['plugin.esa.logger.writer.ilias']);

				return $logger;
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
	 * @param string $identifier
	 * @return string
	 */
	public function ecr_txt($identifier)
	{
		$this->includeClass('class.ilElectronicCourseReserveLangData.php');
		$ecr_lang_data = new ilElectronicCourseReserveLangData();
		return $ecr_lang_data->txt($identifier);
	}


	/**
	 * @param $folder_ref_id
	 * @return bool
	 */
	public function isFolderRelevant($folder_ref_id)
	{
		if( ! array_key_exists($folder_ref_id, $this->relevant_folder_cache))
		{
			global $DIC;
			$res = $DIC->database()->queryF(
				'SELECT * FROM ecr_folder WHERE ref_id = %s',
				array('integer'),
				array($folder_ref_id)
			);
			$row = $DIC->database()->fetchAssoc($res);
			$this->relevant_folder_cache[$folder_ref_id] = false;
			if(is_array($row) && array_key_exists('ref_id', $row))
			{
				$this->relevant_folder_cache[$folder_ref_id] = true;
			}
		}
		return $this->relevant_folder_cache[$folder_ref_id];
	}

	/**
	 * @param int $crs_ref_id
	 * @return array
	 */
	public function getRelevantCourseAndFolderData($crs_ref_id)
	{
		global $DIC;
		$res = $DIC->database()->queryF(
			'SELECT ref_id, crs_ref_id FROM ecr_folder WHERE crs_ref_id = %s',
			array('integer'),
			array($crs_ref_id)
		);
		$folders = array();
		while($row = $DIC->database()->fetchAssoc($res))
		{
			$folders[$row['ref_id']] = $row['ref_id'];
		}
		return $folders;
	}

	/**
	 * @return array
	 */
	public function getAllRefIds()
	{
		global $DIC;
		$res = $DIC->database()->query(
			'SELECT ref_id, folder_ref_id FROM ecr_description'
		);
		$ref_ids = array();
		while($row = $DIC->database()->fetchAssoc($res))
		{
			$ref_ids[$row['ref_id']] = $row['ref_id'];
			$ref_ids[$row['folder_ref_id']] = $row['folder_ref_id'];
		}
		return $ref_ids;
	}

	/**
	 * @param $folder_ref_id
	 */
	public function queryFolderData($folder_ref_id)
	{
		if( ! array_key_exists($folder_ref_id, $this->already_queried_folders))
		{
			global $DIC;
			$res = $DIC->database()->queryF(
				'SELECT * FROM ecr_description WHERE folder_ref_id = %s',
				array('integer'),
				array($folder_ref_id)
			);
			while($row = $DIC->database()->fetchAssoc($res))
			{
				if(is_array($row) && array_key_exists('ref_id', $row))
				{
					$this->item_data[$row['ref_id']] = $row;
				}
			}
			$this->already_queried_folders[$folder_ref_id];
		}
	}

	/**
	 * @param $item_ref_id
	 * @return mixed
	 */
	public function queryItemData($item_ref_id)
	{
		if( ! array_key_exists($item_ref_id, $this->already_queried_items))
		{
			global $DIC;
			$res = $DIC->database()->queryF(
				'SELECT * FROM ecr_description WHERE ref_id = %s',
				array('integer'),
				array($item_ref_id)
			);
			while($row = $DIC->database()->fetchAssoc($res))
			{
				if(is_array($row) && array_key_exists('ref_id', $row))
				{
					$this->already_queried_items[$item_ref_id] = $row;

				}
				else
				{
					$this->already_queried_items[$item_ref_id] = array();
				}
			}
		}
		return $this->already_queried_items[$item_ref_id];
	}

	/**
	 * @param int $ref_id
	 * @param int $show_description
	 * @param int $show_image
	 */
	public function updateItemData($ref_id, $show_description, $show_image)
	{
		global $DIC;
		$DIC->database()->update("ecr_description", array(
			"show_description" => array("int", $show_description),
			"show_image"       => array("int", $show_image)),
			array(
				"ref_id"       => array("int", $ref_id)
			));
	}

	/**
	 * @param $ref_id
	 * @return bool
	 */
	public function isCourseRelevant($ref_id)
	{
		if( ! array_key_exists($ref_id, $this->relevant_course_cache))
		{
			global $DIC;
			$res = $DIC->database()->queryF(
				'SELECT * FROM ecr_folder WHERE crs_ref_id = %s',
				array('integer'),
				array($ref_id)
			);
			$row = $DIC->database()->fetchAssoc($res);
			$this->relevant_course_cache[$ref_id] = false;
			if(is_array($row) && array_key_exists('crs_ref_id', $row))
			{
				$this->relevant_course_cache[$ref_id] = true;
			}
		}
		return $this->relevant_course_cache[$ref_id];
	}

	/**
	 * @return array
	 */
	public function getItemData()
	{
		return $this->item_data;
	}

	/**
	 * @param string $component
	 * @param string $slot
	 * @param string $plugin_class
	 *
	 * @return bool
	 */
	public function isPluginInstalled($component, $slot, $plugin_class)
	{
		if (isset(self::$active_plugins_check_cache[$component][$slot][$plugin_class])) {
			return self::$active_plugins_check_cache[$component][$slot][$plugin_class];
		}

		foreach (
			$GLOBALS['ilPluginAdmin']->getActivePluginsForSlot(IL_COMP_SERVICE, $component,
				$slot) as $plugin_name
		) {
			$plugin = \ilPluginAdmin::getPluginObject(IL_COMP_SERVICE, $component, $slot, $plugin_name);
			if (\class_exists($plugin_class) && $plugin instanceof $plugin_class) {
				return (self::$active_plugins_check_cache[$component][$slot][$plugin_class] = true);
			}
		}

		return (self::$active_plugins_check_cache[$component][$slot][$plugin_class] = false);
	}

	/**
	 * @param string $component
	 * @param string $slot
	 * @param string $plugin_class
	 *
	 * @return \ilPlugin
	 * @throws \ilException
	 */
	public function getPlugin($component, $slot, $plugin_class)
	{
		if (isset(self::$active_plugins_cache[$component][$slot][$plugin_class])) {
			return self::$active_plugins_cache[$component][$slot][$plugin_class];
		}

		foreach (
			$GLOBALS['ilPluginAdmin']->getActivePluginsForSlot(IL_COMP_SERVICE, $component,
				$slot) as $plugin_name
		) {
			$plugin = \ilPluginAdmin::getPluginObject(IL_COMP_SERVICE, $component, $slot, $plugin_name);
			if (\class_exists($plugin_class) && $plugin instanceof $plugin_class) {
				return (self::$active_plugins_cache[$component][$slot][$plugin_class] = $plugin);
			}
		}

		throw new \ilException($plugin_class . ' plugin not installed!');
	}
}
