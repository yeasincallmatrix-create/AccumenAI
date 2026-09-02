<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use Concerns\TenantScoped;
    use SoftDeletes;

    protected $table = 'branches';

    public $timestamps = true;

    protected $guarded = [];

    protected $fillable = [
        'institute_id',
        'name',
        'manager_user_id',
        'phone',
        'email',
        'address',
        'status',
        'is_principal',
        'code',
    ];

    protected $casts = [
        'is_principal' => 'boolean',
        'code' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (Branch $branch) {
            if (empty($branch->code)) {
                $branch->code = self::generateUniqueCode();
            }
        });

        static::created(function (Branch $branch) {
            self::reconcilePrincipalStatus($branch->institute_id);
        });

        static::deleted(function (Branch $branch) {
            self::reconcilePrincipalStatus($branch->institute_id);
        });

        static::restored(function (Branch $branch) {
            self::reconcilePrincipalStatus($branch->institute_id);
        });
    }

    private static function generateUniqueCode(): string
    {
        do {
            $code = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (self::where('code', $code)->exists());
        return $code;
    }

    /**
     * If an institute has exactly one (non-deleted) branch, ensure it is marked principal.
     * If multiple branches exist, do not auto-unset (allows admin override).
     */
    private static function reconcilePrincipalStatus(int $instituteId): void
    {
        $activeBranches = static::where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->get(['id', 'is_principal']);

        if ($activeBranches->count() === 1) {
            $single = $activeBranches->first();
            if (!$single->is_principal) {
                static::withoutTimestamps(function () use ($single) {
                    static::where('id', $single->id)->update(['is_principal' => true]);
                });
            }
        }
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'manager_user_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(InstituteUser::class);
    }
}
