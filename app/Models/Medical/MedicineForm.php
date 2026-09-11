<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 10 — controlled dosage-form vocabulary (Tablet, Capsule, …).
 * Lookup is case-insensitive (utf8mb4_ci unique); unmapped legacy values
 * stay on the product display string and are flagged, never guessed.
 */
class MedicineForm extends Model
{
    protected $fillable = ['name', 'status'];
}
