<?php


class ilElectronicCourseReserveListGUIHelper
{

    public array $actions_to_remove = array('cut', 'initTargetSelection', 'link');

    /**
     * @param DomXPath $xpath
     * @param int $item_ref_id
     * @param DOMDocument $dom
     * @param string $parent
     * @throws DOMException
     */
    public function replaceCheckbox(DomXPath $xpath, int $item_ref_id, DOMDocument $dom, string $parent = 'div'): void
    {
        $node_list = $xpath->query("//" . $parent . "/input[contains(@value,'" . $item_ref_id . "')]");
        $placeholder_div = $dom->createElement('div');
        $placeholder_div->setAttribute('style', 'width:15px');
        for ($i = 0; $i < count($node_list); $i++) {
            $node = $node_list->item($i);
            $node?->parentNode->replaceChild($placeholder_div, $node);
        }
    }

    /**
     * @param DOMNodeList $node_list
     */
    public function removeAction(DOMNodeList $node_list): void
    {
        for ($i = 0; $i < count($node_list); $i++) {
            $node = $node_list->item($i);
            $node?->parentNode->removeChild($node);
        }
    }

    /**
     * @param DomXPath $xpath
     * @return int
     */
    public function getRefIdFromItemUrl(DomXPath $xpath): int
    {
        $ref_id_node_list = $xpath->query("//a[@class='il_ContainerItemTitle']");
        $ref_id_node = $ref_id_node_list->item(0);
        if ($ref_id_node !== null) {
            $url_with_ref_id = $ref_id_node->getAttribute('href');
            $re = '/ref_id=(\d+)/m';
            preg_match($re, $url_with_ref_id, $matches);
            if (count($matches) > 1 && $matches[1] > 0) {
                return (int) $matches[1];
            } else {
                $re = '/target=file_(\d+)/m';
                preg_match($re, $url_with_ref_id, $matches);
                if (count($matches) > 1 && $matches[1] > 0) {
                    return (int) $matches[1];
                }

                $re = '/_file_(\d+)(.*?)\.html$/m';
                preg_match($re, $url_with_ref_id, $matches);
                if (count($matches) > 1 && $matches[1] > 0) {
                    return (int) $matches[1];
                }
            }
        }
        return 0;
    }
}