<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

class ilElectronicCourseReserveDataMapper
{
	/**
	 * @var ilDB
	 */
	protected $db;

	public function __construct()
	{
		global $DIC;

		$this->db = $DIC->database();
	}

	public function saveHistory(ilElectronicCourseReserveHistoryEntity $entity)
	{
		$this->db->insert('ecr_import_history', array(
			'ref_id'        => array('integer', $entity->getRefId()),
			'target_ref_id' => array('integer', $entity->getTargetRefId()),
			'ts'            => array('integer', $entity->getTimestamp()),
			'job_nr'        => array('text', $entity->getJobNumber())
		));
	}
}
