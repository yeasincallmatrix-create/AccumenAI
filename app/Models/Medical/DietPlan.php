<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DietPlan extends Model
{
    use SoftDeletes;

    protected $table = 'diet_plans';

    public const DIET_TYPES = [
        'regular' => 'Regular',
        'diabetic' => 'Diabetic',
        'renal' => 'Renal',
        'low_sodium' => 'Low Sodium',
        'cardiac' => 'Cardiac',
        'pediatric' => 'Pediatric',
        'liquid' => 'Liquid',
        'soft' => 'Soft',
        'tube_feeding' => 'Tube Feeding',
        'tpn' => 'TPN (Total Parenteral Nutrition)',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'completed' => 'Completed',
        'discontinued' => 'Discontinued',
        'on_hold' => 'On Hold',
    ];

    protected $fillable = [
        'institute_id', 'branch_id', 'plan_number',
        'patient_id', 'prescribed_by', 'admission_id',
        'plan_name', 'diet_type', 'restrictions', 'medical_notes',
        'daily_calories', 'protein_grams', 'carbs_grams', 'fat_grams',
        'sodium_mg', 'potassium_mg', 'fluid_ml',
        'start_date', 'end_date', 'days_planned',
        'status', 'discontinue_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'daily_calories' => 'integer',
        'days_planned' => 'integer',
        'protein_grams' => 'decimal:2',
        'carbs_grams' => 'decimal:2',
        'fat_grams' => 'decimal:2',
        'sodium_mg' => 'decimal:2',
        'potassium_mg' => 'decimal:2',
        'fluid_ml' => 'decimal:2',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescribedBy()
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function mealSchedules()
    {
        return $this->hasMany(MealSchedule::class, 'diet_plan_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('diet_plans.institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('diet_plans.branch_id', $id) : $q;
    }

    public function scopeActive($q)
    {
        return $q->where('diet_plans.status', 'active');
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('diet_plans.patient_id', $patientId);
    }

    public function scopeByType($q, string $type)
    {
        return $q->where('diet_plans.diet_type', $type);
    }

    public function dietTypeLabel(): string
    {
        return self::DIET_TYPES[$this->diet_type] ?? $this->diet_type;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'completed' => 'primary',
            'on_hold' => 'warning',
            'discontinued' => 'secondary',
            default => 'dark',
        };
    }

    public function dietTypeColor(): string
    {
        return match ($this->diet_type) {
            'regular' => 'success',
            'diabetic' => 'info',
            'renal' => 'primary',
            'low_sodium', 'cardiac' => 'danger',
            'pediatric' => 'warning',
            'liquid', 'soft' => 'secondary',
            'tube_feeding', 'tpn' => 'dark',
            default => 'light',
        };
    }

    public function progressPercent(): int
    {
        $total = $this->mealSchedules()->count();
        if ($total === 0) {
            return 0;
        }
        $served = $this->mealSchedules()->where('meal_schedules.status', 'served')->count();

        return (int) round(($served / $total) * 100);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
