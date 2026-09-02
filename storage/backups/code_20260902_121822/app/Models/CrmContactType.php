<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Multi-tenant shared catalog of contact types (seeded; no institute_id).
 */
class CrmContactType extends Model
{
    protected $table = 'crm_contact_types';

    protected $guarded = [];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }
}
