<?php

namespace Al\Chaser\Providers;

use Symfony\Component\EventDispatcher\EventDispatcher;

class EventServiceProvider extends ServiceProvider
{
    protected $name = 'event';

    public function register()
    {
        $this->app->bind($this->getName(), function () {
            $dispatcher = new EventDispatcher();
        });
    }

    public function boot()
    {
        //
    }
}