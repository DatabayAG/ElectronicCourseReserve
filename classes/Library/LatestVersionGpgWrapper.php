<?php

namespace ILIAS\Plugin\ElectronicCourseReserve\Library;

use GnuPG;
use GpgListKeysResult;
use GpgSignResult;

/**
 * Class LatestVersionGpgWrapper
 * @package ILIAS\Plugin\ElectronicCourseReserve\Library
 * @author Michael Jansen <mjansen@databay.de>
 */
class LatestVersionGpgWrapper implements GpgWrapper
{
    private GnuPG $coreLibrary;

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
        bool $learsign = true,
        bool $detach = false,
        bool $binary = false
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
                    str_contains($result->err, 'wird als voreingestellter geheimer Signaturschlüssel benutzt') ||
                    str_contains($result->err, 'as default secret key for signing')
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
    public function listKeys(bool $secret = false): GpgListKeysResult
    {
        return $this->coreLibrary->listKeys($secret);
    }
}