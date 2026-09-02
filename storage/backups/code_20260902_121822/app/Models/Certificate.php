<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model
{
    use Concerns\TenantScoped, SoftDeletes;

    protected $table = 'certificates';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'issue_date' => 'date',
        'reviewed_at' => 'datetime',
        'certificate_type_id' => 'integer',
        'template_id' => 'integer',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class, 'certificate_type_id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'reviewed_by');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'issued_by');
    }

    public static function numberFor($certificate = null): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $length = 6;
        $tries = 0;
        $maxTries = 1000;

        do {
            $number = '';
            for ($i = 0; $i < $length; $i++) {
                $number .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $tries++;
            if ($tries > $maxTries) {
                // Extremely unlikely fallback: add a random digit at the end to avoid infinite loop
                $number .= random_int(0, 9);
            }
        } while (self::where('certificate_number', $number)->exists());

        return $number;
    }
}
