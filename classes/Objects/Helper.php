<?php

/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Objects;

use Exception;
use ilException;
use ilObject;
use ilObjectFactory;

/**
 * Class Helper
 * @package ILIAS\Plugin\ElectronicCourseReserve\Objects
 */
class Helper
{
    protected static array|ilException $instanceByRefIdCache = array();

    protected static array $trashedRefIds = array();

    /**
     * @param int $ref_id
     * @return ilObject
     * @throws ilException
     * @throws Exception
     */
    public function getInstanceByRefId(int $ref_id): ilObject
    {
        if (!array_key_exists($ref_id, self::$instanceByRefIdCache)) {
            $instance = ilObjectFactory::getInstanceByRefId($ref_id, false);
            if (!$instance) {
                $e = new ilException(sprintf("Could not find object by ref_id %s!", $ref_id));

                self::$instanceByRefIdCache[$ref_id] = $e;
                throw $e;
            }

            self::$instanceByRefIdCache[$ref_id] = $instance;
        }

        if (self::$instanceByRefIdCache[$ref_id] instanceof Exception) {
            throw self::$instanceByRefIdCache[$ref_id];
        }

        return self::$instanceByRefIdCache[$ref_id];
    }

    /**
     * @param int $ref_id
     * @return boolean
     */
    public function isRefIdTrashed(int $ref_id): bool
    {
        if (!array_key_exists($ref_id, self::$trashedRefIds)) {
            self::$trashedRefIds[$ref_id] = $GLOBALS['tree']->isDeleted($ref_id);
        }

        return self::$trashedRefIds[$ref_id];
    }
}
