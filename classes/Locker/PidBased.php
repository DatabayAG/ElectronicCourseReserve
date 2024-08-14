<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Locker;

use Exception;
use ilLogger;
use ilSetting;

/**
 * Class PidBased
 * @package ILIAS\Plugin\ElectronicCourseReserve\Locker
 */
class PidBased implements LockerInterface
{

    protected ilSetting $settings;


    protected ilLogger $logger;

    /**
     * PidBased constructor.
     * @param ilSetting $settings
     * @param ilLogger $logger
     */
    public function __construct(ilSetting $settings, ilLogger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * @param string $pid
     * @return bool
     */
    protected function isRunning(string $pid): bool
    {
        try {
            $result = shell_exec(sprintf("ps %d", $pid));
            if (count(preg_split("/\n/", $result)) > 2) {
                return true;
            }
        } catch (Exception $e) {
            $this->logger->error("Can\'t determine locking state: " . $e->getMessage());
        }

        return false;
    }

    /**
     *
     */
    protected function writeLockedState(): void
    {
        $this->settings->set('esa_cron_lock_status', '1');
        $this->settings->set('esa_cron_lock_ts', (string) time());
        $this->settings->set('esa_cron_lock_pid', (string) getmypid());
    }

    /**
     * @inheritdoc
     */
    public function acquireLock(): bool
    {
        if (!$this->settings->get('esa_cron_lock_status', '0')) {
            $this->writeLockedState();
            return true;
        }

        $pid = $this->settings->get('esa_cron_lock_pid');
        if ($pid && $this->isRunning($pid)) {
            $lastLockTimestamp = $this->settings->get('esa_cron_lock_ts', (string) time());
            if ($lastLockTimestamp > time() - (60 * 60 * 3)) {
                return false;
            }
        }

        $this->writeLockedState();
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isLocked(): bool
    {
        return (bool) $this->settings->get('esa_cron_lock_status', '0');
    }

    /**
     * @inheritdoc
     */
    public function releaseLock(): void
    {
        $this->settings->set('esa_cron_lock_status', '0');
        $this->settings->delete('esa_cron_lock_ts');
        $this->settings->delete('esa_cron_lock_pid');
    }
}