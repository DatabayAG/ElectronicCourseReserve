<?php declare(strict_types=1);
/* Copyright (c) 1998-2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\ElectronicCourseReserve\HttpContext;

use ilCtrl;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;
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
    protected ilObjectDataCache $objectCache;
    protected ServerRequestInterface $httpRequest;

    protected ilCtrl $ctrl;

    private WrapperFactory $httpWrapper;

    private Factory $refinery;



    /**
     * @param string $class
     * @return bool
     */
    final public function isBaseClass(string $class) : bool
    {
        if($this->httpWrapper->query()->has("baseClass")){
            $baseClass = $this->httpWrapper->query()->retrieve("baseClass", $this->refinery->kindlyTo()->string());
        } else {
            $baseClass = "";
        }

        return strtolower($class) === strtolower($baseClass);
    }

    /**
     * @return bool
     */
    final public function hasBaseClass() : bool
    {
        return $this->httpWrapper->query()->has('baseClass');
    }

    /**
     * @param string $class
     * @return bool
     */
    final public function isCommandClass(string $class) : bool
    {
        if($this->httpWrapper->query()->has('cmdClass')) {
            $cmdClass = $this->httpWrapper->query()->retrieve('cmdClass', $this->refinery->kindlyTo()->string());
        } else {
            $cmdClass= "";
        }

        return strtolower($class) === strtolower($cmdClass);
    }

    /**
     * @return bool
     */
    final public function hasCommandClass() : bool
    {
        return $this->httpWrapper->query()->has('cmdClass');
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
            strtolower($this->httpWrapper->query()->has('cmdClass') ? $this->httpWrapper->query()->retrieve('cmdClass', $this->refinery->kindlyTo()->string()) : ""),
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

            return str_contains(strtolower((string)$this->ctrl->getCmd()), strtolower($command));
        })) > 0;
    }

    /**
     * @return int
     */
    final public function getRefId() : int
    {
        if($this->httpWrapper->query()->has('ref_id')){
            return $this->httpWrapper->query()->retrieve('ref_id', $this->refinery->kindlyTo()->int());
        }
        return 0;
    }

    /**
     * @return int
     */
    final public function getTargetRefId() : int
    {
        $matches = null;
        if($this->httpWrapper->query()->has('target')){
            $target = $this->httpWrapper->query()->retrieve('target', $this->refinery->kindlyTo()->string());
        } else {
            $target = '';
        }
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

        return ($this->objectCache->lookupObjId($refId) === $objId);
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

        $objId = $this->objectCache->lookupObjId($refId);

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

        $objId = $this->objectCache->lookupObjId($refId);

        return $this->objectCache->lookupType($objId) === $type;
    }
}
