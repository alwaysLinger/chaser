<?php

namespace Al\Chaser\Stages;

use Al\Chaser\App;
use Al\Chaser\Providers\ConfigServiceProvider;
use Al\Chaser\Providers\LogServiceProvider;
use League\Pipeline\StageInterface;

class AppInit implements StageInterface
{
    private App $app;

    protected array $baseServices = [
        ConfigServiceProvider::class,
        LogServiceProvider::class,
    ];

    /**
     * @param App $app
     * @return App
     */
    public function __invoke($app)
    {
        $this->app = $app;
        $app->bind(App::class, $app);
        $this->registerBaseServices();
        (fn() => $this->setLogPath())->call($this->app);
        (fn() => $this->setDebugMode())->call($this->app);
        return $app;
    }

    private function registerBaseServices(): void
    {
        foreach ($this->baseServices as $service) {
            $this->app->call([new $service($this->app), 'register']);
        }
    }
}

;