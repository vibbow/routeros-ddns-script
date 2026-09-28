<?php

namespace Ddns\Provider;

use Ddns\Client\TencentClient;
use Ddns\Support\Cache;

/**
 * Tencent Cloud DNSPod.
 */
final class Dnspod extends ZonedProvider
{
    private readonly TencentClient $client;

    public function __construct(string $accessId, string $accessSecret, Cache $cache)
    {
        parent::__construct($accessId, $accessSecret, $cache);
        $this->client = new TencentClient('dnspod', '2021-03-23', $accessId, $accessSecret);
    }

    protected function listZones(): array
    {
        $response = $this->client->call('DescribeDomainList', ['Limit' => 3000]);

        $zones = [];
        foreach ($response['DomainList'] ?? [] as $domain) {
            $zones[$domain['Name']] = $domain['DomainId'];
        }

        return $zones;
    }

    protected function findRecordInZone(Zone $zone, string $domain, string $type): ?Record
    {
        $response = $this->client->call('DescribeRecordList', [
            'Domain'       => $zone->name,
            'DomainId'     => (int) $zone->id,
            'Subdomain'    => $zone->subdomain($domain),
            'RecordType'   => $type,
            'ErrorOnEmpty' => 'no',
        ]);

        $record = $response['RecordList'][0] ?? null;

        if ($record === null) {
            return null;
        }

        return new Record($record['Value'], $record + ['Domain' => $zone->name, 'DomainId' => (int) $zone->id]);
    }

    protected function updateRecord(Record $record, string $ip): void
    {
        $this->client->call('ModifyDynamicDNS', [
            'Domain'       => $record->raw['Domain'],
            'DomainId'     => $record->raw['DomainId'],
            'RecordId'     => $record->raw['RecordId'],
            'SubDomain'    => $record->raw['Name'],
            'RecordLine'   => $record->raw['Line'],
            'RecordLineId' => $record->raw['LineId'],
            'Value'        => $ip,
        ]);
    }
}
