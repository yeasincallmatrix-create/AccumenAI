<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrRequisition extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_requisitions';
    protected $guarded = [];
    public const STATUSES = ['draft','pending_approval','approved','rejected','published','closed','cancelled'];

    protected function casts(): array
    {
        return [
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(HrDepartment::class,'department_id'); }
    public function designation(): BelongsTo { return $this->belongsTo(HrDesignation::class,'designation_id'); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(InstituteUser::class,'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(InstituteUser::class,'approved_by'); }
}
