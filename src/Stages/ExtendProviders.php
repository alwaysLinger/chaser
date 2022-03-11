<?php

namespace Al\Chaser\Stages;

use Al\Chaser\App;
use League\Pipeline\StageInterface;

class ExtendProviders implements StageInterface
{
    /**
     * @param App $app
     * @return App
     */
    public function __invoke($app)
    {
        foreach ($app->getProviders() as $provider) {
            $app->call([new $provider($app), 'boot']);
        }
        return $app;
    }
}