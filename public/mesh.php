<?php

use Ddns\DdnsException;
use Ddns\Mesh\Peer;
use Ddns\Mesh\Registry;
use Ddns\Paths;

require dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// RouterOS 的 fetch 遇到非 2xx 状态码时读不到响应内容，所以错误也以 200 返回
try {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

    if (!preg_match('#^/mesh/([a-z0-9_-]{8,32})$#', (string) $path, $matches)) {
        throw new DdnsException('Invalid mesh ID');
    }

    $meshId = $matches[1];
    $peer = Peer::fromRequest();
    $peers = (new Registry(Paths::MESH . 'nodes/'))->upsert($meshId, $peer);
} catch (DdnsException $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// 返回除自己以外的节点，由路由器上的脚本自行更新 WireGuard peer
$result = [];

foreach ($peers as $item) {
    if ($item['identity_name'] === $peer['identity_name']) {
        continue;
    }

    $result[] = [
        'name'             => $item['identity_name'],
        'public_key'       => $item['wg_public_key'],
        'endpoint_address' => $item['remote_ip'],
        'endpoint_port'    => (int) $item['listen_port'],
    ];
}

echo json_encode(['peers' => $result], JSON_UNESCAPED_SLASHES);
