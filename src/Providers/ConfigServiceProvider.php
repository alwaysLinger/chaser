<?php

namespace Al\Chaser\Providers;

use Al\Chaser\Config;

class ConfigServiceProvider extends ServiceProvider
{
    protected $name = 'config';

    public function register()
    {
        $this->app->bind($this->getName(), function () {
            return $this->app->make(Config::class, [
                'dirs' => $this->app->getConfPath()
            ]);
        });
    }

    public function boot()
    {

    }
}