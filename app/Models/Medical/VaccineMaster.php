<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;

class VaccineMaster extends Model
{
    use SoftDeletes;

    protected $table = 'vaccine_masters';

    protected $fillable = [
        'institute_id', 'code', 'name', 'short_name', 'category',
        'description', 'protects_against', 'route', 'site', 'dose_volume',
        'doses_in_series', 'min_age_days', 'max_age_days', 'interval_days_min',
        'default_fee', 'is_active',
    ];

    protected $casts = [
        'doses_in_series' => 'integer',
        'min_age_days' => 'integer',
        'max_age_days' => 'integer',
        'interval_days_min' => 'integer',
        'default_fee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public const CATEGORIES = [
        'EPI' => 'EPI (Expanded Programme on Immunization)',
        'optional' => 'Optional',
        'travel' => 'Travel',
        'seasonal' => 'Seasonal',
        'covid' => 'COVID-19',
        'booster' => 'Booster',
    ];

    public const ROUTES = [
        'IM' => 'Intramuscular (IM)',
        'SC' => 'Subcutaneous (SC)',
        'oral' => 'Oral',
        'intradermal' => 'Intradermal (ID)',
    ];

    public const SITES = [
        'left_thigh' => 'Left Thigh (Anterolateral)',
        'right_thigh' => 'Right Thigh (Anterolateral)',
        'left_arm' => 'Left Arm (Deltoid)',
        'right_arm' => 'Right Arm (Deltoid)',
        'oral' => 'Oral',
    ];

    public const ADVERSE_EVENTS = [
        'none' => 'None',
        'mild' => 'Mild',
        'moderate' => 'Moderate',
        'severe' => 'Severe',
    ];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function schedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VaccinationSchedule::class, 'vaccine_master_id');
    }

    public function records(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VaccinationRecord::class, 'vaccine_master_id');
    }

    public function stocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VaccineStock::class, 'vaccine_master_id');
    }

    public function scopeForInstitute($q, int $instituteId)
    {
        return $q->where(function ($query) use ($instituteId) {
            $query->whereNull('institute_id')
                ->orWhere('institute_id', $instituteId);
        });
    }

    public function scopeByCategory($q, ?string $category)
    {
        return $category ? $q->where('category', $category) : $q;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function isEligibleForAge(?int $ageInDays): bool
    {
        if ($this->min_age_days !== null && $ageInDays !== null && $ageInDays < $this->min_age_days) {
            return false;
        }
        if ($this->max_age_days !== null && $ageInDays !== null && $ageInDays > $this->max_age_days) {
            return false;
        }
        return true;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function routeLabel(): string
    {
        return self::ROUTES[$this->route] ?? $this->route;
    }

    public function siteLabel(): string
    {
        return self::SITES[$this->site] ?? $this->site;
    }

    public function availableStock(int $instituteId): int
    {
        return $this->stocks()
            ->where('institute_id', $instituteId)
            ->where('status', 'available')
            ->sum('quantity_available');
    }
}
