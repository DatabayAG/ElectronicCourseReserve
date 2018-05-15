<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/WebServices/SOAP/classes/class.ilSoapClient.php';
require_once 'Services/Xml/classes/class.ilXmlWriter.php';
require_once 'Services/WebServices/Rest/classes/class.ilRestFileStorage.php';
require_once 'Services/Cron/classes/class.ilCronJobResult.php';
require_once 'Modules/File/classes/class.ilObjFile.php';
require_once 'Modules/WebResource/classes/class.ilObjLinkResource.php';
require_once 'Modules/WebResource/classes/class.ilLinkResourceItems.php';
require_once 'Modules/Course/classes/class.ilObjCourse.php';
require_once 'Modules/Folder/classes/class.ilObjFolder.php';
require_once 'Services/Utilities/classes/class.ilMimeTypeUtil.php';
require_once dirname(__FILE__).'/class.ilElectronicCourseReserveHistoryEntity.php';
require_once 'Customizing/global/plugins/Services/Cron/CronHook/CronElectronicCourseReserve/classes/class.ilElectronicCourseReserveParser.php';


class ilElectronicCourseReserveDigitizedMediaImporter
{
	const ITEM_TYPE_FILE = 'file';
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
	const LOCK_FILENAME = 'ecr.lock';

	/**
	 * @var $logger ilLog
	 */
	protected $logger;

	/**
	 * @var $user ilObjUser
	 */
	protected $user;

	/**
	 * @var array 
	 */
	protected $valid_items = array(self::ITEM_TYPE_FILE, self::ITEM_TYPE_URL);

	/**
	 * @var ilElectronicCourseReservePlugin
	 */
	public $pluginObj = null;

	/**
	 *
	 */
	public function __construct()
	{
		global $DIC;

		$this->logger = $DIC->logger()->root();
		$this->user   = $DIC->user();
		$this->pluginObj = ilPlugin::getPluginObject('Services', 'UIComponent', 'uihk', 'ElectronicCourseReserve');

	}

	/**
	 * @return string
	 */
	public static function getLockFilePath()
	{
		return ilUtil::getDataDir() . DIRECTORY_SEPARATOR . self::LOCK_FILENAME;
	}

	/**
	 *
	 */
	public function run()
	{
		$this->perform();
	}

	/**
	 *
	 */
	protected function perform()
	{
		$this->logger->write('Digitized media import script started');
		try
		{
			$this->ensureUserRelatedPreconditions();
			$this->ensureSystemPreconditions();
			$this->ensureCorrectLockingState();

			$this->logger->write('Started determination with file pattern.');

			$dir = $this->pluginObj->getSetting('import_directory');
			if(strlen($dir) === 0)
			{
				$dir = DIRECTORY_SEPARATOR . self::IMPORT_DIR;
			}
			ilUtil::makeDirParents(ilUtil::getDataDir() . DIRECTORY_SEPARATOR . $dir);
			$iter = new RegexIterator(
				new DirectoryIterator(ilUtil::getDataDir() . DIRECTORY_SEPARATOR . $dir),
				'/(.*).xml/'
			);
			foreach($iter as $fileinfo)
			{
				/**
				 * @var $fileinfo SplFileInfo
				 */
				if($fileinfo->isDir())
				{
					continue;
				}

				$pathname = $fileinfo->getPathname();
				$filename = $fileinfo->getFileName();

				$this->logger->write('Found file to import: ' . $filename);
				$this->logger->write('Pathname: ' . $pathname);

				$content = @file_get_contents($pathname);

				//Todo: Validate against xslt
				$this->logger->write('MD5 checksum: ' . md5($content));
				$this->logger->write('SHA1 checksum: ' . sha1($content));

				$parser = new ilElectronicCourseReserveParser($pathname);
				$parser->startParsing();
				$parsed_item = $parser->getElectronicCourseReserveContainer();

				if(! in_array($parsed_item->getType(), $this->valid_items))
				{
					$this->logger->write(sprintf('Type of item (%s) is unknown, skipping item.', $parsed_item->getType() ));
					continue;
				}

				$this->logger->write('Starting item creation...');
				if($parsed_item->getType() === self::ITEM_TYPE_FILE)
				{
					$this->createFileItem($parsed_item, $content);
				}
				else if($parsed_item->getType() === self::ITEM_TYPE_URL)
				{
					$this->createWebResourceItem($parsed_item, $content);
				}
				$this->logger->write('...item creation done.');
				if( ! $this->moveXmlToBackupFolder($pathname))
				{
					
				}
				//Todo: Mail on error
			}
		}
		catch(ilException $e)
		{
			$this->logger->write($e->getMessage());
		}

		try
		{
			$this->releaseLock();
		}
		catch(ilException $e)
		{
			$this->logger->write($e->getMessage());
		}

		$this->logger->write('Digitized media import script finished');
	}

