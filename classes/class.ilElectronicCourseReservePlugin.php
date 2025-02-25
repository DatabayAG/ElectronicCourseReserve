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

use ILIAS\DI\Container;
use ILIAS\Plugin\ElectronicCourseReserve\Library\LatestVersionGpgWrapper;
use ILIAS\Plugin\ElectronicCourseReserve\Library\LinkBuilder;
use ILIAS\Plugin\ElectronicCourseReserve\Locker\PidBased;
use ILIAS\Plugin\ElectronicCourseReserve\Logging\Log;
use ILIAS\Plugin\ElectronicCourseReserve\Logging\Writer\StdOut;
use ILIAS\Plugin\ElectronicCourseReserve\Objects\Helper;
use Laminas\Crypt;

class ilElectronicCourseReservePlugin extends ilUserInterfaceHookPlugin
{
    public const CTYPE = 'Services';
    public const CNAME = 'UIComponent';
    public const SLOT_ID = 'uihk';
    public const PLUGIN_ID = 'ecr';
    public const PNAME = 'ElectronicCourseReserve';
    public const ICON_URL = 'url';
    public const ICON_FILE = 'file';

    private static ?ilElectronicCourseReservePlugin $instance = null;
    private static bool $initialized = false;
    /** @var array<string, array<string, array<string, bool>>> */
    private static array $active_plugins_check_cache = [];
    /** @var array<string, array<string, array<string, ilPlugin>>> */
    private static array $active_plugins_cache = [];
    /** @var array<int, bool>*/
    private array $relevant_folder_cache = [];

    /** @var array<int, bool> */
    private array $relevant_course_cache = [];
    /** @var array<int, list<array<string, null|string|int|float>>> */
    private array $already_queried_folders = [];
    /** @var array<int, array<string, null|string|int|float>|array{}> */
    private array $already_queried_items = [];
    /** @var list<array<string, null|string|int|float>> */
    private array $item_data = [];
    private Container $dic;

    public function __construct(ilDBInterface $db, ilComponentRepositoryWrite $component_repository, string $id)
    {
        global $DIC;

        $this->dic = $DIC;

        parent::__construct($db, $component_repository, $id);
    }

    public function getPluginName(): string
    {
        return self::PNAME;
    }

    protected function init(): void
    {
        parent::init();
        $this->registerAutoloader();

        if (!self::$initialized) {
            self::$initialized = true;

            $that = $this;

            $GLOBALS['DIC']['plugin.esa.object.helper'] = static function (Container $c) {
                return new Helper();
            };

            $GLOBALS['DIC']['plugin.esa.crypt.blockcipher'] = static function (Container $c) {
                $cipher = Crypt\BlockCipher::factory('openssl', ['algorithm' => 'aes']);
                $cipher->setKey(md5(implode('|', [
                    CLIENT_ID
                ])));

                return $cipher;
            };

            $GLOBALS['DIC']['plugin.esa.library.linkbuilder'] = static function (Container $c) use ($that) {
                return new LinkBuilder(
                    $that,
                    $c['plugin.esa.crypt.gpg'],
                    $c['plugin.esa.crypt.gpg-latest'],
                    $c->user(),
                    $c['ilSetting'],
                    $c['plugin.esa.crypt.blockcipher']
                );
            };

            $GLOBALS['DIC']['plugin.esa.crypt.gpg'] = static function (Container $c) use ($that) {
                return $c['plugin.esa.crypt.gpg.factory']($that->getSetting('gpg_homedir'));
            };

            $GLOBALS['DIC']['plugin.esa.crypt.gpg-latest'] = static function (Container $c) {
                return new LatestVersionGpgWrapper(
                    $c['plugin.esa.crypt.gpg']
                );
            };

            $GLOBALS['DIC']['plugin.esa.crypt.gpg.factory'] = static function (Container $c) use ($that) {
                return static function ($homeDirectory) use ($that) {
                    require_once $that->getDirectory() . '/libs/php-gnupg/gpg.php';
                    return new GnuPG($homeDirectory);
                };
            };

            $GLOBALS['DIC']['plugin.esa.locker'] = static function (Container $c) {
                return new PidBased(
                    $c['ilSetting'],
                    $c->logger()->root()
                );
            };

            $GLOBALS['DIC']['plugin.esa.logger.writer.ilias'] = static function (Container $c) {
                $logLevel = ilLoggingDBSettings::getInstance()->getLevel();

                return new \ILIAS\Plugin\ElectronicCourseReserve\Logging\Writer\Ilias($c['ilLog'], $logLevel);
            };

            $GLOBALS['DIC']['plugin.esa.cronjob.logger'] = static function (Container $c) {
                $logger = new Log();

                $logger->addWriter(new StdOut());
                $logger->addWriter($c['plugin.esa.logger.writer.ilias']);

                return $logger;
            };
        }
    }

