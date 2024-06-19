<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging;

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
        $this->level = $logLevel;
        $this->cache_level = \ilLogLevel::DEBUG;

        $this->directory = $directory;
        $this->file = $file;
    }

    /**
     * @inheritdoc
     */
    public function getLevelByComponent($a_component_id): int
    {
        return $this->getLevel();
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getLogDir(): string
    {
        return $this->directory;
    }

    /**
     * @inheritdoc
     */
    public function getLogFile(): string
    {
        return $this->file;
    }

    /**
     * @inheritdoc
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @inheritdoc
     */
    public function getCacheLevel(): int
    {
        return $this->cache_level;
    }

    /**
     * @inheritdoc
     */
    public function isCacheEnabled(): bool
    {
        return $this->cache;
    }

    /**
     * @inheritdoc
     */
    public function isMemoryUsageEnabled(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isBrowserLogEnabled(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function isBrowserLogEnabledForUser($a_login): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getBrowserLogUsers(): array
    {
        return array();
    }
}
