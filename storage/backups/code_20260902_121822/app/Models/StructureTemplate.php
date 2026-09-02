<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StructureTemplate extends Model
{
    protected $table = 'structure_templates';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_global',
        'institute_id',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'status' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(StructureTemplateLevel::class, 'template_id')
            ->orderBy('level_order')
            ->orderBy('id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(StructureNode::class, 'template_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(IndustryTemplateMapping::class, 'structure_template_id');
    }
}
