<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingCertificate extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_certificates';

    protected $fillable = [
        'uuid', 'institute_id', 'student_id', 'course_id', 'batch_id', 'certificate_type_id',
        'template_id', 'result_id', 'certificate_number', 'issue_date', 'qr_code_path',
        'verification_url', 'digital_signature', 'status', 'revoked_reason', 'issued_by',
        'reviewed_by', 'review_note',
    ];

    protected $casts = [
        'uuid' => 'uuid', 'issue_date' => 'date', 'reviewed_at' => 'datetime',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(TrainingStudent::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrainingBatch::class);
    }
}
