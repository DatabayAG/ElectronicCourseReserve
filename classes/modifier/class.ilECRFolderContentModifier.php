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

class ilECRFolderContentModifier extends Base
{
    private static bool $contentModified = false;

    private ilContainerGUI $folderGui;

    private function initRendering(int $refId): void
    {
        $this->folderGui = new ilObjCategoryGUI([], $refId);
    }

    public function shouldModifyHtml($a_comp, $a_part, $a_par): bool
    {
        $refId = $this->getRefId();
        if ($refId <= 0) {
            $refId = $this->getTargetRefId();
        }
        if ($refId <= 0) {
            return false;
        }

        if (self::$contentModified) {
            return false;
        }

        $is_main_template = (
            $a_part === 'template_show' &&
            isset($a_par['tpl_id']) &&
            $a_par['tpl_id'] === 'src/UI/templates/default/Layout/tpl.standardpage.html'
        );

        $is_page_editor_content_template = (
            $a_part === 'template_get' &&
            isset($a_par['tpl_id']) &&
            $a_par['tpl_id'] === 'Services/Container/tpl.container_page.html'
        );

        if (!$is_page_editor_content_template && !$is_main_template) {
            return false;
        }

        $isFolder = $this->dic['ilObjDataCache']->lookupType($this->dic['ilObjDataCache']->lookupObjId($refId)) === 'fold';
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
            !ilSession::get('clipboard')
        );
    }

    public function modifyHtml($a_comp, $a_part, $a_par): array
    {
        self::$contentModified = true;

        $refId = $this->getRefId();
        if ($refId === 0) {
            $refId = $this->getTargetRefId();
        }

        $message = ilElectronicCourseReservePlugin::getInstance()->getFolderDeletionMessage($refId);

        if ($a_par['tpl_id'] === 'Services/Container/tpl.container_page.html') {
            return [
                'mode' => ilUIHookPluginGUI::PREPEND,
                'html' => $message
            ];
        }

        // There is no content in the folder, so appending the message is more difficult
        $uploadScriptsById = [];
        $a_par['html'] = preg_replace_callback(
            '#<script type="text/x-tmpl"[\s\S]*?</script>#i',
            static function ($matches) use (&$uploadScriptsById): string {
                $id = '###' . md5(uniqid((string) mt_rand(), true)) . '###';

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
