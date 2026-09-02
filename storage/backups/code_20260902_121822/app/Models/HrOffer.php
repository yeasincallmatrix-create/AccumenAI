<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrOffer extends Model
{
    use TenantScoped;

    protected $table = 'hr_offers';
    protected $guarded = [];
    public const STATUSES = ['draft','sent','accepted','rejected','withdrawn','expired'];

    protected function casts(): array
    {
        return [
            'offer_date' => 'date',
            'expiry_date' => 'date',
            'joining_date' => 'date',
            'offered_salary' => 'decimal:2',
        ];
    }

    public function application(): BelongsTo { return $this->belongsTo(HrApplication::class,'application_id'); }
    public function candidateLead(): BelongsTo { return $this->belongsTo(CrmLead::class,'candidate_lead_id'); }
    public function designation(): BelongsTo { return $this->belongsTo(HrDesignation::class,'proposed_designation_id'); }
    public function department(): BelongsTo { return $this->belongsTo(HrDepartment::class,'proposed_department_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class,'proposed_branch_id'); }
}
