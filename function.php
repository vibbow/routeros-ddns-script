<?php

if (!function_exists('filterInputPostGet')) {
    function filterInputPostGet(string $name, ?string $default = null): ?string
    {
        $value = $_POST[$name] ?? $_GET[$name] ?? null;

        if ($value === null) {
            return $default;
        }

        return trim($value);
    }
}

function getIP(): string
{
    $manualIP = filterInputPostGet('ip');

    if (!empty($manualIP)) {
        $ip = $manualIP;
    } else {
        $ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = trim(explode(',', $ip)[0]);
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new Exception('No valid IP');
    }

    return $ip;
}

function getIPType(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return 'A';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return 'AAAA';
    }

    throw new Exception('Unknown IP type');
}
