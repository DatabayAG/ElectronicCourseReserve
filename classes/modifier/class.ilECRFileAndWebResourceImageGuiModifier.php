<?php

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveListGUIHelper.php";

/**
 * Class ilECRFileAndWebResourceImageGuiModifier
 */
class ilECRFileAndWebResourceImageGuiModifier implements ilECRBaseModifier
{

    protected array $object_types = array('file', 'webr');

    protected ilObjDataCache $data_cache;


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

    /**
     * @param $a_comp
     * @param $a_part
     * @param $a_par
     * @return bool
     */
    public function shouldModifyHtml($a_comp, $a_part, $a_par): bool
    {
        if ($this->modified) {
            return false;
        }

        if(!$this->httpWrapper->query()->has('ref_id')){
            return false;
        }
        $refId = $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());
        if (!$refId) {
            return false;
        }

        $obj_id = $this->data_cache->lookupObjId($refId);
        $type = $this->data_cache->lookupType($obj_id);

        if (in_array($type, $this->object_types)) {
            $this->modified = true;
            return true;
        }
        return false;
    }

    /**
     * @param $a_comp
     * @param $a_part
     * @param $a_par
     */
    public function modifyHtml($a_comp, $a_part, $a_par): array
    {
        global $DIC;
        $plugin = ilElectronicCourseReservePlugin::getInstance();
        $refId = $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());;
        $item_data = $plugin->queryItemData($refId);
        if (is_array($item_data)
            && array_key_exists('icon', $item_data)
            && strlen($item_data['icon']) > 0
            && array_key_exists('show_image', $item_data)
            && $item_data['show_image'] == 1) {

            $replace = '#headerimage';
            if (array_key_exists('icon_type',
                    $item_data) && $item_data['icon_type'] === ilElectronicCourseReservePlugin::ICON_URL) {
                $with = $item_data['icon'];
            } else {
                $with = ILIAS_WEB_DIR . DIRECTORY_SEPARATOR . CLIENT_ID . DIRECTORY_SEPARATOR . $item_data['icon'];
            }

            $DIC->ui()->mainTemplate()->addJavaScript('Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/js/ElectronicCourseReserveObjectIcon.js');
            $DIC->ui()->mainTemplate()->addOnLoadCode('il.ElectronicCourseReserveObjectIcon.setConfig("' . $replace . '", "' . $with . '");');
        }
        return [];
    }
}
