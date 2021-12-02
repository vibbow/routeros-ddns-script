<?php

define('DS', DIRECTORY_SEPARATOR);
define('BASE_DIR', __DIR__ . DS);
define('CACHE_DIR', BASE_DIR . 'cache' . DS);

require_once(BASE_DIR . 'function.php');
require_once(BASE_DIR . 'vendor' . DS . 'autoload.php');
require_once(BASE_DIR . 'service' . DS . 'aliyun.php');
require_once(BASE_DIR . 'service' . DS . 'dnspod.php');

try {
    $serviceType = filterInputPostGet('service');
    $accessID = filterInputPostGet('access_id');
    $accessSecret = filterInputPostGet('access_secret');
    $domain = filterInputPostGet('domain');

    if (empty($serviceType)) {
        throw new Exception('service type is empty');
    }

    if (empty($accessID)) {
        throw new Exception('access id is empty');
    }

    if (empty($accessSecret)) {
        throw new Exception('access secret is empty');
    }

    if (empty($domain)) {
        throw new Exception('domain is empty');
    }

    switch ($serviceType) {
        case 'aliyun':
            $ddnsService = new Aliyun($accessID, $accessSecret);
            echo $ddnsService->ddns($domain);
            break;
        case 'dnspod':
            $ddnsService = new Dnspod($accessID, $accessSecret);
            echo $ddnsService->ddns($domain);
            break;
        default:
            throw new Exception('Unknown service type');
    }
}
catch (Exception $e) {
    echo $e->getMessage();
}
