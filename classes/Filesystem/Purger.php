<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Filesystem;

use Exception;
use FilesystemIterator;
use ILIAS\Plugin\ElectronicCourseReserve\Logging\Logger;
use ParentIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Class Purger
 * @package ILIAS\Plugin\ElectronicCourseReserve\Filesystem
 */
class Purger
{
    protected Logger $log;

    protected string $directory = '';

    /**
     * @param Logger $log
     * @param string $directory
     */
    public function __construct(Logger $log, string $directory)
    {
        $this->log = $log;
        $this->directory = $directory;
    }

    /**
     *
     */
    public function purge(): void
    {
        try {
            $this->log->info('Started cleanup job for: ' . realpath($this->directory));

            $iterator = new RecursiveDirectoryIterator(
                realpath($this->directory),
                FilesystemIterator::SKIP_DOTS
            );

            $directories = new ParentIterator($iterator);
            $filterIterator = new Filter\Mtime(
                new RecursiveIteratorIterator($directories, RecursiveIteratorIterator::SELF_FIRST),
                strtotime('-1 minute')
            );

            $emptyDirectories = array();
            foreach ($filterIterator as $dir) {
                if (iterator_count($iterator->getChildren()) === 0) {
                    $emptyDirectories[] = $dir->getPathname();
                }
            }

            foreach ($emptyDirectories as $directory) {
                rmdir($directory);
                $this->log->info('Deleted empty directory: ' . $directory);
            }

            $this->log->info('Finished cleanup.');
        } catch (Exception $e) {
            $this->log->err($e->getMessage());
        } catch (Throwable $e) {
            $this->log->err($e->getMessage());
        }
    }
}