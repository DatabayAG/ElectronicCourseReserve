<?php

include_once "Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php";
/**
 * Class ilECRFolderListGuiModifier
 */
class ilECRFolderListGuiModifier implements ilECRBaseModifier
{
	public function __construct()
	{
	}

	public function shouldModifyHtml($a_comp, $a_part, $a_par)
	{
		$ref_id = (int)$_GET['ref_id'];
		$obj    = ilObjectFactory::getInstanceByRefId($ref_id, false);
		
		if($a_par['tpl_id'] == 'Services/Container/tpl.container_list_item.html' && $obj->getType() == 'fold')
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
		if(!($obj instanceof ilObjFolder) || !$DIC->access()->checkAccess('read', '', $obj->getRefId()))
		{
			return '';
		}
		
		$html = $a_par['html'];
		
		$dom = new \DOMDocument("1.0", "utf-8");
		if(!@$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>'))
		{
			return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
		}

		$dom->encoding  = 'UTF-8';
		$xpath = new DomXPath($dom);
		$text_node_list = $xpath->query("//div[@class='il_ContainerListItem']");
		$text_node = $text_node_list->item(0);
		//Todo replace with database value
		$text = $dom->createElement('div',  'My Great Custom Text');
		$text->setAttribute('class', 'il_MetaDataValue');
		$text_node->appendChild($text);

		$image_node_list = $xpath->query("//img[@class='ilListItemIcon']");
		$image_node = $image_node_list->item(0);
		//Todo replace with database value
		$image_node->setAttribute('src', 'http://placekitten.com/g/200/300');
		$processed_html = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
		if(!$processed_html)
		{
			return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
		}
		return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $processed_html];
	}
}
