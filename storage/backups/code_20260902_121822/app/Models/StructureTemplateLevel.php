<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StructureTemplateLevel extends Model
{
    protected $table = 'structure_template_levels';

    public $timestamps = true;

    protected $fillable = [
        'template_id',
        'level_order',
        'label',
        'label_key',
        'required',
        'has_values',
        'value_source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'level_order' => 'integer',
            'required' => 'boolean',
            'has_values' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(StructureTemplate::class, 'template_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(StructureNode::class, 'template_level_id');
    }
}
