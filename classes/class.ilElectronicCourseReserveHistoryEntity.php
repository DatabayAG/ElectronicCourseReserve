<?php

/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

class ilElectronicCourseReserveHistoryEntity
{
    protected int $ref_id = 0;

    protected int $target_ref_id = 0;

    protected string $job_number = '';

    protected int $timestamp = 0;

    /**
     * @param string $job_number
     */
    public function setJobNumber(string $job_number): void
    {
        $this->job_number = $job_number;
    }

    /**
     * @return string
     */
    public function getJobNumber(): string
    {
        return $this->job_number;
    }

    /**
     * @param int $ref_id
     */
    public function setRefId(int $ref_id): void
    {
        $this->ref_id = $ref_id;
    }

    /**
     * @return int
     */
    public function getRefId(): int
    {
        return $this->ref_id;
    }

    /**
     * @param int $target_ref_id
     */
    public function setTargetRefId(int $target_ref_id): void
    {
        $this->target_ref_id = $target_ref_id;
    }

    /**
     * @return int
     */
    public function getTargetRefId(): int
    {
        return $this->target_ref_id;
    }

    /**
     * @param int $timestamp
     */
    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
}