    public function registerAutoloader(): void
    {
        require_once __DIR__ . '/../libs/composer/vendor/autoload.php';
    }

    public function setSetting(string $keyword, mixed $value): void
    {
        global $DIC;

        $ilSetting = $DIC['ilSetting'];

        $ilSetting->set('ecr_' . $keyword, $value);
    }

    public function getSetting(string $keyword): mixed
    {
        global $DIC;

        $ilSetting = $DIC['ilSetting'];

        return $ilSetting->get('ecr_' . $keyword, '');
    }

    public static function getInstance(): self
    {
        global $DIC;

        if (self::$instance instanceof self) {
            return self::$instance;
        }

        /** @var ilComponentRepository $component_repository */
        $component_repository = $DIC['component.repository'];
        /** @var ilComponentFactory $component_factory */
        $component_factory = $DIC['component.factory'];

        $plugin_info = $component_repository->getComponentByTypeAndName(
            self::CTYPE,
            self::CNAME
        )->getPluginSlotById(self::SLOT_ID)->getPluginByName(self::PNAME);

        self::$instance = $component_factory->getPlugin($plugin_info->getId());

        return self::$instance;
    }

    public function isAssignedToRequiredRole(int $usr_id): bool
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
            if ($rbacreview->isAssigned($usr_id, (int) $role_id)) {
                return true;
            }
        }

        return false;
    }

    public function ecr_txt(string $identifier): string
    {
        $ecr_lang_data = new ilElectronicCourseReserveLangData();

        $translation = $ecr_lang_data->txt($identifier);
        if ($translation === '') {
            $translation = $this->txt($identifier);
        }

        return $translation;
    }


    /**
     * @param $folder_ref_id
     */
    public function isFolderRelevant($folder_ref_id): bool
    {
        if (!array_key_exists($folder_ref_id, $this->relevant_folder_cache)) {
            global $DIC;
            $res = $DIC->database()->queryF(
                'SELECT * FROM ecr_folder WHERE ref_id = %s',
                ['integer'],
                [$folder_ref_id]
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
     * @return array
     */
    public function getRelevantCourseAndFolderData(int $crs_ref_id): array
    {
        global $DIC;
        $res = $DIC->database()->queryF(
            'SELECT ref_id, crs_ref_id FROM ecr_folder WHERE crs_ref_id = %s',
            ['integer'],
            [$crs_ref_id]
        );
        $folders = [];
        while ($row = $DIC->database()->fetchAssoc($res)) {
            $folders[$row['ref_id']] = $row['ref_id'];
        }
        return $folders;
    }

    /**
     * @return array
     */
    public function getAllRefIds(): array
    {
        global $DIC;
        $res = $DIC->database()->query(
            'SELECT ref_id, folder_ref_id FROM ecr_description'
        );
        $ref_ids = [];
        while ($row = $DIC->database()->fetchAssoc($res)) {
            $ref_ids[$row['ref_id']] = $row['ref_id'];
            $ref_ids[$row['folder_ref_id']] = $row['folder_ref_id'];
        }
        return $ref_ids;
    }

    /**
     * @param $folder_ref_id
     */
    public function queryFolderData($folder_ref_id): void
    {
        if (!array_key_exists($folder_ref_id, $this->already_queried_folders)) {
            global $DIC;
            $res = $DIC->database()->queryF(
                'SELECT * FROM ecr_description WHERE folder_ref_id = %s',
                ['integer'],
                [$folder_ref_id]
            );
            $this->already_queried_folders[$folder_ref_id] = [];
            while ($row = $DIC->database()->fetchAssoc($res)) {
                if (is_array($row) && array_key_exists('ref_id', $row)) {
                    $this->already_queried_folders[$folder_ref_id][] = $row;
                    $this->item_data[$row['ref_id']] = $row;
                }
            }
        }
    }

    public function getImportedFolderItems(int $folderRefId): array
    {
        if (!array_key_exists($folderRefId, $this->already_queried_folders)) {
            $this->queryFolderData($folderRefId);
        }

        return $this->already_queried_folders[$folderRefId];
    }

    public function deleteFolderImportRecord(int $folderRefId): void
    {
        global $DIC;

        $DIC->database()->manipulateF(
            'DELETE FROM ecr_folder WHERE ref_id = %s',
            ['integer'],
            [$folderRefId]
        );
    }

    public function deleteFolderItemImportRecords(int $folderRefId, ?array $itemRefIds): void
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
    ): void {
        global $DIC;

        $uuid = static function () {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                // 16 bits for "time_mid"
                random_int(0, 0xffff),
                // 16 bits for "time_high_and_version",
                // four most significant bits holds version number 4
                random_int(0, 0x0fff) | 0x4000,
                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                random_int(0, 0x3fff) | 0x8000,
                // 48 bits for "node"
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff)
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
                    [$usec, $sec] = explode(' ', microtime());
                    return (int) ((int) $sec * 1000 + ((float) $usec * 1000));
                })()],
                'deletion_message' => ['blob', $message],
                'metadata' => ['blob', $metadata],
            ]
        );
    }

    public function hasFolderDeletionMessage(int $refId): bool
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

    public function getFolderDeletionMessage(int $refId): string
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

        return $row['deletion_message'] ?? '';
    }

    /**
     * @param $item_ref_id
     */
    public function queryItemData($item_ref_id): mixed
    {
        if (!isset($this->already_queried_items[$item_ref_id])) {
            global $DIC;
            $res = $DIC->database()->queryF(
                'SELECT * FROM ecr_description WHERE ref_id = %s',
                ['integer'],
                [$item_ref_id]
            );

            $this->already_queried_items[$item_ref_id] = [];
            while ($row = $DIC->database()->fetchAssoc($res)) {
                if (is_array($row) && array_key_exists('ref_id', $row)) {
                    $this->already_queried_items[$item_ref_id] = $row;
                }
            }
        }

        return $this->already_queried_items[$item_ref_id];
    }

    public function updateItemData(int $ref_id, int $show_description, int $show_image): void
    {
        global $DIC;
        $DIC->database()->update(
            'ecr_description',
            [
                'show_description' => ['integer', $show_description],
                'show_image' => ['integer', $show_image]
        ],
            [
                'ref_id' => ['integer', $ref_id]
            ]
        );
    }

    /**
     * @param $ref_id
     */
    public function isCourseRelevant($ref_id): bool
    {
        if (!array_key_exists($ref_id, $this->relevant_course_cache)) {
            global $DIC;
            $res = $DIC->database()->queryF(
                'SELECT * FROM ecr_folder WHERE crs_ref_id = %s',
                ['integer'],
                [$ref_id]
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
     * @return list<array<string, null|string|int|float>>
     */
    public function getItemData(): array
    {
        return $this->item_data;
    }

    public function isPluginInstalled(string $component, string $slot, string $plugin_class): bool
    {
        if (isset(self::$active_plugins_check_cache[$component][$slot][$plugin_class])) {
            return self::$active_plugins_check_cache[$component][$slot][$plugin_class];
        }

        /** @var ilComponentRepository $component_repository */
        $component_repository = $this->dic['component.repository'];

        $has_plugin = $component_repository->getComponentByTypeAndName(
            'Services',
            $component
        )->getPluginSlotById($slot)->hasPluginName($plugin_class);

        if ($has_plugin) {
            $plugin_info = $component_repository->getComponentByTypeAndName(
                'Services',
                $component
            )->getPluginSlotById($slot)->getPluginByName($plugin_class);
            $has_plugin = $plugin_info->isActive();
        }

        return (self::$active_plugins_check_cache[$component][$slot][$plugin_class] = $has_plugin);
    }

    public function getPlugin(string $component, string $slot, string $plugin_class): ilPlugin
    {
        if (isset(self::$active_plugins_cache[$component][$slot][$plugin_class])) {
            return self::$active_plugins_cache[$component][$slot][$plugin_class];
        }

        /** @var ilComponentRepository $component_repository */
        $component_repository = $this->dic['component.repository'];
        /** @var ilComponentFactory $component_factory */
        $component_factory = $this->dic['component.factory'];

        $plugin_info = $component_repository->getComponentByTypeAndName(
            'Services',
            $component
        )->getPluginSlotById($slot)->getPluginByName($plugin_class);

        $plugin = $component_factory->getPlugin($plugin_info->getId());

        return (self::$active_plugins_cache[$component][$slot][$plugin_class] = $plugin);
    }

    protected function beforeUninstall(): bool
    {
        $this->deleteDatabaseTables();

        return true;
    }

    private function deleteDatabaseTables(): void
    {
        $databaseTables = [
            'ecr_import_history',
            'ecr_lang_agreements',
            'ecr_user_acceptance',
            'ecr_lang_data',
            'ecr_description',
            'ecr_folder',
            'ecr_deletion_log',
        ];

        foreach ($databaseTables as $databaseTable) {
            $this->db->dropTable($databaseTable, false);
        }
    }
}
