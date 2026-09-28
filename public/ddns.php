<?php

use Ddns\DdnsException;
use Ddns\Paths;
use Ddns\Provider\Provider;
use Ddns\Support\Cache;
use Ddns\Support\FailureReport;
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

    $provider = Provider::create($service, $accessId, $accessSecret, new Cache(Paths::CACHE));

    echo $provider->ddns($domain, $ip);
} catch (Throwable $e) {
    $report = new FailureReport([$accessId, $accessSecret]);

    if (Request::input('debug')) {
        echo $report->debug($e);
    } elseif ($e instanceof DdnsException) {
        $report->log($e->getMessage());
        echo $e->getMessage();
    } else {
        $report->log((string) $e);
        echo 'Internal error';
    }
}
