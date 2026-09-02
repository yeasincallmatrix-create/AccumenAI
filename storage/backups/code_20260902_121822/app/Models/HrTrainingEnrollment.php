<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrTrainingEnrollment extends Model
{
    use TenantScoped;

    protected $table = 'hr_training_enrollments';

    protected $guarded = [];

    public const STATUSES = ['enrolled', 'attending', 'completed', 'dropped', 'cancelled'];

    public const RESULTS = ['pass', 'fail', 'pending'];

    protected function casts(): array
    {
        return ['enrollment_date' => 'date', 'completion_date' => 'date', 'score' => 'decimal:2'];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(HrTraining::class, 'training_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
