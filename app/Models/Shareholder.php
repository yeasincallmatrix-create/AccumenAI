<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shareholder extends Model
{
    use TenantScoped;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'face_value' => 'decimal:2',
            'share_percent' => 'decimal:2',
            'issued_at' => 'date',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
