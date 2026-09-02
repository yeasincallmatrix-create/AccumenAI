<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StructureLabel extends Model
{
    protected $table = 'structure_label_dictionary';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'code',
        'category',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
