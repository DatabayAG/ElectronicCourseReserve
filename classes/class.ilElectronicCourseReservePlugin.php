<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\Container;
use ILIAS\Plugin\ElectronicCourseReserve\Library\LatestVersionGpgWrapper;
use ILIAS\Plugin\ElectronicCourseReserve\Library\LinkBuilder;
use ILIAS\Plugin\ElectronicCourseReserve\Locker\PidBased;
use ILIAS\Plugin\ElectronicCourseReserve\Logging\Log;
use ILIAS\Plugin\ElectronicCourseReserve\Logging\Writer\StdOut;
use ILIAS\Plugin\ElectronicCourseReserve\Objects\Helper;
use Zend\Crypt;

class ilElectronicCourseReservePlugin extends ilUserInterfaceHookPlugin
{
    /**
     * @var string
     */
    const CTYPE = 'Services';

    /**
     * @var string
     */
    const CNAME = 'UIComponent';

    /**
     * @var string
     */
    const SLOT_ID = 'uihk';

    /**
     * @var string
     */
    const PNAME = 'ElectronicCourseReserve';

    /**
     * @var string
     */
    const ICON_URL = 'url';

    /**
     * @var string
     */
    const ICON_FILE = 'file';

    /**
     * @var ilElectronicCourseReservePlugin
     */
    private static $instance = null;

    /**
     * @var array
     */
    protected $relevant_folder_cache = array();

    /**
     * @var array
     */
    protected $relevant_course_cache = array();

    /**
     * @var array
     */
    protected $already_queried_folders = array();

    /**
     * @var array
     */
    protected $already_queried_items = array();

    /**
     * @var array
     */
    protected $item_data = array();

    /** @var bool */
    protected static $initialized = false;

    /** @var array */
    protected static $active_plugins_check_cache = array();

    /** @var array */
    protected static $active_plugins_cache = array();

    /**
     * @inheritdoc
     */
    public function getPluginName()
    {
        return self::PNAME;
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();
        $this->registerAutoloader();

        if (!self::$initialized) {
            self::$initialized = true;

            $that = $this;

            $GLOBALS['DIC']['plugin.esa.object.helper'] = function (Container $c) use ($that) {
                return new Helper();
            };

            $GLOBALS['DIC']['plugin.esa.crypt.blockcipher'] = function (Container $c) use ($that) {
                $cipher = Crypt\BlockCipher::factory('openssl', ['algorithm' => 'aes']);
                $cipher->setKey(md5(implode('|', [
                    CLIENT_ID
                ])));

                return $cipher;
            };

            $GLOBALS['DIC']['plugin.esa.library.linkbuilder'] = function (Container $c) use ($that) {
                $linkBuilder = new LinkBuilder(
                    $that,
                    $c['plugin.esa.crypt.gpg'],
                    $c['plugin.esa.crypt.gpg-latest'],
                    $c->user(),
                    $c['ilSetting'],
                    $c['plugin.esa.crypt.blockcipher']
                );

                return $linkBuilder;
            };

            $GLOBALS['DIC']['plugin.esa.crypt.gpg'] = function (Container $c) use ($that) {
                return $c['plugin.esa.crypt.gpg.factory']($that->getSetting('gpg_homedir'));
            };

            $GLOBALS['DIC']['plugin.esa.crypt.gpg-latest'] = function (Container $c) use ($that) {
                return new LatestVersionGpgWrapper(
                    $c['plugin.esa.crypt.gpg']
                );
            };

            $GLOBALS['DIC']['plugin.esa.crypt.gpg.factory'] = function (Container $c) use ($that) {
                return function ($homeDirectory) use ($that) {
                    require_once $that->getDirectory() . '/libs/php-gnupg/gpg.php';
                    $gpg = new GnuPG($homeDirectory);

                    return $gpg;
                };
            };

            $GLOBALS['DIC']['plugin.esa.locker'] = function (Container $c) {
                return new PidBased(
                    $c['ilSetting'],
                    $c->logger()->root()
                );
            };

            $GLOBALS['DIC']['plugin.esa.logger.writer.ilias'] = function (Container $c) {
                $logLevel = ilLoggingDBSettings::getInstance()->getLevel();

                return new \ILIAS\Plugin\ElectronicCourseReserve\Logging\Writer\Ilias($c['ilLog'], $logLevel);
            };

            $GLOBALS['DIC']['plugin.esa.cronjob.logger'] = function (Container $c) {
                $logger = new Log();

                $logger->addWriter(new StdOut());
                $logger->addWriter($c['plugin.esa.logger.writer.ilias']);

                return $logger;
            };
        }
    }

