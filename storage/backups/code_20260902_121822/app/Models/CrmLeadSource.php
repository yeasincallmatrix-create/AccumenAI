<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Multi-tenant shared catalog of lead acquisition sources (seeded; no institute_id).
 */
class CrmLeadSource extends Model
{
    protected $table = 'crm_lead_sources';

    protected $guarded = [];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'source_id');
    }
}
