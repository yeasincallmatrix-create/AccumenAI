<?php

namespace App\Models\LabIntegration;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LabDeviceCredential extends Model
{
    use TenantScoped;

    protected $table = 'lab_device_credentials';
    protected $fillable = [
        'institute_id', 'analyzer_id', 'token_hash', 'token_prefix',
        'name', 'abilities', 'rotated_at', 'revoked_at', 'expires_at',
        'last_used_at', 'last_ip', 'notes',
    ];
    protected $casts = [
        'abilities' => 'array',
        'rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function institute() { return $this->belongsTo(\App\Models\Institute::class); }
    public function analyzer() { return $this->belongsTo(LabAnalyzer::class, 'analyzer_id'); }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public static function generateToken(): array
    {
        $plain = Str::random(48);
        return [
            'plain' => $plain,
            'hash' => hash('sha256', $plain),
            'prefix' => substr($plain, 0, 12),
        ];
    }

    public static function findByToken(string $plainToken): ?self
    {
        return self::where('token_prefix', substr($plainToken, 0, 12))
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();
    }
}
