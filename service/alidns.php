<?php

use AlibabaCloud\Credentials\Credential;
use AlibabaCloud\Credentials\Credential\Config as CredentialConfig;
use AlibabaCloud\SDK\Alidns\V20150109\Alidns;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DescribeSubDomainRecordsRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DescribeSubDomainRecordsResponseBody\domainRecords\record as DomainRecord;
use AlibabaCloud\SDK\Alidns\V20150109\Models\UpdateDomainRecordRequest;
use Darabonba\OpenApi\Models\Config as OpenApiConfig;

class AlidnsService
{
    private Alidns $client;

    public function __construct(string $accessID, string $accessSecret)
    {
        $credConfig = new CredentialConfig([
            'type'            => 'access_key',
            'accessKeyId'     => $accessID,
            'accessKeySecret' => $accessSecret,
        ]);

        $config = new OpenApiConfig([
            'credential' => new Credential($credConfig),
            'endpoint'   => 'alidns.aliyuncs.com',
        ]);

        $this->client = new Alidns($config);
    }

    public function ddns(string $domain, string $accessIP): string
    {
        $recordType = getIPType($accessIP);
        $record = $this->getRecord($domain, $recordType);
        $recordIP = $record->value;

        if ($recordIP === $accessIP) {
            return 'IP not changed';
        }

        $this->updateRecord($record, $accessIP);
        return "IP update from {$recordIP} to {$accessIP}";
    }

    private function getRecord(string $domain, string $recordType): DomainRecord
    {
        $req = new DescribeSubDomainRecordsRequest();
        $req->subDomain = $domain;
        $req->type = $recordType;

        try {
            $response = $this->client->DescribeSubDomainRecords($req);
            $records = $response->body->domainRecords->record;

            if (empty($records)) {
                throw new Exception('Record not found');
            }

            return $records[0];
        } catch (Exception $e) {
            throw new Exception('Failed to get record: ' . $e->getMessage());
        }
    }

    private function updateRecord(DomainRecord $record, string $ip): void
    {
        $req = new UpdateDomainRecordRequest();
        $req->recordId = $record->recordId;
        $req->RR = $record->RR;
        $req->type = $record->type;
        $req->value = $ip;

        try {
            $this->client->UpdateDomainRecord($req);
        } catch (Exception $e) {
            throw new Exception('Failed to update record: ' . $e->getMessage());
        }
    }
}
