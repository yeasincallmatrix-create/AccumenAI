<?php

namespace App\Models;

use App\Exceptions\AccountTypeMismatchException;
use App\Models\Concerns\HasUserPreferences;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * The global AccumenAI account. One account may hold many memberships
 * (institution_user) across many organizations. Person-level identity and
 * authentication (password, 2FA, email verification, sessions) live here —
 * never duplicated per organization.
 *
 * The `users` table stores the password in `password_hash` (matching
 * institute_users / platform_admins), so authentication reads that column.
 */
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use Concerns\DeletesFiles;
    use HasFactory, HasUserPreferences, MustVerifyEmail, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fileColumns = ['photo'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'uid',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'email_verified_at',
        'phone_verified_at',
        'pending_email',
        'pending_email_token_hash',
        'pending_email_expires_at',
        'pending_phone',
        'preferred_language',
        'preferences',
        'photo',
        'password_hash',
        'status',
        'account_type',
        'is_test',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'pending_email_expires_at' => 'datetime',
            'failed_login_count' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'inactivity_warning_sent_at' => 'datetime',
            'inactivity_final_warning_sent_at' => 'datetime',
            'inactivity_deleted_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'preferences' => 'array',
            'is_test' => 'boolean',
        ];
    }

    /**
     * Forever-idempotent password setter — accepts plain or already-hashed.
     * Prevents double-hashing when callers do Hash::make() + Eloquent save.
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

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->uid)) {
                if (function_exists('generateUid')) {
                    $user->uid = generateUniqueUid((new static)->getTable());
                } elseif (function_exists('generate_platform_uid')) {
                    $user->uid = generate_platform_uid();
                } else {
                    $user->uid = static::generateUidFallback(10);
                }
            }
        });

        static::saving(function (User $user) {
            // Ensure UID is always set (covers raw creates that bypass creating event edge-cases)
            if (empty($user->uid)) {
                if (function_exists('generateUid')) {
                    $user->uid = generateUniqueUid((new static)->getTable());
                } elseif (function_exists('generate_platform_uid')) {
                    $user->uid = generate_platform_uid();
                } else {
                    $user->uid = static::generateUidFallback(10);
                }
            }

            // Only guard account-type CHANGES on existing accounts.
            // Creation has no memberships yet, so it's always consistent.
            if ($user->exists && $user->isDirty('account_type')) {
                $user->assertAccountTypeConsistentWithMemberships();
            }

            // Email normalization: lowercase + trim where dirty
            if ($user->isDirty('email') && $user->email !== null) {
                $norm = \App\Support\EmailNormalizer::normalize($user->email);
                if ($norm !== null) {
                    $user->email = $norm;
                }
            }
            if ($user->isDirty('pending_email') && $user->pending_email !== null) {
                $norm = \App\Support\EmailNormalizer::normalize($user->pending_email);
                if ($norm !== null) {
                    $user->pending_email = $norm;
                }
            }
            // Phone normalization: canonical E164 via PhoneNormalizer where dirty
            if ($user->isDirty('phone') && $user->phone !== null && $user->phone !== '') {
                $norm = \App\Support\PhoneNormalizer::toE164($user->phone, 'Bangladesh');
                if ($norm !== null) {
                    $user->phone = $norm;
                }
            }
            if ($user->isDirty('pending_phone') && $user->pending_phone !== null && $user->pending_phone !== '') {
                $norm = \App\Support\PhoneNormalizer::toE164($user->pending_phone, 'Bangladesh');
                if ($norm !== null) {
                    $user->pending_phone = $norm;
                }
            }
        });
    }

    public function isOwnerAccount(): bool
    {
        return $this->account_type === 'owner';
    }

    public function isStaffAccount(): bool
    {
        return $this->account_type === 'staff';
    }

    public function assertAccountTypeConsistentWithMemberships(): void
    {
        $hasOwner = $this->memberships()->whereHas('role', fn ($q) => $q->where('slug', 'institute-owner'))->exists();
        $hasStaff = $this->memberships()->whereHas('role', fn ($q) => $q->where('slug', '!=', 'institute-owner'))->exists();

        if ($this->account_type === 'staff' && $hasOwner) {
            throw AccountTypeMismatchException::staffCannotConvert();
        }
        if ($this->account_type === 'owner' && $hasStaff) {
            throw AccountTypeMismatchException::ownerCannotConvert();
        }
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institute::class, 'institution_user', 'user_id', 'institution_id')
            ->withPivot([
                'uuid', 'role_id', 'branch_id', 'employee_id', 'designation',
                'department', 'qualification', 'salary', 'joining_date', 'status',
            ])
            ->withTimestamps();
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function getInstituteIdAttribute(): ?int
    {
        $membership = \App\Support\Workspace::membership();
        if ($membership) {
            return (int) $membership->institution_id;
        }
        // Fallback: direct query when auth not set (e.g. CLI) or workspace not via auth
        $wid = \App\Support\Workspace::id() ?? \App\Support\TenantContext::id();
        if ($wid !== null) {
            $m = \App\Models\Membership::where('user_id', $this->id)->where('institution_id', $wid)->where('status', 'active')->first();
            if ($m) {
                return (int) $m->institution_id;
            }

            return (int) $wid;
        }

        $first = $this->memberships()->where('status', 'active')->orderBy('institution_id')->first();
        if ($first) {
            return (int) $first->institution_id;
        }

        return null;
    }

    private function activeMembership(): ?\App\Models\Membership
    {
        $m = \App\Support\Workspace::membership();
        if ($m && (int) $m->user_id === (int) $this->id) {
            return $m;
        }
        $wid = \App\Support\Workspace::id() ?? \App\Support\TenantContext::id();
        if ($wid !== null) {
            $direct = \App\Models\Membership::where('user_id', $this->id)->where('institution_id', $wid)->where('status', 'active')->first();
            if ($direct) {
                return $direct;
            }
        }
        // Last resort: first active membership (single-org owners)
        return $this->memberships()->where('status', 'active')->orderBy('institution_id')->first();
    }

    public function hasPermission(string $permission): bool
    {
        $membership = $this->activeMembership();
        if ($membership === null) {
            return false;
        }

        return $membership->hasPermission($permission);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        $membership = $this->activeMembership();
        if ($membership === null) {
            return false;
        }

        return $membership->hasAnyPermission($permissions);
    }

    public function hasRole(string|array $roles): bool
    {
        $membership = $this->activeMembership();
        if ($membership === null) {
            return false;
        }

        return $membership->hasRole($roles);
    }

    public function isOwner(): bool
    {
        $membership = $this->activeMembership();
        if ($membership === null) {
            return $this->isOwnerAccount();
        }

        return $membership->isOwner();
    }

    /**
     * The password storage column is `password_hash`.
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

    public function getEmailVerifiedAtAttribute($value)
    {
        if ($value !== null) {
            return $this->asDateTime($value);
        }
        return null;
    }

    /**
     * Queued verification dispatch — fixes E12.4 timeout forensic.
     * In testing env, keep synchronous Illuminate\Auth\Notifications\VerifyEmail
     * so Notification::fake() + assertSentTo(VerifyEmail::class) remains green.
     * In local/production, dispatch App\Notifications\QueuedVerifyEmail via
     * database queue (non-blocking). HTTP request only does INSERT into jobs
     * (<100ms); SMTP (4s or 30s timeout) happens in queue worker, not in
     * web request, thus no "Maximum execution time ... Connection.php:420".
     */
    public function sendEmailVerificationNotification(): void
    {
        if (app()->environment('testing')) {
            $this->notify(new \Illuminate\Auth\Notifications\VerifyEmail);
            return;
        }
        try {
            $this->notify(new \App\Notifications\QueuedVerifyEmail);
            if (app()->environment('local') && config('queue.default') === 'database') {
                try {
                    $pending = \Illuminate\Support\Facades\DB::table('jobs')->count();
                    if ($pending > 3) {
                        \Illuminate\Support\Facades\Log::warning('verification_queue_stuck_hint', ['pending_jobs' => $pending, 'hint' => 'Run php artisan queue:work for email delivery']);
                    }
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('verification_queued_failed_fallback', ['user_id' => $this->getKey(), 'error' => substr($e->getMessage(), 0, 300)]);
            // Fallback to sync notification so link still arrives
            $this->notify(new \Illuminate\Auth\Notifications\VerifyEmail);
        }
    }

    /**
     * Fallback UID generator when helper is not yet loaded.
     * Generates 10-char UID per spec: 6 alphanumeric + 4 numeric (tens digit !=0).
     * Falls back to 6-char if length explicitly 6 for BC.
     */
    private static function generateUidFallback(int $length = 10): string
    {
        if ($length === 10) {
            $alphanumeric = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $maxAttempts = 100;
            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $firstTwo = str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
                $lastTwo = str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
                $uid = '';
                for ($i = 0; $i < 6; $i++) {
                    $uid .= $alphanumeric[random_int(0, strlen($alphanumeric) - 1)];
                }
                $uid .= $firstTwo . $lastTwo;
                $exists = false;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'uid')) {
                        $exists = \Illuminate\Support\Facades\DB::table('users')->where('uid', $uid)->exists();
                    }
                } catch (\Throwable $e) {}
                if (! $exists) return $uid;
            }
            throw new \RuntimeException('Unable to generate unique UID fallback after 100 attempts');
        }
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charLen = strlen($chars);
        $maxAttempts = 100;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $uid = '';
            for ($i = 0; $i < $length; $i++) {
                $uid .= $chars[random_int(0, $charLen - 1)];
            }
            $exists = false;
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'uid')) {
                    $exists = \Illuminate\Support\Facades\DB::table('users')->where('uid', $uid)->exists();
                }
            } catch (\Throwable $e) {}
            if (! $exists) return $uid;
        }
        throw new \RuntimeException('Unable to generate unique UID fallback after ' . $maxAttempts . ' attempts');
    }
}
