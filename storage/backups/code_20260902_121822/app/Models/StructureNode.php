<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StructureNode extends Model
{
    use TenantScoped;

    protected $table = 'structure_nodes';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'template_id',
        'template_level_id',
        'parent_node_id',
        'level_order',
        'name',
        'code',
        'display_order',
        'status',
        'is_custom',
        'branch_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'level_order' => 'integer',
            'display_order' => 'integer',
            'status' => 'boolean',
            'is_custom' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(StructureTemplate::class, 'template_id');
    }

    public function templateLevel(): BelongsTo
    {
        return $this->belongsTo(StructureTemplateLevel::class, 'template_level_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_node_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_node_id')->orderBy('display_order')->orderBy('id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
