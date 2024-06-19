<?php

/**
 * Class ilECRCommandDispatcher
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
class ilECRCommandDispatcher
{
    private static ?ilECRCommandDispatcher $instance = null;


    protected ilUIHookPluginGUI $controller;

    private function __clone()
    {
    }


    private function __construct($controller)
    {
        $this->controller = $controller;
    }

    public static function getInstance($controller): ilECRCommandDispatcher
    {
        if (self::$instance === null) {
            self::$instance = new self($controller);
        }
        return self::$instance;
    }

    public function dispatch(string $cmd): string
    {
        $controller = $this->getController($cmd);
        $command = $this->getCommand($cmd);
        $controller = $this->instantiateController($controller);
        return $controller->$command();
    }

    protected function getController(string $cmd): string
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

//    /**
//     * @return string
//     */
    protected function getControllerPath()  //@todo rausfinden wieso das auskommentiert
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
    protected function requireController(string $controller)
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