<?php

use Ddns\DdnsException;
use Ddns\Provider\Provider;
use Ddns\Support\Cache;
use Ddns\Support\Ip;
use Ddns\Support\Request;

require dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$accessId = $accessSecret = '';

try {
    $service      = Request::required('service');
    $accessId     = Request::required('access_id');
    $accessSecret = Request::required('access_secret');
    $domain       = Request::required('domain');

    $ip = Request::input('ip');

    if ($ip === null || $ip === '') {
        $ip = Request::clientIp();
    } elseif (!Ip::isValid($ip)) {
        throw new DdnsException('No valid IP');
    }

    $provider = Provider::create($service, $accessId, $accessSecret, new Cache(CACHE_DIR));

    echo $provider->ddns($domain, $ip);
} catch (Throwable $e) {
    if (Request::input('debug')) {
        echo debugReport($e, [$accessId, $accessSecret]);
    } elseif ($e instanceof DdnsException) {
        logFailure($e->getMessage(), $accessSecret);
        echo $e->getMessage();
    } else {
        logFailure((string) $e, $accessSecret);
        echo 'Internal error';
    }
}

function logFailure(string $error, string $secret): void
{
    $error = redact($error, [$secret]);

    $line = sprintf(
        "[%s] service=%s domain=%s ip=%s error=%s\n",
        date('Y-m-d H:i:s'),
        Request::input('service'),
        Request::input('domain'),
        $_SERVER['REMOTE_ADDR'] ?? '',
        $error,
    );

    @error_log($line, 3, LOG_DIR . 'ddns-error.log');
}

/**
 * Full diagnostic report, only reachable with debug=1. Access keys are redacted.
 *
 * @param string[] $secrets
 */
function debugReport(Throwable $e, array $secrets): string
{
    $out = 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')' . PHP_EOL;
    $out .= 'curl ' . (curl_version()['version'] ?? 'unknown') . ', ' . (curl_version()['ssl_version'] ?? '') . PHP_EOL;

    for ($current = $e; $current !== null; $current = $current->getPrevious()) {
        $out .= PHP_EOL . get_class($current) . ': ' . $current->getMessage() . PHP_EOL;
        $out .= 'at ' . $current->getFile() . ':' . $current->getLine() . PHP_EOL;
        $out .= $current->getTraceAsString() . PHP_EOL;
    }

    return redact($out, $secrets);
}

/**
 * Masks credentials in $text. Very short values are skipped: masking them would
 * garble unrelated text, and real keys are never that short.
 *
 * @param string[] $secrets
 */
function redact(string $text, array $secrets): string
{
    foreach ($secrets as $secret) {
        if (strlen($secret) >= 6) {
            $text = str_replace($secret, '***', $text);
        }
    }

    return $text;
}
