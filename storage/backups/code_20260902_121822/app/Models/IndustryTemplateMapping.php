<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndustryTemplateMapping extends Model
{
    protected $table = 'industry_template_mappings';

    public $timestamps = true;

    protected $fillable = [
        'industry',
        'sub_industry',
        'country_id',
        'structure_template_id',
        'priority',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'status' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(StructureTemplate::class, 'structure_template_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
