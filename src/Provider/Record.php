<?php

namespace Ddns\Provider;

final class Record
{
    /**
     * @param array $raw the provider's own record fields, needed to update it
     */
    public function __construct(
        public readonly string $value,
        public readonly array $raw,
    ) {
    }
}
