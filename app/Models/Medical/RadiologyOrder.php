<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;
use App\Models\User;
use App\Models\Medical\Patient;

class RadiologyOrder extends Model
{
    use SoftDeletes;

    protected $table = 'radiology_orders';

    protected $fillable = [
        'institute_id', 'branch_id', 'order_number',
        'patient_id', 'doctor_id', 'appointment_id',
        'modality', 'body_part', 'laterality', 'clinical_indication',
        'is_contrast', 'contrast_type', 'is_urgent', 'is_fasting_required',
        'status', 'scheduled_at', 'performed_at', 'performed_by',
        'technique', 'findings', 'impression', 'recommendations',
        'radiologist_name', 'radiologist_id', 'reported_at', 'verified_at', 'verified_by',
        'fee', 'payment_status', 'invoice_id',
    ];

    protected $casts = [
        'is_contrast' => 'boolean',
        'is_urgent' => 'boolean',
        'is_fasting_required' => 'boolean',
        'scheduled_at' => 'datetime',
        'performed_at' => 'datetime',
        'reported_at' => 'datetime',
        'verified_at' => 'datetime',
        'fee' => 'decimal:2',
    ];

    public const MODALITIES = [
        'X-Ray' => 'X-Ray',
        'CT' => 'CT Scan',
        'MRI' => 'MRI',
        'USG' => 'Ultrasound',
        'Mammography' => 'Mammography',
        'Fluoroscopy' => 'Fluoroscopy',
        'PET' => 'PET Scan',
    ];

    public const STATUSES = [
        'ordered' => 'Ordered',
        'scheduled' => 'Scheduled',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'reported' => 'Reported',
        'cancelled' => 'Cancelled',
    ];

    public const BODY_PARTS = [
        'Head', 'Neck', 'Chest', 'Abdomen', 'Pelvis',
        'Spine - Cervical', 'Spine - Thoracic', 'Spine - Lumbar',
        'Upper Limb', 'Lower Limb', 'Knee', 'Shoulder', 'Hip',
        'Whole Body', 'Other',
    ];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function radiologist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'radiologist_id');
    }

    public function performedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function verifiedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RadiologyImage::class, 'radiology_order_id');
    }

    public function appointment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function scopeOrdered($q)
    {
        return $q->where('status', 'ordered');
    }

    public function scopeScheduled($q)
    {
        return $q->where('status', 'scheduled');
    }

    public function scopeInProgress($q)
    {
        return $q->where('status', 'in_progress');
    }

    public function scopeCompleted($q)
    {
        return $q->where('status', 'completed');
    }

    public function scopeReported($q)
    {
        return $q->where('status', 'reported');
    }

    public function scopeUrgent($q)
    {
        return $q->where('is_urgent', true);
    }

    public function scopePending($q)
    {
        return $q->whereIn('status', ['ordered', 'scheduled', 'in_progress']);
    }

    public function scopeToday($q)
    {
        return $q->whereDate('scheduled_at', today());
    }

    public function scopeModality($q, string $modality)
    {
        return $q->where('modality', $modality);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['ordered', 'scheduled', 'in_progress']);
    }

    public function isReported(): bool
    {
        return $this->status === 'reported';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'ordered' => 'secondary',
            'scheduled' => 'info',
            'in_progress' => 'warning',
            'completed' => 'primary',
            'reported' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    public function modalityLabel(): string
    {
        return self::MODALITIES[$this->modality] ?? $this->modality;
    }

    public function fullStudyName(): string
    {
        $parts = array_filter([
            $this->modalityLabel(),
            $this->body_part,
            $this->laterality ? '(' . ucfirst($this->laterality) . ')' : null,
        ]);

        return implode(' — ', $parts);
    }
}
