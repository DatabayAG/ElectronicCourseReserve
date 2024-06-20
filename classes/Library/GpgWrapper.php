<?php

namespace ILIAS\Plugin\ElectronicCourseReserve\Library;

use GpgListKeysResult;
use GpgSignResult;

/**
 * Interface GpgWrapper
 * @package ILIAS\Plugin\ElectronicCourseReserve\Library
 * @author Michael Jansen <mjansen@datababay.de>
 */
interface GpgWrapper
{
    /**
     * @param $message
     * @param null $keyId
     * @param null $passphrase
     * @param bool $learsign
     * @param bool $detach
     * @param bool $binary
     * @return GpgSignResult
     */
    public function sign($message, $keyId = null, $passphrase = null, bool $learsign = true, bool $detach = false, bool $binary = false): GpgSignResult;

    /**
     * @param bool $secret
     * @return GpgListKeysResult
     */
    public function listKeys(bool $secret = false): GpgListKeysResult;
}