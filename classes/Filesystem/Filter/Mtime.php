<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Filesystem\Filter;

/**
 * Class Mtime
 * @package ILIAS\Plugin\ElectronicCourseReserve\Filesystem\Filter
 */
class Mtime extends \FilterIterator
{
	/** @var int */
	protected $boundaryTimestamp;

	/**
	 * @param \Iterator $iter
	 * @param int $boundaryTimestamp
	 */
	public function __construct(\Iterator $iter, int $boundaryTimestamp)
	{
		parent::__construct($iter);
		$this->boundaryTimestamp = $boundaryTimestamp;
	}

	/**
	 * @inheritdoc
	 */
	public function accept()
	{
		/** @var \SplFileInfo */
		$current = parent::current();

		if (!is_numeric($this->boundaryTimestamp) || $this->boundaryTimestamp <= 0) {
			return false;
		}

		if ($current->getMTime() < $this->boundaryTimestamp) {
			return true;
		}

		return false;
	}
}