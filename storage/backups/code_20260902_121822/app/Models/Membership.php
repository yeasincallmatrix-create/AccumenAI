<?php

namespace App\Models;

use App\Exceptions\AccountTypeMismatchException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Membership — the many-to-many link between a global AccumenAI account
 * (User) and an Organization (Institute). The role is scoped to the
 * membership, never globally to the user.
 *
 * Replaces the legacy per-institute account (institute_users) concept.
 */
class Membership extends Model
{
    use SoftDeletes;

    protected $table = 'institution_user';

    public $timestamps = true;

    protected $fillable = [
        'uuid',
        'user_id',
        'institution_id',
        'role_id',
        'branch_id',
        'employee_id',
        'designation',
        'department',
        'status',
        'is_test',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'joining_date' => 'date',
        'is_test' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Membership $membership) {
            $membership->assertRoleAllowedForAccountType();
        });

        static::updating(function (Membership $membership) {
            $membership->assertRoleAllowedForAccountType();
        });
    }

    /**
     * Owner/Staff invariant: resolve the role fresh from the current
     * role_id attribute (never a cached ->role relationship, which can be
     * stale after role_id changes) and compare against the account type.
     */
    public function assertRoleAllowedForAccountType(): void
    {
        $user = $this->user;
        $role = Role::query()->find($this->getAttribute('role_id'));

        if ($user === null || $role === null) {
            return;
        }

        $isOwnerRole = $role->slug === 'institute-owner';

        if ($isOwnerRole && ! $user->isOwnerAccount()) {
            throw AccountTypeMismatchException::staffCannotOwn();
        }
        if (! $isOwnerRole && ! $user->isStaffAccount()) {
            throw AccountTypeMismatchException::ownerCannotBeStaff();
        }
    }

    /**
     * Read-time counterpart of assertRoleAllowedForAccountType(): whether
     * this membership's role is permitted for the given account type.
     * Used for defense-in-depth when resolving/verifying a workspace — an
     * unverifiable user/role (null) returns true so nothing is blocked.
     */
    public function roleAllowedForAccountType(?User $user): bool
    {
        $user ??= $this->user;
        $role = $this->role ?? Role::query()->find($this->getAttribute('role_id'));

        if ($user === null || $role === null) {
            return true;
        }

        $isOwnerRole = $role->slug === 'institute-owner';

        return $isOwnerRole ? $user->isOwnerAccount() : $user->isStaffAccount();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institute::class, 'institution_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Whether this membership holds any of the given role slugs.
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
     * Whether this membership may perform the given action
     * (module.permission slug). The owner is a super-user inside their
     * organization; other roles follow the role_permissions matrix.
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

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deleted_at === null;
    }
}
