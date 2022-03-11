<?php

namespace Al\Chaser\Events;

use Al\Chaser\Contracts\Event;
use Illuminate\Support\Arr;

// TODO code optimise
class Epoll implements Event
{
    private $eventBase;
    private array $events = [];
    private array $sigEvents = [];
    private int $timerId = 1;
    private array $timers = [];

    public function __construct()
    {
        $this->eventBase = new \EventBase();
    }

    public function add($fd, int $flag, callable $callback, array $args = [])
    {
        switch ($flag) {
            case self::EV_READ:
                $event = new \Event($this->eventBase, $fd, \Event::READ | \Event::PERSIST, $callback, $args);
                if ($event->add()) {
                    $this->events[(int)$fd][self::EV_READ] = $event;
                    return true;
                }
                return false;
                break;
            case self::EV_WRITE:
                $event = new \Event($this->eventBase, $fd, \Event::WRITE | \Event::PERSIST, $callback, $args);
                if ($event->add()) {
                    $this->events[(int)$fd][self::EV_WRITE] = $event;
                    return true;
                }
                return false;
                break;
            case self::EV_SIGNAL:
                $event = new \Event($this->eventBase, $fd, \Event::SIGNAL, $callback, $args);
                if ($event->add()) {
                    $this->sigEvents[(int)$fd] = $event;
                    return true;
                }
                return false;
                break;
            case self::EV_TIMER_AFTER:
            case self::EV_TIMER_TICK:
                //as for the timer event, the var fd is the interval here
                if ($this->timerModifier($flag, ++$this->timerId, $fd, $callback, $args)) {
                    return $this->timerId;
                }
                return 0;
                break;
            default:
                return false;
        }
    }

    public function del($fd, int $flag): bool
    {
        switch ($flag) {
            case self::EV_READ:
                if (isset($this->events[(int)$fd][self::EV_READ])) {
                    if (!$this->events[(int)$fd][self::EV_READ]->del()) {
                        return false;
                    }
                    $this->events[(int)$fd][self::EV_READ]->free();
                    unset($this->events[(int)$fd][self::EV_READ]);
                }
                if (empty($this->events[(int)$fd])) {
                    unset($this->events[(int)$fd]);
                }
                return true;
                break;
            case self::EV_WRITE:
                if (isset($this->events[(int)$fd][self::EV_WRITE])) {
                    if (!$this->events[(int)$fd][self::EV_WRITE]->del()) {
                        return false;
                    }
                    $this->events[(int)$fd][self::EV_WRITE]->free();
                    unset($this->events[(int)$fd][self::EV_WRITE]);
                }
                if (empty($this->events[(int)$fd])) {
                    unset($this->events[(int)$fd]);
                }
                return true;
                break;
            case self::EV_TIMER_AFTER:
            case self::EV_TIMER_TICK:
                // var fd represents the timerid here
                if (isset($this->timers[$fd])) {
                    if (!$this->timers[$fd]->del()) {
                        return false;
                    }
                    $this->timers[$fd]->free();
                    unset($this->timers[$fd]);
                    return true;
                }
                return false;
                break;
            default:
                return false;
        }
    }

    public function loop(): void
    {
        $this->eventBase->loop();
    }

    private function timerModifier(int $flag, int $timerId, float $interval, callable $callback, array $args): bool
    {
        $event = new \Event($this->eventBase, -1, \Event::TIMEOUT | \Event::PERSIST, function () use ($flag, $timerId, $callback) {
            if ($flag === self::EV_TIMER_AFTER) {
                if (isset($this->timers[$timerId])) {
                    if ($this->timers[$timerId]->del()) {
                        $this->timers[$timerId]->free();
                        unset($this->timers[$timerId]);
                    }
                }
            }
            \call_user_func($callback, $timerId, \func_get_arg(2));
        }, $args);
        if ($event->add($interval)) {
            $this->timers[$timerId] = $event;
            return true;
        }
        return false;
    }

    // TODO 好好考虑event的移除
    public function exitLoop(): void
    {
        $this->clearRdEvents();
        $this->eventBase->stop();
        $this->eventBase->free();
        $this->eventBase = null;
    }

    private function clearRdEvents()
    {
        $this->events = [];
    }

    private function clearTimers(): bool
    {
        foreach ($this->timers as $timerId => $event) {
            if (!$event->del()) {
                return false;
            }
            $event->free();
            unset($this->timer[$timerId]);
        }
        $this->timers = [];
        return true;
    }

    private function clearSigHandler()
    {
        foreach ($this->sigEvents as $event) {
            $event->del();
            $event->free();
        }
        $this->sigEvents = [];
    }
}