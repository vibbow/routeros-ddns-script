<?php

namespace Ddns\Provider;

use Ddns\Client\AliyunClient;
use Ddns\Support\Cache;
use Ddns\Support\Ip;

/**
 * Alibaba Cloud ESA (边缘安全加速). Only NS-access sites host DNS records.
 */
final class AliEsa extends ZonedProvider
{
    private const PAGE_SIZE = 500;

    private readonly AliyunClient $client;

    public function __construct(string $accessId, string $accessSecret, Cache $cache)
    {
        parent::__construct($accessId, $accessSecret, $cache);
        $this->client = new AliyunClient('esa.cn-hangzhou.aliyuncs.com', '2024-09-10', $accessId, $accessSecret);
    }

    protected function listZones(): array
    {
        $response = $this->client->call('ListSites', [
            'AccessType' => 'NS',
            'PageSize'   => self::PAGE_SIZE,
        ]);

        $zones = [];
        foreach ($response['Sites'] ?? [] as $site) {
            $zones[$site['SiteName']] = $site['SiteId'];
        }

        return $zones;
    }

    protected function findRecordInZone(Zone $zone, string $domain, string $type): ?Record
    {
        $response = $this->client->call('ListRecords', [
            'SiteId'          => $zone->id,
            'RecordName'      => $domain,
            'RecordMatchType' => 'exact',
            'Type'            => 'A/AAAA',
            'PageSize'        => self::PAGE_SIZE,
        ]);

        // ESA stores A and AAAA under one "A/AAAA" type; tell them apart by value.
        foreach ($response['Records'] ?? [] as $record) {
            $value = $record['Data']['Value'] ?? '';
            $sameName = strtolower($record['RecordName']) === $domain;

            if ($sameName && Ip::isValid($value) && Ip::recordType($value) === $type) {
                return new Record($value, $record);
            }
        }

        return null;
    }

    protected function updateRecord(Record $record, string $ip): void
    {
        $this->client->call('UpdateRecord', [
            'RecordId' => $record->raw['RecordId'],
            'Data'     => json_encode(['Value' => $ip]),
        ], 'POST');
    }
}
