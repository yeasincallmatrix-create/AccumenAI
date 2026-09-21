<?php

namespace App\Models\Accounting;

use App\Models\Concerns\TenantScoped;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringTemplate extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'recurring_templates';

    protected $fillable = [
        'institute_id', 'branch_id', 'template_number', 'name',
        'transaction_type', 'frequency', 'interval_count', 'custom_cron',
        'start_date', 'end_date', 'max_occurrences', 'occurrences_generated',
        'next_run_at', 'last_generated_at', 'auto_post',
        'status', 'consecutive_failures', 'last_error',
        'template_data', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_at' => 'datetime',
            'last_generated_at' => 'datetime',
            'auto_post' => 'boolean',
            'template_data' => 'array',
            'interval_count' => 'integer',
            'max_occurrences' => 'integer',
            'occurrences_generated' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }

    public const TRANSACTION_TYPES = ['journal_entry', 'invoice', 'vendor_bill', 'expense', 'payment'];
    public const FREQUENCIES = ['daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'semiannual', 'annual', 'custom'];
    public const STATUSES = ['active', 'paused', 'completed', 'cancelled', 'failed'];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Institute::class);
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function generations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RecurringGeneration::class, 'template_id');
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\InstituteUser::class, 'created_by');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeForInstitute($q, $id)
    {
        return $q->where('institute_id', $id);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isDue(): bool
    {
        return $this->isActive() && $this->next_run_at && $this->next_run_at->isPast();
    }

    public function canGenerate(): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        if ($this->end_date && $this->next_run_at->isAfter($this->end_date->endOfDay())) {
            return false;
        }
        if ($this->max_occurrences && $this->occurrences_generated >= $this->max_occurrences) {
            return false;
        }

        return true;
    }

    public function computeNextRun(?Carbon $from = null): ?Carbon
    {
        $from = $from ?? $this->next_run_at ?? Carbon::now();

        $next = match ($this->frequency) {
            'daily' => $from->copy()->addDays($this->interval_count),
            'weekly' => $from->copy()->addWeeks($this->interval_count),
            'biweekly' => $from->copy()->addWeeks(2 * $this->interval_count),
            'monthly' => $from->copy()->addMonthsNoOverflow($this->interval_count),
            'quarterly' => $from->copy()->addMonthsNoOverflow(3 * $this->interval_count),
            'semiannual' => $from->copy()->addMonthsNoOverflow(6 * $this->interval_count),
            'annual' => $from->copy()->addYears($this->interval_count),
            'custom' => $this->computeNextFromCron($from),
            default => null,
        };

        return $next;
    }

    protected function computeNextFromCron(Carbon $from): ?Carbon
    {
        if (!$this->custom_cron) {
            return null;
        }

        try {
            $cron = new \Cron\CronExpression($this->custom_cron);

            return Carbon::instance($cron->getNextRunDate($from->toDateTime()));
        } catch (\Throwable $e) {
            \Log::error('Invalid cron expression: ' . $this->custom_cron);

            return null;
        }
    }

    public function previewOccurrences(int $count = 5): array
    {
        $occurrences = [];
        $cursor = $this->next_run_at?->copy() ?? Carbon::now();

        for ($i = 0; $i < $count; $i++) {
            if ($this->end_date && $cursor->isAfter($this->end_date->endOfDay())) {
                break;
            }
            if ($this->max_occurrences && ($this->occurrences_generated + $i) >= $this->max_occurrences) {
                break;
            }

            $occurrences[] = $cursor->copy();
            $cursor = $this->computeNextRun($cursor);
            if (!$cursor) {
                break;
            }
        }

        return $occurrences;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'paused' => 'warning',
            'completed' => 'primary',
            'cancelled' => 'secondary',
            'failed' => 'danger',
            default => 'secondary',
        };
    }
}
