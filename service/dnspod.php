<?php

use TencentCloud\Common\Credential;
use TencentCloud\Dnspod\V20210323\DnspodClient;
use TencentCloud\Dnspod\V20210323\Models\DescribeDomainListRequest;
use TencentCloud\Dnspod\V20210323\Models\DescribeRecordListRequest;
use TencentCloud\Dnspod\V20210323\Models\DescribeRecordRequest;
use TencentCloud\Dnspod\V20210323\Models\ModifyDynamicDNSRequest;
use TencentCloud\Dnspod\V20210323\Models\RecordInfo;

class DnspodService
{
    private DnspodClient $client;
    private ?int $domainID = null;
    private ?int $recordID = null;

    public function __construct(string $accessID, string $accessSecret)
    {
        $cred = new Credential($accessID, $accessSecret);
        $this->client = new DnspodClient($cred, '');
    }

    public function ddns(string $domain, string $accessIP): string
    {
        $recordType = getIPType($accessIP);
        $this->getId($domain, $recordType);

        $record = $this->getRecord();
        $recordIP = $record->Value;

        if ($recordIP === $accessIP) {
            return 'IP not changed';
        }

        $this->updateRecord($record, $accessIP);
        return "IP update from {$recordIP} to {$accessIP}";
    }

    private function getRecord(): RecordInfo
    {
        $request = new DescribeRecordRequest();
        $request->Domain = '';
        $request->DomainId = $this->domainID;
        $request->RecordId = $this->recordID;

        try {
            $response = $this->client->DescribeRecord($request);
            return $response->getRecordInfo();
        } catch (Exception $e) {
            throw new Exception('Failed to get record: ' . $e->getMessage());
        }
    }

    private function updateRecord(RecordInfo $record, string $ip): void
    {
        $request = new ModifyDynamicDNSRequest();
        $request->Domain = '';
        $request->DomainId = $this->domainID;
        $request->RecordId = $this->recordID;
        $request->Value = $ip;
        $request->SubDomain = $record->SubDomain;
        $request->RecordLine = $record->RecordLine;

        try {
            $this->client->ModifyDynamicDNS($request);
        } catch (Exception $e) {
            throw new Exception('Failed to update record: ' . $e->getMessage());
        }
    }

    private function getId(string $domain, string $recordType): void
    {
        $cacheFile = CACHE_DIR . md5('dnspod' . $domain . $recordType);

        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            $cache = json_decode($content, true);

            $this->domainID = $cache['domain_id'];
            $this->recordID = $cache['record_id'];
            return;
        }

        $request = new DescribeDomainListRequest();
        $response = $this->client->DescribeDomainList($request);
        $domainList = $response->getDomainList();

        $domainID = null;
        $domainName = null;

        foreach ($domainList as $eachDomain) {
            $thisDomainName = $eachDomain->Name;
            $thisDomainID = $eachDomain->DomainId;

            if (str_ends_with($domain, $thisDomainName)) {
                $domainID = $thisDomainID;
                $domainName = $thisDomainName;
                break;
            }
        }

        if (empty($domainID)) {
            throw new Exception('Domain not found');
        }

        $request = new DescribeRecordListRequest();
        $request->Domain = $domainName;
        $request->DomainId = $domainID;

        $response = $this->client->DescribeRecordList($request);
        $recordList = $response->getRecordList();

        $recordID = null;

        foreach ($recordList as $eachRecord) {
            $thisRecordID = $eachRecord->RecordId;
            $thisRecordName = trim($eachRecord->Name, '.');
            $thisRecordType = $eachRecord->Type;
            $thisRecordDomain = $thisRecordName . '.' . $domainName;

            if ($thisRecordType === $recordType && $thisRecordDomain === $domain) {
                $recordID = $thisRecordID;
                break;
            }
        }

        if (empty($recordID)) {
            throw new Exception('Record not found');
        }

        // 确保缓存目录存在
        if (!is_dir(CACHE_DIR)) {
            mkdir(CACHE_DIR, 0755, true);
        }

        $cacheData = [
            'domain'      => $domain,
            'domain_id'   => $domainID,
            'record_type' => $recordType,
            'record_id'   => $recordID,
        ];

        file_put_contents($cacheFile, json_encode($cacheData));

        $this->domainID = $domainID;
        $this->recordID = $recordID;
    }
}
