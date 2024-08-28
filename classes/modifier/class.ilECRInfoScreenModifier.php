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

use ILIAS\HTTP\Wrapper\WrapperFactory as WrapperFactoryAlias;
use ILIAS\Plugin\ElectronicCourseReserve\Objects\Helper;
use ILIAS\Refinery\Factory as FactoryAlias;

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

    public function shouldModifyHtml($a_comp, $a_part, $a_par): bool
    {
        if ($a_par['tpl_id'] !== 'Services/InfoScreen/tpl.infoscreen.html') {
            return false;
        }

        if (!$this->httpWrapper->query()->has("cmdClass")) {
            return false;
        }

        if (!in_array(strtolower($this->httpWrapper->query()->retrieve("cmdClass", $this->refinery->kindlyTo()->string())), ['ilinfoscreengui', 'ilnotegui',])) {
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

        if ($type !== 'crs') {
            return false;
        }

        return true;
    }

    public function modifyHtml($a_comp, $a_part, $a_par): array
    {
        /** @var Helper $objectHelper */
        $objectHelper = $GLOBALS['DIC']['plugin.esa.object.helper'];

        $instance = $objectHelper->getInstanceByRefId($this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int()));

        $dom = new DOMDocument("1.0", "utf-8");
        if (!@$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $a_par['html'] . '</body></html>')) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $firstInfoScreenSection = null;
        for ($i = 0; $i < 10; $i++) {
            // The index is not always 1, so we make 10 attempts to retrieve a matching element
            $elm = $dom->getElementById('infoscreen_section_' . $i);
            if ($elm) {
                $firstInfoScreenSection = $elm;
                break;
            }
        }

        if (!$firstInfoScreenSection) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $row = $dom->createElement('div');
        $row->setAttribute('class', 'form-group row');
        $plugin = ilElectronicCourseReservePlugin::getInstance();
        $label = $dom->createElement('div', $plugin->txt('crs_ref_id'));
        $label->setAttribute('class', 'il_InfoScreenProperty control-label col-sm-4 col-md-3 col-lg-2');
        $value = $dom->createElement('div');
        $value->setAttribute('class', 'il_InfoScreenPropertyValue col-sm-8 col-md-9 col-lg-10');
        $value->nodeValue = (string) $instance->getRefId();
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
