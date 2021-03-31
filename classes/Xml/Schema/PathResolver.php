<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema;

use ilElectronicCourseReservePlugin;

/**
 * Class PathResolver
 * @package ILIAS\Plugin\ElectronicCourseReserve\Xml\Schema
 * @author Michael Jansen <mjansen@databay.de>
 */
final class PathResolver
{
    /** @var ilElectronicCourseReservePlugin */
    private $plugin;

    /**
     * PathResolver constructor.
     * @param ilElectronicCourseReservePlugin $plugin
     */
    public function __construct(ilElectronicCourseReservePlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param string $filename
     * @return string
     */
    public function resolvePath(string $filename) : string
    {
        return $this->plugin->getDirectory() . '/xsd/' . $filename;
    }
}
