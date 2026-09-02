<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Student extends Model
{
    use Concerns\BranchScoped;
    use Concerns\DeletesFiles;
    use Concerns\TenantScoped;
    use SoftDeletes;

    protected $fileColumns = ['photo', 'document'];

    protected $table = 'students';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'branch_id',
        'user_id',
        'reg_no',
        'student_id',
        'student_id_number',
        'application_number',
        'application_date',
        'admission_status',
        'admission_source',
        'admission_reject_reason',
        'applied_course_id',
        'applied_academic_year_id',
        'preferred_batch_id',
        'admission_assigned_user_id',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'roll_number',
        'full_name',
        'first_name',
        'last_name',
        'photo',
        'document',
        'father_name',
        'mother_name',
        'gender',
        'dob',
        'blood_group',
        'religion',
        'nationality',
        'nid_number',
        'birth_cert_number',
        'phone',
        'guardian_phone',
        'email',
        'country',
        'present_address',
        'permanent_address',
        'present_division_id',
        'present_district_id',
        'present_upazila_id',
        'present_post_office',
        'present_zip_code',
        'permanent_division_id',
        'permanent_district_id',
        'permanent_upazila_id',
        'permanent_post_office',
        'permanent_zip_code',
        'present_country_id',
        'present_admin_1_id',
        'present_admin_2_id',
        'present_admin_3_id',
        'permanent_country_id',
        'permanent_admin_1_id',
        'permanent_admin_2_id',
        'permanent_admin_3_id',
        'national_id_or_birth_certificate',
        'passport_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'admission_date',
        'status',
        'crm_contact_id',
        'crm_lead_id',
    ];

    protected $hidden = [
        'nid_number',
        'birth_cert_number',
        'passport_number',
        'national_id_or_birth_certificate',
        'crm_contact_id',
        'crm_lead_id',
        'admission_assigned_user_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'admission_date' => 'date',
        'application_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Keep tenant/branch scoping traits happy — they don't define booted, so parent call is safe
        // Generate unique global reg_no + institute-scoped student_id per spec on creation
        static::creating(function (Student $model) {
            if (empty($model->reg_no)) {
                $instituteUid = '0000000000';
                try {
                    if (! empty($model->institute_id)) {
                        $inst = \App\Models\Institute::find($model->institute_id);
                        if ($inst && ! empty($inst->uid)) {
                            $instituteUid = $inst->uid;
                        }
                    }
                } catch (\Throwable $e) {}

                $attempts = 0;
                do {
                    $regNo = function_exists('generateStudentRegNo')
                        ? generateStudentRegNo($instituteUid)
                        : static::fallbackRegNo($instituteUid);
                    $exists = false;
                    try {
                        $exists = static::where('reg_no', $regNo)->exists();
                    } catch (\Throwable $e) {
                        $exists = false;
                    }
                    $attempts++;
                    if ($attempts > 100) break;
                } while ($exists);
                $model->reg_no = $regNo;
            }

            // Auto-generate 6-digit institute-scoped student_id
            if (empty($model->student_id)) {
                try {
                    $model->student_id = function_exists('generateInstituteStudentId')
                        ? generateInstituteStudentId($model->institute_id)
                        : str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                } catch (\Throwable $e) {
                    // Fallback to random if helper throws (e.g., table not yet migrated)
                    $model->student_id = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                }
                // Ensure uniqueness within institute with retry (handles race)
                $attempts = 0;
                while ($attempts < 50) {
                    try {
                        $exists = false;
                        if (! empty($model->institute_id)) {
                            // Use DB directly to avoid global scope recursion
                            $exists = DB::table('students')
                                ->where('institute_id', $model->institute_id)
                                ->where('student_id', $model->student_id)
                                ->exists();
                        }
                        if (! $exists) break;
                    } catch (\Throwable $e) { break; }
                    $model->student_id = function_exists('generateInstituteStudentId')
                        ? generateInstituteStudentId($model->institute_id)
                        : str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                    $attempts++;
                }
            }

            // Auto-link to user via email (structural linking for publish)
            if (empty($model->user_id) && ! empty($model->email)) {
                try {
                    $user = DB::table('users')->where('email', $model->email)->first(['id']);
                    if ($user) {
                        $model->user_id = $user->id;
                    }
                } catch (\Throwable $e) {}
            }
        });

        static::saving(function (Student $model) {
            if (empty($model->reg_no)) {
                $instituteUid = '0000000000';
                try {
                    if (! empty($model->institute_id)) {
                        $inst = \App\Models\Institute::find($model->institute_id);
                        if ($inst && ! empty($inst->uid)) $instituteUid = $inst->uid;
                    }
                } catch (\Throwable $e) {}
                $regNo = function_exists('generateStudentRegNo') ? generateStudentRegNo($instituteUid) : static::fallbackRegNo($instituteUid);
                $attempts = 0;
                while ($attempts < 50) {
                    try {
                        if (! static::where('reg_no', $regNo)->exists()) break;
                    } catch (\Throwable $e) { break; }
                    $regNo = function_exists('generateStudentRegNo') ? generateStudentRegNo($instituteUid) : static::fallbackRegNo($instituteUid);
                    $attempts++;
                }
                $model->reg_no = $regNo;
            }

            if (empty($model->student_id) && ! empty($model->institute_id)) {
                try {
                    $model->student_id = function_exists('generateInstituteStudentId')
                        ? generateInstituteStudentId($model->institute_id)
                        : str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                } catch (\Throwable $e) {
                    $model->student_id = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                }
                $attempts = 0;
                while ($attempts < 50) {
                    try {
                        if (! DB::table('students')->where('institute_id', $model->institute_id)->where('student_id', $model->student_id)->exists()) break;
                    } catch (\Throwable $e) { break; }
                    $model->student_id = function_exists('generateInstituteStudentId')
                        ? generateInstituteStudentId($model->institute_id)
                        : str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
                    $attempts++;
                }
            }

            // Auto-link to user via email if not already linked
            if (empty($model->user_id) && ! empty($model->email)) {
                try {
                    $user = DB::table('users')->where('email', $model->email)->first(['id']);
                    if ($user) {
                        $model->user_id = $user->id;
                    }
                } catch (\Throwable $e) {}
            }
        });
    }

    private static function fallbackRegNo(string $instituteUid): string
    {
        $lastTwo = substr($instituteUid, -2);
        if (! preg_match('/^\d{2}$/', $lastTwo)) {
            $digits = preg_replace('/\D/', '', $instituteUid);
            $lastTwo = strlen($digits) >= 2 ? substr($digits, -2) : str_pad($digits ?? '', 2, '0', STR_PAD_LEFT);
            $lastTwo = str_pad($lastTwo, 2, '0', STR_PAD_LEFT);
        }
        $year = date('y');
        $month = date('m');
        $random = str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
        $dayLastDigit = substr(date('j'), -1);
        return $lastTwo . $year . $month . $random . $dayLastDigit;
    }

    public const ADMISSION_STATUS_DRAFT = 'draft';

    public const ADMISSION_STATUS_SUBMITTED = 'submitted';

    public const ADMISSION_STATUS_UNDER_REVIEW = 'under_review';

    public const ADMISSION_STATUS_APPROVED = 'approved';

    public const ADMISSION_STATUS_REJECTED = 'rejected';

    public const ADMISSION_STATUS_CANCELLED = 'cancelled';

    public const ADMISSION_STATUS_ENROLLED = 'enrolled';

    public const ADMISSION_STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DROPPED = 'dropped';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_DROPPED,
        self::STATUS_SUSPENDED,
    ];

    public const ADMISSION_STATUSES = [
        self::ADMISSION_STATUS_DRAFT,
        self::ADMISSION_STATUS_SUBMITTED,
        self::ADMISSION_STATUS_UNDER_REVIEW,
        self::ADMISSION_STATUS_APPROVED,
        self::ADMISSION_STATUS_REJECTED,
        self::ADMISSION_STATUS_CANCELLED,
        self::ADMISSION_STATUS_ENROLLED,
        self::ADMISSION_STATUS_WITHDRAWN,
    ];

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('reg_no', 'like', $like)
                ->orWhere('student_id_number', 'like', $like)
                ->orWhere('application_number', 'like', $like)
                ->orWhere('roll_number', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('passport_number', 'like', $like)
                ->orWhere('nid_number', 'like', $like)
                ->orWhere('birth_cert_number', 'like', $like)
                ->orWhere('national_id_or_birth_certificate', 'like', $like);
        });
    }

    /**
     * Next student_id_number for an institute, continuing the numeric series
     * used by the legacy dataset (e.g. inst 4 ends at 7840110102 -> 7840110103).
     */
    public static function nextStudentNumber(int $instituteId): string
    {
        // Include soft-deleted rows: their (institute, number) pairs still
        // occupy the unique index, so the generated number must not collide.
        $max = (int) static::query()
            ->withTrashed()
            ->where('institute_id', $instituteId)
            ->withoutGlobalScope('institute')
            ->max(DB::raw('CAST(student_id_number AS UNSIGNED)'));

        return (string) ($max + 1);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function crmContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function appliedCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'applied_course_id');
    }

    public function appliedAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'applied_academic_year_id');
    }

    public function preferredBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'preferred_batch_id');
    }

    public function admissionAssignedUser(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'admission_assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'rejected_by');
    }

    public function getPhotoUrlAttribute(): string
    {
        if (empty($this->photo)) {
            return asset('images/default-avatar.png');
        }

        // Remove any legacy prefixes (storage/, public/, leading slash)
        $path = ltrim($this->photo, '/');
        $path = preg_replace('#^(public/|storage/)#', '', $path);

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        // Fallback (preserves old behavior for debugging)
        return asset('storage/' . $path);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    /**
     * Accept an applicant's combined name from the admission form and split it
     * into the first/last columns (single-token names land in first_name).
     */
    public function setFullNameAttribute(string $value): void
    {
        $name = trim($value);
        if ($name === '') {
            return;
        }

        $parts = preg_split('/\s+/', $name);
        if (count($parts) === 1) {
            $this->attributes['first_name'] = $parts[0];
            $this->attributes['last_name'] = null;

            return;
        }

        $last = (string) array_pop($parts);
        $this->attributes['first_name'] = implode(' ', $parts);
        $this->attributes['last_name'] = $last;
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(\App\Models\Training\Enrollment::class, 'student_id');
    }

    public function examResults(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function alumniProfile(): HasOne
    {
        return $this->hasOne(Alumni::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function cashMemos(): HasMany
    {
        return $this->hasMany(CashMemo::class);
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'enrollments', 'student_id', 'batch_id');
    }

    public function academicPlacements(): HasMany
    {
        return $this->hasMany(StudentAcademicPlacement::class);
    }

    /**
     * The student's most recent academic placement (current year wins), used
     * by the academic exit + lifecycle services as the single point that
     * identifies the "current" placement vs. historical ones.
     */
    public function currentAcademicPlacement(): ?StudentAcademicPlacement
    {
        return $this->academicPlacements()
            ->orderByDesc('academic_year_id')
            ->orderByDesc('id')
            ->first();
    }
}
