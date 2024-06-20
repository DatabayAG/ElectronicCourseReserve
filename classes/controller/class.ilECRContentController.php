<?php

use ILIAS\Plugin\ElectronicCourseReserve\Library\LinkBuilder;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;

/**
 * Class ilECRContentController
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilECRContentController
{
    protected ilElectronicCourseReservePlugin $plugin_object;

    protected ilGlobalTemplateInterface $tpl;
    protected ilCtrlInterface $ctrl;
    protected ilTabsGUI $tabs;
    protected ilLanguage $lng;
    protected ilLogger $logger;
    protected ilSetting $settings;
    protected ilObjUser $user;
    protected ilAccessHandler $access;
    protected UIFactory $uiFactory;
    protected UIRenderer $uiRenderer;

    /**
     * ilECRContentController constructor.
     */
    public function __construct()
    {
        global $DIC;

        $this->plugin_object = ilElectronicCourseReservePlugin::getInstance();

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->tabs = $DIC->tabs();
        $this->lng = $DIC->language();
        $this->logger = $DIC->logger()->root();
        $this->settings = $DIC['ilSetting'];
        $this->user = $DIC->user();
        $this->access = $DIC->access();
        $this->uiFactory = $DIC->ui()->factory();
        $this->uiRenderer = $DIC->ui()->renderer();
    }

    /**
     *
     */
    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();
        if (method_exists($this, $cmd)) {
            $this->$cmd();
        }
    }

    /**
     * @throws ilObjectNotFoundException
     * @throws ilCtrlException
     * @throws ilDatabaseException
     * @throws ilTemplateException
     */
    private function checkUseAgreementCondition(): bool
    {
        $is_use_agreement_enabled = $this->plugin_object->getSetting('enable_use_agreement');
        if ($is_use_agreement_enabled) {
            return $this->checkUserAcceptance();
        } else {
            return true;
        }
    }

    /**
     * @throws ilObjectNotFoundException
     * @throws ilDateTimeException
     * @throws ilDatabaseException
     */
    private function printUserAgreementAcceptance(): void
    {
        $is_use_agreement_enabled = $this->plugin_object->getSetting('enable_use_agreement');
        if ($is_use_agreement_enabled) {
            $ref_id = (int) $_GET['ref_id'];
            $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);
            $ilUserAcceptance = new ilElectronicCourseReserveAcceptance($obj->getRefId());
            if ($ilUserAcceptance->hasUserAcceptedAgreement()) {
                $this->tpl->setOnScreenMessage("info", sprintf($this->plugin_object->txt('agr_accepted_on'),
                    ilDatePresentation::formatDate(new ilDateTime($ilUserAcceptance->getAcceptanceTimestamp(),
                        IL_CAL_UNIX))));
            }
        }
    }

    /**
     * @return bool
     * @throws ilCtrlException
     * @throws ilDatabaseException
     * @throws ilObjectNotFoundException
     * @throws ilTemplateException
     */
    private function checkUserAcceptance(): bool
    {
        $ref_id = (int) $_GET['ref_id'];
        $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);

        $ilUserAcceptance = new ilElectronicCourseReserveAcceptance($obj->getRefId());
        if ($ilUserAcceptance->hasUserAcceptedAgreement()) {
            return true;
        }

        $this->showUseAgreement();

        return false;
    }

    /**
     * @throws ilObjectNotFoundException
     * @throws ilCtrlException
     * @throws ilDatabaseException
     */
    public function handleAcceptanceCmd(): void
    {
        if (isset($_POST['cmd']['saveAcceptedUserAgreement'])) {
            $this->saveAcceptedUserAgreement();
        } else {
            $this->cancelAcceptance();
        }
    }

    /**
     * @throws ilCtrlException
     * @throws ilTemplateException
     */
    private function showUseAgreement(): void
    {
        $agreementTpl = $this->plugin_object->getTemplate('tpl.crs_acceptance_agreement.html');
        $agreementTpl->setVariable('TXT_USER_AGREEMENT', $this->plugin_object->txt('use_agreement'));

        $agreement = new ilElectronicCourseReserveAgreement();
        $agreement->loadByLang($this->user->getLanguage());
        $agreementTpl->setVariable('AGREEMENT_CONTENT', $agreement->getAgreement());

        $confirmBtn = $this->uiRenderer->render(
            $this->uiFactory->button()->standard(
                $this->lng->txt('confirm'),
                $this->ctrl->getLinkTargetByClass(['ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'],
                    'ilECRContentController.saveAcceptedUserAgreement')
            )
        );
        $cancelBtn = $this->uiRenderer->render(
            $this->uiFactory->button()->standard(
                $this->lng->txt('cancel'),
                $this->ctrl->getLinkTargetByClass(['ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'],
                    'ilECRContentController.cancelAcceptance')
            )
        );

        foreach ([$confirmBtn, $cancelBtn] as $btn) {
            $agreementTpl->setCurrentBlock('button');
            $agreementTpl->setVariable('BUTTON', $btn);
            $agreementTpl->parseCurrentBlock();
        }

        $this->tpl->setOnScreenMessage("question", $agreementTpl->get());

        $this->tpl->setContent('');
    }

    /**
     * @throws ilCtrlException
     */
    public function cancelAcceptance(): void
    {
        $ref_id = (int) $_GET['ref_id'];
        $this->ctrl->setParameterByClass('ilObjCourseGUI', 'ref_id', $ref_id);
        $url = $this->ctrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjCourseGUI'), 'view', '');

        $this->ctrl->redirectToURL($url);
    }

    /**
     * @throws ilObjectNotFoundException
     * @throws ilCtrlException
     * @throws ilDatabaseException
     */
    public function saveAcceptedUserAgreement(): void
    {
        $ref_id = (int) $_GET['ref_id'];
        $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);

        $ilUserAcceptance = new ilElectronicCourseReserveAcceptance($obj->getRefId());
        $ilUserAcceptance->saveUserAcceptance();
        $url = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'),
            'ilECRContentController.showECRContent', '');

        $this->tpl->setOnScreenMessage("success", $this->plugin_object->txt('ecr_accepted_agreement'), true);

        $this->ctrl->redirectToURL($url);
    }

    /**
     * @throws ilCtrlException
     * @throws ilObjectNotFoundException
     * @throws ilDatabaseException
     * @throws ilDateTimeException
     * @throws ilTemplateException
     */
    public function showECRContent(): string
    {
        $ref_id = (int) $_GET['ref_id'];
        $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);

        if (!$this->checkUseAgreementCondition()) {
            return '';
        }

        $this->printUserAgreementAcceptance();

        $this->tpl->setTitle($obj->getTitle());
        $this->tpl->setTitleIcon(ilUtil::getImagePath('icon_crs.svg'));

        $this->ctrl->setParameterByClass('ilObjCourseGUI', 'ref_id', $obj->getRefId());
        $this->tabs->setBackTarget($this->lng->txt('back'),
            $this->ctrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjCourseGUI'), 'view'));

        $ecr_content = ilElectronicCourseReserveLangData::lookupEcrContentByLangKey($this->user->getLanguage());
        $html = ilRTE::_replaceMediaObjectImageSrc($ecr_content, 1);
        if (strlen($html)) {

            return $this->replacePlaceholder($html);
        }

        $ecr_content = ilElectronicCourseReserveLangData::lookupEcrContentByLangKey($this->lng->getDefaultLanguage());
        $html = ilRTE::_replaceMediaObjectImageSrc($ecr_content, 1);
        if (strlen($html)) {
            return $this->replacePlaceholder($html);
        }

        return $this->getDefaultECRContent();
    }

    /**
     * @throws ilCtrlException
     */
    protected function getDefaultECRContent(): string
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin_object->txt('ecr_title'));

        $link = new ilNonEditableValueGUI($this->plugin_object->txt('ecr_url_search_system'), 'ecr', true);
        $url = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'),
            'ilECRContentController.performRedirect');
        $link->setValue('<a href="' . $url . '&pluginCmd=perform" target="_blank">' . $this->plugin_object->getSetting('url_search_system') . '</a>');

        $link->setInfo($this->plugin_object->txt('ecr_desc'));
        $form->addItem($link);

        return $form->getHTML();
    }

    /**
     * @throws ilObjectNotFoundException
     * @throws ilCtrlException
     * @throws ilDatabaseException
     */
    public function showECRItemContent(): string
    {
        $ref_id = (int) $_GET['ref_id'];
        $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);
        $item = $this->plugin_object->queryItemData($ref_id);

        $this->checkPermission();

        $this->tpl->setTitle($obj->getTitle());

        if ($obj->getType() === 'file') {
            if (array_key_exists('show_image', $item)
                && $item['show_image'] == 1
                && strlen($item['icon']) > 0) {
                $image_path = ILIAS_WEB_DIR . DIRECTORY_SEPARATOR . CLIENT_ID . DIRECTORY_SEPARATOR . $item['icon'];
                $this->tpl->setTitleIcon($image_path);
            } else {
                $this->tpl->setTitleIcon(ilUtil::getImagePath('icon_file.svg'));
            }
            $this->ctrl->setParameterByClass('ilObjFileGUI', 'ref_id', $obj->getRefId());
            $this->tabs->setBackTarget($this->lng->txt('back'),
                $this->ctrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjFileGUI'), 'infoScreen'));
        } else {
            if ($obj->getType() === 'webr') {
                if (array_key_exists('show_image', $item)
                    && $item['show_image'] == 1
                    && strlen($item['icon']) > 0) {
                    $image_path = ILIAS_WEB_DIR . DIRECTORY_SEPARATOR . CLIENT_ID . DIRECTORY_SEPARATOR . $item['icon'];
                    $this->tpl->setTitleIcon($image_path);
                } else {
                    $this->tpl->setTitleIcon(ilUtil::getImagePath('icon_webr.svg'));
                }
                $this->ctrl->setParameterByClass('ilObjLinkResourceGUI', 'ref_id', $obj->getRefId());
                $this->tabs->setBackTarget($this->lng->txt('back'),
                    $this->ctrl->getLinkTargetByClass(array('ilRepositoryGUI', 'ilObjLinkResourceGUI'), 'infoScreen'));
            }
        }

        $form = new ilPropertyFormGUI();
        $form->setFormAction($url = $this->ctrl->getLinkTargetByClass(array(
            'ilUIPluginRouterGUI',
            'ilElectronicCourseReserveUIHookGUI'
        ), 'ilECRContentController.updateItemSettings'));
        $form->addCommandButton('ilECRContentController', $this->lng->txt('submit'));
        $form->setTitle($this->plugin_object->txt('ecr_title'));

        $show_description = new ilCheckboxInputGUI($this->plugin_object->txt('show_description'), 'show_description');

        if (array_key_exists('show_description', $item)
            && $item['show_description'] == 1) {
            $show_description->setChecked(true);
        }
        $form->addItem($show_description);

        $show_image = new ilCheckboxInputGUI($this->plugin_object->txt('show_image'), 'show_image');
        if (array_key_exists('show_image', $item)
            && $item['show_image'] == 1) {
            $show_image->setChecked(true);
        }
        $form->addItem($show_image);

        $hidden = new ilHiddenInputGUI('ref_id');
        $hidden->setValue($ref_id);
        $form->addItem($hidden);

        if (strlen($item['raw_xml']) > 0) {
            $xml = simplexml_load_string($item['raw_xml']);

            $non_edit = new ilNonEditableValueGUI($this->plugin_object->txt('metadata'), '', true);
            $non_edit->setValue('<pre> ' . htmlspecialchars($xml->item->metadata->asXML()) . '</pre>');
            $form->addItem($non_edit);
        }

        return $form->getHTML();
    }

    /**
     * @throws ilCtrlException
     */
    protected function replacePlaceholder($html): string
    {
        $url = $this->ctrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'ilElectronicCourseReserveUIHookGUI'),
            'ilECRContentController.performRedirect');
        $esa_url = '<a href="' . $url . '&pluginCmd=perform" target="_blank">' . $this->plugin_object->getSetting('url_search_system') . '</a>';
        return str_replace('###URL_ESA###', $esa_url, $html);
    }

    /**
     * @throws ilCtrlException
     */
    public function updateItemSettings(): void
    {
        $show_description = (int) $_POST['show_description'];
        $show_image = (int) $_POST['show_image'];
        $ref_id = (int) $_POST['ref_id'];
        if ($ref_id > 0) {
            $this->plugin_object->updateItemData($ref_id, $show_description, $show_image);
        }

        $this->ctrl->redirect(new ilElectronicCourseReserveUIHookGUI(), 'ilECRContentController.showECRItemContent');
    }

    /**
     * @throws ilObjectNotFoundException
     * @throws ilDatabaseException
     */
    public function performRedirect(): ?string
    {
        $this->checkPermission();

        try {
            $ref_id = (int) $_GET['ref_id'];
            $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);

            /** @var LinkBuilder $linkBuilder */
            $linkBuilder = $GLOBALS['DIC']['plugin.esa.library.linkbuilder'];

            if($obj instanceof ilObjCourse){
                $url = $linkBuilder->getLibraryOrderLink($obj);

                ilUtil::redirect($url);
            } else {
                throw new Exception("Ref ID does not belong to an ilObjCourse");
            }

        } catch (Exception $e) {
            if (defined('DEVMODE') && DEVMODE) {
                $this->tpl->setOnScreenMessage("failure", $e->getMessage());
            } else {
                $this->logger->write($e->getMessage());
                $this->tpl->setOnScreenMessage("failure", $this->plugin_object->txt('ecr_sign_error_occured'));
            }
            return '';
        }
        return null;
    }


    /**
     * @throws ilObjectNotFoundException
     * @throws ilDatabaseException
     */
    public function checkPermission(string $permission = 'write'): void
    {
        $ref_id = (int) $_GET['ref_id'];
        $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);

        if (!(($obj instanceof ilObjCourse || $obj instanceof ilObjFile || $obj instanceof ilObjLinkResource)
            && $this->access->checkAccess($permission, '', $obj->getRefId())
            && $this->plugin_object->isAssignedToRequiredRole($this->user->getId()))) {
            $this->tpl->setOnScreenMessage("failure", $this->lng->txt("msg_no_perm_read"), true);
        }
    }
}