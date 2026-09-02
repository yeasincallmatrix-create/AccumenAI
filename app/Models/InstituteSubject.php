<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstituteSubject extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'institute_subjects';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_custom' => 'boolean',
            'minimum_selection' => 'integer',
            'maximum_selection' => 'integer',
        ];
    }

    /**
     * true when this row carries a visible override (name and/or display_order) —
     * unused by the resolver (status alone marks customized), kept for UI hints.
     */
    public function hasVisibleOverride(): bool
    {
        return filled($this->name) || filled($this->display_order);
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function selectionGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicSelectionGroup::class, 'selection_group_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'assigned_by');
    }
}
