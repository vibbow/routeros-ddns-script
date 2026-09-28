<?php

namespace Ddns\Support;

use Ddns\DdnsException;

final class Ip
{
    public static function isValid(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * The DNS record type (A / AAAA) that holds this address.
     */
    public static function recordType(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'A';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'AAAA';
        }

        throw new DdnsException('Unknown IP type');
    }

    /**
     * Compares addresses by value, so "2001:DB8::1" equals "2001:db8:0::1".
     */
    public static function equals(string $a, string $b): bool
    {
        $binA = @inet_pton($a);
        $binB = @inet_pton($b);

        if ($binA === false || $binB === false) {
            return $a === $b;
        }

        return $binA === $binB;
    }
}
