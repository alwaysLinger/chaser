<?php


namespace Al\Chaser\Protocols;


interface Protocol
{
    // determine buffer contains at least one complete message
    public function containsOne(string $data): bool;

    public function encode(string $data): string;

    public function decode(string $data): string;

    public function msgLen(string $data): int;
}