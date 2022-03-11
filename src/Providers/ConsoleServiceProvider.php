<?php

namespace Al\Chaser\Providers;

use Al\Chaser\Console;

class ConsoleServiceProvider extends ServiceProvider
{
    protected $name = 'console';

    public function register()
    {
        $this->app->bind($this->getName(), function () {
            return new Console();
        });
    }

    public function boot()
    {
        $this->app[$this->getName()]->addCommands(array_map(function ($comamnd) {
            return new $comamnd();
        }, $this->app->getCommands()));

        $this->app[$this->getName()]->app = $this->app;
    }
}