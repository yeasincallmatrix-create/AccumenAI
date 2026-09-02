<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrInterview extends Model
{
    use TenantScoped;

    protected $table = 'hr_interviews';
    protected $guarded = [];
    public const TYPES = ['onsite','online','phone','panel'];
    public const STATUSES = ['scheduled','completed','cancelled','no_show'];
    public const RECOMMENDATIONS = ['hire','reject','hold','pending'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    public function application(): BelongsTo { return $this->belongsTo(HrApplication::class,'application_id'); }
    public function vacancy(): BelongsTo { return $this->belongsTo(HrVacancy::class,'vacancy_id'); }
    public function candidateLead(): BelongsTo { return $this->belongsTo(CrmLead::class,'candidate_lead_id'); }
    public function interviewer(): BelongsTo { return $this->belongsTo(InstituteUser::class,'interviewer_id'); }
}
