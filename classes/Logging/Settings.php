<?php

/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging;

use ilLoggingSettings;
use ilLogLevel;

/**
 * Class Settings
 * @package ILIAS\Plugin\ElectronicCourseReserve\Logging
 */
class Settings implements ilLoggingSettings
{
    protected static ?Settings $instance = null;

    private ?int $level;
    private bool $cache = false;
    private ?int $cache_level;

    protected string $directory = '';


    protected string $file = '';

    /**
     * Settings constructor.
     * @param string $directory
     * @param string $file
     * @param int $logLevel
     */
    public function __construct(string $directory, string $file, int $logLevel = ilLogLevel::INFO)
    {
        $this->level = $logLevel;
        $this->cache_level = ilLogLevel::DEBUG;

        $this->directory = $directory;
        $this->file = $file;
    }

    public function getLevelByComponent($a_component_id): int
    {
        return $this->getLevel();
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getLogDir(): string
    {
        return $this->directory;
    }

    public function getLogFile(): string
    {
        return $this->file;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getCacheLevel(): int
    {
        return $this->cache_level;
    }

    public function isCacheEnabled(): bool
    {
        return $this->cache;
    }

    public function isMemoryUsageEnabled(): bool
    {
        return true;
    }

    public function isBrowserLogEnabled(): bool
    {
        return false;
    }

    public function isBrowserLogEnabledForUser($a_login): bool
    {
        return false;
    }

    public function getBrowserLogUsers(): array
    {
        return array();
    }
}
