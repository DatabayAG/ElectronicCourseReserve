<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";

/**
 * Class ilECRInfoScreenModifier
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilECRInfoScreenModifier implements ilECRBaseModifier
{
	/**
	 * @inheritdoc
	 */
	public function shouldModifyHtml($a_comp, $a_part, $a_par)
	{
		if ($a_par['tpl_id'] !== 'Services/InfoScreen/tpl.infoscreen.html') {
			return false;
		}

		if (!in_array(strtolower($_GET['cmdClass']), ['ilinfoscreengui', 'ilnotegui',])) {
			return false;
		}

		$refId = (int)$_GET['ref_id'];
		if (!$refId) {
			return false;
		}

		/** @var \ILIAS\Plugin\ElectronicCourseReserve\Objects\Helper $objectHelper */
		$objectHelper = $GLOBALS['DIC']['plugin.esa.object.helper'];

		$instance = $objectHelper->getInstanceByRefId($refId);
		if ($instance->getType() !== 'crs') {
			return false;
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function modifyHtml($a_comp, $a_part, $a_par)
	{
		/** @var \ILIAS\Plugin\ElectronicCourseReserve\Objects\Helper $objectHelper */
		$objectHelper = $GLOBALS['DIC']['plugin.esa.object.helper'];

		$instance = $objectHelper->getInstanceByRefId((int)$_GET['ref_id']);

		$dom = new \DOMDocument("1.0", "utf-8");
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = true;
		if (!@$dom->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"/></head><body>' . $a_par['html'] . '</body></html>')) {
			return ['mode' => \ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

		$firstInfoScreenSection = $dom->getElementById('infoscreen_section_1');

		$row = $dom->createElement('div');
		$row->setAttribute('class', 'form-group');
		$plugin = ilElectronicCourseReservePlugin::getInstance();
		$label = $dom->createElement('div', $plugin->txt('crs_ref_id'));
		$label->setAttribute('class', 'il_InfoScreenProperty control-label col-xs-3');
		$value = $dom->createElement('div');
		$value->setAttribute('class', 'il_InfoScreenPropertyValue col-xs-9');
		$value->nodeValue = (int)$instance->getRefId();
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
