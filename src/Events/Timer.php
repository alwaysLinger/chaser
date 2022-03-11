<?php

namespace Al\Chaser\Events;

use Al\Chaser\Contracts\Event;

class Timer
{
    // millisecond level supported
    public static function after(float $msec, callable $callback, array $args = []): int
    {
        return app('worker')->addTimer($msec, $callback, $args, Event::EV_TIMER_AFTER);
    }

    public static function tick(int $msec, callable $callback, array $args = []): int
    {
        return app('worker')->addTimer($msec, $callback, $args, Event::EV_TIMER_TICK);
    }

    public static function clear(int $timerId): bool
    {
        return \call_user_func(function () use ($timerId) {
            return app('worker')->clearTimer($timerId);
        });
    }

    public static function clearAll(): bool
    {
        return \call_user_func(function () {
            return app('worker')->clearTimer();
        });
    }
}