<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    use TenantScoped;

    protected $table = 'approval_requests';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:4',
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'request_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'requested_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'resolved_by');
    }
}
