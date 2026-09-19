<?php

namespace Database\Seeders;

use App\Models\Institute;
use App\Models\TaxGroup;
use Illuminate\Database\Seeder;

/**
 * Seeds the default Standard VAT (15%) tax group for one institute.
 * Idempotent (firstOrCreate on institute+name). No-op without institute.
 */
class TaxGroupSeeder extends Seeder
{
    public function __construct(
        protected ?int $instituteId = null,
        protected ?int $branchId = null,
    ) {}

    public function run(): ?TaxGroup
    {
        if ($this->instituteId === null) {
            return null;
        }

        if (Institute::whereKey($this->instituteId)->doesntExist()) {
            return null;
        }

        return TaxGroup::firstOrCreate(
            ['institute_id' => $this->instituteId, 'name' => 'Standard VAT'],
            [
                'branch_id' => $this->branchId,
                'type' => 'vat',
                'rate' => 15,
                'is_compound' => false,
                'is_default' => true,
                'is_active' => true,
            ]
        );
    }
}
