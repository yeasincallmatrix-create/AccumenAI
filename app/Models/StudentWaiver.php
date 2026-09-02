<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An approved education waiver (Step 37). Recorded against an invoice whose
 * discount was raised through the finance core — this row keeps the approval
 * provenance (who / when / why) and feeds the student ledger and the
 * discounts/waivers dashboard metric. It never moves money on its own.
 */
class StudentWaiver extends Model
{
    use TenantScoped;

    protected $table = 'student_waivers';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'waived_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Training\Enrollment::class, 'enrollment_id');
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'waived_by');
    }
}
