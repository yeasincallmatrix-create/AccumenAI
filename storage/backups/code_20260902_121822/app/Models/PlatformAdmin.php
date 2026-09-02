<?php

namespace App\Models;

use App\Models\Concerns\HasUserPreferences;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class PlatformAdmin extends Authenticatable implements MustVerifyEmailContract
{
    use HasUserPreferences;
    use MustVerifyEmail;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $table = 'platform_admins';

    public $timestamps = true;

    /**
     * Mass assignment protection: privileged ownership fields must never be set via request.
     * Only allow safe fields via fillable; is_owner/singleton_guard are guarded.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'avatar',
        'password_hash',
        'preferred_language',
        'preferences',
        'theme',
        'email_verified_at',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'preferred_2fa_method',
        'sms_2fa_enabled',
        'email_2fa_enabled',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = ['password_hash', 'two_factor_secret'];

    protected $casts = [
        'is_owner' => 'boolean',
        'singleton_guard' => 'integer',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'locked_until' => 'datetime',
        'failed_login_count' => 'integer',
        'preferences' => 'array',
    ];

    protected static function booted(): void
    {
        // Singleton enforcement: exactly 1 super admin
        static::creating(function (self $admin) {
            // Block any attempt to create second super admin
            if (static::query()->exists()) {
                try {
                    \App\Models\PlatformAuditLog::record('platform_admin', 'singleton', 'blocked_second_creation_attempt', [
                        'attempted_email' => $admin->email ?? null,
                    ]);
                } catch (\Throwable $e) {}
                throw new \App\Exceptions\SingleSuperAdminViolationException(
                    'A Platform Super Admin already exists. Additional Super Admin accounts cannot be created.'
                );
            }
            // Force singleton_guard=1 and is_owner=1 for the sole row
            $admin->singleton_guard = 1;
            $admin->is_owner = true;
            if ($admin->isDirty('email') && $admin->email !== null) {
                $normalized = \App\Support\EmailNormalizer::normalize($admin->email);
                if ($normalized !== null) {
                    $admin->email = $normalized;
                }
            }
        });

        static::saving(function (self $admin) {
            if ($admin->isDirty('email') && $admin->email !== null) {
                $normalized = \App\Support\EmailNormalizer::normalize($admin->email);
                if ($normalized !== null) {
                    $admin->email = $normalized;
                }
            }
            // Immutability: singleton super admin (id=1) identity must not change
            if ($admin->exists && (int) $admin->getOriginal('id') === 1) {
                if ($admin->isDirty('email') && strtolower(trim((string) $admin->email)) !== strtolower(trim((string) $admin->getOriginal('email')))) {
                    try {
                        \App\Models\PlatformAuditLog::record('platform_admin', 'email', 'blocked_identity_change', [
                            'original' => $admin->getOriginal('email'),
                            'attempted' => $admin->email,
                        ]);
                    } catch (\Throwable $e) {}
                    throw new \App\Exceptions\SingleSuperAdminViolationException(
                        'Platform Super Admin identity is immutable and cannot be replaced.'
                    );
                }
                if ($admin->isDirty('is_owner') && ! $admin->is_owner) {
                    try {
                        \App\Models\PlatformAuditLog::record('platform_admin', 'is_owner', 'blocked_demotion_attempt');
                    } catch (\Throwable $e) {}
                    throw new \App\Exceptions\SingleSuperAdminViolationException(
                        'Platform Super Admin ownership cannot be demoted.'
                    );
                }
                if ($admin->isDirty('singleton_guard') && (int) $admin->singleton_guard !== 1) {
                    $admin->singleton_guard = 1;
                }
                // Prevent id change
                if ($admin->isDirty('id')) {
                    throw new \App\Exceptions\SingleSuperAdminViolationException('Platform Super Admin ID is immutable.');
                }
            }
            // Any existing row: forbid demoting is_owner or changing singleton_guard
            if ($admin->exists && $admin->isDirty('is_owner') && (int) $admin->getOriginal('is_owner') === 1 && ! $admin->is_owner) {
                throw new \App\Exceptions\SingleSuperAdminViolationException('Platform ownership cannot be transferred or demoted.');
            }
            if ($admin->isDirty('singleton_guard') && (int) $admin->singleton_guard !== 1) {
                $admin->singleton_guard = 1;
            }
        });

        static::deleting(function (self $admin) {
            // Absolute immutability: never allow delete of sole super admin
            try {
                \App\Models\PlatformAuditLog::record('platform_admin', 'delete', 'blocked_delete_attempt', [
                    'id' => $admin->getKey(),
                    'email' => $admin->email,
                ]);
            } catch (\Throwable $e) {}
            throw new \App\Exceptions\SingleSuperAdminViolationException(
                'Platform Super Admin cannot be deleted. The system must always have exactly one Super Admin.'
            );
        });
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Queued verification dispatch — fixes E12.4 timeout forensic.
     * Testing keeps sync VerifyEmail for Notification::fake() assertions.
     */
    public function sendEmailVerificationNotification(): void
    {
        if (app()->environment('testing')) {
            $this->notify(new \Illuminate\Auth\Notifications\VerifyEmail);
        } else {
            $this->notify(new \App\Notifications\QueuedVerifyEmail);
        }
    }

    /**
     * Forever-idempotent password setter — accepts plain or already-hashed.
     */
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

    /**
     * Return the actual password column (platform_admins.password_hash / institute_users.password_hash).
     */
    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['password_hash'] ?? '');
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Test helper: reuse existing singleton super admin for legacy tests.
     * In testing, legacy tests call PlatformAdmin::create inside DatabaseTransactions.
     * This would violate singleton invariant. Instead reuse existing id=1 and optionally
     * update its password_hash to match the test's expected password.
     * Production code must never use this - it is strictly for test isolation.
     * SAFETY: This method is strictly test-only and must never overwrite the real
     * production Super Admin credential (platform_admins.id=1). It now verifies
     * both APP_ENV=testing and database=monetix_test before mutating any row.
     */
    public static function firstOrReuseForTests(array $attributes = []): self
    {
        $existing = static::query()->first();
        if ($existing) {
            // Production safeguard: never mutate real Super Admin outside testing
            $env = app()->environment();
            $db = config('database.connections.mysql.database');
            $isTestingEnv = $env === 'testing';
            $isTestDb = $db === 'monetix_test';

            if (! $isTestingEnv || ! $isTestDb) {
                try {
                    \Illuminate\Support\Facades\Log::warning('platform_admin_firstOrReuse_blocked_outside_testing', [
                        'env' => $env,
                        'db' => $db,
                        'existing_id' => $existing->getKey(),
                        'existing_email' => $existing->email ?? null,
                    ]);
                } catch (\Throwable $e) {}
                // Return existing WITHOUT mutating password_hash/status
                return $existing;
            }

            // Update password if test provides one, without triggering immutability on email
            if (isset($attributes['password_hash'])) {
                $existing->password_hash = $attributes['password_hash'];
                // Save without triggering email/is_owner checks (only password)
                $existing->saveQuietly();
            }
            if (isset($attributes['status'])) {
                $existing->status = $attributes['status'];
                $existing->saveQuietly();
            }
            return $existing;
        }

        // No existing row: only allow creation in testing
        $env = app()->environment();
        $db = config('database.connections.mysql.database');
        if ($env !== 'testing' || ! preg_match('/^monetix_test(_test_\d+)?$/', $db)) {
            throw new \RuntimeException(
                'PlatformAdmin::firstOrReuseForTests is test-only and cannot create PlatformAdmin outside testing (env='.$env.', db='.$db.').'
            );
        }

        return static::create($attributes);
    }
}
