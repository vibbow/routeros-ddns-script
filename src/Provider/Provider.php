<?php

namespace Ddns\Provider;

use Ddns\DdnsException;
use Ddns\Support\Cache;
use Ddns\Support\Ip;

abstract class Provider
{
    public function __construct(
        protected readonly string $accessId,
        protected readonly string $accessSecret,
        protected readonly Cache $cache,
    ) {
    }

    public static function create(string $service, string $accessId, string $accessSecret, Cache $cache): self
    {
        return match ($service) {
            'aliyun', 'alidns' => new Alidns($accessId, $accessSecret, $cache),
            'aliesa'           => new AliEsa($accessId, $accessSecret, $cache),
            'dnspod'           => new Dnspod($accessId, $accessSecret, $cache),
            default            => throw new DdnsException('Unknown service type'),
        };
    }

    /**
     * Points the domain's A/AAAA record at $ip, unless it already does.
     */
    public function ddns(string $domain, string $ip): string
    {
        $domain = strtolower(rtrim($domain, '.'));
        $record = $this->findRecord($domain, Ip::recordType($ip));

        if (Ip::equals($record->value, $ip)) {
            return 'IP not changed';
        }

        $this->updateRecord($record, $ip);

        return "IP update from {$record->value} to {$ip}";
    }

    /**
     * @param string $type "A" or "AAAA"
     */
    abstract protected function findRecord(string $domain, string $type): Record;

    abstract protected function updateRecord(Record $record, string $ip): void;
}
