<?php

namespace Ddns\Client;

use Ddns\ApiException;
use Ddns\Support\Http;

/**
 * Minimal Alibaba Cloud OpenAPI client (RPC style, ACS3-HMAC-SHA256 signature).
 *
 * @see https://help.aliyun.com/zh/sdk/product-overview/v3-request-structure-and-signature
 */
final class AliyunClient
{
    private const ALGORITHM = 'ACS3-HMAC-SHA256';

    public function __construct(
        private readonly string $endpoint,
        private readonly string $version,
        private readonly string $accessKeyId,
        private readonly string $accessKeySecret,
    ) {
    }

    /**
     * Calls an API action; all parameters travel in the query string.
     *
     * @param array<string, scalar|null> $params
     */
    public function call(string $action, array $params = [], string $method = 'GET'): array
    {
        $query = self::canonicalQuery($params);

        $headers = [
            'host'                  => $this->endpoint,
            'x-acs-action'          => $action,
            'x-acs-content-sha256'  => hash('sha256', ''),
            'x-acs-date'            => gmdate('Y-m-d\TH:i:s\Z'),
            'x-acs-signature-nonce' => bin2hex(random_bytes(16)),
            'x-acs-version'         => $this->version,
        ];
        $headers['Authorization'] = $this->authorization($method, $query, $headers);

        $url = "https://{$this->endpoint}/" . ($query === '' ? '' : "?{$query}");
        [$status, $data] = Http::json($method, $url, $headers);

        if ($status >= 400 || isset($data['Code'])) {
            throw new ApiException((string) ($data['Code'] ?? "HTTP {$status}"), (string) ($data['Message'] ?? ''));
        }

        return $data;
    }

    /**
     * @param array<string, string> $headers lower-case names, already sorted
     */
    private function authorization(string $method, string $query, array $headers): string
    {
        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= "{$name}:" . trim($value) . "\n";
        }
        $signedHeaders = implode(';', array_keys($headers));

        $canonicalRequest = implode("\n", [
            $method,
            '/',
            $query,
            $canonicalHeaders,
            $signedHeaders,
            $headers['x-acs-content-sha256'],
        ]);

        $stringToSign = self::ALGORITHM . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->accessKeySecret);

        return self::ALGORITHM
            . " Credential={$this->accessKeyId},SignedHeaders={$signedHeaders},Signature={$signature}";
    }

    private static function canonicalQuery(array $params): string
    {
        $params = array_filter($params, static fn ($v) => $v !== null);
        ksort($params, SORT_STRING);

        $pairs = [];
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return implode('&', $pairs);
    }
}
