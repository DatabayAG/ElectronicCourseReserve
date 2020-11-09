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
     * @param false $detach
     * @param false $binary
     * @return GpgSignResult
     */
    public function sign($message, $keyId = null, $passphrase = null, $learsign = true, $detach = false, $binary = false);

    /**
     * @param false $secret
     * @return GpgListKeysResult
     */
    public function listKeys($secret = false);
}