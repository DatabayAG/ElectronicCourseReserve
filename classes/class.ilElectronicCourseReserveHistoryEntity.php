<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

class ilElectronicCourseReserveHistoryEntity
{
    /**
     * @var int
     */
    protected $ref_id;

    /**
     * @var int
     */
    protected $target_ref_id;

    /**
     * @var string
     */
    protected $job_number;

    /**
     * @var int
     */
    protected $timestamp;

    /**
     * @param string $job_number
     */
    public function setJobNumber($job_number)
    {
        $this->job_number = $job_number;
    }

    /**
     * @return string
     */
    public function getJobNumber()
    {
        return $this->job_number;
    }

    /**
     * @param int $ref_id
     */
    public function setRefId($ref_id)
    {
        $this->ref_id = $ref_id;
    }

    /**
     * @return int
     */
    public function getRefId()
    {
        return $this->ref_id;
    }

    /**
     * @param int $target_ref_id
     */
    public function setTargetRefId($target_ref_id)
    {
        $this->target_ref_id = $target_ref_id;
    }

    /**
     * @return int
     */
    public function getTargetRefId()
    {
        return $this->target_ref_id;
    }

    /**
     * @param int $timestamp
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @return int
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }
}