	/**
	 * @param string $path_to_file
	 * @return bool
	 */
	protected function moveXmlToBackupFolder($path_to_file)
	{
		if(file_exists($path_to_file))
		{
			$dir = ilUtil::getDataDir() . DIRECTORY_SEPARATOR . self::BACKUP_DIR . DIRECTORY_SEPARATOR . date("Y-m-d");
			if( ! is_dir($dir))
			{
				ilUtil::makeDirParents($dir);
			}
			try
			{
				//Todo uncomment
				//ilUtil::moveUploadedFile($path_to_file, basename($path_to_file), $dir . DIRECTORY_SEPARATOR . basename($path_to_file), true, 'copy');
				//unlink($path_to_file);
				return true;
			}
			catch(ilException $e)
			{
				$this->logger->write($e->getMessage());
				return false;
			}
		}
		else
		{
			$this->logger->write(sprintf('File (%s) not found can not move it., skipping item.', $path_to_file));
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
		$fold->setTitle($parsed_item->getItem()->getLabel());
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
		$fold = new ilObjFolder($ref_id);
		if($parsed_item->getItem()->getLabel() != $fold->getTitle())
		{
			$this->logger->write('Title for folder (ref_id: %s), get updated from "%s" to "%s".', $ref_id, $fold->getTitle(), $parsed_item->getItem()->getLabel());
			$fold->setTitle($parsed_item->getItem()->getLabel());
			$fold->update();
		}
		
	}

	/**
	 * @param ilElectronicCourseReserveContainer $parsed_item
	 * @param string $raw_xml
	 * @throws ilFileUtilsException
	 */
	protected function createFileItem($parsed_item, $raw_xml)
	{
		$folder_ref_id = (int) $this->ensureCorrectCourseAndFolderStructure($parsed_item);
		if(file_exists($parsed_item->getItem()->getFile()) &&
			$folder_ref_id != 0
		)
		{
			$filename = basename($parsed_item->getItem()->getFile());
			$new_file = new ilObjFile();
			$new_file->setTitle($filename);
			$new_file->setFileType(pathinfo($parsed_item->getItem()->getFile(), PATHINFO_EXTENSION));
			$new_file->setFileName($filename);
			$new_file->create();
			$new_file->setFilename($new_file->getFileName());
			$new_file->addNewsNotification("file_updated");
			$new_file->createReference();
			$new_file->putInTree($folder_ref_id);
			$new_file->setPermissions($folder_ref_id);
			$new_file->update();
			$dir = $new_file->getDirectory(1);
			if( ! is_dir($dir))
			{
				ilUtil::makeDirParents($dir);
			}

			ilUtil::moveUploadedFile($parsed_item->getItem()->getFile(), $filename, $dir . '/' . $filename, true, 'copy');
			$new_file->determineFileSize();
			$new_file->update();

			$this->writeDescriptionToDB($parsed_item, $new_file->getRefId(), $raw_xml);
		}
		else if($folder_ref_id === 0)
		{
			$this->logger->write('Could not find/create course/folder structure, skipping item.');
		}
		else
		{
			$this->logger->write(sprintf('File %s not found for item %s, skipping item creation.', $parsed_item->getItem()->getFile(),  $parsed_item->getLabel()));
		}
	}

	/**
	 * @param ilElectronicCourseReserveContainer $parsed_item
	 * @param string $raw_xml
	 */
	protected function createWebResourceItem($parsed_item, $raw_xml)
	{
		$ref_id = $this->ensureCorrectCourseAndFolderStructure($parsed_item);
		if(strlen($parsed_item->getItem()->getUrl()) > 0 &&
			$ref_id != 0)
		{
			$new_link = new ilObjLinkResource();
			$new_link->setTitle($parsed_item->getLabel());
			$new_link->create();
			$new_link->createReference();
			$new_link->putInTree($ref_id);
			$new_link->setPermissions($ref_id);
			$link_item = new ilLinkResourceItems($new_link->getId());
			$link_item->setTitle($parsed_item->getItem()->getLabel());
			$link_item->setActiveStatus(1);
			$link_item->setValidStatus(1);
			$link_item->setTarget($parsed_item->getItem()->getUrl());
			$link_item->setInternal(false);
			$link_item->add();
			$this->writeDescriptionToDB($parsed_item, $new_link->getRefId(), $raw_xml);
		}
		else
		{
			$this->logger->write(sprintf('No url given for %s, skipping item creation.', $parsed_item->getLabel()));
		}
	}

	/**
	 * @param ilElectronicCourseReserveContainer $parsed_item
	 * @param int                                $new_obj_ref_id
	 * @param string                             $raw_xml
	 */
	protected function writeDescriptionToDB($parsed_item, $new_obj_ref_id, $raw_xml)
	{
		global $DIC;
		$version = 1;
		$res = $DIC->database()->queryF(
			'SELECT version FROM ecr_description WHERE ref_id = %s',
			array('integer'),
			array($new_obj_ref_id)
		);
		$row = $DIC->database()->fetchAssoc($res);

		if(is_array($row) && array_key_exists('version', $row))
		{
			$version = $row['version'] + 1;
		}

		$DIC->database()->insert('ecr_description', array(
			'ref_id'      => array('integer', $new_obj_ref_id),
			'version'     => array('integer', $version),
			'timestamp'   => array('integer', strtotime($parsed_item->getTimestamp())),
			'icon'        => array('text', $parsed_item->getItem()->getIcon()),
			'description' => array('text', $parsed_item->getItem()->getDescription()),
			'raw_xml'     => array('text', $raw_xml)
		));
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
			'ref_id'     => array('integer', $ref_id),
			'import_id'  => array('integer', $import_id),
			'crs_ref_id' => array('integer', $crs_ref_id)
		));
	}

	/**
	 * @throws ilException
	 */
	protected function ensureSystemPreconditions()
	{
		global $ilSetting;

		if(!$ilSetting->get('soap_user_administration'))
		{
			throw new ilException('Please enable soap in the ILIAS administration.');
		}
	}

	/**
	 * @throws ilException
	 */
	protected function releaseLock()
	{
		if(@unlink(self::getLockFilePath()))
		{
			$this->logger->write('Removed lock file: ' . self::getLockFilePath() . '.');
		}
		else
		{
			throw new ilException('Could not delete lock file: ' . self::getLockFilePath() . '. Please remove this file manually before you start the next job.');
		}
	}

	/**
	 * @throws ilException
	 */
	protected function ensureCorrectLockingState()
	{
		if(file_exists(self::getLockFilePath()))
		{
			throw new ilException('Script is probably running. Please remove the following lock file if you are sure no esr task is running: ' . self::getLockFilePath());
		}

		if(@file_put_contents(self::getLockFilePath(), getmypid(), LOCK_EX))
		{
			$this->logger->write('Created lock file: ' . self::getLockFilePath());
		}
		else
		{
			throw new ilException('Could create lock file: ' . self::getLockFilePath() . ' . Please check the filesystem permissions.');
		}
	}

	/**
	 * @throws ilException
	 */
	protected function ensureUserRelatedPreconditions()
	{
		if(!$this->user->hasAcceptedUserAgreement())
		{
			throw new ilException('The passed ILIAS user has to accept the user agreement.');
		}
	}

	/**
	 * @param ilElectronicCourseReserveContainer $parsed_item
	 * @return int
	 */
	protected function ensureCorrectCourseAndFolderStructure($parsed_item)
	{
		$crs_ref_id = $parsed_item->getCrsRefId();
		$folder_import_id = $parsed_item->getFolderImportId();

		if($crs_ref_id === 0 || $folder_import_id === 0)
		{
			$this->logger->write(sprintf('Import id (%s) or Course Ref id (%s) was not set, skipping this one.', $folder_import_id, $crs_ref_id));
			return 0;
		}

		/**
		 * @var $ilObjDataCache ilObjectDataCache
		 * @var $tree ilTree
		 */
		global $ilObjDataCache, $tree;

		$crs_obj_id = (int) $ilObjDataCache->lookupObjId($crs_ref_id);
		if($crs_obj_id > 0 && $ilObjDataCache->lookupType($crs_obj_id) === 'crs' && ! ilObject::_isInTrash($crs_ref_id))
		{
			$this->logger->write(sprintf('Found course for ref_id, looking for folder.', $crs_ref_id));
			$folder_obj_id = ilObject::_lookupObjIdByImportId($folder_import_id);
			if($folder_obj_id === 0)
			{
				$this->logger->write(sprintf('Folder with Import id (%s) not found creating new folder.', $folder_import_id));
				return $this->createFolder($parsed_item, $folder_import_id, $crs_ref_id);
			}
			else if($ilObjDataCache->lookupType($folder_obj_id) === 'fold')
			{
				$this->logger->write(sprintf('Found folder with Import id (%s).', $folder_import_id));
				$ref_ids = ilObject::_getAllReferences($folder_obj_id);
				$ref_id  = current($ref_ids);
				$this->updateFolderTitle($parsed_item, $ref_id);
				if($ref_id != null && $ref_id > 0 && ! ilObject::_isInTrash($ref_id))
				{
					$parent = $tree->getParentId($ref_id);
					if($parent == $crs_ref_id)
					{
						return $ref_id;
					}
					else if( $ref_id > 0 && ilObject::_isInTrash($ref_id))
					{
						$this->logger->write(sprintf('Object with ref_id (%s) is in trash, skipping.', $ref_id));
					}
					else
					{
						// Todo:  $tree->moveTree();
						$this->logger->write(sprintf('Folder with Import id (%s) not at the correct course %s.', $folder_import_id, $crs_ref_id));
					}
				}
			}
			else
			{
				$this->logger->write(sprintf('Object with Import id (%s) is not of type folder (%s).', $folder_import_id, $ilObjDataCache->lookupType($folder_obj_id)));
			}
		}
		else if($crs_obj_id > 0 && ilObject::_isInTrash($crs_ref_id))
		{
			$this->logger->write(sprintf('Object with ref_id (%s) is in trash, skipping.', $crs_ref_id));
		}
		else if($crs_obj_id > 0 && $ilObjDataCache->lookupType($crs_obj_id) !== 'crs')
		{
			$this->logger->write(sprintf('Ref id (%s) does not belong to a course its a %s instead, skipping.', $crs_ref_id, $ilObjDataCache->lookupType($crs_obj_id)));
		}
		return 0;
	}
}