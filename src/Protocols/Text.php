<?php

namespace Al\Chaser\Protocols;

class Text implements Protocol
{
    protected string $packageEof;

    public function __construct(string $packageEof)
    {
        $this->packageEof = $packageEof;
    }

    public function containsOne(string $data): bool
    {
        // return strpos($data, $this->packageEof) === false;
        return strpos($data, $this->packageEof) !== false;
    }

    public function encode(string $data): string
    {
        return $data . $this->packageEof;
    }

    public function decode(string $data): string
    {
        return rtrim($data, $this->packageEof);
    }

    // 返回buffer中第一条完整消息的长度
    public function msgLen(string $data): int
    {
        return strpos($data, $this->packageEof) . strlen($this->packageEof);
    }
}