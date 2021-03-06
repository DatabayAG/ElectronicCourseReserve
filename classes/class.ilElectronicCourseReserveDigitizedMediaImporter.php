<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\Plugin\ElectronicCourseReserve\Filesystem\Purger;
use ILIAS\Plugin\ElectronicCourseReserve\Logging\Log;

require_once 'Modules/Course/classes/class.ilObjCourse.php';
require_once 'Modules/File/classes/class.ilObjFile.php';
require_once 'Modules/Folder/classes/class.ilObjFolder.php';
require_once 'Modules/WebResource/classes/class.ilLinkResourceItems.php';
require_once 'Modules/WebResource/classes/class.ilObjLinkResource.php';
require_once 'Services/Cron/classes/class.ilCronJobResult.php';
require_once 'Services/Cron/classes/class.ilCronManager.php';
require_once 'Services/Mail/classes/class.ilMail.php';
require_once 'Services/Mail/classes/class.ilMimeMail.php';
require_once 'Services/MediaObjects/classes/class.ilObjMediaObject.php';
require_once 'Services/Utilities/classes/class.ilMimeTypeUtil.php';
require_once 'Services/WebServices/SOAP/classes/class.ilSoapClient.php';
require_once 'Services/WebServices/Rest/classes/class.ilRestFileStorage.php';
require_once 'Services/Xml/classes/class.ilXmlWriter.php';
require_once dirname(__FILE__) . '/class.ilElectronicCourseReserveHistoryEntity.php';
require_once 'Customizing/global/plugins/Services/Cron/CronHook/CronElectronicCourseReserve/classes/class.ilElectronicCourseReserveParser.php';
require_once 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/classes/class.ilElectronicCourseReservePostPurifier.php';

class ilElectronicCourseReserveDigitizedMediaImporter
{
    /**
     * @var string
     */
    const ITEM_TYPE_FILE = 'file';

    /**
     * @var string
     */
    const ITEM_TYPE_URL = 'url';

    /**
     * @var string
     */
    const IMPORT_DIR = 'ecr_import';

    /**
     * @var string
     */
    const BACKUP_DIR = 'ecr_import_backup';

    /**
     * @var string
     */
    const PATH_TO_XSD = 'Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/ElectronicCourseReserve/xsd/import.xsd';

    /**
     * @var string
     */
    const IMAGE_DIR = 'ecr_images';

    /**
     * @var boolean
     */
    const DELETE_FILES = true;

    /**
     * @var string
     */
    const ESA_FOLDER_IMPORT_PREFIX = 'esa_';

    /**
     * @var $logger Log
     */
    protected $logger;

    /**
     * @var $user ilObjUser
     */
    protected $user;

    /**
     * @var ilMailMimeSender|string
     */
    protected $from;

    /**
     * @var array
     */
    protected $valid_items = array(self::ITEM_TYPE_FILE, self::ITEM_TYPE_URL);

    /**
     * @var ilElectronicCourseReservePlugin
     */
    public $pluginObj = null;

    /**
     * @var ILIAS\Plugin\ElectronicCourseReserve\Locker\LockerInterface
     */
    protected $lock;

    /**
     *
     */
    public function __construct()
    {
        global $DIC;

        $factory = null;
        if (isset($GLOBALS['DIC']['mail.mime.sender.factory'])) {
            $factory = $GLOBALS['DIC']['mail.mime.sender.factory'];
        } elseif (isset($GLOBALS['mail.mime.sender.factory'])) {
            $factory = $GLOBALS['mail.mime.sender.factory'];
        }

        if ($factory !== null && $factory instanceof ilMailMimeSenderFactory) {
            $this->from = $factory->system();
        } else {
            $this->from = ilMail::getIliasMailerAddress();
        }

        $this->pluginObj = ilPlugin::getPluginObject('Services', 'UIComponent', 'uihk', 'ElectronicCourseReserve');
        $this->lock = $DIC['plugin.esa.locker'];
        $this->user = $DIC->user();

        $this->logger = $DIC['plugin.esa.cronjob.logger'];
    }

    /**
     * @param string $job_id
     */
    public function run($job_id)
    {
        $this->perform($job_id);
    }

