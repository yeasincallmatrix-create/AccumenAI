<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrVacancy extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_vacancies';
    protected $guarded = [];
    public const STATUSES = ['draft','pending_approval','approved','published','closed','cancelled'];

    protected function casts(): array
    {
        return [
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function requisition(): BelongsTo { return $this->belongsTo(HrRequisition::class,'requisition_id'); }
    public function department(): BelongsTo { return $this->belongsTo(HrDepartment::class,'department_id'); }
    public function designation(): BelongsTo { return $this->belongsTo(HrDesignation::class,'designation_id'); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id'); }
    public function applications(): HasMany { return $this->hasMany(HrApplication::class,'vacancy_id'); }
}
