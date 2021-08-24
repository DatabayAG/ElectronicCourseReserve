<?php

use ILIAS\Plugin\ElectronicCourseReserve\HttpContext\HttpContext;

require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/modifier/Base.php';
require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/interfaces/interface.ilECRBaseModifier.php';
require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReserveListGUIHelper.php';

/**
 * Class ilECRFolderContentModifier
 */
class ilECRFolderContentModifier extends Base implements ilECRBaseModifier
{
    /** @var ilObjFolder */
    private $folder;
    /** @var ilObjFolderGUI */
    private $folderGui;
    /** @var string */
    private $viewMode;
    /** @var bool */
    private static $contentModified = false;

    /**
     * @param int $refId
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     */
    private function initRendering(int $refId) : void
    {
        $this->folder = ilObjectFactory::getInstanceByRefId($refId);
        $this->folderGui = new ilObjCategoryGUI([], $refId);
        $this->viewMode = (
            ilContainer::_lookupContainerSetting($this->folder->getId(), 'list_presentation') === 'tile' &&
            !$this->folderGui ->isActiveAdministrationPanel()
        ) ? ilContainerContentGUI::VIEW_MODE_TILE : ilContainerContentGUI::VIEW_MODE_LIST;
    }

    /**
     * @inheritDoc
     */
    public function shouldModifyHtml($a_comp, $a_part, $a_par) : bool
    {
        $refId = $this->getRefId();
        if (0 === $refId) {
            $refId = $this->getTargetRefId();
        }

        if ($refId <= 0) {
            return false;
        }

        if (self::$contentModified) {
            return false;
        }

        $isMainTemplate = (
            $a_part === 'template_show' &&
            isset($a_par['tpl_id']) &&
            $a_par['tpl_id'] === 'tpl.main.html'
        );

        $isRelevantTemplate = (
            $a_part === 'template_get' &&
            isset($a_par['tpl_id']) &&
            $a_par['tpl_id'] === 'Services/Container/tpl.container_page.html'
        );
        
        if (!$isRelevantTemplate && !$isMainTemplate) {
            return false;
        }

        $isFolder = 'fold' === $this->dic['ilObjDataCache']->lookupType($this->dic['ilObjDataCache']->lookupObjId($refId));
        if (!$isFolder) {
            return false;
        }

        if (!ilElectronicCourseReservePlugin::getInstance()->hasFolderDeletionMessage($refId)) {
            return false;
        }

        $this->initRendering($refId);

        return (
            !$this->folderGui->isActiveAdministrationPanel() &&
            !$this->folderGui->isActiveItemOrdering() &&
            !$this->folderGui->isActiveOrdering() &&
            !$this->folderGui->isMultiDownloadEnabled() &&
            !$_SESSION['clipboard']
        );
    }

    /**
     * @inheritDoc
     */
    public function modifyHtml($a_comp, $a_part, $a_par) : array
    {
        self::$contentModified = true;

        $refId = $this->getRefId();
        if (0 === $refId) {
            $refId = $this->getTargetRefId();
        }

        $message =  ilElectronicCourseReservePlugin::getInstance()->getFolderDeletionMessage($refId);

        if ($a_par['tpl_id'] === 'Services/Container/tpl.container_page.html') {
            return [
                'mode' => ilUIHookPluginGUI::PREPEND,
                'html' => $message
            ];
        }

        // There is no content in the folder, so appending the message is more difficult
        $uploadScriptsById = [];
        $a_par['html'] = preg_replace_callback(
            '#<script type="text/x-tmpl"[\s\S]*?</script>#is',
            static function ($matches) use (&$uploadScriptsById) {
                $id = '###' . md5(uniqid((string) rand(), true)) . '###';

                $uploadScriptsById[$id] = $matches[0];

                return $id;
            },
            $a_par['html']
        );

        $document = $this->getDocumentForHtml(
            $a_par['html'],
            false
        );

        $messageDoc = new DOMDocument('1.0', 'utf-8');
        if (@$messageDoc->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $message . '</body></html>')) {
            $messageDoc->encoding = 'UTF-8';
            foreach ($messageDoc->getElementsByTagName('body')->item(0)->childNodes as $child) {
                $newSectionNode = $document->importNode($child, true);
                
                $document->getElementById('il_center_col')->appendChild($newSectionNode);
            }
        }

        $output = $this->getDocumentContentAsUiHookOutput($document, false);
        foreach ($uploadScriptsById as $id => $script) {
            $output['html'] = str_replace($id, $script, $output['html']);
        }

        return $output;
    }
}
