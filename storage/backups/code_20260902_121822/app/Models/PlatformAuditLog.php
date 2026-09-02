<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    protected $table = 'platform_audit_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'admin_id');
    }

    public static function record(string $section, string $key, string $action, ?array $meta = null): void
    {
        $req = request();
        static::create([
            'admin_id' => $req->user()?->getKey(),
            'section' => $section,
            'setting_key' => $key,
            'action' => $action,
            'ip_address' => $req->ip(),
            'user_agent' => substr((string) $req->userAgent(), 0, 500),
            'meta' => $meta,
        ]);
    }
}
