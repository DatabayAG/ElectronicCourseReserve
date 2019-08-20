<?php

/**
 * Class ilECRBaseController
 * @author Nadia Matuschek <nmatuschek@databay.de>
 */
abstract class ilECRBaseController
{
    /**
     * @var
     */
    protected $controller;

    /**
     * ilECRBaseController constructor.
     * @param $controller
     */
    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return mixed
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param $controller
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }
}