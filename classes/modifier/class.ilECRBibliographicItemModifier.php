<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php';

/**
 * Class ilECRBibliographicItemModifier
 * @author Michael Jansen <mjansen@databay.de>
 */
class ilECRBibliographicItemModifier implements \ilECRBaseModifier
{
	/**
	 * @inheritdoc
	 */
	public function shouldModifyHtml($a_comp, $a_part, $a_par)
	{
		if ($a_par['tpl_id'] !== 'Services/Table/tpl.table2.html') {
			return false;
		}

		if (
			!(in_array(strtolower($_GET['cmdClass']), ['ilobjbibliographicgui',]) && in_array(strtolower($_GET['cmd']),
					['showcontent', 'render'])) &&
			!(in_array(strtolower($_GET['cmdClass']), ['ilrepositorygui',]) && in_array(strtolower($_GET['cmd']),
					['render',]))
		) {
			return false;
		}

		$refId = (int)$_GET['ref_id'];
		if (!$refId) {
			return false;
		}

		/** @var \ILIAS\Plugin\ElectronicCourseReserve\Objects\Helper $objectHelper */
		$objectHelper = $GLOBALS['DIC']['plugin.esa.object.helper'];

		$instance = $objectHelper->getInstanceByRefId($refId);
		if ($instance->getType() !== 'bibl') {
			return false;
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function modifyHtml($a_comp, $a_part, $a_par)
	{
		$dom = new \DOMDocument("1.0", "utf-8");
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = true;
		if (!@$dom->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"/></head><body>' . $a_par['html'] . '</body></html>')) {
			return ['mode' => \ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

		$table = $dom->getElementById('tbl_bibl_overview');

		$xp = new DOMXPath($dom);
		$actionCells = $xp->query('tbody/tr/td[last()]', $table);
		if ($actionCells->length <= 0) {
			return ['mode' => \ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

		foreach ($actionCells as $actionCell) {
			/** @var $actionCell DOMNode */

			$bibButtons = $xp->query('a', $actionCell);
			if ($bibButtons->length !== 1) {
				continue;
			}

			$href = $bibButtons->item(0)->getAttribute('href');
			// TODO: Append token to OpenURL
			$href = \ilUtil::appendUrlParameterString($href, 'token=xxxxxxxxx');
			$bibButtons->item(0)->setAttribute('href', $href);
		}

		$processedHtml = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
		if (!$processedHtml) {
			return ['mode' => \ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

		return [
			'mode' => \ilUIHookPluginGUI::REPLACE,
			'html' => str_replace(['<body>', '</body>'], '', $processedHtml)
		];
	}
}
