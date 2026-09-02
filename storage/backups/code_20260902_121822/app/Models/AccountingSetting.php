<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Accounting configuration key/value (JSON) scoped per institute. branch_id
 * NULL = institute-wide setting. Keys: base_currency, coa_template, ar_ap_mode,
 * statement_lock, money_precision, invoice_auto_post, fiscal_year_start.
 */
class AccountingSetting extends Model
{
    use BranchScopedOrShared;
    use TenantScoped;

    protected $table = 'accounting_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings_value' => 'array',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }
}
