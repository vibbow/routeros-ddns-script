<?php

namespace Ddns\Support;

use Ddns\DdnsException;

final class Http
{
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT = 15;

    /**
     * Sends a request and decodes the JSON response body.
     *
     * @param array<string, string> $headers
     * @return array{0: int, 1: array} [HTTP status, decoded body]
     */
    public static function json(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        // Keep curl from adding "Expect: 100-continue" on POST
        $lines[] = 'Expect:';

        $options = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_USERAGENT      => 'ddns.vsean.net',
        ];

        if ($body !== null || $method === 'POST') {
            $options[CURLOPT_POSTFIELDS] = $body ?? '';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($response === false) {
            throw new DdnsException("HTTP request failed: {$error}");
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new DdnsException("Unexpected response (HTTP {$status})");
        }

        return [$status, $data];
    }
}
