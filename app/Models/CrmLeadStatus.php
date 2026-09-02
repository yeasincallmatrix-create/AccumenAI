<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Multi-tenant shared catalog of lead pipeline statuses (seeded; no institute_id).
 */
class CrmLeadStatus extends Model
{
    protected $table = 'crm_lead_statuses';

    protected $guarded = [];

    public const SLUG_NEW = 'new';

    public const SLUG_CONTACTED = 'contacted';

    public const SLUG_QUALIFIED = 'qualified';

    public const SLUG_PROPOSAL = 'proposal';

    public const SLUG_WON = 'won';

    public const SLUG_LOST = 'lost';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'status_id');
    }
}
