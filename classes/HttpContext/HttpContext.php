<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\HttpContext;

use ilCtrl;
use ilObjectDataCache;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

/**
 * Trait HttpContext
 * @package ILIAS\Plugin\ElectronicCourseReserve\HttpContext
 * @author Michael Jansen <mjansen@databay.de>
 */
trait HttpContext
{
    /** @var ilObjectDataCache */
    protected $objectCache;
    /** @var ServerRequestInterface */
    protected $httpRequest;
    /** @var ilCtrl */
    protected $ctrl;

    /**
     * @param string $class
     * @return bool
     */
    final public function isBaseClass(string $class) : bool
    {
        $baseClass = (string) ($_GET['baseClass'] ?? '');

        return strtolower($class) === strtolower($baseClass);
    }

    /**
     * @return bool
     */
    final public function hasBaseClass() : bool
    {
        return isset($_GET['baseClass']);
    }

    /**
     * @param string $class
     * @return bool
     */
    final public function isCommandClass(string $class) : bool
    {
        $cmdClass = (string) ($_GET['cmdClass'] ?? '');

        return strtolower($class) === strtolower($cmdClass);
    }

    /**
     * @return bool
     */
    final public function hasCommandClass() : bool
    {
        return isset($_GET['cmdClass']);
    }

    /**
     * @param string[] $cmdClasses
     * @return bool
     */
    final public function isOneOfCommandClasses(array $cmdClasses) : bool
    {
        if (!$this->hasCommandClass()) {
            return false;
        }

        return in_array(
            strtolower($_GET['cmdClass']),
            array_map(
                'strtolower',
                $cmdClasses
            )
        );
    }

    /**
     * @param string[] $commands
     * @return bool
     */
    final public function isOneOfCommands(array $commands) : bool
    {
        return in_array(
            strtolower((string) $this->ctrl->getCmd()),
            array_map(
                'strtolower',
                $commands
            )
        );
    }
    
    /**
     * @param string[] $commands
     * @return bool
     */
    final public function isOneOfPluginCommandsLike(array $commands) : bool
    {
        return count(array_filter($commands, function (string $command) {
            if (class_exists($command)) {
                $command = (new ReflectionClass($command))->getShortName();
            }
            
            return strpos(strtolower((string) $this->ctrl->getCmd()), strtolower($command)) !== false;
        })) > 0;
    }

    /**
     * @return int
     */
    final public function getRefId() : int
    {
        $refId = (int) ($_GET['ref_id'] ?? 0);

        return $refId;
    }

    /**
     * @return int
     */
    final public function getTargetRefId() : int
    {
        $matches = null;
        $target = ((string) $_GET['target'] ?? '');
        if (preg_match('/^[a-zA-Z0-9]+_(\d+)$/', $target, $matches)) {
            if (is_array($matches) && isset($matches[1]) && is_numeric($matches[1]) && $matches[1] > 0) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    /**
     * @param int $objId
     * @return bool
     */
    final public function isObjectOfId(int $objId) : bool
    {
        $refId = $this->getRefId();
        if ($refId <= 0) {
            return false;
        }

        return ((int) $this->objectCache->lookupObjId($refId) === $objId);
    }

    /**
     * @param string $type
     * @return bool
     */
    final public function isObjectOfType(string $type) : bool
    {
        $refId = $this->getRefId();
        if ($refId <= 0) {
            return false;
        }

        $objId = (int) $this->objectCache->lookupObjId($refId);

        return $this->objectCache->lookupType($objId) === $type;
    }

    /**
     * @param string $type
     * @return bool
     */
    final public function isTargetObjectOfType(string $type) : bool
    {
        $refId = $this->getTargetRefId();
        if ($refId <= 0) {
            return false;
        }

        $objId = (int) $this->objectCache->lookupObjId($refId);

        return $this->objectCache->lookupType($objId) === $type;
    }
}
