<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/WebServices/SOAP/classes/class.ilSoapClient.php';
require_once 'Services/Xml/classes/class.ilXmlWriter.php';
require_once 'Services/WebServices/Rest/classes/class.ilRestFileStorage.php';
require_once dirname(__FILE__).'/class.ilElectronicCourseReserveHistoryEntity.php';
require_once dirname(__FILE__).'/class.ilElectronicCourseReserveDataMapper.php';

class ilElectronicCourseReserveDigitizedMediaImporter
{
	/**
	 * @var string
	 */
	const IMPORT_DIR = 'ecr_import';

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
	 *
	 */
	public function __construct()
	{
		global $DIC;

		$this->logger = $DIC->logger();
		$this->user   = $DIC->user();
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

			$soap_client = new SoapClient(ilUtil::_getHttpPath() . '/webservice/soap/server.php?wsdl', array(
				'trace'     => true,
				'exception' => true
			));

			if(php_sapi_name() == 'cli')
			{
				$sid = $soap_client->__soapCall('login', array(CLIENT_ID,  $_SERVER['argv'][1], $_SERVER['argv'][2]));
			}
			else
			{
				$currentSessionId = session_id();
				$newSessionId = ilSession::_duplicate($currentSessionId);

				$sid = $newSessionId . '::' . CLIENT_ID;
			}

			$this->logger->write('Started determination with file pattern.');

			$mapper = new ilElectronicCourseReserveDataMapper();

			ilUtil::makeDirParents(ilUtil::getDataDir() . DIRECTORY_SEPARATOR . self::IMPORT_DIR);
			$iter = new RegexIterator(
				new DirectoryIterator(ilUtil::getDataDir() . DIRECTORY_SEPARATOR . self::IMPORT_DIR),
				'/(\d+)_(.*).pdf$/'
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

				$crs_ref_id = null;
				$job_nr     = null;
				$matches    = null;
				preg_match('/^(\d+)_(.*).pdf$/i', $filename, $matches);

				if(is_array($matches) && isset($matches[1]) && is_numeric($matches[1]))
				{
					$this->logger->write('Found target ref_id ' . $matches[1]);
					$crs_ref_id = $matches[1];
					$job_nr     = $matches[2];
				}
				else
				{
					$this->logger->write('Could not extract target ref_id from filename. Skipped file.');
					continue;
				}

				$content = @file_get_contents($pathname);

				$this->logger->write('MD5 checksum: ' . md5($content));
				$this->logger->write('SHA1 checksum: ' . sha1($content));

				$xml = new ilXmlWriter();
				$xml->xmlStartTag('File', array('type' => 'application/pdf'));
				$xml->xmlElement('Filename', array(), $filename);
				$xml->xmlElement('Title', array(), $filename);

				$xml->xmlElement('Content', array('mode' => 'FS_COPY'), $pathname);

				$xml->xmlEndTag('File');

				//@todo: process xml file if existing

				try
				{
					$ref_id = $soap_client->__soapCall('addFile', array($sid, $crs_ref_id, $xml->xmlDumpMem()));
					if(is_numeric($ref_id))
					{
						$entity = new ilElectronicCourseReserveHistoryEntity();
						$entity->setRefId($ref_id);
						$entity->setTargetRefId($crs_ref_id);
						$entity->setTimestamp(time());
						$entity->setJobNumber($job_nr);
						$mapper->saveHistory($entity);

						$this->logger->write('Created a new file object with ref_id: ' . $ref_id);

						$file = ilObjectFactory::getInstanceByRefId($ref_id, false);
						if($file instanceof ilObjFile)
						{
							$file = $file->getFile();
							$this->logger->write('Checking final file object: ' . $file);
							$content = @file_get_contents($file);
							$this->logger->write('MD5 checksum: ' . md5($content));
							$this->logger->write('SHA1 checksum: ' . sha1($content));
						}

						@unlink($fileinfo->getPathname());
						@unlink(str_replace('.pdf', '.xml', $pathname));
					}
					else
					{
						$this->logger->write('Could not create file object.');
					}
				}
				catch(SoapFault $e)
				{
					$this->logger->write('Skipped file.' . $e->getMessage());
				}
			}
		}
		catch(SoapFault $e)
		{
			$this->logger->write($e->getMessage());
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
	 * @throws ilException
	 */
	protected function ensureSystemPreconditions()
	{
		global $DIC;
		$ilSetting = $DIC->settings();

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
}