<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;
use App\Models\User;
use App\Models\Medical\Patient;

class DentalProcedure extends Model
{
    use SoftDeletes;

    protected $table = 'dental_procedures';

    protected $fillable = [
        'institute_id', 'branch_id', 'procedure_number',
        'patient_id', 'dentist_id', 'appointment_id', 'dental_chart_id',
        'procedure_code', 'procedure_name', 'category',
        'tooth_number', 'tooth_surface', 'quadrant',
        'diagnosis', 'procedure_notes',
        'anesthesia_type', 'anesthesia_agent', 'anesthesia_volume_ml',
        'medications_prescribed', 'materials_used',
        'performed_at', 'duration_minutes',
        'follow_up_date', 'follow_up_instructions',
        'status', 'fee', 'payment_status',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'follow_up_date' => 'date',
        'anesthesia_volume_ml' => 'decimal:2',
        'duration_minutes' => 'integer',
        'fee' => 'decimal:2',
        'materials_used' => 'array',
    ];

    public const CATEGORIES = [
        'diagnostic' => 'Diagnostic',
        'preventive' => 'Preventive',
        'restorative' => 'Restorative',
        'endodontic' => 'Endodontic',
        'surgical' => 'Surgical',
        'orthodontic' => 'Orthodontic',
        'prosthetic' => 'Prosthetic',
        'cosmetic' => 'Cosmetic',
    ];

    public const STATUSES = [
        'planned' => 'Planned',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'followed_up' => 'Followed Up',
    ];

    public const ANESTHESIA_TYPES = [
        'local' => 'Local',
        'general' => 'General',
        'none' => 'None',
    ];

    public const TOOTH_SURFACES = [
        'mesial' => 'Mesial',
        'distal' => 'Distal',
        'occlusal' => 'Occlusal',
        'buccal' => 'Buccal',
        'lingual' => 'Lingual',
        'incisal' => 'Incisal',
    ];

    public const QUADRANTS = [
        'upper_right' => 'Upper Right',
        'upper_left' => 'Upper Left',
        'lower_right' => 'Lower Right',
        'lower_left' => 'Lower Left',
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

    public function dentalChart(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DentalChart::class, 'dental_chart_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('patient_id', $patientId);
    }

    public function scopeToday($q)
    {
        return $q->whereDate('performed_at', today());
    }

    public function scopeUpcomingFollowUps($q)
    {
        return $q->where('follow_up_date', '>=', today())
            ->where('status', '!=', 'followed_up')
            ->whereNotNull('follow_up_date');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'planned' => 'info',
            'in_progress' => 'warning',
            'completed' => 'success',
            'cancelled' => 'secondary',
            'followed_up' => 'primary',
            default => 'secondary',
        };
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function fullToothName(): ?string
    {
        if (! $this->tooth_number) {
            return null;
        }
        $quadrant = DentalService::quadrantFromTooth($this->tooth_number);
        $quadrantLabel = self::QUADRANTS[$quadrant] ?? $quadrant;
        return "{$this->tooth_number} ({$quadrantLabel})";
    }
}
