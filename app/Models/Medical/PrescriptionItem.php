<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    protected $table = 'prescription_items';

    protected $fillable = [
        'prescription_id',
        'medicine_id',
        'medicine_name',
        'dgda_code',
        // Phase 10 — immutable medicine-identity snapshots (written once at
        // item creation; never updated afterwards).
        'medicine_concept_id',
        'medicine_product_id',
        'display_name_snapshot',
        'generic_name_snapshot',
        'strength_snapshot',
        'dosage_form_snapshot',
        'unit_snapshot',
        'pack_size_snapshot',
        'category_snapshot',
        'route_snapshot',
        'rxnorm_code_snapshot',
        'dosage',
        'frequency',
        'duration_days',
        'quantity',
        'special_instructions',
        'status',
        'item_status',
        'discontinued_reason',
        'discontinued_at',
        'continued_from_item_id',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'quantity' => 'integer',
        'discontinued_at' => 'datetime',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class)->withTrashed();
    }

    public function dispenses()
    {
        return $this->hasMany(PharmacyDispense::class, 'prescription_item_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDispensed($query)
    {
        return $query->where('status', 'dispensed');
    }

    public function continuedFrom()
    {
        return $this->belongsTo(PrescriptionItem::class, 'continued_from_item_id');
    }

    public function continuations()
    {
        return $this->hasMany(PrescriptionItem::class, 'continued_from_item_id');
    }

    /**
     * Best-effort display name: snapshot → live medicine → fallback.
     */
    public function getMedicineDisplayNameAttribute(): string
    {
        return $this->display_name_snapshot
            ?? $this->medicine_name
            ?? $this->medicine?->display_name
            ?? 'Unknown';
    }

    /**
     * Composed full display string for print/export contexts.
     */
    public function getFullDisplayAttribute(): string
    {
        $parts = array_filter([
            $this->medicine_name,
            $this->strength_snapshot,
            $this->dosage_form_snapshot,
        ]);

        return implode(' ', $parts);
    }

    public function scopeActive($query)
    {
        return $query->where('item_status', 'active');
    }

    public function scopeDiscontinued($query)
    {
        return $query->where('item_status', 'discontinued');
    }

    public function isActive(): bool
    {
        return $this->item_status === 'active';
    }

    public function isDiscontinued(): bool
    {
        return $this->item_status === 'discontinued';
    }
}
