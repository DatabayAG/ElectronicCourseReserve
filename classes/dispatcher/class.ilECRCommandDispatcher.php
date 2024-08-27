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

        return $parts[0];
    }

    protected function getCommand(string $cmd): string
    {
        $parts = explode('.', $cmd);

        return $parts[1];
    }

    protected function instantiateController(string $controller): mixed
    {
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


    public function getCoreController(): ilUIHookPluginGUI
    {
        return $this->controller;
    }

    /**
     * @param $controller
     */
    public function setCoreController($controller): void
    {
        $this->controller = $controller;
    }
}
