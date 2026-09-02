<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teacher/instructor professional profile (Step 36).
 *
 * 1:1 extension of `institute_users` (role teacher). Identity fields (name,
 * email, phone, photo, gender, designation, qualification, joining_date,
 * employee_id, status, branch) stay on the identity table — this table only
 * carries instructor-specific data that the identity table does not.
 *
 * Branch scoping is intentionally NOT applied here: the branch lives on the
 * owning institute_user and is enforced through its existing global scopes.
 */
class TeacherProfile extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'teacher_profiles';

    protected $guarded = [];

    public const EMPLOYMENT_STATUSES = ['active', 'inactive', 'suspended', 'resigned', 'terminated', 'on_leave'];

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contractual', 'adjunct', 'volunteer'];

    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
            'date_of_birth' => 'date',
            'skills' => 'array',
            'languages' => 'array',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function instituteUser(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'institute_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }
}
