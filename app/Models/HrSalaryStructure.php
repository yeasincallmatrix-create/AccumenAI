<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrSalaryStructure extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_salary_structures';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'basic_salary' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'medical_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'other_allowance' => 'decimal:2',
            'overtime_rate' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'tax_deduction' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(HrDepartment::class, 'department_id'); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(InstituteUser::class, 'created_by'); }
    public function components(): HasMany { return $this->hasMany(HrSalaryStructureComponent::class, 'salary_structure_id')->orderBy('display_order')->orderBy('id'); }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeOrdered($q) { return $q->orderBy('name'); }

    public function netSalary(): float
    {
        return (float) $this->basic_salary
            + (float) $this->housing_allowance
            + (float) $this->medical_allowance
            + (float) $this->transport_allowance
            + (float) $this->other_allowance
            + (float) $this->bonus_amount
            + (float) $this->commission_amount
            - (float) $this->deduction_amount
            - (float) $this->tax_deduction;
    }
}
