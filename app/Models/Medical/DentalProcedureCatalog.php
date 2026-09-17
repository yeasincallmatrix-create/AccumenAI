<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use App\Models\Institute;

class DentalProcedureCatalog extends Model
{
    protected $table = 'dental_procedure_catalog';

    protected $fillable = [
        'institute_id', 'code', 'name', 'category',
        'body_site', 'description', 'default_fee',
        'default_duration_minutes', 'is_active',
    ];

    protected $casts = [
        'default_fee' => 'decimal:2',
        'default_duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public const CATEGORIES = [
        'diagnostic' => 'Diagnostic',
        'preventive' => 'Preventive',
        'restorative' => 'Restorative',
        'endodontic' => 'Endodontic',
        'surgical' => 'Surgical',
        'orthodontic' => 'Orthodontic',
        'prosthetic' => 'Prosthetic',
        'cosmetic' => 'Cosmetic',
    ];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * Global (institute_id NULL) + tenant-specific entries.
     */
    public function scopeForInstitute($q, int $instituteId)
    {
        return $q->where(function ($query) use ($instituteId) {
            $query->whereNull('institute_id')
                ->orWhere('institute_id', $instituteId);
        });
    }

    public function scopeByCategory($q, ?string $category)
    {
        return $category ? $q->where('category', $category) : $q;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
