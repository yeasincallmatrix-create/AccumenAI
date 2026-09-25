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
        return $this->belongsTo(TrainingStudent::class, 'student_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrainingBatch::class, 'batch_id');
    }

    /**
     * The certificate's course. training_certificates.course_id stores a
     * training_courses id (set from batch->course_id when generating),
     * so this must resolve against TrainingCourse — not the shared
     * education Course table — for name/subjects to be correct.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CertificateType::class, 'certificate_type_id');
    }

    public static function numberFor($certificate = null): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $length = 6;
        $tries = 0;
        $maxTries = 100;

        do {
            $number = '';
            for ($i = 0; $i < $length; $i++) {
                $number .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $tries++;
            if ($tries > $maxTries) {
                $number .= random_int(0, 9);
            }
        } while (self::where('certificate_number', $number)->exists());

        return $number;
    }
}
