<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrAttendance extends Model
{
    use TenantScoped;

    protected $table = 'hr_attendances';

    protected $guarded = [];

    public const STATUSES = ['present', 'absent', 'late', 'early_departure', 'leave', 'holiday', 'weekend', 'half_day'];

    public const SOURCES = ['manual', 'system', 'api', 'import'];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'working_minutes' => 'integer',
            'late_minutes' => 'integer',
            'overtime_minutes' => 'integer',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(HrWorkShift::class, 'shift_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(HrAttendanceCorrection::class, 'attendance_id');
    }
}
