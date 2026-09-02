<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrApplication extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_applications';
    protected $guarded = [];
    public const STAGES = ['new','screening','shortlisted','interview','assessment','selected','rejected','hired','withdrawn'];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
        ];
    }

    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function vacancy(): BelongsTo { return $this->belongsTo(HrVacancy::class,'vacancy_id'); }
    public function candidateLead(): BelongsTo { return $this->belongsTo(CrmLead::class,'candidate_lead_id'); }
    public function candidateContact(): BelongsTo { return $this->belongsTo(CrmContact::class,'candidate_contact_id'); }
    public function hiredEmployee(): BelongsTo { return $this->belongsTo(HrEmployee::class,'hired_employee_id'); }
    public function recruiter(): BelongsTo { return $this->belongsTo(InstituteUser::class,'assigned_recruiter_id'); }
    public function source(): BelongsTo { return $this->belongsTo(CrmLeadSource::class,'source_id'); }
    public function histories(): HasMany { return $this->hasMany(HrApplicationHistory::class,'application_id')->orderBy('created_at'); }
    public function interviews(): HasMany { return $this->hasMany(HrInterview::class,'application_id'); }
    public function offer(): BelongsTo { return $this->belongsTo(HrOffer::class,'id','application_id'); } // hasOne via application_id unique
    public function getOffer(): ?HrOffer { return HrOffer::where('application_id',$this->id)->first(); }
}
