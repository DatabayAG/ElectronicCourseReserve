<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Library;

use Zend\Crypt\BlockCipher;

/**
 * Class LinkBuilder
 * @package ILIAS\Plugin\ElectronicCourseReserve\Library
 */
class LinkBuilder
{
	/**
	 * @var \ilElectronicCourseReservePlugin
	 */
	protected $plugin;

	/**
	 * @var \ilObjUser 
	 */
	protected $user;

	/**
	 * @var \ilSetting 
	 */
	protected $settings;

	/**
	 * @var BlockCipher 
	 */
	protected $blockCipher;
	
	/**
	 * @var \GnuPG 
	 */
	protected $gpg;

	/**
	 * LinkBuilder constructor.
	 * @param \ilElectronicCourseReservePlugin $plugin
	 * @param \ilObjUser $user
	 * @param \ilSetting $settings
	 * @param BlockCipher $blockCipher
	 */
	public function __construct(
		\ilElectronicCourseReservePlugin $plugin,
		\GnuPG $gpg,
		\ilObjUser $user,
		\ilSetting $settings,
		BlockCipher $blockCipher
	) {
		$this->plugin = $plugin;
		$this->gpg = $gpg;
		$this->user = $user;
		$this->settings = $settings;
		$this->blockCipher = $blockCipher;
	}

	/**
	 * @param \ilObjCourse $container
	 * @return string
	 */
	public function getLibraryOrderLink(\ilObjCourse $container)
	{
		$params = $this->getLibraryUrlParameters($container);

		$url = $this->plugin->getSetting('url_search_system');
		if (strpos($url, '?') === false) {
			$separator = '?';
		} else {
			$separator = '&';
		}

		return $url . $separator . http_build_query($params);
	}

	/**
	 * @param \ilObjCourse $container
	 * @return array
	 */
	public function getLibraryUrlParameters(\ilObjCourse $container)
	{
		$default_auth = $this->settings->get('auth_mode') ? $this->settings->get('auth_mode') : AUTH_LOCAL;
		$usr_id = $this->user->getLogin();

		if (
			strlen(trim($this->user->getExternalAccount())) &&
			!(
				(
					$this->user->getAuthMode() == 'default' &&
					$default_auth == AUTH_LOCAL
				) ||
				$this->user->getAuthMode(true) == AUTH_LOCAL
			)
		) {
			$usr_id = $this->user->getExternalAccount();
		}

		$params = array(
			'ref_id' => $container->getRefId(),
			'usr_id' => $usr_id,
			'ts' => time(),
			'email' => $this->user->getEmail()
		);

		if ($this->plugin->getSetting('token_append_obj_title')) {
			$params['iltitle'] = $container->getTitle();
		}

		$data_to_sign = implode('', $params);

		$passphrase = strlen($this->plugin->getSetting('sign_key_passphrase')) ? $this->blockCipher->decrypt($this->plugin->getSetting('sign_key_passphrase')) : '';

		$key_id = null;
		foreach ($this->gpg->listKeys(true) as $result) {
			foreach ($result as $key) {
				if (strpos($key['uid'][0], '<' . $this->plugin->getSetting('sign_key_email') . '>') !== false) {
					$sign_result = $this->gpg->sign($data_to_sign, $key_id, $passphrase, false, true);
					$signed_data = $sign_result->data;
					$sign_error = $sign_result->err;

					if ($signed_data && !$sign_error) {
						$signature = $this->gpg->sign($data_to_sign, $key_id, $passphrase, false, true)->data;
						$params['iltoken'] = base64_encode($signature);
						break 2;
					}
				}
			}
		}

		return $params;
	}
}