    /**
     * Registers the plugin autoloader
     */
    public function registerAutoloader() : void
    {
        require_once __DIR__ . '/../libs/composer/vendor/autoload.php';
    }

    /**
     * @param string $keyword
     * @param mixed $value
     */
    public function setSetting($keyword, $value)
    {
        global $DIC;

        $ilSetting = $DIC['ilSetting'];

        $ilSetting->set('ecr_' . $keyword, $value);
    }

    /**
     * @param string $keyword
     * @return mixed
     */
    public function getSetting($keyword)
    {
        global $DIC;

        $ilSetting = $DIC['ilSetting'];

        return $ilSetting->get('ecr_' . $keyword, '');
    }

    /**
     * @param array $path
     * @param array $params
     * @param $cmd
     * @return string
     */
    public function getLinkTarget(array $path, array $params = array(), $cmd)
    {
        global $DIC;

        $ilDB = $DIC->database();

        $class_IN_ctrlClasses = $ilDB->in('class', $path, false, 'text');

        $query = "
            SELECT    class, cid
           
            FROM    ctrl_classfile
           
            WHERE    $class_IN_ctrlClasses
        ";

        $resultSet = $ilDB->query($query);

        $ctrlClasses = array_flip($path);

        $commandNodeIds = array();

        while ($dataSet = $ilDB->fetchAssoc($resultSet)) {
            $commandNodeIds[$ctrlClasses[$dataSet['class']]] = $dataSet['cid'];
        }

        ksort($commandNodeIds);

        $params = array_merge(array(
            'cmd' => $cmd,
            'baseClass' => $path[0],
            'cmdClass' => $path[count($path) - 1],
            'cmdNode' => implode(':', $commandNodeIds)
        ), $params);

        $target = 'ilias.php';

        foreach ($params as $paramName => $paramValue) {
            $target = ilUtil::appendUrlParameterString($target, "$paramName=$paramValue", false);
        }

        return $target;
    }

