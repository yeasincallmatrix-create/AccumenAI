<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $table = 'import_batches';

    protected $guarded = [];

    protected $casts = [
        'errors' => 'array',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(ImportBatchRow::class);
    }
}
