<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php';

class ilElectronicCourseReservePlugin extends ilUserInterfaceHookPlugin
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

	/**
	 * @var int
	 */
	protected static $iv_source = MCRYPT_DEV_URANDOM;

	/**
	 * Get Plugin Name. Must be same as in class name il<Name>Plugin
	 * and must correspond to plugins subdirectory name.
	 * Must be overwritten in plugin class of plugin
	 * (and should be made final)
	 * @return string
	 */
	public function getPluginName()
	{
		return self::PNAME;
	}

	/**
	 * @param string $crypt_data
	 * @return string
	 */
	public static function decrypt($crypt_data)
	{
		$sym_key = self::getSymKey();

		$cipher   = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
		$iv       = mcrypt_create_iv(mcrypt_enc_get_iv_size($cipher), self::$iv_source);
		$key_size = mcrypt_enc_get_key_size($cipher);
		$sym_key  = substr($sym_key, 0, $key_size);
		mcrypt_generic_init($cipher, $sym_key, $iv);
		$plain_data = trim(mdecrypt_generic($cipher, self::urlbase64_decode($crypt_data)));
		mcrypt_generic_deinit($cipher);
		mcrypt_module_close($cipher);
		return $plain_data;
	}

	/**
	 * @param string $plain_data
	 * @return string
	 */
	public static function encrypt($plain_data)
	{
		$sym_key = self::getSymKey();

		$cipher   = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
		$iv       = mcrypt_create_iv(mcrypt_enc_get_iv_size($cipher), self::$iv_source);
		$key_size = mcrypt_enc_get_key_size($cipher);
		$sym_key  = substr($sym_key, 0, $key_size);
		mcrypt_generic_init($cipher, $sym_key, $iv);
		$crypt_data = self::urlbase64_encode(mcrypt_generic($cipher, $plain_data));
		mcrypt_generic_deinit($cipher);
		mcrypt_module_close($cipher);
		return $crypt_data;
	}

	protected static function getSymKey()
	{
		return md5(implode('|', array(CLIENT_ID, $_SERVER['HOST'])));
	}

	/**
	 * @param string $data
	 * @return string
	 */
	protected static function urlbase64_decode($data)
	{
		return base64_decode(str_replace(array('_', '-', '.'), array('/', '+', '='), $data), true);
	}

	/**
	 * @param string $data
	 * @return string
	 */
	protected static function urlbase64_encode($data)
	{
		return str_replace(array('/', '+', '='), array('_', '-', '.'), base64_encode($data));
	}

	/**
	 * @param string $keyword
	 * @param mixed  $value
	 */
	public function setSetting($keyword, $value)
	{
		global $ilSetting;

		$ilSetting->set('ecr_' . $keyword, $value);
	}

	/**
	 * @param string $keyword
	 * @return mixed
	 */
	public function getSetting($keyword)
	{
		global $ilSetting;

		return $ilSetting->get('ecr_' . $keyword, '');
	}

	/**
	 * @param array $path
	 * @param array $params
	 * @param       $cmd
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

		while($dataSet = $ilDB->fetchAssoc($resultSet))
		{
			$commandNodeIds[$ctrlClasses[$dataSet['class']]] = $dataSet['cid'];
		}

		ksort($commandNodeIds);

		$params = array_merge(array(
			'cmd'       => $cmd,
			'baseClass' => $path[0],
			'cmdClass'  => $path[count($path) - 1],
			'cmdNode'   => implode(':', $commandNodeIds)
		), $params);

		$target = 'ilias.php';

		foreach($params as $paramName => $paramValue)
		{
			$target = ilUtil::appendUrlParameterString($target, "$paramName=$paramValue", false);
		}

		return $target;
	}

	/**
	 * @return ilElectronicCourseReservePlugin | ilPlugin
	 */
	public static function getInstance()
	{
		if(null === self::$instance)
		{
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
	 * @param $usr_id
	 * @return bool
	 */
	public function isAssignedToRequiredRole($usr_id)
	{
		global $DIC; 
		$rbacreview = $DIC->rbac()->review();

		$plugin = self::getInstance();

		if(!$plugin->getSetting('limit_to_groles'))
		{
			return true;
		}

		$groles = explode(',', $plugin->getSetting('global_roles'));
		$groles = array_filter($groles);

		if(!$groles)
		{
			return true;
		}

		foreach($groles as $role_id)
		{
			if($rbacreview->isAssigned($usr_id, $role_id))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param ilContainer $container
	 * @return string
	 */
	public function getLibraryOrderLink(ilContainer $container)
	{
		$params = $this->getLibraryUrlParameters($container);

		$url = $this->getSetting('url_search_system');
		if(strpos($url, '?') === false)
		{
			$separator = '?';
		}
		else
		{
			$separator = '&';
		}

		return $url . $separator . http_build_query($params);
	}

	/**
	 * @param ilContainer $container
	 * @return array
	 */
	public function getLibraryUrlParameters(ilContainer $container)
	{
		global $DIC, $ilSetting; 
		$user = $DIC->user();

		$default_auth = $ilSetting->get('auth_mode') ? $ilSetting->get('auth_mode') : AUTH_LOCAL;
		$usr_id       = $user->getLogin();

		if(
			strlen(trim($user->getExternalAccount())) &&
			!(
				(
					$user->getAuthMode() == 'default' &&
					$default_auth == AUTH_LOCAL
				) ||
				$user->getAuthMode(true) == AUTH_LOCAL
			)
		)
		{
			$usr_id = $user->getExternalAccount();
		}

		$params = array(
			'ref_id' => $container->getRefId(),
			'usr_id' => $usr_id,
			'ts'     => time(),
			'email'  => $user->getEmail()
		);
		$data_to_sign = implode('', $params);

		require_once $this->getDirectory() . '/lib/php-gnupg/gpg.php';
		$gpg = new GnuPG($this->getSetting('gpg_homedir'));
		$passphrase = strlen($this->getSetting('sign_key_passphrase')) ? ilElectronicCourseReservePlugin::decrypt($this->getSetting('sign_key_passphrase')) : '';

		$key_id = null;
		foreach($gpg->listKeys(true) as $result)
		{
			foreach($result as $key)
			{
				if(strpos($key['uid'][0], '<' . $this->getSetting('sign_key_email') . '>') !== false)
				{
					$sign_result = $gpg->sign($data_to_sign, $key_id, $passphrase, false, true);
					$signed_data  = $sign_result->data;
					$sign_error   = $sign_result->err;

					if($signed_data && !$sign_error)
					{
						$signature         = $gpg->sign($data_to_sign, $key_id, $passphrase, false, true)->data;
						$params['iltoken'] = base64_encode($signature);
						break 2;
					}
				}
			}
		}

		return $params;
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
}
