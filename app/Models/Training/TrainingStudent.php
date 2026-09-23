<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingStudent extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_students';

    protected $fillable = [
        'institute_id', 'branch_id', 'user_id', 'reg_no', 'student_id',
        'is_test', 'first_name', 'last_name', 'full_name', 'name', 'uuid', 'student_id_number',
        'application_number', 'application_date', 'admission_status', 'admission_source',
        'admission_reject_reason', 'applied_course_id', 'applied_academic_year_id',
        'preferred_batch_id', 'admission_assigned_user_id', 'created_by', 'approved_by',
        'approved_at', 'rejected_by', 'rejected_at', 'roll_number', 'photo', 'document',
        'father_name', 'mother_name', 'gender', 'dob', 'blood_group', 'religion',
        'nationality', 'nid_number', 'birth_cert_number', 'phone', 'guardian_phone',
        'email', 'country', 'present_country_id', 'present_admin_1_id', 'present_admin_2_id',
        'present_admin_3_id', 'present_address', 'permanent_address', 'present_post_office',
        'present_zip_code', 'permanent_post_office', 'permanent_zip_code', 'permanent_country_id',
        'permanent_admin_1_id', 'permanent_admin_2_id', 'permanent_admin_3_id',
        'national_id_or_birth_certificate', 'passport_number', 'emergency_contact_name',
        'emergency_contact_phone', 'admission_date', 'status', 'crm_contact_id', 'crm_lead_id',
    ];

    protected $hidden = [
        'nid_number', 'birth_cert_number', 'passport_number', 'national_id_or_birth_certificate',
        'crm_contact_id', 'crm_lead_id',
    ];

    protected $casts = [
        'dob' => 'date', 'admission_date' => 'date', 'approved_at' => 'datetime', 'rejected_at' => 'datetime',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class, 'student_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(TrainingAttendance::class, 'student_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(TrainingCertificate::class, 'student_id');
    }

    public function examResults(): HasMany
    {
        return $this->hasMany(TrainingExamResult::class, 'student_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrainingBatch::class, 'preferred_batch_id');
    }
}
