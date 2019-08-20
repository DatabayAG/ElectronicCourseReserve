<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Objects;

/**
 * Class Helper
 * @package ILIAS\Plugin\ElectronicCourseReserve\Objects
 */
class Helper
{
    /**
     * @var \ilObject[]|\ilException
     */
    protected static $instanceByRefIdCache = array();

    /**
     * @var boolean[]
     */
    protected static $trashedRefIds = array();

    /**
     * @param int $ref_id
     * @return \ilObject
     * @throws \ilException
     */
    public function getInstanceByRefId($ref_id)
    {
        if (!array_key_exists($ref_id, self::$instanceByRefIdCache)) {
            $instance = \ilObjectFactory::getInstanceByRefId($ref_id, false);
            if (!$instance) {
                $e = new \ilException(sprintf("Could not find object by ref_id %s!", $ref_id));

                self::$instanceByRefIdCache[$ref_id] = $e;
                throw $e;
            }

            self::$instanceByRefIdCache[$ref_id] = $instance;
        }

        if (self::$instanceByRefIdCache[$ref_id] instanceof \Exception) {
            throw self::$instanceByRefIdCache[$ref_id];
        }

        return self::$instanceByRefIdCache[$ref_id];
    }

    /**
     * @param int $ref_id
     * @return boolean
     */
    public function isRefIdTrashed($ref_id)
    {
        if (!array_key_exists($ref_id, self::$trashedRefIds)) {
            self::$trashedRefIds[$ref_id] = $GLOBALS['tree']->isDeleted($ref_id);
        }

        return self::$trashedRefIds[$ref_id];
    }
}