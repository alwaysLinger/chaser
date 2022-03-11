<?php

namespace Al\Chaser\Stages;

use Al\Chaser\App;
use League\Pipeline\StageInterface;

class EnvCheck implements StageInterface
{
    /**
     * @param App $app
     * @return App
     */
    public function __invoke($app)
    {
        if (\version_compare(PHP_VERSION, '7.4', '<')) {
            exit('php 7.4 requried' . PHP_EOL);
        }
        if ('cli' !== \PHP_SAPI) {
            exit('only run in command line mode' . PHP_EOL);
        }
        if ('Linux' !== \php_uname('s')) {
            exit('only support linux platform' . PHP_EOL);
        }
        if (!\extension_loaded('mbstring')) {
            exit('require mbstring extension enabled' . PHP_EOL);
        }
        if (!\extension_loaded('posix')) {
            exit('require posix extension enabled' . PHP_EOL);
        }
        if (!\extension_loaded('pcntl')) {
            exit('require pcntl extension enabled' . PHP_EOL);
        }
        if (!\extension_loaded('event')) {
            exit('require event extension enabled' . PHP_EOL);
        }
        return $app;
    }

}