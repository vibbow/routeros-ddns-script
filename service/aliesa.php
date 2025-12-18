<?php

use AlibabaCloud\Credentials\Credential;
use AlibabaCloud\Credentials\Credential\Config as CredentialConfig;
use AlibabaCloud\SDK\ESA\V20240910\ESA;
use AlibabaCloud\SDK\ESA\V20240910\Models\GetRecordRequest;
use AlibabaCloud\SDK\ESA\V20240910\Models\GetRecordResponseBody\recordModel;
use AlibabaCloud\SDK\ESA\V20240910\Models\ListRecordsRequest;
use AlibabaCloud\SDK\ESA\V20240910\Models\ListSitesRequest;
use AlibabaCloud\SDK\ESA\V20240910\Models\UpdateRecordRequest;
use Darabonba\OpenApi\Models\Config as OpenApiConfig;

class AliesaService
{
    private ESA $client;

    private ?int $recordID = null;

    public function __construct(string $accessID, string $accessSecret)
    {
        $credConfig = new CredentialConfig([
            'type'            => 'access_key',
            'accessKeyId'     => $accessID,
            'accessKeySecret' => $accessSecret,
        ]);

        $config = new OpenApiConfig([
            'credential' => new Credential($credConfig),
            'endpoint'   => 'esa.cn-hangzhou.aliyuncs.com',
        ]);

        $this->client = new ESA($config);
    }

    public function ddns(string $domain, string $accessIP): string
    {
        $recordType = getIPType($accessIP);
        $this->getId($domain, $recordType);

        $record = $this->getRecord();
        $recordIP = $record->data->value;

        if ($recordIP === $accessIP) {
            return 'IP not changed';
        }

        $this->updateRecord($record, $accessIP);
        return "IP update from {$recordIP} to {$accessIP}";
    }

    private function getRecord(): recordModel
    {
        $req = new GetRecordRequest();
        $req->recordId = $this->recordID;

        try {
            $response = $this->client->GetRecord($req);
            return $response->body->recordModel;
        } catch (Exception $e) {
            throw new Exception('Failed to get record: ' . $e->getMessage());
        }
    }

    private function updateRecord(recordModel $record, string $ip): void
    {
        $req = new UpdateRecordRequest();
        $req->recordId = $record->recordId;
        $req->data = new UpdateRecordRequest\data();
        $req->data->value = $ip;

        try {
            $this->client->UpdateRecord($req);
        } catch (Exception $e) {
            throw new Exception('Failed to update record: ' . $e->getMessage());
        }
    }

    private function getId(string $domain, string $recordType): void
    {
        $cacheFile = CACHE_DIR . md5('aliesa' . $domain . $recordType);

        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            $cache = json_decode($content, true);

            $this->recordID = $cache['record_id'];
            return;
        }

        $req = new ListSitesRequest();
        $response = $this->client->ListSites($req);
        $sites = $response->body->sites;

        $siteID = null;
        $siteName = null;
        $siteAccessType = null;

        foreach ($sites as $eachSite) {
            $thisSiteID = $eachSite->siteId;
            $thisSiteName = $eachSite->siteName;
            $thisSiteAccessType = $eachSite->accessType;

            if (str_ends_with($domain, $thisSiteName)) {
                $siteID = $thisSiteID;
                $siteName = $thisSiteName;
                $siteAccessType = $thisSiteAccessType;
                break;
            }
        }

        if (empty($siteID)) {
            throw new Exception('Site not found');
        }

        if ($siteAccessType !== 'NS') {
            throw new Exception('Site access type is not NS');
        }

        $req = new ListRecordsRequest();
        $req->siteId = $siteID;
        $req->type = 'A/AAAA';
        $response = $this->client->ListRecords($req);
        $records = $response->body->records;

        $recordID = null;

        foreach ($records as $eachRecord) {
            $thisRecordID = $eachRecord->recordId;
            $thisRecordName = $eachRecord->recordName;
            $thisRecordType = getIPType($eachRecord->data->value);

            if ($thisRecordType === $recordType && $thisRecordName === $domain) {
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
            'record_type' => $recordType,
            'record_id'   => $recordID,
        ];

        file_put_contents($cacheFile, json_encode($cacheData));

        $this->recordID = $recordID;
    }
}
