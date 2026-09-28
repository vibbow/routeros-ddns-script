<?php

namespace Ddns\Provider;

use Ddns\Client\AliyunClient;
use Ddns\DdnsException;
use Ddns\Support\Cache;

/**
 * Alibaba Cloud DNS (云解析 DNS).
 */
final class Alidns extends Provider
{
    private readonly AliyunClient $client;

    public function __construct(string $accessId, string $accessSecret, Cache $cache)
    {
        parent::__construct($accessId, $accessSecret, $cache);
        $this->client = new AliyunClient('alidns.aliyuncs.com', '2015-01-09', $accessId, $accessSecret);
    }

    protected function findRecord(string $domain, string $type): Record
    {
        $response = $this->client->call('DescribeSubDomainRecords', [
            'SubDomain' => $domain,
            'Type'      => $type,
        ]);

        $record = $response['DomainRecords']['Record'][0] ?? null;

        if ($record === null) {
            throw new DdnsException('Record not found');
        }

        return new Record($record['Value'], $record);
    }

    protected function updateRecord(Record $record, string $ip): void
    {
        $this->client->call('UpdateDomainRecord', [
            'RecordId' => $record->raw['RecordId'],
            'RR'       => $record->raw['RR'],
            'Type'     => $record->raw['Type'],
            'Value'    => $ip,
        ], 'POST');
    }
}
