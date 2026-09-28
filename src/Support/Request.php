<?php

namespace Ddns\Support;

use Ddns\DdnsException;

final class Request
{
    /**
     * Reads a trimmed parameter from POST, falling back to GET.
     */
    public static function input(string $name, ?string $default = null): ?string
    {
        $value = $_POST[$name] ?? $_GET[$name] ?? null;

        if (!is_string($value)) {
            return $default;
        }

        return trim($value);
    }

    public static function required(string $name): string
    {
        $value = self::input($name);

        if ($value === null || $value === '') {
            throw new DdnsException(str_replace('_', ' ', $name) . ' is empty');
        }

        return $value;
    }

    /**
     * The client's address. Proxy headers are ignored: Caddy faces clients
     * directly, so they would be client-supplied and spoofable.
     */
    public static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (!Ip::isValid($ip)) {
            throw new DdnsException('No valid IP');
        }

        return $ip;
    }
}
