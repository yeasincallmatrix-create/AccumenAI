<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPlacementNode extends Model
{
    protected $table = 'student_placement_nodes';

    public $timestamps = true;

    protected $fillable = [
        'student_academic_placement_id',
        'level_order',
        'node_id',
    ];

    protected function casts(): array
    {
        return [
            'level_order' => 'integer',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicPlacement::class, 'student_academic_placement_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(StructureNode::class, 'node_id');
    }
}
