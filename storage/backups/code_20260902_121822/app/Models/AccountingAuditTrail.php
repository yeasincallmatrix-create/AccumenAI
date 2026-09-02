<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit log of every financial write. No updated_at, no soft
 * delete. Rows are inserted by the accounting services and never modified.
 */
class AccountingAuditTrail extends Model
{
    use TenantScoped;

    protected $table = 'accounting_audit_trails';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before_payload' => 'array',
            'after_payload' => 'array',
            'created_at' => 'datetime',
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
}
