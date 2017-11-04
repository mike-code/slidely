<?php

namespace App\Controllers;

abstract class GenericController
{
    /**
     * Internal storage for application settings
     *
     * @var string
     */
    protected $app;

    /**
     * Internal storage for logger object
     *
     * @var string
     */
    protected $logger;

    /**
     * Constructor method
     *
     * @return void
     */
    function __construct($app, $logger)
    {
        $this->app    = $app;
        $this->logger = $logger;
    }

    /**
     * Wrapper method that throws LogicException exception object
     *
     * @return void
     */
    public function throwLogicalException($message)
    {
        throw new \App\LogicException($message);
    }
}
