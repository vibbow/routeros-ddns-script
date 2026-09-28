<?php

namespace Ddns;

/**
 * The DNS provider's API rejected a request.
 */
class ApiException extends DdnsException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct("{$errorCode}: {$message}");
    }
}
