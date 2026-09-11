<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 10 — controlled administration-route vocabulary (Oral,
 * Intravenous, …). Seeded; product links stay nullable until curated —
 * routes are never inferred from dosage forms in this phase.
 */
class MedicineRoute extends Model
{
    protected $fillable = ['name', 'status'];
}