    /**
     * @return ilElectronicCourseReservePlugin
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            return self::$instance = ilPluginAdmin::getPluginObject(
                self::CTYPE,
                self::CNAME,
                self::SLOT_ID,
                self::PNAME
            );
        }

        return self::$instance;
    }

    /**
     * @param int $usr_id
     * @return bool
     */
    public function isAssignedToRequiredRole($usr_id)
    {
        global $DIC;
        $rbacreview = $DIC->rbac()->review();

        $plugin = self::getInstance();

        if (!$plugin->getSetting('limit_to_groles')) {
            return true;
        }

        $groles = explode(',', $plugin->getSetting('global_roles'));
        $groles = array_filter($groles);

        if (!$groles) {
            return true;
        }

        foreach ($groles as $role_id) {
            if ($rbacreview->isAssigned($usr_id, $role_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $identifier
     * @return string
     */
    public function ecr_txt($identifier)
    {
        $this->includeClass('class.ilElectronicCourseReserveLangData.php');
        $ecr_lang_data = new ilElectronicCourseReserveLangData();

        $translation = $ecr_lang_data->txt($identifier);

        if (0 === strlen($translation)) {
            $translation = $this->txt($identifier);
        }

        return $translation;
    }


    /**
     * @param $folder_ref_id
     * @return bool
     */
    public function isFolderRelevant($folder_ref_id)
    {
        if (!array_key_exists($folder_ref_id, $this->relevant_folder_cache)) {
            global $DIC;
            $res = $DIC->database()->queryF(
                'SELECT * FROM ecr_folder WHERE ref_id = %s',
                array('integer'),
                array($folder_ref_id)
            );
            $row = $DIC->database()->fetchAssoc($res);
            $this->relevant_folder_cache[$folder_ref_id] = false;
            if (is_array($row) && array_key_exists('ref_id', $row)) {
                $this->relevant_folder_cache[$folder_ref_id] = true;
            }
        }
        return $this->relevant_folder_cache[$folder_ref_id];
    }

    /**
     * @param int $crs_ref_id
     * @return array
     */
    public function getRelevantCourseAndFolderData($crs_ref_id)
    {
        global $DIC;
        $res = $DIC->database()->queryF(
            'SELECT ref_id, crs_ref_id FROM ecr_folder WHERE crs_ref_id = %s',
            array('integer'),
            array($crs_ref_id)
        );
        $folders = array();
        while ($row = $DIC->database()->fetchAssoc($res)) {
            $folders[$row['ref_id']] = $row['ref_id'];
        }
        return $folders;
    }

    /**
     * @return array
     */
    public function getAllRefIds()
    {
        global $DIC;
        $res = $DIC->database()->query(
            'SELECT ref_id, folder_ref_id FROM ecr_description'
        );
        $ref_ids = array();
        while ($row = $DIC->database()->fetchAssoc($res)) {
            $ref_ids[$row['ref_id']] = $row['ref_id'];
            $ref_ids[$row['folder_ref_id']] = $row['folder_ref_id'];
        }
        return $ref_ids;
    }

    /**
     * @param $folder_ref_id
     */
    public function queryFolderData($folder_ref_id)
    {
        if (!array_key_exists($folder_ref_id, $this->already_queried_folders)) {
            global $DIC;
            $res = $DIC->database()->queryF(
                'SELECT * FROM ecr_description WHERE folder_ref_id = %s',
                array('integer'),
                array($folder_ref_id)
            );
            $items = [];
            while ($row = $DIC->database()->fetchAssoc($res)) {
                if (is_array($row) && array_key_exists('ref_id', $row)) {
                    $items[] = $row;
                    $this->item_data[$row['ref_id']] = $row;
                }
            }
            $this->already_queried_folders[$folder_ref_id] = $items;
        }
    }

    public function getImportedFolderItems(int $folderRefId) : array
    {
        if (!array_key_exists($folderRefId, $this->already_queried_folders)) {
            $this->queryFolderData($folderRefId);
        }

        return $this->already_queried_folders[$folderRefId];
    }

    public function deleteFolderImportRecord(int $folderRefId)
    {
        global $DIC;

        $DIC->database()->manipulateF(
            'DELETE FROM ecr_folder WHERE ref_id = %s',
            ['integer'],
            [$folderRefId]
        );
    }

    public function deleteFolderItemImportRecords(int $folderRefId, ?array $itemRefIds)
    {
        global $DIC;

        if (null === $itemRefIds) {
            $DIC->database()->manipulateF(
                'DELETE FROM ecr_description WHERE folder_ref_id = %s',
                ['integer'],
                [$folderRefId]
            );
        } else {
            $DIC->database()->manipulateF(
                'DELETE FROM ecr_description WHERE folder_ref_id = %s AND ' . $DIC->database()->in('ref_id', $itemRefIds, false, 'integer'),
                ['integer'],
                [$folderRefId]
            );
        }
    }

    public function logDeletion(
        int $crsRefId,
        int $folderRefId,
        string $mode,
        ?string $message,
        ?string $metadata
    ) {
        global $DIC;

        $uuid = static function() {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                // 16 bits for "time_mid"
                mt_rand(0, 0xffff),
                // 16 bits for "time_high_and_version",
                // four most significant bits holds version number 4
                mt_rand(0, 0x0fff) | 0x4000,
                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand(0, 0x3fff) | 0x8000,
                // 48 bits for "node"
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff)
            );
        };

        $DIC->database()->insert(
            'ecr_deletion_log',
            [
                'log_id' => ['text', $uuid()],
                'crs_ref_id' => ['integer', $crsRefId],
                'folder_ref_id' => ['integer', $folderRefId],
                'deletion_mode' => ['text', $mode],
                'deletion_timestamp' => ['integer', time()],
                'deletion_timestamp_ms' => ['integer', (static function () {
                    list($usec, $sec) = explode(' ', microtime());
                    return (int) ((int) $sec * 1000 + ((float) $usec * 1000));
                })()],
                'deletion_message' => ['blob', $message],
                'metadata' => ['blob', $metadata],
            ]
        );
    }

    /**
     * @param int $refId
     * @return bool
     */
    public function hasFolderDeletionMessage(int $refId) : bool
    {
        global $DIC;

        $query = "
            SELECT folder_ref_id
            FROM ecr_deletion_log
            WHERE deletion_message IS NOT NULL AND TRIM(deletion_message) != ''
            AND folder_ref_id = " . $DIC->database()->quote($refId, 'integer');
        $res = $DIC->database()->query($query);

        return $DIC->database()->numRows($res) > 0;
    }

    /**
     * @param int $refId
     * @return string
     */
    public function getFolderDeletionMessage(int $refId) : string
    {
        global $DIC;

        $query = '
            SELECT deletion_message
            FROM ecr_deletion_log
            INNER JOIN (
                SELECT folder_ref_id, MAX(deletion_timestamp_ms) deletion_timestamp_ms
                FROM ecr_deletion_log
                WHERE folder_ref_id = ' . $DIC->database()->quote($refId, 'integer') . '
                GROUP BY folder_ref_id
            ) tmp ON ecr_deletion_log.folder_ref_id = tmp.folder_ref_id AND ecr_deletion_log.deletion_timestamp_ms = tmp.deletion_timestamp_ms';
        $res = $DIC->database()->query($query);
        $row = $DIC->database()->fetchAssoc($res);

        return $row['deletion_message'];
    }

    /**
     * @param $item_ref_id
     * @return mixed
     */
    public function queryItemData($item_ref_id)
    {
        if (!array_key_exists($item_ref_id, $this->already_queried_items)) {
            global $DIC;
            $res = $DIC->database()->queryF(
                'SELECT * FROM ecr_description WHERE ref_id = %s',
                array('integer'),
                array($item_ref_id)
            );
            while ($row = $DIC->database()->fetchAssoc($res)) {
                if (is_array($row) && array_key_exists('ref_id', $row)) {
                    $this->already_queried_items[$item_ref_id] = $row;

                } else {
                    $this->already_queried_items[$item_ref_id] = array();
                }
            }
        }
        return $this->already_queried_items[$item_ref_id];
    }

    /**
     * @param int $ref_id
     * @param int $show_description
     * @param int $show_image
     */
    public function updateItemData($ref_id, $show_description, $show_image)
    {
        global $DIC;
        $DIC->database()->update("ecr_description", array(
            "show_description" => array("int", $show_description),
            "show_image" => array("int", $show_image)
        ),
            array(
                "ref_id" => array("int", $ref_id)
            ));
    }

    /**
     * @param $ref_id
     * @return bool
     */
    public function isCourseRelevant($ref_id)
    {
        if (!array_key_exists($ref_id, $this->relevant_course_cache)) {
            global $DIC;
            $res = $DIC->database()->queryF(
                'SELECT * FROM ecr_folder WHERE crs_ref_id = %s',
                array('integer'),
                array($ref_id)
            );
            $row = $DIC->database()->fetchAssoc($res);
            $this->relevant_course_cache[$ref_id] = false;
            if (is_array($row) && array_key_exists('crs_ref_id', $row)) {
                $this->relevant_course_cache[$ref_id] = true;
            }
        }
        return $this->relevant_course_cache[$ref_id];
    }

    /**
     * @return array
     */
    public function getItemData()
    {
        return $this->item_data;
    }

    /**
     * @param string $component
     * @param string $slot
     * @param string $plugin_class
     *
     * @return bool
     */
    public function isPluginInstalled($component, $slot, $plugin_class)
    {
        if (isset(self::$active_plugins_check_cache[$component][$slot][$plugin_class])) {
            return self::$active_plugins_check_cache[$component][$slot][$plugin_class];
        }

        foreach (
            $GLOBALS['ilPluginAdmin']->getActivePluginsForSlot(IL_COMP_SERVICE, $component,
                $slot) as $plugin_name
        ) {
            $plugin = ilPluginAdmin::getPluginObject(IL_COMP_SERVICE, $component, $slot, $plugin_name);
            if (class_exists($plugin_class) && $plugin instanceof $plugin_class) {
                return (self::$active_plugins_check_cache[$component][$slot][$plugin_class] = true);
            }
        }

        return (self::$active_plugins_check_cache[$component][$slot][$plugin_class] = false);
    }

    /**
     * @param string $component
     * @param string $slot
     * @param string $plugin_class
     *
     * @return ilPlugin
     * @throws ilException
     */
    public function getPlugin($component, $slot, $plugin_class)
    {
        if (isset(self::$active_plugins_cache[$component][$slot][$plugin_class])) {
            return self::$active_plugins_cache[$component][$slot][$plugin_class];
        }

        foreach (
            $GLOBALS['ilPluginAdmin']->getActivePluginsForSlot(IL_COMP_SERVICE, $component,
                $slot) as $plugin_name
        ) {
            $plugin = ilPluginAdmin::getPluginObject(IL_COMP_SERVICE, $component, $slot, $plugin_name);
            if (class_exists($plugin_class) && $plugin instanceof $plugin_class) {
                return (self::$active_plugins_cache[$component][$slot][$plugin_class] = $plugin);
            }
        }

        throw new ilException($plugin_class . ' plugin not installed!');
    }
}
