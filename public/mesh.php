<?php

use Ddns\DdnsException;
use Ddns\Mesh\Peer;
use Ddns\Mesh\Registry;
use Ddns\Paths;

require dirname(__DIR__) . '/src/bootstrap.php';

// 获取 Mesh ID，如果不存在则跳转到主页
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$meshId = preg_match('#^/mesh/([a-z0-9_-]{8,32})$#', (string) $path, $matches) ? $matches[1] : null;

if ($meshId === null) {
    http_response_code(302);
    header('Location: https://www.vsean.net/routeros-wireguard-mesh-sync/');
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $peer = Peer::fromRequest();
    $peers = (new Registry(Paths::MESH . 'nodes/'))->upsert($meshId, $peer);
} catch (DdnsException $e) {
    echo ':log error ("' . $e->getMessage() . '")';
    exit;
}

// 生成脚本
$template = file_get_contents(Paths::MESH . 'template.rsc');

echo '/interface/wireguard/peers' . PHP_EOL . PHP_EOL;

foreach ($peers as $key => $item) {
    if ($item['identity_name'] === $peer['identity_name']) {
        continue;
    }

    echo strtr($template, [
        '$ID$'   => $key + 1,
        '#NAME#' => $item['identity_name'],
        '$PK$'   => $item['wg_public_key'],
        '$EA$'   => $item['remote_ip'],
        '$EP$'   => $item['listen_port'],
    ]);
}
