<?php

namespace ILIAS\Plugin\ElectronicCourseReserve\Library;

use GnuPG;
use GpgSignResult;

/**
 * Class LatestVersionGpgWrapper
 * @package ILIAS\Plugin\ElectronicCourseReserve\Library
 * @author Michael Jansen <mjansen@databay.de>
 */
class LatestVersionGpgWrapper implements GpgWrapper
{
    /** @var GnuPG */
    private $coreLibrary;

    /**
     * LatestVersionGpgWrapper constructor.
     * @param GnuPG $coreLibrary
     */
    public function __construct(GnuPG $coreLibrary)
    {
        $this->coreLibrary = $coreLibrary;
    }

    /**
     * @inheritDoc
     */
    public function sign(
        $message,
        $keyId = null,
        $passphrase = null,
        $learsign = true,
        $detach = false,
        $binary = false
    ) {
        // See: https://d.sb/2016/11/gpg-inappropriate-ioctl-for-device-errors
        $result = $this->coreLibrary->sign($message, $keyId, $passphrase, $learsign, $detach, $binary);

        if ($result instanceof GpgSignResult) {
            if (
                is_string($result->err) &&
                $result->err !== '' &&
                is_string($result->data) &&
                $result->data !== ''
            ) {
                if (
                    strpos($result->err, 'wird als voreingestellter geheimer Signaturschlüssel benutzt') !== false ||
                    strpos($result->err, 'as default secret key for signing') !== false
                ) {
                    $result->err = '';
                }
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function listKeys($secret = false)
    {
        return $this->coreLibrary->listKeys($secret);
    }
}