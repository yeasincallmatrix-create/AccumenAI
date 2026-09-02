<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAttendanceCorrection extends Model
{
    use TenantScoped;

    protected $table = 'hr_attendance_corrections';

    protected $guarded = [];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected function casts(): array
    {
        return [
            'correction_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(HrAttendance::class, 'attendance_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'reviewed_by');
    }
}
