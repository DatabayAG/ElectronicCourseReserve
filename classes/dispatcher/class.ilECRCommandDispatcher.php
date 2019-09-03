<?php

/**
 * Class ilECRCommandDispatcher
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilECRCommandDispatcher
{
    /**
     * @var self
     */
    private static $instance = null;

    /**
     * @var ilUIHookPluginGUI
     */
    protected $controller;

    /**
     *
     */
    private function __clone()
    {
    }

    /**
     * @param $controller
     */
    private function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * @param  $controller
     * @return self
     */
    public static function getInstance($controller)
    {
        if (self::$instance === null) {
            self::$instance = new self($controller);
        }
        return self::$instance;
    }

    /**
     * @param string $cmd
     * @return string
     */
    public function dispatch($cmd)
    {
        $controller = $this->getController($cmd);
        $command = $this->getCommand($cmd);
        $controller = $this->instantiateController($controller);
        return $controller->$command();
    }

    /**
     * @param string $cmd
     * @return string
     */
    protected function getController($cmd)
    {
        $parts = explode('.', $cmd);

        $controller = $parts[0];
        return $controller;
    }

    /**
     * @param string $cmd
     * @return string
     */
    protected function getCommand($cmd)
    {
        $parts = explode('.', $cmd);

        $cmd = $parts[1];

        return $cmd;
    }

    /**
     * @param string $controller
     * @return mixed
     */
    protected function instantiateController($controller)
    {
        ilElectronicCourseReservePlugin::getInstance()->includeClass('./controller/class.' . $controller . '.php');

        return new $controller($controller);
    }

    /**
     * @return string
     */
    protected function getControllerPath()
    {
//
//		
//		$path = $this->getCoreController()->getPluginObject()->getDirectory() .
//			DIRECTORY_SEPARATOR .
//			'classes' .
//			DIRECTORY_SEPARATOR .
//			'controller' .
//			DIRECTORY_SEPARATOR;
//		
//		return $path;
    }

    /**
     * @param string $controller
     */
    protected function requireController($controller)
    {

    }

    /**
     * @return mixed
     */
    public function getCoreController()
    {
        return $this->controller;
    }

    /**
     * @param $controller
     */
    public function setCoreController($controller)
    {
        $this->controller = $controller;
    }
}