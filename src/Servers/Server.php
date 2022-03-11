<?php

namespace Al\Chaser\Servers;

use Al\Chaser\App;
use Al\Chaser\Config;
use Al\Chaser\Connections\TcpConnection;
use Al\Chaser\Contracts\Event;
use Al\Chaser\Events\Epoll;
use Al\Chaser\Protocols\Protocol;
use Al\Chaser\Protocols\Stream;
use Al\Chaser\Protocols\Text;

class Server
{
    const EOF_CHECK = 1;
    const LENGTH_CHECK = 2;

    const CONNECT = 'connect';
    const RECEIVE = 'receive';
    const CLOSE = 'close';

    const STATUS_STARTING = 0;
    const STATUS_RUNNING = 1;
    const STATUS_SHUTDOWN = 2;

    private int $serverStatus = self::STATUS_STARTING;

    protected App $app;
    protected string $serverName = '';

    protected string $host;
    protected int $port;
    protected array $supportedEvents = [self::CONNECT, self::RECEIVE, self::CLOSE];
    protected array $callbacks;
    protected Config $config;
    protected string $eventClass;
    protected ?Event $eventLoop = null;

    protected int $maxSendBuffer = 1024 * 1024 * 10;
    protected int $maxRecvBuffer = 1024 * 1024 * 10;

    protected $splitType;
    protected $packageEof;

    protected $packageMaxLength;
    protected $packageLengthType;
    protected $packageBodyOffset;

    private ?Protocol $proto;

    private int $workerNum = 1;
    private array $workers = [];
    private array $context = [];

    private bool $isMaster = true;
    private bool $isWorker = false;
    private int $workerId = 0;
    private int $workerPid = 0;
    private int $exitedWorkerPid = 0;

    private int $masterPid = 0;

    private $listenSock;
    public array $connections = [];

    public function __construct(App $app, string $serverName)
    {
        $this->app = $app;
        if ($this->app->getServer($serverName)) {
            throw new \Exception(\sprintf('%s server already exists', $serverName));
        }
        $this->serverName = $serverName;
        $this->pidFile    = $this->app->getLogPath() . '/pids/' . $this->serverName . '.pid';
        $this->config     = $app['config'];
        $confKey          = "server.{$serverName}";
        $this->host       = $this->config["{$confKey}.host"];
        $this->port       = $this->config["{$confKey}.port"];
        $this->workerNum  = $this->config->get("{$confKey}.worker_num", 1);
        $this->eventClass = $this->config->get("{$confKey}.event", Epoll::class);
        if ($this->config["{$confKey}.package_eof_check"]) {
            $this->splitType  = self::EOF_CHECK;
            $this->packageEof = $this->config["{$confKey}.package_eof"];
        }
        if ($this->config["{$confKey}.package_length_check"]) {
            $this->splitType         = self::LENGTH_CHECK;
            $this->packageMaxLength  = $this->config["{$confKey}.package_max_length"];
            $this->packageLengthType = $this->config["{$confKey}.package_length_type"];
            $this->packageBodyOffset = $this->config["{$confKey}.package_body_offset"];
        }
        $this->attachToApp($serverName, $this);
    }

    public function on(string $eventName, callable $callback): void
    {
        if (!in_array($eventName, $this->supportedEvents)) {
            throw new \Exception(\sprintf('%s event not supported in tcp server', $eventName));
        }
        $this->callbacks[$eventName] = $callback;
    }

    private function genProto(): ?Protocol
    {
        if ($this->splitType === self::EOF_CHECK) {
            return new Text($this->packageEof);
        }
        if ($this->splitType === self::LENGTH_CHECK) {
            return new Stream($this->packageMaxLength, $this->packageLengthType, $this->packageBodyOffset);
        }
        return null;
    }

    // TODO 子进程退出的时候清理所有的事件 信号监听等东西 一定要清理
    private function listen(): void
    {
        $flags            = \STREAM_SERVER_LISTEN | \STREAM_SERVER_BIND;
        $context          = \stream_context_create(['socket' => ['backlog' => 102400, 'so_reuseport' => 1]]);
        $this->listenSock = \stream_socket_server(\sprintf('tcp://%s:%d', $this->host, $this->port), $errno, $errstr, $flags, $context);
        if (!\is_resource($this->listenSock)) {
            throw new \Exception("server created failed, errno: $errno, errstr: $errstr");
            exit(0);
        }
        \stream_set_blocking($this->listenSock, false);
        \stream_set_read_buffer($this->listenSock, 0);
        $socket = \socket_import_stream($this->listenSock);
        \socket_set_option($socket, \SOL_SOCKET, \SO_KEEPALIVE, 1);
        \socket_set_option($socket, \SOL_TCP, \TCP_NODELAY, 1);
    }

