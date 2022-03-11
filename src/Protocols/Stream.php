<?php

namespace Al\Chaser\Protocols;

class Stream implements Protocol
{
    protected int $packageMaxLength;
    protected string $packageLengthType;
    protected int $packageBodyOffset;

    public function __construct(int $packageMaxLength, string $packageLengthType, int $packageBodyOffset)
    {
        $this->packageMaxLength  = $packageMaxLength;
        $this->packageLengthType = $packageLengthType;
        $this->packageBodyOffset = $packageBodyOffset;
    }

    public function containsOne(string $data): bool
    {
        if (strlen($data) <= $this->packageBodyOffset) {
            return false;
        }
        $packageLen = unpack($this->packageLengthType, substr($data, 0, $this->packageBodyOffset))[1] + $this->packageBodyOffset;
        if ($packageLen > $this->packageMaxLength) {
            // TODO 当数据报过大
            return false;
        }
        if (strlen($data) < $packageLen) {
            return false;
        }
        return true;
    }

    public function encode(string $data): string
    {
        return pack($this->packageLengthType, strlen($data)) . $data;
    }

    public function decode(string $data): string
    {
        $packageLen = unpack($this->packageLengthType, substr($data, 0, $this->packageBodyOffset))[1];
        return substr($data, $this->packageBodyOffset, $packageLen);
    }

    // 返回一条完整数据的长度
    public function msgLen(string $data): int
    {
        return $this->packageBodyOffset + unpack($this->packageLengthType, substr($data, 0, $this->packageBodyOffset))[1];
    }
}