<?php

use Ddns\DdnsException;
use Ddns\Mesh\Registry;
use Ddns\Support\Request;

require dirname(__DIR__) . '/src/bootstrap.php';

define('MESH_DIR', BASE_DIR . 'mesh' . DIRECTORY_SEPARATOR);

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
    $peer = readPeer();
    $peers = (new Registry(MESH_DIR . 'nodes' . DIRECTORY_SEPARATOR))->upsert($meshId, $peer);
} catch (DdnsException $e) {
    echo ':log error ("' . $e->getMessage() . '")';
    exit;
}

// 生成脚本
$template = file_get_contents(MESH_DIR . 'template.rsc');

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

/**
 * Validates the submitting node. Every field ends up inside a RouterOS script run by the
 * other peers, so each must be strictly validated to prevent script injection.
 */
function readPeer(): array
{
    $identityName = strtolower((string) filter_input(INPUT_POST, 'identity_name'));
    $listenPort = (string) filter_input(INPUT_POST, 'wg_listen_port');
    $publicKey = (string) filter_input(INPUT_POST, 'wg_public_key');

    if ($identityName === '' || $listenPort === '' || $publicKey === '') {
        throw new DdnsException('Invalid request');
    }

    if (!preg_match('/^[a-z0-9_-]{1,64}$/', $identityName)) {
        throw new DdnsException('Invalid identity name');
    }

    if ($identityName === 'mikrotik' || $identityName === 'routeros') {
        throw new DdnsException("Identity name can't be default name");
    }

    // WireGuard keys are 32 bytes, base64 encoded
    if (!preg_match('#^[A-Za-z0-9+/]{43}=$#', $publicKey)) {
        throw new DdnsException('Invalid public key');
    }

    if (!ctype_digit($listenPort) || (int) $listenPort < 1 || (int) $listenPort > 65535) {
        throw new DdnsException('Invalid listen port');
    }

    return [
        'identity_name' => $identityName,
        'wg_public_key' => $publicKey,
        'remote_ip'     => Request::clientIp(),
        'listen_port'   => $listenPort,
    ];
}
