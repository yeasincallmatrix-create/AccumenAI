<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partner extends Model
{
    use TenantScoped;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'capital' => 'decimal:2',
            'share_percent' => 'decimal:2',
            'joined_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
