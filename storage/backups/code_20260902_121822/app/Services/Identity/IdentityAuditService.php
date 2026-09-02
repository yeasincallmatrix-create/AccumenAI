<?php

namespace App\Services\Identity;

use App\Models\IdentityAuditLog;
use Illuminate\Support\Facades\Request;

class IdentityAuditService
{
    public static function log(?int $userId, string $event, ?string $identifierType = null, array $meta = []): void
    {
        try {
            IdentityAuditLog::create([
                'user_id' => $userId,
                'event' => $event,
                'identifier_type' => $identifierType,
                'ip_address' => Request::ip(),
                'meta' => $meta,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
