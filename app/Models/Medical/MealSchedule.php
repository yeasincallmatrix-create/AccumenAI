<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MealSchedule extends Model
{
    use SoftDeletes;

    protected $table = 'meal_schedules';

    public const MEAL_TYPES = [
        'breakfast' => 'Breakfast',
        'mid_morning' => 'Mid-Morning',
        'lunch' => 'Lunch',
        'afternoon' => 'Afternoon Snack',
        'dinner' => 'Dinner',
        'bed_time' => 'Bed Time',
    ];

    public const MEAL_TIMES = [
        'breakfast' => '08:00',
        'mid_morning' => '11:00',
        'lunch' => '13:00',
        'afternoon' => '16:00',
        'dinner' => '19:00',
        'bed_time' => '22:00',
    ];

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'prepared' => 'Prepared',
        'served' => 'Served',
        'refused' => 'Refused',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'diet_plan_id', 'institute_id', 'branch_id',
        'meal_date', 'meal_type', 'scheduled_time',
        'menu_items', 'calories',
        'status', 'prepared_at', 'prepared_by',
        'served_at', 'served_by', 'notes',
    ];

    protected $casts = [
        'meal_date' => 'date',
        'calories' => 'integer',
        'prepared_at' => 'datetime',
        'served_at' => 'datetime',
    ];

    public function dietPlan()
    {
        return $this->belongsTo(DietPlan::class, 'diet_plan_id');
    }

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function servedBy()
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('meal_schedules.institute_id', $id);
    }

    public function scopeScheduled($q)
    {
        return $q->where('meal_schedules.status', 'scheduled');
    }

    public function scopeServed($q)
    {
        return $q->where('meal_schedules.status', 'served');
    }

    public function scopeForDate($q, $date)
    {
        return $q->whereDate('meal_schedules.meal_date', $date);
    }

    public function scopeToday($q)
    {
        return $q->whereDate('meal_schedules.meal_date', today());
    }

    public function scopeForPlan($q, int $planId)
    {
        return $q->where('meal_schedules.diet_plan_id', $planId);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'scheduled' => 'secondary',
            'prepared' => 'info',
            'served' => 'success',
            'refused' => 'danger',
            'cancelled' => 'dark',
            default => 'light',
        };
    }

    public function mealTypeLabel(): string
    {
        return self::MEAL_TYPES[$this->meal_type] ?? $this->meal_type;
    }

    public function markPrepared(int $userId): void
    {
        $this->update([
            'status' => 'prepared',
            'prepared_at' => now(),
            'prepared_by' => $userId,
        ]);
    }

    public function markServed(int $userId): void
    {
        $this->update([
            'status' => 'served',
            'served_at' => now(),
            'served_by' => $userId,
        ]);
    }

    public function markRefused(?string $notes = null): void
    {
        $this->update([
            'status' => 'refused',
            'notes' => $notes ?? $this->notes,
        ]);
    }
}
