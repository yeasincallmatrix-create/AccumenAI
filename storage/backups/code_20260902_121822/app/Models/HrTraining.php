<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrTraining extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_trainings';

    protected $guarded = [];

    public const STATUSES = ['draft', 'planned', 'ongoing', 'completed', 'cancelled'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_online' => 'boolean', 'cost' => 'decimal:2'];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(HrTrainingEnrollment::class, 'training_id');
    }
}
