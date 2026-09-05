<?php

namespace App\Models;

use App\Models\Concerns\DeletesFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Institute extends Model
{
    use DeletesFiles;
    use SoftDeletes;

    protected $fileColumns = ['logo_path', 'cover_photo', 'logo'];

    protected $table = 'institutes';

    public $timestamps = true;

    protected $guarded = [];

    protected $dates = ['deleted_at', 'deletion_requested_at'];

    protected $casts = [
        'is_test' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Institute $institute) {
            // P2: Test institutes created without industry should default to academic
            // so domain:academic routes remain accessible in tests. Production
            // institutes are always created via UI with explicit industry.
            if (app()->environment('testing') && empty($institute->industry) && empty($institute->sub_industry)) {
                $institute->industry = 'education';
                $institute->sub_industry = 'school';
            }

            if (empty($institute->uid)) {
                $institute->uid = function_exists('generateUniqueUid')
                    ? generateUniqueUid('institutes')
                    : static::generateUidFallback();
            }
        });

        static::saving(function (Institute $institute) {
            if (empty($institute->uid)) {
                $institute->uid = function_exists('generateUniqueUid')
                    ? generateUniqueUid('institutes')
                    : static::generateUidFallback();
            }
        });

        static::updating(function (Institute $institute) {
            // Domain immutability: block industry/sub_industry change when domain data exists
            if ($institute->isDirty('industry') || $institute->isDirty('sub_industry')) {
                $oldIndustry = $institute->getOriginal('industry');
                $oldSub = $institute->getOriginal('sub_industry');
                $newIndustry = $institute->industry;
                $newSub = $institute->sub_industry;
                $oldDomain = \App\Support\InstituteDomain::fromKeys((string) $oldIndustry, (string) $oldSub);
                $newDomain = \App\Support\InstituteDomain::fromKeys((string) $newIndustry, (string) $newSub);
                if ($oldDomain !== $newDomain) {
                    if (\App\Support\InstituteDomain::hasDomainData((int) $institute->id)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'industry' => 'Domain change is blocked: this institute has existing courses, subjects, curricula, batches, placements, assessments or results. Create a new institute for the new domain or contact support for a migration workflow.',
                        ]);
                    }
                }
            }
        });
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function notices(): HasMany
    {
        return $this->hasMany(Notice::class);
    }

    public function galleryAlbums(): HasMany
    {
        return $this->hasMany(GalleryAlbum::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function accountHeads(): HasMany
    {
        return $this->hasMany(AccountHead::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function cashMemos(): HasMany
    {
        return $this->hasMany(CashMemo::class);
    }

    public function offlineSyncQueue(): HasMany
    {
        return $this->hasMany(OfflineSyncQueue::class);
    }

    public function courseRequests(): HasMany
    {
        return $this->hasMany(CourseRequest::class);
    }

    public function instituteCourses(): HasMany
    {
        return $this->hasMany(InstituteCourse::class);
    }

    public function instituteSubjects(): HasMany
    {
        return $this->hasMany(InstituteSubject::class);
    }

    public function instituteSubscriptions(): HasMany
    {
        return $this->hasMany(InstituteSubscription::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(InstituteUser::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'institution_id');
    }

    public function memberUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'institution_user', 'institution_id', 'user_id')
            ->withPivot([
                'uuid', 'role_id', 'branch_id', 'employee_id', 'designation',
                'department', 'qualification', 'salary', 'joining_date', 'status',
            ])
            ->withTimestamps();
    }

    public function settings(): HasOne
    {
        return $this->hasOne(InstituteSetting::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function adminLevel1(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'admin_level_1_id');
    }

    public function adminLevel2(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'admin_level_2_id');
    }

    public function adminLevel3(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'admin_level_3_id');
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function academicPlacements(): HasMany
    {
        return $this->hasMany(StudentAcademicPlacement::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    public function moduleOverrides(): HasMany
    {
        return $this->hasMany(InstituteModuleOverride::class);
    }

    public function moduleAccessLogs(): HasMany
    {
        return $this->hasMany(ModuleAccessLog::class);
    }

    public function isModuleEnabled(string $moduleKey): bool
    {
        return app(\App\Services\ModuleAccessService::class)->isEnabled($this, $moduleKey);
    }

    public function enabledModules(): array
    {
        return app(\App\Services\ModuleAccessService::class)->getEnabledModules($this);
    }

    public function getTrainingSetting(string $key, bool $default = true): bool
    {
        $config = $this->settings?->training_config ?? [];
        if (!is_array($config)) {
            $config = [];
        }
        return array_key_exists($key, $config) ? (bool) $config[$key] : $default;
    }

    public function isTrainingFeatureEnabled(string $feature): bool
    {
        return $this->getTrainingSetting($feature, true);
    }

    // Unified logo path — prefers logo_path (spec) then legacy logo column
    public function getLogoPathResolvedAttribute(): ?string
    {
        $path = $this->attributes['logo_path'] ?? null;
        if (!empty($path)) return $path;
        $legacy = $this->attributes['logo'] ?? null;
        if (!empty($legacy)) return $legacy;
        return null;
    }

    public function getLogoUrlAttribute(): string
    {
        $path = $this->logo_path_resolved;
        // spec: if logo_path exists return asset('storage/' . logo_path)
        // we use Storage::disk('public')->url which respects public disk url (includes /monetix/public subfolder), fallback to asset
        if (!empty($path) && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }
        // also try asset fallback even if file not on current disk (e.g., legacy absolute public path)
        if (!empty($path) && is_file(public_path('storage/' . ltrim($path, '/')))) {
            return asset('storage/' . ltrim($path, '/'));
        }
        // spec fallback: default-logo.png
        if (file_exists(public_path('images/default-logo.png'))) {
            return asset('images/default-logo.png');
        }
        // alternative placeholders present in repo
        if (file_exists(public_path('images/logo-placeholder.png'))) {
            return asset('images/logo-placeholder.png');
        }
        if (file_exists(public_path('images/logo.png'))) {
            return asset('images/logo.png');
        }
        // final transparent fallback — generate via placeholder service path
        return asset('images/default-logo.png');
    }

    // Ensure setting logo_path also mirrors to logo for backwards compat (no recursion via direct attribute array)
    public function setLogoPathAttribute($value): void
    {
        $this->attributes['logo_path'] = $value;
        $this->attributes['logo'] = $value;
    }

    public function setLogoAttribute($value): void
    {
        $this->attributes['logo'] = $value;
        $this->attributes['logo_path'] = $value;
    }

    /**
     * Fallback UID generator when helper is not yet loaded.
     * 10-char per spec: 6 alphanum + 4 numeric (tens digit !=0). Supports legacy length param.
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
                    if (\Illuminate\Support\Facades\Schema::hasColumn('institutes', 'uid')) {
                        $exists = \Illuminate\Support\Facades\DB::table('institutes')->where('uid', $uid)->exists();
                    }
                } catch (\Throwable $e) {}
                if (! $exists) return $uid;
            }
            throw new \RuntimeException('Unable to generate unique Institute UID after 100 attempts');
        }
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charLen = strlen($characters);
        $maxAttempts = 100;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $uid = '';
            for ($i = 0; $i < $length; $i++) {
                $uid .= $characters[random_int(0, $charLen - 1)];
            }
            $exists = false;
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('institutes', 'uid')) {
                    $exists = \Illuminate\Support\Facades\DB::table('institutes')->where('uid', $uid)->exists();
                }
            } catch (\Throwable $e) {}
            if (! $exists) return $uid;
        }
        throw new \RuntimeException('Unable to generate unique Institute UID after ' . $maxAttempts . ' attempts');
    }
}