    /**
     * @param string $job_id
     */
    protected function perform($job_id)
    {
        $this->logger->info('Digitized media import script started');
        try {
            $this->ensureUserRelatedPreconditions();
            $this->ensureSystemPreconditions();

            if ($this->lock->acquireLock()) {
                $this->logger->info('Acquired lock.');
            } else {
                $this->logger->info('Script is probably running, please remove the lock if you are sure no task is running.');
                return;
            }

            $this->cleanUpDeletedObjects();

            $this->logger->info('Started determination with file pattern.');

            $dir = $this->getImportDir();
            ilUtil::makeDirParents($dir);
            $iter = new RegexIterator(
                new DirectoryIterator($dir),
                '/(.*).xml/'
            );
            foreach ($iter as $file_info) {
                ilCronManager::ping($job_id);

                /**
                 * @var $file_info SplFileInfo
                 */
                if ($file_info->isDir()) {
                    continue;
                }

                $pathname = $file_info->getPathname();
                $filename = $file_info->getFileName();

                $this->logger->info('Found file to import: ' . $filename);
                $this->logger->info('Pathname: ' . $pathname);

                $content = @file_get_contents($pathname);

                $valid = $this->validateXmlAgainstXsd($filename, $content);
                if ($valid === true) {
                    $this->logger->info('MD5 checksum: ' . md5($content));
                    $this->logger->info('SHA1 checksum: ' . sha1($content));

                    $parser = new ilElectronicCourseReserveParser($pathname);
                    $parser->startParsing();
                    $parsed_item = $parser->getElectronicCourseReserveContainer();

                    if (!in_array($parsed_item->getType(), $this->valid_items)) {
                        $this->logger->info(sprintf('Type of item (%s) is unknown, skipping item.',
                            $parsed_item->getType()));
                        continue;
                    }

                    $this->logger->info('Starting item creation...');
                    if ($parsed_item->getType() === self::ITEM_TYPE_FILE) {
                        if (!$this->createFileItem($parsed_item, $content)) {
                            $msg = sprintf($this->pluginObj->txt('error_create_file_mail'), $parsed_item->getCrsRefId(),
                                $parsed_item->getFolderImportId());
                            $this->sendMailOnError($msg);
                        }
                    } else {
                        if ($parsed_item->getType() === self::ITEM_TYPE_URL) {
                            if (!$this->createWebResourceItem($parsed_item, $content)) {
                                $msg = sprintf($this->pluginObj->txt('error_create_url_mail'),
                                    $parsed_item->getCrsRefId(), $parsed_item->getFolderImportId());
                                $this->sendMailOnError($msg);
                            }
                        }
                    }
                    $this->logger->info('...item creation done.');

                    if (!$this->moveXmlToBackupFolder($pathname)) {
                        $msg = sprintf($this->pluginObj->txt('error_move_mail'), $pathname);
                        $this->sendMailOnError($msg);
                    }
                } else {
                    $this->sendMailOnError($valid, $pathname);
                }
            }
            $this->cleanUpFileSystem();
        } catch (ilException $e) {
            $this->logger->info($e->getMessage());
        }

        try {
            $this->lock->releaseLock();
            $this->logger->info('Released lock.');
        } catch (ilException $e) {
            $this->logger->info($e->getMessage());
        }

        $this->logger->info('Digitized media import script finished');
    }

    /**
     * @param string $filename
     * @param string $xml_string
     * @return bool|string
     */
    protected function validateXmlAgainstXsd($filename, $xml_string)
    {
        $this->logger->info('Started XML validation');

        libxml_use_internal_errors(true);
        $xml = new DOMDocument();
        $xml->loadXML($xml_string);

        if (!$xml->schemaValidate(self::PATH_TO_XSD)) {
            $errors = libxml_get_errors();
            $error_msg = '';
            foreach ($errors as $error) {
                $error_msg .= sprintf("\n" . 'XML error "%s" [%d] (Code %d) in %s on line %d column %d' . "\n",
                    $error->message, $error->level, $error->code, $error->file,
                    $error->line, $error->column);
            }
            $msg = sprintf($this->pluginObj->txt('error_with_xml_validation'), $filename, $error_msg);
            libxml_clear_errors();
            libxml_use_internal_errors(false);

            $this->logger->info('Finished XML validation');

            return $msg;
        }
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $this->logger->info('Finished XML validation');

        return true;
    }

    /**
     *
     */
    protected function cleanUpFileSystem()
    {
        $purger = new Purger(
            $this->logger, ilUtil::getDataDir() . DIRECTORY_SEPARATOR . self::BACKUP_DIR
        );
        $purger->purge();
    }

