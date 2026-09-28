<?php

namespace Ddns\Mesh;

use Ddns\DdnsException;
use Ddns\Support\Request;

final class Peer
{
    /**
     * Validates the submitting node. Every field ends up inside a RouterOS script run by the
     * other peers, so each must be strictly validated to prevent script injection.
     *
     * @return array{identity_name: string, wg_public_key: string, remote_ip: string, listen_port: string}
     */
    public static function fromRequest(): array
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
}
