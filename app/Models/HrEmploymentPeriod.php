<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HR Employment Period — continuous service segment.
 *
 * A new period opens on joining/rejoin/reactivation; the current period is closed
 * (end_date set, status=closed) on resignation approval or termination. Never deleted.
 * Supports reporting: current period (status=active), previous periods (closed), total duration.
 */
class HrEmploymentPeriod extends Model
{
    use TenantScoped;

    protected $table = 'hr_employment_periods';

    protected $guarded = [];

    public const STATUSES = ['active', 'closed'];

    public const END_REASONS = ['resigned', 'terminated', 'inactive', 'other'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'started_by');
    }

    public function ender(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'ended_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_date === null;
    }

    public function durationInDays(): int
    {
        $end = $this->end_date ?? Carbon::today();
        $start = $this->start_date instanceof Carbon ? $this->start_date : Carbon::parse($this->start_date);

        return (int) $start->diffInDays($end, false);
    }

    /**
     * Total service days across all periods for an employee.
     */
    public static function totalServiceDays(int $employeeId, int $instituteId): int
    {
        $periods = static::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('institute_id', $instituteId)
            ->get();

        $total = 0;
        foreach ($periods as $period) {
            $total += $period->durationInDays();
        }

        return max(0, $total);
    }
}
