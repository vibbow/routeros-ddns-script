<?php

namespace Ddns\Provider;

final class Zone
{
    public function __construct(
        public readonly string $name,
        public readonly int|string $id,
    ) {
    }

    /**
     * Picks the most specific zone containing $domain.
     *
     * @param array<string, int|string> $zones zone name => zone id
     */
    public static function match(string $domain, array $zones): ?self
    {
        $best = null;

        foreach ($zones as $name => $id) {
            $name = strtolower((string) $name);

            $contains = $domain === $name || str_ends_with($domain, ".{$name}");

            if ($contains && ($best === null || strlen($name) > strlen($best->name))) {
                $best = new self($name, $id);
            }
        }

        return $best;
    }

    /**
     * The record's host part within this zone, "@" for the apex.
     */
    public function subdomain(string $domain): string
    {
        return $domain === $this->name ? '@' : substr($domain, 0, -strlen($this->name) - 1);
    }
}
