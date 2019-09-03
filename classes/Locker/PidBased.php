<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Locker;

/**
 * Class PidBased
 * @package ILIAS\Plugin\ElectronicCourseReserve\Locker
 */
class PidBased implements LockerInterface
{
    /**
     * @var \ilSetting
     */
    protected $settings;

    /**
     * @var \ilLogger
     */
    protected $logger;

    /**
     * PidBased constructor.
     * @param \ilSetting $settings
     * @param \ilLogger $logger
     */
    public function __construct(\ilSetting $settings, \ilLogger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * @param string $pid
     * @return bool
     */
    protected function isRunning($pid)
    {
        try {
            $result = shell_exec(sprintf("ps %d", $pid));
            if (count(preg_split("/\n/", $result)) > 2) {
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->error("Can\'t determine locking state: " . $e->getMessage());
        }

        return false;
    }

    /**
     *
     */
    protected function writeLockedState()
    {
        $this->settings->set('esa_cron_lock_status', 1);
        $this->settings->set('esa_cron_lock_ts', time());
        $this->settings->set('esa_cron_lock_pid', getmypid());
    }

    /**
     * @inheritdoc
     */
    public function acquireLock()
    {
        if (!$this->settings->get('esa_cron_lock_status', 0)) {
            $this->writeLockedState();
            return true;
        }

        $pid = $this->settings->get('esa_cron_lock_pid', null);
        if ($pid && $this->isRunning($pid)) {
            $lastLockTimestamp = $this->settings->get('esa_cron_lock_ts', time());
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
    public function isLocked()
    {
        return (bool) $this->settings->get('esa_cron_lock_status', 0);
    }

    /**
     * @inheritdoc
     */
    public function releaseLock()
    {
        $this->settings->set('esa_cron_lock_status', 0);
        $this->settings->set('esa_cron_lock_ts', null);
        $this->settings->set('esa_cron_lock_pid', null);
    }
}