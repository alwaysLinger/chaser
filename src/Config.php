<?php

namespace Al\Chaser;

use Illuminate\Support\Arr;
use Symfony\Component\Finder\Finder;

class Config implements \ArrayAccess
{
    protected array $repos = [];

    public function __construct(array $dirs)
    {
        $finder = new Finder();
        foreach ($dirs as $dir) {
            $finder->files('*.php')->in($dir);
            if ($finder->hasResults()) {
                foreach ($finder as $fileInfo) {
                    $this->repos[$fileInfo->getFilenameWithoutExtension()] = require_once $fileInfo->getRealPath();
                }
            }
        }
    }

    public function get(string $key, $default = null)
    {
        return Arr::get($this->repos, $key, $default);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->repos, $key);
    }

    public function set(string $key, $value): array
    {
        return Arr::set($this->repos, $key, $value);
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
        return false;
    }
}