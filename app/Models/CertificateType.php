<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateType extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'certificate_types';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'certificate_type_id');
    }
}
