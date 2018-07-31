<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Locker;

/**
 * Interface LockerInterface
 * @package ILIAS\Plugin\ElectronicCourseReserve\Locker
 */
interface LockerInterface
{
	/**
	 * @return bool
	 */

	public function acquireLock();

	/**
	 * @return bool
	 */
	public function isLocked();

	/**
	 * @return void
	 */
	public function releaseLock();
}