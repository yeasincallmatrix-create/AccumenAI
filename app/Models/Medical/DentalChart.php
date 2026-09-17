<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;
use App\Models\User;
use App\Models\Medical\Patient;

class DentalChart extends Model
{
    use SoftDeletes;

    protected $table = 'dental_charts';

    protected $fillable = [
        'institute_id', 'branch_id', 'patient_id', 'dentist_id',
        'tooth_conditions', 'total_teeth', 'caries_count', 'filled_count',
        'missing_count', 'crown_count', 'rct_count',
        'general_notes', 'oral_hygiene', 'last_assessed_at', 'assessed_by',
    ];

    protected $casts = [
        'tooth_conditions' => 'array',
        'last_assessed_at' => 'datetime',
        'total_teeth' => 'integer',
        'caries_count' => 'integer',
        'filled_count' => 'integer',
        'missing_count' => 'integer',
        'crown_count' => 'integer',
        'rct_count' => 'integer',
    ];

    public const TOOTH_NUMBERS = [
        '11','12','13','14','15','16','17','18',
        '21','22','23','24','25','26','27','28',
        '31','32','33','34','35','36','37','38',
        '41','42','43','44','45','46','47','48',
    ];

    public const TOOTH_CONDITIONS = [
        'healthy' => 'Healthy',
        'caries' => 'Caries',
        'filled' => 'Filled',
        'crown' => 'Crown',
        'rct' => 'Root Canal Treated',
        'missing' => 'Missing',
        'impacted' => 'Impacted',
        'fractured' => 'Fractured',
        'mobile' => 'Mobile',
        'sensitive' => 'Sensitive',
    ];

    public const ORAL_HYGIENE = [
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor',
    ];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function dentist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_id');
    }

    public function assessedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function procedures(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DentalProcedure::class, 'dental_chart_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function getToothCondition(string $toothNumber): array
    {
        $conditions = $this->tooth_conditions ?? [];
        return $conditions[$toothNumber] ?? ['condition' => 'healthy'];
    }

    public function recalculateCounts(): void
    {
        $conditions = $this->tooth_conditions ?? [];
        $counts = ['caries' => 0, 'filled' => 0, 'missing' => 0, 'crown' => 0, 'rct' => 0];
        foreach ($conditions as $data) {
            $cond = $data['condition'] ?? null;
            if (isset($counts[$cond])) {
                $counts[$cond]++;
            }
        }
        $this->update([
            'caries_count' => $counts['caries'],
            'filled_count' => $counts['filled'],
            'missing_count' => $counts['missing'],
            'crown_count' => $counts['crown'],
            'rct_count' => $counts['rct'],
        ]);
    }

    public function conditionColor(string $toothNumber): string
    {
        $data = $this->getToothCondition($toothNumber);
        return match ($data['condition'] ?? 'healthy') {
            'healthy' => '#e5e7eb',
            'caries' => '#ef4444',
            'filled' => '#3b82f6',
            'crown' => '#f59e0b',
            'rct' => '#8b5cf6',
            'missing' => '#9ca3af',
            'impacted' => '#f97316',
            'fractured' => '#dc2626',
            'mobile' => '#6b7280',
            'sensitive' => '#06b6d4',
            default => '#e5e7eb',
        };
    }
}
