<?php

use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Dnspod\V20210323\DnspodClient;
use TencentCloud\Dnspod\V20210323\Models\DescribeRecordRequest;
use TencentCloud\Dnspod\V20210323\Models\ModifyDynamicDNSRequest;
use TencentCloud\Dnspod\V20210323\Models\DescribeDomainListRequest;
use TencentCloud\Dnspod\V20210323\Models\DescribeRecordListRequest;

class Dnspod
{
    private $client;
    private $domainID;
    private $recordID;

    public function __construct($accessID, $accessSecret) {
        $domainID = filterInputPostGet('domain_id');
        $recordID = filterInputPostGet('record_id');

        if ( ! empty($domainID) && ! empty($recordID)) {
            $this->domainID = intval($domainID);
            $this->recordID = intval($recordID);
        }

        $cred = new Credential($accessID, $accessSecret);
        $this->client = new DnspodClient($cred, "");
    }

    public function ddns($domain)
    {
        if (empty($this->domainID) || empty($this->recordID)) {
            $this->getId($domain);
        }

        $record = $this->getRecord();
        $recordIP = $record->Value;
        $accessIP = get_ip();
    
        if ($recordIP === $accessIP) {
            return 'IP not changed';
        }
        else {
            $this->updateRecord($record, $accessIP);
            return "IP update from {$recordIP} to {$accessIP}";
        }
    }

    private function getRecord()
    {
        $request = new DescribeRecordRequest();
        $request->setDomain('example.com');
        $request->setDomainId($this->domainID);
        $request->setRecordId($this->recordID);
    
        $response = $this->client->DescribeRecord($request);
        $recordInfo = $response->getRecordInfo();
    
        return $recordInfo;
    }

    private function updateRecord($record, $ip)
    {
        $request = New ModifyDynamicDNSRequest();
        $request->setDomain('example.com');
        $request->setDomainId($this->domainID);
        $request->setRecordId($this->recordID);
        $request->setValue($ip);
        $request->setSubDomain($record->SubDomain);
        $request->setRecordLine($record->RecordLine);
        $this->client->ModifyDynamicDNS($request);
    }

    private function getId($domain)
    {
        $domainID = null;
        $recordID = null;

        if (file_exists(CACHE_DIR . md5($domain))) {
            $content = file_get_contents(CACHE_DIR . md5($domain));
            $record = json_decode($content, true);

            $this->domainID = $record['domain_id'];
            $this->recordID = $record['record_id'];
            return;
        }

        $request = new DescribeDomainListRequest();
        $response = $this->client->DescribeDomainList($request);
        $domainList = $response->getDomainList();

        foreach ($domainList as $eachDomain) {
            $thisDomainName = $eachDomain->Name;
            $thisdomainID = $eachDomain->DomainId;
    
            if (str_ends_with($domain, $thisDomainName)) {
                $domainID = $thisdomainID;
                $domainName = $thisDomainName;
                break;
            }
        }
    
        if (empty($domainID)) {
            throw new Exception("Domain not found");
        }

        $request = new DescribeRecordListRequest();
        $request->setDomain('example.com');
        $request->setDomainId($domainID);
        $response = $this->client->DescribeRecordList($request);
        $recordList = $response->getRecordList();

        foreach ($recordList as $eachRecord) {
            $thisRecordID = $eachRecord->RecordId;
            $thisRecordName = trim($eachRecord->Name, '.');
            $thisRecordType = $eachRecord->Type;
            $thisRecordDomain = $thisRecordName . '.' . $domainName;
            
            if ($thisRecordType === 'A' && $thisRecordDomain === $domain) {
                $recordID = $thisRecordID;
                break;
            }
        }

        if (empty($recordID)) {
            throw new Exception("Record not found");
        }

        $cacheData = [
            'domain'    => $domain,
            'domain_id' => $domainID,
            'record_id' => $recordID
        ];

        file_put_contents(CACHE_DIR . md5($domain), json_encode($cacheData));

        $this->domainID = $domainID;
        $this->recordID = $recordID;
    }
}
