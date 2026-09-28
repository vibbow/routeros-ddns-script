<?php

namespace Ddns\Support;

/**
 * Tiny JSON file cache. Failures are silent: the cache is only an optimisation.
 */
final class Cache
{
    public function __construct(private readonly string $dir)
    {
    }

    public function get(string $key): ?array
    {
        $file = $this->path($key);

        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    public function set(string $key, array $value): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0755, true);
        }

        @file_put_contents($this->path($key), json_encode($value), LOCK_EX);
    }

    public function delete(string $key): void
    {
        @unlink($this->path($key));
    }

    private function path(string $key): string
    {
        return $this->dir . hash('sha256', $key) . '.json';
    }
}