    private function accept(): void
    {
        if ($this->isShutdown()) {
            return;
        }
        if (!\is_resource($this->listenSock)) {
            throw new \Exception('main listen socket error');
        }
        $sock = @\stream_socket_accept($this->listenSock, 0, $peername);
        if (!\is_resource($sock)) {
            $this->app['log']->error('accept connection failed');
            return;
        }
        $connection                           = new TcpConnection($sock, $this);
        $this->connections[$connection->id()] = $connection;
        $this->triggerEvent(self::CONNECT, [$this, $this->connections[$connection->id()]]);
    }

    private function loop()
    {
        $this->eventLoop->loop();
    }

    private function attachToApp(string $serverName, self $server): void
    {
        (fn() => $this->servers[$serverName] = $server)->call($this->app);
    }

    private function triggerEvent(string $eventName, array $args = [])
    {
        if (!isset($this->callbacks[$eventName])) {
            throw new \Exception(\sprintf('event %s did not get registered', $eventName));
        }
        \call_user_func_array($this->callbacks[$eventName], $args);
    }

    public function send(TcpConnection $fd, string $data)
    {
        return (fn() => $this->send($data))->call($fd);
    }

    public function close(TcpConnection $fd, bool $force = false)
    {
        (fn() => $this->close($force))->call($fd);
    }
    // TODO getStatus 用来在各个地方判断 这个服务的状态

    // TODO 命令 stop 某个server  start 某个server 这些都可以通过命令行设置

    public function start(): void
    {
        // TODO 保存各种文件 然后启动的时候 根据server name进行判断 是否已经启动了
        $this->init();
        $this->doStart();
        $this->installSigHandler();
        $this->preFork();
        $this->masterWait();
    }

    private function init()
    {
        if (!\file_exists($this->pidFile)) {
            \touch($this->pidFile);
            \chown($this->pidFile, \posix_geteuid());
            \chmod($this->pidFile, 0644);
        }
    }

    private function doStart(): bool
    {
        if (\is_file($this->pidFile) && $pid = (int)\file_get_contents($this->pidFile)) {
            if ($pid && \posix_kill((int)$pid, 0) && \posix_getpid() !== $pid) {
                exit(\sprintf("chaser %s still running\n", $this->serverName));
            }
        }

        if (!\cli_set_process_title(\sprintf('chaser/%s-master', $this->serverName))) {
            throw new \Exception(\sprintf('set %s process title failed', $this->serverName));
        }

        $this->masterPid = \posix_getpid();

        \file_put_contents($this->pidFile, \posix_getpid());

        return true;
    }

    private function installSigHandler()
    {
        if ($this->isMaster) {
            // TODO 完善信号安装
            \pcntl_signal(\SIGINT, \Closure::fromCallable([$this, 'sigHandler']), false);
            \pcntl_signal(\SIGTERM, \Closure::fromCallable([$this, 'sigHandler']), false);
            \pcntl_signal(\SIGQUIT, \Closure::fromCallable([$this, 'sigHandler']), false);
        } elseif ($this->isWorker) {
            $this->eventLoop->add(\SIGINT, Event::EV_SIGNAL, \Closure::fromCallable([$this, 'terminateWorker']));
            $this->eventLoop->add(\SIGTERM, Event::EV_SIGNAL, \Closure::fromCallable([$this, 'terminateWorker']));
            $this->eventLoop->add(\SIGQUIT, Event::EV_SIGNAL, \Closure::fromCallable([$this, 'terminateWorker']));
        }
    }

    private function sigHandler(int $signo)
    {
        switch ($signo) {
            case \SIGINT:
            case \SIGTERM:
            case \SIGQUIT:
                // TODO
                $this->serverStatus = self::STATUS_SHUTDOWN;
                $this->shutdown();

                app('log')->info(\sprintf('%s all workers exited', $this->serverName));
                break;
        }
    }

