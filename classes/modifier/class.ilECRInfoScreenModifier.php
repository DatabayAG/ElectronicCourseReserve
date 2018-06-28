<?php

require_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
/**
 * Class ilECRInfoScreenModifier
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilECRInfoScreenModifier implements ilECRBaseModifier
{
	public function __construct()
	{
	
	}
	
	public function shouldModifyHtml($a_comp, $a_part, $a_par)
	{
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		
		if($a_par['tpl_id'] == 'Services/InfoScreen/tpl.infoscreen.html' && $obj->getType() == 'crs')
		{
			return true;
		}
		return false;
	}
	
	public function modifyHtml($a_comp, $a_part, $a_par)
	{
		global $DIC; 
		
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		if(!($obj instanceof ilObjCourse) || !$DIC->access()->checkAccess('read', '', $obj->getRefId()))
		{
			//return parent::getHTML($a_comp, $a_part, $a_par);
			//Todo: we don't have a parent!
		}
		
		$html = $a_par['html'];
		
		$dom = new \DOMDocument("1.0", "utf-8");
		if(!@$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>'))
		{
			return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
		}
		
		$dom->encoding  = 'UTF-8';

		$infoscreen_section_8 = $dom->getElementById('infoscreen_section_8');
		
		$row = $dom->createElement('div');
		$row->setAttribute('class', 'form-group');
		$plugin = ilElectronicCourseReservePlugin::getInstance();
		$label = $dom->createElement('div', $plugin->txt('crs_ref_id'));
		$label->setAttribute('class', 'il_InfoScreenProperty control-label col-xs-3');
		$value = $dom->createElement('div');
		$value->setAttribute('class', 'il_InfoScreenPropertyValue col-xs-9');
		$value->nodeValue = (int)$obj->getRefId();
		$row->appendChild($label);
		$row->appendChild($value);

		$infoscreen_section_8->appendChild($row);
		
		$processed_html = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
		if(!$processed_html)
		{
			return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
		}
		return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $processed_html];
	}
}
