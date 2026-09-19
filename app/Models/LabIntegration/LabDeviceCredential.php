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
        'previous_token_hash', 'previous_token_prefix', 'previous_token_expires_at',
        'name', 'abilities', 'rotated_at', 'revoked_at', 'expires_at',
        'last_used_at', 'last_ip', 'notes',
    ];
    protected $casts = [
        'abilities' => 'array',
        'rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'previous_token_expires_at' => 'datetime',
    ];

    public const GRACE_PERIOD_HOURS = 24;

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

    /**
     * Returns true if the previous token (during rotation grace) is still valid.
     */
    public function previousTokenIsValid(): bool
    {
        return $this->previous_token_hash !== null
            && $this->previous_token_expires_at !== null
            && $this->previous_token_expires_at->isFuture();
    }

    /**
     * Find a credential matching the given plaintext token,
     * checking current + previous (during grace).
     */
    public static function findByTokenWithGrace(string $plainToken): ?self
    {
        if (strlen($plainToken) < 12) {
            return null;
        }

        $hash = hash('sha256', $plainToken);
        $prefix = substr($plainToken, 0, 12);

        // Try current
        $current = self::where('token_prefix', $prefix)
            ->where('token_hash', $hash)
            ->first();
        if ($current) {
            return $current;
        }

        // Try previous (grace)
        return self::where('previous_token_prefix', $prefix)
            ->where('previous_token_hash', $hash)
            ->where('previous_token_expires_at', '>', now())
            ->first();
    }
}
