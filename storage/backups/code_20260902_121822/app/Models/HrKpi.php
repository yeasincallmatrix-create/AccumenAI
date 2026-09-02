<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrKpi extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_kpis';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('display_order')->orderBy('name');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
