<?php

namespace Al\Chaser\Providers;

use Al\Chaser\App;

abstract class ServiceProvider
{
    protected $app;
    protected $name;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function register()
    {
        //
    }

    public function boot()
    {
        //
    }

    protected function getName()
    {
        return $this->name ?? static::class;
    }
}