<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\Data\Factory as DataTypeFactory;
use ILIAS\Plugin\ElectronicCourseReserve\Filesystem\Purger;
use ILIAS\Plugin\ElectronicCourseReserve\Logging\Log;
use ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema\PathResolver;
use ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema\Validation\ErrorFormatter;
use ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema\Validation\SchemaValidator;

require_once __DIR__ . '/class.ilElectronicCourseReserveHistoryEntity.php';
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
    const PATH_TO_IMPORT_XSD = 'import.xsd';

    /**
     * @var string
     */
    const PATH_TO_DELETE_XSD = 'deletion.xsd';

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
        global $DIC;

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
                '/(\d+)\-delete\-(.*?)\-manifest\.xml$/'
            );
            foreach ($iter as $file_info) {
                ilCronManager::ping($job_id);

                /** @var $file_info SplFileInfo */
                if ($file_info->isDir()) {
                    continue;
                }

                $pathname = $file_info->getPathname();
                $filename = $file_info->getFileName();

                $this->logger->info('Found deletion file: ' . $filename);
                $this->logger->info('Pathname: ' . $pathname);

                $content = @file_get_contents($pathname);

                $valid = $this->validateXmlAgainstXsd($filename, $content, self::PATH_TO_DELETE_XSD);
                if ($valid === true) {
                    $this->logger->info('MD5 checksum: ' . md5($content));
                    $this->logger->info('SHA1 checksum: ' . sha1($content));

                    $this->logger->info('Starting item deletion...');
 
                    $deletionDocument = new SimpleXMLElement($content);
                    $esaCrsRefId = trim((string) $deletionDocument['iliasID']);
                    $deletionMode = trim((string) $deletionDocument->delete['type']);
                    $deletionMessage = trim((string) $deletionDocument->delete->message);

                    $this->logger->info('Searching folders for iliasID (course)' . $esaCrsRefId);
                    $crsRefIdsByFolderRefId = $this->pluginObj->getRelevantCourseAndFolderData((int) $esaCrsRefId);
                    $folderRefIds = array_unique(array_keys($crsRefIdsByFolderRefId));

                    $deletionErrors = [];
                    $partialSuccesses = [];
                    foreach ($folderRefIds as $folderRefId) {
                        try {
                            $this->logger->info(sprintf(
                                'Started deletion process of folder with ref_id %s with mode %s and message %s',
                                $folderRefId,
                                $deletionMode,
                                $deletionMessage ?: '-'
                            ));

                            $referenceExists = ilObject::_exists($folderRefId, true);
                            if (!$referenceExists) {
                                $this->logger->info(sprintf('Folder is already deleted'));
                                continue;
                            }
                            if ($DIC->repositoryTree()->isDeleted($folderRefId)) {
                                $this->logger->info(sprintf('Folder is already deleted'));
                                continue;
                            }

                            $partialSuccesses[$folderRefId] = new stdClass();
                            $partialSuccesses[$folderRefId]->ref_id = $folderRefId;
                            $partialSuccesses[$folderRefId]->title = ilObject::_lookupTitle(
                                ilObject::_lookupObjId($folderRefId)
                            );

                            $itemRefIds = null;

                            $folderNode = $DIC->repositoryTree()->getNodeData($folderRefId);
                            $partialSuccesses[$folderRefId]->itemsBeforeDeletion = $DIC->repositoryTree()->getSubTree($folderNode);
                            if ('all' === $deletionMode) {
                                $this->deleteFolder($folderRefId, null);
                            } else {
                                $items = $this->pluginObj->getImportedFolderItems($folderRefId);
                                $itemRefIds = array_map(static function (array $item) : int {
                                    return (int) $item['ref_id'];
                                }, $items);

                                $this->deleteFolder($folderRefId, $itemRefIds);
                            }
                            $partialSuccesses[$folderRefId]->itemsAfterDeletion = $DIC->repositoryTree()->getSubTree($folderNode);

                            $this->pluginObj->logDeletion(
                                (int) $esaCrsRefId,
                                (int) $folderRefId,
                                $deletionMode,
                                $deletionMessage,
                                json_encode($partialSuccesses[$folderRefId])
                            );

                            $this->pluginObj->deleteFolderItemImportRecords((int) $folderRefId, $itemRefIds);
                            $partialSuccesses[$folderRefId]->finished = true;
                        } catch (Exception $e) {
                            $deletionErrors[] = $e;
                            $this->sendMailOnDeletionError($e->getMessage());
                        } finally {
                            $this->logger->info(sprintf(
                                'Finished deletion process of folder with ref_id %s',
                                $folderRefId
                            ));
                        }
                    }

                    $partialSuccesses = array_filter(
                        $partialSuccesses,
                        static function (stdClass $deletionProtocol) : bool {
                            return property_exists($deletionProtocol, 'finished') && $deletionProtocol->finished === true;
                        }
                    );
                    if ($deletionErrors === []) {
                        if ($this->moveXmlToBackupFolder($pathname)) {
                            $msg = sprintf($this->pluginObj->txt('mail_del_processed_without_errors'), $pathname);
                            $this->sendMailOnDeletionSuccess($msg, $partialSuccesses);
                        } else {
                            $msg = sprintf($this->pluginObj->txt('error_move_mail'), $pathname);
                            $this->sendMailOnDeletionError($msg, $partialSuccesses);
                        }
                    } elseif ($partialSuccesses !== []) {
                        $msg = sprintf($this->pluginObj->txt('mail_del_processed_with_partial_errors'), $pathname);
                        $this->sendMailOnDeletionSuccess($msg, $partialSuccesses);
                    }

                    $this->logger->info('...item deletion done.');
                } else {
                    $this->sendMailOnDeletionError($valid, null, $pathname);
                }
            }

            $iter = new RegexIterator(
                new DirectoryIterator($dir),
                '/(.*)\.xml$/'
            );
            foreach ($iter as $file_info) {
                ilCronManager::ping($job_id);

                /** @var $file_info SplFileInfo */
                if ($file_info->isDir()) {
                    continue;
                }

                $pathname = $file_info->getPathname();
                $filename = $file_info->getFileName();

                if (preg_match('/(\d+)\-delete\-(.*?)\-manifest\.xml$/', $pathname)) {
                    continue;
                }

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
                        $this->logger->info(sprintf(
                            'Type of item (%s) is unknown, skipping item.',
                            $parsed_item->getType()
                        ));
                        continue;
                    }

                    $this->logger->info('Starting item creation...');
                    if ($parsed_item->getType() === self::ITEM_TYPE_FILE) {
                        if (!$this->createFileItem($parsed_item, $content)) {
                            $msg = sprintf(
                                $this->pluginObj->txt('error_create_file_mail'),
                                $parsed_item->getCrsRefId(),
                                $parsed_item->getFolderImportId()
                            );
                            $this->sendMailOnError($msg);
                        }
                    } elseif ($parsed_item->getType() === self::ITEM_TYPE_URL) {
                        if (!$this->createWebResourceItem($parsed_item, $content)) {
                            $msg = sprintf(
                                $this->pluginObj->txt('error_create_url_mail'),
                                $parsed_item->getCrsRefId(),
                                $parsed_item->getFolderImportId()
                            );
                            $this->sendMailOnError($msg);
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
     * @param string $path_to_schema
     * @return bool|string
     */
    protected function validateXmlAgainstXsd($filename, $xml_string, $path_to_schema = '')
    {
        $this->logger->info(sprintf('Started XML validation of %s', $filename));

        if ('' === $path_to_schema) {
            $path_to_schema = self::PATH_TO_IMPORT_XSD;
        }

        require_once __DIR__ . '/Xml/Schema/Validation/SchemaValidator.php';
        require_once __DIR__ . '/Xml/Schema/PathResolver.php';
        require_once __DIR__ . '/Xml/Schema/Validation/ErrorFormatter.php';
        $schemaValidator = new SchemaValidator(
            new DataTypeFactory(),
            new PathResolver(ilElectronicCourseReservePlugin::getInstance()),
            new ErrorFormatter()
        );

        $validation = $schemaValidator->validate(
            $xml_string,
            $path_to_schema
        );

        $this->logger->info('Finished XML validation');

        if (!$validation->result()->isOK()) {
            return $validation->result()->error();
        }

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
    protected function createFileItem(ilElectronicCourseReserveContainer $parsed_item, string $raw_xml) : bool
    {
        global $DIC;

        $folder_ref_id = (int) $this->ensureCorrectCourseAndFolderStructure($parsed_item);
        $file_path = $this->getResolvedFilePath($parsed_item->getItem()->getFile());

        if ($folder_ref_id !== 0 && file_exists($file_path)) {
            $filename = basename($parsed_item->getItem()->getFilename());

            $new_file = new ilObjFile();

            $file_type = pathinfo($parsed_item->getItem()->getFile(), PATHINFO_EXTENSION);
            if (version_compare(ILIAS_VERSION_NUMERIC, '7.0', '<')) {
                $new_file->setTitle($parsed_item->getItem()->getLabel() . '.' . $file_type);
                $new_file->setFileType($file_type);
                $new_file->setFileName($filename);
                $new_file->setVersion(1);
            }
            $new_file->create();
            if (version_compare(ILIAS_VERSION_NUMERIC, '7.0', '<')) {
                $new_file->setFilename($new_file->getFileName());
                $new_file->addNewsNotification("file_updated");
            }

            $new_file->createReference();
            $new_file->putInTree($folder_ref_id);
            $new_file->setPermissions($folder_ref_id);

            $rbac_log_roles = $DIC->rbac()->review()->getParentRoleIds($new_file->getRefId(), false);
            $rbac_log = ilRbacLog::gatherFaPa($new_file->getRefId(), array_keys($rbac_log_roles), true);
            ilRbacLog::add(ilRbacLog::CREATE_OBJECT, $new_file->getRefId(), $rbac_log);

            if (version_compare(ILIAS_VERSION_NUMERIC, '7.0', '>=')) {
                $fileHandle = fopen($file_path, 'rb');
                $stream = \ILIAS\Filesystem\Stream\Streams::ofResource($fileHandle);
                $new_file->appendStream($stream, $parsed_item->getItem()->getLabel() . '.' . $file_type);
            }

            $new_file->update();

            if (version_compare(ILIAS_VERSION_NUMERIC, '7.0', '<')) {
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
            } else {
                if (self::DELETE_FILES) {
                    unlink($file_path);
                }
            }

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
            'import_id' => array('text', $import_id),
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
        $this->logger->info('Removed deleted entries from table ers_description.');
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
    protected function ensureCorrectCourseAndFolderStructure(ilElectronicCourseReserveContainer $parsed_item) : int
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
            $this->logger->info(sprintf('Found course for ref_id %s, looking for folder.', $crs_ref_id));
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

    protected function sendMailOnDeletionError(string $msg,  ?array $deletionProtocols = null, string $attachment = null)
    {
        if ((int) $this->pluginObj->getSetting('is_del_mail_enabled') === 1) {
            $mail = new ilMimeMail();
            $mail->From($this->from);
            $recipients = $this->pluginObj->getSetting('mail_del_recipients');
            $mail->To($this->getEmailsForRecipients($recipients));
            $mail->Subject($this->pluginObj->txt(sprintf('error_with_deletion_item')));
            $mail_text = str_replace(
                '[BR]',
                "\n",
                $this->pluginObj->txt('error_mail_greeting') . $msg
            );
            $this->renderMailDeletionProtocol($deletionProtocols, $mail_text);
            $mail->Body($mail_text . ilMail::_getInstallationSignature());
            if ($attachment !== null) {
                $mail->Attach($attachment);
            }
            $mail->Send();
        }
    }

    protected function sendMailOnDeletionSuccess(string $msg, array $deletionProtocols)
    {
        if ((int) $this->pluginObj->getSetting('is_del_mail_enabled') === 1) {
            $mail = new ilMimeMail();
            $mail->From($this->from);
            $recipients = $this->pluginObj->getSetting('mail_del_recipients');
            $mail->To($this->getEmailsForRecipients($recipients));
            $mail->Subject($this->pluginObj->txt(sprintf('success_with_deletion_item')));
            $mail_text = str_replace(
                '[BR]',
                "\n",
                $this->pluginObj->txt('error_mail_greeting') . $msg 
            );
            $this->renderMailDeletionProtocol($deletionProtocols, $mail_text);
            $mail->Body($mail_text . ilMail::_getInstallationSignature());
            $mail->Send();
        }
    }

    protected function renderMailDeletionProtocol(array $deletionProtocols, string &$mail_text)
    {
        if ($deletionProtocols !== []) {
            $mail_text .= "\n";
            foreach ($deletionProtocols as $deletionProtocol) {
                $mail_text .= "\n";
                $mail_text .= sprintf(
                    $this->pluginObj->txt('mail_del_folder_header'),
                    $deletionProtocol->title
                );
                $mail_text .= "\n";
                $mail_text .= sprintf(
                    $this->pluginObj->txt('mail_del_folder_metric_num_obj_bd'),
                    count($deletionProtocol->itemsBeforeDeletion)
                );
                $mail_text .= "\n";
                $mail_text .= sprintf(
                    $this->pluginObj->txt('mail_del_folder_metric_num_obj_ad'),
                    count($deletionProtocol->itemsAfterDeletion)
                );
                $mail_text .= "\n";
                $mail_text .= sprintf(
                    $this->pluginObj->txt('mail_del_folder_metric_num_obj_d'),
                    count($deletionProtocol->itemsBeforeDeletion) - count($deletionProtocol->itemsAfterDeletion)
                );
            }
        }
    }

    /**
     * @param string $msg
     * @param string|null $attachment
     */
    protected function sendMailOnError($msg, $attachment = null)
    {
        if ((int) $this->pluginObj->getSetting('is_mail_enabled') === 1) {
            $mail = new ilMimeMail();
            $mail->From($this->from);
            $recipients = $this->pluginObj->getSetting('mail_recipients');
            $mail->To($this->getEmailsForRecipients($recipients));
            $mail->Subject($this->pluginObj->txt('error_with_import_item'));
            $mail_text = str_replace(
                '[BR]',
                "\n",
                $this->pluginObj->txt('error_mail_greeting') . $msg . ilMail::_getInstallationSignature()
            );
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

    /**
     * @param int $refId
     * @param int[]|null $childrenRefIds
     */
    private function deleteFolder(int $refId, ?array $childrenRefIds = null) : void
    {
        global $DIC;

        $refIdsToDeleteByParent = [];
        $refIdsToBeRemovedFromSystem = [];

        if (null === $childrenRefIds) {
            $childrenRefIds = [];
            foreach ($DIC->repositoryTree()->getChildIds($refId) as $childRefId) {
                $childrenRefIds[] = $childRefId;
                $refIdsToDeleteByParent[$refId][] = $childRefId;
            }
        } else {
            foreach ($childrenRefIds as $childRefId) {
                $referenceExists = ilObject::_exists($childRefId, true);
                if (!$referenceExists) {
                    continue;
                }
                if ($DIC->repositoryTree()->isDeleted($childRefId)) {
                    $refIdsToBeRemovedFromSystem[] = $childRefId;
                    continue;
                }

                $parents = $DIC->repositoryTree()->getPathId($childRefId, ROOT_FOLDER_ID);
                $parents = array_filter(array_map('intval', $parents));
                array_pop($parents); // Remove element itself
                $is = array_intersect($parents, $childrenRefIds); // Check if parent is to be deleted as well
                if (count($is) === 0) {
                    $parentRefId = array_pop($parents);
                    $refIdsToDeleteByParent[$parentRefId][] = $childRefId;
                }
            }
        }

        $DIC['ilObjDataCache']->preloadReferenceCache($childrenRefIds);
        $DIC['ilObjDataCache']->preloadReferenceCache(array_keys($refIdsToDeleteByParent));

        foreach ($refIdsToDeleteByParent as $parentRefId => $childRefIds) {
            $DIC->logger()->root()->info(sprintf(
                "Delegated deletion request in context of parent reference '%s' (ref_id: %s|obj_id: %s) " .
                "for children '%s' to ILIAS core process...",
                $DIC['ilObjDataCache']->lookupTitle($DIC['ilObjDataCache']->lookupObjId($parentRefId)),
                $parentRefId,
                $DIC['ilObjDataCache']->lookupObjId($parentRefId),
                implode(', ', $childRefIds)
            ));

            try {
                ilRepUtil::deleteObjects($parentRefId, $childRefIds);
                if ($DIC->settings()->get('enable_trash')) {
                    // If the trash is enabled, we have to remove the references afterwards
                    ilRepUtil::removeObjectsFromSystem($childRefIds);
                }
            } catch (Exception $e) {
                $DIC->logger()->root()->error('Error during object deletion');
                $DIC->logger()->root()->error($e->getMessage());
                $DIC->logger()->root()->error($e->getTraceAsString());
            }
        }

        if ($DIC->settings()->get('enable_trash')) {
            try {
                ilRepUtil::removeObjectsFromSystem($refIdsToBeRemovedFromSystem);
            } catch (Exception $e) {
                $DIC->logger()->root()->error('Error during object deletion');
                $DIC->logger()->root()->error($e->getMessage());
                $DIC->logger()->root()->error($e->getTraceAsString());
            }
        }
    }
}