    private function waitWorker()
    {
        $workerPid = \pcntl_wait($status);
        if ($workerPid > 0) {
            $this->workers = \array_map(fn($worker) => $worker === $workerPid ? 0 : $worker, $this->workers);
            $this->app['log']->info(\sprintf('%s wait worker pid:%d', $this->serverName, $workerPid));
        }
        if (!$this->isShutdown()) {
            app('log')->debug('try to pull another worker');
            [$this->workerId, $this->exitedWorkerPid] = $this->exitedWorker($workerPid);
            $this->workers[$this->workerId] = 0;
            $this->pullWorker();
        }
    }

    private function exitedWorker(int $workerPid): array
    {
        if ($workerPid > 0) {
            return [\array_search($workerPid, $this->workers), $workerPid];
        }
    }

    private function pullWorker(): int
    {
        $pid = \pcntl_fork();
        if (0 === $pid) {
            $this->forkWorker();
        } else {
            $this->workers[$this->workerId] = $pid;
            return $pid;
        }
    }

    private function uninstallSigs(): void
    {
        \pcntl_signal(\SIGINT, \SIG_IGN, false);
        \pcntl_signal(\SIGTERM, \SIG_IGN, false);
        \pcntl_signal(\SIGQUIT, \SIG_IGN, false);

        \pcntl_signal(\SIGPIPE, \SIG_IGN, false);
    }

    private function preFork()
    {
        for ($i = 1; $i <= $this->workerNum; $i++) {
            $this->workerId = $i;
            $pid            = \pcntl_fork();
            if (0 === $pid) {
                $this->forkWorker();
            } else {
                $this->workers[$i] = $pid;
            }
        }
    }

    private function forkWorker()
    {
        $this->app->bind('worker', $this);
        $this->listen();
        \cli_set_process_title(\sprintf('chaser/%s-worker#%d', $this->serverName, $this->workerId));
        $this->isMaster  = false;
        $this->isWorker  = true;
        $this->workerPid = \posix_getpid();
        $this->proto     = $this->genProto();
        $this->eventLoop = new $this->eventClass();
        $this->uninstallSigs();
        $this->installSigHandler();
        $this->eventLoop->add($this->listenSock, Epoll::EV_READ, \Closure::fromCallable([$this, 'accept']), []);
        $this->loop();
        $this->app['log']->info(\sprintf('%s-worker#%d-pid:%d exited', $this->serverName, $this->workerId, $this->workerPid));
        exit(0);
    }

    private function masterWait()
    {
        $this->serverStatus = self::STATUS_RUNNING;
        while ($this->workerAlive()) {
            \pcntl_signal_dispatch();
            $this->waitWorker();
            \pcntl_signal_dispatch();
        }
        $this->app['log']->info('master exited');
    }

    private function isRunning(): bool
    {
        return $this->serverStatus === self::STATUS_RUNNING;
    }

    private function isShutdown(): bool
    {
        return $this->serverStatus === self::STATUS_SHUTDOWN;
    }

    private function workerAlive(): bool
    {
        $aliveWorkers = \array_filter($this->workers, fn($worker) => $worker !== 0 && \posix_kill($worker, 0));
        return !empty($aliveWorkers);
    }

    // TODO 关闭master的listen socket
    public function shutdown()
    {
        // wait a little bit for the sigint signal, the signal will interrupt the master wait
        usleep(0.2 * 1000000);
        foreach ($this->workers as $worker) {
            $res = \posix_kill($worker, \SIGINT);
        }
        if (\is_resource($this->listenSock)) {
            \stream_socket_shutdown($this->listenSock, \STREAM_SHUT_RD);
        }
    }

    public function terminateWorker()
    {
        \fclose($this->listenSock);
        $this->app['log']->debug('worker try to exit');

        // TODO 关掉listen socket
        $this->eventLoop->del($this->listenSock, Event::EV_READ);
        $this->connections = [];
        $this->eventLoop->exitLoop();
    }

    public function addTimer(float $msec, callable $callback, array $args, int $flag): int
    {
        if (!$this->eventLoop) {
            return 0;
        }
        return $this->eventLoop->add($msec, $flag, $callback, $args);
    }

    public function clearTimer(int $timerId = 0): bool
    {
        if (!$this->eventLoop) {
            return false;
        }
        if ($timerId) {
            return $this->eventLoop->del($timerId, Event::EV_TIMER_AFTER);
        }
        return (fn() => $this->clearTimers())->call($this->eventLoop);
    }
}