    /**
     * @param string $path_to_file
     * @return bool
     */
    protected function moveXmlToBackupFolder($path_to_file)
    {
        if (file_exists($path_to_file)) {
            $dir = ilUtil::getDataDir() . DIRECTORY_SEPARATOR . self::BACKUP_DIR . DIRECTORY_SEPARATOR . date("Y-m-d");
            if (!is_dir($dir)) {
                ilUtil::makeDirParents($dir);
            }
            try {
                if (file_exists($path_to_file)) {
                    copy($path_to_file, $dir . DIRECTORY_SEPARATOR . basename($path_to_file));
                    if (file_exists($dir . DIRECTORY_SEPARATOR . basename($path_to_file))) {
                        if (self::DELETE_FILES) {
                            unlink($path_to_file);
                        }
                    }
                }
                return true;
            } catch (ilException $e) {
                $this->logger->info($e->getMessage());
                return false;
            }
        } else {
            $this->logger->info(sprintf('File (%s) not found can not move it., skipping item.', $path_to_file));
            return false;
        }
    }

    /**
     * @param ilElectronicCourseReserveContainer $parsed_item
     * @param $folder_import_id
     * @param $crs_ref_id
     * @return int
     */
    protected function createFolder($parsed_item, $folder_import_id, $crs_ref_id)
    {
        $fold = new ilObjFolder();
        $fold->setTitle($parsed_item->getLabel());
        $fold->setImportId($folder_import_id);
        $fold->create();
        $fold->createReference();
        $fold->putInTree($crs_ref_id);
        $fold->setPermissions($crs_ref_id);
        $fold->update();
        $this->writeFolderCreationToDB($fold->getRefId(), $folder_import_id, $crs_ref_id);
        return $fold->getRefId();
    }

    /**
     * @param ilElectronicCourseReserveContainer $parsed_item
     * @param $ref_id
     */
    protected function updateFolderTitle($parsed_item, $ref_id)
    {
        if ($parsed_item->getOverwrite() == 1) {
            $fold = new ilObjFolder($ref_id);
            if ($parsed_item->getLabel() != $fold->getTitle()) {
                $this->logger->info(sprintf('Title for folder (ref_id: %s), get updated from "%s" to "%s".', $ref_id,
                    $fold->getTitle(), $parsed_item->getItem()->getLabel()));
                $fold->setTitle($parsed_item->getLabel());
                $fold->update();
            }
        } else {
            $this->logger->info(sprintf('Title for folder (ref_id: %s), is not updated overwrite is disabled in xml.',
                $ref_id));
        }
    }

    /**
     * @param ilElectronicCourseReserveContainer $parsed_item
     * @param $raw_xml
     * @return bool
     * @throws ilFileUtilsException
     */
    protected function createFileItem($parsed_item, $raw_xml)
    {
        $folder_ref_id = (int) $this->ensureCorrectCourseAndFolderStructure($parsed_item);
        $file_path = $this->getResolvedFilePath($parsed_item->getItem()->getFile());

        if ($folder_ref_id != 0 && file_exists($file_path)) {

            $filename = basename($parsed_item->getItem()->getFilename());
            $new_file = new ilObjFile();
            $file_type = pathinfo($parsed_item->getItem()->getFile(), PATHINFO_EXTENSION);
            $new_file->setTitle($parsed_item->getItem()->getLabel() . '.' . $file_type);
            $new_file->setFileType($file_type);
            $new_file->setFileName($filename);
            $new_file->setVersion(1);
            $new_file->create();
            $new_file->setFilename($new_file->getFileName());
            $new_file->addNewsNotification("file_updated");
            $new_file->createReference();
            $new_file->putInTree($folder_ref_id);
            $new_file->setPermissions($folder_ref_id);
            $new_file->update();
            $dir = $new_file->getDirectory(1);
            if (!is_dir($dir)) {
                ilUtil::makeDirParents($dir);
            }

            if (file_exists($file_path)) {
                copy($file_path, $dir . '/' . $filename);
                if (file_exists($dir . '/' . $filename)) {
                    if (self::DELETE_FILES) {
                        unlink($file_path);
                    }
                }
            }

            $new_file->determineFileSize();
            $new_file->update();

            $this->writeDescriptionToDB($parsed_item, $new_file->getRefId(), $raw_xml, $folder_ref_id);
            return true;
        } else {
            if ($folder_ref_id === 0) {
                $this->logger->info('Could not find/create course/folder structure, skipping item.');
                return false;
            } else {
                $this->logger->info(sprintf('File %s not found for item %s, skipping item creation.',
                    $parsed_item->getItem()->getFile(), $parsed_item->getLabel()));
                return false;
            }
        }
    }

