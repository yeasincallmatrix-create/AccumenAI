<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\Medical\ClinicalAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use SoftDeletes;
    protected $table = 'medicines';

    protected $fillable = [
        'institute_id',
        'medicine_product_id',
        'code',
        'generic_name',
        'brand_name',
        'normalized_name',
        'category',
        'dosage_form',
        'strength',
        'unit',
        'pack_size',
        'purchase_price',
        'selling_price',
        'vat_percentage',
        'reorder_level',
        'reorder_quantity',
        'requires_prescription',
        'is_controlled',
        'dgda_code',
        'dgda_dar_number',
        'dgda_concept_id',
        'dgda_synced_at',
        'dgda_status',
        'side_effects',
        'contraindications',
        'storage_conditions',
        'is_active',
    ];

    protected $casts = [
        'pack_size' => 'integer',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'reorder_level' => 'integer',
        'reorder_quantity' => 'integer',
        'requires_prescription' => 'boolean',
        'is_controlled' => 'boolean',
        'is_active' => 'boolean',
        'dgda_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Sequential per-tenant code assignment. Only fills empty codes —
        // legacy MED- codes and existing numerics are never touched. The
        // reservation runs under a row lock (see MedicineCodeService), so
        // concurrent creates cannot receive the same code.
        static::creating(function (Medicine $medicine) {
            if (empty($medicine->code)) {
                $service = app(\App\Services\Medical\MedicineCodeService::class);
                $code = $service->reserveCode((int) $medicine->institute_id);

                if ($code === null) {
                    throw new \RuntimeException(
                        "Medicine code capacity exhausted for institute {$medicine->institute_id}. ".
                        'Maximum '.\App\Services\Medical\MedicineCodeService::MAX_CODE.' codes reached.'
                    );
                }

                $medicine->code = $code;
            }
        });

        static::saving(function (Medicine $medicine) {
            $base = $medicine->brand_name ?? '';
            $strength = $medicine->strength ?? '';
            $medicine->normalized_name = preg_replace(
                '/[^a-z0-9]/',
                '',
                strtolower(trim($base.' '.$strength))
            );
        });

        static::created(function (Medicine $medicine) {
            try {
                ClinicalAuditLog::record($medicine, 'created', [
                    'new' => ClinicalAuditLog::snapshot($medicine),
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Medicine audit log failed (created)', [
                    'medicine_id' => $medicine->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::updated(function (Medicine $medicine) {
            try {
                $changes = $medicine->getChanges();
                unset($changes['updated_at'], $changes['normalized_name']);

                if (empty($changes)) {
                    return;
                }

                $oldValues = array_intersect_key($medicine->getOriginal(), $changes);

                if (empty(array_diff_key($oldValues, array_flip(['normalized_name'])))) {
                    return;
                }

                ClinicalAuditLog::record($medicine, 'updated', [
                    'old' => $oldValues,
                    'new' => $changes,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Medicine audit log failed (updated)', [
                    'medicine_id' => $medicine->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * Phase 10 — compatibility link to the normalized product. Nullable
     * until mapped; all operational reads/writes keep using this row.
     */
    public function product()
    {
        return $this->belongsTo(MedicineProduct::class, 'medicine_product_id');
    }

    public function stocks()
    {
        return $this->hasMany(PharmacyStock::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeControlled($query)
    {
        return $query->where('is_controlled', true);
    }

    public function scopeOverTheCounter($query)
    {
        return $query->where('requires_prescription', false);
    }

    public function scopeRequiresPrescription($query)
    {
        return $query->where('requires_prescription', true);
    }

    public function scopeDgdaCoded($query)
    {
        return $query->whereNotNull('dgda_code')->where('dgda_code', '!=', '');
    }

    public function scopeDgdaPending($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('dgda_code')->orWhere('dgda_code', '');
        });
    }

    public function scopeOfForm($query, string $form)
    {
        return $query->where('dosage_form', $form);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('(
            SELECT COALESCE(SUM(current_quantity), 0) FROM pharmacy_stock
            WHERE pharmacy_stock.medicine_id = medicines.id
        ) <= medicines.reorder_level')
            ->where('reorder_level', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->whereRaw('(
            SELECT COALESCE(SUM(current_quantity), 0) FROM pharmacy_stock
            WHERE pharmacy_stock.medicine_id = medicines.id
        ) <= 0');
    }

    /**
     * Case-insensitive search using normalized_name for brand matching.
     */
    public function scopeSearch($query, $search)
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower(trim($search)));

        return $query->where(function ($q) use ($search, $normalized) {
            $q->where('generic_name', 'LIKE', "%{$search}%")
                ->orWhere('normalized_name', 'LIKE', "%{$normalized}%")
                ->orWhere('code', 'LIKE', "%{$search}%")
                ->orWhere('dgda_code', 'LIKE', "%{$search}%")
                ->orWhere('dgda_dar_number', 'LIKE', "%{$search}%");
        });
    }

    public function scopeForIndex($query)
    {
        return $query->active()->orderBy('brand_name');
    }

    public function getTotalStockAttribute()
    {
        return $this->stocks()->sum('current_quantity');
    }

    public function getAvailableStockAttribute()
    {
        return $this->stocks()
            ->where('expiry_date', '>', now())
            ->where('current_quantity', '>', 0)
            ->sum('current_quantity');
    }

    /**
     * Phase 3 addition.
     *
     * Whether this medicine can be dispensed without a prescription.
     */
    public function isOverTheCounter(): bool
    {
        return ! $this->requires_prescription;
    }

    /**
     * Phase 3 addition.
     *
     * Display name (generic + brand).
     */
    public function getDisplayNameAttribute(): string
    {
        $name = (string) $this->generic_name;
        if ($this->brand_name) {
            $name .= ' ('.$this->brand_name.')';
        }

        return $name;
    }
}
