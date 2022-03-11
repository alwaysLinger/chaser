<?php

namespace Al\Chaser\Contracts;

interface Event
{
    const EV_READ = 1;
    const EV_WRITE = 2;
    const EV_SIGNAL = 4;
    const EV_TIMER_TICK = 8;
    const EV_TIMER_AFTER = 16;

    public function add($fd, int $flag, callable $callback, array $args);

    public function del($fd, int $flag): bool;

    public function loop(): void;

    public function exitLoop(): void;
}