    /**
     * @param ilElectronicCourseReserveContainer $parsed_item
     * @param $raw_xml
     * @return bool
     * @throws ilFileUtilsException
     */
    protected function createWebResourceItem($parsed_item, $raw_xml)
    {
        $folder_ref_id = $this->ensureCorrectCourseAndFolderStructure($parsed_item);
        if (strlen($parsed_item->getItem()->getUrl()) > 0 &&
            $folder_ref_id != 0) {
            $new_link = new ilObjLinkResource();
            $new_link->setTitle($parsed_item->getLabel());
            $new_link->create();
            $new_link->createReference();
            $new_link->putInTree($folder_ref_id);
            $new_link->setPermissions($folder_ref_id);
            $link_item = new ilLinkResourceItems($new_link->getId());
            $link_item->setTitle($parsed_item->getItem()->getLabel());
            $link_item->setActiveStatus(1);
            $link_item->setValidStatus(1);
            $link_item->setTarget($parsed_item->getItem()->getUrl());
            $link_item->setInternal(false);
            $link_item->add();
            $this->writeDescriptionToDB($parsed_item, $new_link->getRefId(), $raw_xml, $folder_ref_id);
            return true;
        } else {
            $this->logger->info(sprintf('No url given for %s, skipping item creation.', $parsed_item->getLabel()));
            return false;
        }
    }

    /**
     * @param ilElectronicCourseReserveContainer $parsed_item
     * @param $new_obj_ref_id
     * @param $raw_xml
     * @param $folder_ref_id
     * @throws ilFileUtilsException
     */
    protected function writeDescriptionToDB($parsed_item, $new_obj_ref_id, $raw_xml, $folder_ref_id)
    {
        global $DIC;
        $version = 1;
        $res = $DIC->database()->queryF(
            'SELECT version FROM ecr_description WHERE ref_id = %s',
            array('integer'),
            array($new_obj_ref_id)
        );
        $row = $DIC->database()->fetchAssoc($res);
        $purifier = new ilElectronicCourseReservePostPurifier();
        $description = $purifier->purify($parsed_item->getItem()->getDescription());
        $medatata = $purifier->purify($parsed_item->getItem()->getMetadata());

        if (is_array($row) && array_key_exists('version', $row)) {
            $version = $row['version'] + 1;
        }

        $icon = $this->getIcon($parsed_item, $new_obj_ref_id);
        $DIC->database()->insert('ecr_description', array(
            'ref_id' => array('integer', $new_obj_ref_id),
            'version' => array('integer', $version),
            'timestamp' => array('integer', strtotime($parsed_item->getTimestamp())),
            'icon' => array('text', $icon['icon']),
            'icon_type' => array('text', $icon['icon_type']),
            'description' => array('clob', $description),
            'metadata' => array('clob', $medatata),
            'raw_xml' => array('clob', $raw_xml),
            'folder_ref_id' => array('integer', $folder_ref_id)
        ));
    }

