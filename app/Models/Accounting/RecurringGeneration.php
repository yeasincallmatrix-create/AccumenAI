<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class RecurringGeneration extends Model
{
    protected $table = 'recurring_generations';

    protected $fillable = [
        'institute_id', 'template_id', 'scheduled_for', 'generated_at',
        'generated_type', 'generated_id', 'status', 'error_message', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'generated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public const STATUSES = ['success', 'failed', 'skipped'];

    public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RecurringTemplate::class, 'template_id');
    }
}
