<?php

/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Library;

use GnuPG;
use ilAuthUtils;
use ilElectronicCourseReservePlugin;
use ilObjCourse;
use ilObjUser;
use ilSetting;
use Laminas\Crypt\BlockCipher;

/**
 * Class LinkBuilder
 * @package ILIAS\Plugin\ElectronicCourseReserve\Library
 */
class LinkBuilder
{
    protected ilElectronicCourseReservePlugin $plugin;

    protected ilObjUser $user;

    protected ilSetting $settings;

    protected BlockCipher $blockCipher;

    protected GnuPG $gpg;

    private LatestVersionGpgWrapper $gpgLatest;

    /**
     * LinkBuilder constructor.
     * @param ilElectronicCourseReservePlugin $plugin
     * @param GnuPG $gpg
     * @param LatestVersionGpgWrapper $gpgLatest
     * @param ilObjUser $user
     * @param ilSetting $settings
     * @param BlockCipher $blockCipher
     */
    public function __construct(
        ilElectronicCourseReservePlugin $plugin,
        GnuPG $gpg,
        LatestVersionGpgWrapper $gpgLatest,
        ilObjUser $user,
        ilSetting $settings,
        BlockCipher $blockCipher
    ) {
        $this->plugin = $plugin;
        $this->gpg = $gpg;
        $this->user = $user;
        $this->settings = $settings;
        $this->blockCipher = $blockCipher;
        $this->gpgLatest = $gpgLatest;
    }

    public function getLibraryOrderLink(ilObjCourse $container): string
    {
        $params = $this->getLibraryUrlParameters($container);

        $url = $this->plugin->getSetting('url_search_system');
        if (!str_contains($url, '?')) {
            $separator = '?';
        } else {
            $separator = '&';
        }

        return $url . $separator . http_build_query($params);
    }

    public function getLibraryUrlParameters(ilObjCourse $container): array
    {
        $default_auth = $this->settings->get('auth_mode') ?: ilAuthUtils::AUTH_LOCAL;
        $usr_id = $this->user->getLogin();

        if (
            trim($this->user->getExternalAccount()) !== '' &&
            !(
                (
                    $this->user->getAuthMode() === 'default' &&
                    (int) $default_auth === ilAuthUtils::AUTH_LOCAL
                ) ||
                (int) $this->user->getAuthMode(true) === ilAuthUtils::AUTH_LOCAL
            )
        ) {
            $usr_id = $this->user->getExternalAccount();
        }

        $params = [
            'ref_id' => $container->getRefId(),
            'usr_id' => $usr_id,
            'ts' => time(),
            'email' => $this->user->getEmail()
        ];

        if ($this->plugin->getSetting('token_append_obj_title')) {
            $params['iltitle'] = $container->getTitle();
        }

        $data_to_sign = implode('', $params);

        $passphrase = strlen($this->plugin->getSetting('sign_key_passphrase')) ? $this->blockCipher->decrypt($this->plugin->getSetting('sign_key_passphrase')) : '';

        $keys = $this->gpg->listKeys(true);

        foreach ($keys as $result) {
            if (!is_array($result)) {
                continue;
            }

            foreach ($result as $key) {
                $fingerprint = $key['fingerprint'];

                if ($fingerprint === $this->plugin->getSetting('sign_key_fingerprint')) {
                    $signResult = $this->gpgLatest->sign($data_to_sign, $fingerprint, $passphrase, false, true);
                    $signature = $signResult->data;
                    $signedError = $signResult->err;

                    if ($signature && !$signedError) {
                        $params['iltoken'] = base64_encode($signature);
                        break 2;
                    }
                }
            }
        }

        return $params;
    }
}
