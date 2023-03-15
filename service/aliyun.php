<?php

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Alidns\Alidns;

class Aliyun
{
    public function __construct($accessID, $accessSecret) {
        AlibabaCloud::accessKeyClient($accessID, $accessSecret)
            ->regionId('cn-hangzhou')
            ->asDefaultClient();
    }

    public function ddns($domain, $accessIP)
    {
        $recordType = getRecordType($accessIP);
        $record = $this->getRecord($domain, $recordType);
        $recordIP = $record->Value;
    
        if ($recordIP === $accessIP) {
            return 'IP not changed';
        }
        else {
            $this->updateRecord($record, $accessIP);
            return "IP update from {$recordIP} to {$accessIP}";
        }
    }

    private function getRecord($domain, $recordType)
    {
        $response = Alidns::v20150109()
            ->DescribeSubDomainRecords()
            ->withSubDomain($domain)
            ->withType($recordType)
            ->request();

        $record = $response->DomainRecords->Record[0] ?? null;

        if (empty($record)) {
            throw new Exception('No available records');
        }

        return $record;
    }

    private function updateRecord($record, $ip)
    {
        Alidns::v20150109()
            ->UpdateDomainRecord()
            ->withRecordId($record->RecordId)
            ->withRR($record->RR)
            ->withType($record->Type)
            ->withValue($ip)
            ->request();
    }
}
