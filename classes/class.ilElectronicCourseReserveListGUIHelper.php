<?php


class ilElectronicCourseReserveListGUIHelper
{
	/**
	 * @var array
	 */
	public $actions_to_remove = array('delete', 'cut', 'initTargetSelection', 'link');

	/**
	 * @param DomXPath    $xpath
	 * @param int         $item_ref_id
	 * @param DOMDocument $dom
	 */
	public function replaceCheckbox($xpath, $item_ref_id, $dom)
	{
		$node_list = $xpath->query("//div/input[contains(@value,'" . $item_ref_id . "')]");
		for ($i = 0; $i < count($node_list); $i++) {
			$node = $node_list->item($i);
			if ($node !== null) {
				$placeholder_div = $dom->createElement('div');
				$placeholder_div->setAttribute('style', 'width:20px');
				$node->parentNode->replaceChild($placeholder_div, $node);
			}
		}
	}

	/**
	 * @param DOMNodeList $node_list
	 */
	public function removeAction($node_list)
	{
		for ($i = 0; $i < count($node_list); $i++) {
			$node = $node_list->item($i);
			if ($node !== null) {
				$node->parentNode->removeChild($node);
			}
		}
	}

	/**
	 * @param DomXPath $xpath
	 * @return int
	 */
	public function getRefIdFromItemUrl($xpath)
	{
		$ref_id_node_list = $xpath->query("//a[@class='il_ContainerItemTitle']");
		$ref_id_node      = $ref_id_node_list->item(0);
		$url_with_ref_id  = $ref_id_node->getAttribute('href');
		$re               = '/ref_id=(\d+)/m';
		preg_match($re, $url_with_ref_id, $matches);
		if (count($matches) > 1 && $matches[1] > 0) {
			return (int)$matches[1];
		} else {
			$re = '/target=file_(\d+)/m';
			preg_match($re, $url_with_ref_id, $matches);
			if (count($matches) > 1 && $matches[1] > 0) {
				return (int)$matches[1];
			}
		}
		return 0;
	}
}