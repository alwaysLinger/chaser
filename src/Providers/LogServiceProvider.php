<?php

namespace Al\Chaser\Providers;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LogServiceProvider extends ServiceProvider
{
    protected $name = 'log';

    public function register()
    {
        $this->app->bind($this->getName(), function () {
            $stdHandler = new StreamHandler(\STDOUT, Logger::DEBUG, true, 0644, true);
            $format    = "[%datetime%] [%extra%] [%context%]\n%level_name%: %message%\n";
            $formatrer = new LineFormatter($format, 'Y-m-d H:i:s', true);
            $stdHandler->setFormatter($formatrer);
            $rotateHandler = new RotatingFileHandler($this->app->getLogPath() . '/logs/chaser.log', 7, Logger::DEBUG, true, 0644, true);
            $rotateHandler->setFormatter($formatrer);
            if (!app()->debugMode()) {
                $rotateHandler->setBubble(false);
            }
            $logger = new Logger('chaser', [$rotateHandler, $stdHandler], [function ($record) {
                // $record['extra']['context'] = app()->context();
                $record['extra']['pid']     = \posix_getpid();
                return $record;
            }]);
            return $logger;
        });
    }

    public function boot()
    {
        //TODO register other log handler
    }
}