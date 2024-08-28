<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;

class ilECRFileAndWebResourceImageGuiModifier implements ilECRBaseModifier
{
     /** @var list<string> */
    protected array $object_types = ['file', 'webr'];
    protected ilObjectDataCache $data_cache;
    protected ilAccessHandler $access;
    protected bool $modified = false;
    private WrapperFactory $httpWrapper;
    private Factory $refinery;

    public function __construct()
    {
        global $DIC;
        $this->access = $DIC->access();
        $this->data_cache = $DIC['ilObjDataCache'];
        $this->httpWrapper = $DIC->http()->wrapper();
        $this->refinery = $DIC->refinery();
    }

    public function shouldModifyHtml($a_comp, $a_part, $a_par): bool
    {
        if ($this->modified) {
            return false;
        }

        if (!$this->httpWrapper->query()->has('ref_id')) {
            return false;
        }
        $refId = $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());
        if (!$refId) {
            return false;
        }

        $obj_id = $this->data_cache->lookupObjId($refId);
        $type = $this->data_cache->lookupType($obj_id);

        if (in_array($type, $this->object_types, true)) {
            $this->modified = true;
            return true;
        }

        return false;
    }

    public function modifyHtml($a_comp, $a_part, $a_par): array
    {
        global $DIC;

        $refId = $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());

        $plugin = ilElectronicCourseReservePlugin::getInstance();
        $item_data = $plugin->queryItemData($refId);
        if (is_array($item_data) &&
            array_key_exists('icon', $item_data) && is_string($item_data['icon']) && $item_data['icon'] !== '' &&
            array_key_exists('show_image', $item_data) && is_numeric($item_data['show_image']) && (int) $item_data['show_image'] === 1) {

            $replace = '#headerimage';
            if (array_key_exists(
                'icon_type',
                $item_data
            ) && $item_data['icon_type'] === ilElectronicCourseReservePlugin::ICON_URL) {
                $with = $item_data['icon'];
            } else {
                $with = ILIAS_WEB_DIR . DIRECTORY_SEPARATOR . CLIENT_ID . DIRECTORY_SEPARATOR . $item_data['icon'];
            }

            $DIC->ui()->mainTemplate()->addJavaScript('Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/js/ElectronicCourseReserveObjectIcon.js');
            $DIC->ui()->mainTemplate()->addOnLoadCode('il.ElectronicCourseReserveObjectIcon.setConfig("' . $replace . '", "' . $with . '");');
        }

        return [
            'mode' => ilUIHookPluginGUI::KEEP,
            'html' => ''
        ];
    }
}
