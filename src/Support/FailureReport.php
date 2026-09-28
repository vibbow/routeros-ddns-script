<?php

namespace Ddns\Support;

use Ddns\Paths;
use Throwable;

/**
 * Logs and renders failed DDNS requests. Access keys are always redacted.
 */
final class FailureReport
{
    /**
     * @param string[] $secrets values to redact
     */
    public function __construct(private readonly array $secrets)
    {
    }

    /**
     * Appends one line to logs/ddns-error.log.
     */
    public function log(string $error): void
    {
        $line = sprintf(
            "[%s] service=%s domain=%s ip=%s error=%s\n",
            date('Y-m-d H:i:s'),
            Request::input('service'),
            Request::input('domain'),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $this->redact($error),
        );

        @error_log($line, 3, Paths::LOGS . 'ddns-error.log');
    }

    /**
     * Full diagnostic report, shown only with debug=1.
     */
    public function debug(Throwable $e): string
    {
        $curl = curl_version();

        $out = 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')' . PHP_EOL;
        $out .= 'curl ' . ($curl['version'] ?? 'unknown') . ', ' . ($curl['ssl_version'] ?? '') . PHP_EOL;

        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $out .= PHP_EOL . get_class($current) . ': ' . $current->getMessage() . PHP_EOL;
            $out .= 'at ' . $current->getFile() . ':' . $current->getLine() . PHP_EOL;
            $out .= $current->getTraceAsString() . PHP_EOL;
        }

        return $this->redact($out);
    }

    /**
     * Very short values are skipped: masking them would garble unrelated text,
     * and real keys are never that short.
     */
    private function redact(string $text): string
    {
        foreach ($this->secrets as $secret) {
            if (strlen($secret) >= 6) {
                $text = str_replace($secret, '***', $text);
            }
        }

        return $text;
    }
}
