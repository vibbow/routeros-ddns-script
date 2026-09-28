<?php

namespace Ddns\Mesh;

use Ddns\DdnsException;

/**
 * The peers of one mesh, stored as a JSON file per mesh id.
 */
final class Registry
{
    public function __construct(private readonly string $dir)
    {
    }

    /**
     * Inserts or updates $peer (keyed by identity_name) and returns every peer of the mesh.
     * The read-modify-write runs under an exclusive lock so concurrent check-ins don't lose peers.
     *
     * @param array{identity_name: string, wg_public_key: string, remote_ip: string, listen_port: string} $peer
     * @return list<array> peers in registration order
     */
    public function upsert(string $meshId, array $peer): array
    {
        $fp = @fopen($this->dir . $meshId . '.json', 'c+');

        if ($fp === false) {
            throw new DdnsException('Cannot open mesh storage');
        }

        try {
            flock($fp, LOCK_EX);

            $original = stream_get_contents($fp);
            $peers = json_decode($original, true) ?: [];

            $index = array_search($peer['identity_name'], array_column($peers, 'identity_name'), true);

            if ($index === false) {
                $peers[] = $peer;
            } else {
                $peers[$index] = $peer;
            }

            $content = json_encode($peers, JSON_PRETTY_PRINT);

            if ($content !== $original) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, $content);
                fflush($fp);
            }

            return $peers;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