    /**
     * @param ilElectronicCourseReserveContainer $parsed_item
     * @param int $new_obj_ref_id
     * @return array
     * @throws ilFileUtilsException
     */
    protected function getIcon($parsed_item, $new_obj_ref_id)
    {
        $icon_type = $this->evaluateIconType($parsed_item->getItem()->getIcon());
        $pl = $this->pluginObj;
        $valid_icon_types = array('jpeg', 'jpg', 'png', 'svg');

        if ($icon_type === $pl::ICON_URL) {
            return array('icon' => $parsed_item->getItem()->getIcon(), 'icon_type' => $icon_type);
        } else {
            $file = $this->getResolvedFilePath($parsed_item->getItem()->getIcon());

            if (file_exists($file)) {
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array(strtolower($extension), $valid_icon_types)) {
                    if (file_exists($file)) {
                        $dir = $this->getImageFolder($new_obj_ref_id);
                        $filename = basename($parsed_item->getItem()->getIcon());
                        $target = $dir . DIRECTORY_SEPARATOR . $filename;
                        if (file_exists($file)) {
                            copy($file, $target);
                        }
                        if (file_exists($target)) {
                            $file_path = './' . self::IMAGE_DIR . DIRECTORY_SEPARATOR . $new_obj_ref_id . DIRECTORY_SEPARATOR . $filename;
                            if (self::DELETE_FILES) {
                                unlink($file);
                            }
                            return array('icon' => $file_path, 'icon_type' => $icon_type);
                        }
                    }
                } else {
                    $this->logger->warn(sprintf('File of type %s, is not a valid icon type, skipping icon for course ref id %s and folder import id %s.',
                        $extension, $parsed_item->getCrsRefId(), $parsed_item->getFolderImportId()));
                }
            } else {
                $this->logger->warn(sprintf('No file found either under the absolute or relative path for file %s in course %s and folder %s.',
                    $parsed_item->getItem()->getIcon(), $parsed_item->getCrsRefId(),
                    $parsed_item->getFolderImportId()));
            }

        }
        return array('icon' => '', 'icon_type' => '');
    }

    /**
     * @param string $given_file_path
     * @return string
     */
    protected function getResolvedFilePath($given_file_path)
    {
        $resolved_file_path = realpath(implode(DIRECTORY_SEPARATOR, [
            rtrim($this->getImportDir(), DIRECTORY_SEPARATOR),
            ltrim($given_file_path, DIRECTORY_SEPARATOR),
        ]));

        if (!file_exists($resolved_file_path) && file_exists($given_file_path)) {
            $resolved_file_path = $given_file_path;
        }

        return $resolved_file_path;
    }

    /**
     * @param int $new_obj_ref_id
     * @return string
     */
    protected function getImageFolder($new_obj_ref_id)
    {
        $dir = CLIENT_WEB_DIR . DIRECTORY_SEPARATOR . self::IMAGE_DIR . DIRECTORY_SEPARATOR . $new_obj_ref_id . DIRECTORY_SEPARATOR;
        ilUtil::makeDirParents($dir);
        return $dir;
    }

    /**
     * @param $icon
     * @return bool
     */
    protected function evaluateIconType($icon)
    {
        if (strlen($icon) === 0) {
            return false;
        }

        $pl = $this->pluginObj;
        preg_match('/http(s)?:\/\//', $icon, $matches);
        if (count($matches) > 0) {
            return $pl::ICON_URL;
        } else {
            return $pl::ICON_FILE;
        }
    }

    /**
     * @param $ref_id
     * @param $import_id
     * @param $crs_ref_id
     */
    protected function writeFolderCreationToDB($ref_id, $import_id, $crs_ref_id)
    {
        global $DIC;

        $DIC->database()->insert('ecr_folder', array(
            'ref_id' => array('integer', $ref_id),
            'import_id' => array('integer', $import_id),
            'crs_ref_id' => array('integer', $crs_ref_id)
        ));
    }

    /**
     * @throws ilException
     */
    protected function ensureSystemPreconditions()
    {
        global $DIC;
        $ilSetting = $DIC['ilSetting'];

        if (!$ilSetting->get('soap_user_administration')) {
            throw new ilException('Please enable soap in the ILIAS administration.');
        }
    }

    /**
     */
    protected function cleanUpDeletedObjects()
    {
        global $DIC;
        $DIC->database()->manipulate(
            'DELETE ecr.* FROM ecr_description ecr LEFT JOIN object_reference ON object_reference.ref_id = ecr.ref_id WHERE object_reference.ref_id IS NULL');
        $this->logger->info('Removed deleted entries from table ecs_description.');
    }

    /**
     * @throws ilException
     */
    protected function ensureUserRelatedPreconditions()
    {
        if ($this->user->hasToAcceptTermsOfService()) {
            throw new ilException('The passed ILIAS user has to accept the user agreement.');
        }
    }

    /**
     * @param ilElectronicCourseReserveContainer $parsed_item
     * @return int
     */
    protected function ensureCorrectCourseAndFolderStructure($parsed_item)
    {
        $crs_ref_id = (int) $parsed_item->getCrsRefId();
        $folder_import_id = (int) $parsed_item->getFolderImportId();
        $folder_import_id_prefix = self::ESA_FOLDER_IMPORT_PREFIX . $folder_import_id;

        if ($crs_ref_id === null || $crs_ref_id === 0 || $folder_import_id === null || $folder_import_id === 0) {
            $this->logger->info(sprintf('Import id (%s) or Course Ref id (%s) was not set, skipping this one.',
                $folder_import_id_prefix, $crs_ref_id));
            return 0;
        }

        /**
         * @var $ilObjDataCache ilObjectDataCache
         * @var $tree ilTree
         */
        global $ilObjDataCache, $tree;

        $crs_obj_id = (int) $ilObjDataCache->lookupObjId($crs_ref_id);
        if ($crs_obj_id > 0 && $ilObjDataCache->lookupType($crs_obj_id) === 'crs' && !ilObject::_isInTrash($crs_ref_id)) {
            $this->logger->info(sprintf('Found course for ref_id, looking for folder.', $crs_ref_id));
            $folder_obj_id = ilObject::_lookupObjIdByImportId($folder_import_id_prefix);
            if ($folder_obj_id === 0) {
                $this->logger->info(sprintf('Folder with Import id (%s) not found creating new folder.',
                    $folder_import_id_prefix));
                return $this->createFolder($parsed_item, $folder_import_id_prefix, $crs_ref_id);
            } else {
                if ($ilObjDataCache->lookupType($folder_obj_id) === 'fold') {
                    $this->logger->info(sprintf('Found folder with Import id (%s).', $folder_import_id_prefix));
                    $ref_ids = ilObject::_getAllReferences($folder_obj_id);
                    $ref_id = current($ref_ids);
                    $this->updateFolderTitle($parsed_item, $ref_id);
                    if ($ref_id != null && $ref_id > 0 && !ilObject::_isInTrash($ref_id)) {
                        $parent = (int) $tree->getParentId($ref_id);
                        if ($parent === $crs_ref_id) {
                            return $ref_id;
                        } else {
                            $this->logger->info(sprintf('Folder with Import id (%s) not at the correct course %s.',
                                $folder_import_id_prefix, $crs_ref_id));
                        }
                    } else {
                        if ($ref_id > 0 && ilObject::_isInTrash($ref_id)) {
                            $this->logger->info(sprintf('Object with ref_id (%s) is in trash, skipping.', $ref_id));
                        }
                    }
                } else {
                    $this->logger->info(sprintf('Object with Import id (%s) is not of type folder (%s).',
                        $folder_import_id_prefix, $ilObjDataCache->lookupType($folder_obj_id)));
                }
            }
        } else {
            if ($crs_obj_id > 0 && ilObject::_isInTrash($crs_ref_id)) {
                $this->logger->info(sprintf('Object with ref_id (%s) is in trash, skipping.', $crs_ref_id));
            } else {
                if ($crs_obj_id > 0 && $ilObjDataCache->lookupType($crs_obj_id) !== 'crs') {
                    $this->logger->info(sprintf('Ref id (%s) does not belong to a course its a %s instead, skipping.',
                        $crs_ref_id, $ilObjDataCache->lookupType($crs_obj_id)));
                } else {
                    if ($crs_obj_id == 0) {
                        $this->logger->info(sprintf('No course found with ref_id %s, skipping.', $crs_ref_id));
                    }
                }
            }
        }
        return 0;
    }

    /**
     * @param $msg
     * @param $attachment
     */
    protected function sendMailOnError($msg, $attachment = null)
    {
        if ((int) $this->pluginObj->getSetting('is_mail_enabled') === 1) {
            $mail = new ilMimeMail();
            $mail->From($this->from);
            $recipients = $this->pluginObj->getSetting('mail_recipients');
            $mail->To($this->getEmailsForRecipients($recipients));
            $mail->Subject($this->pluginObj->txt(sprintf('error_with_import_item', '')));
            $mail_text = str_replace('[BR]', "\n", $this->pluginObj->txt('error_mail_greeting')
                . $msg . ilMail::_getInstallationSignature());
            $mail->Body($mail_text);
            if ($attachment !== null) {
                $mail->Attach($attachment);
            }
            $mail->Send();
        }
    }

    /**
     * @param string $recipients
     * @return array
     */
    protected function getEmailsForRecipients($recipients)
    {
        $emails = [];
        $recipients = explode(',', $recipients);
        if (is_array($recipients) && sizeof($recipients) > 0) {
            foreach ($recipients as $value) {
                $user_id = ilObjUser::_loginExists($value);
                if ($user_id != false) {
                    $emails [] = ilObjUser::_lookupEmail($user_id);
                }
            }
        }
        return $emails;
    }

    /**
     * @return string
     */
    protected function getImportDir()
    {
        $dir = $this->pluginObj->getSetting('import_directory');
        if (strlen($dir) === 0) {
            $dir = self::IMPORT_DIR;
        }
        return ilUtil::getDataDir() . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR;
    }
}