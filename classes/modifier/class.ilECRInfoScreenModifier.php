<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\HTTP\Wrapper\WrapperFactory as WrapperFactoryAlias;
use ILIAS\Plugin\ElectronicCourseReserve\Objects\Helper;
use ILIAS\Refinery\Factory as FactoryAlias;

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";

/**
 * Class ilECRInfoScreenModifier
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilECRInfoScreenModifier implements ilECRBaseModifier
{

    protected ilObjectDataCache $data_cache;


    protected ilAccessHandler $access;
    protected WrapperFactoryAlias $httpWrapper;
    protected FactoryAlias $refinery;

    public function __construct()
    {
        global $DIC;
        $this->access = $DIC->access();
        $this->data_cache = $DIC['ilObjDataCache'];
        $this->httpWrapper = $DIC->http()->wrapper();
        $this->refinery = $DIC->refinery();
    }

    /**
     * @inheritdoc
     */
    public function shouldModifyHtml($a_comp, $a_part, $a_par): bool
    {
        if ($a_par['tpl_id'] != 'Services/InfoScreen/tpl.infoscreen.html') {
            return false;
        }

        if (!$this->httpWrapper->query()->has("cmdClass")) {
            return false;
        }

        if(!in_array(strtolower($this->httpWrapper->query()->retrieve("cmdClass", $this->refinery->kindlyTo()->string())),['ilinfoscreengui', 'ilnotegui',] )) {
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

        if ($type !== 'crs') {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     * @throws ilException
     * @throws DOMException
     */
    public function modifyHtml($a_comp, $a_part, $a_par): array
    {
        /** @var Helper $objectHelper */
        $objectHelper = $GLOBALS['DIC']['plugin.esa.object.helper'];

        $instance = $objectHelper->getInstanceByRefId($this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int()));

        $dom = new DOMDocument("1.0", "utf-8");
        if (!@$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $a_par['html'] . '</body></html>')) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $firstInfoScreenSection  = null;
        for ($i = 0; $i < 10; $i++) {
            $elm = $dom->getElementById('infoscreen_section_' . $i);
            if ($elm) {
                $firstInfoScreenSection = $elm;
            }
        }

        if (!$firstInfoScreenSection) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $row = $dom->createElement('div');
        $row->setAttribute('class', 'form-group');
        $plugin = ilElectronicCourseReservePlugin::getInstance();
        $label = $dom->createElement('div', $plugin->txt('crs_ref_id'));
        $label->setAttribute('class', 'il_InfoScreenProperty control-label col-xs-3');
        $value = $dom->createElement('div');
        $value->setAttribute('class', 'il_InfoScreenPropertyValue col-xs-9');
        $value->nodeValue = $instance->getRefId();
        $row->appendChild($label);
        $row->appendChild($value);

        $firstInfoScreenSection->appendChild($row);

        $processedHtml = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
        if (!$processedHtml) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $processedHtml];
    }
}
