<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An academic session/year owned by an institute (e.g. "2026"). Student
 * academic placements are tied to one year so each year's placement is
 * preserved historically.
 */
class AcademicYear extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'academic_years';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(StudentAcademicPlacement::class);
    }
}
