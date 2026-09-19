<?php

namespace App\Services\LabIntegration;

class MessageHasher
{
    /**
     * Deterministic hash for idempotency.
     * Normalizes whitespace and line endings so that a re-transmitted
     * message with different CRLF/LF still dedupes correctly.
     */
    public static function hash(string $rawPayload, ?int $analyzerId = null): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($rawPayload));
        $normalized = preg_replace('/[ \t]+/', ' ', $normalized);

        return hash('sha256', ($analyzerId ?? 0).'|'.$normalized);
    }
}
