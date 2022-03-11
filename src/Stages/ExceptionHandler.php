<?php

namespace Al\Chaser\Stages;

use Al\Chaser\App;
use League\Pipeline\StageInterface;

class ExceptionHandler implements StageInterface
{
    /**
     * @param App $app
     * @return App
     */
    public function __invoke($app)
    {
        \error_reporting(\E_ALL);
        \set_error_handler(\Closure::fromCallable([$this, 'error']));
        \set_exception_handler(\Closure::fromCallable([$this, 'exception']));
        \register_shutdown_function(\Closure::fromCallable([$this, 'shutdown']));

        return $app;
    }

    private function error(int $errno, string $errstr, string $errfile = '', int $errline = 0): void
    {
        throw new \ErrorException(\sprintf('%s FILE:%s LINE:%d ERRNO:%d', $errstr, $errfile, $errline, $errno),
            $errno, $errno, $errfile, $errline);
    }

    private function shutdown(): void
    {
        if (!\is_null($error = \error_get_last()) && $this->isFatal($error['type'])) {
            throw new \ErrorException(\sprintf('%s FILE:%s LINE:%d ERRNO:%d', $error['message'], $error['file'], $error['line'], $error['tpye']),
                $error['type'], $error['type'], $error['file'], $error['line']);
        }
    }

    private function isFatal(int $errno): bool
    {
        return \in_array($errno, [\E_ERROR, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_PARSE]);
    }

    private function exception(\Throwable $th): void
    {
        $this->logAndRecond($th);
    }

    private function logAndRecond(\Throwable $th): void
    {
        app('log')->error($th->getMessage() . "\nTRACE:" . $th->getTraceAsString(), [
            'file' => $th->getFile(),
            'line' => $th->getLine(),
            'code' => $th->getCode(),
        ]);
    }
}