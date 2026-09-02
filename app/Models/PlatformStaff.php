<?php

namespace App\Models;

use App\Models\Concerns\HasUserPreferences;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class PlatformStaff extends Authenticatable implements MustVerifyEmailContract
{
    use HasUserPreferences;
    use MustVerifyEmail;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    protected $table = 'platform_staffs';

    public $timestamps = true;

    /**
     * Fillable - explicitly excludes is_owner/guard escalation vectors.
     * Platform staff must NEVER become super admin.
     */
    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password_hash',
        'preferred_language',
        'preferences',
        'role',
        'status',
        'email_verified_at',
    ];

    protected $hidden = ['password_hash', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'failed_login_count' => 'integer',
        'preferences' => 'array',
    ];

    public const ROLES = ['support', 'finance', 'verification', 'technical', 'content', 'compliance'];

    public const ROLE_PERMISSIONS = [
        'support' => ['institutes.view', 'users.view', 'support.manage', 'audit.view'],
        'finance' => ['finance.view', 'finance.manage', 'reports.view', 'reports.financial.view'],
        'verification' => ['institutes.view', 'institutes.verify', 'documents.view', 'documents.manage'],
        'technical' => ['audit.view', 'technical.manage', 'settings.view', 'system.view'],
        'content' => ['courses.view', 'courses.manage', 'curriculum.view', 'curriculum.manage'],
        'compliance' => ['institutes.view', 'documents.view', 'audit.view', 'compliance.manage'],
    ];

    protected static function booted(): void
    {
        static::saving(function (self $staff) {
            if ($staff->isDirty('email') && $staff->email !== null) {
                $normalized = \App\Support\EmailNormalizer::normalize($staff->email);
                if ($normalized !== null) {
                    $staff->email = $normalized;
                }
            }
            if ($staff->isDirty('role') && ! in_array($staff->role, self::ROLES, true)) {
                throw new \InvalidArgumentException('Invalid platform staff role: '.$staff->role);
            }
            // Block any attempt to inject is_owner via mass assignment workaround
            if (isset($staff->attributes['is_owner'])) {
                unset($staff->attributes['is_owner']);
            }
        });

        static::creating(function (self $staff) {
            // Ensure platform staff never gets singleton_guard or is_owner
            if (isset($staff->attributes['singleton_guard'])) {
                unset($staff->attributes['singleton_guard']);
            }
            if (isset($staff->attributes['is_owner'])) {
                unset($staff->attributes['is_owner']);
            }
        });
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function setPasswordHashAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['password_hash'] = $value;
            return;
        }
        $this->attributes['password_hash'] = \App\Support\PasswordHash::looksValid((string) $value)
            ? (string) $value
            : \Illuminate\Support\Facades\Hash::make((string) $value);
    }

    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['password_hash'] ?? '');
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function sendEmailVerificationNotification(): void
    {
        if (app()->environment('testing')) {
            $this->notify(new \Illuminate\Auth\Notifications\VerifyEmail);
        } else {
            $this->notify(new \App\Notifications\QueuedVerifyEmail);
        }
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'platform_staff_permissions', 'platform_staff_id', 'permission_id');
    }

    public function hasPermission(string $permission): bool
    {
        // Direct permission check + role-based defaults
        if ($this->permissions->contains('slug', $permission)) {
            return true;
        }
        $roleDefaults = self::ROLE_PERMISSIONS[$this->role] ?? [];
        return in_array($permission, $roleDefaults, true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if ($this->hasPermission($p)) return true;
        }
        return false;
    }

    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: ($this->email ?? '');
    }
}
