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
					['showcontent', 'render', 'view'])) &&
			!(in_array(strtolower($_GET['cmdClass']), ['ilrepositorygui',]) && in_array(strtolower($_GET['cmd']),
					['render',]))
		) {
			return false;
		}

		$refId = (int)$_GET['ref_id'];
		if (!$refId) {
			return false;
		}

		if (!\ilElectronicCourseReservePlugin::getInstance()->getSetting('token_append_to_bibl')) {
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
		global $DIC;

		/** @var \ILIAS\Plugin\ElectronicCourseReserve\Objects\Helper $objectHelper */
		$objectHelper = $GLOBALS['DIC']['plugin.esa.object.helper'];

		$instance = $objectHelper->getInstanceByRefId((int)$_GET['ref_id']);
		$parentCrsRefId = $DIC->repositoryTree()->checkForParentType($instance->getRefId(), 'crs', true);
		if (!$parentCrsRefId) {
			return ['mode' => \ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

		$crs = $objectHelper->getInstanceByRefId($parentCrsRefId);
		if (
			!($crs instanceof \ilObjCourse) ||
			!$DIC->access()->checkAccess('write', '', $crs->getRefId()) ||
			!\ilElectronicCourseReservePlugin::getInstance()->isAssignedToRequiredRole($DIC->user()->getId())) {
			return ['mode' => \ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

		require_once 'Modules/Bibliographic/classes/Admin/class.ilBibliographicSetting.php';
		$libs = \ilBibliographicSetting::getAll();
		$libsShownInList = array_filter($libs, function(\ilBibliographicSetting $libs) {
			return $libs->getShowInList();
		});

		if (0 === count($libsShownInList)) {
			return ['mode' => \ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

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

		/** @var \ILIAS\Plugin\ElectronicCourseReserve\Library\LinkBuilder $linkBuilder */
		$linkBuilder = $GLOBALS['DIC']['plugin.esa.library.linkbuilder'];
		$linkParameters = $linkBuilder->getLibraryUrlParameters($crs);

		foreach ($actionCells as $actionCell) {
			/** @var $actionCell DOMNode */

			$bibButtons = $xp->query('a', $actionCell);
			if ($bibButtons->length < 1) {
				continue;
			}

			foreach ($bibButtons as $bibButton) {
				$href = $bibButton->getAttribute('href');
				foreach ($linkParameters as $paramKey => $paramValue) {
					$href = \ilUtil::appendUrlParameterString($href, $paramKey . '=' . $paramValue);
				}
				$bibButton->setAttribute('href', $href);
			}
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
