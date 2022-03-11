<?php

namespace Al\Chaser;

use Al\Chaser\Commands\StartCommand;
use Al\Chaser\Providers\ConfigServiceProvider;
use Al\Chaser\Providers\ConsoleServiceProvider;
use Al\Chaser\Providers\LogServiceProvider;
use Al\Chaser\Stages\AppInit;
use Al\Chaser\Stages\EnvCheck;
use Al\Chaser\Stages\ExceptionHandler;
use Al\Chaser\Stages\ExtendProviders;
use Al\Chaser\Stages\RegisterProviders;
use DI\Container;
use DI\ContainerBuilder;
use Illuminate\Support\Arr;
use League\Pipeline\PipelineBuilder;

class App extends ContainerBuilder implements \ArrayAccess
{
    const NAME = 'Chaser';
    const VERSION = '0.0.1';

    private array $servers = [];

    private Container $container;
    private static $app;
    private bool $debug = true;

    // TODO 将依赖改为可修改的
    // TODO make dependencies modifiable
    protected array $dependencies = [];

    protected array $stages = [
        EnvCheck::class,
        AppInit::class,
        ExceptionHandler::class,
        RegisterProviders::class,
        ExtendProviders::class,
    ];

    private array $confPath = [];

    private array $baseServices = [
        ConfigServiceProvider::class,
        LogServiceProvider::class,
    ];

    protected array $providers = [
        ConsoleServiceProvider::class,
    ];

    // TODO 需改命令为可设置
    protected array $commands = [
        StartCommand::class,
        // StatusCommand::class,
    ];

    private string $logPath = '';

    private function __construct(array $confPath)
    {
        $this->confPath = $confPath ?: [BASE_PATH . '/config'];
        parent::__construct();
        $this->addDefinitions($this->dependencies);
        $this->container = $this->build();

        $builder = new PipelineBuilder();
        foreach ($this->stages as $stage) {
            $builder->add(new $stage());
        }
        $builder->build()->process($this);
    }

    public static function getInstance(array $confPath = []): self
    {
        if (!self::$app instanceof self) {
            static::$app = new static($confPath);
        }
        return static::$app;
    }

    public function runApp(): void
    {
        $this['console']->run();
    }

    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getConfPath(): array
    {
        return $this->confPath;
    }

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        return $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        // TODO: Implement offsetUnset() method.
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function bind(string $abstract, $factory): void
    {
        $this->set($abstract, $factory);
    }

    public function __call($name, $arguments)
    {
        return $this->container->{$name}(...$arguments);
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    public function getServer(string $serverName = '')
    {
        if (empty($serverName)) {
            return $this->servers;
        }
        return Arr::get(Arr::wrap($this->servers), $serverName, null);
    }

    private function setLogPath(): void
    {
        $this->logPath = $this['config']->get('app.logpath') ?? BASE_PATH . '/storage';
    }

    public function getLogPath(): string
    {
        return $this->logPath;
    }

    // get current server
    private function server(): string
    {
        if ($this->servers) {
            return '';
        }
        $workers = [];
        foreach ($this->servers as $serverName => $server) {
            $workers[$serverName] = (fn() => $this->workers)->call($server);
        }
        $names = Arr::where($workers, fn($worker) => \in_array(\posix_getpid(), $worker));
        return Arr::first(\array_keys($names), null, '');
    }

    // get current process role
    private function role(): string
    {
        $serverName = $this->server();
        if (empty($serverName)) {
            return '';
        }
        return (fn() => $this->isWorker ? 'worker' : 'master')->call($this->getServer($serverName));
    }

    public function context(): array
    {
        $server = $this->server();
        $role   = $this->role();
        return compact('server', 'role');
    }

    private function registerBaseService()
    {
        foreach ($this->baseServices as $service) {
            $this->call([new $service($this), 'register']);
        }
    }

    private function setDebugMode()
    {
        $this->debug = $this['config']->get('app.debug') ?? true;
    }

    public function debugMode(): bool
    {
        return $this->debug;
    }

    public function shutdownServer(string $serverName)
    {
        $pidFile = $this->getLogPath() . "/logs/pids/{$serverName}.pid";
        if (!\file_exists($pidFile)) {
            exit(\sprintf("%s pid file dose not exist\n"));
        }
        if ($pid = \file_get_contents($pidFile)) {
            if (!\posix_kill($pid, 0)) {
                $errno = \posix_get_last_error();
                exit(\sprintf("%s is not alive, errno:%d, errstr:%s\n", $serverName, $errno, \posix_strerror($errno)));
            }
            if (!\posix_kill($pid, \SIGINT)) {
                $errno = \posix_get_last_error();
                exit(\sprintf("stop %s failed, errno:%d, errstr:%s\n", $serverName, $errno, \posix_strerror($errno)));
            }
        }
    }
}