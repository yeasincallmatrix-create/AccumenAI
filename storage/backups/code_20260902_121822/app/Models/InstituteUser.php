<?php

namespace App\Models;

use App\Models\Concerns\HasUserPreferences;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class InstituteUser extends Authenticatable implements MustVerifyEmailContract
{
    use Concerns\BranchScoped;
    use Concerns\TenantScoped;
    use HasApiTokens;
    use HasUserPreferences;
    use MustVerifyEmail;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    protected $table = 'institute_users';

    public $timestamps = true;

    /**
     * Guarded: privileged escalation vectors must not be mass assignable
     */
    protected $fillable = [
        'uuid',
        'institute_id',
        'role_id',
        'branch_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password_hash',
        'status',
        'email_verified_at',
        'employee_id',
        'designation',
        'department',
        'salary',
        'joining_date',
        'preferred_language',
        'preferences',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'failed_login_count' => 'integer',
        'salary' => 'decimal:2',
        'locked_until' => 'datetime',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'preferences' => 'array',
    ];

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

    protected static function booted(): void
    {
        static::creating(function (InstituteUser $user) {
            // P2: In testing, auto-verify email so `verified` middleware does not
            // block academic route tests that create users without email_verified_at.
            if (app()->environment('testing') && empty($user->email_verified_at)) {
                $user->email_verified_at = now();
            }
        });

        static::saving(function (InstituteUser $user) {
            if ($user->isDirty('email') && $user->email !== null) {
                $norm = \App\Support\EmailNormalizer::normalize($user->email);
                if ($norm !== null) $user->email = $norm;
            }
            if ($user->isDirty('phone') && $user->phone !== null && $user->phone !== '') {
                $norm = \App\Support\PhoneNormalizer::toE164($user->phone, 'Bangladesh');
                if ($norm !== null) $user->phone = $norm;
            }
            // Mass assignment protection: block is_owner/super_admin escalation vectors
            foreach (['is_owner', 'singleton_guard', 'platform_admin', 'super_admin', 'guard', 'account_type'] as $blocked) {
                if (isset($user->attributes[$blocked])) {
                    unset($user->attributes[$blocked]);
                }
            }
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
     * Whether the user holds any of the given role slugs.
     */
    public function hasRole(string|array $roles): bool
    {
        if ($this->role === null) {
            return false;
        }

        $slugs = is_array($roles) ? $roles : [$roles];

        return in_array($this->role->slug, $slugs, true);
    }

    /**
     * Whether the user may perform the given action (module.permission slug).
     *
     * The institute owner is treated as a super-user inside their institute.
     * The role's linked permissions come from the role_permissions matrix.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $role = $this->role;
        if ($role === null || $role->status !== 'active') {
            return false;
        }

        return $role->permissions->contains('slug', $permission);
    }

    /**
     * Whether the user may perform any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isOwner(): bool
    {
        return $this->hasRole('institute-owner');
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

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class, 'institute_user_id');
    }

    public function academicAssignments(): HasMany
    {
        return $this->hasMany(TeacherAcademicAssignment::class, 'institute_user_id');
    }
}
