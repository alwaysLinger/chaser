<?php

namespace Al\Chaser\Contracts;

interface Connection
{
    public function isConnected(): bool;

    public function close(): bool;

    public function sock();
}