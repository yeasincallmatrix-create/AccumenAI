<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrSalaryStructureComponent extends Model
{
    use TenantScoped;

    protected $table = 'hr_salary_structure_components';
    protected $guarded = [];
    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'percent_base' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function structure(): BelongsTo { return $this->belongsTo(HrSalaryStructure::class, 'salary_structure_id'); }
}
