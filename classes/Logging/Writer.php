<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Logging;

/**
 * Interface Writer
 * @package ILIAS\Plugin\ElectronicCourseReserve\Logging
 */
interface Writer
{
	/**
	 * @param array $message
	 * @return void
	 */
	public function write(array $message);

	/**
	 * @return void
	 */
	public function shutdown();
}