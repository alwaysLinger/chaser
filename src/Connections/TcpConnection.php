<?php

namespace Al\Chaser\Connections;

use Al\Chaser\Contracts\Event;
use Al\Chaser\Protocols\Protocol;
use Al\Chaser\Servers\Server;

// TODO 重构方式 使用socket_recvfrom配合peek参数 socket_set_option设置发送和接收缓冲区大小 socket_get_line自动读取一段完整的数据

class TcpConnection
{
    const STATUS_CONNECTED = 1;
    const STATUS_CLOSED = 2;

    private int $status = self::STATUS_CLOSED;
    private int $id;
    private $sock;
    private Server $worker;
    private $proto;
    private $clientInfo;
    private Event $eventLoop;
    private int $maxSendBuffer;
    private int $maxRecvBuffer;
    private int $packageMaxLength;

    // 缓存区
    private string $recvBuffer = '';
    // 缓存区的长度
    private int $recvLen = 0;
    // 每次read的长度
    private int $readLen = 1024;

    // 发送缓冲区
    private string $sendBuffer = '';
    // 缓存区的长度
    private int $sendLen = 0;

    public function __construct($sock, $worker)
    {
        \stream_set_blocking($sock, false);
        \stream_set_read_buffer($sock, 0);
        \stream_set_write_buffer($sock, 0);
        $this->id         = (int)$sock;
        $this->sock       = $sock;
        $this->clientInfo = \stream_socket_get_name($this->sock, true);
        $this->worker     = $worker;

        $this->inheritProps($this->worker, ['proto', 'eventLoop', 'maxSendBuffer', 'maxRecvBuffer', 'packageMaxLength']);
        $this->addToLoop(Event::EV_READ);
        $this->status = self::STATUS_CONNECTED;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function sock()
    {
        return $this->sock;
    }

    public function proto(): ?Protocol
    {
        return $this->proto;
    }

    private function inheritProps(object $from, array $props): void
    {
        foreach ($props as $prop) {
            $this->inheritProp($from, $prop);
        }
    }

    private function inheritProp(object $from, string $prop): void
    {
        $this->$prop = (fn() => $this->$prop)->call($from);
    }

    private function addToLoop(int $flag): bool
    {
        return $this->eventLoop->add($this->sock, $flag, \Closure::fromCallable([$this, 'recv']), [$this]);
    }

    private function recv(): void
    {
        if ($this->recvLen < $this->maxRecvBuffer) {
            $data = \stream_socket_recvfrom($this->sock, $this->readLen);
            if ($this->needClose($data)) {
                $this->worker->close($this);
            }
            $this->recvBuffer .= $data;
            $this->recvLen    += \strlen($data);
        } else {
            // TODO when the application recv buffer is full
        }

        $this->handleAndTrigger();
    }

    private function needClose(string $data): bool
    {
        return \strlen($data) === 0 || $data === false || !\is_resource($this->sock) || \feof($this->sock);
    }

    private function close(bool $force): void
    {
        // TODO 从描述符中移除 关闭连接 触发回调
        if ($this->status === self::STATUS_CLOSED) {
            return;
        }

        $this->delFromLoop(Event::EV_READ);
        $this->delFromLoop(Event::EV_WRITE);
        @\stream_socket_shutdown($this->sock, \STREAM_SHUT_RDWR);
        $this->clearBuffer();
        unset($this->worker->connections[$this->id]);
        $this->status = self::STATUS_CLOSED;
        if (!$force) {
            $this->triggerEvent(Server::CLOSE, [$this->worker, $this]);
        }
    }

    private function delFromLoop(int $flag): bool
    {
        return $this->eventLoop->del($this->sock, $flag);
    }

    private function handleAndTrigger(): void
    {
        if (\is_object($this->proto)) {
            while ($this->proto()->containsOne($this->recvBuffer)) {
                $msgLen           = $this->proto()->msgLen($this->recvBuffer);
                $this->recvLen    -= $msgLen;
                $msg              = $this->proto()->decode($this->recvBuffer);
                $this->recvBuffer = \substr($this->recvBuffer, $msgLen);
                $this->triggerEvent(Server::RECEIVE, [$this->worker, $this, $msg]);
            }
        } else {
            $msg              = $this->recvBuffer;
            $this->recvBuffer = '';
            $this->recvLen    = 0;
            $this->triggerEvent(Server::RECEIVE, [$this->worker, $this, $msg]);
        }
    }

    private function triggerEvent(string $eventName, array $args): void
    {
        (fn() => $this->triggerEvent($eventName, $args))->call($this->worker);
    }

    private function clearBuffer(): void
    {
        $this->recvBuffer = '';
        $this->recvLen    = 0;
        $this->sendBuffer = '';
        $this->sendLen    = 0;
    }

    private function send(string $data)
    {
        $payload          = $this->proto->encode($data);
        $this->sendBuffer .= $payload;
        $this->sendLen    += strlen($payload);
        $res              = @\stream_socket_sendto($this->sock, $this->sendBuffer);
        if (!$res) {
            $this->worker->close($this);
            return false;
        }
        $this->sendBuffer = substr($this->sendBuffer, (int)$res);
        $this->sendLen    -= $res;
        if ($this->sendLen > 0) {
            $this->eventLoop->add($this->sock, Event::EV_WRITE, \Closure::fromCallable([$this, 'sendto']), [$this]);
        }
        return $res;
    }

    private function sendto(): void
    {
        if ($this->sendLen = 0) {
            return;
        }
        $res = @\stream_socket_sendto($this->sock, $this->sendBuffer);
        if (!$res) {
            $this->worker->close($this);
        }
        $this->sendBuffer = substr($this->sendBuffer, (int)$res);
        $this->sendLen    -= $res;
    }

    private function clearTimers(): void
    {

    }
}