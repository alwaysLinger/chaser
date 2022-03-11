<?php


namespace Al\Chaser\Servers;


use Illuminate\Support\Arr;

class ServerManager
{
    protected array $servers;

    public function __construct()
    {
    }

    public function get(string $serverName)
    {
        return Arr::get($this->servers, null);
    }
}