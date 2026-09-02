<?php

namespace App\Models;

use App\Mail\GuardianPasswordReset;
use App\Models\Concerns\TenantScoped;
use App\Support\PasswordHash;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Step 47 — Parent / Guardian Portal account.
 *
 * A guardian belongs to exactly one institute and may be linked (through
 * student_guardians) to one or many students. Guardians authenticate through
 * their own dedicated 'guardian' guard and are strictly read-only: they never
 * receive institute permissions and hasPermission() always returns false.
 */
class Guardian extends Authenticatable implements CanResetPasswordContract
{
    use Notifiable;
    use SoftDeletes;
    use TenantScoped;
    use TwoFactorAuthenticatable;

    protected $table = 'guardians';

    public $timestamps = true;

    protected $guarded = [];

    protected $hidden = ['password_hash', 'remember_token'];

    protected $casts = [
        'status' => 'string',
        'preferred_language' => 'string',
        'email_verified_at' => 'datetime',
        'failed_login_count' => 'integer',
        'locked_until' => 'datetime',
        'last_login_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
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

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected static function booted(): void
    {
        static::saving(function (Guardian $g) {
            if ($g->isDirty('email') && $g->email !== null) {
                $norm = \App\Support\EmailNormalizer::normalize($g->email);
                if ($norm !== null) $g->email = $norm;
            }
            if ($g->isDirty('phone') && $g->phone !== null && $g->phone !== '') {
                $norm = \App\Support\PhoneNormalizer::toE164($g->phone, 'Bangladesh');
                if ($norm !== null) $g->phone = $norm;
            }
        });
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Guardians are never granted institute permissions — strictly read-only.
     */
    public function hasPermission(string $permission): bool
    {
        return false;
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return false;
    }

    /**
     * Return the actual password column (guardians.password_hash).
     */
    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['password_hash'] ?? '');
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function routeNotificationForMail($notification): string|array|null
    {
        return $this->email;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new GuardianPasswordReset($token));
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function studentGuardians(): HasMany
    {
        return $this->hasMany(GuardianStudent::class);
    }

    /**
     * Students the guardian is linked to, restricted to currently active
     * relationships.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
            ->withPivot(['relationship', 'is_primary', 'status', 'created_at'])
            ->wherePivot('status', 'active');
    }

    public function primaryStudent(): ?Student
    {
        return $this->studentGuardians()
            ->where('is_primary', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->first()
            ?->student;
    }

    /**
     * Linked students scoped to this guardian's own institute. Guardian queries
     * never carry a BranchContext (they span every branch of their institute),
     * so this returns students regardless of branch.
     */
    public function linkedStudents(): Collection
    {
        return $this->students()->get();
    }

    /**
     * Resolve a linked, active student by id (null when not authorized).
     */
    public function linkedStudent(int $studentId): ?Student
    {
        return $this->students()->whereKey($studentId)->first();
    }

    /**
     * Validate the stored password hash looks well-formed before comparing.
     */
    public function hasValidPasswordHash(): bool
    {
        return PasswordHash::looksValid((string) $this->getAuthPassword());
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }
}
