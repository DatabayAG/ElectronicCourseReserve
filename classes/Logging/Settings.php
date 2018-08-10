<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging;

require_once './Services/Logging/classes/public/class.ilLogLevel.php';
require_once './Services/Logging/interfaces/interface.ilLoggingSettings.php';

/**
 * Class Settings
 * @package ILIAS\Plugin\ElectronicCourseReserve\Logging
 */
class Settings implements \ilLoggingSettings
{
	/**
	 * @var null|self
	 */
	protected static $instance = null;

	private $level = null;
	private $cache = false;
	private $cache_level = null;

	/**
	 * @var string
	 */
	protected $directory = '';

	/**
	 * @var string
	 */
	protected $file = '';

	/**
	 * Settings constructor.
	 * @param string $directory
	 * @param string $file
	 * @param int $logLevel
	 */
	public function __construct($directory, $file, $logLevel = \ilLogLevel::INFO)
	{
		$this->level       = $logLevel;
		$this->cache_level = \ilLogLevel::DEBUG;

		$this->directory = $directory;
		$this->file      = $file;
	}

	/**
	 * @inheritdoc
	 */
	public function getLevelByComponent($a_component_id)
	{
		return $this->getLevel();
	}

	/**
	 * @inheritdoc
	 */
	public function isEnabled()
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function getLogDir()
	{
		return $this->directory;
	}

	/**
	 * @inheritdoc
	 */
	public function getLogFile()
	{
		return $this->file;
	}

	/**
	 * @inheritdoc
	 */
	public function getLevel()
	{
		return $this->level;
	}

	/**
	 * @inheritdoc
	 */
	public function getCacheLevel()
	{
		return $this->cache_level;
	}

	/**
	 * @inheritdoc
	 */
	public function isCacheEnabled()
	{
		return $this->cache;
	}

	/**
	 * @inheritdoc
	 */
	public function isMemoryUsageEnabled()
	{
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function isBrowserLogEnabled()
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function isBrowserLogEnabledForUser($a_login)
	{
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function getBrowserLogUsers()
	{
		return array();
	}
}
