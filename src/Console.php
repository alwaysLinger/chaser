<?php

namespace Al\Chaser;

use Symfony\Component\Console\Application;

class Console extends Application
{
    public $app;

    public function __construct()
    {
        parent::__construct(App::NAME, App::VERSION);
        $this->setCatchExceptions(false);
    }
}