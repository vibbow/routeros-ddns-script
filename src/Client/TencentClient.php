<?php

namespace Ddns\Client;

use Ddns\ApiException;
use Ddns\Support\Http;

/**
 * Minimal Tencent Cloud API 3.0 client (TC3-HMAC-SHA256 signature).
 *
 * @see https://cloud.tencent.com/document/api/1427/56189
 */
final class TencentClient
{
    private const ALGORITHM = 'TC3-HMAC-SHA256';
    private const CONTENT_TYPE = 'application/json; charset=utf-8';

    private readonly string $host;

    public function __construct(
        private readonly string $service,
        private readonly string $version,
        private readonly string $secretId,
        private readonly string $secretKey,
    ) {
        $this->host = "{$service}.tencentcloudapi.com";
    }

    /**
     * Calls an API action and returns the "Response" object.
     */
    public function call(string $action, array $params = []): array
    {
        $payload = json_encode((object) $params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = time();

        $headers = [
            'Authorization'  => $this->authorization($payload, $timestamp),
            'Content-Type'   => self::CONTENT_TYPE,
            'Host'           => $this->host,
            'X-TC-Action'    => $action,
            'X-TC-Timestamp' => (string) $timestamp,
            'X-TC-Version'   => $this->version,
        ];

        [, $data] = Http::json('POST', "https://{$this->host}/", $headers, $payload);
        $response = $data['Response'] ?? [];

        if (isset($response['Error'])) {
            throw new ApiException((string) $response['Error']['Code'], (string) $response['Error']['Message']);
        }

        return $response;
    }

    private function authorization(string $payload, int $timestamp): string
    {
        $date = gmdate('Y-m-d', $timestamp);
        $scope = "{$date}/{$this->service}/tc3_request";
        $signedHeaders = 'content-type;host';

        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            '',
            'content-type:' . self::CONTENT_TYPE . "\nhost:{$this->host}\n",
            $signedHeaders,
            hash('sha256', $payload),
        ]);

        $stringToSign = implode("\n", [self::ALGORITHM, $timestamp, $scope, hash('sha256', $canonicalRequest)]);

        $key = hash_hmac('sha256', $date, 'TC3' . $this->secretKey, true);
        $key = hash_hmac('sha256', $this->service, $key, true);
        $key = hash_hmac('sha256', 'tc3_request', $key, true);
        $signature = hash_hmac('sha256', $stringToSign, $key);

        return self::ALGORITHM
            . " Credential={$this->secretId}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
    }
}
