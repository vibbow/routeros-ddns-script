<?php

namespace Ddns;

/**
 * Writable and data directories, all outside the public web root.
 */
final class Paths
{
    public const ROOT  = __DIR__ . '/../';
    public const CACHE = self::ROOT . 'cache/';
    public const LOGS  = self::ROOT . 'logs/';
    public const MESH  = self::ROOT . 'mesh/';
}
