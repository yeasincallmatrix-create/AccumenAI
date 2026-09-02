<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmployeeSkill extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_employee_skills';

    protected $guarded = [];

    public const PROFICIENCY = ['beginner', 'intermediate', 'advanced', 'expert'];

    public const VERIFICATION = ['pending', 'verified', 'rejected'];

    protected function casts(): array
    {
        return ['acquired_date' => 'date', 'verified_at' => 'datetime'];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'verified_by');
    }
}
